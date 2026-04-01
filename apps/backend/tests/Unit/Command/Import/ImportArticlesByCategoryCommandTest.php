<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportArticlesByCategoryCommand;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportArticlesByCategoryCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:articles-by-category', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('target'));
        $this->assertTrue($command->getDefinition()->hasOption('section'));
    }

    public function testExecuteWithNoSections(): void
    {
        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildCommand(defaultConnection: $defaultConn);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('articles', strtolower($output));
    }

    public function testExecuteWithCustomTarget(): void
    {
        $defaultConn = $this->createMock(Connection::class);
        $defaultConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildCommand(defaultConnection: $defaultConn);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--target' => '500']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('500', $tester->getDisplay());
    }

    private function buildCommand(
        ?EntityManagerInterface $em = null,
        ?Connection $newscoopConnection = null,
        ?Connection $defaultConnection = null,
        ?CategoryRepository $categoryRepository = null,
        ?AuthorRepository $authorRepository = null,
        ?LoggerInterface $logger = null,
    ): ImportArticlesByCategoryCommand {
        return new ImportArticlesByCategoryCommand(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $newscoopConnection ?? $this->createStub(Connection::class),
            $defaultConnection ?? $this->createStub(Connection::class),
            $categoryRepository ?? $this->createStub(CategoryRepository::class),
            $authorRepository ?? $this->createStub(AuthorRepository::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
