<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SeedLiveTextTemplatesCommand;
use App\Entity\LiveTextTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SeedLiveTextTemplatesCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedLiveTextTemplatesCommand($em);
        $this->assertSame('app:seed-livetext-templates', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedLiveTextTemplatesCommand($em);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasForceOption(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedLiveTextTemplatesCommand($em);
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    public function testExecuteSkipsWhenTemplatesExistWithoutForce(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('count')->willReturn(3);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(LiveTextTemplate::class)->willReturn($repo);

        $command = new SeedLiveTextTemplatesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('already exist', $tester->getDisplay());
    }

    public function testExecuteSeedsWhenNoTemplatesExist(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('count')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(LiveTextTemplate::class)->willReturn($repo);

        $command = new SeedLiveTextTemplatesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithForceRemovesExistingAndReseeds(): void
    {
        $existingTemplate = $this->createStub(LiveTextTemplate::class);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('count')->willReturn(2);
        $repo->method('findBy')->willReturn([$existingTemplate]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(LiveTextTemplate::class)->willReturn($repo);
        $em->expects($this->atLeastOnce())->method('remove');
        $em->expects($this->atLeastOnce())->method('persist');
        $em->expects($this->atLeastOnce())->method('flush');

        $command = new SeedLiveTextTemplatesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }
}
