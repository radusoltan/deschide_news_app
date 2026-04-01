<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Image;
use App\Repository\ArticleImageRepository;
use App\State\ArticleImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArticleImageProcessorTest extends TestCase
{
    private ArticleImageProcessor $processor;
    private EntityManagerInterface $entityManager;
    private ArticleImageRepository $articleImageRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->articleImageRepository = $this->createStub(ArticleImageRepository::class);

        $this->processor = new ArticleImageProcessor(
            $this->entityManager,
            $this->articleImageRepository
        );
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesArticleImage(): void
    {
        $articleImage = $this->createStub(ArticleImage::class);

        $this->entityManager->expects($this->once())->method('remove')->with($articleImage);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($articleImage, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsNullForDeleteOfNonArticleImage(): void
    {
        $operation = new Delete();
        $result = $this->processor->process('not-article-image', $operation);

        $this->assertNull($result);
    }

    // ========================
    // CREATE Operation Tests
    // ========================

    #[Test]
    public function itCreatesArticleImageAssociation(): void
    {
        $article = $this->createStub(Article::class);
        $image = $this->createStub(Image::class);

        $articleImage = new ArticleImage($article, $image, 0, false);

        $this->articleImageRepository->method('isImageAttachedToArticle')
            ->with($article, $image)
            ->willReturn(false);

        $this->articleImageRepository->method('findByArticleOrdered')
            ->with($article)
            ->willReturn([]);

        $this->entityManager->expects($this->once())->method('persist')->with($articleImage);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($articleImage, $operation);

        $this->assertInstanceOf(ArticleImage::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenArticleOrImageMissing(): void
    {
        $articleImage = new ArticleImage();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Article and Image are required');

        $operation = new Post();
        $this->processor->process($articleImage, $operation);
    }

    #[Test]
    public function itThrowsExceptionWhenImageAlreadyAttached(): void
    {
        $article = $this->createStub(Article::class);
        $image = $this->createStub(Image::class);

        $articleImage = new ArticleImage($article, $image, 0, false);

        $this->articleImageRepository->method('isImageAttachedToArticle')
            ->with($article, $image)
            ->willReturn(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Image is already attached to this article');

        $operation = new Post();
        $this->processor->process($articleImage, $operation);
    }

    #[Test]
    public function itUnfeaturesOtherImagesWhenSettingFeatured(): void
    {
        $article = $this->createStub(Article::class);
        $image = $this->createStub(Image::class);

        $articleImage = new ArticleImage($article, $image, 1, true);

        $this->articleImageRepository->method('isImageAttachedToArticle')->willReturn(false);

        $existingAi = $this->createMock(ArticleImage::class);
        $existingAi->expects($this->once())->method('setIsFeatured')->with(false);

        $this->articleImageRepository->method('findByArticleOrdered')
            ->with($article)
            ->willReturn([$existingAi]);

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $this->processor->process($articleImage, $operation);
    }

    #[Test]
    public function itAutoPositionsWhenPositionIsZero(): void
    {
        $article = $this->createStub(Article::class);
        $image = $this->createStub(Image::class);

        $articleImage = new ArticleImage($article, $image, 0, false);

        $this->articleImageRepository->method('isImageAttachedToArticle')->willReturn(false);

        // 3 existing items, so new position should be 3
        $this->articleImageRepository->method('findByArticleOrdered')
            ->with($article)
            ->willReturn(['item1', 'item2', 'item3']);

        $operation = new Post();
        $result = $this->processor->process($articleImage, $operation);

        $this->assertEquals(3, $result->getPosition());
    }

    // ========================
    // UPDATE Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingArticleImage(): void
    {
        $existingAi = $this->createMock(ArticleImage::class);
        $existingAi->method('isFeatured')->willReturn(false);
        $existingAi->method('getArticle')->willReturn($this->createMock(Article::class));

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(10)->willReturn($existingAi);

        $this->entityManager->method('getRepository')
            ->with(ArticleImage::class)
            ->willReturn($repo);

        $data = $this->createStub(ArticleImage::class);
        $data->method('getPosition')->willReturn(5);
        $data->method('isFeatured')->willReturn(false);

        $existingAi->expects($this->once())->method('setPosition')->with(5);

        $operation = new Put();
        $result = $this->processor->process($data, $operation, ['id' => 10]);

        $this->assertInstanceOf(ArticleImage::class, $result);
    }

    #[Test]
    public function itThrowsExceptionWhenUpdatingNonExistentArticleImage(): void
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($repo);

        $data = $this->createStub(ArticleImage::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ArticleImage not found');

        $operation = new Put();
        $this->processor->process($data, $operation, ['id' => 999]);
    }

    // ========================
    // Non-ArticleImage Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonArticleImageData(): void
    {
        $operation = new Post();
        $result = $this->processor->process('not-article-image', $operation);

        $this->assertNull($result);
    }
}
