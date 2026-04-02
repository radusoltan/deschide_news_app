<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\ThumbnailProfile;
use App\Enum\ThumbnailCategory;
use App\Enum\ThumbnailMode;
use App\Repository\ThumbnailProfileRepository;
use App\State\ThumbnailProfileProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ThumbnailProfileProcessorTest extends TestCase
{
    private ThumbnailProfileProcessor $processor;
    private EntityManagerInterface $entityManager;
    private ThumbnailProfileRepository $thumbnailProfileRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->thumbnailProfileRepository = $this->createStub(ThumbnailProfileRepository::class);

        $this->processor = new ThumbnailProfileProcessor(
            $this->entityManager,
            $this->thumbnailProfileRepository
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesProfileWithNoThumbnails(): void
    {
        $profile = $this->createStub(ThumbnailProfile::class);
        $profile->method('getThumbnails')->willReturn(new ArrayCollection());

        $this->entityManager->expects($this->once())->method('remove')->with($profile);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($profile, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itThrowsExceptionWhenDeletingProfileWithThumbnails(): void
    {
        $profile = $this->createStub(ThumbnailProfile::class);
        $profile->method('getThumbnails')->willReturn(new ArrayCollection(['thumb1']));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot delete profile that has associated thumbnails');

        $operation = new Delete();
        $this->processor->process($profile, $operation);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesNewProfile(): void
    {
        $profile = $this->createStub(ThumbnailProfile::class);
        $profile->method('getName')->willReturn('card_large');
        $profile->method('getWidth')->willReturn(800);
        $profile->method('getHeight')->willReturn(600);
        $profile->method('getMode')->willReturn(ThumbnailMode::COVER);

        $this->thumbnailProfileRepository->method('findOneByName')
            ->with('card_large')
            ->willReturn(null);

        $this->thumbnailProfileRepository->method('existsByDimensionsAndMode')
            ->with(800, 600, 'cover')
            ->willReturn(false);

        $this->entityManager->expects($this->once())->method('persist')->with($profile);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($profile, $operation);

        $this->assertInstanceOf(ThumbnailProfile::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenNameMissing(): void
    {
        $profile = $this->createStub(ThumbnailProfile::class);
        $profile->method('getName')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Name is required');

        $operation = new Post();
        $this->processor->process($profile, $operation);
    }

    #[Test]
    public function itThrowsExceptionWhenNameAlreadyExists(): void
    {
        $profile = $this->createStub(ThumbnailProfile::class);
        $profile->method('getName')->willReturn('card_large');

        $this->thumbnailProfileRepository->method('findOneByName')
            ->with('card_large')
            ->willReturn(new ThumbnailProfile());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Profile with this name already exists');

        $operation = new Post();
        $this->processor->process($profile, $operation);
    }

    #[Test]
    public function itThrowsExceptionWhenDimensionsAndModeAlreadyExist(): void
    {
        $profile = $this->createStub(ThumbnailProfile::class);
        $profile->method('getName')->willReturn('new_profile');
        $profile->method('getWidth')->willReturn(800);
        $profile->method('getHeight')->willReturn(600);
        $profile->method('getMode')->willReturn(ThumbnailMode::COVER);

        $this->thumbnailProfileRepository->method('findOneByName')->willReturn(null);
        $this->thumbnailProfileRepository->method('existsByDimensionsAndMode')
            ->with(800, 600, 'cover')
            ->willReturn(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Profile with these dimensions and mode already exists');

        $operation = new Post();
        $this->processor->process($profile, $operation);
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingProfileInDefaultLocale(): void
    {
        $existingProfile = $this->createMock(ThumbnailProfile::class);
        $existingProfile->method('getId')->willReturn(10);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingProfile);

        $translationRepo = $this->createStub(\Gedmo\Translatable\Entity\Repository\TranslationRepository::class);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($repo, $translationRepo) {
                if ($class === ThumbnailProfile::class) {
                    return $repo;
                }
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(ThumbnailProfile::class);
        $data->method('getDisplayName')->willReturn('Updated Display Name');
        $data->method('getDescription')->willReturn('Updated Description');
        $data->method('getWidth')->willReturn(null);
        $data->method('getHeight')->willReturn(null);
        $data->method('getAspectRatio')->willReturn(null);
        $data->method('getMode')->willReturn(ThumbnailMode::COVER);
        $data->method('getQuality')->willReturn(85);
        $data->method('isActive')->willReturn(true);
        $data->method('getCategory')->willReturn(ThumbnailCategory::ARTICLE);

        $existingProfile->expects($this->once())->method('setDisplayName')->with('Updated Display Name');
        $existingProfile->expects($this->once())->method('setDescription')->with('Updated Description');

        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(ThumbnailProfile::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentProfile(): void
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($repo);

        $data = $this->createStub(ThumbnailProfile::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ThumbnailProfile not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    #[Test]
    public function itUpdatesProfileInNonDefaultLocale(): void
    {
        $existingProfile = $this->createMock(ThumbnailProfile::class);
        $existingProfile->method('getId')->willReturn(10);
        $existingProfile->method('getHeight')->willReturn(600);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingProfile);

        $translationRepo = $this->createMock(\Gedmo\Translatable\Entity\Repository\TranslationRepository::class);
        $translationRepo->expects($this->exactly(2))->method('translate');

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($repo, $translationRepo) {
                if ($class === ThumbnailProfile::class) {
                    return $repo;
                }
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $data = $this->createStub(ThumbnailProfile::class);
        $data->method('getDisplayName')->willReturn('English Name');
        $data->method('getDescription')->willReturn('English Description');
        $data->method('getWidth')->willReturn(null);
        $data->method('getHeight')->willReturn(null);
        $data->method('getAspectRatio')->willReturn(null);
        $data->method('getMode')->willReturn(ThumbnailMode::COVER);
        $data->method('getQuality')->willReturn(85);
        $data->method('isActive')->willReturn(true);
        $data->method('getCategory')->willReturn(ThumbnailCategory::ARTICLE);

        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10], ['locale' => 'en']);

        $this->assertInstanceOf(ThumbnailProfile::class, $result);
    }

    #[Test]
    public function itUpdatesWidthAndValidatesDimensionsOnUpdate(): void
    {
        $existingProfile = $this->createMock(ThumbnailProfile::class);
        $existingProfile->method('getId')->willReturn(10);
        $existingProfile->method('getHeight')->willReturn(600);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingProfile);

        $translationRepo = $this->createStub(\Gedmo\Translatable\Entity\Repository\TranslationRepository::class);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($repo, $translationRepo) {
                if ($class === ThumbnailProfile::class) {
                    return $repo;
                }
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $this->thumbnailProfileRepository->method('existsByDimensionsAndMode')
            ->willReturn(false);

        $data = $this->createStub(ThumbnailProfile::class);
        $data->method('getDisplayName')->willReturn(null);
        $data->method('getDescription')->willReturn(null);
        $data->method('getWidth')->willReturn(1200);
        $data->method('getHeight')->willReturn(800);
        $data->method('getAspectRatio')->willReturn('3:2');
        $data->method('getMode')->willReturn(ThumbnailMode::CONTAIN);
        $data->method('getQuality')->willReturn(90);
        $data->method('isActive')->willReturn(true);
        $data->method('getCategory')->willReturn(ThumbnailCategory::ARTICLE);

        $existingProfile->expects($this->once())->method('setWidth')->with(1200);
        $existingProfile->expects($this->once())->method('setHeight')->with(800);
        $existingProfile->expects($this->once())->method('setAspectRatio')->with('3:2');

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(ThumbnailProfile::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenDimensionsConflictOnUpdate(): void
    {
        $existingProfile = $this->createStub(ThumbnailProfile::class);
        $existingProfile->method('getId')->willReturn(10);
        $existingProfile->method('getHeight')->willReturn(600);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingProfile);

        $translationRepo = $this->createStub(\Gedmo\Translatable\Entity\Repository\TranslationRepository::class);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($repo, $translationRepo) {
                if ($class === ThumbnailProfile::class) {
                    return $repo;
                }
                if ($class === 'Gedmo\Translatable\Entity\Translation') {
                    return $translationRepo;
                }
                return $this->createMock(EntityRepository::class);
            });

        $this->thumbnailProfileRepository->method('existsByDimensionsAndMode')
            ->willReturn(true);

        $data = $this->createStub(ThumbnailProfile::class);
        $data->method('getDisplayName')->willReturn(null);
        $data->method('getDescription')->willReturn(null);
        $data->method('getWidth')->willReturn(800);
        $data->method('getHeight')->willReturn(null);
        $data->method('getMode')->willReturn(ThumbnailMode::COVER);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Profile with these dimensions and mode already exists');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 10]);
    }

    #[Test]
    public function itDeletesReturnsNullForNonProfileData(): void
    {
        $operation = new Delete();
        $result = $this->processor->process('not-a-profile', $operation);

        $this->assertNull($result);
    }

    // ========================
    // Non-ThumbnailProfile Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonProfileData(): void
    {
        $operation = new Post();
        $result = $this->processor->process('not-a-profile', $operation);

        $this->assertNull($result);
    }
}
