<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\ArticleImage;
use App\Entity\Image;
use App\Entity\ThumbnailProfile;
use App\Service\ImageService;
use App\State\ImageProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImageProcessorTest extends TestCase
{
    private ImageProcessor $processor;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private ImageService $imageService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->imageService = $this->createMock(ImageService::class);

        $this->processor = new ImageProcessor(
            $this->entityManager,
            $this->requestStack,
            $this->imageService
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesImageWithNoArticleAssociations(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $image->method('getArticleImages')->willReturn(new ArrayCollection());
        $image->method('getPath')->willReturn('images/test.png');
        $image->method('getThumbnails')->willReturn(new ArrayCollection());

        $this->entityManager->expects($this->once())->method('remove')->with($image);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($image, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itThrowsConflictWhenDeletingImageAttachedToArticles(): void
    {
        $this->setupRequest('ro');

        $articleImage = $this->createStub(ArticleImage::class);
        $image = $this->createStub(Image::class);
        $image->method('getArticleImages')->willReturn(new ArrayCollection([$articleImage]));

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Cannot delete image: it is attached to 1 article(s)');

        $operation = new Delete();
        $this->processor->process($image, $operation);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesNewImageInDefaultLocale(): void
    {
        $this->setupRequest('ro');

        $image = new Image();
        $image->setAlt('Test image');

        $this->entityManager->expects($this->once())->method('persist')->with($image);
        $this->entityManager->expects($this->once())->method('flush');

        $this->imageService->expects($this->once())
            ->method('generateAllThumbnails')
            ->with($image);

        $operation = new Post();
        $result = $this->processor->process($image, $operation);

        $this->assertInstanceOf(Image::class, $result);
    }

    #[Test]
    public function itHandlesThumbnailGenerationFailureGracefully(): void
    {
        $this->setupRequest('ro');

        $image = new Image();
        $image->setAlt('Test image');

        $this->imageService->method('generateAllThumbnails')
            ->willThrowException(new \RuntimeException('Thumbnail generation failed'));

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($image, $operation);

        // Should still return the image even if thumbnail generation fails
        $this->assertInstanceOf(Image::class, $result);
    }

    #[Test]
    public function itCreatesImageWithTranslationForNonDefaultLocale(): void
    {
        $this->setupRequest('en');

        $image = new Image();
        $image->setAlt('English alt');
        $image->setCaption('English caption');
        $image->setDescription('English description');

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->exactly(3))
            ->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $this->processor->process($image, $operation);
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingImage(): void
    {
        $this->setupRequest('ro');

        $existingImage = $this->createMock(Image::class);
        $existingImage->method('getId')->willReturn(10);

        $existingImage->expects($this->once())->method('setAlt')->with('Updated alt');
        $existingImage->expects($this->once())->method('setCaption')->with('Updated caption');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(10)->willReturn($existingImage);

        $this->entityManager->method('getRepository')->willReturn($imageRepo);

        $data = $this->createStub(Image::class);
        $data->method('getAlt')->willReturn('Updated alt');
        $data->method('getCaption')->willReturn('Updated caption');
        $data->method('getDescription')->willReturn(null);
        $data->method('getImageAuthor')->willReturn(null);

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Image::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentImage(): void
    {
        $this->setupRequest('ro');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($imageRepo);

        $data = $this->createStub(Image::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Image not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    // ========================
    // Custom Operations Tests
    // ========================

    #[Test]
    public function itHandlesCropOperationWithMissingImageId(): void
    {
        $this->setupRequest('ro');

        $data = $this->createStub(Image::class);

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image ID is required');

        $this->processor->process($data, $operation, []);
    }

    #[Test]
    public function itHandlesGenerateThumbnailsOperationWithMissingImageId(): void
    {
        $this->setupRequest('ro');

        $data = $this->createStub(Image::class);

        $operation = new Post(uriTemplate: '/images/{id}/generate-thumbnails');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image ID is required');

        $this->processor->process($data, $operation, []);
    }

    // ========================
    // Non-Image Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonImageData(): void
    {
        $this->setupRequest('ro');

        $operation = new Post();
        $result = $this->processor->process('not-an-image', $operation);

        $this->assertNull($result);
    }

    // ========================
    // UPDATE with Non-Default Locale Tests
    // ========================

    #[Test]
    public function itUpdatesImageWithNonDefaultLocaleTranslation(): void
    {
        $this->setupRequest('en');

        $existingImage = $this->createMock(Image::class);
        $existingImage->method('getId')->willReturn(10);
        // After setAlt/setCaption/setDescription are called, getters must return the values
        // for addTranslation to work
        $existingImage->method('getAlt')->willReturn('English alt');
        $existingImage->method('getCaption')->willReturn('English caption');
        $existingImage->method('getDescription')->willReturn('English description');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(10)->willReturn($existingImage);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->expects($this->exactly(3))
            ->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo, $translationRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Image::class);
        $data->method('getAlt')->willReturn('English alt');
        $data->method('getCaption')->willReturn('English caption');
        $data->method('getDescription')->willReturn('English description');
        $data->method('getImageAuthor')->willReturn('Author');

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(Image::class, $result);
    }

    // ========================
    // DELETE with Multiple Articles Attached
    // ========================

    #[Test]
    public function itThrowsConflictWhenDeletingImageAttachedToMultipleArticles(): void
    {
        $this->setupRequest('ro');

        $ai1 = $this->createStub(ArticleImage::class);
        $ai2 = $this->createStub(ArticleImage::class);
        $ai3 = $this->createStub(ArticleImage::class);

        $image = $this->createStub(Image::class);
        $image->method('getArticleImages')->willReturn(new ArrayCollection([$ai1, $ai2, $ai3]));

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Cannot delete image: it is attached to 3 article(s)');

        $operation = new Delete();
        $this->processor->process($image, $operation);
    }

    // ========================
    // DELETE for non-Image data
    // ========================

    #[Test]
    public function itReturnsNullForDeleteOfNonImageData(): void
    {
        $this->setupRequest('ro');

        $operation = new Delete();
        $result = $this->processor->process('not-an-image', $operation);

        $this->assertNull($result);
    }

    // ========================
    // Crop Operation Tests
    // ========================

    #[Test]
    public function itHandlesCropOperationWithNonExistentImage(): void
    {
        $this->setupRequest('ro');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Image::class);
        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/crop');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Image not found');

        $this->processor->process($data, $operation, ['id' => 999]);
    }

    #[Test]
    public function itHandlesCropOperationWithMissingProfile(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(1)->willReturn($image);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createMock(Image::class);
        $data->method('getProfile')->willReturn(null);

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile name is required');

        $this->processor->process($data, $operation, ['id' => 1]);
    }

    #[Test]
    public function itHandlesCropOperationWithMissingCropData(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(1)->willReturn($image);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createMock(Image::class);
        $data->method('getProfile')->willReturn('article_card');
        $data->method('getCropData')->willReturn(null);

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Crop data is required');

        $this->processor->process($data, $operation, ['id' => 1]);
    }

    #[Test]
    public function itHandlesCropOperationWithNonExistentProfile(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(1)->willReturn($image);

        $profileRepo = $this->createStub(EntityRepository::class);
        $profileRepo->method('findOneBy')->with(['name' => 'nonexistent'])->willReturn(null);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo, $profileRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                if ($class === ThumbnailProfile::class) {
                    return $profileRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createMock(Image::class);
        $data->method('getProfile')->willReturn('nonexistent');
        $data->method('getCropData')->willReturn(['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100]);

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile "nonexistent" not found');

        $this->processor->process($data, $operation, ['id' => 1]);
    }

    #[Test]
    public function itHandlesCropOperationSuccessfully(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $profile = $this->createStub(ThumbnailProfile::class);
        $cropData = ['x' => 10, 'y' => 20, 'width' => 300, 'height' => 200];

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(1)->willReturn($image);

        $profileRepo = $this->createStub(EntityRepository::class);
        $profileRepo->method('findOneBy')->with(['name' => 'article_card'])->willReturn($profile);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo, $profileRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                if ($class === ThumbnailProfile::class) {
                    return $profileRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $thumbnail = $this->createStub(\App\Entity\Thumbnail::class);
        $this->imageService->expects($this->once())
            ->method('generateThumbnail')
            ->with($image, $profile, 'webp', $cropData)
            ->willReturn($thumbnail);

        $data = $this->createMock(Image::class);
        $data->method('getProfile')->willReturn('article_card');
        $data->method('getFormat')->willReturn('webp');
        $data->method('getCropData')->willReturn($cropData);

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/crop');
        $result = $this->processor->process($data, $operation, ['id' => 1]);

        $this->assertSame($thumbnail, $result);
    }

    // ========================
    // Reset-Crop Operation Tests
    // ========================

    #[Test]
    public function itHandlesResetCropOperationWithMissingImageId(): void
    {
        $this->setupRequest('ro');

        $data = $this->createStub(Image::class);
        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/reset-crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image ID is required');

        $this->processor->process($data, $operation, []);
    }

    #[Test]
    public function itHandlesResetCropOperationSuccessfully(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $profile = $this->createStub(ThumbnailProfile::class);

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(1)->willReturn($image);

        $profileRepo = $this->createStub(EntityRepository::class);
        $profileRepo->method('findOneBy')->with(['name' => 'article_card'])->willReturn($profile);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo, $profileRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                if ($class === ThumbnailProfile::class) {
                    return $profileRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $thumbnail = $this->createStub(\App\Entity\Thumbnail::class);
        $this->imageService->expects($this->once())
            ->method('generateThumbnail')
            ->with($image, $profile, 'webp', null) // null cropData means reset
            ->willReturn($thumbnail);

        $data = $this->createMock(Image::class);
        $data->method('getProfile')->willReturn('article_card');
        $data->method('getFormat')->willReturn(null); // defaults to 'webp'

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/reset-crop');
        $result = $this->processor->process($data, $operation, ['id' => 1]);

        $this->assertSame($thumbnail, $result);
    }

    #[Test]
    public function itHandlesResetCropWithNonExistentProfile(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(1)->willReturn($image);

        $profileRepo = $this->createStub(EntityRepository::class);
        $profileRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo, $profileRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                if ($class === ThumbnailProfile::class) {
                    return $profileRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createMock(Image::class);
        $data->method('getProfile')->willReturn('nonexistent');

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/reset-crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile "nonexistent" not found');

        $this->processor->process($data, $operation, ['id' => 1]);
    }

    // ========================
    // Generate Thumbnails Operation Tests
    // ========================

    #[Test]
    public function itHandlesGenerateThumbnailsOperationWithNonExistentImage(): void
    {
        $this->setupRequest('ro');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(Image::class);
        $operation = new Post(uriTemplate: '/images/{id}/generate-thumbnails');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Image not found');

        $this->processor->process($data, $operation, ['id' => 999]);
    }

    #[Test]
    public function itHandlesGenerateThumbnailsOperationSuccessfully(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(1)->willReturn($image);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $thumbnails = ['thumb1', 'thumb2'];
        $this->imageService->expects($this->once())
            ->method('generateAllThumbnails')
            ->with($image)
            ->willReturn($thumbnails);

        $data = $this->createStub(Image::class);
        $operation = new Post(uriTemplate: '/images/{id}/generate-thumbnails');
        $result = $this->processor->process($data, $operation, ['id' => 1]);

        $this->assertSame($thumbnails, $result);
    }

    // ========================
    // Locale Handling Tests
    // ========================

    #[Test]
    public function itExtractsLocaleFromDashFormat(): void
    {
        $this->setupRequest('en-US');

        $image = new Image();
        $image->setAlt('Alt');

        $translationRepo = $this->createMock(TranslationRepository::class);
        // Should extract 'en' from 'en-US'
        $translationRepo->expects($this->once())
            ->method('translate')
            ->with($image, 'alt', 'en', 'Alt');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $this->processor->process($image, $operation);
    }

    #[Test]
    public function itExtractsLocaleFromCommaFormat(): void
    {
        $this->setupRequest('ru,en;q=0.9');

        $image = new Image();
        $image->setAlt('Alt');

        $translationRepo = $this->createMock(TranslationRepository::class);
        // Should extract 'ru' from 'ru,en;q=0.9'
        $translationRepo->expects($this->once())
            ->method('translate')
            ->with($image, 'alt', 'ru', 'Alt');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($translationRepo) {
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post();
        $this->processor->process($image, $operation);
    }

    #[Test]
    public function itUsesDefaultLocaleWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $image = new Image();
        $image->setAlt('Alt');

        // Default locale is 'ro', so no translation call
        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($image, $operation);

        $this->assertInstanceOf(Image::class, $result);
    }

    // ========================
    // Non-Image data for crop/reset
    // ========================

    #[Test]
    public function itThrowsForNonImageDataOnCrop(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(1)->willReturn($image);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request data');

        $this->processor->process('not-an-image', $operation, ['id' => 1]);
    }

    // ========================
    // Reset Crop Operation
    // ========================

    #[Test]
    public function itHandlesResetCropWithMissingImageId(): void
    {
        $this->setupRequest('ro');

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/reset-crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image ID is required');

        $this->processor->process(new Image(), $operation, []);
    }

    #[Test]
    public function itHandlesResetCropWithMissingImage(): void
    {
        $this->setupRequest('ro');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/reset-crop');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Image not found');

        $this->processor->process(new Image(), $operation, ['id' => 999]);
    }

    #[Test]
    public function itHandlesResetCropWithMissingProfileName(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->willReturn($image);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $requestImage = new Image();
        // No profile set

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/reset-crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile name is required');

        $this->processor->process($requestImage, $operation, ['id' => 1]);
    }

    // ========================
    // Generate Thumbnails Operation
    // ========================

    #[Test]
    public function itHandlesGenerateThumbnailsWithMissingImageId(): void
    {
        $this->setupRequest('ro');

        $operation = new Post(uriTemplate: '/images/{id}/generate-thumbnails');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image ID is required');

        $this->processor->process(new Image(), $operation, []);
    }

    #[Test]
    public function itHandlesGenerateThumbnailsWithMissingImage(): void
    {
        $this->setupRequest('ro');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post(uriTemplate: '/images/{id}/generate-thumbnails');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Image not found');

        $this->processor->process(new Image(), $operation, ['id' => 999]);
    }

    #[Test]
    public function itGeneratesThumbnailsSuccessfully(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->willReturn($image);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $thumbnails = [$this->createStub(\App\Entity\Thumbnail::class)];
        $this->imageService->method('generateAllThumbnails')->willReturn($thumbnails);

        $operation = new Post(uriTemplate: '/images/{id}/generate-thumbnails');
        $result = $this->processor->process(new Image(), $operation, ['id' => 1]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ========================
    // Non-Image data on reset-crop
    // ========================

    #[Test]
    public function itThrowsForNonImageDataOnResetCrop(): void
    {
        $this->setupRequest('ro');

        $image = $this->createStub(Image::class);
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('find')->with(1)->willReturn($image);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) use ($imageRepo) {
                if ($class === Image::class) {
                    return $imageRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $operation = new Post(uriTemplate: '/images/{id}/thumbnails/reset-crop');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request data');

        $this->processor->process('not-an-image', $operation, ['id' => 1]);
    }

    // ========================
    // Thumbnail generation failure on create
    // ========================

    #[Test]
    public function itHandlesThumbnailGenerationFailureOnCreate(): void
    {
        $this->setupRequest('ro');

        $image = new Image();
        $image->setAlt('Test');

        $this->imageService->method('generateAllThumbnails')
            ->willThrowException(new \RuntimeException('GD error'));

        $operation = new Post();
        $result = $this->processor->process($image, $operation);

        // Should not fail - thumbnail generation errors are caught
        $this->assertInstanceOf(Image::class, $result);
    }

    // ========================
    // Helper Methods
    // ========================

    private function setupRequest(string $locale): void
    {
        $request = new Request();
        $request->headers = new HeaderBag(['Accept-Language' => $locale]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);
    }
}
