<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\RedirectHealthCommand;
use App\Entity\UrlRedirect;
use App\Repository\UrlRedirectRepository;
use App\Service\SlugLookupService;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RedirectHealthCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:redirects:health', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('check-limit'));
        $this->assertTrue($definition->hasOption('fail-on-warning'));
        $this->assertTrue($definition->hasOption('json'));
    }

    public function testDefaultCheckLimitIs100(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('check-limit');
        $this->assertSame('100', $option->getDefault());
    }

    // ── Healthy system (no issues) ──

    public function testExecuteHealthyNoIssues(): void
    {
        $repo = $this->buildHealthyRepoMock();
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('healthy', $tester->getDisplay());
    }

    public function testExecuteHealthyJsonStatus(): void
    {
        $repo = $this->buildHealthyRepoMock();
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($data);
        $this->assertSame('healthy', $data['status']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('issues', $data);
        $this->assertArrayHasKey('recommendations', $data);
        $this->assertEmpty($data['issues']);
    }

    public function testExecuteHealthyJsonAllChecksPassed(): void
    {
        $repo = $this->buildHealthyRepoMock();
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $checks = $data['checks'];

        $this->assertSame('pass', $checks['total_redirects']['status']);
        $this->assertSame('pass', $checks['unused_redirects']['status']);
        $this->assertSame('pass', $checks['old_redirects']['status']);
        $this->assertSame('pass', $checks['long_chains']['status']);
        $this->assertSame('pass', $checks['circular_chains']['status']);
        $this->assertSame('pass', $checks['self_redirects']['status']);
    }

    // ── High unused percentage (warning) ──

    public function testExecuteHighUnusedPercentageTriggersWarning(): void
    {
        $repo = $this->buildRepoMockWithUnused(100, 25); // 25% > 20% threshold
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('warning', $data['status']);
        $this->assertSame('warning', $data['checks']['unused_redirects']['status']);
        $this->assertSame(25, $data['checks']['unused_redirects']['value']);
        $this->assertNotEmpty($data['issues']);
    }

    public function testExecuteModerateUnusedPercentageTriggersInfoWarning(): void
    {
        $repo = $this->buildRepoMockWithUnused(100, 15); // 15% > 10% but <= 20%
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('warning', $data['status']);
        $this->assertSame('warning', $data['checks']['unused_redirects']['status']);

        // Find the issue with info severity
        $infoIssues = array_filter($data['issues'], fn ($i) => $i['severity'] === 'info');
        $this->assertNotEmpty($infoIssues);
    }

    public function testExecuteLowUnusedPercentageIsPass(): void
    {
        $repo = $this->buildRepoMockWithUnused(100, 5); // 5% < 10%
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('pass', $data['checks']['unused_redirects']['status']);
    }

    // ── Old redirects ──

    public function testExecuteWithOldUnusedRedirects(): void
    {
        // Build repo where the old_redirects query returns > 0
        $repo = $this->buildRepoMockWithOldRedirects(5);
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('info', $data['checks']['old_redirects']['status']);
        $this->assertSame(5, $data['checks']['old_redirects']['value']);

        $oldRedirectIssues = array_filter($data['issues'], fn ($i) => $i['type'] === 'old_redirects');
        $this->assertCount(1, $oldRedirectIssues);
    }

    public function testExecuteWithNoOldRedirectsPassesCheck(): void
    {
        $repo = $this->buildRepoMockWithOldRedirects(0);
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('pass', $data['checks']['old_redirects']['status']);
        $this->assertSame(0, $data['checks']['old_redirects']['value']);
    }

    // ── Long chains ──

    public function testExecuteWithLongChainsTriggersWarning(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getOldUrl')->willReturn('/old-url');

        $repo = $this->buildRepoMockWithRedirects([$redirect]);
        $service = $this->createMock(SlugLookupService::class);
        $service->method('getRedirectChain')->willReturn([
            'chain_length' => 5,
            'final_url' => '/final-url',
            'circular' => false,
        ]);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('warning', $data['status']);
        $this->assertSame('warning', $data['checks']['long_chains']['status']);
        $this->assertSame(1, $data['checks']['long_chains']['value']);
        $this->assertNotEmpty($data['checks']['long_chains']['sample']);
    }

    public function testExecuteWithNoLongChainsPassesCheck(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getOldUrl')->willReturn('/url');

        $repo = $this->buildRepoMockWithRedirects([$redirect]);
        $service = $this->createStub(SlugLookupService::class);
        $service->method('getRedirectChain')->willReturn([
            'chain_length' => 1,
            'final_url' => '/new-url',
            'circular' => false,
        ]);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('pass', $data['checks']['long_chains']['status']);
        $this->assertSame(0, $data['checks']['long_chains']['value']);
    }

    // ── Circular chains ──

    public function testExecuteWithCircularChainsReturnsCriticalAndFailure(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getOldUrl')->willReturn('/loop-url');

        $repo = $this->buildRepoMockWithRedirects([$redirect]);
        $service = $this->createStub(SlugLookupService::class);
        $service->method('getRedirectChain')->willReturn([
            'chain_length' => 2,
            'final_url' => '/loop-url',
            'circular' => true,
        ]);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('critical', $data['status']);
        $this->assertSame('critical', $data['checks']['circular_chains']['status']);
    }

    // ── Self redirects ──

    public function testExecuteWithSelfRedirectsReturnsCritical(): void
    {
        // Build mock that returns self_redirects count > 0
        $repo = $this->buildRepoMockWithSelfRedirects(3);
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('critical', $data['status']);
        $this->assertSame('critical', $data['checks']['self_redirects']['status']);
        $this->assertSame(3, $data['checks']['self_redirects']['value']);
    }

    // ── JSON output format ──

    public function testJsonOutputIncludesTimestamp(): void
    {
        $repo = $this->buildHealthyRepoMock();
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['timestamp']);
    }

    public function testJsonOutputDoesNotContainTitle(): void
    {
        $repo = $this->buildHealthyRepoMock();
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        // JSON output should not include the "Redirect System Health Check" title
        $display = $tester->getDisplay();
        // First line should be JSON opening brace
        $lines = explode("\n", trim($display));
        $this->assertSame('{', trim($lines[0]));
    }

    // ── --fail-on-warning flag ──

    public function testFailOnWarningWithWarningReturnsFAILURE(): void
    {
        $repo = $this->buildRepoMockWithUnused(100, 30);
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--fail-on-warning' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testFailOnWarningWithHealthyStatusReturnsSUCCESS(): void
    {
        $repo = $this->buildHealthyRepoMock();
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--fail-on-warning' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testWithoutFailOnWarningWithWarningReturnsSUCCESS(): void
    {
        $repo = $this->buildRepoMockWithUnused(100, 30);
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // ── Table output ──

    public function testTableOutputContainsHealthChecksSection(): void
    {
        $repo = $this->buildHealthyRepoMock();
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Redirect System Health Check', $display);
        $this->assertStringContainsString('Health Checks', $display);
        $this->assertStringContainsString('Health check completed at:', $display);
    }

    public function testTableOutputWithIssuesShowsRecommendations(): void
    {
        $repo = $this->buildRepoMockWithUnused(100, 30);
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Issues Found', $display);
        $this->assertStringContainsString('Recommendations', $display);
    }

    // ── Check limit option ──

    public function testCheckLimitLimitsNumberOfRedirectsChecked(): void
    {
        $redirects = [];
        for ($i = 0; $i < 5; ++$i) {
            $r = $this->createStub(UrlRedirect::class);
            $r->method('getOldUrl')->willReturn("/url-{$i}");
            $redirects[] = $r;
        }

        $repo = $this->buildRepoMockWithRedirects($redirects);

        $callCount = 0;
        $service = $this->createMock(SlugLookupService::class);
        $service->method('getRedirectChain')
            ->willReturnCallback(function () use (&$callCount) {
                ++$callCount;

                return ['chain_length' => 1, 'final_url' => '/dest', 'circular' => false];
            });

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true, '--check-limit' => '2']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertLessThanOrEqual(2, $callCount);
    }

    // ── Recommendations ──

    public function testRecommendationsIncludedForUnusedRedirects(): void
    {
        $repo = $this->buildRepoMockWithUnused(100, 25);
        $service = $this->createStub(SlugLookupService::class);

        $tester = $this->createTester(new RedirectHealthCommand($repo, $service));
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertNotEmpty($data['recommendations']);
        $hasCleanupRec = false;
        foreach ($data['recommendations'] as $rec) {
            if (str_contains($rec, 'redirects:cleanup')) {
                $hasCleanupRec = true;
            }
        }
        $this->assertTrue($hasCleanupRec);
    }

    // ── Helpers ──

    private function buildCommand(): RedirectHealthCommand
    {
        return new RedirectHealthCommand(
            $this->createStub(UrlRedirectRepository::class),
            $this->createStub(SlugLookupService::class),
        );
    }

    /**
     * Builds a repo mock that reports a healthy state:
     * - 10 total, 0 unused
     * - No old redirects (count query returns 0)
     * - No self-redirects (count query returns 0)
     * - No redirects in findAll (no chains to check)
     */
    private function buildHealthyRepoMock(): UrlRedirectRepository
    {
        return $this->buildRepoMockFull(10, 0, 0, 0, []);
    }

    private function buildRepoMockWithUnused(int $total, int $unused): UrlRedirectRepository
    {
        return $this->buildRepoMockFull($total, $unused, 0, 0, []);
    }

    private function buildRepoMockWithOldRedirects(int $oldCount): UrlRedirectRepository
    {
        return $this->buildRepoMockFull(10, 0, $oldCount, 0, []);
    }

    private function buildRepoMockWithSelfRedirects(int $selfCount): UrlRedirectRepository
    {
        return $this->buildRepoMockFull(10, 0, 0, $selfCount, []);
    }

    private function buildRepoMockWithRedirects(array $redirects): UrlRedirectRepository
    {
        return $this->buildRepoMockFull(\count($redirects), 0, 0, 0, $redirects);
    }

    /**
     * Builds a complete repo mock.
     *
     * The command issues two createQueryBuilder calls:
     * 1. For "old redirects" check (r.createdAt < date AND r.hitCount = 0)
     * 2. For "self redirects" check (r.oldUrl = r.newUrl)
     *
     * Both use getSingleScalarResult(). We use consecutive returns.
     */
    private function buildRepoMockFull(
        int $total,
        int $unused,
        int $oldRedirectsCount,
        int $selfRedirectsCount,
        array $redirects,
    ): UrlRedirectRepository {
        // Query that returns old redirect count then self redirect count
        $countQuery = $this->createStub(Query::class);
        $countQuery->method('getSingleScalarResult')
            ->willReturnOnConsecutiveCalls($oldRedirectsCount, $selfRedirectsCount);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($countQuery);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('getStatistics')->willReturn([
            'total' => $total,
            'unused' => $unused,
        ]);
        $repo->method('findAll')->willReturn($redirects);
        $repo->method('createQueryBuilder')->willReturn($qb);

        return $repo;
    }

    private function createTester(RedirectHealthCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:redirects:health'));
    }
}
