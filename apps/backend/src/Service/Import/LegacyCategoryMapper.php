<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Psr\Log\LoggerInterface;

/**
 * Maps CSV category slugs from the legacy deschide.md export to Category entities.
 * All 11 target categories are created by CategoryFixtures — no runtime auto-creation.
 */
class LegacyCategoryMapper
{
    /** CSV slug → DB slug mapping */
    private const CATEGORY_SLUG_MAP = [
        // Direct mappings (slug rename only)
        'politic' => 'politica',
        'social' => 'societate',
        'economic' => 'economie',
        'editorial' => 'editoriale',
        'externe' => 'externe',
        'romania' => 'romania',
        'cultura' => 'cultura',
        'opinii' => 'opinii',
        'sport' => 'sport',
        'advertorial' => 'advertorial',
        'anti-fake' => 'anti-fake',

        // Absorbed mappings (legacy → parent category)
        'alegeri' => 'politica',
        'transnistria' => 'politica',
        'dialog-deschis' => 'opinii',
    ];

    /** @var array<string, Category> */
    private array $cache = [];

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Map a CSV category slug to a Category entity.
     * Returns null if the slug is empty or unknown.
     */
    public function map(string $csvCategorySlug): ?Category
    {
        $csvCategorySlug = trim(mb_strtolower($csvCategorySlug));

        if (empty($csvCategorySlug)) {
            return null;
        }

        // Check cache
        if (isset($this->cache[$csvCategorySlug])) {
            return $this->cache[$csvCategorySlug];
        }

        // Map to DB slug
        $dbSlug = self::CATEGORY_SLUG_MAP[$csvCategorySlug] ?? null;

        if ($dbSlug === null) {
            $this->logger->warning('Unknown CSV category slug: {slug}', ['slug' => $csvCategorySlug]);

            return null;
        }

        // Find in DB — all 11 categories are created by fixtures
        $category = $this->categoryRepository->findOneBy(['slug' => $dbSlug]);

        if ($category === null) {
            $this->logger->warning('Category not found in DB: {slug}', ['slug' => $dbSlug]);

            return null;
        }

        $this->cache[$csvCategorySlug] = $category;

        return $category;
    }

    /**
     * Clear the internal cache (call after EntityManager::clear()).
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * No-op — all categories now come from fixtures.
     * Kept for backward compatibility with ImportCsvLegacyArticlesCommand.
     */
    public function ensureAllCategoriesExist(): void
    {
        // All 11 categories are created by CategoryFixtures, nothing to auto-create.
    }
}
