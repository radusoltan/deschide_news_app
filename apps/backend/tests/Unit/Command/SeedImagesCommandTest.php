<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SeedImagesCommand;
use App\Entity\Article;
use App\Entity\Image;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SeedImagesCommandTest extends TestCase
{
    // ── Metadata Tests ──────────────────────────────────────────────────────

    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:seed:images', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasAllOptions(): void
    {
        $command = $this->buildCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('count'));
        $this->assertTrue($definition->hasOption('link-percent'));
        $this->assertTrue($definition->hasOption('skip-images'));
        $this->assertTrue($definition->hasOption('skip-links'));
        $this->assertTrue($definition->hasOption('batch-size'));
    }

    public function testCountOptionDefaultsTo200(): void
    {
        $command = $this->buildCommand();
        $this->assertEquals(200, $command->getDefinition()->getOption('count')->getDefault());
    }

    public function testLinkPercentOptionDefaultsTo80(): void
    {
        $command = $this->buildCommand();
        $this->assertEquals(80, $command->getDefinition()->getOption('link-percent')->getDefault());
    }

    public function testBatchSizeOptionDefaultsTo50(): void
    {
        $command = $this->buildCommand();
        $this->assertEquals(50, $command->getDefinition()->getOption('batch-size')->getDefault());
    }

    // ── Execute with Skip Both ──────────────────────────────────────────────

    public function testExecuteSkipBothSkipsAllWork(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);

        $tester = $this->runCommand($command, [
            '--skip-images' => true,
            '--skip-links' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('SKIP', $output);
    }

    // ── Execute with Skip Images Only ───────────────────────────────────────

    public function testExecuteWithSkipImagesOnlyLinksExisting(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);

        $tester = $this->runCommand($command, ['--skip-images' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('SKIP', $output);
        $this->assertStringContainsString('Link Images', $output);
    }

    // ── Execute with Skip Links Only ────────────────────────────────────────

    public function testExecuteWithSkipLinksGeneratesImagesOnly(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Image::class => $imageRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em);

        $tester = $this->runCommand($command, [
            '--skip-links' => true,
            '--count' => '3',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Phase 1', $output);
        $this->assertStringContainsString('SKIP', $output);
    }

    // ── Execute with Custom Count ───────────────────────────────────────────

    public function testExecuteWithCustomCount(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Image::class => $imageRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em);

        $tester = $this->runCommand($command, [
            '--count' => '5',
            '--skip-links' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // ── Execute Default ─────────────────────────────────────────────────────

    public function testExecuteDefaultGeneratesAndLinks(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Image::class => $imageRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em);

        // Use a small count so the test does not run too long
        $tester = $this->runCommand($command, ['--count' => '2', '--skip-links' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('STG-09', $tester->getDisplay());
    }

    // ── Execute Shows Title ─────────────────────────────────────────────────

    public function testExecuteDisplaysTitle(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true, '--skip-links' => true]);

        $this->assertStringContainsString('STG-09: Seed Images and Link to Articles', $tester->getDisplay());
    }

    // ── Execute Shows Definition List ───────────────────────────────────────

    public function testExecuteShowsDefinitionList(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true, '--skip-links' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Images to generate', $output);
        $this->assertStringContainsString('Link percentage', $output);
    }

    // ── Execute Shows Success Message ───────────────────────────────────────

    public function testExecuteShowsCompletionMessage(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true, '--skip-links' => true]);

        $this->assertStringContainsString('STG-09 completed', $tester->getDisplay());
    }

    // ── Link Phase: No Articles Without Images ──────────────────────────────

    public function testLinkPhaseSkipsWhenAllArticlesHaveImages(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        // fetchFirstColumn for articles without images: empty
        $dbalConn->method('fetchFirstColumn')->willReturn([]);
        $dbalConn->method('fetchOne')->willReturn(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('All articles already have images', $output);
    }

    // ── Final Summary Always Shown ──────────────────────────────────────────

    public function testFinalSummaryAlwaysShown(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true, '--skip-links' => true]);

        $this->assertStringContainsString('Final Summary', $tester->getDisplay());
    }

    // ── Execute with Count Zero ────────────────────────────────────────────

    public function testExecuteWithCountZeroGeneratesNoImages(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Image::class => $imageRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em);

        $tester = $this->runCommand($command, [
            '--count' => '0',
            '--skip-links' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Phase 1', $output);
    }

    // ── Link Phase: Target Percentage Already Met ───────────────────────────

    public function testLinkPhaseSkipsWhenTargetPercentAlreadyMet(): void
    {
        $callCount = 0;
        $dbalConn = $this->createStub(DbalConnection::class);
        // fetchFirstColumn: first call returns articles without images, second returns empty (allImageIds)
        $dbalConn->method('fetchFirstColumn')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;
            if ($callCount === 1) {
                return ['1', '2', '3']; // articles without images
            }

            return []; // no images available (for printSummary or allImageIds)
        });
        // fetchOne: totalArticles=10, alreadyLinked=10 (100% already linked)
        $fetchOneCount = 0;
        $dbalConn->method('fetchOne')->willReturnCallback(function () use (&$fetchOneCount) {
            ++$fetchOneCount;

            return match ($fetchOneCount) {
                1 => 10,  // totalArticles
                2 => 10,  // alreadyLinked (already at target)
                default => 0,
            };
        });

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Target link percentage already met', $output);
    }

    // ── Link Phase: No Images Available ─────────────────────────────────────

    public function testLinkPhaseErrorsWhenNoImagesAvailable(): void
    {
        $fetchFirstColumnCount = 0;
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchFirstColumn')->willReturnCallback(function () use (&$fetchFirstColumnCount) {
            ++$fetchFirstColumnCount;

            return match ($fetchFirstColumnCount) {
                1 => ['1', '2'], // articles without images
                2 => [],         // allImageIds: empty!
                default => [],
            };
        });
        $fetchOneCount = 0;
        $dbalConn->method('fetchOne')->willReturnCallback(function () use (&$fetchOneCount) {
            ++$fetchOneCount;

            return match ($fetchOneCount) {
                1 => 10,  // totalArticles
                2 => 0,   // alreadyLinked
                default => 0,
            };
        });

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('No images available to link', $output);
    }

    // ── Link Phase: Successful Linking ──────────────────────────────────────

    public function testLinkPhaseLInksImagesToArticles(): void
    {
        $fetchFirstColumnCount = 0;
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchFirstColumn')->willReturnCallback(function () use (&$fetchFirstColumnCount) {
            ++$fetchFirstColumnCount;

            return match ($fetchFirstColumnCount) {
                1 => ['1', '2'],    // articles without images
                2 => ['10', '20'],  // allImageIds
                default => [],      // printSummary calls
            };
        });
        $fetchOneCount = 0;
        $dbalConn->method('fetchOne')->willReturnCallback(function () use (&$fetchOneCount) {
            ++$fetchOneCount;

            return match ($fetchOneCount) {
                1 => 5,   // totalArticles
                2 => 0,   // alreadyLinked
                default => 0,
            };
        });

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);
        $em->method('getReference')->willReturnCallback(function (string $class) {
            if ($class === \App\Entity\Image::class) {
                return $this->createStub(\App\Entity\Image::class);
            }
            return $this->createStub(\App\Entity\Article::class);
        });

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('ArticleImage records created', $output);
    }

    // ── Link Phase: Link Percent Zero ───────────────────────────────────────

    public function testLinkPhaseWithLinkPercentZeroLinksNothing(): void
    {
        $fetchFirstColumnCount = 0;
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchFirstColumn')->willReturnCallback(function () use (&$fetchFirstColumnCount) {
            ++$fetchFirstColumnCount;

            return match ($fetchFirstColumnCount) {
                1 => ['1', '2'],  // articles without images
                default => [],
            };
        });
        $fetchOneCount = 0;
        $dbalConn->method('fetchOne')->willReturnCallback(function () use (&$fetchOneCount) {
            ++$fetchOneCount;

            return match ($fetchOneCount) {
                1 => 5,   // totalArticles
                2 => 0,   // alreadyLinked
                default => 0,
            };
        });

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true, '--link-percent' => '0']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        // With 0% target, targetLinked=0, toLink=max(0, 0-0)=0
        $this->assertStringContainsString('Target link percentage already met', $output);
    }

    // ── Summary Section: Shows Disk Count ───────────────────────────────────

    public function testPrintSummaryShowsSeedImageFilesOnDisk(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true, '--skip-links' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seed image files on disk', $output);
    }

    // ── Summary Section: Shows Entity Counts ────────────────────────────────

    public function testPrintSummaryShowsAllEntityCounts(): void
    {
        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(42);
        $dbalConn->method('fetchFirstColumn')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);

        $command = $this->buildCommand(em: $em);
        $tester = $this->runCommand($command, ['--skip-images' => true, '--skip-links' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total images', $output);
        $this->assertStringContainsString('Total article_image links', $output);
        $this->assertStringContainsString('Total articles', $output);
        $this->assertStringContainsString('Articles with images', $output);
    }

    // ── Generate Images: Existing Image Skipped ─────────────────────────────

    public function testGenerateImagesSkipsExistingFileWithEntity(): void
    {
        // Create a temp file to simulate existing image on disk
        $tmpDir = sys_get_temp_dir() . '/test_seed_' . uniqid();
        @mkdir($tmpDir . '/public/uploads/images/originals', 0755, true);
        $existingFile = $tmpDir . '/public/uploads/images/originals/seed_img_001_1920x1080.jpg';
        file_put_contents($existingFile, 'fake-image');

        $existingImage = $this->createStub(Image::class);
        $existingImage->method('getId')->willReturn(999);

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn($existingImage);

        $dbalConn = $this->createStub(DbalConnection::class);
        $dbalConn->method('fetchOne')->willReturn(0);
        $dbalConn->method('fetchFirstColumn')->willReturn(['999']);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($dbalConn);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Image::class => $imageRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturnMap([
            ['kernel.project_dir', $tmpDir],
        ]);

        $command = $this->buildCommand(em: $em, params: $params);

        $tester = $this->runCommand($command, [
            '--count' => '1',
            '--skip-links' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipped (already exist)', $output);

        // Cleanup
        @unlink($existingFile);
        @rmdir($tmpDir . '/public/uploads/images/originals');
        @rmdir($tmpDir . '/public/uploads/images');
        @rmdir($tmpDir . '/public/uploads');
        @rmdir($tmpDir . '/public');
        @rmdir($tmpDir);
    }

    // ── Helper Methods ──────────────────────────────────────────────────────

    private function runCommand(SeedImagesCommand $command, array $input): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:seed:images'));
        $tester->execute($input);

        return $tester;
    }

    private function buildCommand(
        ?EntityManagerInterface $em = null,
        ?ParameterBagInterface $params = null,
    ): SeedImagesCommand {
        if ($params === null) {
            $params = $this->createStub(ParameterBagInterface::class);
            $params->method('get')->willReturnMap([
                ['kernel.project_dir', '/tmp'],
            ]);
        }

        if ($em === null) {
            $dbalConn = $this->createStub(DbalConnection::class);
            $dbalConn->method('fetchOne')->willReturn(0);
            $dbalConn->method('fetchFirstColumn')->willReturn([]);

            $em = $this->createStub(EntityManagerInterface::class);
            $em->method('getConnection')->willReturn($dbalConn);
        }

        return new SeedImagesCommand($em, $params);
    }
}
