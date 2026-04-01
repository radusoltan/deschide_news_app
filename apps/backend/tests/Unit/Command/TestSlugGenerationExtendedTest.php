<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\TestSlugGenerationCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class TestSlugGenerationExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = new TestSlugGenerationCommand(
            $this->createStub(EntityManagerInterface::class),
        );
        $this->assertSame('app:test:slug-generation', $command->getName());
    }

    public function testCommandDescription(): void
    {
        $command = new TestSlugGenerationCommand(
            $this->createStub(EntityManagerInterface::class),
        );
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandExecuteCallsPersistAndFlush(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        // The command creates and persists one Category per test case (5 test cases)
        $em->expects($this->atLeast(5))->method('persist');
        // flush is called twice per iteration: once to persist, once to remove
        $em->expects($this->atLeast(5))->method('flush');
        // remove is also called once per iteration
        $em->expects($this->atLeast(5))->method('remove');

        $command = new TestSlugGenerationCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        // The slug generation will fail (no real DB) — actual slug will be null/empty
        // But the command should complete (either 0 or 1 based on slug comparison)
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Slug Generation', $output);
    }

    public function testCommandOutputContainsTable(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $command = new TestSlugGenerationCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        // The table is always displayed
        $this->assertStringContainsString('Romanian', $output);
    }
}
