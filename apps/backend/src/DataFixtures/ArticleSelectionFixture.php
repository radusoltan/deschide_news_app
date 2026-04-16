<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Culls CSV-imported articles per category caps (most-recent-first).
 * Runs AFTER CSV import phases 1-3 and BEFORE LivePipelineSampleFixture.
 *
 * Destructive by design — hard-deletes articles exceeding the cap
 * together with their translations and external mappings.
 */
class ArticleSelectionFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    /** Per-category article caps. 999 = "keep all available". */
    private const CAPS = [
        'politica' => 400,
        'societate' => 400,
        'externe' => 400,
        'economie' => 400,
        'romania' => 999,
        'cultura' => 999,
        'sport' => 999,
        'editoriale' => 999,
        'opinii' => 999,
        'advertorial' => 999,
        'anti-fake' => 999,
    ];

    public static function getGroups(): array
    {
        return ['dev', 'article-selection'];
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        assert($manager instanceof EntityManagerInterface);
        /** @var Connection $conn */
        $conn = $manager->getConnection();

        $totalBefore = (int) $conn->fetchOne('SELECT COUNT(*) FROM articles');
        if ($totalBefore === 0) {
            echo "  No articles found — skipping selection (CSV import not run yet?)\n";

            return;
        }

        $totalDeleted = 0;

        foreach (self::CAPS as $slug => $cap) {
            // Find category ID
            $categoryId = $conn->fetchOne(
                'SELECT id FROM categories WHERE slug = ?',
                [$slug],
            );

            if ($categoryId === false) {
                echo "  WARNING: category '{$slug}' not found, skipping\n";
                continue;
            }

            // Count articles in this category from CSV import
            $count = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM articles a
                 JOIN external_article_mapping eam ON eam.article_id = a.id
                 WHERE eam.source = 'csv_legacy_deschide'
                   AND a.category_id = ?",
                [$categoryId],
            );

            if ($count <= $cap) {
                continue;
            }

            // Find IDs to delete (beyond cap, oldest first)
            $idsToDelete = $conn->fetchFirstColumn(
                "WITH ranked AS (
                    SELECT a.id,
                           ROW_NUMBER() OVER (
                               ORDER BY a.published_at DESC NULLS LAST, a.id DESC
                           ) AS rn
                    FROM articles a
                    JOIN external_article_mapping eam ON eam.article_id = a.id
                    WHERE eam.source = 'csv_legacy_deschide'
                      AND a.category_id = ?
                )
                SELECT id FROM ranked WHERE rn > ?",
                [$categoryId, $cap],
            );

            if (\count($idsToDelete) === 0) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, \count($idsToDelete), '?'));

            // Delete Gedmo translations (no FK cascade)
            $conn->executeStatement(
                "DELETE FROM ext_translations
                 WHERE object_class = 'App\\Entity\\Article'
                   AND foreign_key::int IN ({$placeholders})",
                $idsToDelete,
            );

            // Delete articles (cascades to external_article_mapping, article_images, article_tag, article_topics)
            $deleted = $conn->executeStatement(
                "DELETE FROM articles WHERE id IN ({$placeholders})",
                $idsToDelete,
            );

            $totalDeleted += $deleted;
        }

        $totalAfter = $totalBefore - $totalDeleted;
        echo "  Article selection: {$totalBefore} → {$totalAfter} ({$totalDeleted} culled)\n";
    }
}
