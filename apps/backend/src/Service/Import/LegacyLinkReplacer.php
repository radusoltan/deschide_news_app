<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Repository\ArticleRepository;
use App\Repository\ExternalArticleMappingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Psr\Log\LoggerInterface;

/**
 * Phase 3: Replaces internal deschide.md links in article content.
 * Scans both RO content (articles table) and RU content (ext_translations table).
 *
 * Old format: https://deschide.md/articole/{slug}
 * New format: kept as /ro/articole/{slug} (relative, locale-prefixed)
 */
class LegacyLinkReplacer
{
    private const LINK_PATTERN = '/https?:\/\/(?:www\.)?deschide\.md\/articole\/([^\s"<>?]+)(?:\?[^\s"<>]*)?/i';

    /** @var array{replaced: int, orphan: int, articlesUpdated: int} */
    private array $stats = ['replaced' => 0, 'orphan' => 0, 'articlesUpdated' => 0];

    /** @var array<int, array{articleId: int, slug: string, brokenLink: string, referencedSlug: string}> */
    private array $orphanLinks = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleRepository $articleRepository,
        private readonly ExternalArticleMappingRepository $mappingRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Process all imported articles and replace deschide.md links in their content.
     *
     * @param bool $dryRun If true, count replacements but don't persist
     */
    public function replaceAll(bool $dryRun = false, int $batchSize = 100): void
    {
        // Get all articles imported from this CSV source
        $mappings = $this->mappingRepository->findBySource(LegacyArticleImporter::SOURCE_KEY);

        // Build a set of all known slugs for fast lookup
        $knownSlugs = $this->buildSlugIndex();

        $processed = 0;

        foreach ($mappings as $mapping) {
            $article = $mapping->getArticle();
            if ($article === null) {
                continue;
            }

            $changed = false;

            // Replace in RO content
            $content = $article->getContent();
            if ($content !== null && preg_match(self::LINK_PATTERN, $content)) {
                $newContent = $this->replaceLinksInText($content, $knownSlugs, $article->getId(), $article->getSlug());
                if ($newContent !== $content) {
                    if (!$dryRun) {
                        $article->setContent($newContent);
                        $this->entityManager->persist($article);
                    }
                    $changed = true;
                }
            }

            // Replace in RO lead
            $lead = $article->getLead();
            if ($lead !== null && preg_match(self::LINK_PATTERN, $lead)) {
                $newLead = $this->replaceLinksInText($lead, $knownSlugs, $article->getId(), $article->getSlug());
                if ($newLead !== $lead) {
                    if (!$dryRun) {
                        $article->setLead($newLead);
                        $this->entityManager->persist($article);
                    }
                    $changed = true;
                }
            }

            if ($changed) {
                ++$this->stats['articlesUpdated'];
            }

            ++$processed;

            if (!$dryRun && $processed % $batchSize === 0) {
                $this->entityManager->flush();
            }
        }

        // Also process RU translations in ext_translations
        $this->replaceInTranslations($knownSlugs, $dryRun);

        if (!$dryRun) {
            $this->entityManager->flush();
        }
    }

    /**
     * Replace deschide.md links in a text block.
     */
    private function replaceLinksInText(string $text, array $knownSlugs, int $articleId, string $articleSlug): string
    {
        return (string) preg_replace_callback(
            self::LINK_PATTERN,
            function (array $match) use ($knownSlugs, $articleId, $articleSlug): string {
                $referencedSlug = rtrim($match[1], '.,;:!?)');

                if (isset($knownSlugs[$referencedSlug])) {
                    ++$this->stats['replaced'];

                    return '/ro/articole/' . $referencedSlug;
                }

                // Orphan link — referenced article not found
                ++$this->stats['orphan'];
                $this->orphanLinks[] = [
                    'articleId' => $articleId,
                    'articleSlug' => $articleSlug,
                    'brokenLink' => $match[0],
                    'referencedSlug' => $referencedSlug,
                ];

                // Keep the original URL but update domain
                return '/ro/articole/' . $referencedSlug;
            },
            $text,
        );
    }

    /**
     * Replace links in RU translations stored in ext_translations.
     */
    private function replaceInTranslations(array $knownSlugs, bool $dryRun): void
    {
        $translationRepo = $this->entityManager->getRepository(Translation::class);

        // Find all RU content/lead translations for Article that contain deschide.md links
        $qb = $this->entityManager->createQueryBuilder();
        $translations = $qb->select('t')
            ->from(Translation::class, 't')
            ->where('t.objectClass = :class')
            ->andWhere('t.locale = :locale')
            ->andWhere('t.field IN (:fields)')
            ->andWhere('t.content LIKE :pattern')
            ->setParameter('class', 'App\\Entity\\Article')
            ->setParameter('locale', 'ru')
            ->setParameter('fields', ['content', 'lead'])
            ->setParameter('pattern', '%deschide.md/articole/%')
            ->getQuery()
            ->getResult();

        foreach ($translations as $translation) {
            $content = $translation->getContent();
            $newContent = $this->replaceLinksInText(
                $content,
                $knownSlugs,
                (int) $translation->getForeignKey(),
                'ru-translation',
            );

            if ($newContent !== $content && !$dryRun) {
                $translation->setContent($newContent);
                $this->entityManager->persist($translation);
            }
        }
    }

    /**
     * Build a lookup index of all article slugs in the DB.
     *
     * @return array<string, true>
     */
    private function buildSlugIndex(): array
    {
        $qb = $this->articleRepository->createQueryBuilder('a');
        $slugs = $qb->select('a.slug')
            ->getQuery()
            ->getSingleColumnResult();

        $index = [];
        foreach ($slugs as $slug) {
            $index[$slug] = true;
        }

        return $index;
    }

    /**
     * @return array{replaced: int, orphan: int, articlesUpdated: int}
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * @return array<int, array{articleId: int, articleSlug: string, brokenLink: string, referencedSlug: string}>
     */
    public function getOrphanLinks(): array
    {
        return $this->orphanLinks;
    }

    public function resetStats(): void
    {
        $this->stats = ['replaced' => 0, 'orphan' => 0, 'articlesUpdated' => 0];
        $this->orphanLinks = [];
    }
}
