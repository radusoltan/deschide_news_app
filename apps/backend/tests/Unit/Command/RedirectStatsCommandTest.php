<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\RedirectStatsCommand;
use App\Entity\UrlRedirect;
use App\Repository\UrlRedirectRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RedirectStatsCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = new RedirectStatsCommand($this->createStub(UrlRedirectRepository::class));
        $this->assertSame('app:redirects:stats', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = new RedirectStatsCommand($this->createStub(UrlRedirectRepository::class));
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = new RedirectStatsCommand($this->createStub(UrlRedirectRepository::class));
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('detailed'));
        $this->assertTrue($definition->hasOption('export'));
    }

    public function testDefaultFormatIsTable(): void
    {
        $command = new RedirectStatsCommand($this->createStub(UrlRedirectRepository::class));
        $option = $command->getDefinition()->getOption('format');
        $this->assertSame('table', $option->getDefault());
    }

    public function testFormatOptionHasShortcutF(): void
    {
        $command = new RedirectStatsCommand($this->createStub(UrlRedirectRepository::class));
        $option = $command->getDefinition()->getOption('format');
        $this->assertSame('f', $option->getShortcut());
    }

    // ── Table format tests ──

    public function testExecuteTableFormatWithEmptyData(): void
    {
        $repo = $this->buildRepoMock([]);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Redirect Statistics', $display);
        $this->assertStringContainsString('Overview', $display);
        $this->assertStringContainsString('Total Redirects', $display);
        $this->assertStringContainsString('Total Hits', $display);
    }

    public function testExecuteTableFormatWithRedirects(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getHitCount')->willReturn(25);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-10 days'));

        $repo = $this->buildRepoMock([$redirect], 25);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Redirects by Type', $display);
        $this->assertStringContainsString('Redirects by Age', $display);
    }

    public function testExecuteTableFormatShowsMostUsedSection(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getHitCount')->willReturn(50);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-5 days'));

        $repo = $this->buildRepoMock([$redirect], 50, [
            ['oldUrl' => '/old-path', 'newUrl' => '/new-path', 'hitCount' => 50],
        ]);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Most Used Redirects', $tester->getDisplay());
    }

    // ── JSON format tests ──

    public function testExecuteJsonFormatReturnsValidJson(): void
    {
        $repo = $this->buildRepoMock([]);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('overview', $data);
        $this->assertArrayHasKey('by_type', $data);
        $this->assertArrayHasKey('by_age', $data);
        $this->assertArrayHasKey('most_used', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testExecuteJsonFormatContainsOverviewMetrics(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getHitCount')->willReturn(10);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-15 days'));

        $repo = $this->buildRepoMock([$redirect], 10);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'json']);

        $data = json_decode($tester->getDisplay(), true);
        $overview = $data['overview'];
        $this->assertSame(1, $overview['total_redirects']);
        $this->assertSame(10, $overview['total_hits']);
        $this->assertSame(0, $overview['unused_redirects']);
        $this->assertEquals(0, $overview['unused_percentage']);
        $this->assertEquals(10.0, $overview['average_hits_per_redirect']);
    }

    // ── CSV format tests ──

    public function testExecuteCsvFormatContainsSections(): void
    {
        $repo = $this->buildRepoMock([]);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'csv']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Redirect Statistics Report', $display);
        $this->assertStringContainsString('OVERVIEW', $display);
        $this->assertStringContainsString('BY TYPE', $display);
        $this->assertStringContainsString('BY AGE', $display);
        $this->assertStringContainsString('Metric,Value', $display);
    }

    public function testExecuteCsvFormatIncludesOverviewData(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getHitCount')->willReturn(5);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-45 days'));

        $repo = $this->buildRepoMock([$redirect], 5);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'csv']);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Total Redirects,1', $display);
        $this->assertStringContainsString('Total Hits,5', $display);
    }

    // ── Invalid format tests ──

    public function testExecuteInvalidFormatReturnsFAILURE(): void
    {
        $repo = $this->createStub(UrlRedirectRepository::class);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'xml']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid format', $tester->getDisplay());
    }

    public function testExecuteExportWithTableFormatReturnsFAILURE(): void
    {
        $repo = $this->createStub(UrlRedirectRepository::class);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--export' => '/tmp/test.txt', '--format' => 'table']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Export requires json or csv format', $tester->getDisplay());
    }

    // ── Detailed flag tests ──

    public function testExecuteWithDetailedFlagIncludesExtraStats(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getHitCount')->willReturn(5);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-200 days'));
        $redirect->method('getHttpStatusCode')->willReturn(301);
        $redirect->method('getLocale')->willReturn('ro');

        $queryResult = $this->createStub(Query::class);
        $queryResult->method('getResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryResult);

        $repo = $this->createStub(UrlRedirectRepository::class);
        $repo->method('getStatistics')->willReturn([
            'total' => 1,
            'unused' => 0,
            'by_type' => ['article' => 1],
            'most_used' => [],
        ]);
        $repo->method('findAll')->willReturn([$redirect]);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--detailed' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('By Status Code', $display);
        $this->assertStringContainsString('By Locale', $display);
    }

    public function testExecuteDetailedJsonFormatIncludesStatusCodeAndLocale(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getHitCount')->willReturn(7);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-60 days'));
        $redirect->method('getHttpStatusCode')->willReturn(302);
        $redirect->method('getLocale')->willReturn('en');

        $queryResult = $this->createStub(Query::class);
        $queryResult->method('getResult')->willReturn([]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryResult);

        $repo = $this->createStub(UrlRedirectRepository::class);
        $repo->method('getStatistics')->willReturn([
            'total' => 1,
            'unused' => 0,
            'by_type' => ['article' => 1],
            'most_used' => [],
        ]);
        $repo->method('findAll')->willReturn([$redirect]);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'json', '--detailed' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        $this->assertArrayHasKey('by_status_code', $data);
        $this->assertArrayHasKey('by_locale', $data);
        $this->assertArrayHasKey('recent_activity', $data);
        $this->assertSame([302 => 1], $data['by_status_code']);
        $this->assertSame(['en' => 1], $data['by_locale']);
    }

    // ── Export flag tests ──

    public function testExecuteExportJsonToFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'redirect_stats_test_');
        @unlink($tmpFile);

        $repo = $this->buildRepoMock([]);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'json', '--export' => $tmpFile]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($tmpFile);
        $this->assertStringContainsString('exported', $tester->getDisplay());

        $content = file_get_contents($tmpFile);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('overview', $data);

        @unlink($tmpFile);
    }

    public function testExecuteExportCsvToFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'redirect_stats_csv_');
        @unlink($tmpFile);

        $repo = $this->buildRepoMock([]);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'csv', '--export' => $tmpFile]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($tmpFile);
        $this->assertStringContainsString('exported', $tester->getDisplay());

        $content = file_get_contents($tmpFile);
        $this->assertStringContainsString('OVERVIEW', $content);

        @unlink($tmpFile);
    }

    // ── Age distribution tests ──

    public function testAgeDistributionBuckets(): void
    {
        $redirects = [
            $this->makeRedirectStub(5, new \DateTimeImmutable('-10 days')),   // < 1 month
            $this->makeRedirectStub(3, new \DateTimeImmutable('-60 days')),   // 1-3 months
            $this->makeRedirectStub(2, new \DateTimeImmutable('-120 days')),  // 3-6 months
            $this->makeRedirectStub(8, new \DateTimeImmutable('-365 days')),  // > 6 months
        ];

        $repo = $this->buildRepoMock($redirects, 18);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'json']);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame(1, $data['by_age']['less_than_1_month']);
        $this->assertSame(1, $data['by_age']['1_to_3_months']);
        $this->assertSame(1, $data['by_age']['3_to_6_months']);
        $this->assertSame(1, $data['by_age']['more_than_6_months']);
    }

    public function testOverviewUnusedPercentageCalculation(): void
    {
        $redirect = $this->makeRedirectStub(0, new \DateTimeImmutable('-5 days'));

        $repo = $this->createStub(UrlRedirectRepository::class);
        $repo->method('getStatistics')->willReturn([
            'total' => 4,
            'unused' => 1,
            'by_type' => ['article' => 4],
            'most_used' => [],
        ]);
        $repo->method('findAll')->willReturn([$redirect]);

        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'json']);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertEquals(25.0, $data['overview']['unused_percentage']);
    }

    public function testOverviewZeroTotalDoesNotDivideByZero(): void
    {
        $repo = $this->createStub(UrlRedirectRepository::class);
        $repo->method('getStatistics')->willReturn([
            'total' => 0,
            'unused' => 0,
            'by_type' => [],
            'most_used' => [],
        ]);
        $repo->method('findAll')->willReturn([]);

        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'json']);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame(0, $data['overview']['unused_percentage']);
        $this->assertSame(0, $data['overview']['average_hits_per_redirect']);
    }

    // ── CSV with most_used data ──

    public function testCsvFormatIncludesMostUsedSection(): void
    {
        $redirect = $this->makeRedirectStub(100, new \DateTimeImmutable('-10 days'));

        $repo = $this->createStub(UrlRedirectRepository::class);
        $repo->method('getStatistics')->willReturn([
            'total' => 1,
            'unused' => 0,
            'by_type' => ['article' => 1],
            'most_used' => [
                ['oldUrl' => '/old-path', 'newUrl' => '/new-path', 'hitCount' => 100],
            ],
        ]);
        $repo->method('findAll')->willReturn([$redirect]);

        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'csv']);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('MOST USED REDIRECTS', $display);
        $this->assertStringContainsString('Old URL,New URL,Hits', $display);
    }

    // ── Detailed with recent activity data in table format ──

    public function testDetailedTableFormatShowsRecentActivity(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getHitCount')->willReturn(10);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-10 days'));
        $redirect->method('getHttpStatusCode')->willReturn(301);
        $redirect->method('getLocale')->willReturn('ro');
        $redirect->method('getOldUrl')->willReturn('/old');
        $redirect->method('getNewUrl')->willReturn('/new');
        $redirect->method('getLastAccessedAt')->willReturn(new \DateTimeImmutable());

        $queryResult = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $queryResult->method('getResult')->willReturn([$redirect]);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($queryResult);

        $repo = $this->createStub(UrlRedirectRepository::class);
        $repo->method('getStatistics')->willReturn([
            'total' => 1, 'unused' => 0,
            'by_type' => ['article' => 1], 'most_used' => [],
        ]);
        $repo->method('findAll')->willReturn([$redirect]);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--detailed' => true]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Recent Activity', $display);
    }

    // ── JSON export (not to file, just stdout) without export flag ──

    public function testJsonOutputGoesToStdoutWhenNoExportFlag(): void
    {
        $repo = $this->buildRepoMock([]);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'json']);

        $display = $tester->getDisplay();
        // Should contain JSON, not "exported to" message
        $this->assertStringNotContainsString('exported', $display);
        $data = json_decode($display, true);
        $this->assertIsArray($data);
    }

    // ── CSV output to stdout ──

    public function testCsvOutputGoesToStdoutWhenNoExportFlag(): void
    {
        $repo = $this->buildRepoMock([]);
        $tester = $this->createTester(new RedirectStatsCommand($repo));
        $tester->execute(['--format' => 'csv']);

        $display = $tester->getDisplay();
        $this->assertStringNotContainsString('exported', $display);
        $this->assertStringContainsString('OVERVIEW', $display);
    }

    // ── Helpers ──

    private function makeRedirectStub(int $hitCount, \DateTimeImmutable $createdAt): UrlRedirect
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getHitCount')->willReturn($hitCount);
        $redirect->method('getCreatedAt')->willReturn($createdAt);

        return $redirect;
    }

    private function buildRepoMock(array $redirects, int $totalHits = 0, array $mostUsed = []): UrlRedirectRepository
    {
        $repo = $this->createStub(UrlRedirectRepository::class);
        $repo->method('getStatistics')->willReturn([
            'total' => \count($redirects),
            'unused' => 0,
            'by_type' => ['article' => \count($redirects)],
            'most_used' => $mostUsed,
        ]);
        $repo->method('findAll')->willReturn($redirects);

        return $repo;
    }

    private function createTester(RedirectStatsCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:redirects:stats'));
    }
}
