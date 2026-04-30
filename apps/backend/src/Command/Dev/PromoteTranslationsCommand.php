<?php

declare(strict_types=1);

namespace App\Command\Dev;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Service\Translation\ArticleTranslationCompletenessCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dev-data utility: promotes locales (en, ru) into Article::publishedLocales for
 * articles whose ext_translations are complete (title + lead + content) but
 * which were left at publishedLocales=['ro'] because the AI pipeline flagged
 * them needs_review and TranslationResultProcessor skipped auto-promotion.
 *
 * NOT for production. Refuses to run when APP_ENV=prod.
 *
 * Touches only Article::publishedLocales (via the existing idempotent setter
 * `addPublishedLocale`). Leaves needs_review and translationStatus untouched.
 */
#[AsCommand(
    name: 'app:dev:promote-translations',
    description: 'Promovează în publishedLocales locale-urile cu traduceri complete (dev/test only)',
)]
final class PromoteTranslationsCommand extends Command
{
    private const SUPPORTED_LOCALES = ['en', 'ru'];

    public function __construct(
        private readonly string $environment,
        private readonly EntityManagerInterface $em,
        private readonly ArticleRepository $articleRepository,
        private readonly ArticleTranslationCompletenessCheckerInterface $completenessChecker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Inspectează modificările fără a scrie în baza de date',
            )
            ->addOption(
                'locale',
                null,
                InputOption::VALUE_REQUIRED,
                'Limita la un singur locale: en, ru sau all',
                'all',
            )
            ->addOption(
                'article',
                null,
                InputOption::VALUE_REQUIRED,
                'Limita la un singur articol (ID numeric)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Promovare traduceri în publishedLocales');

        if ($this->environment !== 'dev' && $this->environment !== 'test') {
            $io->error(\sprintf(
                'Această comandă rulează DOAR în dev/test. Mediul curent: %s. Refuz execuția.',
                $this->environment,
            ));

            return Command::FAILURE;
        }

        $localeOption = (string) $input->getOption('locale');
        $locales = $this->resolveLocales($localeOption);
        if ($locales === null) {
            $io->error(\sprintf('Locale invalid: %s. Valori acceptate: en, ru, all.', $localeOption));

            return Command::FAILURE;
        }

        $articleOption = $input->getOption('article');
        $articles = $this->resolveArticles($articleOption);
        if ($articles === null) {
            $io->error(\sprintf('Articol inexistent pentru ID: %s', (string) $articleOption));

            return Command::FAILURE;
        }

        $isDryRun = (bool) $input->getOption('dry-run');

        if ($isDryRun) {
            $io->note('Mod dry-run: nu se scrie nimic în baza de date.');
        }

        $rows = [];
        $promoted = 0;
        $skippedAlready = 0;
        $skippedIncomplete = 0;

        foreach ($articles as $article) {
            $articleId = $article->getId();
            if ($articleId === null) {
                continue;
            }

            foreach ($locales as $locale) {
                if ($article->isPublishedInLocale($locale)) {
                    $rows[] = [$articleId, $locale, 'skipped (already published)', ''];
                    ++$skippedAlready;
                    continue;
                }

                if (!$this->completenessChecker->isComplete($article, $locale)) {
                    $missing = $this->completenessChecker->getMissingFields($article, $locale);
                    $rows[] = [
                        $articleId,
                        $locale,
                        'skipped (incomplete translation)',
                        implode(', ', $missing),
                    ];
                    ++$skippedIncomplete;
                    continue;
                }

                if (!$isDryRun) {
                    $article->addPublishedLocale($locale);
                }
                $rows[] = [$articleId, $locale, $isDryRun ? 'would promote' : 'promoted', ''];
                ++$promoted;
            }
        }

        if ($rows === []) {
            $io->info('Nicio combinație articol/locale de procesat.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Article ID', 'Locale', 'Action', 'Missing fields'],
            $rows,
        );

        $io->section('Sumar');
        $io->listing([
            \sprintf('Promovate: %d', $promoted),
            \sprintf('Sărite (deja publicate): %d', $skippedAlready),
            \sprintf('Sărite (traducere incompletă): %d', $skippedIncomplete),
        ]);

        if ($promoted === 0) {
            $io->info('Niciun articol nou promovat. Starea publishedLocales rămâne neschimbată.');

            return Command::SUCCESS;
        }

        if ($isDryRun) {
            $io->warning(\sprintf('Dry-run: %d promovări NU au fost scrise în DB.', $promoted));

            return Command::SUCCESS;
        }

        $this->em->flush();
        $io->success(\sprintf('Au fost promovate %d locale-uri în publishedLocales.', $promoted));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    private function resolveLocales(string $option): ?array
    {
        if ($option === 'all') {
            return self::SUPPORTED_LOCALES;
        }

        if (!\in_array($option, self::SUPPORTED_LOCALES, true)) {
            return null;
        }

        return [$option];
    }

    /**
     * @return list<Article>|null
     */
    private function resolveArticles(mixed $articleOption): ?array
    {
        if ($articleOption === null || $articleOption === '') {
            return $this->articleRepository->findAll();
        }

        if (!\is_numeric($articleOption)) {
            return null;
        }

        $article = $this->articleRepository->find((int) $articleOption);
        if ($article === null) {
            return null;
        }

        return [$article];
    }
}
