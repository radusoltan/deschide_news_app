<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\FixArticleCategoriesCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class FixArticleCategoriesExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:fix-article-categories', $command->getName());
    }

    public function testCommandHasDryRunOption(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testDryRunWithNoArticles(): void
    {
        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildCommand(defaultConnection: $defaultConn);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('0 articles', $output);
    }

    public function testLiveRunWithNoArticles(): void
    {
        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildCommand(defaultConnection: $defaultConn);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Successfully fixed', $output);
    }

    public function testDryRunWithArticlesWhereNewscoopArticleNotFound(): void
    {
        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchAllAssociative')->willReturn([
            [
                'article_id' => 1,
                'current_category_id' => 5,
                'newscoop_article_id' => 100,
                'current_category_name' => 'Stiri',
            ],
        ]);

        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn(false);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Errors', $output);
    }

    public function testDryRunWithArticlesWhereMappingMissing(): void
    {
        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchAllAssociative')->willReturn([
            [
                'article_id' => 1,
                'current_category_id' => 5,
                'newscoop_article_id' => 100,
                'current_category_name' => 'Stiri',
            ],
        ]);
        $defaultConn->method('fetchOne')->willReturn(false);

        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn(['NrSection' => 3]);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Missing section mapping', $output);
    }

    public function testDryRunWithArticlesAlreadyCorrect(): void
    {
        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchAllAssociative')->willReturn([
            [
                'article_id' => 1,
                'current_category_id' => 5,
                'newscoop_article_id' => 100,
                'current_category_name' => 'Stiri',
            ],
        ]);
        // fetchOne returns the same category id → already correct
        $defaultConn->method('fetchOne')->willReturn(5);

        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn(['NrSection' => 3]);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('already correct', $output);
    }

    public function testLiveRunWithArticlesFixed(): void
    {
        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchAllAssociative')->willReturn([
            [
                'article_id' => 1,
                'current_category_id' => 5,
                'newscoop_article_id' => 100,
                'current_category_name' => 'Stiri',
            ],
        ]);
        // Return a different category id → needs fixing
        $defaultConn->method('fetchOne')->willReturn(7);
        $defaultConn->expects($this->once())->method('update')->willReturn(1);

        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn(['NrSection' => 3]);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Successfully fixed', $output);
    }

    private function buildCommand(
        ?Connection $newscoopConnection = null,
        ?Connection $defaultConnection = null,
        ?LoggerInterface $logger = null,
    ): FixArticleCategoriesCommand {
        return new FixArticleCategoriesCommand(
            $newscoopConnection ?? $this->createStub(Connection::class),
            $defaultConnection ?? $this->createStub(Connection::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
