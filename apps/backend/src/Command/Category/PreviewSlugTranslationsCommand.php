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
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:category:preview-slug-translations',
    description: 'Generate JSON proposal of category slug translations for human review (read-only)',
)]
final class PreviewSlugTranslationsCommand extends Command
{
    private const NON_RO_LOCALES = ['en', 'ru'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Path to write JSON output (stdout if omitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputPath = $input->getOption('output');

        $translationRepo = $this->em->getRepository(Translation::class);
        $categories = $this->em->getRepository(Category::class)->findBy([], ['id' => 'ASC']);

        if ($categories === []) {
            $io->warning('No categories found.');

            return Command::SUCCESS;
        }

        $proposals = [];
        $missingTitleTranslations = [];

        foreach ($categories as $category) {
            $catId = $category->getId();
            $existing = $translationRepo->findTranslations($category);

            $entry = [
                'category_id' => $catId,
                'ro_slug' => $category->getSlug(),
                'ro_title' => $category->getTitle(),
                'translations' => [],
            ];

            foreach (self::NON_RO_LOCALES as $locale) {
                $translatedTitle = $existing[$locale]['title'] ?? null;

                if ($translatedTitle === null || $translatedTitle === '') {
                    $missingTitleTranslations[] = sprintf('%d/%s', $catId, $locale);

                    continue;
                }

                $proposedSlug = $this->slugger
                    ->slug($translatedTitle, '-', $locale)
                    ->lower()
                    ->toString();

                $entry['translations'][$locale] = [
                    'title' => $translatedTitle,
                    'proposed_slug' => $proposedSlug,
                ];
            }

            $proposals[] = $entry;
        }

        $json = json_encode($proposals, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $io->error('Failed to encode JSON.');

            return Command::FAILURE;
        }

        if ($outputPath !== null && $outputPath !== '') {
            $bytes = file_put_contents($outputPath, $json . "\n");
            if ($bytes === false) {
                $io->error("Failed to write to {$outputPath}");

                return Command::FAILURE;
            }
            $io->success(sprintf('Wrote %d categories to %s (%d bytes)', \count($proposals), $outputPath, $bytes));
        } else {
            $output->writeln($json);
        }

        if ($missingTitleTranslations !== []) {
            $io->warning(sprintf(
                'Missing title translations for %d (category_id/locale) pair(s): %s',
                \count($missingTitleTranslations),
                implode(', ', $missingTitleTranslations),
            ));
        }

        if ($outputPath !== null) {
            $io->section('Summary');
            foreach ($proposals as $entry) {
                $line = sprintf('  %2d: %s', $entry['category_id'], $entry['ro_slug']);
                $parts = [];
                foreach (self::NON_RO_LOCALES as $locale) {
                    $proposed = $entry['translations'][$locale]['proposed_slug'] ?? '—';
                    $parts[] = sprintf('%s=%s', $locale, $proposed);
                }
                $io->writeln($line . '  →  ' . implode(', ', $parts));
            }
        }

        return Command::SUCCESS;
    }
}
