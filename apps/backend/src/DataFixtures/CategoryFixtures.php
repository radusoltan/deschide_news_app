<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Enum\CategoryStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    /**
     * Categories matching legacy deschide.md structure.
     * Slug must match LegacyCategoryMapper::CATEGORY_SLUG_MAP values.
     */
    private const CATEGORIES = [
        [
            'ro' => 'Politică',
            'en' => 'Politics',
            'ru' => 'Политика',
            'slug' => 'politica',
            'onFrontPage' => true,
            'frontPagePosition' => 1,
            'frontPageLayout' => 'featured',
            'inMenu' => true,
        ],
        [
            'ro' => 'Societate',
            'en' => 'Society',
            'ru' => 'Общество',
            'slug' => 'societate',
            'onFrontPage' => true,
            'frontPagePosition' => 2,
            'frontPageLayout' => 'grid_4_cols',
            'inMenu' => true,
        ],
        [
            'ro' => 'Externe',
            'en' => 'International',
            'ru' => 'Международные',
            'slug' => 'externe',
            'onFrontPage' => true,
            'frontPagePosition' => 3,
            'frontPageLayout' => 'featured',
            'inMenu' => true,
        ],
        [
            'ro' => 'Economie',
            'en' => 'Economy',
            'ru' => 'Экономика',
            'slug' => 'economie',
            'onFrontPage' => true,
            'frontPagePosition' => 4,
            'frontPageLayout' => 'compact_list',
            'inMenu' => true,
        ],
        [
            'ro' => 'România',
            'en' => 'Romania',
            'ru' => 'Румыния',
            'slug' => 'romania',
            'onFrontPage' => false,
            'frontPagePosition' => 0,
            'frontPageLayout' => null,
            'inMenu' => true,
        ],
        [
            'ro' => 'Cultură',
            'en' => 'Culture',
            'ru' => 'Культура',
            'slug' => 'cultura',
            'onFrontPage' => true,
            'frontPagePosition' => 5,
            'frontPageLayout' => 'grid_3_cols',
            'inMenu' => true,
        ],
        [
            'ro' => 'Sport',
            'en' => 'Sports',
            'ru' => 'Спорт',
            'slug' => 'sport',
            'onFrontPage' => true,
            'frontPagePosition' => 6,
            'frontPageLayout' => 'compact_list',
            'inMenu' => true,
        ],
        [
            'ro' => 'Editoriale',
            'en' => 'Editorials',
            'ru' => 'Редакция',
            'slug' => 'editoriale',
            'onFrontPage' => true,
            'frontPagePosition' => 7,
            'frontPageLayout' => 'featured',
            'inMenu' => true,
        ],
        [
            'ro' => 'Opinii',
            'en' => 'Opinions',
            'ru' => 'Мнения',
            'slug' => 'opinii',
            'onFrontPage' => true,
            'frontPagePosition' => 8,
            'frontPageLayout' => null,
            'inMenu' => true,
        ],
        [
            'ro' => 'Advertorial',
            'en' => 'Advertorial',
            'ru' => 'Рекламный материал',
            'slug' => 'advertorial',
            'onFrontPage' => true,
            'frontPagePosition' => 9,
            'frontPageLayout' => 'featured',
            'inMenu' => true,
        ],
        [
            'ro' => 'Anti-Fake',
            'en' => 'Anti-Fake',
            'ru' => 'Антифейк',
            'slug' => 'anti-fake',
            'onFrontPage' => true,
            'frontPagePosition' => 10,
            'frontPageLayout' => 'featured',
            'inMenu' => true,
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::CATEGORIES as $index => $data) {
            $category = new Category();

            // Set Romanian (default locale)
            $category->setTitle($data['ro']);
            $category->setSlug($data['slug']);
            $category->setStatus(CategoryStatus::ACTIVE);
            $category->setOnFrontPage($data['onFrontPage']);
            $category->setFrontPagePosition($data['frontPagePosition']);
            $category->setFrontPageLayout($data['frontPageLayout']);
            $category->setInMenu($data['inMenu']);
            $category->setTranslatableLocale('ro');

            $manager->persist($category);
            $manager->flush();

            // Set English translation
            $category->setTitle($data['en']);
            $category->setTranslatableLocale('en');
            $manager->persist($category);
            $manager->flush();

            // Set Russian translation
            $category->setTitle($data['ru']);
            $category->setTranslatableLocale('ru');
            $manager->persist($category);
            $manager->flush();

            // Reset to default locale
            $manager->refresh($category);
            $category->setTranslatableLocale('ro');

            // Add reference for other fixtures
            $this->addReference('category_' . $index, $category);
        }

        echo '✅ Created ' . \count(self::CATEGORIES) . " categories with translations (ro/en/ru)\n";
    }
}
