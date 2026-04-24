<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Article;
use App\Repository\ExternalArticleMappingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Psr\Log\LoggerInterface;

/**
 * Phase 2: Imports RU translations for already-imported articles via Gedmo Translatable.
 * Operates on the ext_translations table directly via the Gedmo Translation repository.
 */
class LegacyTranslationImporter
{
    /** @var array{translated: int, skipped: int, errors: int} */
    private array $stats = ['translated' => 0, 'skipped' => 0, 'errors' => 0];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalArticleMappingRepository $mappingRepository,
        private readonly LegacyHtmlSanitizer $sanitizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Import RU translation for a single parsed CSV row.
     * The article must already exist in the DB (imported in Phase 1).
     */
    public function importTranslation(array $data, bool $dryRun = false): bool
    {
        if (!$data['hasRuTranslation']) {
            return false;
        }

        $uuid = $data['uuid'];

        // Find the article via ExternalArticleMapping
        $article = $this->mappingRepository->findArticleByExternalId(
            LegacyArticleImporter::SOURCE_KEY,
            $uuid,
        );

        if ($article === null) {
            $this->logger->debug('Skipping RU translation for non-imported article: {uuid}', ['uuid' => $uuid]);
            ++$this->stats['skipped'];

            return false;
        }

        if ($dryRun) {
            $this->logger->info('[DRY] Would translate: {title}', [
                'title' => mb_substr($data['titleRu'], 0, 60),
            ]);
            ++$this->stats['translated'];

            return true;
        }

        try {
            $this->applyTranslation($article, $data);
            ++$this->stats['translated'];

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to translate article {uuid}: {error}', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            ++$this->stats['errors'];

            return false;
        }
    }

    /**
     * Apply RU translation to an article via Gedmo Translation repository.
     */
    private function applyTranslation(Article $article, array $data): void
    {
        /** @var \Gedmo\Translatable\Entity\Repository\TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository(Translation::class);

        $locale = 'ru';

        // Title (required)
        $titleRu = $this->sanitizer->normalizeDiacritics(trim($data['titleRu']));
        if (mb_strlen($titleRu) > 255) {
            $titleRu = mb_substr($titleRu, 0, 252) . '...';
        }
        $translationRepo->translate($article, 'title', $locale, $titleRu);

        // Slug
        if (!empty(trim($data['slugRu']))) {
            $translationRepo->translate($article, 'slug', $locale, trim($data['slugRu']));
        }

        // Lead
        if (!empty(trim($data['leadRu']))) {
            $leadRu = $this->sanitizer->sanitizePlainText($data['leadRu']);
            $translationRepo->translate($article, 'lead', $locale, $leadRu);
        }

        // Content
        if (!empty(trim($data['contentRu']))) {
            $contentRu = $this->sanitizer->sanitize($data['contentRu']);
            $translationRepo->translate($article, 'content', $locale, $contentRu);
        }

        // Add 'ru' to publishedLocales
        if (!$article->isPublishedInLocale('ru')) {
            $article->addPublishedLocale('ru');
            $this->entityManager->persist($article);
        }
    }

    /**
     * Flush pending translations.
     */
    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * @return array{translated: int, skipped: int, errors: int}
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    public function resetStats(): void
    {
        $this->stats = ['translated' => 0, 'skipped' => 0, 'errors' => 0];
    }
}
