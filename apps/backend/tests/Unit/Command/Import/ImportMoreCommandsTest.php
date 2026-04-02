<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\GenerateThumbnailsCommand;
use App\Command\Import\ImportArticlesWithRelationsCommand;
use App\Command\Import\ImportAuthorsDirectCommand;
use App\Command\Import\ImportCategoriesDirectCommand;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Service\ImageService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportMoreCommandsTest extends TestCase
{
    // ── GenerateThumbnailsCommand ──────────────────────────────────────────

    public function testGenerateThumbnailsCommandName(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createStub(ImageService::class);
        $command = new GenerateThumbnailsCommand($em, $service);
        $this->assertSame('app:import:generate-thumbnails', $command->getName());
    }

    public function testGenerateThumbnailsCommandHasOptions(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createStub(ImageService::class);
        $command = new GenerateThumbnailsCommand($em, $service);
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('offset'));
        $this->assertTrue($command->getDefinition()->hasOption('batch-size'));
    }

    public function testGenerateThumbnailsWithNoProfiles(): void
    {
        $profileRepo = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $profileRepo->method('findAll')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($profileRepo);

        $service = $this->createStub(ImageService::class);

        $command = new GenerateThumbnailsCommand($em, $service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No thumbnail profiles', $tester->getDisplay());
    }

    // ── ImportArticlesWithRelationsCommand ─────────────────────────────────

    public function testImportArticlesWithRelationsCommandName(): void
    {
        $command = $this->buildImportWithRelationsCommand();
        $this->assertSame('app:import:articles-with-relations', $command->getName());
    }

    public function testImportArticlesWithRelationsCommandHasOptions(): void
    {
        $command = $this->buildImportWithRelationsCommand();
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('offset'));
    }

    public function testImportArticlesWithRelationsDryRunWithNoArticles(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $command = $this->buildImportWithRelationsCommand(newscoopConnection: $newscoopConn);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('articles', strtolower($output));
    }

    private function buildImportWithRelationsCommand(
        ?Connection $newscoopConnection = null,
    ): ImportArticlesWithRelationsCommand {
        return new ImportArticlesWithRelationsCommand(
            $this->createStub(EntityManagerInterface::class),
            $newscoopConnection ?? $this->createStub(Connection::class),
            $this->createStub(Connection::class),
            $this->createStub(CategoryRepository::class),
            $this->createStub(AuthorRepository::class),
            $this->createStub(LoggerInterface::class),
        );
    }
}
