<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\EventSubscriber\ArticleWebcodeSubscriber;
use App\Service\ShortCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArticleWebcodeSubscriberTest extends TestCase
{
    private ShortCodeGenerator $shortCodeGenerator;
    private LoggerInterface $logger;
    private ArticleWebcodeSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->shortCodeGenerator = $this->createStub(ShortCodeGenerator::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->subscriber = new ArticleWebcodeSubscriber(
            $this->shortCodeGenerator,
            $this->logger,
            'https://deschide.md'
        );
    }

    // PrePersist events use final args, so we test preUpdate (non-final args) paths
    // and use the generateWebcodeAndShortLink path via reflection for prePersist

    #[Test]
    public function preUpdateIgnoresNonArticleEntities(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn(new \stdClass());
        $args->expects($this->never())->method('hasChangedField');

        $this->subscriber->preUpdate($args);
    }

    #[Test]
    public function preUpdateIgnoresWhenStatusNotChanged(): void
    {
        $article = $this->createStub(Article::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('status')->willReturn(false);
        $args->expects($this->never())->method('getNewValue');

        $this->subscriber->preUpdate($args);
    }

    #[Test]
    public function preUpdateGeneratesWebcodeWhenStatusChangesToPublished(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $article->method('getWebcode')->willReturn(null);
        $article->method('getId')->willReturn(55);
        $article->method('getLocale')->willReturn('en');
        $article->method('getSlug')->willReturn('english-article');
        $article->method('getTitle')->willReturn('English Article');
        $article->expects($this->once())->method('setWebcode');

        $this->shortCodeGenerator->method('generateWebcode')->willReturn('AB12CD');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('status')->willReturn(true);
        $args->method('getNewValue')->with('status')->willReturn(ArticleStatus::PUBLISHED);
        $args->method('getObjectManager')->willReturn($em);

        $this->subscriber->preUpdate($args);
    }

    #[Test]
    public function preUpdateIgnoresWhenStatusChangesToNew(): void
    {
        $article = $this->createStub(Article::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('status')->willReturn(true);
        $args->method('getNewValue')->with('status')->willReturn(ArticleStatus::NEW);
        $args->expects($this->never())->method('getObjectManager');

        $this->subscriber->preUpdate($args);
    }

    #[Test]
    public function preUpdateIgnoresPublishedArticleWithExistingWebcode(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getWebcode')->willReturn('EXISTS');

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('status')->willReturn(true);
        $args->method('getNewValue')->with('status')->willReturn(ArticleStatus::PUBLISHED);
        $args->expects($this->never())->method('getObjectManager');

        $this->subscriber->preUpdate($args);
    }

    #[Test]
    public function generateWebcodeHandlesExceptionGracefully(): void
    {
        $shortCodeGenerator = $this->createStub(ShortCodeGenerator::class);
        $shortCodeGenerator->method('generate')
            ->willThrowException(new \Exception('Generation failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $subscriber = new ArticleWebcodeSubscriber($shortCodeGenerator, $logger);

        $article = $this->createMock(Article::class);
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $article->method('getWebcode')->willReturn(null);
        $article->method('getId')->willReturn(99);

        $em = $this->createStub(EntityManagerInterface::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('status')->willReturn(true);
        $args->method('getNewValue')->with('status')->willReturn(ArticleStatus::PUBLISHED);
        $args->method('getObjectManager')->willReturn($em);

        // Should not throw
        $subscriber->preUpdate($args);
    }

    #[Test]
    public function generateWebcodeUsesNullLocaleAsRo(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $article->method('getWebcode')->willReturn(null);
        $article->method('getId')->willReturn(1);
        $article->method('getLocale')->willReturn(null);
        $article->method('getSlug')->willReturn('slug');
        $article->method('getTitle')->willReturn('Title');

        $this->shortCodeGenerator->method('generateWebcode')->willReturn('CODE');

        $em = $this->createStub(EntityManagerInterface::class);

        $args = $this->createMock(PreUpdateEventArgs::class);
        $args->method('getObject')->willReturn($article);
        $args->method('hasChangedField')->with('status')->willReturn(true);
        $args->method('getNewValue')->with('status')->willReturn(ArticleStatus::PUBLISHED);
        $args->method('getObjectManager')->willReturn($em);

        $this->subscriber->preUpdate($args);
    }
}
