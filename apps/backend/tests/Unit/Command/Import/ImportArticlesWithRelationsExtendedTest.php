<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportArticlesWithRelationsCommand;
use App\Entity\Category;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportArticlesWithRelationsExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:articles-with-relations', $command->getName());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('offset'));
    }

    public function testDryRunWithNoArticles(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildCommand(newscoopConnection: $newscoopConn);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No articles found', $tester->getDisplay());
    }

    public function testDryRunWithArticles(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            [
                'Number' => 101,
                'IdLanguage' => 2,
                'Name' => 'Test',
                'PublishDate' => '2024-01-15 10:00:00',
                'UploadDate' => '2024-01-15 09:00:00',
                'time_updated' => '2024-01-15 10:30:00',
                'Keywords' => 'test',
                'NrSection' => 3,
                'FTitlu' => 'Test Article Title',
                'Fsubtitlu' => 'Subtitle',
                'Flead' => 'Lead text here',
                'FContinut' => 'Article content goes here...',
                'FBREAKING_NEWS' => null,
                'FNEWS_ALERT' => null,
                'FFLASH' => null,
            ],
        ]);

        $command = $this->buildCommand(newscoopConnection: $newscoopConn);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('1', $output);
    }

    public function testDryRunWithBreakingNewsArticle(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            [
                'Number' => 102,
                'IdLanguage' => 2,
                'Name' => 'Breaking Test',
                'PublishDate' => '2024-02-01 08:00:00',
                'UploadDate' => null,
                'time_updated' => null,
                'Keywords' => null,
                'NrSection' => 1,
                'FTitlu' => 'Breaking News Title',
                'Fsubtitlu' => null,
                'Flead' => null,
                'FContinut' => 'Breaking content',
                'FBREAKING_NEWS' => '1',
                'FNEWS_ALERT' => null,
                'FFLASH' => null,
            ],
        ]);

        $command = $this->buildCommand(newscoopConnection: $newscoopConn);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('DRY RUN', $tester->getDisplay());
    }

    public function testDryRunSkipsAlreadyImportedArticle(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            [
                'Number' => 101,
                'IdLanguage' => 2,
                'Name' => 'Test',
                'PublishDate' => '2024-01-15 10:00:00',
                'UploadDate' => null,
                'time_updated' => null,
                'Keywords' => null,
                'NrSection' => 1,
                'FTitlu' => 'Test Title',
                'Fsubtitlu' => null,
                'Flead' => null,
                'FContinut' => 'Content',
                'FBREAKING_NEWS' => null,
                'FNEWS_ALERT' => null,
                'FFLASH' => null,
            ],
        ]);

        $defaultConn = $this->createMock(Connection::class);
        // fetchOne returns existing article mapping
        $defaultConn->method('fetchOne')->willReturn(55);

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
        $this->assertStringContainsString('Skipped (already exist)', $output);
    }

    public function testCommandWithCustomLimitAndOffset(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildCommand(newscoopConnection: $newscoopConn);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--limit' => '50', '--offset' => '100', '--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('50', $output);
        $this->assertStringContainsString('100', $output);
    }

    private function buildCommand(
        ?Connection $newscoopConnection = null,
        ?Connection $defaultConnection = null,
    ): ImportArticlesWithRelationsCommand {
        return new ImportArticlesWithRelationsCommand(
            $this->createStub(EntityManagerInterface::class),
            $newscoopConnection ?? $this->createStub(Connection::class),
            $defaultConnection ?? $this->createStub(Connection::class),
            $this->createStub(CategoryRepository::class),
            $this->createStub(AuthorRepository::class),
            $this->createStub(LoggerInterface::class),
        );
    }
}
