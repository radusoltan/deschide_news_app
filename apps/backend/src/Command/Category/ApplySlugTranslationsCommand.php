<?php

declare(strict_types=1);

namespace App\Command\Category;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:category:apply-slug-translations',
    description: 'Persist approved category slug translations from JSON to ext_translations (idempotent)',
)]
final class ApplySlugTranslationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'Path to approved JSON file (required)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show actions without persisting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $inputPath = $input->getOption('input');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($inputPath === null || $inputPath === '') {
            $io->error('Missing required --input=<path>');

            return Command::INVALID;
        }

        if (!is_file($inputPath) || !is_readable($inputPath)) {
            $io->error("Input file not found or unreadable: {$inputPath}");

            return Command::FAILURE;
        }

        $raw = file_get_contents($inputPath);
        if ($raw === false) {
            $io->error("Failed to read {$inputPath}");

            return Command::FAILURE;
        }

        try {
            /** @var list<array{category_id:int,ro_slug?:string,ro_title?:string,translations:array<string,array{title?:string,proposed_slug:string}>}> $entries */
            $entries = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error("JSON decode failed: {$e->getMessage()}");

            return Command::FAILURE;
        }

        if (!\is_array($entries)) {
            $io->error('JSON root must be an array.');

            return Command::FAILURE;
        }

        $translationRepo = $this->em->getRepository(Translation::class);
        $categoryRepo = $this->em->getRepository(Category::class);

        $rows = [];
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'missing' => 0];

        foreach ($entries as $entry) {
            $catId = $entry['category_id'] ?? null;
            $translations = $entry['translations'] ?? [];

            if (!\is_int($catId) || !\is_array($translations)) {
                $io->warning('Skipping malformed entry (missing category_id or translations).');

                continue;
            }

            $category = $categoryRepo->find($catId);
            if ($category === null) {
                $rows[] = [(string) $catId, '—', 'missing', '—'];
                ++$counts['missing'];

                continue;
            }

            $existing = $translationRepo->findTranslations($category);
            $hasChanges = false;

            foreach ($translations as $locale => $payload) {
                if (!\is_string($locale) || !\is_array($payload)) {
                    continue;
                }
                $proposed = $payload['proposed_slug'] ?? null;
                if (!\is_string($proposed) || $proposed === '') {
                    continue;
                }

                $current = $existing[$locale]['slug'] ?? null;

                if ($current === $proposed) {
                    $rows[] = [(string) $catId, $locale, 'skipped', $proposed];
                    ++$counts['skipped'];

                    continue;
                }

                $action = ($current === null || $current === '') ? 'created' : 'updated';

                if (!$dryRun) {
                    $translationRepo->translate($category, 'slug', $locale, $proposed);
                    $hasChanges = true;
                }

                $rows[] = [(string) $catId, $locale, $action, $proposed];
                ++$counts[$action];
            }

            if ($hasChanges && !$dryRun) {
                $this->em->flush();
                // Doctrine ORM 3.x: EntityManager::clear() takes no arguments
                // (the per-class signature was deprecated in 2.x and removed
                // in 3.x). Clearing everything is safe here because this
                // command runs in batch mode and the loop re-fetches
                // categories on the next iteration.
                $this->em->clear();
            }
        }

        $io->table(['category_id', 'locale', 'action', 'slug'], $rows);

        $summary = sprintf(
            '%s — created: %d, updated: %d, skipped: %d, missing categories: %d',
            $dryRun ? 'DRY-RUN' : 'APPLIED',
            $counts['created'],
            $counts['updated'],
            $counts['skipped'],
            $counts['missing'],
        );

        if ($counts['missing'] > 0) {
            $io->warning($summary);

            return Command::FAILURE;
        }

        $io->success($summary);

        return Command::SUCCESS;
    }
}
