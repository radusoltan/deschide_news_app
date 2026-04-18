<?php

declare(strict_types=1);

namespace App\DataFixtures\Editorial;

use App\Entity\Editorial\VerifiedSource;
use App\Enum\EditorialAlignment;
use App\Repository\Editorial\VerifiedSourceRepository;
use App\Repository\SourceRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Sprint 53 T53.4b — Seeds the 15 editorial-pipeline signal sources from
 * `fixtures/data/verified-sources.yaml` (ADR-020 D4 amendment 2026-04-18).
 *
 * - Resolves `source_name` against the canonical {@see \App\Entity\Source}
 *   registry via `SourceRepository::findByName()`. Missing Source rows are
 *   logged as warnings and skipped (graceful degradation).
 * - Find-or-skip on `VerifiedSource.slug` for idempotent --append runs.
 * - Run after `app:source:seed-international` + `app:source:seed-diaspora`
 *   have populated the `sources` table.
 */
class VerifiedSourceFixture extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly VerifiedSourceRepository $verifiedSourceRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getGroups(): array
    {
        return ['editorial-sources'];
    }

    public function load(ObjectManager $manager): void
    {
        // src/DataFixtures/Editorial/ → backend root (up 3)
        $yamlPath = \dirname(__DIR__, 3) . '/fixtures/data/verified-sources.yaml';

        if (!is_file($yamlPath)) {
            throw new \RuntimeException(sprintf(
                'VerifiedSourceFixture: YAML source not found at %s',
                $yamlPath,
            ));
        }

        /** @var array{verified_sources?: list<array<string, mixed>>} $data */
        $data = Yaml::parseFile($yamlPath);
        $entries = $data['verified_sources'] ?? [];

        $matched = 0;
        $sourceMissing = 0;
        $alreadyExists = 0;

        foreach ($entries as $entry) {
            $slug = (string) ($entry['slug'] ?? '');
            $sourceName = (string) ($entry['source_name'] ?? '');
            $tier = (int) ($entry['tier'] ?? 0);
            $alignmentValue = (string) ($entry['editorial_alignment'] ?? '');
            $trustBaseline = (string) ($entry['trust_score_baseline'] ?? '');
            $enabled = (bool) ($entry['enabled'] ?? true);
            $notes = isset($entry['editorial_notes']) ? (string) $entry['editorial_notes'] : null;

            if ($slug === '' || $sourceName === '' || $alignmentValue === '' || $trustBaseline === '') {
                $this->logger->warning('VerifiedSourceFixture: skipping malformed entry', ['entry' => $entry]);
                continue;
            }

            if ($this->verifiedSourceRepository->findBySlug($slug) !== null) {
                ++$alreadyExists;
                continue;
            }

            $source = $this->sourceRepository->findByName($sourceName);
            if ($source === null) {
                $this->logger->warning(sprintf(
                    'VerifiedSourceFixture: Source not found by name "%s" (slug=%s), skipping entry',
                    $sourceName,
                    $slug,
                ));
                ++$sourceMissing;
                continue;
            }

            $alignment = EditorialAlignment::from($alignmentValue);

            $verifiedSource = new VerifiedSource(
                slug: $slug,
                tier: $tier,
                editorialAlignment: $alignment,
                trustScoreBaseline: $trustBaseline,
                source: $source,
            );
            $verifiedSource->setEnabled($enabled);
            $verifiedSource->setEditorialNotes($notes);

            $manager->persist($verifiedSource);
            ++$matched;
        }

        $manager->flush();

        $this->logger->info(sprintf(
            'VerifiedSourceFixture loaded: matched=%d, already_exists=%d, source_missing=%d, total_yaml_entries=%d',
            $matched,
            $alreadyExists,
            $sourceMissing,
            \count($entries),
        ));
    }
}
