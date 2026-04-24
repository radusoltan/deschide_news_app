<?php

declare(strict_types=1);

namespace App\Service\Topic;

use App\Dto\Topic\TopicProposal;
use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Enum\PressReleaseStatus;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

class TopicDiscoveryService
{
    private const GEMINI_TIMEOUT = 90;
    private const MIN_CONFIDENCE = 0.8;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TopicRepository $topicRepository,
        private readonly GeminiCliService $geminiCli,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Discover new topics from untagged PressReleases.
     *
     * @return TopicProposal[]
     */
    public function discoverNewTopics(int $hoursBack = 48): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d hours', $hoursBack));

        // Find pending PressReleases without suggested topics
        $qb = $this->em->createQueryBuilder()
            ->select('pr')
            ->from(PressRelease::class, 'pr')
            ->where('pr.status = :status')
            ->andWhere('pr.suggestedTopics IS NULL')
            ->andWhere('pr.createdAt >= :since')
            ->setParameter('status', PressReleaseStatus::PENDING)
            ->setParameter('since', $since)
            ->orderBy('pr.createdAt', 'DESC')
            ->setMaxResults(50);

        /** @var PressRelease[] $pressReleases */
        $pressReleases = $qb->getQuery()->getResult();

        if (empty($pressReleases)) {
            $this->logger->info('TopicDiscovery: no untagged PressReleases found');

            return [];
        }

        // Get existing topic names for context
        $existingTopics = $this->getExistingTopicNames();

        // Build article summaries for Gemini
        $articleSummaries = [];
        foreach ($pressReleases as $pr) {
            $articleSummaries[] = [
                'id' => $pr->getId(),
                'title' => $pr->getTitle(),
                'lead' => mb_substr(strip_tags($pr->getContent()), 0, 300),
            ];
        }

        // Call Gemini CLI for analysis
        $proposals = $this->analyzeWithGemini($articleSummaries, $existingTopics);

        // Create Topic entities for high-confidence proposals
        $created = [];
        foreach ($proposals as $proposal) {
            if ($proposal->confidence < self::MIN_CONFIDENCE) {
                continue;
            }

            $topic = $this->createPendingTopic($proposal);
            if ($topic !== null) {
                $created[] = $proposal;
            }
        }

        $this->em->flush();

        $this->logger->info('TopicDiscovery: created {count} pending topic proposals', [
            'count' => \count($created),
            'analyzed' => \count($pressReleases),
        ]);

        return $created;
    }

    /**
     * @return string[]
     */
    private function getExistingTopicNames(): array
    {
        $topics = $this->topicRepository->findBy(['isActive' => true]);
        $names = [];
        foreach ($topics as $topic) {
            $names[] = $topic->getTitle();
        }

        return $names;
    }

    /**
     * @param array<array{id: int, title: string, lead: string}> $articles
     * @param string[] $existingTopics
     *
     * @return TopicProposal[]
     */
    private function analyzeWithGemini(array $articles, array $existingTopics): array
    {
        $articlesJson = json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $topicsList = implode(', ', $existingTopics);

        $prompt = <<<PROMPT
Analizează aceste articole de presă și identifică dacă formează unul sau mai multe subiecte tematice coerente care NU există deja în lista de subiecte.

Subiecte existente: {$topicsList}

Articole:
{$articlesJson}

Pentru fiecare subiect NOU descoperit, răspunde cu JSON array:
[
  {
    "isCoherentTopic": true,
    "proposedName": {"ro": "...", "en": "...", "ru": "..."},
    "keywords": ["keyword1", "keyword2"],
    "relatedArticleIds": [1, 2],
    "suggestedParentTopic": "Existing Parent Topic Name or null",
    "confidence": 0.0-1.0
  }
]

Dacă nu există subiecte noi, răspunde cu: []
Răspunde DOAR cu JSON valid, fără explicații.
PROMPT;

        try {
            $output = $this->geminiCli->execute($prompt, ['timeout' => self::GEMINI_TIMEOUT]);

            return $this->parseGeminiResponse($output);
        } catch (GeminiCliException $e) {
            $this->logger->warning('TopicDiscovery: Gemini analysis failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return TopicProposal[]
     */
    private function parseGeminiResponse(string $output): array
    {
        // Extract JSON from potential markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $output, $matches)) {
            $output = trim($matches[1]);
        }

        $data = json_decode($output, true);
        if (!\is_array($data)) {
            $this->logger->warning('TopicDiscovery: invalid JSON from Gemini', [
                'output' => mb_substr($output, 0, 200),
            ]);

            return [];
        }

        $proposals = [];
        foreach ($data as $item) {
            if (!($item['isCoherentTopic'] ?? false)) {
                continue;
            }

            $proposals[] = new TopicProposal(
                proposedNameRo: $item['proposedName']['ro'] ?? '',
                proposedNameEn: $item['proposedName']['en'] ?? '',
                proposedNameRu: $item['proposedName']['ru'] ?? '',
                keywords: $item['keywords'] ?? [],
                relatedArticleIds: $item['relatedArticleIds'] ?? [],
                confidence: (float) ($item['confidence'] ?? 0),
                suggestedParentTopic: $item['suggestedParentTopic'] ?? null,
            );
        }

        return $proposals;
    }

    private function createPendingTopic(TopicProposal $proposal): ?Topic
    {
        if ($proposal->proposedNameRo === '') {
            return null;
        }

        // Check if topic already exists
        $existing = $this->topicRepository->findOneBy(['title' => $proposal->proposedNameRo]);
        if ($existing !== null) {
            return null;
        }

        $topic = new Topic();
        $topic->setTitle($proposal->proposedNameRo);
        $topic->setReviewStatus('pending_review');
        $topic->setIsActive(false); // Not active until approved

        // Find parent topic if suggested
        if ($proposal->suggestedParentTopic !== null) {
            $parent = $this->topicRepository->findOneBy(['title' => $proposal->suggestedParentTopic]);
            if ($parent !== null) {
                $topic->setParent($parent);
            }
        }

        $this->em->persist($topic);
        $this->em->flush(); // Flush to get ID for translations

        // Add EN/RU translations via Gedmo
        $translationRepo = $this->em->getRepository(Translation::class);
        if ($proposal->proposedNameEn !== '') {
            $translationRepo->translate($topic, 'title', 'en', $proposal->proposedNameEn);
        }
        if ($proposal->proposedNameRu !== '') {
            $translationRepo->translate($topic, 'title', 'ru', $proposal->proposedNameRu);
        }

        $this->logger->info('TopicDiscovery: created pending topic', [
            'topicId' => $topic->getId(),
            'nameRo' => $proposal->proposedNameRo,
            'confidence' => $proposal->confidence,
        ]);

        return $topic;
    }
}
