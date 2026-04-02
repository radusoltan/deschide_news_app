<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\RedirectConsolidateCommand;
use App\Entity\UrlRedirect;
use App\Repository\UrlRedirectRepository;
use App\Service\SlugLookupService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class RedirectConsolidateExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:redirects:consolidate', $command->getName());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('min-chain-length'));
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    public function testDryRunWithNoRedirects(): void
    {
        $repo = $this->createStub(UrlRedirectRepository::class);
        $repo->method('findAll')->willReturn([]);

        $command = $this->buildCommand(redirectRepository: $repo);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No redirect chains', $tester->getDisplay());
    }

    public function testForceWithNoRedirects(): void
    {
        $repo = $this->createStub(UrlRedirectRepository::class);
        $repo->method('findAll')->willReturn([]);

        $command = $this->buildCommand(redirectRepository: $repo);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testDryRunWithShortChains(): void
    {
        // A redirect where the chain length = 1 (below the default min of 3)
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getOldUrl')->willReturn('/short-url');

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('findAll')->willReturn([$redirect]);

        $slugLookup = $this->createMock(SlugLookupService::class);
        $slugLookup->method('getRedirectChain')->willReturn([
            'chain_length' => 1,
            'final_url' => '/final',
            'redirects' => [$redirect],
        ]);

        $command = $this->buildCommand(redirectRepository: $repo, slugLookupService: $slugLookup);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No redirect chains', $tester->getDisplay());
    }

    public function testDryRunWithLongChains(): void
    {
        $redirect1 = $this->createStub(UrlRedirect::class);
        $redirect1->method('getOldUrl')->willReturn('/url-a');
        $redirect1->method('getHitCount')->willReturn(5);

        $redirect2 = $this->createStub(UrlRedirect::class);
        $redirect2->method('getOldUrl')->willReturn('/url-b');
        $redirect2->method('getHitCount')->willReturn(3);

        $redirect3 = $this->createStub(UrlRedirect::class);
        $redirect3->method('getOldUrl')->willReturn('/url-c');
        $redirect3->method('getHitCount')->willReturn(1);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('findAll')->willReturn([$redirect1]);

        $slugLookup = $this->createMock(SlugLookupService::class);
        $slugLookup->method('getRedirectChain')->willReturn([
            'chain_length' => 3,
            'final_url' => '/final',
            'redirects' => [$redirect1, $redirect2, $redirect3],
        ]);

        $command = $this->buildCommand(redirectRepository: $repo, slugLookupService: $slugLookup);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Would consolidate', $output);
    }

    public function testForceConsolidationWithLongChains(): void
    {
        $redirect1 = $this->createMock(UrlRedirect::class);
        $redirect1->method('getOldUrl')->willReturn('/url-a');
        $redirect1->method('getHitCount')->willReturn(5);
        $redirect1->expects($this->once())->method('setNewUrl')->with('/final');
        $redirect1->expects($this->once())->method('setHitCount');

        $redirect2 = $this->createMock(UrlRedirect::class);
        $redirect2->method('getOldUrl')->willReturn('/url-b');
        $redirect2->method('getHitCount')->willReturn(3);

        $redirect3 = $this->createMock(UrlRedirect::class);
        $redirect3->method('getOldUrl')->willReturn('/url-c');
        $redirect3->method('getHitCount')->willReturn(1);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('findAll')->willReturn([$redirect1]);
        $repo->method('findOneBy')->willReturn($redirect1);

        $slugLookup = $this->createMock(SlugLookupService::class);
        $slugLookup->method('getRedirectChain')->willReturn([
            'chain_length' => 3,
            'final_url' => '/final',
            'redirects' => [$redirect1, $redirect2, $redirect3],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->atLeastOnce())->method('remove');
        $em->expects($this->once())->method('flush');

        $command = $this->buildCommand(
            redirectRepository: $repo,
            slugLookupService: $slugLookup,
            em: $em,
        );
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Successfully consolidated', $output);
    }

    public function testUserCancelsConsolidation(): void
    {
        $redirect1 = $this->createStub(UrlRedirect::class);
        $redirect1->method('getOldUrl')->willReturn('/url-a');
        $redirect1->method('getHitCount')->willReturn(5);

        $redirect2 = $this->createStub(UrlRedirect::class);
        $redirect2->method('getHitCount')->willReturn(3);

        $redirect3 = $this->createStub(UrlRedirect::class);
        $redirect3->method('getHitCount')->willReturn(1);

        $repo = $this->createMock(UrlRedirectRepository::class);
        $repo->method('findAll')->willReturn([$redirect1]);

        $slugLookup = $this->createMock(SlugLookupService::class);
        $slugLookup->method('getRedirectChain')->willReturn([
            'chain_length' => 3,
            'final_url' => '/final',
            'redirects' => [$redirect1, $redirect2, $redirect3],
        ]);

        $command = $this->buildCommand(redirectRepository: $repo, slugLookupService: $slugLookup);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->setInputs(['no']);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('cancelled', $tester->getDisplay());
    }

    private function buildCommand(
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
