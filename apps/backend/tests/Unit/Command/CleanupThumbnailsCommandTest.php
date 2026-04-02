<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CleanupThumbnailsCommand;
use App\Entity\Image;
use App\Entity\Thumbnail;
use App\Entity\ThumbnailProfile;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class CleanupThumbnailsCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cleanup_thumbs_test_' . uniqid();
        mkdir($this->tmpDir . '/thumbnails', 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $this->removeDir($this->tmpDir);
        }
    }

    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:cleanup-thumbnails', $command->getName());
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
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('type'));
    }

    public function testDefaultTypeIsAll(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('type');
        $this->assertSame('all', $option->getDefault());
    }

    public function testTypeOptionHasShortcutT(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('type');
        $this->assertSame('t', $option->getShortcut());
    }

    // ── No orphaned files ──

    public function testExecuteWithNoOrphanedFilesReportsClean(): void
    {
        // Put a file on disk that IS in the DB
        file_put_contents($this->tmpDir . '/thumbnails/existing.webp', 'data');

        $thumbnail = $this->createStub(Thumbnail::class);
        $thumbnail->method('getFilename')->willReturn('existing.webp');
        $thumbnail->method('getImage')->willReturn($this->createStub(Image::class));
        $thumbnail->method('getProfile')->willReturn($this->createStub(ThumbnailProfile::class));
        $thumbnail->method('getId')->willReturn(1);

        $em = $this->buildEntityManager([], [$thumbnail]);
        $fs = new Filesystem();
        $params = $this->buildParams($this->tmpDir, 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('No orphaned thumbnail files found', $display);
        $this->assertStringContainsString('No broken thumbnail records found', $display);
        $this->assertStringContainsString('No cleanup needed', $display);
    }

    // ── Orphaned files (dry-run) ──

    public function testExecuteWithOrphanedFilesDryRunDoesNotDelete(): void
    {
        // Create an orphaned file on disk (not in DB)
        file_put_contents($this->tmpDir . '/thumbnails/orphan.webp', str_repeat('x', 1024));

        $em = $this->buildEntityManager([], []);

        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->never())->method('remove');

        $params = $this->buildParams($this->tmpDir, 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $display);
        $this->assertStringContainsString('Would delete 1 orphaned files', $display);
        // The file should still exist
        $this->assertFileExists($this->tmpDir . '/thumbnails/orphan.webp');
    }

    public function testExecuteWithOrphanedFilesActuallyDeletes(): void
    {
        file_put_contents($this->tmpDir . '/thumbnails/orphan1.webp', str_repeat('a', 2048));
        file_put_contents($this->tmpDir . '/thumbnails/orphan2.jpg', str_repeat('b', 4096));

        $em = $this->buildEntityManager([], []);
        $fs = new Filesystem();
        $params = $this->buildParams($this->tmpDir, 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Deleted 2 orphaned files', $display);
        // Files should be gone
        $this->assertFileDoesNotExist($this->tmpDir . '/thumbnails/orphan1.webp');
        $this->assertFileDoesNotExist($this->tmpDir . '/thumbnails/orphan2.jpg');
    }

    // ── Broken records ──

    public function testExecuteWithBrokenRecordsImageDeleted(): void
    {
        $thumbnail = $this->createStub(Thumbnail::class);
        $thumbnail->method('getImage')->willReturn(null); // Image deleted
        $thumbnail->method('getProfile')->willReturn(null);
        $thumbnail->method('getId')->willReturn(1);
        $thumbnail->method('getFilename')->willReturn('broken.webp');

        $em = $this->buildEntityManager([], [$thumbnail]);
        $em->expects($this->once())->method('remove')->with($thumbnail);
        $em->expects($this->once())->method('flush');

        $fs = $this->createStub(Filesystem::class);
        $params = $this->buildParams('/nonexistent/path', 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute(['--type' => 'broken']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Found 1 broken thumbnail records', $display);
        $this->assertStringContainsString('Deleted 1 broken thumbnail records', $display);
    }

    public function testExecuteWithBrokenRecordProfileDeleted(): void
    {
        $thumbnail = $this->createStub(Thumbnail::class);
        $thumbnail->method('getImage')->willReturn($this->createStub(Image::class));
        $thumbnail->method('getProfile')->willReturn(null); // Profile deleted
        $thumbnail->method('getId')->willReturn(2);
        $thumbnail->method('getFilename')->willReturn('no_profile.webp');

        $em = $this->buildEntityManager([], [$thumbnail]);
        $em->expects($this->once())->method('remove')->with($thumbnail);
        $em->expects($this->once())->method('flush');

        $fs = $this->createStub(Filesystem::class);
        $params = $this->buildParams('/nonexistent/path', 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute(['--type' => 'broken']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 broken', $tester->getDisplay());
    }

    public function testExecuteWithBrokenRecordFileMissing(): void
    {
        // Thumbnail has image and profile, but file is missing on disk
        $thumbnail = $this->createStub(Thumbnail::class);
        $thumbnail->method('getImage')->willReturn($this->createStub(Image::class));
        $thumbnail->method('getProfile')->willReturn($this->createStub(ThumbnailProfile::class));
        $thumbnail->method('getId')->willReturn(3);
        $thumbnail->method('getFilename')->willReturn('missing_file.webp');

        $em = $this->buildEntityManager([], [$thumbnail]);
        $em->expects($this->once())->method('remove')->with($thumbnail);
        $em->expects($this->once())->method('flush');

        $fs = $this->createStub(Filesystem::class);
        $params = $this->buildParams($this->tmpDir, 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute(['--type' => 'broken']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteWithBrokenRecordsDryRunDoesNotRemove(): void
    {
        $thumbnail = $this->createStub(Thumbnail::class);
        $thumbnail->method('getImage')->willReturn(null);
        $thumbnail->method('getProfile')->willReturn(null);
        $thumbnail->method('getId')->willReturn(1);
        $thumbnail->method('getFilename')->willReturn('broken.webp');

        $em = $this->buildEntityManager([], [$thumbnail]);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $fs = $this->createStub(Filesystem::class);
        $params = $this->buildParams('/nonexistent/path', 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute(['--type' => 'broken', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Would delete 1 broken thumbnail records', $tester->getDisplay());
    }

    // ── --type=orphaned only ──

    public function testExecuteWithTypeOrphanedSkipsBrokenCheck(): void
    {
        file_put_contents($this->tmpDir . '/thumbnails/orphan.webp', 'data');

        // Make a "broken" thumbnail that should NOT be processed if type=orphaned
        $thumbnail = $this->createStub(Thumbnail::class);
        $thumbnail->method('getImage')->willReturn(null);
        $thumbnail->method('getFilename')->willReturn('other.webp');

        $em = $this->buildEntityManager([], [$thumbnail]);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $fs = new Filesystem();
        $params = $this->buildParams($this->tmpDir, 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute(['--type' => 'orphaned']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        // Should process orphaned step
        $this->assertStringContainsString('Finding Orphaned Thumbnail Files', $display);
        // Should NOT contain the broken records section
        $this->assertStringNotContainsString('Finding Broken Thumbnail Database Records', $display);
    }

    // ── --type=broken only ──

    public function testExecuteWithTypeBrokenSkipsOrphanedCheck(): void
    {
        $em = $this->buildEntityManager([], []);
        $fs = $this->createStub(Filesystem::class);
        $params = $this->buildParams($this->tmpDir, 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute(['--type' => 'broken']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        // Should NOT contain the orphaned files section
        $this->assertStringNotContainsString('Finding Orphaned Thumbnail Files', $display);
        // Should contain broken records section
        $this->assertStringContainsString('Finding Broken Thumbnail Database Records', $display);
    }

    // ── Directory does not exist ──

    public function testExecuteWhenDirectoryDoesNotExistShowsWarning(): void
    {
        $em = $this->buildEntityManager([], []);
        $fs = $this->createStub(Filesystem::class);
        $params = $this->buildParams('/nonexistent/path/xyz', 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Thumbnails directory does not exist', $tester->getDisplay());
    }

    // ── Summary table ──

    public function testExecuteSummaryTableShownAfterCleanup(): void
    {
        file_put_contents($this->tmpDir . '/thumbnails/orphan.webp', str_repeat('x', 512));

        $em = $this->buildEntityManager([], []);
        $fs = new Filesystem();
        $params = $this->buildParams($this->tmpDir, 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Cleanup Summary', $display);
        $this->assertStringContainsString('Orphaned Files', $display);
        $this->assertStringContainsString('Broken DB Records', $display);
        $this->assertStringContainsString('Freed Disk Space', $display);
    }

    // ── Active profiles listing ──

    public function testExecuteListsActiveProfiles(): void
    {
        $profile = $this->createStub(ThumbnailProfile::class);
        $profile->method('getName')->willReturn('hero_big');
        $profile->method('getId')->willReturn(1);
        $profile->method('getWidth')->willReturn(1920);
        $profile->method('getHeight')->willReturn(1080);

        $em = $this->buildEntityManager([$profile], []);
        $fs = $this->createStub(Filesystem::class);
        $params = $this->buildParams('/nonexistent/path', 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Active Thumbnail Profiles', $display);
        $this->assertStringContainsString('hero_big', $display);
        $this->assertStringContainsString('1920x1080', $display);
    }

    // ── Multiple broken records ──

    public function testExecuteMultipleBrokenRecordsAllCleaned(): void
    {
        $thumb1 = $this->createStub(Thumbnail::class);
        $thumb1->method('getImage')->willReturn(null);
        $thumb1->method('getId')->willReturn(1);
        $thumb1->method('getFilename')->willReturn('a.webp');

        $thumb2 = $this->createStub(Thumbnail::class);
        $thumb2->method('getImage')->willReturn($this->createStub(Image::class));
        $thumb2->method('getProfile')->willReturn(null);
        $thumb2->method('getId')->willReturn(2);
        $thumb2->method('getFilename')->willReturn('b.webp');

        $em = $this->buildEntityManager([], [$thumb1, $thumb2]);
        $em->expects($this->exactly(2))->method('remove');
        $em->expects($this->once())->method('flush');

        $fs = $this->createStub(Filesystem::class);
        $params = $this->buildParams('/nonexistent/path', 'thumbnails');

        $tester = $this->createTester(new CleanupThumbnailsCommand($em, $fs, $params));
        $tester->execute(['--type' => 'broken']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('2 broken thumbnail records', $tester->getDisplay());
    }

    // ── Helpers ──

    private function buildCommand(): CleanupThumbnailsCommand
    {
        $params = $this->buildParams('/tmp', 'thumbnails');

        return new CleanupThumbnailsCommand(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(Filesystem::class),
            $params,
        );
    }

    private function buildParams(string $storageRoot, string $thumbnailsDir): ParameterBagInterface
    {
        $params = $this->createStub(ParameterBagInterface::class);
        $params->method('get')->willReturnMap([
            ['image.storage.root', $storageRoot],
            ['image.storage.thumbnails_dir', $thumbnailsDir],
        ]);

        return $params;
    }

    private function buildEntityManager(
        array $profiles,
        array $thumbnails,
    ): EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject {
        $profileRepo = $this->createStub(EntityRepository::class);
        $profileRepo->method('findAll')->willReturn($profiles);

        $thumbRepo = $this->createStub(EntityRepository::class);
        $thumbRepo->method('findAll')->willReturn($thumbnails);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [ThumbnailProfile::class, $profileRepo],
            [Thumbnail::class, $thumbRepo],
        ]);

        return $em;
    }

    private function createTester(CleanupThumbnailsCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:cleanup-thumbnails'));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
