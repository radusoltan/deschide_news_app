<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Escalation;

use App\Enum\Editorial\EscalationCategory;
use App\Service\Editorial\Escalation\EscalationClassifier;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Empirical-validation test for EscalationClassifier (Sprint 55 T55.8, ADR-020 D7).
 *
 * Runs the real classifier (LLM-backed) against the 51-claim dataset shipped
 * in T55.15 (50 claims) + T55.18 revision (+1 cat3_negative_1 to close false-
 * positive gap on NBC category) and enforces the audit-locked acceptance bar:
 * false-negative rate must be strictly less than {@see self::FALSE_NEGATIVE_RATE_CEILING}.
 * False POSITIVES are logged for Radu's follow-up but do NOT fail the run
 * (audit D18 weaker acceptance bar on positives).
 *
 * NOT run by default — makes real LLM calls and is slow + costly. Invoke
 * with:
 *
 *     RUN_EMPIRICAL_ESCALATION=1 vendor/bin/phpunit \
 *         --filter=test51ClaimAcceptanceBar
 *
 * Or via the group:
 *
 *     RUN_EMPIRICAL_ESCALATION=1 vendor/bin/phpunit \
 *         --group=empirical-escalation
 *
 * The dataset sanity tests (shape + distribution) run on every CI run.
 */
#[Group('empirical-escalation')]
final class EscalationClassifierEmpiricalTest extends KernelTestCase
{
    /** Audit D18 / T55.15 locked bar — NEVER slacken to accommodate model drift. */
    private const FALSE_NEGATIVE_RATE_CEILING = 0.05;

    public function test51ClaimAcceptanceBar(): void
    {
        if (getenv('RUN_EMPIRICAL_ESCALATION') !== '1') {
            self::markTestSkipped(
                'Empirical classifier benchmark — set RUN_EMPIRICAL_ESCALATION=1 to invoke. '
                    . 'Makes real LLM calls on 51 claims (slow + costly).',
            );
        }

        self::bootKernel();
        $classifier = static::getContainer()->get(EscalationClassifier::class);
        self::assertInstanceOf(EscalationClassifier::class, $classifier);

        $dataset = require __DIR__ . '/../../../../fixtures/editorial/escalation-classifier-dataset.php';
        self::assertCount(51, $dataset);

        $falseNegatives = [];
        $falsePositives = [];
        $categoryMisses = [];

        foreach ($dataset as $entry) {
            $result = $classifier->classify(
                claimText: $entry['title'] . "\n\n" . $entry['summary'],
                primarySourceTitle: $entry['title'],
                topic: null,
            );

            $classifierEscalated = $result !== null;
            $expectedEscalation = (bool) $entry['expected_is_escalation'];
            $expectedCategory = (string) $entry['expected_category'];

            if ($expectedEscalation && !$classifierEscalated) {
                $falseNegatives[] = $entry['id'];

                continue;
            }
            if (!$expectedEscalation && $classifierEscalated) {
                $falsePositives[] = $entry['id'];

                continue;
            }
            // At this point: either both expected=true AND classifier=true (compare category),
            // or both expected=false AND classifier=false (nothing to check).
            if ($result === null) {
                continue;
            }
            if ($expectedEscalation && $result->name !== $expectedCategory) {
                $categoryMisses[] = sprintf('%s: expected=%s got=%s', $entry['id'], $expectedCategory, $result->name);
            }
        }

        $fnRate = count($falseNegatives) / count($dataset);

        // Log the full diagnostic to stdout so --verbose captures it on CI.
        fwrite(
            STDOUT,
            sprintf(
                "\n[empirical-escalation] FN=%d (%.1f%%) FP=%d cat-miss=%d\n  FN ids: %s\n  FP ids: %s\n  cat-miss: %s\n",
                count($falseNegatives),
                $fnRate * 100,
                count($falsePositives),
                count($categoryMisses),
                implode(',', $falseNegatives) ?: '(none)',
                implode(',', $falsePositives) ?: '(none)',
                implode(' | ', $categoryMisses) ?: '(none)',
            ),
        );

        self::assertLessThan(
            self::FALSE_NEGATIVE_RATE_CEILING,
            $fnRate,
            sprintf(
                'False-negative rate %.1f%% exceeds locked ceiling %.1f%% (%d/%d missed escalations: %s). '
                    . 'Do NOT slacken the ceiling — fix the prompt or the dataset coverage.',
                $fnRate * 100,
                self::FALSE_NEGATIVE_RATE_CEILING * 100,
                count($falseNegatives),
                count($dataset),
                implode(',', $falseNegatives) ?: '(none)',
            ),
        );
    }

