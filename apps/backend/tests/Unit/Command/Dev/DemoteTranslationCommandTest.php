<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Dev;

use App\Command\Dev\DemoteTranslationCommand;
use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class DemoteTranslationCommandTest extends TestCase
{
    public function testRefusesToRunInProd(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $repo = $this->createStub(ArticleRepository::class);

        $tester = $this->runCommand(
            new DemoteTranslationCommand('prod', $em, $repo),
            ['--article' => '5', '--locale' => 'en'],
        );

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('DOAR în dev/test', $tester->getDisplay());
    }

    public function testRequiresArticleFlag(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $repo = $this->createStub(ArticleRepository::class);

        $tester = $this->runCommand(
            new DemoteTranslationCommand('dev', $em, $repo),
            ['--locale' => 'en'],
        );

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('--article', $tester->getDisplay());
    }

    public function testRejectsInvalidLocale(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $repo = $this->createStub(ArticleRepository::class);

        // 'ro' is intentionally rejected: demoting RO would corrupt the
        // editorial source-of-truth invariant. Only en/ru are accepted.
        foreach (['fr', 'ro', '', 'all'] as $bad) {
            $tester = $this->runCommand(
                new DemoteTranslationCommand('dev', $em, $repo),
                ['--article' => '5', '--locale' => $bad],
            );

            $this->assertSame(
                Command::FAILURE,
                $tester->getStatusCode(),
                \sprintf('Locale %s should be rejected', $bad === '' ? '(empty)' : $bad),
            );
        }
    }

    public function testFailsWhenArticleNotFound(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $repo = $this->createMock(ArticleRepository::class);
        $repo->method('find')->with(9999)->willReturn(null);

        $tester = $this->runCommand(
            new DemoteTranslationCommand('dev', $em, $repo),
            ['--article' => '9999', '--locale' => 'en'],
        );

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('nu există', $tester->getDisplay());
    }

    public function testRemovesLocaleFromPublishedLocalesAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $article = $this->buildArticle(id: 5, publishedLocales: ['ro', 'en', 'ru']);
        $repo = $this->createMock(ArticleRepository::class);
        $repo->method('find')->with(5)->willReturn($article);

        $tester = $this->runCommand(
            new DemoteTranslationCommand('dev', $em, $repo),
            ['--article' => '5', '--locale' => 'en'],
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['ro', 'ru'], $article->getPublishedLocales());
        $this->assertStringContainsString('Locale en eliminat', $tester->getDisplay());
    }

    public function testIsIdempotentWhenLocaleAlreadyAbsent(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $article = $this->buildArticle(id: 5, publishedLocales: ['ro']);
        $repo = $this->createMock(ArticleRepository::class);
        $repo->method('find')->with(5)->willReturn($article);

        $tester = $this->runCommand(
            new DemoteTranslationCommand('dev', $em, $repo),
            ['--article' => '5', '--locale' => 'en'],
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['ro'], $article->getPublishedLocales());
        $this->assertStringContainsString('nu este publicat', $tester->getDisplay());
    }

    public function testDryRunDoesNotFlushOrMutate(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $article = $this->buildArticle(id: 5, publishedLocales: ['ro', 'en']);
        $repo = $this->createMock(ArticleRepository::class);
        $repo->method('find')->with(5)->willReturn($article);

        $tester = $this->runCommand(
            new DemoteTranslationCommand('dev', $em, $repo),
            ['--article' => '5', '--locale' => 'en', '--dry-run' => true],
        );

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['ro', 'en'], $article->getPublishedLocales(), 'In-memory entity untouched in dry-run');
        $this->assertStringContainsString('Dry-run', $tester->getDisplay());
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
    private function runCommand(DemoteTranslationCommand $command, array $input): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:dev:demote-translation'));
        $tester->execute($input);

        return $tester;
    }
}
