<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:seed-category-slug-translations',
    description: 'Generate slug translations for all categories based on existing title translations',
)]
final class SeedCategorySlugTranslationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
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

        $io->title('Seed Category Slug Translations');

        try {
        $translationRepo = $this->em->getRepository(Translation::class);
        $categories = $this->em->getRepository(Category::class)->findAll();

        $io->info(\sprintf('Found %d categories', \count($categories)));

        $created = 0;
        $skipped = 0;

        foreach ($categories as $category) {
            $catId = $category->getId();

            // Get existing translations for this category
            $translations = $translationRepo->findTranslations($category);

            foreach (['en', 'ru'] as $locale) {
                $titleTranslation = $translations[$locale]['title'] ?? null;

                if ($titleTranslation === null || $titleTranslation === '') {
                    $io->writeln(\sprintf('  [SKIP] Category %d (%s): no %s title translation', $catId, $category->getTitle(), $locale));
                    $skipped++;
                    continue;
                }

                // Check if slug translation already exists
                $existingSlug = $translations[$locale]['slug'] ?? null;
                if ($existingSlug !== null && $existingSlug !== '') {
                    $io->writeln(\sprintf('  [EXISTS] Category %d (%s) %s: slug="%s"', $catId, $category->getTitle(), $locale, $existingSlug), OutputInterface::VERBOSITY_VERBOSE);
                    $skipped++;
                    continue;
                }

                // Generate slug from translated title
                $slug = $this->slugger->slug($titleTranslation)->lower()->toString();

                $io->writeln(\sprintf('  [%s] Category %d (%s) %s: title="%s" → slug="%s"', $dryRun ? 'DRY-RUN' : 'CREATE', $catId, $category->getTitle(), $locale, $titleTranslation, $slug));

                if (!$dryRun) {
                    $translationRepo->translate($category, 'slug', $locale, $slug);
                }

                $created++;
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->table(['Metric', 'Valoare'], [
            ['Created', (string) $created],
            ['Skipped', (string) $skipped],
        ]);

        $io->success(\sprintf('%s %d slug translations.', $dryRun ? 'Would create' : 'Created', $created));

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
