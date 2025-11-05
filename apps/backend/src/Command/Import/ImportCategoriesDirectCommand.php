<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Entity\Category;
use App\Enum\CategoryStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:import:categories-direct',
    description: 'Import categories from Newscoop with translations (direct to DB)'
)]
class ImportCategoriesDirectCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'doctrine.dbal.newscoop_connection')]
        private Connection $newscoopConnection,
        private Connection $defaultConnection,
        private SluggerInterface $slugger,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without persisting data')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing categories first')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $clear = $input->getOption('clear');

        $io->title('Import Categories from Newscoop (Direct with Translations)');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No data will be persisted');
        }

        // Clear existing mappings if requested
        if ($clear && !$dryRun) {
            $io->section('Clearing existing category mappings');
            $deleted = $this->defaultConnection->executeStatement(
                "DELETE FROM newscoop_id_mapping WHERE entity_type = 'section'"
            );
            $io->text(\sprintf('Deleted %d existing mappings', $deleted));
        }

        $io->section('Step 1: Fetching Sections from Newscoop');

        // Fetch all sections
        $sql = '
            SELECT
                Number,
                IdLanguage,
                Name,
                ShortName,
                Description
            FROM Sections
            ORDER BY Number, IdLanguage
        ';

        $sections = $this->newscoopConnection->fetchAllAssociative($sql);
        $io->success(\sprintf('Found %d section records in Newscoop', \count($sections)));

        // Group by Number
        $sectionGroups = [];
        foreach ($sections as $section) {
            $number = $section['Number'];
            $langCode = $this->mapLanguageId($section['IdLanguage']);

            if (!isset($sectionGroups[$number])) {
                $sectionGroups[$number] = [];
            }

            $sectionGroups[$number][$langCode] = $section;
        }

        $io->text(\sprintf('Grouped into %d unique sections', \count($sectionGroups)));

        $io->section('Step 2: Creating Categories with Translations');

        $importedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $translationsCount = 0;

        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

        foreach ($sectionGroups as $number => $translations) {
            try {
                // Check if already imported
                if (!$dryRun) {
                    $existing = $this->defaultConnection->fetchOne(
                        'SELECT news_app_id FROM newscoop_id_mapping WHERE entity_type = ? AND newscoop_id = ?',
                        ['section', $number]
                    );

                    if ($existing) {
                        $io->text(\sprintf('  ⊙ Section #%d already imported (Category ID: %d)', $number, $existing), OutputInterface::VERBOSITY_VERBOSE);
                        ++$skippedCount;
                        continue;
                    }
                }

                // Choose default language (priority: ro > en > ru)
                $defaultLang = $translations['ro'] ?? $translations['en'] ?? $translations['ru'] ?? reset($translations);
                $defaultLocale = array_search($defaultLang, $translations, true);

                $io->text(\sprintf(
                    '  Processing Section #%d: %s (%d translations)',
                    $number,
                    $defaultLang['Name'],
                    \count($translations)
                ), OutputInterface::VERBOSITY_VERBOSE);

                // Create category
                $category = new Category();
                $category->setTitle($defaultLang['Name']);

                // Generate slug
                $slugBase = $defaultLang['ShortName'] ?: $defaultLang['Name'];
                $slug = strtolower($this->slugger->slug($slugBase)->toString());
                $category->setSlug($slug);

                // Description (if exists)
                if (!empty($defaultLang['Description'])) {
                    $description = $this->decodeBlobContent($defaultLang['Description']);
                    $category->setDescription($description);
                }

                $category->setStatus(CategoryStatus::ACTIVE);
                $category->setOnFrontPage(false);

                if (!$dryRun) {
                    // Set translatable locale for default language
                    $category->setTranslatableLocale($defaultLocale);

                    $this->entityManager->persist($category);
                    $this->entityManager->flush();

                    // Add translations for other languages
                    foreach ($translations as $locale => $data) {
                        if ($locale === $defaultLocale) {
                            continue; // Skip default language
                        }

                        $translationRepo->translate($category, 'title', $locale, $data['Name']);

                        $slugLocale = strtolower($this->slugger->slug($data['ShortName'] ?: $data['Name'])->toString());
                        $translationRepo->translate($category, 'slug', $locale, $slugLocale);

                        if (!empty($data['Description'])) {
                            $desc = $this->decodeBlobContent($data['Description']);
                            $translationRepo->translate($category, 'description', $locale, $desc);
                        }

                        ++$translationsCount;

                        $io->text(\sprintf('    + Translation: %s → %s', $locale, $data['Name']), OutputInterface::VERBOSITY_VERY_VERBOSE);
                    }

                    $this->entityManager->flush();

                    // Save mapping
                    $this->defaultConnection->insert('newscoop_id_mapping', [
                        'entity_type' => 'section',
                        'newscoop_id' => $number,
                        'news_app_id' => $category->getId(),
                    ]);

                    $io->text(\sprintf(
                        '  ✓ Created: %s (ID: %d, %d translations)',
                        $category->getTitle(),
                        $category->getId(),
                        \count($translations) - 1
                    ), OutputInterface::VERBOSITY_VERBOSE);
                }

                ++$importedCount;

            } catch (Exception $e) {
                ++$errorCount;
                $io->error(\sprintf(
                    'Failed to import section #%d: %s',
                    $number,
                    $e->getMessage()
                ));

                $this->logger->error('Category import failed', [
                    'section_number' => $number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Summary
        $io->section('Import Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total unique sections', \count($sectionGroups)],
                ['Categories imported', $importedCount],
                ['Translations added', $translationsCount],
                ['Skipped (already exist)', $skippedCount],
                ['Errors', $errorCount],
            ]
        );

        if ($dryRun) {
            $io->warning('DRY RUN: No data was persisted');
        } else {
            $io->success(\sprintf(
                'Successfully imported %d categories with %d translations!',
                $importedCount,
                $translationsCount
            ));
        }

        return Command::SUCCESS;
    }

    private function mapLanguageId(int $languageId): string
    {
        return match ($languageId) {
            1 => 'en',
            2 => 'ro',
            15 => 'ru',
            default => 'unknown',
        };
    }

    private function decodeBlobContent(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        if (\is_resource($content)) {
            $content = stream_get_contents($content);
        }

        return trim($content) ?: null;
    }
}
