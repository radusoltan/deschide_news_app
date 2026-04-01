<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportAuthorsDirectCommand;
use App\Entity\Author;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportAuthorsDirectExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:authors-direct', $command->getName());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
    }

    public function testDryRunWithNoAuthors(): void
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
        $this->assertStringContainsString('0 authors', $output);
    }

    public function testDryRunWithOneAuthor(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            ['id' => 1, 'first_name' => 'Ion', 'last_name' => 'Popescu', 'email' => 'ion@test.com', 'biography' => null],
        ]);

        $command = $this->buildCommand(newscoopConnection: $newscoopConn);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
        // In dry run, imported count is incremented without persisting
        $this->assertStringContainsString('1 authors', $output);
    }

    public function testDryRunWithAuthorNoEmail(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            ['id' => 99, 'first_name' => 'Ana', 'last_name' => 'Maria', 'email' => null, 'biography' => null],
        ]);

        $command = $this->buildCommand(newscoopConnection: $newscoopConn);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        // Should generate dummy email: author99@imported.deschide.md
        $this->assertStringContainsString('1 authors', $tester->getDisplay());
    }

    public function testSkipsAlreadyImportedAuthor(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            ['id' => 1, 'first_name' => 'Ion', 'last_name' => 'Popescu', 'email' => 'ion@test.com', 'biography' => null],
        ]);

        $defaultConn = $this->createMock(Connection::class);
        // fetchOne returns an existing ID → already imported
        $defaultConn->method('fetchOne')->willReturn(42);

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

    public function testSkipsDuplicateEmail(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            ['id' => 1, 'first_name' => 'Ion', 'last_name' => 'Popescu', 'email' => 'ion@test.com', 'biography' => null],
        ]);

        $defaultConn = $this->createMock(Connection::class);
        // fetchOne for mapping returns null (not imported yet)
        $defaultConn->method('fetchOne')->willReturn(null);

        $existingAuthor = $this->createStub(Author::class);

        $authorRepo = $this->createMock(EntityRepository::class);
        $authorRepo->method('findOneBy')->willReturn($existingAuthor);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($authorRepo);

        $command = $this->buildCommand(
            em: $em,
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipped (duplicate email)', $output);
    }

    public function testLiveRunImportsNewAuthor(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([
            ['id' => 1, 'first_name' => 'Ion', 'last_name' => 'Popescu', 'email' => 'ion@test.com', 'biography' => null],
        ]);

        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchOne')->willReturn(null);
        $defaultConn->expects($this->once())->method('insert')->with('newscoop_id_mapping');

        $authorRepo = $this->createMock(EntityRepository::class);
        $authorRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($authorRepo);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $command = $this->buildCommand(
            em: $em,
            newscoopConnection: $newscoopConn,
            defaultConnection: $defaultConn,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Successfully imported', $output);
    }

    private function buildCommand(
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
}
