<?php

declare(strict_types=1);

namespace App\Service\Topic;

use App\Entity\Article;
use App\Entity\Topic;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Classifies articles to topics in batches using Gemini CLI.
 * Sends N articles per prompt to minimize API calls.
 */
class BatchTopicClassifier
{
    private const TIMEOUT = 300;
    private const SLEEP_BETWEEN_BATCHES = 10;
    private const MAX_TOPICS_PER_ARTICLE = 3;

    /** @var array<int, Topic> id → Topic entity cache */
    private array $topicCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TopicRepository $topicRepository,
        private readonly LoggerInterface $logger,
        private readonly string $geminiCliPath,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Classify articles in batches via Gemini.
     *
     * @param Article[] $articles
     */
    public function classifyBatch(array $articles, int $batchSize = 20, ?callable $onProgress = null): BatchResult
    {
        $result = new BatchResult();

        // Pre-load topic tree
        $topicsTree = $this->topicRepository->getFullTree('ro');
        if (empty($topicsTree)) {
            $this->logger->error('BatchTopicClassifier: no topics in tree');

            return $result;
        }
        $formattedTree = $this->formatTopicsTree($topicsTree);

        // Pre-cache all topics by ID
        $this->warmTopicCache();

        $chunks = array_chunk($articles, $batchSize);
        $totalChunks = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            if ($chunkIndex > 0) {
                sleep(self::SLEEP_BETWEEN_BATCHES);
            }

            try {
                $this->processChunk($chunk, $formattedTree, $result);
            } catch (\Throwable $e) {
                $this->logger->warning('BatchTopicClassifier: chunk {idx} failed: {error}', [
                    'idx' => $chunkIndex,
                    'error' => $e->getMessage(),
                ]);
                $result->failedChunks++;
                $result->failedArticles += count($chunk);

                // Retry once with 10s delay
                sleep(10);
                try {
                    $this->processChunk($chunk, $formattedTree, $result);
                    $this->logger->info('BatchTopicClassifier: chunk {idx} succeeded on retry', [
                        'idx' => $chunkIndex,
                    ]);
                } catch (\Throwable $retryErr) {
                    $this->logger->error('BatchTopicClassifier: chunk {idx} failed on retry: {error}', [
                        'idx' => $chunkIndex,
                        'error' => $retryErr->getMessage(),
                    ]);
                }
            }

            if ($onProgress !== null) {
                $onProgress($chunkIndex + 1, $totalChunks, $result);
            }
        }

        return $result;
    }

    /**
     * @param Article[] $chunk
     */
    private function processChunk(array $chunk, string $formattedTree, BatchResult $result): void
    {
        $prompt = $this->buildBatchPrompt($chunk, $formattedTree);
        $output = $this->callGemini($prompt);
        $mappings = $this->parseResponse($output);

        // Build article lookup
        $articleMap = [];
        foreach ($chunk as $article) {
            $articleMap[$article->getId()] = $article;
        }

        foreach ($mappings as $mapping) {
            $articleId = $mapping['articleId'];
            $topicIds = $mapping['topicIds'];

            if (!isset($articleMap[$articleId])) {
                continue;
            }

            $article = $articleMap[$articleId];

            // Skip if already has topics
            if (!$article->getTopics()->isEmpty()) {
                $result->skipped++;
                continue;
            }

            $assignedCount = 0;
            foreach (array_slice($topicIds, 0, self::MAX_TOPICS_PER_ARTICLE) as $topicId) {
                $topic = $this->topicCache[$topicId] ?? null;
                if ($topic === null) {
                    $this->logger->debug('BatchTopicClassifier: invalid topicId {id}', ['id' => $topicId]);
                    continue;
                }

                $topic->addArticle($article);
                $assignedCount++;
            }

            if ($assignedCount > 0) {
                $result->classified++;
                $result->totalAssignments += $assignedCount;
            }
        }

        $this->em->flush();
    }

    /**
     * @param Article[] $articles
     */
    private function buildBatchPrompt(array $articles, string $formattedTree): string
    {
        $articleLines = [];
        foreach ($articles as $article) {
            $lead = strip_tags($article->getLead() ?? '');
            $lead = mb_substr($lead, 0, 200);
            $articleLines[] = sprintf(
                'ID:%d | TITLU:%s | LEAD:%s',
                $article->getId(),
                $article->getTitle(),
                $lead,
            );
        }

        $articlesList = implode("\n", $articleLines);

        return <<<PROMPT
Ești un editor de știri moldovean. Clasifică fiecare articol pe topicurile relevante.

ARBORELE DE TOPICURI DISPONIBILE:
{$formattedTree}

ARTICOLE DE CLASIFICAT:
{$articlesList}

Răspunde STRICT în JSON valid (fără markdown, fără backticks):
[
  {"articleId": 123, "topicIds": [5, 12]},
  {"articleId": 456, "topicIds": [3]}
]

Reguli:
- Maxim 3 topics per articol
- Preferă topics specifice (leaf nodes) nu rădăcini generice
- Dacă un articol nu se potrivește pe niciun topic, returnează topicIds: []
- NU inventa topicId-uri inexistente
- Analizează TOATE articolele din listă
PROMPT;
    }

    private function callGemini(string $prompt): string
    {
        $process = new Process(
            command: [$this->geminiCliPath, '-p', 'Classify these Moldovan news articles into topics. Return JSON array.'],
            cwd: $this->projectDir,
            env: [
                'HOME' => '/home/radu',
                'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            ],
            timeout: self::TIMEOUT,
        );

        $process->setInput($prompt);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                'Gemini CLI failed (exit ' . $process->getExitCode() . '): '
                . mb_substr($process->getErrorOutput(), 0, 500)
            );
        }

        return trim($process->getOutput());
    }

    /**
     * Parse Gemini response into article→topics mappings.
     *
     * @return array<int, array{articleId: int, topicIds: list<int>}>
     */
    private function parseResponse(string $output): array
    {
        // Extract JSON array from output (handle potential markdown wrapping)
        $jsonStr = $output;
        if (preg_match('/\[.*\]/s', $output, $matches)) {
            $jsonStr = $matches[0];
        }

        $decoded = json_decode($jsonStr, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON from Gemini: ' . mb_substr($output, 0, 300));
        }

        $results = [];
        foreach ($decoded as $item) {
            if (!isset($item['articleId'])) {
                continue;
            }

            $topicIds = array_filter(
                array_map('intval', $item['topicIds'] ?? []),
                fn(int $id) => $id > 0,
            );

            $results[] = [
                'articleId' => (int) $item['articleId'],
                'topicIds' => array_values($topicIds),
            ];
        }

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $tree
     */
    private function formatTopicsTree(array $tree, int $indent = 0): string
    {
        $lines = [];
        foreach ($tree as $node) {
            $prefix = str_repeat('  ', $indent);
            $lines[] = sprintf('%s%s (id:%d)', $prefix, $node['title'], $node['id']);
            if (!empty($node['children'])) {
                $lines[] = $this->formatTopicsTree($node['children'], $indent + 1);
            }
        }

        return implode("\n", $lines);
    }

    private function warmTopicCache(): void
    {
        $all = $this->topicRepository->findBy(['isActive' => true]);
        foreach ($all as $topic) {
            $this->topicCache[$topic->getId()] = $topic;
        }
    }
}
