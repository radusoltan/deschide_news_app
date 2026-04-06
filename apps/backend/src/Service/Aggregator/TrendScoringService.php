<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Entity\Article;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

readonly class TrendScoringService
{
    private const SOURCE_WEIGHTS = [
        'Reuters' => 3.0,
        'AP News' => 3.0,
        'Associated Press' => 3.0,
        'Agerpres' => 2.0,
        'IPN' => 2.0,
        'Moldpres' => 2.0,
        'Gov.md' => 2.0,
        'Aggregator' => 1.0,
    ];

    private const DEFAULT_WEIGHT = 0.5;

    public function __construct(
        private TopicRepository $topicRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    /**
     * Get top trending topics based on recent article activity with temporal decay.
     *
     * @return list<array{topicId: int, topicName: string, score: float, articleCount: int, velocity: float}>
     */
    public function getTopTrendingTopics(int $days = 7, int $limit = 20): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));

        // Query: articles per topic created in the last N days
        $qb = $this->em->createQueryBuilder()
            ->select('t.id AS topicId', 't.title AS topicName', 'a.id AS articleId', 'a.createdAt', 'a.sourceEmail')
            ->from(Topic::class, 't')
            ->innerJoin('t.articles', 'a')
            ->where('a.createdAt >= :since')
            ->andWhere('a.status IN (:statuses)')
            ->andWhere('t.isActive = :active')
            ->setParameter('since', $since)
            ->setParameter('statuses', [ArticleStatus::PUBLISHED, ArticleStatus::NEW])
            ->setParameter('active', true)
            ->orderBy('t.id', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC');

        $results = $qb->getQuery()->getArrayResult();

        if (empty($results)) {
            $this->logger->info('TrendScoring: no articles found in the last {days} days', ['days' => $days]);

            return [];
        }

        // Group by topic and calculate scores
        $topicScores = [];
        $now = time();

        foreach ($results as $row) {
            $topicId = $row['topicId'];

            if (!isset($topicScores[$topicId])) {
                $topicScores[$topicId] = [
                    'topicId' => $topicId,
                    'topicName' => $row['topicName'],
                    'score' => 0.0,
                    'articleCount' => 0,
                    'latestArticleAt' => null,
                    'oldestArticleAt' => null,
                ];
            }

            $createdAt = $row['createdAt'];
            $ageHours = ($now - $createdAt->getTimestamp()) / 3600;
            $weight = $this->getSourceWeight($row['sourceEmail']);

            // Newton's Law of Cooling decay (exponent 1.6 for stronger recency bias)
            $topicScores[$topicId]['score'] += $weight / pow($ageHours + 2, 1.6);
            $topicScores[$topicId]['articleCount']++;

            if ($topicScores[$topicId]['latestArticleAt'] === null || $createdAt > $topicScores[$topicId]['latestArticleAt']) {
                $topicScores[$topicId]['latestArticleAt'] = $createdAt;
            }
            if ($topicScores[$topicId]['oldestArticleAt'] === null || $createdAt < $topicScores[$topicId]['oldestArticleAt']) {
                $topicScores[$topicId]['oldestArticleAt'] = $createdAt;
            }
        }

        // Calculate velocity (articles per day) and clean up
        $trending = [];
        foreach ($topicScores as $data) {
            $latest = $data['latestArticleAt'];
            $oldest = $data['oldestArticleAt'];

            $spanDays = 1.0;
            if ($latest !== null && $oldest !== null && $latest != $oldest) {
                $spanDays = max(1.0, ($latest->getTimestamp() - $oldest->getTimestamp()) / 86400);
            }

            $trending[] = [
                'topicId' => $data['topicId'],
                'topicName' => $data['topicName'],
                'score' => round($data['score'], 4),
                'articleCount' => $data['articleCount'],
                'velocity' => round($data['articleCount'] / $spanDays, 2),
            ];
        }

        // Sort by score descending
        usort($trending, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $trending = \array_slice($trending, 0, $limit);

        $this->logger->info('TrendScoring: found {count} trending topics (top {limit})', [
            'count' => \count($trending),
            'limit' => $limit,
        ]);

        return $trending;
    }

    private function getSourceWeight(?string $sourceName): float
    {
        if ($sourceName === null || $sourceName === '') {
            return self::DEFAULT_WEIGHT;
        }

        foreach (self::SOURCE_WEIGHTS as $key => $weight) {
            if (stripos($sourceName, $key) !== false) {
                return $weight;
            }
        }

        return self::DEFAULT_WEIGHT;
    }
}
