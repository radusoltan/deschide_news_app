<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportAuthorsDirectCommand;
use App\Command\Import\ImportCategoriesDirectCommand;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\String\Slugger\SluggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportDirectCommandsTest extends TestCase
{
    // ── ImportAuthorsDirectCommand ──────────────────────────────────────────

    public function testImportAuthorsDirectCommandName(): void
    {
        $command = $this->buildImportAuthorsDirectCommand();
        $this->assertSame('app:import:authors-direct', $command->getName());
    }

    public function testImportAuthorsDirectCommandHasOptions(): void
    {
        $command = $this->buildImportAuthorsDirectCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
    }

    public function testImportAuthorsDirectDryRunWithNoAuthors(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildImportAuthorsDirectCommand(newscoopConnection: $newscoopConn);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Authors', $output);
    }

    public function testImportAuthorsDirectWithAuthors(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            ['id' => 1, 'first_name' => 'Ion', 'last_name' => 'Popescu', 'email' => 'ion@example.com'],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);

        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchOne')->willReturn(null);

        $command = $this->buildImportAuthorsDirectCommand(
            em: $em,
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
        );
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    // ── ImportCategoriesDirectCommand ────────────────────────────────────

    public function testImportCategoriesDirectCommandName(): void
    {
        $command = $this->buildImportCategoriesDirectCommand();
        $this->assertSame('app:import:categories-direct', $command->getName());
    }

    public function testImportCategoriesDirectCommandHasOptions(): void
    {
        $command = $this->buildImportCategoriesDirectCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('clear'));
    }

    public function testImportCategoriesDirectDryRunWithNoCategories(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildImportCategoriesDirectCommand(newscoopConnection: $newscoopConn);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('categori', strtolower($output));
    }

    private function buildImportAuthorsDirectCommand(
        ?EntityManagerInterface $em = null,
        ?Connection $newscoopConnection = null,
        ?Connection $defaultConnection = null,
        ?LoggerInterface $logger = null,
    ): ImportAuthorsDirectCommand {
        return new ImportAuthorsDirectCommand(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $newscoopConnection ?? $this->createStub(Connection::class),
            $defaultConnection ?? $this->createStub(Connection::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private function buildImportCategoriesDirectCommand(
        ?Connection $newscoopConnection = null,
    ): ImportCategoriesDirectCommand {
        return new ImportCategoriesDirectCommand(
            $this->createStub(EntityManagerInterface::class),
            $newscoopConnection ?? $this->createStub(Connection::class),
            $this->createStub(Connection::class),
            $this->createStub(SluggerInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }
}
