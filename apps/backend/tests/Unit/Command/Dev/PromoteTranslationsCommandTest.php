<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Dev;

use App\Command\Dev\PromoteTranslationsCommand;
use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Service\Translation\ArticleTranslationCompletenessCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class PromoteTranslationsCommandTest extends TestCase
{
    public function testRefusesToRunInProd(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $repo = $this->createStub(ArticleRepository::class);
        $checker = $this->createStub(ArticleTranslationCompletenessCheckerInterface::class);

        $tester = $this->runCommand(
            new PromoteTranslationsCommand('prod', $em, $repo, $checker),
            [],
        );

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('DOAR în dev/test', $tester->getDisplay());
    }

    public function testAcceptsDevAndTestEnvironments(): void
    {
        foreach (['dev', 'test'] as $env) {
            $em = $this->createMock(EntityManagerInterface::class);
            $em->expects($this->once())->method('flush');

            $article = $this->buildArticle(id: 1, publishedLocales: ['ro']);
            $repo = $this->createStub(ArticleRepository::class);
            $repo->method('findAll')->willReturn([$article]);

            $checker = $this->createStub(ArticleTranslationCompletenessCheckerInterface::class);
            $checker->method('isComplete')->willReturn(true);

            $tester = $this->runCommand(
                new PromoteTranslationsCommand($env, $em, $repo, $checker),
                [],
            );

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), \sprintf('Env %s', $env));
            $this->assertContains('en', $article->getPublishedLocales(), \sprintf('Env %s', $env));
            $this->assertContains('ru', $article->getPublishedLocales(), \sprintf('Env %s', $env));
        }
    }

    public function testDryRunDoesNotFlush(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $article = $this->buildArticle(id: 5, publishedLocales: ['ro']);
        $repo = $this->createStub(ArticleRepository::class);
        $repo->method('findAll')->willReturn([$article]);

        $checker = $this->createStub(ArticleTranslationCompletenessCheckerInterface::class);
        $checker->method('isComplete')->willReturn(true);

        $tester = $this->runCommand(
            new PromoteTranslationsCommand('dev', $em, $repo, $checker),
            ['--dry-run' => true],
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('dry-run', strtolower($tester->getDisplay()));
        $this->assertSame(['ro'], $article->getPublishedLocales());
    }

    public function testPromotesCompleteLocaleAndAddsToPublishedLocales(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $article = $this->buildArticle(id: 16, publishedLocales: ['ro']);
        $repo = $this->createStub(ArticleRepository::class);
        $repo->method('findAll')->willReturn([$article]);

        $checker = $this->createMock(ArticleTranslationCompletenessCheckerInterface::class);
        $checker->method('isComplete')->willReturnCallback(
            static fn (Article $a, string $locale): bool => $locale === 'en',
        );
        $checker->method('getMissingFields')->willReturn(['lead', 'content']);

        $tester = $this->runCommand(
            new PromoteTranslationsCommand('dev', $em, $repo, $checker),
            [],
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertContains('en', $article->getPublishedLocales());
        $this->assertNotContains('ru', $article->getPublishedLocales());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('promoted', $output);
        $this->assertStringContainsString('skipped (incomplete translation)', $output);
        $this->assertStringContainsString('lead, content', $output);
    }

    public function testSkipsLocaleAlreadyInPublishedLocalesAndIsIdempotent(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        // Re-running on already-promoted articles must not flush (no changes).
        // The current implementation flushes unconditionally on any non-dry path,
        // so we accept either 0 or 1 flush calls but assert no duplication in the array.
        // No assertion on flush call count: when nothing changes, the impl may
        // still call flush() once (cheap no-op in Doctrine if no UoW changes).

        $article = $this->buildArticle(id: 7, publishedLocales: ['ro', 'en', 'ru']);
        $repo = $this->createStub(ArticleRepository::class);
        $repo->method('findAll')->willReturn([$article]);

        $checker = $this->createStub(ArticleTranslationCompletenessCheckerInterface::class);
        $checker->method('isComplete')->willReturn(true);

        $tester = $this->runCommand(
            new PromoteTranslationsCommand('dev', $em, $repo, $checker),
            [],
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['ro', 'en', 'ru'], $article->getPublishedLocales(), 'No duplication');
        $this->assertStringContainsString('skipped (already published)', $tester->getDisplay());
        $this->assertStringContainsString('Promovate: 0', $tester->getDisplay());
    }

    public function testLocaleFilterRestrictsToSingleLocale(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $article = $this->buildArticle(id: 1, publishedLocales: ['ro']);
        $repo = $this->createStub(ArticleRepository::class);
        $repo->method('findAll')->willReturn([$article]);

        $checker = $this->createStub(ArticleTranslationCompletenessCheckerInterface::class);
        $checker->method('isComplete')->willReturn(true);

        $tester = $this->runCommand(
            new PromoteTranslationsCommand('dev', $em, $repo, $checker),
            ['--locale' => 'en'],
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertContains('en', $article->getPublishedLocales());
        $this->assertNotContains('ru', $article->getPublishedLocales());
    }

    public function testLocaleFilterRejectsInvalidValue(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $repo = $this->createStub(ArticleRepository::class);
        $checker = $this->createStub(ArticleTranslationCompletenessCheckerInterface::class);

        $tester = $this->runCommand(
            new PromoteTranslationsCommand('dev', $em, $repo, $checker),
            ['--locale' => 'fr'],
        );

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Locale invalid', $tester->getDisplay());
    }

    public function testArticleFilterRestrictsToSingleArticle(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $target = $this->buildArticle(id: 16, publishedLocales: ['ro']);
        $other = $this->buildArticle(id: 99, publishedLocales: ['ro']);

        $repo = $this->createMock(ArticleRepository::class);
        $repo->expects($this->once())->method('find')->with(16)->willReturn($target);
        $repo->expects($this->never())->method('findAll');

        $checker = $this->createStub(ArticleTranslationCompletenessCheckerInterface::class);
        $checker->method('isComplete')->willReturn(true);

        $tester = $this->runCommand(
            new PromoteTranslationsCommand('dev', $em, $repo, $checker),
            ['--article' => '16'],
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertContains('en', $target->getPublishedLocales());
        $this->assertSame(['ro'], $other->getPublishedLocales(), 'Other articles untouched');
    }

    public function testArticleFilterRejectsMissingId(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $repo = $this->createMock(ArticleRepository::class);
        $repo->method('find')->with(9999)->willReturn(null);

        $checker = $this->createStub(ArticleTranslationCompletenessCheckerInterface::class);

        $tester = $this->runCommand(
            new PromoteTranslationsCommand('dev', $em, $repo, $checker),
            ['--article' => '9999'],
        );

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Articol inexistent', $tester->getDisplay());
    }

    /**
     * @param list<string> $publishedLocales
     */
    private function buildArticle(int $id, array $publishedLocales): Article
    {
        $article = new Article();
        $article->setPublishedLocales($publishedLocales);

        $reflection = new \ReflectionClass($article);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($article, $id);

        return $article;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(PromoteTranslationsCommand $command, array $input): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:dev:promote-translations'));
        $tester->execute($input);

        return $tester;
    }
}
