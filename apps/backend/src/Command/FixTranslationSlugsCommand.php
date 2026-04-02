<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(
    name: 'app:fix:translation-slugs',
    description: 'Generate missing slug translations from existing translated titles in ext_translations',
)]
final class FixTranslationSlugsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be fixed without writing')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit number of articles to process', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');

        $io->title('Fix Translation Slugs');

        if ($dryRun) {
            $io->note('DRY RUN — no changes will be written');
        }

        $conn = $this->em->getConnection();

        // Find all translated articles that have a title but no slug in ext_translations
        $sql = "
            SELECT t.foreign_key as article_id, t.locale, t.content as title
            FROM ext_translations t
            WHERE t.object_class = 'App\\Entity\\Article'
              AND t.field = 'title'
              AND t.content IS NOT NULL
              AND t.content != ''
              AND NOT EXISTS (
                  SELECT 1 FROM ext_translations s
                  WHERE s.object_class = t.object_class
                    AND s.foreign_key = t.foreign_key
                    AND s.locale = t.locale
                    AND s.field = 'slug'
              )
            ORDER BY t.foreign_key, t.locale
        ";

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        $rows = $conn->fetchAllAssociative($sql);

        if (empty($rows)) {
            $io->success('All translated articles already have slug translations.');

            return Command::SUCCESS;
        }

        $io->info(\sprintf('Found %d missing slug translations', \count($rows)));

        $sluggerEn = new AsciiSlugger('en');
        $sluggerRu = new AsciiSlugger('ru');

        $fixed = 0;
        $errors = 0;

        foreach ($rows as $row) {
            $articleId = $row['article_id'];
            $locale = $row['locale'];
            $title = $row['title'];

            $slugger = $locale === 'ru' ? $sluggerRu : $sluggerEn;
            $slug = $slugger->slug($title)->lower()->toString();

            if (empty($slug)) {
                $io->warning("Could not generate slug for article {$articleId} [{$locale}]: \"{$title}\"");
                ++$errors;

                continue;
            }

            if ($dryRun) {
                $io->writeln("  [{$locale}] Article {$articleId}: <info>{$slug}</info>");
            } else {
                try {
                    $conn->insert('ext_translations', [
                        'object_class' => 'App\\Entity\\Article',
                        'foreign_key' => $articleId,
                        'locale' => $locale,
                        'field' => 'slug',
                        'content' => $slug,
                    ]);
                    ++$fixed;
                } catch (\Throwable $e) {
                    $io->warning("Failed for article {$articleId} [{$locale}]: {$e->getMessage()}");
                    $this->logger->error('FixTranslationSlugs: insert failed', [
                        'articleId' => $articleId,
                        'locale' => $locale,
                        'error' => $e->getMessage(),
                    ]);
                    ++$errors;
                }
            }
        }

        if ($dryRun) {
            $io->success(\sprintf('Would fix %d slug translations (%d errors)', \count($rows), $errors));
        } else {
            $io->success(\sprintf('Fixed %d slug translations (%d errors)', $fixed, $errors));
        }

        return Command::SUCCESS;
    }
}
