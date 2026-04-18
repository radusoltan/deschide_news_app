<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Article;
use App\Entity\PressRelease;
use App\Entity\PressReleaseTopic;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Enum\TopicDetectionMethod;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for TopicRepository::findActiveWithUnprocessedPressReleasesSince.
 *
 * Sprint 52 T52.6 — eligibility query feeding the synchronous article
 * generation path per ADR-019 D2.
 */
class TopicRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TopicRepository $repository;

    /** @var list<int> */
    private array $articleIdsToClean = [];
    /** @var list<int> */
    private array $pressReleaseIdsToClean = [];
    /** @var list<int> */
    private array $topicIdsToClean = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repository = static::getContainer()->get(TopicRepository::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->articleIdsToClean as $id) {
            $this->em->getConnection()->executeStatement('DELETE FROM article_topics WHERE article_id = :id', ['id' => $id]);
            $this->em->getConnection()->executeStatement('DELETE FROM articles WHERE id = :id', ['id' => $id]);
        }
        foreach ($this->pressReleaseIdsToClean as $id) {
            $this->em->getConnection()->executeStatement('DELETE FROM press_release_topics WHERE press_release_id = :id', ['id' => $id]);
            $this->em->getConnection()->executeStatement('DELETE FROM press_releases WHERE id = :id', ['id' => $id]);
        }
        foreach ($this->topicIdsToClean as $id) {
            $this->em->getConnection()->executeStatement('DELETE FROM topics WHERE id = :id', ['id' => $id]);
        }

        $this->em->close();
        parent::tearDown();
    }

    public function testReturnsTopicsWithEnoughPrsAndRelevance(): void
    {
        $windowStart = new \DateTimeImmutable('-12 hours');

        $eligibleTopic = $this->seedTopic('t52-6-eligible');
        $this->linkPrAt($eligibleTopic, confidence: 0.9, detectedAtOffset: '-6 hours');
        $this->linkPrAt($eligibleTopic, confidence: 0.95, detectedAtOffset: '-4 hours');
        // sum 1.85 >= 2.0? No, 1.85 < 2.0. Add third.
        $this->linkPrAt($eligibleTopic, confidence: 0.85, detectedAtOffset: '-2 hours');
        // sum 2.70 >= 2.0 ✓

        $result = $this->repository->findActiveWithUnprocessedPressReleasesSince($windowStart, 1, 2.0);

        $ids = array_map(static fn (Topic $t) => $t->getId(), $result);
        self::assertContains($eligibleTopic->getId(), $ids);
    }

    public function testExcludesTopicsBelowRelevanceThreshold(): void
    {
        $windowStart = new \DateTimeImmutable('-12 hours');

        $lowRelevanceTopic = $this->seedTopic('t52-6-low-relevance');
        $this->linkPrAt($lowRelevanceTopic, confidence: 0.4, detectedAtOffset: '-1 hour');
        $this->linkPrAt($lowRelevanceTopic, confidence: 0.3, detectedAtOffset: '-2 hours');
        // sum 0.7 < 2.0 ✗

        $result = $this->repository->findActiveWithUnprocessedPressReleasesSince($windowStart, 1, 2.0);

        $ids = array_map(static fn (Topic $t) => $t->getId(), $result);
        self::assertNotContains($lowRelevanceTopic->getId(), $ids);
    }

    public function testExcludesTopicsBelowMinPrCount(): void
    {
        $windowStart = new \DateTimeImmutable('-12 hours');

        $singletonTopic = $this->seedTopic('t52-6-singleton');
        // Only 1 PR — fails minPrCount=3 even with high confidence
        $this->linkPrAt($singletonTopic, confidence: 1.0, detectedAtOffset: '-1 hour');

        $result = $this->repository->findActiveWithUnprocessedPressReleasesSince($windowStart, 3, 1.0);

        $ids = array_map(static fn (Topic $t) => $t->getId(), $result);
        self::assertNotContains($singletonTopic->getId(), $ids);
    }

    public function testExcludesTopicsWithArticleAlreadyGeneratedInWindow(): void
    {
        $windowStart = new \DateTimeImmutable('-12 hours');

        $alreadyProcessed = $this->seedTopic('t52-6-already-processed');
        $this->linkPrAt($alreadyProcessed, confidence: 0.9, detectedAtOffset: '-6 hours');
        $this->linkPrAt($alreadyProcessed, confidence: 0.95, detectedAtOffset: '-4 hours');
        $this->linkPrAt($alreadyProcessed, confidence: 0.85, detectedAtOffset: '-2 hours');

        // Article for this topic created inside the window → excluded
        $this->attachArticleToTopic($alreadyProcessed, createdAtOffset: '-3 hours');

        $result = $this->repository->findActiveWithUnprocessedPressReleasesSince($windowStart, 1, 2.0);

        $ids = array_map(static fn (Topic $t) => $t->getId(), $result);
        self::assertNotContains($alreadyProcessed->getId(), $ids);
    }

    public function testIncludesTopicIfArticleCreatedBeforeWindowStart(): void
    {
        $windowStart = new \DateTimeImmutable('-12 hours');

        $topic = $this->seedTopic('t52-6-old-article');
        $this->linkPrAt($topic, confidence: 0.9, detectedAtOffset: '-6 hours');
        $this->linkPrAt($topic, confidence: 0.95, detectedAtOffset: '-4 hours');
        $this->linkPrAt($topic, confidence: 0.85, detectedAtOffset: '-2 hours');

        // Article is older than the window — does NOT disqualify the topic
        $this->attachArticleToTopic($topic, createdAtOffset: '-48 hours');

        $result = $this->repository->findActiveWithUnprocessedPressReleasesSince($windowStart, 1, 2.0);

        $ids = array_map(static fn (Topic $t) => $t->getId(), $result);
        self::assertContains($topic->getId(), $ids);
    }

    public function testOrdersByRelevanceProxyDescending(): void
    {
        $windowStart = new \DateTimeImmutable('-12 hours');

        $low = $this->seedTopic('t52-6-ordering-low');
        $this->linkPrAt($low, confidence: 0.7, detectedAtOffset: '-6 hours');
        $this->linkPrAt($low, confidence: 0.8, detectedAtOffset: '-4 hours');
        $this->linkPrAt($low, confidence: 0.6, detectedAtOffset: '-2 hours');
        // sum 2.1

        $high = $this->seedTopic('t52-6-ordering-high');
        $this->linkPrAt($high, confidence: 0.95, detectedAtOffset: '-6 hours');
        $this->linkPrAt($high, confidence: 0.95, detectedAtOffset: '-4 hours');
        $this->linkPrAt($high, confidence: 0.95, detectedAtOffset: '-2 hours');
        // sum 2.85

        $result = $this->repository->findActiveWithUnprocessedPressReleasesSince($windowStart, 1, 2.0);

        $ids = array_map(static fn (Topic $t) => $t->getId(), $result);
        $highPos = array_search($high->getId(), $ids, true);
        $lowPos = array_search($low->getId(), $ids, true);
        self::assertNotFalse($highPos);
        self::assertNotFalse($lowPos);
        self::assertLessThan($lowPos, $highPos, 'Higher-sum topic must sort before lower-sum topic');
    }

    public function testExcludesInactiveTopics(): void
    {
        $windowStart = new \DateTimeImmutable('-12 hours');

        $inactive = $this->seedTopic('t52-6-inactive', isActive: false);
        $this->linkPrAt($inactive, confidence: 0.9, detectedAtOffset: '-6 hours');
        $this->linkPrAt($inactive, confidence: 0.95, detectedAtOffset: '-4 hours');
        $this->linkPrAt($inactive, confidence: 0.85, detectedAtOffset: '-2 hours');

        $result = $this->repository->findActiveWithUnprocessedPressReleasesSince($windowStart, 1, 2.0);

        $ids = array_map(static fn (Topic $t) => $t->getId(), $result);
        self::assertNotContains($inactive->getId(), $ids);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────

    private function seedTopic(string $prefix, bool $isActive = true): Topic
    {
        $uniq = bin2hex(random_bytes(4));
        $topic = new Topic();
        $topic->setTranslatableLocale('ro');
        $topic->setSlug($prefix . '-' . $uniq);
        $topic->setTitle('T52.6 Topic ' . $uniq);
        $topic->setIsActive($isActive);

        $this->em->persist($topic);
        $this->em->flush();

        $this->topicIdsToClean[] = $topic->getId();

        return $topic;
    }

    private function linkPrAt(Topic $topic, float $confidence, string $detectedAtOffset): void
    {
        $uniq = bin2hex(random_bytes(6));
        $pr = new PressRelease();
        $pr->setTitle('T52.6 PR ' . $uniq);
        $pr->setContent('Integration test press release content. Lorem ipsum dolor sit amet.');
        $pr->setCategorySlug('externe');
        $pr->setSourceType(SourceType::EMAIL);
        $pr->setSourceUrl('https://test.local/t52-6/' . $uniq);
        $pr->setContentHash('hash_t526_' . $uniq);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setReceivedAt(new \DateTimeImmutable());

        $this->em->persist($pr);
        $this->em->flush();
        $this->pressReleaseIdsToClean[] = $pr->getId();

        $link = new PressReleaseTopic($pr, $topic, $confidence, TopicDetectionMethod::LLM);
        $reflection = new \ReflectionProperty(PressReleaseTopic::class, 'detectedAt');
        $reflection->setValue($link, new \DateTimeImmutable($detectedAtOffset));

        $this->em->persist($link);
        $this->em->flush();
    }

    private function attachArticleToTopic(Topic $topic, string $createdAtOffset): void
    {
        $uniq = bin2hex(random_bytes(4));
        $article = new Article();
        $article->setTranslatableLocale('ro');
        $article->setTitle('T52.6 marker article ' . $uniq);
        $article->setSlug('t52-6-marker-' . $uniq);
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setPublishedAt(new \DateTimeImmutable());
        $article->setPublishedLocales(['ro']);
        $article->addTopic($topic);

        $this->em->persist($article);
        $this->em->flush();

        // Override timestamped createdAt (lifecycle callback sets it to now)
        $reflection = new \ReflectionProperty(Article::class, 'createdAt');
        $reflection->setValue($article, new \DateTimeImmutable($createdAtOffset));
        $this->em->flush();

        $this->articleIdsToClean[] = $article->getId();
    }
}
