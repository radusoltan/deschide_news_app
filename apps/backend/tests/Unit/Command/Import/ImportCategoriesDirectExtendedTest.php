<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportCategoriesDirectCommand;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportCategoriesDirectExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:categories-direct', $command->getName());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('clear'));
    }

    public function testDryRunWithNoSections(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildCommand(newscoopConnection: $newscoopConn);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('0 section records', $output);
    }

    public function testDryRunWithOneSection(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            [
                'Number' => 1,
                'IdLanguage' => 2,
                'Name' => 'Stiri',
                'ShortName' => 'stiri',
                'Description' => 'Stiri locale',
            ],
        ]);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopConn,
            slugger: new AsciiSlugger(),
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('1 unique sections', $output);
    }

    public function testDryRunSkipsAlreadyImportedSection(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            [
                'Number' => 1,
                'IdLanguage' => 2,
                'Name' => 'Stiri',
                'ShortName' => 'stiri',
                'Description' => '',
            ],
        ]);

        $defaultConn = $this->createMock(Connection::class);
        // fetchOne returns an existing mapping ID
        $defaultConn->method('fetchOne')->willReturn(5);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
            slugger: new AsciiSlugger(),
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipped (already exist)', $output);
    }

    public function testDryRunWithMultipleLanguagesGrouped(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            ['Number' => 1, 'IdLanguage' => 2, 'Name' => 'Stiri', 'ShortName' => 'stiri', 'Description' => ''],
            ['Number' => 1, 'IdLanguage' => 1, 'Name' => 'News', 'ShortName' => 'news', 'Description' => ''],
            ['Number' => 1, 'IdLanguage' => 15, 'Name' => 'Новости', 'ShortName' => 'novosti', 'Description' => ''],
        ]);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopConn,
            slugger: new AsciiSlugger(),
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        // 3 records → 1 unique section (grouped by Number)
        $this->assertStringContainsString('1 unique sections', $output);
        $this->assertStringContainsString('Categories imported', $output);
    }

    public function testClearOptionDeletesMappings(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->expects($this->once())->method('executeStatement')->willReturn(0);

        $command = $this->buildCommand(
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--clear' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Clearing existing', $output);
    }

    private function buildCommand(
        ?Connection $newscoopConnection = null,
        ?Connection $defaultConnection = null,
        ?SluggerInterface $slugger = null,
        ?LoggerInterface $logger = null,
    ): ImportCategoriesDirectCommand {
        return new ImportCategoriesDirectCommand(
            $this->createStub(EntityManagerInterface::class),
            $newscoopConnection ?? $this->createStub(Connection::class),
            $defaultConnection ?? $this->createStub(Connection::class),
            $slugger ?? $this->createStub(SluggerInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
