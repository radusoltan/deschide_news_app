<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Dto\Scraping\ScrapedContent;
use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Message\Editorial\ProcessScrapedArticleMessage;
use App\Message\TranslateArticleMessage;
use App\Service\Scraping\FrontmatterGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ProcessScrapedArticleHandler
{
    public function __construct(
        private FrontmatterGenerator $frontmatterGenerator,
        private EntityManagerInterface $em,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private ?string $vaultPath = null,
    ) {}

    public function __invoke(ProcessScrapedArticleMessage $message): void
    {
        // Check if article with this hash already exists (race condition guard)
        $existing = $this->em->getRepository(Article::class)->findOneBy([
            'contentHash' => $message->contentHash,
        ]);

        if ($existing !== null) {
            $this->logger->debug('ProcessScrapedArticleHandler: duplicate hash, skipping', [
                'hash' => $message->contentHash,
            ]);

            return;
        }

        // Create article entity directly in PostgreSQL
        $article = new Article();
        $article->setTranslatableLocale($message->originalLanguage);
        $article->setTitle($message->title);
        $article->setContent($message->bodyMarkdown);
        $article->setLead(mb_substr(strip_tags($message->bodyMarkdown), 0, 300) ?: null);
        $article->setStatus(ArticleStatus::NEW);
        $article->setContentHash($message->contentHash);

        // Store source URL as sourceEmail field (used for vault ID / source tracking)
        $sourceId = 'scrape-' . mb_substr($message->contentHash, 0, 50);
        $article->setSourceEmail($sourceId);

        if ($message->publishedAt !== null) {
            $article->setPublishedAt($message->publishedAt);
        }

        $this->em->persist($article);
        $this->em->flush();

        $this->logger->info('ProcessScrapedArticleHandler: article created', [
            'articleId' => $article->getId(),
            'title' => mb_substr($message->title, 0, 80),
            'source' => $message->sourceName,
        ]);

        // Write vault file if vault path is configured
        $this->writeVaultFile($message);

        // Dispatch translation for non-original locales
        $targetLocales = array_values(array_diff(['ro', 'en', 'ru'], [$message->originalLanguage]));
        if ($targetLocales !== []) {
            $this->messageBus->dispatch(new TranslateArticleMessage(
                articleId: $article->getId(),
                locales: $targetLocales,
            ));
        }
    }

    private function writeVaultFile(ProcessScrapedArticleMessage $message): void
    {
        if ($this->vaultPath === null || $this->vaultPath === '') {
            return;
        }

        $date = $message->publishedAt ?? new \DateTimeImmutable();
        $dirPath = sprintf('%s/articles/%s/%s', $this->vaultPath, $date->format('Y'), $date->format('m'));

        if (!is_dir($dirPath) && !mkdir($dirPath, 0o755, true)) {
            $this->logger->warning('ProcessScrapedArticleHandler: cannot create vault directory', [
                'path' => $dirPath,
            ]);

            return;
        }

        // Build a simple frontmatter + body document
        $scraped = new ScrapedContent(
            url: $message->sourceUrl,
            title: $message->title,
            bodyHtml: '',
            bodyText: $message->bodyMarkdown,
            language: $message->originalLanguage,
            sourceName: $message->sourceName,
            publishedAt: $message->publishedAt,
        );

        $content = $this->frontmatterGenerator->generate($scraped, $message->bodyMarkdown);

        $slug = $this->slugify($message->title);
        $filePath = "{$dirPath}/{$slug}.md";

        file_put_contents($filePath, $content);
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
        $text = preg_replace('/-+/', '-', trim($text, '-'));

        // Limit slug length
        return mb_substr($text, 0, 80);
    }
}
