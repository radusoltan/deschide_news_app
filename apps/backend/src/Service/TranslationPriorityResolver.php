<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Enum\ArticleBadge;
use App\Enum\TranslationPriority;
use App\Repository\ImportantArticlesListRepository;

final readonly class TranslationPriorityResolver
{
    private const EDITORIAL_SLUGS = ['editoriale', 'editorials', 'editorial'];

    public function __construct(
        private ImportantArticlesListRepository $importantArticlesRepository,
    ) {
    }

    public function resolve(Article $article): TranslationPriority
    {
        // P0: Breaking / Alert / Flash badges
        if ($this->hasCriticalBadge($article)) {
            return TranslationPriority::CRITICAL;
        }

        // P1: Article is in Important Articles list (hero section)
        if ($this->isImportantArticle($article)) {
            return TranslationPriority::URGENT;
        }

        // P2: Editoriale category
        if ($this->isEditorial($article)) {
            return TranslationPriority::HIGH;
        }

        // P3: Everything else
        return TranslationPriority::NORMAL;
    }

    private function hasCriticalBadge(Article $article): bool
    {
        $badge = $article->getBadge();

        return $badge === ArticleBadge::BREAKING
            || $badge === ArticleBadge::ALERT
            || $badge === ArticleBadge::FLASH;
    }

    private function isImportantArticle(Article $article): bool
    {
        $articleId = $article->getId();

        if ($articleId === null) {
            return false;
        }

        return $this->importantArticlesRepository->findOneBy(['article' => $articleId]) !== null;
    }

    private function isEditorial(Article $article): bool
    {
        $category = $article->getCategory();

        if ($category === null) {
            return false;
        }

        return \in_array($category->getSlug(), self::EDITORIAL_SLUGS, true);
    }
}
