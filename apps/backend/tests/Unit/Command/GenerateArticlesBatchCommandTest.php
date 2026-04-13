<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\GenerateArticlesBatchCommand;
use App\Repository\StoryClusterRepository;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\ArticleWriterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GenerateArticlesBatchCommandTest extends TestCase
{
    #[Test]
    public function commandHasCorrectName(): void
    {
        $command = $this->createCommand();
        $this->assertSame('app:generate-articles', $command->getName());
    }

    #[Test]
    public function commandHasDescription(): void
    {
        $command = $this->createCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    #[Test]
    public function commandHasExpectedOptions(): void
    {
        $command = $this->createCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('min-score'));
        $this->assertTrue($definition->hasOption('limit'));
        $this->assertTrue($definition->hasOption('dry-run'));
    }

    private function createCommand(): GenerateArticlesBatchCommand
    {
        $geminiCli = new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger());
        $writerService = new ArticleWriterService($geminiCli, new NullLogger());
        $clusterRepo = $this->createStub(StoryClusterRepository::class);
        $em = $this->createStub(EntityManagerInterface::class);

        return new GenerateArticlesBatchCommand($writerService, $clusterRepo, $em);
    }
}
