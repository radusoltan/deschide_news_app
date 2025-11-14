<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Entity\Category;
use App\Enum\CategoryStatus;
use App\Service\Import\ImportTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:categories-complete',
    description: 'Import ALL categories from JSON (Complete Import - Option A)'
)]
class ImportCompleteCategoriesCommand extends Command
{
    private const JSON_FILE_PATH = __DIR__ . '/../../../data/import/categories_complete.json';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportTokenService $importTokenService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode (no database writes)')
            ->addOption('skip-translations', null, InputOption::VALUE_NONE, 'Skip translations (import only Romanian)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force reimport (update existing categories)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = $input->getOption('dry-run');
        $skipTranslations = $input->getOption('skip-translations');
        $force = $input->getOption('force');

        $io->title('📦 IMPORT COMPLET CATEGORII - Opțiunea A (28 categorii)');

        $io->section('⚙️ Configuration');
        $io->definitionList(
            ['JSON File' => self::JSON_FILE_PATH],
            ['Mode' => $dryRun ? '🔍 DRY RUN (no database writes)' : '✅ LIVE'],
            ['Translations' => $skipTranslations ? '❌ Skip' : '✅ Import (ro, en, ru)'],
            ['Force Update' => $force ? '⚠️ Yes (update existing)' : '❌ No (skip existing)'],
        );

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No actual database changes will be made');
        }

        // Step 1: Validate JSON file
        $io->section('📁 Step 1: Load and Validate JSON');

        if (!file_exists(self::JSON_FILE_PATH)) {
            $io->error('JSON file not found: ' . self::JSON_FILE_PATH);

            return Command::FAILURE;
        }

        $jsonContent = file_get_contents(self::JSON_FILE_PATH);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Invalid JSON: ' . json_last_error_msg());

