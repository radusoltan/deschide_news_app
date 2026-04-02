<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\ImportCompleteCategoriesCommand;
use App\Entity\Category;
use App\Service\Import\ImportTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImportCompleteCategoriesExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:import:categories-complete', $command->getName());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('skip-translations'));
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    public function testFailsWhenJsonFileNotFound(): void
    {
        $command = $this->buildCommand();
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        // The JSON file doesn't exist in the test environment, so it should fail
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('JSON file not found', $tester->getDisplay());
    }

    public function testDryRunFailsWhenJsonFileNotFound(): void
    {
        $command = $this->buildCommand();
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('JSON file not found', $tester->getDisplay());
    }

    public function testCommandDescriptionIsNotEmpty(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    private function buildCommand(
        ?EntityManagerInterface $em = null,
        ?ImportTokenService $tokenService = null,
    ): ImportCompleteCategoriesCommand {
        return new ImportCompleteCategoriesCommand(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $tokenService ?? $this->createStub(ImportTokenService::class),
        );
    }
}
