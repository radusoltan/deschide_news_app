<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Dto\Editorial\EntityExtractionResult;
use App\Entity\Article;
use App\Entity\GeneratedContent;
use App\Service\Search\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ConnectionDetectionService
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Detect new connections between entities mentioned in the article
     * and entities from previous articles (via Elasticsearch).
     *
     * @return list<array{
     *     entity1: string,
     *     entity2: string,
     *     type: string,
     *     sourceArticleId: int|null,
     *     previousArticles: list<array{id: int, title: string}>,
     *     relevance: string,
     * }>
     */
    public function detectNewConnections(Article $article, EntityExtractionResult $entities): array
    {
        if (!$entities->hasEntities()) {
            return [];
        }

        $connections = [];
        $articleId = $article->getId();

        // Collect all entity names for cross-reference
        $allEntityNames = $this->collectEntityNames($entities);

        // Search for each entity in Elasticsearch and find co-occurrences
        foreach ($allEntityNames as $entity) {
            $searchResults = $this->searchService->search(
                query: $entity['name'],
                locale: 'ro',
                page: 1,
                size: 20,
            );

            if ($searchResults['total'] === 0) {
                continue;
            }

            // Check which other entities from our article appear in the same previous articles
            foreach ($searchResults['hits'] as $hit) {
                $hitId = $hit['id'] ?? 0;
                if ($hitId === $articleId) {
                    continue; // Skip self
                }

                $hitTitle = $hit['source']['title_ro'] ?? $hit['source']['title_en'] ?? '';
                $hitContent = ($hit['source']['body_ro'] ?? '') . ' ' . ($hit['source']['title_ro'] ?? '');

                // Check for co-occurring entities from our current article
                foreach ($allEntityNames as $otherEntity) {
                    if ($otherEntity['name'] === $entity['name']) {
                        continue;
                    }

                    if ($this->entityMentionedIn($otherEntity['name'], $hitContent)) {
                        $connectionKey = $this->connectionKey($entity['name'], $otherEntity['name']);

                        // Avoid duplicate connections
                        if (isset($connections[$connectionKey])) {
                            $connections[$connectionKey]['previousArticles'][] = [
                                'id' => $hitId,
                                'title' => mb_substr($hitTitle, 0, 100),
                            ];

                            continue;
                        }

                        $connections[$connectionKey] = [
                            'entity1' => $entity['name'],
                            'entity2' => $otherEntity['name'],
                            'type' => "{$entity['type']} ↔ {$otherEntity['type']}",
                            'sourceArticleId' => $articleId,
                            'previousArticles' => [[
                                'id' => $hitId,
                                'title' => mb_substr($hitTitle, 0, 100),
                            ]],
                            'relevance' => $this->estimateRelevance($entity, $otherEntity),
                        ];
                    }
                }
            }
        }

        $result = array_values($connections);

        if ($result !== []) {
            $this->logger->info('ConnectionDetection: connections found', [
                'articleId' => $articleId,
                'connectionCount' => \count($result),
            ]);
        }

        return $result;
    }

    /**
     * Save connection alerts as a GeneratedContent entity.
     *
     * @param list<array{entity1: string, entity2: string, type: string, sourceArticleId: int|null, previousArticles: list<array{id: int, title: string}>, relevance: string}> $connections
     */
    public function saveConnectionAlerts(array $connections, Article $article): int
    {
        if ($connections === []) {
            return 0;
        }

        $articleTitle = mb_substr($article->getTitle() ?? '', 0, 80);

        $content = "# Conexiuni din \"{$articleTitle}\"\n\n";

        foreach ($connections as $conn) {
            $prevArticles = array_map(
                fn ($a) => "#{$a['id']} {$a['title']}",
                array_slice($conn['previousArticles'], 0, 5),
            );

            $content .= "- **Entități**: {$conn['entity1']} ↔ {$conn['entity2']}\n"
                . "- **Articole anterioare**: " . implode(', ', $prevArticles) . "\n"
                . "- **Tip**: {$conn['type']}\n"
                . "- **Relevanță estimată**: {$conn['relevance']}\n\n";
        }

        $gc = new GeneratedContent();
        $gc->setType('connection_alert');
        $gc->setTitle('Conexiuni: ' . $articleTitle);
        $gc->setContent($content);
        $gc->setMetadata(['article_id' => $article->getId(), 'connection_count' => \count($connections)]);
        $this->em->persist($gc);
        $this->em->flush();

        $this->logger->info('ConnectionDetection: alerts saved', [
            'articleId' => $article->getId(),
            'alertCount' => \count($connections),
        ]);

        return \count($connections);
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    private function collectEntityNames(EntityExtractionResult $entities): array
    {
        $names = [];

        foreach ($entities->persons as $p) {
            $names[] = ['name' => $p['name'], 'type' => 'person'];
        }

        foreach ($entities->institutions as $i) {
            $names[] = ['name' => $i['name'], 'type' => 'institution'];
        }

        foreach ($entities->events as $e) {
            $names[] = ['name' => $e['name'], 'type' => 'event'];
        }

        return $names;
    }

    private function entityMentionedIn(string $entityName, string $content): bool
    {
        return mb_stripos($content, $entityName) !== false;
    }

    /**
     * @param array{name: string, type: string} $entity1
     * @param array{name: string, type: string} $entity2
     */
    private function estimateRelevance(array $entity1, array $entity2): string
    {
        // Person + Institution = HIGH (potential conflict of interest, appointments)
        $types = [$entity1['type'], $entity2['type']];
        sort($types);
        $pair = implode('+', $types);

        return match ($pair) {
            'institution+person' => 'HIGH',
            'event+person' => 'MEDIUM',
            'event+institution' => 'MEDIUM',
            'person+person' => 'HIGH',
            'institution+institution' => 'MEDIUM',
            default => 'LOW',
        };
    }

    private function connectionKey(string $name1, string $name2): string
    {
        $names = [$name1, $name2];
        sort($names);

        return implode('|', $names);
    }
}
