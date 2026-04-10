<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\MenuItem;
use App\Enum\MenuItemType;
use App\Enum\MenuType;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-menu-items',
    description: 'Populate MenuItem entities from existing categories with inMenu/inFooterMenu flags',
)]
final class SeedMenuItemsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without saving');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Seed Menu Items from Categories');

        try {
        // Use a direct DQL query to bypass potential Gedmo Translatable interference
        // with boolean field hydration
        $categories = $this->em->createQuery('SELECT c FROM App\Entity\Category c ORDER BY c.id ASC')
            ->getResult();
        $io->info(\sprintf('Found %d categories', \count($categories)));

        $translationRepo = $this->em->getRepository(Translation::class);
        $menuItemRepo = $this->em->getRepository(MenuItem::class);

        $createdMain = 0;
        $createdFooter = 0;
        $skipped = 0;
        $translationsCreated = 0;

        // Track position per menu type
        $positionMain = 0;
        $positionFooter = 0;

        foreach ($categories as $category) {
            $catId = $category->getId();
            $catTitle = $category->getTitle();

            // Get existing translations for this category
            $catTranslations = $translationRepo->findTranslations($category);

            // --- MAIN MENU ---
            if ($category->isInMenu()) {
                $existing = $menuItemRepo->findOneBy([
                    'menu' => MenuType::MAIN,
                    'type' => MenuItemType::CATEGORY,
                    'category' => $category,
                ]);

                if ($existing) {
                    $io->writeln(\sprintf('  [SKIP] Main menu: category %d (%s) already exists as MenuItem #%d', $catId, $catTitle, $existing->getId()), OutputInterface::VERBOSITY_VERBOSE);
                    $skipped++;
                } else {
                    $menuItem = new MenuItem();
                    $menuItem->setMenu(MenuType::MAIN);
                    $menuItem->setType(MenuItemType::CATEGORY);
                    $menuItem->setLabel($catTitle);
                    $menuItem->setCategory($category);
                    $menuItem->setPosition($positionMain);
                    $menuItem->setIsActive(true);

                    $io->writeln(\sprintf('  [%s] Main menu: category %d (%s) → position %d', $dryRun ? 'DRY-RUN' : 'CREATE', $catId, $catTitle, $positionMain));

                    if (!$dryRun) {
                        $this->em->persist($menuItem);
                        // Flush to get the ID, which is needed for translations
                        $this->em->flush();

                        // Add translations (en, ru)
                        $translationsCreated += $this->addTranslations($menuItem, $catTranslations, $io);
                    }

                    $createdMain++;
                }

                $positionMain++;
            }

            // --- FOOTER MENU ---
            if ($category->isInFooterMenu()) {
                $existing = $menuItemRepo->findOneBy([
                    'menu' => MenuType::FOOTER,
                    'type' => MenuItemType::CATEGORY,
                    'category' => $category,
                ]);

                if ($existing) {
                    $io->writeln(\sprintf('  [SKIP] Footer menu: category %d (%s) already exists as MenuItem #%d', $catId, $catTitle, $existing->getId()), OutputInterface::VERBOSITY_VERBOSE);
                    $skipped++;
                } else {
                    $menuItem = new MenuItem();
                    $menuItem->setMenu(MenuType::FOOTER);
                    $menuItem->setType(MenuItemType::CATEGORY);
                    $menuItem->setLabel($catTitle);
                    $menuItem->setCategory($category);
                    $menuItem->setPosition($positionFooter);
                    $menuItem->setIsActive(true);

                    $io->writeln(\sprintf('  [%s] Footer menu: category %d (%s) → position %d', $dryRun ? 'DRY-RUN' : 'CREATE', $catId, $catTitle, $positionFooter));

                    if (!$dryRun) {
                        $this->em->persist($menuItem);
                        $this->em->flush();

                        // Add translations (en, ru)
                        $translationsCreated += $this->addTranslations($menuItem, $catTranslations, $io);
                    }

                    $createdFooter++;
                }

                $positionFooter++;
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->newLine();
        $io->table(['Metric', 'Count'], [
            ['Main menu items created', (string) $createdMain],
            ['Footer menu items created', (string) $createdFooter],
            ['Total items created', (string) ($createdMain + $createdFooter)],
            ['Translations created', (string) $translationsCreated],
            ['Skipped (already exist)', (string) $skipped],
        ]);

        $io->success(\sprintf(
            '%s %d menu items (%d main, %d footer) with %d translations.',
            $dryRun ? 'Would create' : 'Created',
            $createdMain + $createdFooter,
            $createdMain,
            $createdFooter,
            $translationsCreated,
        ));

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Add en/ru translations for a MenuItem label based on the category's translations.
     *
     * @param array<string, array<string, string>> $catTranslations Category translations from Gedmo
     *
     * @return int Number of translations created
     */
    private function addTranslations(MenuItem $menuItem, array $catTranslations, SymfonyStyle $io): int
    {
        $translationRepo = $this->em->getRepository(Translation::class);
        $count = 0;

        foreach (['en', 'ru'] as $locale) {
            $translatedTitle = $catTranslations[$locale]['title'] ?? null;

            if ($translatedTitle === null || $translatedTitle === '') {
                $io->writeln(\sprintf('    [SKIP] No %s title translation for category → skipping MenuItem label translation', $locale), OutputInterface::VERBOSITY_VERBOSE);
                continue;
            }

            $translationRepo->translate($menuItem, 'label', $locale, $translatedTitle);
            $io->writeln(\sprintf('    [TRANS] %s: "%s"', $locale, $translatedTitle), OutputInterface::VERBOSITY_VERBOSE);
            $count++;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }
}
