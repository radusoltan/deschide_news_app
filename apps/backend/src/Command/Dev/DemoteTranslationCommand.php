<?php

declare(strict_types=1);

namespace App\Command\Dev;

use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dev fixture builder: removes a locale from Article::publishedLocales while
 * leaving the underlying ext_translations rows intact. Inverse of
 * `app:dev:promote-translations`. Re-running the promote command on the same
 * article restores the demoted locale (since the translation is still
 * complete).
 *
 * Use case: building e2e fixtures for ADR-029 fallback-redirect tests where
 * we need an article that has a complete EN/RU translation but is NOT
 * publishedInLocale(en|ru) — the precondition that makes the article page's
 * `fetchArticleWithFallback` produce isFallback=true.
 *
 * NOT for production. Refuses to run when APP_ENV=prod.
 *
 * Restricted to en/ru (RO is the default/source locale; demoting it here is
 * out of scope and would corrupt the editorial flow's invariant).
 */
#[AsCommand(
    name: 'app:dev:demote-translation',
    description: 'Elimină un locale din publishedLocales pentru un articol (fixture builder dev/test only)',
)]
final class DemoteTranslationCommand extends Command
{
    private const SUPPORTED_LOCALES = ['en', 'ru'];

    public function __construct(
        private readonly string $environment,
        private readonly EntityManagerInterface $em,
        private readonly ArticleRepository $articleRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'article',
                null,
                InputOption::VALUE_REQUIRED,
                'ID-ul articolului (obligatoriu)',
            )
            ->addOption(
                'locale',
                null,
                InputOption::VALUE_REQUIRED,
                'Locale-ul de eliminat din publishedLocales: en sau ru',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Inspectează modificarea fără a scrie în baza de date',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Demote translation locale (dev fixture builder)');

        if ($this->environment !== 'dev' && $this->environment !== 'test') {
            $io->error(\sprintf(
                'Această comandă rulează DOAR în dev/test. Mediul curent: %s. Refuz execuția.',
                $this->environment,
            ));

            return Command::FAILURE;
        }

        $articleOption = $input->getOption('article');
        if ($articleOption === null || $articleOption === '' || !\is_numeric($articleOption)) {
            $io->error('Flag --article=<id> este obligatoriu (ID numeric).');

            return Command::FAILURE;
        }

        $locale = $input->getOption('locale');
        if (!\is_string($locale) || !\in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $io->error(\sprintf(
                'Flag --locale obligatoriu, valori acceptate: %s. Primit: %s',
                implode(', ', self::SUPPORTED_LOCALES),
                \is_string($locale) ? $locale : '(empty)',
            ));

            return Command::FAILURE;
        }

        $article = $this->articleRepository->find((int) $articleOption);
        if ($article === null) {
            $io->error(\sprintf('Articolul cu ID %s nu există.', (string) $articleOption));

            return Command::FAILURE;
        }

        if (!$article->isPublishedInLocale($locale)) {
            $io->info(\sprintf(
                'Articolul %d nu este publicat în %s. Stare neschimbată.',
                (int) $articleOption,
                $locale,
            ));

            return Command::SUCCESS;
        }

        if ((bool) $input->getOption('dry-run')) {
            $io->warning(\sprintf(
                'Dry-run: aș fi eliminat %s din publishedLocales pentru articolul %d. Nu s-a scris nimic.',
                $locale,
                (int) $articleOption,
            ));

            return Command::SUCCESS;
        }

        $article->removePublishedLocale($locale);
        $this->em->flush();

        $io->success(\sprintf(
            'Locale %s eliminat din publishedLocales pentru articolul %d. Stare nouă: [%s].',
            $locale,
            (int) $articleOption,
            implode(', ', $article->getPublishedLocales()),
        ));

        return Command::SUCCESS;
    }
}
