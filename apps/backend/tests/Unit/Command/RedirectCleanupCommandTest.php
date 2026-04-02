<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\RedirectCleanupCommand;
use App\Entity\UrlRedirect;
use App\Repository\UrlRedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class RedirectCleanupCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $repo = $this->createStub(UrlRedirectRepository::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new RedirectCleanupCommand($repo, $em);
        $this->assertSame('app:redirects:cleanup', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $repo = $this->createStub(UrlRedirectRepository::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new RedirectCleanupCommand($repo, $em);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $repo = $this->createStub(UrlRedirectRepository::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new RedirectCleanupCommand($repo, $em);
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('older-than'));
        $this->assertTrue($command->getDefinition()->hasOption('max-hits'));
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    private function buildQueryBuilderMock(array $results): QueryBuilder
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($results);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    public function testExecuteDryRunShowsNothingToDelete(): void
    {
        $qb = $this->buildQueryBuilderMock([]);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = new RedirectCleanupCommand($repo, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true, '--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Dry Run', $output);
    }

    public function testExecuteForceWithNoItems(): void
    {
        $qb = $this->buildQueryBuilderMock([]);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = new RedirectCleanupCommand($repo, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No redirects found', $tester->getDisplay());
    }

    public function testExecuteForceDeletesItems(): void
    {
        $redirect = $this->createMock(UrlRedirect::class);
        $redirect->method('getId')->willReturn(1);
        $redirect->method('getOldUrl')->willReturn('/old-url');
        $redirect->method('getNewUrl')->willReturn('/new-url');
        $redirect->method('getType')->willReturn('article');
        $redirect->method('getHitCount')->willReturn(0);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-200 days'));

        $qb = $this->buildQueryBuilderMock([$redirect]);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove');
        $em->expects($this->once())->method('flush');

        $command = new RedirectCleanupCommand($repo, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteDryRunWithItemsShowsWouldDelete(): void
    {
        $redirect = $this->createMock(UrlRedirect::class);
        $redirect->method('getId')->willReturn(1);
        $redirect->method('getOldUrl')->willReturn('/old-url');
        $redirect->method('getNewUrl')->willReturn('/new-url');
        $redirect->method('getType')->willReturn('article');
        $redirect->method('getHitCount')->willReturn(0);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-200 days'));

        $qb = $this->buildQueryBuilderMock([$redirect]);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = new RedirectCleanupCommand($repo, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $display);
        $this->assertStringContainsString('Would delete', $display);
    }

    public function testExecuteWithTypeFilter(): void
    {
        $qb = $this->buildQueryBuilderMock([]);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = new RedirectCleanupCommand($repo, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--type' => 'article', '--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('article', $display);
    }

    public function testExecuteNoForceUserCancels(): void
    {
        $redirect = $this->createMock(UrlRedirect::class);
        $redirect->method('getId')->willReturn(1);
        $redirect->method('getOldUrl')->willReturn('/old-url');
        $redirect->method('getNewUrl')->willReturn('/new-url');
        $redirect->method('getType')->willReturn('article');
        $redirect->method('getHitCount')->willReturn(0);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-200 days'));

        $qb = $this->buildQueryBuilderMock([$redirect]);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = new RedirectCleanupCommand($repo, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->setInputs(['no']);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('cancelled', $display);
    }

    public function testExecuteForceDeletesMultipleItems(): void
    {
        $redirects = [];
        for ($i = 1; $i <= 3; ++$i) {
            $r = $this->createMock(UrlRedirect::class);
            $r->method('getId')->willReturn($i);
            $r->method('getOldUrl')->willReturn("/old-{$i}");
            $r->method('getNewUrl')->willReturn("/new-{$i}");
            $r->method('getType')->willReturn('category');
            $r->method('getHitCount')->willReturn(0);
            $r->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-200 days'));
            $redirects[] = $r;
        }

        $qb = $this->buildQueryBuilderMock($redirects);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(3))->method('remove');

        $command = new RedirectCleanupCommand($repo, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Successfully deleted 3', $tester->getDisplay());
    }

    public function testMoreThan10ItemsShowsAndMoreMessage(): void
    {
        $redirects = [];
        for ($i = 1; $i <= 15; ++$i) {
            $r = $this->createMock(UrlRedirect::class);
            $r->method('getId')->willReturn($i);
            $r->method('getOldUrl')->willReturn("/old-{$i}");
            $r->method('getNewUrl')->willReturn("/new-{$i}");
            $r->method('getType')->willReturn('manual');
            $r->method('getHitCount')->willReturn(0);
            $r->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-200 days'));
            $redirects[] = $r;
        }

        $qb = $this->buildQueryBuilderMock($redirects);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = new RedirectCleanupCommand($repo, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('and 5 more', $tester->getDisplay());
    }
}
