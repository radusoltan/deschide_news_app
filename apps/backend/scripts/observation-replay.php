<?php

declare(strict_types=1);

/**
 * Sprint 58 — Observation replay script.
 *
 * Injects SourceSignals from YAML into the editorial pipeline so the
 * stabilization scheduler picks them up organically on its next tick.
 *
 * Usage:
 *   cd /var/www/deschide_news_app/apps/backend
 *
 *   # Persist signals (idempotent — skips duplicates by content hash)
 *   symfony php scripts/observation-replay.php scripts/observation-signals.yaml
 *
 *   # Parse + validate + construct entities in memory only (no persist, no
 *   # flush, zero DB mutation). Precheck, hash computation, in-set hash
 *   # uniqueness, and existsByHash lookup still run. Intended for Step 1
 *   # pre-flight check before supervised 2h run.
 *   symfony php scripts/observation-replay.php scripts/observation-signals.yaml --dry-parse
 *
 *   # Delete all observation signals from a prior run (identified by
 *   # raw_payload._obs_id key).
 *   symfony php scripts/observation-replay.php --cleanup
 *
 * Exit codes:
 *   0 — success (persist, dry-parse, or cleanup)
 *   1 — usage error (no YAML arg and no --cleanup flag)
 *   2 — precheck failed (required VerifiedSource slugs missing)
 *   3 — dry-parse detected an anomaly (hash collision, construction error,
 *       or dedup count mismatch); do NOT flip to real run until resolved
 *
 * Design notes:
 *   - ContentHasher has no constructor deps, so we instantiate it directly
 *     rather than pulling a non-public service from the container.
 *   - Hash payload matches AbstractRssMonitor::buildSignalIfNew exactly
 *     (title \n canonical_url \n description) so dedup behaves identically
 *     to organic ingestion.
 *   - rawPayload merges the canonical RSS monitor shape (title/url/...)
 *     with underscore-prefixed observation keys (_obs_id/_cat6_primed/
 *     _observation_run) so downstream consumers reading canonical keys
 *     are unaffected.
 *   - Precheck fails fast if any referenced VerifiedSource slug is absent;
 *     no auto-seed fallback (rejected per decision D1, 2026-04-23).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Kernel;
use App\Message\Editorial\SignalIngestedMessage;
use App\Service\ContentHasher;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Yaml\Yaml;

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$container = $kernel->getContainer();
/** @var \Doctrine\Persistence\ManagerRegistry $doctrine */
$doctrine = $container->get('doctrine');
/** @var \Doctrine\ORM\EntityManagerInterface $em */
$em = $doctrine->getManager();

$vsRepo = $em->getRepository(VerifiedSource::class);
/** @var \App\Repository\Editorial\SourceSignalRepository $signalRepo */
$signalRepo = $em->getRepository(SourceSignal::class);
/** @var MessageBusInterface $messageBus */
$messageBus = $container->get('messenger.default_bus');

$args = array_slice($argv, 1);

// --- Cleanup mode ---------------------------------------------------------
if (in_array('--cleanup', $args, true)) {
    $conn = $em->getConnection();
    $deleted = $conn->executeStatement(
        "DELETE FROM source_signals WHERE raw_payload::jsonb ? '_obs_id'"
    );
    printf("Cleanup: deleted %d observation signal(s).\n", $deleted);
    exit(0);
}

// --- Persist / dry-parse mode --------------------------------------------
$dryParse = in_array('--dry-parse', $args, true);
$positional = array_values(array_filter($args, static fn ($a) => !str_starts_with($a, '--')));
$yamlPath = $positional[0] ?? null;
if ($yamlPath === null || !is_file($yamlPath)) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  symfony php scripts/observation-replay.php <signals.yaml>\n");
    fwrite(STDERR, "  symfony php scripts/observation-replay.php <signals.yaml> --dry-parse\n");
    fwrite(STDERR, "  symfony php scripts/observation-replay.php --cleanup\n");
    exit(1);
}

$config = Yaml::parseFile($yamlPath);
$signals = $config['signals'] ?? [];
if ($signals === []) {
    fwrite(STDERR, "No 'signals' entries found in {$yamlPath}\n");
    exit(1);
}

