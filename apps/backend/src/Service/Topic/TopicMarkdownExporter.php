<?php

declare(strict_types=1);

namespace App\Service\Topic;

use App\Entity\Article;
use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Service\Scraping\HtmlToMarkdownConverter;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Exports topic content (articles + press releases) as a markdown bundle.
 *
 * The markdown output includes YAML frontmatter with metadata and
 * chronologically ordered content sections for articles and press releases.
 */
final class TopicMarkdownExporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HtmlToMarkdownConverter $converter,
    ) {
    }

    /**
     * @param array{strip_images?: bool} $options
     */
    public function export(Topic $topic, ?DateRange $range = null, array $options = []): string
    {
        $range ??= DateRange::lastDays(30);
        $stripImages = $options['strip_images'] ?? true;

        $articles = $this->findArticles($topic, $range);
        $pressReleases = $this->findPressReleases($topic, $range);

        $frontmatter = $this->buildFrontmatter($topic, $range, $articles, $pressReleases);
        $yaml = Yaml::dump($frontmatter, 4, 2, Yaml::DUMP_NULL_AS_TILDE);

        $sections = [];
        $sections[] = "---\n{$yaml}---";
        $sections[] = $this->buildArticlesSection($articles, $stripImages);
        $sections[] = $this->buildPressReleasesSection($pressReleases, $stripImages);

        return implode("\n\n", array_filter($sections)) . "\n";
    }

    /**
     * Returns array of individual sources suitable for NotebookLM addTextSource() calls.
     * Each entry: ['title' => string, 'content' => string].
     *
     * @return list<array{title: string, content: string}>
     */
    public function exportSources(
        Topic $topic,
        ?DateRange $range = null,
        int $maxSources = 300,
        int $maxCharsPerItem = 1000,
    ): array {
        $range ??= DateRange::lastDays(30);

        $sources = [];

        // Articles linked to topic
        $articles = $this->findArticles($topic, $range);
        foreach ($articles as $article) {
            if (\count($sources) >= $maxSources) {
                break;
            }
            $date = $article->getPublishedAt()?->format('Y-m-d') ?? 'N/A';
            $content = $article->getContent() ?? '';
            $md = $content !== '' ? $this->converter->convert($content, ['strip_images' => true]) : '';

            $sources[] = [
                'title' => sprintf('Article: %s (%s)', $article->getTitle() ?? 'Untitled', $date),
                'content' => mb_substr($md, 0, $maxCharsPerItem),
            ];
        }

        // Press releases linked to topic via PressReleaseTopic
        $pressReleases = $this->findPressReleases($topic, $range);
        foreach ($pressReleases as $pr) {
            if (\count($sources) >= $maxSources) {
                break;
            }
            $date = $pr->getReceivedAt()->format('Y-m-d');
            $content = $pr->getContent();
            $md = $content !== '' ? $this->converter->convert($content, ['strip_images' => true]) : '';

            $sources[] = [
                'title' => sprintf('Source: %s (%s)', $pr->getTitle(), $date),
                'content' => mb_substr($md, 0, $maxCharsPerItem),
            ];
        }

        return $sources;
    }

    /**
     * @param list<Article> $articles
     * @param list<PressRelease> $pressReleases
     * @return array<string, mixed>
     */
    private function buildFrontmatter(
        Topic $topic,
        DateRange $range,
        array $articles,
        array $pressReleases,
    ): array {
        return [
            'topic_id' => $topic->getId(),
            'topic_name' => $topic->getTitle(),
            'period' => $range->format(),
            'article_count' => \count($articles),
            'pr_count' => \count($pressReleases),
            'generated_at' => (new \DateTimeImmutable())->format('c'),
        ];
    }

    /**
     * @return list<Article>
     */
    private function findArticles(Topic $topic, DateRange $range): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->join('a.topics', 't')
            ->where('t.id = :topicId')
            ->andWhere('a.publishedAt >= :from')
            ->andWhere('a.publishedAt <= :to')
            ->setParameter('topicId', $topic->getId())
            ->setParameter('from', $range->from)
            ->setParameter('to', $range->to)
            ->orderBy('a.publishedAt', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<PressRelease>
     */
    private function findPressReleases(Topic $topic, DateRange $range): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('pr')
            ->from(PressRelease::class, 'pr')
            ->join('pr.pressReleaseTopics', 'prt')
            ->where('prt.topic = :topicId')
            ->andWhere('pr.receivedAt >= :from')
            ->andWhere('pr.receivedAt <= :to')
            ->setParameter('topicId', $topic->getId())
            ->setParameter('from', $range->from)
            ->setParameter('to', $range->to)
            ->orderBy('pr.receivedAt', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<Article> $articles
     */
    private function buildArticlesSection(array $articles, bool $stripImages): string
    {
        if ($articles === []) {
            return "## Articles\n\n*No articles in this period.*";
        }

        $lines = ['## Articles', ''];

        foreach ($articles as $article) {
            $date = $article->getPublishedAt()?->format('Y-m-d') ?? 'N/A';
            $id = $article->getId();
            $lines[] = "### {$article->getTitle()} — ART#{$id} — {$date}";
            $lines[] = '';

            $content = $article->getContent() ?? '';
            if ($content !== '') {
                $md = $this->converter->convert($content, ['strip_images' => $stripImages]);
                $lines[] = $md;
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<PressRelease> $pressReleases
     */
    private function buildPressReleasesSection(array $pressReleases, bool $stripImages): string
    {
        if ($pressReleases === []) {
            return "## Press Releases\n\n*No press releases in this period.*";
        }

        $lines = ['## Press Releases', ''];

        foreach ($pressReleases as $pr) {
            $date = $pr->getReceivedAt()->format('Y-m-d');
            $domain = $pr->getSourceHostname() ?? 'unknown';
            $id = $pr->getId();
            $lines[] = "### {$pr->getTitle()} — {$domain} — PR#{$id} — {$date}";
            $lines[] = '';

            $content = $pr->getContent();
            if ($content !== '') {
                $md = $this->converter->convert($content, ['strip_images' => $stripImages]);
                $lines[] = $md;
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }
}
