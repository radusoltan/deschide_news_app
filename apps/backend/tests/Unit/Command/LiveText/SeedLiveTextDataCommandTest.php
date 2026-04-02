<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\LiveText;

use App\Command\LiveText\SeedLiveTextDataCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SeedLiveTextDataCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:livetext:seed', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testExecuteFailsWhenNoUsersFound(): void
    {
        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findAll')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($userRepo);

        $command = $this->buildCommand(entityManager: $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No users found', $tester->getDisplay());
    }

    public function testExecuteSucceedsWithUsersPresent(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getUsername')->willReturn('admin');

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findAll')->willReturn([$user]);

        $categoryRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $categoryRepo->method('findAll')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($userRepo, $categoryRepo) {
            if (str_contains($class, 'User')) {
                return $userRepo;
            }

            return $categoryRepo;
        });
        // persist() and flush() return void — no willReturn needed

        $command = $this->buildCommand(entityManager: $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('LiveText', $output);
    }

    private function buildCommand(
        ?EntityManagerInterface $entityManager = null,
    ): SeedLiveTextDataCommand {
        return new SeedLiveTextDataCommand(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
        );
    }
}
