<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Entity\GeneratedContent;
use App\Service\NotebookLM\NotebookLMService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

final class DossierGenerationService
{
    private const GEMINI_TIMEOUT = 180;

    public function __construct(
        private readonly GeminiCliService $geminiCli,
        private readonly EntityManagerInterface $em,
        private readonly NotebookLMService $notebookLMService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Generate a narrative dossier for a topic and persist as GeneratedContent.
     *
     * @param list<Article> $recentArticles
     */
    public function generateDossier(string $topicName, array $recentArticles): ?GeneratedContent
    {
        // Build article summaries for the prompt
        $articleSummaries = $this->buildArticleSummaries($recentArticles);

        $prompt = $this->buildDossierPrompt($topicName, $articleSummaries);
        $dossierContent = $this->callGemini($prompt);

        if ($dossierContent === null) {
            $this->logger->warning('DossierGeneration: Gemini failed for dossier', [
                'topic' => $topicName,
            ]);

            return null;
        }

        // Enrich with NotebookLM insights if available
        $insights = $this->enrichWithNotebookLM($topicName, $recentArticles);

        $fullContent = $dossierContent;
        if ($insights !== null) {
            $fullContent .= "\n\n## Insight-uri NotebookLM\n\n{$insights}";
        }

        $articleIds = array_map(fn (Article $a) => $a->getId(), $recentArticles);

        $gc = new GeneratedContent();
        $gc->setType('dossier');
        $gc->setTitle('Dosar: ' . mb_substr($topicName, 0, 200));
        $gc->setContent($fullContent);
        $gc->setMetadata([
            'topic' => $topicName,
            'article_count' => \count($recentArticles),
            'article_ids' => $articleIds,
        ]);
        $this->em->persist($gc);
        $this->em->flush();

        $this->logger->info('DossierGeneration: dossier created', [
            'topic' => $topicName,
            'articles' => \count($recentArticles),
        ]);

        return $gc;
    }

    /**
     * Get recent articles for a topic by searching the database.
     *
     * @return list<Article>
     */
    public function getRecentArticlesForTopic(string $topicName, int $days = 30, int $limit = 50): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        $qb = $this->em->getRepository(Article::class)->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->where('a.publishedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        // Try to match category name to topic
        $normalizedTopic = mb_strtolower(str_replace([' ', '-'], ['', ''], $topicName));
        $qb->andWhere('LOWER(c.name) LIKE :topic')
            ->setParameter('topic', "%{$normalizedTopic}%");

        return $qb->getQuery()->getResult();
    }

    private function buildDossierPrompt(string $topicName, string $articleSummaries): string
    {
        return <<<PROMPT
Ești un analist editorial pentru o redacție de știri moldovenească.
Generează un dosar narativ pe tema "{$topicName}" bazat pe următoarele articole recente:

{$articleSummaries}

Structura dosarului:
# Dosar: {$topicName}

## Rezumat executiv
(3-5 propoziții care captează esența situației)

## Cronologie evenimentelor
(Lista evenimentelor cheie cu dată, în ordine cronologică)

## Actori cheie și pozițiile lor
(Persoane și instituții principale, cu pozițiile lor declarate)

## Analiză
(Tendințe observate, schimbări de direcție, implicații)

## Întrebări nerezolvate
(Subiecte care necesită investigație suplimentară, lacune de acoperire)

## Recomandări editoriale
(3-5 sugestii concrete pentru echipa de redacție)

Scrie în limba română cu diacritice comma-below (ș, ț).
Tonul: profesional, analitic, echilibrat.
Nu inventa informații — bazează-te strict pe articolele furnizate.
PROMPT;
    }

    /**
     * @param list<Article> $articles
     */
    private function buildArticleSummaries(array $articles): string
    {
        $summaries = [];

        foreach ($articles as $article) {
            $date = $article->getPublishedAt()?->format('Y-m-d') ?? 'N/A';
            $title = $article->getTitle() ?? 'Fără titlu';
            $lead = mb_substr($article->getLead() ?? $article->getContent() ?? '', 0, 200);

            $summaries[] = "- [{$date}] {$title}\n  {$lead}";
        }

        return implode("\n\n", $summaries);
    }

    /**
     * @param list<Article> $articles
     */
    private function enrichWithNotebookLM(string $topicName, array $articles): ?string
    {
        if (!$this->notebookLMService->isAvailable()) {
            return null;
        }

        $notebooks = []; // Would need to be injected — use resolveNotebookId pattern
        $notebookId = $this->notebookLMService->resolveNotebookId(
            $this->slugify($topicName),
            $notebooks,
        );

        if ($notebookId === null) {
            return null;
        }

        // Feed recent articles to notebook
        foreach (array_slice($articles, 0, 10) as $article) {
            $this->notebookLMService->addTextSource(
                $notebookId,
                $article->getTitle() ?? 'Article',
                mb_substr($article->getContent() ?? '', 0, 5000),
            );
        }

        // Ask analytical questions
        $questions = [
            'Care sunt contradicțiile între sursele încărcate?',
            'Ce subiecte apar în toate sursele dar nu au fost aprofundate?',
        ];

        $insights = [];
        foreach ($questions as $q) {
            $answer = $this->notebookLMService->ask($notebookId, $q);
            if ($answer !== null) {
                $insights[] = "**{$q}**\n{$answer}";
            }
        }

        return $insights !== [] ? implode("\n\n", $insights) : null;
    }

    /**
     * @param list<Article> $articles
     */
    private function buildDossierFrontmatter(string $topicName, string $date, array $articles): string
    {
        $articleIds = array_map(fn (Article $a) => $a->getId(), $articles);

        return "type: dossier\n"
            . "topic: {$topicName}\n"
            . "date_created: {$date}\n"
            . "article_count: " . \count($articles) . "\n"
            . "article_ids: [" . implode(', ', $articleIds) . "]\n"
            . "ai:\n"
            . "  auto_generated: true\n"
            . "  reviewed: false\n"
            . "  processed_by: gemini-cli\n"
            . "  processed_at: {$date}\n";
    }

    private function callGemini(string $prompt): ?string
    {
        try {
            return $this->geminiCli->execute($prompt, ['timeout' => self::GEMINI_TIMEOUT]);
        } catch (GeminiCliException $e) {
            $this->logger->error('DossierGeneration: Gemini exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ', ' '],
            ['a', 'a', 'i', 's', 't', 's', 't', '-'],
            $text,
        );
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);

        return preg_replace('/-+/', '-', trim($text, '-'));
    }
}
