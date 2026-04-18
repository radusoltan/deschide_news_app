<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Escalation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Empirical-validation test for EscalationClassifier (Sprint 55 T55.8, ADR-020 D7).
 *
 * Currently SKIPPED — EscalationClassifier service lands in T55.8. The 50-claim
 * dataset is already in place at
 * `tests/fixtures/editorial/escalation-classifier-dataset.php` (Sprint 55 T55.15,
 * reviewed by Radu in T55.18).
 *
 * When T55.8 un-skips this test, the implementation must:
 *  1. Load the dataset.
 *  2. For each entry, call $classifier->classify($claimText, $primaryTitle, $topic).
 *  3. Map the classifier result to `is_escalation = result !== null` and
 *     `category = result?->name`.
 *  4. Compare against `expected_is_escalation` and `expected_category`.
 *  5. Assert the false-negative rate < 5% (≤ 2 of 50) — claims that are true
 *     positives but the classifier returned null.
 *  6. Log false positives for Radu's awareness but do NOT fail the run on them
 *     (weaker acceptance bar per audit D18).
 */
#[Group('empirical-escalation')]
final class EscalationClassifierEmpiricalTest extends TestCase
{
    public function test50ClaimAcceptanceBar(): void
    {
        self::markTestSkipped(
            'Pending EscalationClassifier service from T55.8 — '
                . 'dataset already available at tests/fixtures/editorial/escalation-classifier-dataset.php.'
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
        $this->assertCount(50, $dataset, 'Dataset must contain exactly 50 entries.');

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

        $this->assertCount(50, array_unique($ids), 'All entry IDs must be unique.');
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
            'CATEGORY_3_NBC_ATTACK' => ['pos' => 3, 'neg' => 0],
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
