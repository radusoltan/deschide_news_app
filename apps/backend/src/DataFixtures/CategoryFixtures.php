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
            $manager->flush();

            // Reference for MenuItemFixtures and other dependents
            $this->addReference("category-{$data['slug']}", $category);
        }
    }
}
