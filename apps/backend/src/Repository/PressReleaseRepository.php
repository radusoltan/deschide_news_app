<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PressRelease>
 */
class PressReleaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PressRelease::class);
    }

    public function countPending(): int
    {
        return $this->count(['status' => PressReleaseStatus::PENDING]);
    }

    public function findBySourceEmailId(string $sourceEmailId): ?PressRelease
    {
        return $this->findOneBy(['sourceEmailId' => $sourceEmailId]);
    }

    public function findByContentHash(string $hash): ?PressRelease
    {
        return $this->findOneBy(['contentHash' => $hash]);
    }

    public function findByContentHashAndSourceType(string $hash, SourceType $type): ?PressRelease
    {
        return $this->findOneBy(['contentHash' => $hash, 'sourceType' => $type]);
    }

    public function findBySourceUrl(string $sourceUrl): ?PressRelease
    {
        return $this->findOneBy(['sourceUrl' => $sourceUrl]);
    }

    public function countBySourceType(SourceType $type): int
    {
        return $this->count(['sourceType' => $type]);
    }

    /**
     * Find a PressRelease with the same content_hash that is already assigned to a cluster.
     */
    public function findClusteredDuplicateByHash(string $hash, int $excludeId): ?PressRelease
    {
        return $this->createQueryBuilder('pr')
            ->innerJoin('pr.storyClusters', 'sc')
            ->where('pr.contentHash = :hash')
            ->andWhere('pr.id != :excludeId')
            ->setParameter('hash', $hash)
            ->setParameter('excludeId', $excludeId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Cursor-based pagination for press releases by status.
     *
     * @return array{items: PressRelease[], nextCursor: ?string, hasMore: bool}
     */
    public function findByStatusWithCursor(
        PressReleaseStatus $status,
        ?string $cursor = null,
        int $limit = 20,
        ?string $sourceType = null,
        ?string $originalLanguage = null,
    ): array {
        $qb = $this->createQueryBuilder('pr')
            ->where('pr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('pr.createdAt', 'DESC')
            ->addOrderBy('pr.id', 'DESC')
            ->setMaxResults($limit + 1);

        if ($cursor !== null) {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$cursorDate, $cursorId] = explode(':', $decoded, 2);
                $qb->andWhere('(pr.createdAt < :cursorDate OR (pr.createdAt = :cursorDate AND pr.id < :cursorId))')
                    ->setParameter('cursorDate', new \DateTimeImmutable($cursorDate))
                    ->setParameter('cursorId', (int) $cursorId);
            }
        }

        if ($sourceType !== null) {
            $qb->andWhere('pr.sourceType = :sourceType')
                ->setParameter('sourceType', $sourceType);
        }

        if ($originalLanguage !== null) {
            $qb->andWhere('pr.originalLanguage = :lang')
                ->setParameter('lang', $originalLanguage);
        }

        $results = $qb->getQuery()->getResult();
        $hasMore = \count($results) > $limit;

        if ($hasMore) {
            array_pop($results);
        }

        $nextCursor = null;
        if ($hasMore && \count($results) > 0) {
            $last = end($results);
            $nextCursor = base64_encode($last->getCreatedAt()->format('Y-m-d H:i:s') . ':' . $last->getId());
        }

        return [
            'items' => $results,
            'nextCursor' => $nextCursor,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * Find PressReleases linked to the given Topic whose topic-link
     * detection timestamp falls within [start, end). Excludes REJECTED
     * and ARCHIVED press releases.
     *
     * Backed by the (topic_id, detected_at) index on press_release_topics
     * (`idx_prt_topic_created`). Supersedes the cluster-based context
     * lookup that ArticleWriterService used pre-Sprint 52 (ADR-019).
     *
     * @return list<PressRelease> Ordered newest-first by detection time
     */
    public function findByTopicInWindow(
        Topic $topic,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $limit = null,
    ): array {
        $qb = $this->createQueryBuilder('pr')
            ->innerJoin('pr.pressReleaseTopics', 'prt')
            ->where('prt.topic = :topic')
            ->andWhere('prt.detectedAt >= :start')
            ->andWhere('prt.detectedAt < :end')
            ->andWhere('pr.status NOT IN (:excluded)')
            ->setParameter('topic', $topic)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('excluded', [PressReleaseStatus::REJECTED, PressReleaseStatus::ARCHIVED])
            ->orderBy('prt.detectedAt', 'DESC')
            ->addOrderBy('pr.id', 'DESC');

        if ($limit !== null && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        /** @var list<PressRelease> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<string, int> Pending count per source type, e.g. ['email' => 8, 'scrape' => 15]
     */
    public function countPendingBySourceType(): array
    {
        $rows = $this->createQueryBuilder('pr')
            ->select('pr.sourceType AS type, COUNT(pr.id) AS cnt')
            ->where('pr.status = :status')
            ->setParameter('status', PressReleaseStatus::PENDING)
            ->groupBy('pr.sourceType')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['type']->value ?? $row['type']] = (int) $row['cnt'];
        }

        return $result;
    }
}
