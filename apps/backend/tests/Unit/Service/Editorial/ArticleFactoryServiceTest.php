<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\PressRelease;
use App\Entity\PressReleaseTopic;
use App\Entity\Topic;
use App\Enum\SourceType;
use App\Enum\TopicDetectionMethod;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Service\Editorial\ArticleFactoryService;
use App\Service\RemoteImageDownloader;
use App\Service\SourceAuthorResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[AllowMockObjectsWithoutExpectations]
class ArticleFactoryServiceTest extends TestCase
{
    private ArticleFactoryService $service;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');

        $categoryRepo = $this->createMock(CategoryRepository::class);
        $fallback = new Category();
        $fallback->setSlug('societate');
        $categoryRepo->method('findOneBy')->willReturn($fallback);

        $articleRepo = $this->createMock(ArticleRepository::class);
        $articleRepo->method('findOneBy')->willReturn(null);

        $authorRepo = $this->createMock(AuthorRepository::class);
        $authorRepo->method('findByEmailDomain')->willReturn(null);

        $sourceAuthorResolver = $this->createMock(SourceAuthorResolver::class);
        $imageDownloader = $this->createMock(RemoteImageDownloader::class);

        $this->service = new ArticleFactoryService(
            $em,
            $categoryRepo,
            $articleRepo,
            $authorRepo,
            $sourceAuthorResolver,
            $imageDownloader,
            new NullLogger(),
            sys_get_temp_dir(),
        );
    }

    public function testPropagatesTopicsFromPressRelease(): void
    {
        $pressRelease = $this->buildPressRelease();

        $topicA = $this->buildTopic('politica-moldova');
        $topicB = $this->buildTopic('economie-moldova');

        $this->attachPivots($pressRelease, [
            [$topicA, 0.92],
            [$topicB, 0.81],
        ]);

        $article = $this->service->createFromPressRelease($pressRelease);

        self::assertCount(2, $article->getTopics());
        $slugs = array_map(static fn (Topic $t) => $t->getSlug(), $article->getTopics()->toArray());
        sort($slugs);
        self::assertSame(['economie-moldova', 'politica-moldova'], $slugs);

        // Topic references are identical (no duplicates / cloning)
        self::assertSame($topicA, $article->getTopics()->toArray()[0]);
    }

    public function testHandlesPressReleaseWithZeroTopics(): void
    {
        $pressRelease = $this->buildPressRelease();

        $article = $this->service->createFromPressRelease($pressRelease);

        self::assertInstanceOf(Article::class, $article);
        self::assertCount(0, $article->getTopics());
    }

    private function buildPressRelease(): PressRelease
    {
        $pr = new PressRelease();
        $pr->setTitle('Test title');
        $pr->setLead('Test lead');
        $pr->setContent('Test content');
        $pr->setOriginalLanguage('ro');
        $pr->setSourceType(SourceType::EMAIL);
        $pr->setCategorySlug('societate');

        return $pr;
    }

    private function buildTopic(string $slug): Topic
    {
        $topic = new Topic();
        $topic->setSlug($slug);
        $topic->setTitle(ucfirst($slug));

        return $topic;
    }

    /**
     * @param array<int, array{0: Topic, 1: float}> $pairs
     */
    private function attachPivots(PressRelease $pr, array $pairs): void
    {
        // The PressReleaseTopic constructor does not register itself on the
        // PressRelease's collection automatically. Use reflection to seed it
        // exactly the way Doctrine's hydrator would after a fetch.
        $ref = new \ReflectionProperty(PressRelease::class, 'pressReleaseTopics');
        /** @var \Doctrine\Common\Collections\Collection $collection */
        $collection = $ref->getValue($pr);
        foreach ($pairs as [$topic, $confidence]) {
            $collection->add(new PressReleaseTopic($pr, $topic, $confidence, TopicDetectionMethod::LLM));
        }
    }
}