    /**
     * Schema sanity-check for the dataset shipped in T55.15 (runs even while the
     * empirical test is skipped). Catches accidental corruption of the fixture
     * before T55.8 lands.
     */
    public function testDatasetIsShapedCorrectly(): void
    {
        $dataset = require __DIR__ . '/../../../../fixtures/editorial/escalation-classifier-dataset.php';

        $this->assertIsArray($dataset);
        $this->assertCount(51, $dataset, 'Dataset must contain exactly 51 entries (T55.18: +1 cat3_negative_1).');

        $requiredKeys = ['id', 'title', 'summary', 'expected_category', 'expected_is_escalation', 'notes'];
        $validCategories = [
            'CATEGORY_1_NUCLEAR_WAR',
            'CATEGORY_2_HEAD_OF_STATE_DEATH',
            'CATEGORY_3_NBC_ATTACK',
            'CATEGORY_4_COUP',
            'CATEGORY_5_MASS_CASUALTIES',
            'CATEGORY_6_CRIMINAL_ACCUSATION',
            'CATEGORY_7_PRE_CEC_ELECTORAL',
            'FAMILY_A_CHURCH',
            'FAMILY_B_EU_NATO_RUSSIA',
            'FAMILY_C_TRANSNISTRIA_GAGAUZIA',
            'FAMILY_D_CEC_PARTY_LEADERS',
        ];
        $ids = [];

        foreach ($dataset as $i => $entry) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $entry, "Entry #{$i} missing key `{$key}`.");
            }
            $this->assertIsString($entry['id']);
            $this->assertIsString($entry['title']);
            $this->assertIsString($entry['summary']);
            $this->assertIsString($entry['notes']);
            $this->assertIsBool($entry['expected_is_escalation']);
            $this->assertContains(
                $entry['expected_category'],
                $validCategories,
                "Entry #{$i} ({$entry['id']}) has invalid category `{$entry['expected_category']}`.",
            );

            // Romanian diacritics must be comma-below (U+0219, U+021B), never cedilla.
            $combined = $entry['title'] . $entry['summary'] . $entry['notes'];
            $this->assertSame(
                0,
                preg_match('/[ŞşŢţ]/u', $combined),
                "Entry #{$i} ({$entry['id']}) uses cedilla diacritics — must use comma-below (ș/ț).",
            );

            $ids[] = $entry['id'];
        }

        $this->assertCount(51, array_unique($ids), 'All entry IDs must be unique.');
    }

    public function testDatasetDistributionMatchesAuditD18Spec(): void
    {
        $dataset = require __DIR__ . '/../../../../fixtures/editorial/escalation-classifier-dataset.php';

        $byCategory = [];
        foreach ($dataset as $entry) {
            $cat = $entry['expected_category'];
            $bucket = $entry['expected_is_escalation'] ? 'pos' : 'neg';
            $byCategory[$cat][$bucket] = ($byCategory[$cat][$bucket] ?? 0) + 1;
        }

        $expected = [
            'CATEGORY_1_NUCLEAR_WAR' => ['pos' => 3, 'neg' => 1],
            'CATEGORY_2_HEAD_OF_STATE_DEATH' => ['pos' => 3, 'neg' => 1],
            'CATEGORY_3_NBC_ATTACK' => ['pos' => 3, 'neg' => 1],
            'CATEGORY_4_COUP' => ['pos' => 3, 'neg' => 1],
            'CATEGORY_5_MASS_CASUALTIES' => ['pos' => 3, 'neg' => 1],
            'CATEGORY_6_CRIMINAL_ACCUSATION' => ['pos' => 3, 'neg' => 2],
            'CATEGORY_7_PRE_CEC_ELECTORAL' => ['pos' => 3, 'neg' => 1],
            'FAMILY_A_CHURCH' => ['pos' => 3, 'neg' => 2],
            'FAMILY_B_EU_NATO_RUSSIA' => ['pos' => 3, 'neg' => 2],
            'FAMILY_C_TRANSNISTRIA_GAGAUZIA' => ['pos' => 4, 'neg' => 2],
            'FAMILY_D_CEC_PARTY_LEADERS' => ['pos' => 4, 'neg' => 2],
        ];

        foreach ($expected as $cat => $counts) {
            $actualPos = $byCategory[$cat]['pos'] ?? 0;
            $actualNeg = $byCategory[$cat]['neg'] ?? 0;
            $this->assertSame(
                $counts['pos'],
                $actualPos,
                "Category `{$cat}` expected {$counts['pos']} positives, got {$actualPos}.",
            );
            $this->assertSame(
                $counts['neg'],
                $actualNeg,
                "Category `{$cat}` expected {$counts['neg']} negatives, got {$actualNeg}.",
            );
        }
    }
}
