<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ElasticsearchIndexImagesCommand;
use App\Service\ImageElasticService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ElasticsearchIndexImagesCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:elasticsearch:index-images', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testExecuteFailsWhenElasticsearchDisabled(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(false);

        $command = $this->buildCommand(imageElasticService: $service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not enabled', $tester->getDisplay());
    }

    public function testExecuteIndexesImagesSuccessfully(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->exactly(2))->method('indexDocument');

        $image1 = $this->createMock(\App\Entity\Image::class);
        $image1->method('getId')->willReturn(1);
        $image1->method('getFilename')->willReturn('img1.jpg');
        $image1->method('getOriginalFilename')->willReturn('original1.jpg');
        $image1->method('getAlt')->willReturn('Alt text 1');
        $image1->method('getCaption')->willReturn(null);
        $image1->method('getDescription')->willReturn(null);
        $image1->method('getMimeType')->willReturn('image/jpeg');
        $image1->method('getSize')->willReturn(1024);
        $image1->method('getWidth')->willReturn(800);
        $image1->method('getHeight')->willReturn(600);
        $image1->method('getCreatedAt')->willReturn(null);

        $image2 = $this->createMock(\App\Entity\Image::class);
        $image2->method('getId')->willReturn(2);
        $image2->method('getFilename')->willReturn('img2.png');
        $image2->method('getOriginalFilename')->willReturn('original2.png');
        $image2->method('getAlt')->willReturn(null);
        $image2->method('getCaption')->willReturn(null);
        $image2->method('getDescription')->willReturn(null);
        $image2->method('getMimeType')->willReturn('image/png');
        $image2->method('getSize')->willReturn(2048);
        $image2->method('getWidth')->willReturn(1920);
        $image2->method('getHeight')->willReturn(1080);
        $image2->method('getCreatedAt')->willReturn(null);

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findAll')->willReturn([$image1, $image2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = $this->buildCommand(entityManager: $em, imageElasticService: $service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('indexed', strtolower($tester->getDisplay()));
    }

    public function testExecuteHandlesIndexingException(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(true);

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findAll')->willThrowException(new \Exception('ES connection failed'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = $this->buildCommand(entityManager: $em, imageElasticService: $service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('ES connection failed', $tester->getDisplay());
    }

    public function testExecuteWithNoImages(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->never())->method('indexDocument');

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findAll')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = $this->buildCommand(entityManager: $em, imageElasticService: $service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('0 images', $tester->getDisplay());
    }

    private function buildCommand(
        ?EntityManagerInterface $entityManager = null,
        ?ImageElasticService $imageElasticService = null,
    ): ElasticsearchIndexImagesCommand {
        return new ElasticsearchIndexImagesCommand(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $imageElasticService ?? $this->createStub(ImageElasticService::class),
        );
    }
}
