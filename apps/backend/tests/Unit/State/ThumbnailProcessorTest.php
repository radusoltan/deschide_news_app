<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Image;
use App\Entity\Thumbnail;
use App\Entity\ThumbnailProfile;
use App\Repository\ThumbnailRepository;
use App\Service\ImageService;
use App\State\ThumbnailProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ThumbnailProcessorTest extends TestCase
{
    private ThumbnailProcessor $processor;
    private EntityManagerInterface $entityManager;
    private ThumbnailRepository $thumbnailRepository;
    private ImageService $imageService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->thumbnailRepository = $this->createStub(ThumbnailRepository::class);
        $this->imageService = $this->createMock(ImageService::class);

        $this->processor = new ThumbnailProcessor(
            $this->entityManager,
            $this->thumbnailRepository,
            $this->imageService
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesThumbnail(): void
    {
        $thumbnail = $this->createStub(Thumbnail::class);

        $this->entityManager->expects($this->once())->method('remove')->with($thumbnail);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($thumbnail, $operation);

        $this->assertNull($result);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesThumbnailWithValidData(): void
    {
        $image = $this->createStub(Image::class);
        $profile = $this->createStub(ThumbnailProfile::class);

        $thumbnail = $this->createStub(Thumbnail::class);
        $thumbnail->method('getImage')->willReturn($image);
        $thumbnail->method('getProfile')->willReturn($profile);

        $this->thumbnailRepository->method('existsForImageAndProfile')
            ->with($image, $profile)
            ->willReturn(false);

        $this->entityManager->expects($this->once())->method('persist')->with($thumbnail);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($thumbnail, $operation);

        $this->assertInstanceOf(Thumbnail::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenImageMissing(): void
    {
        $thumbnail = $this->createStub(Thumbnail::class);
        $thumbnail->method('getImage')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image is required');

        $operation = new Post();
        $this->processor->process($thumbnail, $operation);
    }

    #[Test]
    public function itThrowsExceptionWhenThumbnailAlreadyExists(): void
    {
        $image = $this->createStub(Image::class);
        $profile = $this->createStub(ThumbnailProfile::class);

        $thumbnail = $this->createStub(Thumbnail::class);
        $thumbnail->method('getImage')->willReturn($image);
        $thumbnail->method('getProfile')->willReturn($profile);

        $this->thumbnailRepository->method('existsForImageAndProfile')
            ->with($image, $profile)
            ->willReturn(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Thumbnail already exists for this image and profile');

        $operation = new Post();
        $this->processor->process($thumbnail, $operation);
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingThumbnail(): void
    {
        $existingThumbnail = $this->createMock(Thumbnail::class);
        $existingThumbnail->expects($this->once())->method('setWidth')->with(800);
        $existingThumbnail->expects($this->once())->method('setHeight')->with(600);
        $existingThumbnail->expects($this->once())->method('setSize')->with(50000);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingThumbnail);

        $this->entityManager->method('getRepository')
            ->with(Thumbnail::class)
            ->willReturn($repo);

        $data = $this->createStub(Thumbnail::class);
        $data->method('getWidth')->willReturn(800);
        $data->method('getHeight')->willReturn(600);
        $data->method('getSize')->willReturn(50000);
        $data->method('getCropData')->willReturn(null);

        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Thumbnail::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentThumbnail(): void
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($repo);

        $data = $this->createStub(Thumbnail::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thumbnail not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    // ========================
    // Crop Operation Tests
    // ========================

    #[Test]
    public function itHandlesCropOperation(): void
    {
        $thumbnail = $this->createStub(Thumbnail::class);
        $cropData = ['x' => 10, 'y' => 20, 'width' => 300, 'height' => 200];

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($thumbnail);

        $this->entityManager->method('getRepository')
            ->with(Thumbnail::class)
            ->willReturn($repo);

        $data = $this->createStub(Thumbnail::class);
        $data->method('getCropData')->willReturn($cropData);

        $croppedThumbnail = $this->createStub(Thumbnail::class);
        $this->imageService->expects($this->once())
            ->method('applyCrop')
            ->with($thumbnail, $cropData)
            ->willReturn($croppedThumbnail);

        $operation = new Post(uriTemplate: '/thumbnails/{id}/crop');

        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Thumbnail::class, $result);
    }

    #[Test]
    public function itThrowsExceptionForCropWithMissingCropData(): void
    {
        $thumbnail = $this->createStub(Thumbnail::class);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($thumbnail);

        $this->entityManager->method('getRepository')
            ->with(Thumbnail::class)
            ->willReturn($repo);

        $data = $this->createStub(Thumbnail::class);
        $data->method('getCropData')->willReturn(null);

        $operation = new Post(uriTemplate: '/thumbnails/{id}/crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop data is required');

        $this->processor->process($data, $operation, ['id' => 10]);
    }

    // ========================
    // Non-Thumbnail Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonThumbnailData(): void
    {
        $operation = new Post();
        $result = $this->processor->process('not-a-thumbnail', $operation);

        $this->assertNull($result);
    }
}
