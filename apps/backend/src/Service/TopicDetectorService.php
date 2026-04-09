<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TopicRepository;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

class TopicDetectorService
{
    private const TIMEOUT = 60;

    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly GeminiCliService $geminiCli,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {}

    /**
     * Detect relevant topics for an article using Gemini CLI.
     *
     * @return array<int, array{topicId: int, confidence: string, reason: string}>
     */
    public function detectTopics(string $title, string $lead, string $content = ''): array
    {
        $tree = $this->topicRepository->getFullTree('ro');
        if (empty($tree)) {
            return [];
        }

        $formattedTree = $this->formatTopicsTree($tree);
        $prompt = $this->buildPrompt($title, $lead, $content, $formattedTree);

        try {
            $result = $this->callGemini($prompt);

            return $this->parseAndValidate($result);
        } catch (\Throwable $e) {
            $this->logger->warning('TopicDetectorService: Gemini detection failed', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);

            return [];
        }
    }

    private function buildPrompt(string $title, string $lead, string $content, string $topicsTree): string
    {
        $contentTruncated = mb_strlen($content) > 2000 ? mb_substr($content, 0, 2000) . '...' : $content;
        $contentTruncated = strip_tags($contentTruncated);

        return <<<PROMPT
Ești un editor de știri moldovean experimentat. Analizează articolul și identifică subiectele tematice relevante din arborele disponibil.

Arborele de subiecte disponibile:
{$topicsTree}

Articol:
Titlu: {$title}
Lead: {$lead}
Conținut: {$contentTruncated}

Răspunde STRICT în format JSON valid (fără markdown, fără backticks):
[
  {"topicId": 5, "confidence": "high", "reason": "Articolul tratează direct tema alegerilor parlamentare"},
  {"topicId": 12, "confidence": "medium", "reason": "Menționat tangential subiectul energetic"}
]

Reguli:
- Maxim 5 topics
- Preferă topics specifice (noduri frunză) în detrimentul celor generice (rădăcini)
- confidence: high = subiect principal, medium = menționat substanțial, low = tangential
- Dacă niciun topic nu se potrivește, returnează []
- NU inventa topicId-uri care nu există în arborele de mai sus
PROMPT;
    }

    /**
     * @param array<int, array<string, mixed>> $tree
     */
    private function formatTopicsTree(array $tree, int $indent = 0): string
    {
        $lines = [];
        foreach ($tree as $node) {
            $prefix = str_repeat('  ', $indent);
            $lines[] = \sprintf('%s%s (id:%d)', $prefix, $node['title'], $node['id']);
            if (!empty($node['children'])) {
                $lines[] = $this->formatTopicsTree($node['children'], $indent + 1);
            }
        }

        return implode("\n", $lines);
    }

    private function callGemini(string $prompt): string
    {
        return $this->geminiCli->execute(
            'Analyze the article and return topic suggestions as JSON array.',
            [
                'stdin' => $prompt,
                'jsonOutput' => true,
                'timeout' => self::TIMEOUT,
                'cwd' => $this->projectDir,
            ],
        );
    }

    /**
     * Parse Gemini output and validate topic IDs exist in DB.
     *
     * @return array<int, array{topicId: int, confidence: string, reason: string}>
     */
    private function parseAndValidate(string $output): array
    {
        // Extract JSON array from output (handle potential markdown wrapping)
        $jsonStr = $output;
        if (preg_match('/\[.*\]/s', $output, $matches)) {
            $jsonStr = $matches[0];
        }

        $decoded = json_decode($jsonStr, true);
        if (!\is_array($decoded)) {
            $this->logger->warning('TopicDetectorService: invalid JSON from Gemini', [
                'output' => mb_substr($output, 0, 500),
            ]);

            return [];
        }

        $validSuggestions = [];
        foreach ($decoded as $item) {
            if (!isset($item['topicId']) || !isset($item['confidence'])) {
                continue;
            }

            $topicId = (int) $item['topicId'];
            $topic = $this->topicRepository->find($topicId);

            if ($topic === null) {
                continue;
            }

            $validSuggestions[] = [
                'topicId' => $topicId,
                'confidence' => $item['confidence'],
                'reason' => $item['reason'] ?? '',
            ];
        }

        // Sort by confidence: high > medium > low
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($validSuggestions, function (array $a, array $b) use ($order) {
            return ($order[$a['confidence']] ?? 3) <=> ($order[$b['confidence']] ?? 3);
        });

        return \array_slice($validSuggestions, 0, 5);
    }
}