            return Command::FAILURE;
        }

        $categories = $data['categories'] ?? [];
        $meta = $data['meta'] ?? [];

        $io->writeln(\sprintf('✅ JSON loaded successfully'));
        $io->writeln(\sprintf('   Total categories: <info>%d</info>', $meta['total_categories'] ?? count($categories)));
        $io->writeln(\sprintf('   Active: <info>%d</info>, Archived: <info>%d</info>', $meta['active_categories'] ?? 0, $meta['archived_categories'] ?? 0));

        if (count($categories) === 0) {
            $io->warning('No categories found in JSON file');

            return Command::SUCCESS;
        }

        // Step 2: Import categories (Romanian first)
        $io->section('🇷🇴 Step 2: Import Categories (Romanian - Default Locale)');

        $stats = [
            'total' => count($categories),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $io->progressStart(count($categories));

        foreach ($categories as $categoryData) {
            $slug = $categoryData['slug'];
            $roTranslation = $categoryData['translations']['ro'] ?? null;
            $isArchived = $categoryData['isArchived'] ?? false;
            $onFrontPage = $categoryData['onFrontPage'] ?? false;

            if (!$roTranslation) {
                $io->writeln(\sprintf('  ❌ [ERROR] Missing Romanian translation for slug: %s', $slug), OutputInterface::VERBOSITY_VERBOSE);
                ++$stats['errors'];
                $io->progressAdvance();
                continue;
            }

            try {
                // Check if category already exists
                $category = $this->entityManager
                    ->getRepository(Category::class)
                    ->findOneBy(['slug' => $slug]);

                if ($category && !$force) {
                    $io->writeln(\sprintf('  ⏩ [SKIP] Category already exists: %s', $slug), OutputInterface::VERBOSITY_VERBOSE);
                    ++$stats['skipped'];
                    $io->progressAdvance();
                    continue;
                }

                if (!$category) {
                    // Create new category
                    $category = new Category();
                    $category->setSlug($slug);
                    $wasCreated = true;
                } else {
                    $wasCreated = false;
                }

                // Set Romanian (default locale) fields
                $category->setTranslatableLocale('ro');
                $category->setTitle($roTranslation['title']);

                // Set status based on isArchived flag
                $status = $isArchived ? CategoryStatus::ARCHIVED : CategoryStatus::ACTIVE;
                $category->setStatus($status);
                $category->setOnFrontPage($onFrontPage);

                if (!$dryRun) {
                    $this->entityManager->persist($category);
                    $this->entityManager->flush();

                    if ($wasCreated) {
                        ++$stats['created'];
                        $io->writeln(\sprintf('  ✅ [CREATE] %s - %s', $slug, $roTranslation['title']), OutputInterface::VERBOSITY_VERBOSE);
                    } else {
                        ++$stats['updated'];
                        $io->writeln(\sprintf('  🔄 [UPDATE] %s - %s', $slug, $roTranslation['title']), OutputInterface::VERBOSITY_VERBOSE);
                    }
                } else {
                    if ($wasCreated) {
                        ++$stats['created'];
                        $io->writeln(\sprintf('  🔍 [DRY-CREATE] %s - %s', $slug, $roTranslation['title']), OutputInterface::VERBOSITY_VERBOSE);
                    } else {
                        ++$stats['updated'];
                        $io->writeln(\sprintf('  🔍 [DRY-UPDATE] %s - %s', $slug, $roTranslation['title']), OutputInterface::VERBOSITY_VERBOSE);
                    }
                }
            } catch (Exception $e) {
                $io->writeln(\sprintf('  ❌ [ERROR] %s: %s', $slug, $e->getMessage()), OutputInterface::VERBOSITY_VERBOSE);
                ++$stats['errors'];
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Step 3: Import translations (English & Russian)
        if (!$skipTranslations && !$dryRun) {
            $io->section('🌍 Step 3: Import Translations (English & Russian)');

            $translationStats = [
                'en' => ['success' => 0, 'errors' => 0],
                'ru' => ['success' => 0, 'errors' => 0],
            ];

            foreach (['en', 'ru'] as $locale) {
                $localeFlag = $locale === 'en' ? '🇬🇧' : '🇷🇺';
                $io->writeln(\sprintf('%s Processing %s translations...', $localeFlag, strtoupper($locale)));

                $io->progressStart(count($categories));

                foreach ($categories as $categoryData) {
                    $slug = $categoryData['slug'];
                    $translation = $categoryData['translations'][$locale] ?? null;

                    if (!$translation) {
                        $io->writeln(\sprintf('  ⚠️ Missing %s translation for: %s', strtoupper($locale), $slug), OutputInterface::VERBOSITY_VERY_VERBOSE);
                        ++$translationStats[$locale]['errors'];
                        $io->progressAdvance();
                        continue;
                    }

                    try {
                        // Find category by slug
                        $category = $this->entityManager
                            ->getRepository(Category::class)
                            ->findOneBy(['slug' => $slug]);

                        if (!$category) {
                            $io->writeln(\sprintf('  ❌ Category not found: %s', $slug), OutputInterface::VERBOSITY_VERY_VERBOSE);
                            ++$translationStats[$locale]['errors'];
                            $io->progressAdvance();
                            continue;
                        }

                        // Set translation locale and title
                        $category->setTranslatableLocale($locale);
                        $category->setTitle($translation['title']);

                        // IMPORTANT: Re-set slug to original value (prevent Gedmo from regenerating it)
                        $category->setSlug($slug);

                        $this->entityManager->persist($category);
                        $this->entityManager->flush();

                        // Reset locale to default to avoid polluting next operations
                        $this->entityManager->refresh($category);
                        $category->setTranslatableLocale('ro');

                        ++$translationStats[$locale]['success'];
                        $io->writeln(\sprintf('  ✅ [%s] %s - %s', strtoupper($locale), $slug, $translation['title']), OutputInterface::VERBOSITY_VERY_VERBOSE);
                    } catch (Exception $e) {
                        $io->writeln(\sprintf('  ❌ [ERROR] %s (%s): %s', $slug, strtoupper($locale), $e->getMessage()), OutputInterface::VERBOSITY_VERY_VERBOSE);
                        ++$translationStats[$locale]['errors'];
                    }

                    $io->progressAdvance();
                }

                $io->progressFinish();
                $io->writeln(\sprintf('  %s %s: %d success, %d errors', $localeFlag, strtoupper($locale), $translationStats[$locale]['success'], $translationStats[$locale]['errors']));
            }
        } elseif ($skipTranslations) {
            $io->note('Translations skipped (--skip-translations flag)');
        } elseif ($dryRun) {
            $io->note('Translations skipped in DRY RUN mode');
        }

        // Step 4: Display statistics
        $io->section('📊 Step 4: Import Statistics');

        $io->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total', $stats['total'], '100%'],
                ['Created', $stats['created'], \sprintf('%.1f%%', ($stats['created'] / $stats['total']) * 100)],
                ['Updated', $stats['updated'], \sprintf('%.1f%%', ($stats['updated'] / $stats['total']) * 100)],
                ['Skipped', $stats['skipped'], \sprintf('%.1f%%', ($stats['skipped'] / $stats['total']) * 100)],
                ['Errors', $stats['errors'], \sprintf('%.1f%%', ($stats['errors'] / $stats['total']) * 100)],
            ]
        );

        if (!$skipTranslations && !$dryRun && isset($translationStats)) {
            $io->section('🌍 Translation Statistics');
            $io->table(
                ['Locale', 'Success', 'Errors'],
                [
                    ['🇬🇧 English (en)', $translationStats['en']['success'], $translationStats['en']['errors']],
                    ['🇷🇺 Russian (ru)', $translationStats['ru']['success'], $translationStats['ru']['errors']],
                ]
            );
        }

        // Final message
        if ($dryRun) {
            $io->warning('DRY RUN completed - No database changes were made');
            $io->note('Run without --dry-run to actually import categories');
        } else {
            if ($stats['errors'] > 0) {
                $io->warning(\sprintf('Import completed with %d errors', $stats['errors']));
            } else {
                $io->success(\sprintf('✅ Successfully imported %d categories!', $stats['created'] + $stats['updated']));
            }

            // Next steps
            $io->section('📋 Next Steps');
            $io->listing([
                'Add isArchived field to Category entity',
                'Create migration for isArchived column',
                'Re-run this command with --force to update archived flags',
                'Test categories with: curl http://127.0.0.1:8081/api/categories',
                'Test translations: curl -H "Accept-Language: en" http://127.0.0.1:8081/api/categories',
            ]);
        }

        return Command::SUCCESS;
    }
}
