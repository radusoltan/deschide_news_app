<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Enum\CategoryStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Gedmo\Translatable\Entity\Translation;

class CategoryFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * 11 editorial categories — hardcoded (small, stable set).
     * Slugs must match LegacyCategoryMapper::CATEGORY_SLUG_MAP values.
     *
     * @see \App\Service\Import\LegacyCategoryMapper
     */
    private const CATEGORIES = [
        [
            'ro' => 'Politică',
            'en' => 'Politics',
            'ru' => 'Политика',
            'slug' => 'politica',
            'frontPagePosition' => 1,
            'frontPageLayout' => 'featured',
        ],
        [
            'ro' => 'Societate',
            'en' => 'Society',
            'ru' => 'Общество',
            'slug' => 'societate',
            'frontPagePosition' => 2,
            'frontPageLayout' => 'grid_4_cols',
        ],
        [
            'ro' => 'Externe',
            'en' => 'International',
            'ru' => 'Международные',
            'slug' => 'externe',
            'frontPagePosition' => 3,
            'frontPageLayout' => 'featured',
        ],
        [
            'ro' => 'Economie',
            'en' => 'Economy',
            'ru' => 'Экономика',
            'slug' => 'economie',
            'frontPagePosition' => 4,
            'frontPageLayout' => 'compact_list',
        ],
        [
            'ro' => 'România',
            'en' => 'Romania',
            'ru' => 'Румыния',
            'slug' => 'romania',
            'frontPagePosition' => 5,
            'frontPageLayout' => 'grid_3_cols',
        ],
        [
            'ro' => 'Cultură',
            'en' => 'Culture',
            'ru' => 'Культура',
            'slug' => 'cultura',
            'frontPagePosition' => 6,
            'frontPageLayout' => 'grid_3_cols',
        ],
        [
            'ro' => 'Sport',
            'en' => 'Sports',
            'ru' => 'Спорт',
            'slug' => 'sport',
            'frontPagePosition' => 7,
            'frontPageLayout' => 'compact_list',
        ],
        [
            'ro' => 'Editoriale',
            'en' => 'Editorials',
            'ru' => 'Редакция',
            'slug' => 'editoriale',
            'frontPagePosition' => 8,
            'frontPageLayout' => 'featured',
        ],
        [
            'ro' => 'Opinii',
            'en' => 'Opinions',
            'ru' => 'Мнения',
            'slug' => 'opinii',
            'frontPagePosition' => 9,
            'frontPageLayout' => 'compact_list',
        ],
        [
            'ro' => 'Advertorial',
            'en' => 'Advertorial',
            'ru' => 'Рекламный материал',
            'slug' => 'advertorial',
            'frontPagePosition' => 10,
            'frontPageLayout' => 'featured',
        ],
        [
            'ro' => 'Anti-Fake',
            'en' => 'Anti-Fake',
            'ru' => 'Антифейк',
            'slug' => 'anti-fake',
            'frontPagePosition' => 11,
            'frontPageLayout' => 'featured',
        ],
    ];

    public static function getGroups(): array
    {
        return ['dev', 'test', 'categories'];
    }

    public function load(ObjectManager $manager): void
    {
        $translationRepo = $manager->getRepository(Translation::class);
        $slugTranslationsByRoSlug = $this->loadSlugTranslations();

        foreach (self::CATEGORIES as $data) {
            $category = new Category();

            // Set Romanian (default locale) — stored directly in categories table
            $category->setTranslatableLocale('ro');
            $category->setTitle($data['ro']);
            $category->setSlug($data['slug']);
            $category->setStatus(CategoryStatus::ACTIVE);
            $category->setOnFrontPage(true);
            $category->setFrontPagePosition($data['frontPagePosition']);
            $category->setFrontPageLayout($data['frontPageLayout']);
            $category->setInMenu(true);
            $category->setInFooterMenu(false);

            $manager->persist($category);
            $manager->flush();

            // Use Translation repository to guarantee EN/RU translations are stored,
            // even when the translated value matches the default locale (e.g. "Advertorial")
            $translationRepo->translate($category, 'title', 'en', $data['en']);
            $translationRepo->translate($category, 'title', 'ru', $data['ru']);

            // Persist translated slugs from JSON source-of-truth (T60.6)
            $slugTranslations = $slugTranslationsByRoSlug[$data['slug']] ?? null;
            if ($slugTranslations !== null) {
                foreach ($slugTranslations as $locale => $translatedSlug) {
                    $translationRepo->translate($category, 'slug', $locale, $translatedSlug);
                }
            }

            $manager->flush();

            // Reference for MenuItemFixtures and other dependents
            $this->addReference("category-{$data['slug']}", $category);
        }
    }

    /**
     * @return array<string, array<string, string>> keyed by RO slug → [locale => translated_slug]
     */
    private function loadSlugTranslations(): array
    {
        $path = __DIR__ . '/../../fixtures/data/category-slug-translations.json';
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        try {
            $entries = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!\is_array($entries)) {
            return [];
        }

        $byRoSlug = [];
        foreach ($entries as $entry) {
            $roSlug = $entry['ro_slug'] ?? null;
            $translations = $entry['translations'] ?? [];
            if (!\is_string($roSlug) || !\is_array($translations)) {
                continue;
            }

            foreach ($translations as $locale => $payload) {
                if (!\is_string($locale) || !\is_array($payload)) {
                    continue;
                }
                $proposed = $payload['proposed_slug'] ?? null;
                if (\is_string($proposed) && $proposed !== '') {
                    $byRoSlug[$roSlug][$locale] = $proposed;
                }
            }
        }

        return $byRoSlug;
    }
}