// Precheck: every referenced VerifiedSource must already exist.
$requiredSlugs = array_values(array_unique(array_column($signals, 'source_slug')));
$sourceBySlug = [];
$missing = [];
foreach ($requiredSlugs as $slug) {
    $vs = $vsRepo->findOneBy(['slug' => $slug]);
    if ($vs === null) {
        $missing[] = $slug;
    } else {
        $sourceBySlug[$slug] = $vs;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Precheck FAILED. Missing VerifiedSource slugs: " . implode(', ', $missing) . "\n");
    fwrite(STDERR, "Seed them via VerifiedSourceFixture or a dedicated command before retrying.\n");
    exit(2);
}

$hasher = new ContentHasher();

$planned = [];     // obs_ids that would be persisted (or were persisted)
$persistedSignals = []; // obs_id => SourceSignal (real run only) — for post-flush dispatch
$skippedDup = [];  // already present in DB via existsByHash
$skippedErr = []; // [obs_id, reason] — construction failure
$hashCollisions = []; // [obs_id, other_obs_id_with_same_hash]
$hashByPair = [];  // "<vs_id>:<hash>" => obs_id — in-set uniqueness tracker
$perSlug = array_fill_keys(array_keys($sourceBySlug), 0);

foreach ($signals as $def) {
    $slug = $def['source_slug'];
    $vs = $sourceBySlug[$slug];

    // Hash payload matches AbstractRssMonitor::buildSignalIfNew (line 230-232).
    // source_url doubles as the canonical URL for synthetic fixture entries —
    // no normalization is meaningful on example.local placeholders.
    $canonical = $def['source_url'];
    $hash = $hasher->hash(
        $def['title'] . "\n" . $canonical . "\n" . ($def['raw_summary'] ?? '')
    );

    // In-set uniqueness: would the batch itself produce a uniq-constraint violation?
    $pairKey = $vs->getId() . ':' . $hash;
    if (isset($hashByPair[$pairKey])) {
        $hashCollisions[] = [$def['obs_id'], $hashByPair[$pairKey]];
        continue;
    }
    $hashByPair[$pairKey] = $def['obs_id'];

    if ($signalRepo->existsByHash($vs, $hash)) {
        $skippedDup[] = $def['obs_id'];
        continue;
    }

    try {
        $signal = new SourceSignal(
            verifiedSource: $vs,
            sourceUrl: $def['source_url'],
            title: $def['title'],
            rawContentHash: $hash,
        );
        $signal->setCanonicalUrl($canonical);
        $signal->setRawSummary($def['raw_summary'] ?? null);
        $signal->setPublishedAt(new \DateTimeImmutable());
        $signal->setRawPayload([
            // Canonical AbstractRssMonitor shape — keep downstream readers happy.
            'title'        => $def['title'],
            'url'          => $def['source_url'],
            'description'  => $def['raw_summary'] ?? null,
            'image_url'    => null,
            'published_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'source_name'  => $vs->getName(),
            'language'     => 'ro',
            // Observation-only keys. Underscore prefix marks them as non-canonical.
            '_obs_id'          => $def['obs_id'],
            '_cat6_primed'     => (bool) ($def['cat6_primed'] ?? false),
            '_observation_run' => '2026-04-23',
        ]);

        if (!$dryParse) {
            $em->persist($signal);
            $persistedSignals[$def['obs_id']] = $signal;
        }
        $planned[] = $def['obs_id'];
        ++$perSlug[$slug];
    } catch (\Throwable $e) {
        $skippedErr[] = [$def['obs_id'], $e->getMessage()];
    }
}

$dispatched = 0;
$dispatchFailed = []; // obs_ids where $signal->getId() was null post-flush

if (!$dryParse) {
    $em->flush();

    // Dispatch SignalIngestedMessage per persisted signal so the
    // editorial pipeline picks them up organically (mirrors
    // AbstractRssMonitor::flushAndDispatch). Without this, signals land
    // in Postgres but never enter the Redis stabilization buffer.
    foreach ($persistedSignals as $obsId => $signal) {
        $id = $signal->getId();
        if ($id === null) {
            $dispatchFailed[] = $obsId;
            continue;
        }
        $messageBus->dispatch(new SignalIngestedMessage(sourceSignalId: $id));
        ++$dispatched;
    }
}

// --- Report ---------------------------------------------------------------
$total = count($signals);
$verb = $dryParse ? 'Would persist' : 'Persisted';
$mode = $dryParse ? '[DRY-PARSE] ' : '';

printf("%s%s: %d / %d signal(s)\n", $mode, $verb, count($planned), $total);
foreach ($planned as $id) {
    echo "  + $id\n";
}

if ($skippedDup !== []) {
    printf("\n%sSkipped (duplicate content hash — already in DB): %d\n", $mode, count($skippedDup));
    foreach ($skippedDup as $id) {
        echo "  = $id\n";
    }
}

if ($hashCollisions !== []) {
    printf("\n%sIn-set hash collisions (would violate uniq constraint): %d\n", $mode, count($hashCollisions));
    foreach ($hashCollisions as [$id, $firstSeenAs]) {
        echo "  ✕ $id — same (vs_id, hash) as $firstSeenAs\n";
    }
}

if ($skippedErr !== []) {
    printf("\n%sConstruction errors: %d\n", $mode, count($skippedErr));
    foreach ($skippedErr as [$id, $why]) {
        echo "  ! $id — $why\n";
    }
}

if (!$dryParse) {
    printf("\nDispatched: %d / %d SignalIngestedMessage\n", $dispatched, count($persistedSignals));
    if ($dispatchFailed !== []) {
        printf("Dispatch failed (null ID post-flush): %s\n", implode(', ', $dispatchFailed));
    }
}

printf("\n%sPer-slug distribution:\n", $mode);
foreach ($perSlug as $slug => $count) {
    $vs = $sourceBySlug[$slug];
    printf(
        "  %-14s %s (trust=%s)  → %d %s\n",
        $slug,
        $vs->getEditorialAlignment()->value,
        $vs->getTrustScoreBaseline(),
        $count,
        $dryParse ? 'planned' : 'persisted',
    );
}

// --- Dry-parse verdict ---------------------------------------------------
if ($dryParse) {
    $anomaly = $hashCollisions !== [] || $skippedErr !== [];
    echo "\n";
    if ($anomaly) {
        printf(
            "[DRY-PARSE] ANOMALY — %d collision(s), %d construction error(s). Do NOT flip to real run.\n",
            count($hashCollisions),
            count($skippedErr),
        );
        exit(3);
    }
    printf(
        "[DRY-PARSE] OK — %d would persist, %d would skip (existing dedup), 0 errors. Ready for real run.\n",
        count($planned),
        count($skippedDup),
    );
    exit(0);
}
