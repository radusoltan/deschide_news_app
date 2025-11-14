<?php

declare(strict_types=1);

namespace App\Service\Import;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PDO;

/**
 * Service pentru queries către baza Newscoop (MySQL).
 */
class NewscoopConnectionService
{
    private const LOCALE_MAP = [
        'ro' => 2,
        'ru' => 15,
        'en' => 1,
    ];

    public function __construct(
        private readonly Connection $newscoopConnection
    ) {
    }

    /**
     * Fetch articole din Newscoop.
     *
     * @param string $locale Limba (ro, ru, en)
     * @param bool $published Doar articole published
     * @param int|null $limit Limită rezultate
     * @param int $offset Offset pentru paginare
     *
     * @throws Exception
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchArticles(
        string $locale,
        bool $published = true,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $idLanguage = self::LOCALE_MAP[$locale] ?? 2;

        $sql = 'SELECT
            a.Number,
            a.Name,
            a.IdLanguage,
            a.Published,
            a.PublishDate,
            a.UploadDate,
            a.time_updated,
            a.OnFrontPage,
            a.Keywords,
            a.webcode,
            a.NrSection,
            a.NrIssue,
            x.FTitlu,
            x.Fsubtitlu,
            x.Flead,
            x.FContinut,
            x.FBREAKING_NEWS,
            x.FNEWS_ALERT,
            x.FFLASH
        FROM Articles a
        LEFT JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
        WHERE a.Type = :type
          AND a.Published = :published
          AND a.IdLanguage = :idLanguage
        ORDER BY a.Number';

        if ($limit) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $params = [
            'type' => 'stiri',
            'published' => $published ? 'Y' : 'N',
            'idLanguage' => $idLanguage,
        ];

        $types = [];

        if ($limit) {
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            $types['limit'] = PDO::PARAM_INT;
            $types['offset'] = PDO::PARAM_INT;
        }

        return $this->newscoopConnection->fetchAllAssociative($sql, $params, $types);
    }

    /**
     * Fetch categorii (Sections) din Newscoop.
     *
     * @param string $locale Limba (ro, ru, en)
     * @param int|null $limit Limită rezultate
     *
     * @throws Exception
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchCategories(string $locale, ?int $limit = null): array
    {
        $idLanguage = self::LOCALE_MAP[$locale] ?? 2;

        $sql = 'SELECT
            s.Number,
            s.IdLanguage,
            s.Name,
            s.ShortName,
            s.Description,
            s.NrIssue
        FROM Sections s
        WHERE s.IdLanguage = :idLanguage
        ORDER BY s.Number';

        if ($limit) {
            $sql .= ' LIMIT :limit';
        }

        $params = [
            'idLanguage' => $idLanguage,
        ];

        $types = [];

        if ($limit) {
            $params['limit'] = $limit;
            $types['limit'] = PDO::PARAM_INT;
        }

        return $this->newscoopConnection->fetchAllAssociative($sql, $params, $types);
    }

    /**
     * Fetch autori din Newscoop.
     *
     * @param int|null $limit Limită rezultate
     *
     * @throws Exception
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAuthors(?int $limit = null): array
    {
        $sql = 'SELECT
            id,
            first_name,
            last_name,
            email,
            biography
        FROM Authors
        ORDER BY id';

        if ($limit) {
            $sql .= ' LIMIT :limit';
        }

        $params = [];
        $types = [];

        if ($limit) {
            $params['limit'] = $limit;
            $types['limit'] = PDO::PARAM_INT;
        }

        return $this->newscoopConnection->fetchAllAssociative($sql, $params, $types);
    }

    /**
     * Fetch imagini folosite în articole published.
     *
     * @param int|null $limit Limită rezultate
     * @param int $offset Offset pentru paginare
     *
     * @throws Exception
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchImages(?int $limit = null, int $offset = 0): array
    {
        $sql = 'SELECT DISTINCT
            i.Id,
            i.ImageFileName,
            i.Description,
            i.width,
            i.height,
            i.ContentType,
            i.Photographer,
            i.photographer_url,
            i.Source,
            i.TimeCreated
        FROM Images i
        INNER JOIN ArticleImages ai ON i.Id = ai.IdImage
        INNER JOIN Articles a ON ai.NrArticle = a.Number
        WHERE a.Type = :type
          AND a.Published = :published
        ORDER BY i.Id';

        if ($limit) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $params = [
            'type' => 'stiri',
            'published' => 'Y',
        ];

        $types = [];

        if ($limit) {
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            $types['limit'] = PDO::PARAM_INT;
            $types['offset'] = PDO::PARAM_INT;
        }

        return $this->newscoopConnection->fetchAllAssociative($sql, $params, $types);
    }

    /**
     * Fetch traduceri pentru un articol specific.
     *
     * @param int $articleNumber Article Number din Newscoop
     * @param array<int> $languageIds IDs limbilor (1=en, 15=ru)
     *
     * @throws Exception
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchTranslations(int $articleNumber, array $languageIds): array
    {
        $placeholders = implode(',', array_fill(0, \count($languageIds), '?'));

        $sql = "SELECT
            a.Number,
            a.Name,
            a.IdLanguage,
            x.FTitlu,
            x.Fsubtitlu,
            x.Flead,
            x.FContinut,
            l.RFC3066bis as locale
        FROM Articles a
        LEFT JOIN Xstiri x ON a.Number = x.NrArticle AND a.IdLanguage = x.IdLanguage
        INNER JOIN Languages l ON a.IdLanguage = l.Id
        WHERE a.Type = 'stiri'
          AND a.Published = 'Y'
          AND a.Number = ?
          AND a.IdLanguage IN ($placeholders)
        ORDER BY a.IdLanguage";

        $params = array_merge([$articleNumber], $languageIds);

        return $this->newscoopConnection->fetchAllAssociative($sql, $params);
    }

    /**
     * Test conexiune Newscoop.
     *
     * @return bool True dacă conexiunea funcționează
     */
    public function testConnection(): bool
    {
        try {
            $result = $this->newscoopConnection->fetchOne('SELECT 1');

            return $result === 1 || $result === '1';
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get statistici Newscoop.
     *
     * @throws Exception
     *
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        $stats = [];

        // Total articole published
        $stats['articles_total'] = (int) $this->newscoopConnection->fetchOne(
            "SELECT COUNT(*) FROM Articles WHERE Type='stiri' AND Published='Y'"
        );

        // Articole per limbă
        $result = $this->newscoopConnection->fetchAllAssociative(
            "SELECT IdLanguage, COUNT(*) as count
             FROM Articles
             WHERE Type='stiri' AND Published='Y'
             GROUP BY IdLanguage"
        );

        foreach ($result as $row) {
            $locale = array_search($row['IdLanguage'], self::LOCALE_MAP, true);
            if ($locale) {
                $stats["articles_$locale"] = (int) $row['count'];
            }
        }

        // Total imagini
        $stats['images_total'] = (int) $this->newscoopConnection->fetchOne(
            'SELECT COUNT(*) FROM Images'
        );

        // Imagini folosite în articole
        $stats['images_used'] = (int) $this->newscoopConnection->fetchOne(
            "SELECT COUNT(DISTINCT ai.IdImage)
             FROM ArticleImages ai
             INNER JOIN Articles a ON ai.NrArticle = a.Number
             WHERE a.Type='stiri' AND a.Published='Y'"
        );

        // Total categorii
        $stats['categories_total'] = (int) $this->newscoopConnection->fetchOne(
            'SELECT COUNT(DISTINCT Number) FROM Sections'
        );

        // Total autori
        $stats['authors_total'] = (int) $this->newscoopConnection->fetchOne(
            'SELECT COUNT(*) FROM Authors'
        );

        return $stats;
    }
}
