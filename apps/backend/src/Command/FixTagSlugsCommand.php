<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fix tag slugs: base table contains EN slugs instead of RO.
 *
 * Problem: Tags 1-20 were imported with English slugs in the base table
 * (which Gedmo treats as the default locale = RO). Tags 21+ were created
 * later with correct RO slugs, creating duplicates.
 *
 * This command (in a single transaction):
 * 1. Deletes 14 duplicate tags and their ext_translations
 * 2. Updates 15 tag slugs from EN to correct RO
 *
 * Safe because article_tag has 0 rows (no tags linked to articles yet).
 */
#[AsCommand(
    name: 'app:fix:tag-slugs',
    description: 'Fix tag slugs: correct EN→RO in base table and remove duplicate tags',
)]
class FixTagSlugsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Fix Tag Slugs: EN → RO in base table');

        // Verify precondition: article_tag must be empty
        $articleTagCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM article_tag');
        if ($articleTagCount > 0) {
            $io->error("article_tag has {$articleTagCount} rows — cannot safely delete duplicate tags. Aborting.");

            return Command::FAILURE;
        }
        $io->success('Precondition OK: article_tag has 0 rows');

        // Duplicate tags to delete (IDs from the 21+ range that duplicate 1-20)
        $duplicatesToDelete = [21, 23, 28, 29, 30, 31, 33, 34, 36, 38, 40, 41, 42, 44];

        // Slug corrections for tags 1-20 (only those that need changing)
        $slugFixes = [
            1 => 'politica',
            2 => 'economie',
            4 => 'cultura',
            5 => 'tehnologie',
            6 => 'sanatate',
            7 => 'educatie',
            8 => 'mediu',
            9 => 'justitie',
            10 => 'infrastructura',
            12 => 'ue',
            14 => 'rusia',
            17 => 'alegeri',
            18 => 'coruptie',
            19 => 'agricultura',
            20 => 'energie',
        ];

        // Show current state
        $io->section('Current State (tags with EN slugs)');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, name, slug FROM tags WHERE id <= 20 ORDER BY id'
        );
        $io->table(['ID', 'Name (RO)', 'Current Slug (EN)'], $rows);

        $io->section('Duplicate tags to delete');
        $dupRows = $this->connection->fetchAllAssociative(
            'SELECT id, name, slug FROM tags WHERE id IN (' . implode(',', $duplicatesToDelete) . ') ORDER BY id'
        );
        $io->table(['ID', 'Name', 'Slug (RO - duplicate)'], $dupRows);

        $io->section('Planned slug corrections');
        $planRows = [];
        foreach ($slugFixes as $id => $newSlug) {
            $currentSlug = $this->connection->fetchOne('SELECT slug FROM tags WHERE id = ?', [$id]);
            $name = $this->connection->fetchOne('SELECT name FROM tags WHERE id = ?', [$id]);
            $planRows[] = [$id, $name, $currentSlug, $newSlug];
        }
        $io->table(['ID', 'Name', 'Before', 'After'], $planRows);

        if ($dryRun) {
            $io->warning('DRY RUN — no changes made.');

            return Command::SUCCESS;
        }

        // Execute in transaction
        $this->connection->beginTransaction();
        try {
            // Step 1: Delete ext_translations for duplicate tags
            $placeholders = implode(',', array_map(fn ($id) => "'{$id}'", $duplicatesToDelete));
            $deletedTranslations = $this->connection->executeStatement(
                "DELETE FROM ext_translations WHERE object_class = 'App\\Entity\\Tag' AND foreign_key IN ({$placeholders})"
            );
            $io->text("Deleted {$deletedTranslations} ext_translations rows for duplicate tags");

            // Step 2: Delete duplicate tags
            $deletedTags = $this->connection->executeStatement(
                'DELETE FROM tags WHERE id IN (' . implode(',', $duplicatesToDelete) . ')'
            );
            $io->text("Deleted {$deletedTags} duplicate tags");

            // Step 3: Update slugs on original tags
            $updated = 0;
            foreach ($slugFixes as $id => $newSlug) {
                $this->connection->executeStatement(
                    'UPDATE tags SET slug = ? WHERE id = ?',
                    [$newSlug, $id]
                );
                ++$updated;
            }
            $io->text("Updated {$updated} tag slugs");

            $this->connection->commit();
            $io->success('All changes committed successfully.');
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $io->error('Transaction rolled back: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Verify final state
        $io->section('Final State');
        $finalRows = $this->connection->fetchAllAssociative(
            'SELECT id, name, slug FROM tags ORDER BY id'
        );
        $io->table(['ID', 'Name', 'Slug'], $finalRows);

        $totalTags = $this->connection->fetchOne('SELECT COUNT(*) FROM tags');
        $io->info("Total tags remaining: {$totalTags}");

        return Command::SUCCESS;
    }
}
