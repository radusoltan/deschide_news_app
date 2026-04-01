<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportCsvArticlesCommand;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Repository\ExternalArticleMappingRepository;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportCsvArticlesCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:csv-articles', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('file'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('offset'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('locale'));
    }

    public function testExecuteDryRunDisplaysConfiguration(): void
    {
        $command = $this->buildCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Import Articles from CSV', $output);
    }

    public function testExecuteDryRunWithSpecificFile(): void
    {
        $command = $this->buildCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true, '--file' => 'buchis.csv']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('buchis.csv', $tester->getDisplay());
    }

    public function testExecuteDryRunWithLocale(): void
    {
        $command = $this->buildCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true, '--locale' => 'en']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('en', $tester->getDisplay());
    }

    public function testExecuteDryRunWithLimit(): void
    {
        $command = $this->buildCommand();
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true, '--limit' => '10', '--offset' => '5']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('10', $output);
        $this->assertStringContainsString('5', $output);
    }

    private function buildCommand(): ImportCsvArticlesCommand
    {
        return new ImportCsvArticlesCommand(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ArticleRepository::class),
            $this->createStub(CategoryRepository::class),
            $this->createStub(AuthorRepository::class),
            $this->createStub(ImageRepository::class),
            $this->createStub(ExternalArticleMappingRepository::class),
            $this->createStub(SluggerInterface::class),
            $this->createStub(HttpClientInterface::class),
            $this->createStub(ManagerRegistry::class),
        );
    }
}
