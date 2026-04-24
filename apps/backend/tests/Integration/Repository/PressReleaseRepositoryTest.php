<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\PressRelease;
use App\Entity\PressReleaseTopic;
use App\Entity\Topic;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Enum\TopicDetectionMethod;
use App\Repository\PressReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for PressReleaseRepository::findByTopicInWindow.
 *
 * Sprint 52 T52.5 — supersedes the cluster-based context lookup
 * ArticleWriterService used pre-Sprint 52 (ADR-019).
 */
class PressReleaseRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PressReleaseRepository $repository;

    /** @var array<int, int> */
    private array $pressReleaseIdsToClean = [];

    /** @var array<int, int> */
    private array $topicIdsToClean = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repository = static::getContainer()->get(PressReleaseRepository::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->pressReleaseIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM press_release_topics WHERE press_release_id = :id',
                ['id' => $id],
            );
            $this->em->getConnection()->executeStatement(
                'DELETE FROM press_releases WHERE id = :id',
                ['id' => $id],
            );
        }
        foreach ($this->topicIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM topics WHERE id = :id',
                ['id' => $id],
            );
        }

        $this->em->close();
        parent::tearDown();
    }

    public function testReturnsOnlyPressReleasesWithDetectionInsideTheWindow(): void
    {
        $topic = $this->seedTopic();
        $start = new \DateTimeImmutable('2026-04-15 00:00:00');
        $end = new \DateTimeImmutable('2026-04-17 00:00:00');

        $beforeWindow = $this->seedPressRelease();
        $insideEarly = $this->seedPressRelease();
        $insideLate = $this->seedPressRelease();
        $afterWindow = $this->seedPressRelease();

        $this->linkAt($beforeWindow, $topic, new \DateTimeImmutable('2026-04-14 12:00:00'));
        $this->linkAt($insideEarly, $topic, new \DateTimeImmutable('2026-04-15 06:00:00'));
        $this->linkAt($insideLate, $topic, new \DateTimeImmutable('2026-04-16 22:00:00'));
        $this->linkAt($afterWindow, $topic, new \DateTimeImmutable('2026-04-17 00:00:00')); // exactly end → excluded

        $result = $this->repository->findByTopicInWindow($topic, $start, $end);

        $ids = array_map(static fn (PressRelease $pr) => $pr->getId(), $result);
        self::assertContains($insideEarly->getId(), $ids);
        self::assertContains($insideLate->getId(), $ids);
        self::assertNotContains($beforeWindow->getId(), $ids);
        self::assertNotContains($afterWindow->getId(), $ids, 'Window upper bound must be exclusive');
    }

    public function testOrdersResultsNewestFirstByDetectedAt(): void
    {
        $topic = $this->seedTopic();
        $start = new \DateTimeImmutable('2026-04-15 00:00:00');
        $end = new \DateTimeImmutable('2026-04-17 00:00:00');

        $first = $this->seedPressRelease();
        $second = $this->seedPressRelease();
        $third = $this->seedPressRelease();

        $this->linkAt($first, $topic, new \DateTimeImmutable('2026-04-15 06:00:00'));
        $this->linkAt($second, $topic, new \DateTimeImmutable('2026-04-16 06:00:00'));
        $this->linkAt($third, $topic, new \DateTimeImmutable('2026-04-15 18:00:00'));

        $result = $this->repository->findByTopicInWindow($topic, $start, $end);

        // Expected order: second (newest) → third → first
        self::assertCount(3, $result);
        self::assertSame($second->getId(), $result[0]->getId());
        self::assertSame($third->getId(), $result[1]->getId());
        self::assertSame($first->getId(), $result[2]->getId());
    }

    public function testExcludesRejectedAndArchivedPressReleases(): void
    {
        $topic = $this->seedTopic();
        $start = new \DateTimeImmutable('2026-04-15 00:00:00');
        $end = new \DateTimeImmutable('2026-04-17 00:00:00');

        $pending = $this->seedPressRelease(PressReleaseStatus::PENDING);
        $approved = $this->seedPressRelease(PressReleaseStatus::APPROVED);
        $rejected = $this->seedPressRelease(PressReleaseStatus::REJECTED);
        $archived = $this->seedPressRelease(PressReleaseStatus::ARCHIVED);

        foreach ([$pending, $approved, $rejected, $archived] as $pr) {
            $this->linkAt($pr, $topic, new \DateTimeImmutable('2026-04-16 12:00:00'));
        }

        $result = $this->repository->findByTopicInWindow($topic, $start, $end);
        $ids = array_map(static fn (PressRelease $pr) => $pr->getId(), $result);

        self::assertContains($pending->getId(), $ids);
        self::assertContains($approved->getId(), $ids);
        self::assertNotContains($rejected->getId(), $ids);
        self::assertNotContains($archived->getId(), $ids);
    }

    public function testIgnoresOtherTopicsLinks(): void
    {
        $topicA = $this->seedTopic('t52-5-tA');
        $topicB = $this->seedTopic('t52-5-tB');
        $start = new \DateTimeImmutable('2026-04-15 00:00:00');
        $end = new \DateTimeImmutable('2026-04-17 00:00:00');

        $prA = $this->seedPressRelease();
        $prB = $this->seedPressRelease();

        $this->linkAt($prA, $topicA, new \DateTimeImmutable('2026-04-16 06:00:00'));
        $this->linkAt($prB, $topicB, new \DateTimeImmutable('2026-04-16 06:00:00'));

        $resultA = $this->repository->findByTopicInWindow($topicA, $start, $end);
        $idsA = array_map(static fn (PressRelease $pr) => $pr->getId(), $resultA);

        self::assertContains($prA->getId(), $idsA);
        self::assertNotContains($prB->getId(), $idsA);
    }

    public function testRespectsLimitParameter(): void
    {
        $topic = $this->seedTopic();
        $start = new \DateTimeImmutable('2026-04-15 00:00:00');
        $end = new \DateTimeImmutable('2026-04-17 00:00:00');

        for ($i = 0; $i < 5; $i++) {
            $pr = $this->seedPressRelease();
            $this->linkAt($pr, $topic, new \DateTimeImmutable("2026-04-15 0{$i}:00:00"));
        }

        $result = $this->repository->findByTopicInWindow($topic, $start, $end, limit: 2);

        self::assertCount(2, $result);
    }

    public function testEmptyResultWhenNothingMatches(): void
    {
        $topic = $this->seedTopic();
        $start = new \DateTimeImmutable('2030-01-01 00:00:00');
        $end = new \DateTimeImmutable('2030-01-02 00:00:00');

        $result = $this->repository->findByTopicInWindow($topic, $start, $end);

        self::assertSame([], $result);
    }

    private function seedPressRelease(PressReleaseStatus $status = PressReleaseStatus::PENDING): PressRelease
    {
        $uniq = bin2hex(random_bytes(6));

        $pr = new PressRelease();
        $pr->setTitle('T52.5 PR ' . $uniq);
        $pr->setContent('Integration test press release content. Lorem ipsum dolor sit amet.');
        $pr->setCategorySlug('externe');
        $pr->setSourceType(SourceType::EMAIL);
        $pr->setSourceUrl('https://test.local/t52-5/' . $uniq);
        $pr->setContentHash('hash_' . $uniq);
        $pr->setStatus($status);
        $pr->setReceivedAt(new \DateTimeImmutable());

        $this->em->persist($pr);
        $this->em->flush();

        $this->pressReleaseIdsToClean[] = $pr->getId();

        return $pr;
    }

    private function seedTopic(string $prefix = 't52-5'): Topic
    {
        $uniq = bin2hex(random_bytes(4));

        $topic = new Topic();
        $topic->setTranslatableLocale('ro');
        $topic->setTitle('T52.5 Topic ' . $uniq);
        $topic->setSlug($prefix . '-' . $uniq);
        $topic->setIsActive(true);

        $this->em->persist($topic);
        $this->em->flush();

        $this->topicIdsToClean[] = $topic->getId();

        return $topic;
    }

    private function linkAt(PressRelease $pr, Topic $topic, \DateTimeImmutable $detectedAt): void
    {
        $link = new PressReleaseTopic($pr, $topic, 0.9, TopicDetectionMethod::LLM);
        // detectedAt is set by constructor to "now"; override via reflection
        // because there is no public setter (entity is immutable post-creation).
        $reflection = new \ReflectionProperty(PressReleaseTopic::class, 'detectedAt');
        $reflection->setValue($link, $detectedAt);

        $this->em->persist($link);
        $this->em->flush();
    }
}
