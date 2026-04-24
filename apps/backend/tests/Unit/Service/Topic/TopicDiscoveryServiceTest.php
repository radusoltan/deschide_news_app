<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Topic;

use App\Dto\Topic\TopicProposal;
use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Enum\PressReleaseStatus;
use App\Repository\TopicRepository;
use App\Service\Topic\TopicDiscoveryService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\NullLogger;

#[CoversClass(TopicDiscoveryService::class)]
class TopicDiscoveryServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TopicRepository&MockObject $topicRepo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->topicRepo = $this->createMock(TopicRepository::class);
    }

    public function testDiscoverReturnEmptyWhenNoPressReleases(): void
    {
        $this->mockQueryResults([]);

        $service = new TopicDiscoveryService(
            $this->em,
            $this->topicRepo,
            new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()),
            new NullLogger(),
        );

        $result = $service->discoverNewTopics(48);

        self::assertSame([], $result);
    }

    public function testTopicProposalDtoConstruction(): void
    {
        $proposal = new TopicProposal(
            proposedNameRo: 'Criza energetică',
            proposedNameEn: 'Energy Crisis',
            proposedNameRu: 'Энергетический кризис',
            keywords: ['energy', 'crisis', 'Moldova'],
            relatedArticleIds: [1, 2, 3],
            confidence: 0.95,
            suggestedParentTopic: 'Economie',
        );

        self::assertSame('Criza energetică', $proposal->proposedNameRo);
        self::assertSame('Energy Crisis', $proposal->proposedNameEn);
        self::assertSame('Энергетический кризис', $proposal->proposedNameRu);
        self::assertCount(3, $proposal->keywords);
        self::assertCount(3, $proposal->relatedArticleIds);
        self::assertSame(0.95, $proposal->confidence);
        self::assertSame('Economie', $proposal->suggestedParentTopic);
    }

    public function testTopicProposalWithoutParent(): void
    {
        $proposal = new TopicProposal(
            proposedNameRo: 'Subiect nou',
            proposedNameEn: 'New topic',
            proposedNameRu: 'Новая тема',
            keywords: ['test'],
            relatedArticleIds: [],
            confidence: 0.85,
        );

        self::assertNull($proposal->suggestedParentTopic);
    }

    public function testTopicReviewStatusField(): void
    {
        $topic = new Topic();
        self::assertSame('approved', $topic->getReviewStatus());

        $topic->setReviewStatus('pending_review');
        self::assertSame('pending_review', $topic->getReviewStatus());

        $topic->setReviewStatus('rejected');
        self::assertSame('rejected', $topic->getReviewStatus());
    }

    private function mockQueryResults(array $results): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($results);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);
    }
}
