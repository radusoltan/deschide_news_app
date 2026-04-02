<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\EmbedController;
use App\Entity\Category;
use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\LiveTextSportMatch;
use App\Entity\User;
use App\Enum\LiveTextStatus;
use App\Repository\LiveTextPostRepository;
use App\Repository\LiveTextRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class EmbedControllerTest extends TestCase
{
    private EmbedController $controller;
    private LiveTextRepository $liveTextRepo;
    private LiveTextPostRepository $postRepo;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->liveTextRepo = $this->createMock(LiveTextRepository::class);
        $this->postRepo = $this->createMock(LiveTextPostRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);

        $this->controller = new EmbedController(
            $this->liveTextRepo,
            $this->postRepo,
            $this->em,
            'http://localhost:3005'
        );

        // Set up a minimal container so AbstractController::json() works
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['serializer', false],
            ['security.token_storage', false],
        ]);
        $this->controller->setContainer($container);
    }

    // ========================
    // getLiveText Tests
    // ========================

    #[Test]
    public function getLiveTextReturns404WhenNotFound(): void
    {
        $this->liveTextRepo->method('find')->with(999)->willReturn(null);

        $request = new Request();
        $response = $this->controller->getLiveText(999, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('LiveText not found', $data['error']);
    }

    #[Test]
    public function getLiveTextReturnsDataWhenFound(): void
    {
        $author = $this->createUserStub(1, 'Test Author');

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(42);
        $liveText->method('getTitle')->willReturn('Test Live');
        $liveText->method('getSlug')->willReturn('test-live');
        $liveText->method('getDescription')->willReturn('Test description');
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getStartTime')->willReturn(new \DateTime('2025-01-01'));
        $liveText->method('getEndTime')->willReturn(null);
        $liveText->method('getCategory')->willReturn(null);
        $liveText->method('getAuthor')->willReturn($author);
        $liveText->method('getSportMatch')->willReturn(null);

        $this->liveTextRepo->method('find')->with(42)->willReturn($liveText);
        $this->setupPostQueryBuilder([]);

        $request = new Request(query: ['locale' => 'ro']);
        $response = $this->controller->getLiveText(42, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame(42, $data['id']);
        $this->assertSame('Test Live', $data['title']);
        $this->assertSame('test-live', $data['slug']);
        $this->assertSame('ro', $data['locale']);
        $this->assertSame('live', $data['status']);
        $this->assertArrayHasKey('posts', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertArrayHasKey('embedInfo', $data);
        $this->assertSame('1.0', $data['embedInfo']['version']);
        $this->assertStringContainsString('localhost:3005', $data['embedInfo']['sourceUrl']);
    }

    #[Test]
    public function getLiveTextWithCategoryReturnsCategory(): void
    {
        $author = $this->createUserStub(1, 'Author');

        $category = $this->createCategoryStub(5, 'Sport');

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(10);
        $liveText->method('getTitle')->willReturn('Match');
        $liveText->method('getSlug')->willReturn('match');
        $liveText->method('getDescription')->willReturn(null);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getStartTime')->willReturn(null);
        $liveText->method('getEndTime')->willReturn(null);
        $liveText->method('getCategory')->willReturn($category);
        $liveText->method('getAuthor')->willReturn($author);
        $liveText->method('getSportMatch')->willReturn(null);

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->setupPostQueryBuilder([]);

        $response = $this->controller->getLiveText(10, new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertNotNull($data['category']);
        $this->assertSame(5, $data['category']['id']);
        $this->assertSame('Sport', $data['category']['name']);
    }

    #[Test]
    public function getLiveTextWithSportMatchIncludesSportData(): void
    {
        $author = $this->createUserStub(1, 'Author');

        $sportMatch = $this->createStub(LiveTextSportMatch::class);
        $sportMatch->method('getId')->willReturn(1);
        $sportMatch->method('getSportType')->willReturn('football');
        $sportMatch->method('getHomeTeam')->willReturn('Team A');
        $sportMatch->method('getAwayTeam')->willReturn('Team B');
        $sportMatch->method('getHomeScore')->willReturn(2);
        $sportMatch->method('getAwayScore')->willReturn(1);
        $sportMatch->method('getStatus')->willReturn('in_progress');
        $sportMatch->method('getCurrentMinute')->willReturn(45);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(10);
        $liveText->method('getTitle')->willReturn('Match');
        $liveText->method('getSlug')->willReturn('match');
        $liveText->method('getDescription')->willReturn(null);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getStartTime')->willReturn(null);
        $liveText->method('getEndTime')->willReturn(null);
        $liveText->method('getCategory')->willReturn(null);
        $liveText->method('getAuthor')->willReturn($author);
        $liveText->method('getSportMatch')->willReturn($sportMatch);

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->setupPostQueryBuilder([]);

        $response = $this->controller->getLiveText(10, new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('sportMatch', $data);
        $this->assertSame('football', $data['sportMatch']['sportType']);
        $this->assertSame('Team A', $data['sportMatch']['homeTeam']);
        $this->assertSame('Team B', $data['sportMatch']['awayTeam']);
        $this->assertSame(2, $data['sportMatch']['homeScore']);
        $this->assertSame(1, $data['sportMatch']['awayScore']);
        $this->assertSame(45, $data['sportMatch']['currentMinute']);
    }

    #[Test]
    public function getLiveTextExtractsLocaleFromDashFormat(): void
    {
        $author = $this->createUserStub(1, 'Author');

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(10);
        $liveText->method('getTitle')->willReturn('Title');
        $liveText->method('getSlug')->willReturn('title');
        $liveText->method('getDescription')->willReturn(null);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getStartTime')->willReturn(null);
        $liveText->method('getEndTime')->willReturn(null);
        $liveText->method('getCategory')->willReturn(null);
        $liveText->method('getAuthor')->willReturn($author);
        $liveText->method('getSportMatch')->willReturn(null);

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->setupPostQueryBuilder([]);

        // Pass locale as 'en-US' -> should be parsed to 'en'
        $request = new Request(query: ['locale' => 'en-US']);
        $response = $this->controller->getLiveText(10, $request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('en', $data['locale']);
    }

    #[Test]
    public function getLiveTextHandsPaginationParams(): void
    {
        $author = $this->createUserStub(1, 'Author');

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(10);
        $liveText->method('getTitle')->willReturn('Title');
        $liveText->method('getSlug')->willReturn('title');
        $liveText->method('getDescription')->willReturn(null);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getStartTime')->willReturn(null);
        $liveText->method('getEndTime')->willReturn(null);
        $liveText->method('getCategory')->willReturn(null);
        $liveText->method('getAuthor')->willReturn($author);
        $liveText->method('getSportMatch')->willReturn(null);

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->setupPostQueryBuilder([]);

        $request = new Request(query: ['limit' => '10', 'offset' => '5']);
        $response = $this->controller->getLiveText(10, $request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(10, $data['pagination']['limit']);
        $this->assertSame(5, $data['pagination']['offset']);
        $this->assertFalse($data['pagination']['hasMore']);
    }

    #[Test]
    public function getLiveTextWithPosts(): void
    {
        $author = $this->createUserStub(1, 'Author');
        $postAuthor = $this->createUserStub(2, 'Post Author');

        $post = $this->createStub(LiveTextPost::class);
        $post->method('getId')->willReturn(100);
        $post->method('getContent')->willReturn('Post content');
        $post->method('getContentHtml')->willReturn('<p>Post content</p>');
        $post->method('isKeyPoint')->willReturn(true);
        $post->method('getPublishedAt')->willReturn(new \DateTime('2025-06-01 12:00:00'));
        $post->method('getAuthor')->willReturn($postAuthor);

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(10);
        $liveText->method('getTitle')->willReturn('Title');
        $liveText->method('getSlug')->willReturn('title');
        $liveText->method('getDescription')->willReturn(null);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getStartTime')->willReturn(null);
        $liveText->method('getEndTime')->willReturn(null);
        $liveText->method('getCategory')->willReturn(null);
        $liveText->method('getAuthor')->willReturn($author);
        $liveText->method('getSportMatch')->willReturn(null);

        $this->liveTextRepo->method('find')->willReturn($liveText);
        $this->setupPostQueryBuilder([$post]);

        $request = new Request();
        $response = $this->controller->getLiveText(10, $request);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['posts']);
        $this->assertSame(100, $data['posts'][0]['id']);
        $this->assertSame('Post content', $data['posts'][0]['content']);
        $this->assertSame('<p>Post content</p>', $data['posts'][0]['contentHtml']);
        $this->assertTrue($data['posts'][0]['isKeyPoint']);
        $this->assertSame(2, $data['posts'][0]['author']['id']);
        $this->assertSame('Post Author', $data['posts'][0]['author']['name']);
    }

    #[Test]
    public function getLiveTextHasMoreWhenPostsMatchLimit(): void
    {
        $author = $this->createUserStub(1, 'Author');

        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(10);
        $liveText->method('getTitle')->willReturn('Title');
        $liveText->method('getSlug')->willReturn('title');
        $liveText->method('getDescription')->willReturn(null);
        $liveText->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $liveText->method('getStartTime')->willReturn(null);
        $liveText->method('getEndTime')->willReturn(null);
        $liveText->method('getCategory')->willReturn(null);
        $liveText->method('getAuthor')->willReturn($author);
        $liveText->method('getSportMatch')->willReturn(null);

        $this->liveTextRepo->method('find')->willReturn($liveText);

        // Create exactly 2 posts (matching the limit=2 below)
        $posts = [];
        for ($i = 0; $i < 2; ++$i) {
            $pAuthor = $this->createUserStub(1, 'A');
            $p = $this->createStub(LiveTextPost::class);
            $p->method('getId')->willReturn($i);
            $p->method('getContent')->willReturn('content');
            $p->method('getContentHtml')->willReturn('content');
            $p->method('isKeyPoint')->willReturn(false);
            $p->method('getPublishedAt')->willReturn(null);
            $p->method('getAuthor')->willReturn($pAuthor);
            $posts[] = $p;
        }

        $this->setupPostQueryBuilder($posts);

        $request = new Request(query: ['limit' => '2']);
        $response = $this->controller->getLiveText(10, $request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['pagination']['hasMore']);
    }

    // ========================
    // getLiveTextBySlug Tests
    // ========================

    #[Test]
    public function getLiveTextBySlugReturns404WhenNotFound(): void
    {
        $this->liveTextRepo->method('findOneBy')
            ->with(['slug' => 'nonexistent'])
            ->willReturn(null);

        $request = new Request();
        $response = $this->controller->getLiveTextBySlug('nonexistent', $request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('LiveText not found', $data['error']);
    }

    // ========================
    // getEmbedCode Tests
    // ========================

    #[Test]
    public function getEmbedCodeReturns404WhenNotFound(): void
    {
        $this->liveTextRepo->method('find')->with(999)->willReturn(null);

        $request = new Request();
        $response = $this->controller->getEmbedCode(999, $request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('LiveText not found', $data['error']);
    }

    #[Test]
    public function getEmbedCodeReturnsCodeWithDefaultParams(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(5);
        $liveText->method('getSlug')->willReturn('test-slug');

        $this->liveTextRepo->method('find')->with(5)->willReturn($liveText);

        $request = new Request();
        $response = $this->controller->getEmbedCode(5, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame(5, $data['liveTextId']);
        $this->assertSame('test-slug', $data['slug']);
        $this->assertStringContainsString('localhost:3005', $data['embedUrl']);
        $this->assertStringContainsString('test-slug', $data['embedUrl']);
        $this->assertStringContainsString('theme=light', $data['embedUrl']);
        $this->assertStringContainsString('<iframe', $data['iframeCode']);
        $this->assertStringContainsString('100%', $data['iframeCode']); // default width
        $this->assertStringContainsString('600px', $data['iframeCode']); // default height
        $this->assertStringContainsString('DeschideLiveText.embed', $data['javascriptCode']);
        $this->assertSame('ro', $data['options']['locale']);
        $this->assertSame('100%', $data['options']['width']);
        $this->assertSame('600px', $data['options']['height']);
        $this->assertSame('light', $data['options']['theme']);
    }

    #[Test]
    public function getEmbedCodeReturnsCodeWithCustomParams(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(5);
        $liveText->method('getSlug')->willReturn('test-slug');

        $this->liveTextRepo->method('find')->with(5)->willReturn($liveText);

        $request = new Request(query: [
            'locale' => 'en',
            'width' => '800px',
            'height' => '400px',
            'theme' => 'dark',
        ]);
        $response = $this->controller->getEmbedCode(5, $request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('en', $data['options']['locale']);
        $this->assertSame('800px', $data['options']['width']);
        $this->assertSame('400px', $data['options']['height']);
        $this->assertSame('dark', $data['options']['theme']);
        $this->assertStringContainsString('theme=dark', $data['embedUrl']);
        $this->assertStringContainsString('/en/', $data['embedUrl']);
    }

    // ========================
    // listEmbeddableLiveTexts Tests
    // ========================

    #[Test]
    public function listEmbeddableLiveTextsReturnsEmptyList(): void
    {
        $this->setupListQueryBuilder([]);

        $request = new Request();
        $response = $this->controller->listEmbeddableLiveTexts($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertSame([], $data['liveTexts']);
        $this->assertSame(0, $data['count']);
    }

    #[Test]
    public function listEmbeddableLiveTextsReturnsLiveTexts(): void
    {
        $lt = $this->createStub(LiveText::class);
        $lt->method('getId')->willReturn(1);
        $lt->method('getTitle')->willReturn('Live Title');
        $lt->method('getSlug')->willReturn('live-title');
        $lt->method('getStatus')->willReturn(LiveTextStatus::LIVE);
        $lt->method('getStartTime')->willReturn(new \DateTime('2025-01-01'));

        $this->setupListQueryBuilder([$lt]);

        $request = new Request();
        $response = $this->controller->listEmbeddableLiveTexts($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(1, $data['count']);
        $this->assertSame(1, $data['liveTexts'][0]['id']);
        $this->assertSame('Live Title', $data['liveTexts'][0]['title']);
        $this->assertSame('live', $data['liveTexts'][0]['status']);
        $this->assertStringContainsString('live-title', $data['liveTexts'][0]['embedUrl']);
    }

    #[Test]
    public function listEmbeddableLiveTextsWithStatusFilter(): void
    {
        $this->setupListQueryBuilder([]);

        $request = new Request(query: ['status' => 'live']);
        $response = $this->controller->listEmbeddableLiveTexts($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['count']);
    }

    #[Test]
    public function listEmbeddableLiveTextsRespectsLimitParam(): void
    {
        $this->setupListQueryBuilder([]);

        $request = new Request(query: ['limit' => '5']);
        $response = $this->controller->listEmbeddableLiveTexts($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    // ========================
    // Helper Methods
    // ========================

    /**
     * Create a User stub that extends User and adds getName().
     * The controller calls ->getName() which doesn't exist on the real User entity.
     */
    private function createUserStub(int $id, string $name): User
    {
        return new class($id, $name) extends User {
            public function __construct(private int $stubId, private string $stubName) {}
            public function getId(): ?int { return $this->stubId; }
            public function getName(): string { return $this->stubName; }
        };
    }

    /**
     * Create a Category stub that extends Category and adds getName().
     */
    private function createCategoryStub(int $id, string $name): Category
    {
        return new class($id, $name) extends Category {
            public function __construct(private int $stubId, private string $stubName) {}
            public function getId(): ?int { return $this->stubId; }
            public function getName(): string { return $this->stubName; }
        };
    }

    private function setupPostQueryBuilder(array $posts): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($posts);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('setFirstResult')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->postRepo->method('createQueryBuilder')->willReturn($qb);
    }

    private function setupListQueryBuilder(array $results): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($results);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->liveTextRepo->method('createQueryBuilder')->willReturn($qb);
    }
}
