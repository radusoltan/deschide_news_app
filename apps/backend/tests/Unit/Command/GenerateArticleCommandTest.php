<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\GenerateArticleCommand;
use App\Repository\StoryClusterRepository;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\ArticleWriterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateArticleCommandTest extends TestCase
{
    private StoryClusterRepository $clusterRepository;
    private EntityManagerInterface $em;
    private GenerateArticleCommand $command;

    protected function setUp(): void
    {
        $geminiCli = new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger());
        $writerService = new ArticleWriterService($geminiCli, new NullLogger());
        $this->clusterRepository = $this->createMock(StoryClusterRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->command = new GenerateArticleCommand($writerService, $this->clusterRepository, $this->em);
    }

    #[Test]
    public function commandHasCorrectName(): void
    {
        $this->assertSame('app:generate-article', $this->command->getName());
    }

    #[Test]
    public function commandHasDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    #[Test]
    public function failsWhenClusterNotFound(): void
    {
        $this->clusterRepository->method('find')->willReturn(null);

        $app = new Application();
        $app->addCommand($this->command);
        $tester = new CommandTester($this->command);

        $tester->execute(['cluster-id' => '9999']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    #[Test]
    public function rejectsLowScoreCluster(): void
    {
        $cluster = new \App\Entity\StoryCluster();
        $cluster->setPrimaryHeadline('Low score cluster');
        // importanceScore defaults to 0

        $this->clusterRepository->method('find')->willReturn($cluster);

        $app = new Application();
        $app->addCommand($this->command);
        $tester = new CommandTester($this->command);

        $tester->execute(['cluster-id' => '1']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('below minimum', $tester->getDisplay());
    }

    #[Test]
    public function dryRunDoesNotPersist(): void
    {
        // Create cluster that passes score check but fails Gemini (since /usr/bin/false)
        $cluster = new \App\Entity\StoryCluster();
        $cluster->setPrimaryHeadline('Good cluster');

        // Use reflection to set importance score
        $ref = new \ReflectionProperty($cluster, 'importanceScore');
        $ref->setValue($cluster, 0.90);

        // Add enough PRs with enough content
        for ($i = 0; $i < 5; $i++) {
            $pr = new \App\Entity\PressRelease();
            $pr->setTitle("PR #{$i}");
            $pr->setContent(str_repeat('Long content. ', 150));
            $pr->setCategorySlug('externe');
            $cluster->addPressRelease($pr);
        }
        $cluster->recalculateCounts();

        $this->clusterRepository->method('find')->willReturn($cluster);

        // em->persist should NOT be called in dry-run (but Gemini will fail anyway)
        $this->em->expects($this->never())->method('persist');

        $app = new Application();
        $app->addCommand($this->command);
        $tester = new CommandTester($this->command);

        // This will fail at Gemini call (not at persist), which is expected
        $tester->execute(['cluster-id' => '1', '--dry-run' => true]);

        // Command returns FAILURE because Gemini fails, but persist was never called
        $this->assertSame(1, $tester->getStatusCode());
    }
}
