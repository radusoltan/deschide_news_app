<?php

declare(strict_types=1);

namespace App\Service\Translation;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Checks whether an article has all required translated fields for a given locale.
 *
 * Required fields: title, lead, content (must all be non-empty in ext_translations).
 * Default locale (ro) is always considered complete since fields live on the entity itself.
 */
final readonly class ArticleTranslationCompletenessChecker
{
    private const REQUIRED_FIELDS = ['title', 'lead', 'content'];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function isComplete(Article $article, string $locale): bool
    {
        if ($locale === 'ro') {
            return !empty($article->getTitle()) && !empty($article->getLead()) && !empty($article->getContent());
        }

        $missing = $this->getMissingFields($article, $locale);

        return $missing === [];
    }

    /**
     * @return string[] Locales with complete translations
     */
    public function getCompleteLocales(Article $article): array
    {
        $locales = ['ro'];

        foreach (['en', 'ru'] as $locale) {
            if ($this->isComplete($article, $locale)) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    /**
     * @return string[] Field names missing translation for the given locale
     */
    public function getMissingFields(Article $article, string $locale): array
    {
        if ($locale === 'ro') {
            $missing = [];
            if (empty($article->getTitle())) {
                $missing[] = 'title';
            }
            if (empty($article->getLead())) {
                $missing[] = 'lead';
            }
            if (empty($article->getContent())) {
                $missing[] = 'content';
            }

            return $missing;
        }

        $articleId = $article->getId();

        if ($articleId === null) {
            return self::REQUIRED_FIELDS;
        }

        $conn = $this->entityManager->getConnection();

        $rows = $conn->executeQuery(
            "SELECT field FROM ext_translations
             WHERE object_class = 'App\\Entity\\Article'
               AND foreign_key = :foreignKey
               AND locale = :locale
               AND field IN (:fields)
               AND content IS NOT NULL
               AND content != ''",
            [
                'foreignKey' => (string) $articleId,
                'locale' => $locale,
                'fields' => self::REQUIRED_FIELDS,
            ],
            [
                'foreignKey' => \Doctrine\DBAL\ParameterType::STRING,
                'locale' => \Doctrine\DBAL\ParameterType::STRING,
                'fields' => \Doctrine\DBAL\ArrayParameterType::STRING,
            ],
        )->fetchFirstColumn();

        return array_values(array_diff(self::REQUIRED_FIELDS, $rows));
    }
}
