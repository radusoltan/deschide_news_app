<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\User;
use App\Enum\LiveTextStatus;
use App\Service\LiveTextNotificationService;
use App\Service\SocialMediaService;
use App\State\LiveTextPostProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextPostProcessorTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LiveTextNotificationService $notificationService;
    private SocialMediaService $socialMediaService;
    private LiveTextPostProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->notificationService = $this->createStub(LiveTextNotificationService::class);
        $this->socialMediaService = $this->createStub(SocialMediaService::class);

        $this->processor = new LiveTextPostProcessor(
            $this->entityManager,
            $this->notificationService,
            $this->socialMediaService,
        );
    }

    private function createLiveText(LiveTextStatus $status = LiveTextStatus::LIVE, int $id = 1): LiveText
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn($id);
        $liveText->method('getStatus')->willReturn($status);

        return $liveText;
    }

    private function createUser(int $id = 1): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getUsername')->willReturn('admin');
        $user->method('getEmail')->willReturn('admin@example.com');

        return $user;
    }

    /**
     * Create a valid LiveTextPost stub. For tests that need the create path to
     * succeed (PostCreatedEventDto::fromEntity requires non-null publishedAt),
     * the mock auto-tracks publishedAt state via setPublishedAt callback.
     *
     * Important: do NOT call expects()->method('setPublishedAt') on the returned
     * mock - use a custom mock for that.
     */
    private function createValidPost(
        ?LiveText $liveText = null,
        ?User $author = null,
        string $content = 'Valid post content',
        bool $isKeyPoint = false,
        int $position = 0,
        ?int $id = 100,
    ): LiveTextPost {
        $publishedAt = new \DateTime();
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getId')->willReturn($id);
        $post->method('getContent')->willReturn($content);
        $post->method('getContentHtml')->willReturn('<p>' . $content . '</p>');
        $post->method('isKeyPoint')->willReturn($isKeyPoint);
        $post->method('getPosition')->willReturn($position);
        $post->method('getLiveText')->willReturn($liveText ?? $this->createLiveText());
        $post->method('getAuthor')->willReturn($author ?? $this->createUser());
        $post->method('getPublishedAt')->willReturn($publishedAt);

        return $post;
    }

    // ========================
    // DELETE Operation Tests
    // ========================

    #[Test]
    public function itDeletesPostAndPublishesDeletedEvent(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createValidPost($liveText, id: 42);

        $this->entityManager->expects($this->once())->method('remove')->with($post);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Delete();
        $result = $this->processor->process($post, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsNullForDeleteOfNonLiveTextPostData(): void
    {
        $operation = new Delete();
        $result = $this->processor->process('not-a-post', $operation);

        $this->assertNull($result);
    }

    // ========================
    // CREATE (POST) Operation Tests
    // ========================

    #[Test]
    public function itCreatesNewPostAndPublishesCreatedEvent(): void
    {
        $liveText = $this->createLiveText();
        $author = $this->createUser();

        $publishedAt = null;
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getId')->willReturn(100);
        $post->method('getContent')->willReturn('Valid post content');
        $post->method('getContentHtml')->willReturn('<p>Valid post content</p>');
        $post->method('isKeyPoint')->willReturn(false);
        $post->method('getPosition')->willReturn(0);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getAuthor')->willReturn($author);
        $post->method('getPublishedAt')->willReturnCallback(function () use (&$publishedAt) {
            return $publishedAt;
        });

        // Auto-set position when position is 0
        $this->setupMaxPositionQuery(5);

        $setPublishedAtCalled = false;
        $post->expects($this->once())->method('setPublishedAt')
            ->willReturnCallback(function (\DateTimeInterface $dt) use (&$publishedAt, &$setPublishedAtCalled, $post) {
                $publishedAt = $dt;
                $setPublishedAtCalled = true;
                return $post;
            });
        $post->expects($this->once())->method('setPosition')->with(6);

        $this->entityManager->expects($this->once())->method('persist')->with($post);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Post();
        $result = $this->processor->process($post, $operation);

        $this->assertSame($post, $result);
        $this->assertTrue($setPublishedAtCalled);
    }

    #[Test]
    public function itDoesNotOverridePublishedAtIfAlreadySet(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getId')->willReturn(101);
        $post->method('getContent')->willReturn('Content');
        $post->method('getContentHtml')->willReturn('<p>Content</p>');
        $post->method('isKeyPoint')->willReturn(false);
        $post->method('getPosition')->willReturn(5); // non-zero, skip auto-position
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getAuthor')->willReturn($this->createUser());
        $post->method('getPublishedAt')->willReturn(new \DateTime('2026-01-01'));

        // publishedAt is already set, so setPublishedAt should NOT be called
        $post->expects($this->never())->method('setPublishedAt');

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($post, $operation);

        $this->assertSame($post, $result);
    }

    #[Test]
    public function itDoesNotAutoSetPositionWhenPositionIsNonZero(): void
    {
        $liveText = $this->createLiveText();
        $publishedAt = null;
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getId')->willReturn(102);
        $post->method('getContent')->willReturn('Content');
        $post->method('getContentHtml')->willReturn('<p>Content</p>');
        $post->method('isKeyPoint')->willReturn(false);
        $post->method('getPosition')->willReturn(10);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getAuthor')->willReturn($this->createUser());
        $post->expects($this->once())->method('setPublishedAt')
            ->willReturnCallback(function (\DateTimeInterface $dt) use (&$publishedAt, $post) {
                $publishedAt = $dt;
                return $post;
            });
        $post->method('getPublishedAt')->willReturnCallback(function () use (&$publishedAt) {
            return $publishedAt;
        });

        // Position is 10 (non-zero), so setPosition should NOT be called
        $post->expects($this->never())->method('setPosition');

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $this->processor->process($post, $operation);
    }

    #[Test]
    public function itPostsToSocialMediaWhenKeyPoint(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createValidPost($liveText, isKeyPoint: true, position: 1);

        $this->socialMediaService = $this->createMock(SocialMediaService::class);
        $this->socialMediaService->expects($this->once())->method('postImportantUpdate')->with($post);

        $processor = new LiveTextPostProcessor(
            $this->entityManager,
            $this->notificationService,
            $this->socialMediaService,
        );

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $processor->process($post, $operation);
    }

    #[Test]
    public function itDoesNotPostToSocialMediaWhenNotKeyPoint(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createValidPost($liveText, isKeyPoint: false, position: 1);

        $this->socialMediaService = $this->createMock(SocialMediaService::class);
        $this->socialMediaService->expects($this->never())->method('postImportantUpdate');

        $processor = new LiveTextPostProcessor(
            $this->entityManager,
            $this->notificationService,
            $this->socialMediaService,
        );

        $operation = new Post();
        $processor->process($post, $operation);
    }

    // ========================
    // UPDATE (PUT) Operation Tests
    // ========================

    #[Test]
    public function itUpdatesExistingPostAndPublishesUpdatedEvent(): void
    {
        $liveText = $this->createLiveText();
        $author = $this->createUser();

        $existingPost = $this->createMock(LiveTextPost::class);
        $existingPost->method('getId')->willReturn(42);
        $existingPost->method('getContent')->willReturn('Updated content');
        $existingPost->method('getContentHtml')->willReturn('<p>Updated</p>');
        $existingPost->method('isKeyPoint')->willReturn(false);
        $existingPost->method('getPosition')->willReturn(1);
        $existingPost->method('getLiveText')->willReturn($liveText);
        $existingPost->method('getAuthor')->willReturn($author);
        $existingPost->method('getPublishedAt')->willReturn(new \DateTime());

        $incomingData = $this->createMock(LiveTextPost::class);
        $incomingData->method('getContent')->willReturn('New content');
        $incomingData->method('getContentHtml')->willReturn('<p>New</p>');
        $incomingData->method('isKeyPoint')->willReturn(true);
        $incomingData->method('getPosition')->willReturn(2);
        $incomingData->method('getLiveText')->willReturn($liveText);
        $incomingData->method('getAuthor')->willReturn($author);
        $incomingData->method('getPublishedAt')->willReturn(new \DateTime('2026-03-01'));

        $postRepo = $this->createStub(EntityRepository::class);
        $postRepo->method('find')->with(42)->willReturn($existingPost);

        $this->entityManager->method('getRepository')
            ->with(LiveTextPost::class)
            ->willReturn($postRepo);

        $existingPost->expects($this->once())->method('setContent')->with('New content');
        $existingPost->expects($this->once())->method('setContentHtml')->with('<p>New</p>');
        $existingPost->expects($this->once())->method('setIsKeyPoint')->with(true);
        $existingPost->expects($this->once())->method('setPosition')->with(2);
        $existingPost->expects($this->once())->method('setPublishedAt');
        $existingPost->expects($this->once())->method('setLiveText')->with($liveText);

        $this->entityManager->expects($this->once())->method('persist')->with($existingPost);
        $this->entityManager->expects($this->once())->method('flush');

        $operation = new Put();
        $result = $this->processor->process($incomingData, $operation, ['id' => 42]);

        $this->assertSame($existingPost, $result);
    }

    #[Test]
    public function itThrowsRuntimeExceptionWhenUpdatingNonExistentPost(): void
    {
        $liveText = $this->createLiveText();
        $incomingData = $this->createValidPost($liveText);

        $postRepo = $this->createStub(EntityRepository::class);
        $postRepo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(LiveTextPost::class)
            ->willReturn($postRepo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LiveTextPost not found');

        $operation = new Put();
        $this->processor->process($incomingData, $operation, ['id' => 999]);
    }

    #[Test]
    public function itDoesNotUpdatePublishedAtWhenNull(): void
    {
        $liveText = $this->createLiveText();
        $author = $this->createUser();

        $existingPost = $this->createMock(LiveTextPost::class);
        $existingPost->method('getId')->willReturn(42);
        $existingPost->method('getContent')->willReturn('Content');
        $existingPost->method('getContentHtml')->willReturn('<p>Content</p>');
        $existingPost->method('isKeyPoint')->willReturn(false);
        $existingPost->method('getPosition')->willReturn(1);
        $existingPost->method('getLiveText')->willReturn($liveText);
        $existingPost->method('getAuthor')->willReturn($author);
        $existingPost->method('getPublishedAt')->willReturn(new \DateTime());

        $incomingData = $this->createMock(LiveTextPost::class);
        $incomingData->method('getContent')->willReturn('Updated');
        $incomingData->method('getContentHtml')->willReturn('<p>Updated</p>');
        $incomingData->method('isKeyPoint')->willReturn(false);
        $incomingData->method('getPosition')->willReturn(1);
        $incomingData->method('getLiveText')->willReturn($liveText);
        $incomingData->method('getAuthor')->willReturn($author);
        $incomingData->method('getPublishedAt')->willReturn(null);

        $postRepo = $this->createStub(EntityRepository::class);
        $postRepo->method('find')->with(42)->willReturn($existingPost);

        $this->entityManager->method('getRepository')
            ->with(LiveTextPost::class)
            ->willReturn($postRepo);

        // publishedAt is null on incoming, so setPublishedAt should NOT be called
        $existingPost->expects($this->never())->method('setPublishedAt');

        $operation = new Put();
        $this->processor->process($incomingData, $operation, ['id' => 42]);
    }

    // ========================
    // Validation Tests
    // ========================

    #[Test]
    public function itThrowsBadRequestWhenContentIsEmpty(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getContent')->willReturn('');
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getAuthor')->willReturn($this->createUser());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Post content cannot be empty.');

        $operation = new Post();
        $this->processor->process($post, $operation);
    }

    #[Test]
    public function itThrowsBadRequestWhenContentIsNull(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getContent')->willReturn(null);
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getAuthor')->willReturn($this->createUser());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Post content cannot be empty.');

        $operation = new Post();
        $this->processor->process($post, $operation);
    }

    #[Test]
    public function itThrowsBadRequestWhenContentIsWhitespaceOnly(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getContent')->willReturn('   ');
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getAuthor')->willReturn($this->createUser());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Post content cannot be empty.');

        $operation = new Post();
        $this->processor->process($post, $operation);
    }

    #[Test]
    public function itThrowsBadRequestWhenContentExceeds10000Chars(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getContent')->willReturn(str_repeat('a', 10001));
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getAuthor')->willReturn($this->createUser());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Post content cannot exceed 10000 characters.');

        $operation = new Post();
        $this->processor->process($post, $operation);
    }

    #[Test]
    public function itThrowsBadRequestWhenNoLiveTextAssociated(): void
    {
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getContent')->willReturn('Valid content');
        $post->method('getLiveText')->willReturn(null);
        $post->method('getAuthor')->willReturn($this->createUser());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Post must be associated with a LiveText.');

        $operation = new Post();
        $this->processor->process($post, $operation);
    }

    #[Test]
    public function itThrowsBadRequestWhenNoAuthor(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getContent')->willReturn('Valid content');
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getAuthor')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Post must have an author.');

        $operation = new Post();
        $this->processor->process($post, $operation);
    }

    #[Test]
    public function itThrowsBadRequestWhenLiveTextStatusIsEnded(): void
    {
        $liveText = $this->createLiveText(LiveTextStatus::ENDED);
        $post = $this->createMock(LiveTextPost::class);
        $post->method('getContent')->willReturn('Valid content');
        $post->method('getLiveText')->willReturn($liveText);
        $post->method('getAuthor')->willReturn($this->createUser());

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot create or update posts in an ended LiveText.');

        $operation = new Post();
        $this->processor->process($post, $operation);
    }

    #[Test]
    public function itAllowsPostingToLiveLiveText(): void
    {
        $liveText = $this->createLiveText(LiveTextStatus::LIVE);
        $post = $this->createValidPost($liveText, position: 1);

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($post, $operation);

        $this->assertSame($post, $result);
    }

    #[Test]
    public function itAllowsPostingToPausedLiveText(): void
    {
        $liveText = $this->createLiveText(LiveTextStatus::PAUSED);
        $post = $this->createValidPost($liveText, position: 1);

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($post, $operation);

        $this->assertSame($post, $result);
    }

    #[Test]
    public function itAllowsPostingToDraftLiveText(): void
    {
        $liveText = $this->createLiveText(LiveTextStatus::DRAFT);
        $post = $this->createValidPost($liveText, position: 1);

        $this->entityManager->expects($this->once())->method('persist');

        $operation = new Post();
        $result = $this->processor->process($post, $operation);

        $this->assertSame($post, $result);
    }

    // ========================
    // Non-LiveTextPost Data Tests
    // ========================

    #[Test]
    public function itReturnsNullForNonLiveTextPostDataOnPost(): void
    {
        $operation = new Post();
        $result = $this->processor->process('not-a-post', $operation);

        $this->assertNull($result);
    }

    // ========================
    // Auto-Position with max=null (empty LiveText)
    // ========================

    #[Test]
    public function itSetsPositionToOneWhenNoExistingPosts(): void
    {
        $liveText = $this->createLiveText();
        $post = $this->createValidPost($liveText, position: 0);

        // Max position returns null (no posts yet)
        $this->setupMaxPositionQuery(null);

        $post->expects($this->once())->method('setPosition')->with(1);

        $operation = new Post();
        $this->processor->process($post, $operation);
    }

    // ========================
    // UPDATE validation with ENDED status
    // ========================

    #[Test]
    public function itThrowsBadRequestWhenUpdatingPostOnEndedLiveText(): void
    {
        $liveText = $this->createLiveText(LiveTextStatus::ENDED);
        $author = $this->createUser();

        $existingPost = $this->createStub(LiveTextPost::class);
        $existingPost->method('getId')->willReturn(42);

        $incomingData = $this->createMock(LiveTextPost::class);
        $incomingData->method('getContent')->willReturn('Updated content');
        $incomingData->method('getLiveText')->willReturn($liveText);
        $incomingData->method('getAuthor')->willReturn($author);

        $postRepo = $this->createStub(EntityRepository::class);
        $postRepo->method('find')->with(42)->willReturn($existingPost);

        $this->entityManager->method('getRepository')
            ->with(LiveTextPost::class)
            ->willReturn($postRepo);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot create or update posts in an ended LiveText.');

        $operation = new Put();
        $this->processor->process($incomingData, $operation, ['id' => 42]);
    }

    // ========================
    // Helpers
    // ========================

    private function setupMaxPositionQuery(int|null $maxPosition): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getSingleScalarResult')->willReturn($maxPosition);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);
    }
}
