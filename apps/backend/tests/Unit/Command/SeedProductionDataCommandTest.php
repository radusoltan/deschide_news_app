<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SeedProductionDataCommand;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SeedProductionDataCommandTest extends TestCase
{
    public function testStagingFreshSeedSucceeds(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchOne')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);
        $em->expects($this->once())->method('persist');
        $em->expects($this->atLeastOnce())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed_xyz');

        $tester = $this->runCommand($em, $hasher, [
            '--staging' => true,
            '--initial-password' => 'RotatedStrongPassword123',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('seeded', strtolower($tester->getDisplay()));
    }

    public function testStagingExistingUserWithoutForceAborts(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchOne')->willReturn('42');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $tester = $this->runCommand($em, $hasher, [
            '--staging' => true,
            '--initial-password' => 'RotatedStrongPassword123',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('already exists', $output);
        $this->assertStringContainsString('--force', $output);
    }

    public function testStagingExistingUserWithForceUpdatesPassword(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchOne')->willReturn('42');

        $mockUser = $this->createMock(User::class);
        $mockUser->expects($this->once())->method('setPassword')->with('hashed_new');

        $mockRepo = $this->createMock(EntityRepository::class);
        $mockRepo->method('findOneBy')->willReturn($mockUser);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);
        $em->method('getRepository')->willReturn($mockRepo);
        $em->expects($this->never())->method('persist');
        $em->expects($this->atLeastOnce())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed_new');

        $tester = $this->runCommand($em, $hasher, [
            '--staging' => true,
            '--initial-password' => 'RotatedStrongPassword123',
            '--force' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('rotated', strtolower($tester->getDisplay()));
    }

    public function testWeakPasswordRejected(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getConnection');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $tester = $this->runCommand($em, $hasher, [
            '--staging' => true,
            '--initial-password' => 'short',
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('>=12 characters', $tester->getDisplay());
    }

    public function testLiteralPasswordRejected(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getConnection');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $tester = $this->runCommand($em, $hasher, [
            '--staging' => true,
            '--initial-password' => 'password',
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('rejected', strtolower($tester->getDisplay()));
    }

    public function testMissingPasswordOptionRejected(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getConnection');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);

        $tester = $this->runCommand($em, $hasher, [
            '--staging' => true,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('--initial-password', $tester->getDisplay());
    }

    public function testDefaultPathUnchanged(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchOne')->willReturn(false);
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $tester = $this->runCommand($em, $hasher, []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Production Staging: Seed Data', $output);
        $this->assertStringContainsString('Seeding', $output);
    }

    private function runCommand(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        array $input,
    ): CommandTester {
        $command = new SeedProductionDataCommand($em, $hasher);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:seed:production'));
        $tester->execute($input);

        return $tester;
    }
}
