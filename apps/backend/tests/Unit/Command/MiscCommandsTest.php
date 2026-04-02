<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\DatabaseQueryAuditCommand;
use App\Command\RedirectConsolidateCommand;
use App\Command\TestSlugGenerationCommand;
use App\Repository\UrlRedirectRepository;
use App\Service\SlugLookupService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class MiscCommandsTest extends TestCase
{
    // ── DatabaseQueryAuditCommand ───────────────────────────────────────────

    public function testDatabaseQueryAuditCommandName(): void
    {
        $command = $this->buildDatabaseQueryAuditCommand();
        $this->assertSame('app:db:audit', $command->getName());
    }

    public function testDatabaseQueryAuditCommandHasDescription(): void
    {
        $command = $this->buildDatabaseQueryAuditCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testDatabaseQueryAuditCommandHasOptions(): void
    {
        $command = $this->buildDatabaseQueryAuditCommand();
        $this->assertTrue($command->getDefinition()->hasOption('endpoint'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('locale'));
    }

    // ── TestSlugGenerationCommand ───────────────────────────────────────────

    public function testTestSlugGenerationCommandName(): void
    {
        $command = $this->buildTestSlugGenerationCommand();
        $this->assertSame('app:test:slug-generation', $command->getName());
    }

    public function testTestSlugGenerationCommandHasDescription(): void
    {
        $command = $this->buildTestSlugGenerationCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    // ── RedirectConsolidateCommand ──────────────────────────────────────────

    public function testRedirectConsolidateCommandName(): void
    {
        $command = $this->buildRedirectConsolidateCommand();
        $this->assertSame('app:redirects:consolidate', $command->getName());
    }

    public function testRedirectConsolidateCommandHasOptions(): void
    {
        $command = $this->buildRedirectConsolidateCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('min-chain-length'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    public function testRedirectConsolidateDryRunWithNoRedirects(): void
    {
        $redirectRepo = $this->createMock(UrlRedirectRepository::class);
        $redirectRepo->method('findAll')->willReturn([]);

        $command = $this->buildRedirectConsolidateCommand(redirectRepository: $redirectRepo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Consolidat', $output);
    }

    public function testRedirectConsolidateForceWithNoChains(): void
    {
        $redirectRepo = $this->createMock(UrlRedirectRepository::class);
        $redirectRepo->method('findAll')->willReturn([]);

        $command = $this->buildRedirectConsolidateCommand(redirectRepository: $redirectRepo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    private function buildDatabaseQueryAuditCommand(
        ?EntityManagerInterface $em = null,
        ?HttpClientInterface $httpClient = null,
    ): DatabaseQueryAuditCommand {
        return new DatabaseQueryAuditCommand(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $httpClient ?? $this->createStub(HttpClientInterface::class),
        );
    }

    private function buildTestSlugGenerationCommand(
        ?EntityManagerInterface $em = null,
    ): TestSlugGenerationCommand {
        return new TestSlugGenerationCommand(
            $em ?? $this->createStub(EntityManagerInterface::class),
        );
    }

    private function buildRedirectConsolidateCommand(
        ?UrlRedirectRepository $redirectRepository = null,
        ?SlugLookupService $slugLookupService = null,
        ?EntityManagerInterface $em = null,
    ): RedirectConsolidateCommand {
        return new RedirectConsolidateCommand(
            $redirectRepository ?? $this->createStub(UrlRedirectRepository::class),
            $slugLookupService ?? $this->createStub(SlugLookupService::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
        );
    }
}
