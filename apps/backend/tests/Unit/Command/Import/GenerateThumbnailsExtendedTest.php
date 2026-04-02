<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Import;

use App\Command\Import\GenerateThumbnailsCommand;
use App\Entity\Image;
use App\Entity\ThumbnailProfile;
use App\Enum\ThumbnailMode;
use App\Service\ImageService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class GenerateThumbnailsExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createStub(ImageService::class);
        $command = new GenerateThumbnailsCommand($em, $service);
        $this->assertSame('app:import:generate-thumbnails', $command->getName());
    }

    public function testCommandHasOptions(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createStub(ImageService::class);
        $command = new GenerateThumbnailsCommand($em, $service);
        $this->assertTrue($command->getDefinition()->hasOption('limit'));
        $this->assertTrue($command->getDefinition()->hasOption('offset'));
        $this->assertTrue($command->getDefinition()->hasOption('batch-size'));
    }

    public function testFailsWithNoProfiles(): void
    {
        $profileRepo = $this->createStub(EntityRepository::class);
        $profileRepo->method('findAll')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($profileRepo);

        $service = $this->createStub(ImageService::class);

        $command = new GenerateThumbnailsCommand($em, $service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No thumbnail profiles found', $tester->getDisplay());
    }

    public function testSuccessWhenAllImagesHaveThumbnails(): void
    {
        $profile = $this->createStub(ThumbnailProfile::class);
        $profile->method('getName')->willReturn('card_large');
        $profile->method('getWidth')->willReturn(800);
        $profile->method('getHeight')->willReturn(600);
        $profile->method('getMode')->willReturn(ThumbnailMode::COVER);

        $profileRepo = $this->createStub(EntityRepository::class);
        $profileRepo->method('findAll')->willReturn([$profile]);

        // Query for images without thumbnails returns empty (all have thumbnails)
        // Command returns early after this — no further QB calls needed
        $imageQuery = $this->createStub(Query::class);
        $imageQuery->method('getResult')->willReturn([]);

        $imageQb = $this->createStub(QueryBuilder::class);
        $imageQb->method('leftJoin')->willReturnSelf();
        $imageQb->method('where')->willReturnSelf();
        $imageQb->method('andWhere')->willReturnSelf();
        $imageQb->method('orderBy')->willReturnSelf();
        $imageQb->method('select')->willReturnSelf();
        $imageQb->method('setMaxResults')->willReturnSelf();
        $imageQb->method('setFirstResult')->willReturnSelf();
        $imageQb->method('setParameter')->willReturnSelf();
        $imageQb->method('getQuery')->willReturn($imageQuery);

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('createQueryBuilder')->willReturn($imageQb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function ($class) use ($profileRepo, $imageRepo) {
            if ($class === ThumbnailProfile::class) {
                return $profileRepo;
            }
            return $imageRepo;
        });

        $service = $this->createStub(ImageService::class);

        $command = new GenerateThumbnailsCommand($em, $service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('All images already have thumbnails', $tester->getDisplay());
    }

    public function testCommandDescription(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createStub(ImageService::class);
        $command = new GenerateThumbnailsCommand($em, $service);
        $this->assertSame('Generate thumbnails for all imported images', $command->getDescription());
    }
}
