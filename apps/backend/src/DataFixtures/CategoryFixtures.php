<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Enum\CategoryStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    private const CATEGORIES = [
        [
            'ro' => 'Politică',
            'en' => 'Politics',
            'ru' => 'Политика',
            'onFrontPage' => true,
        ],
        [
            'ro' => 'Economie',
            'en' => 'Economy',
            'ru' => 'Экономика',
            'onFrontPage' => true,
        ],
        [
            'ro' => 'Cultură',
            'en' => 'Culture',
            'ru' => 'Культура',
            'onFrontPage' => true,
        ],
        [
            'ro' => 'Sport',
            'en' => 'Sports',
            'ru' => 'Спорт',
            'onFrontPage' => true,
        ],
        [
            'ro' => 'Tehnologie',
            'en' => 'Technology',
            'ru' => 'Технология',
            'onFrontPage' => true,
        ],
        [
            'ro' => 'Sănătate',
            'en' => 'Health',
            'ru' => 'Здоровье',
            'onFrontPage' => false,
        ],
        [
            'ro' => 'Internațional',
            'en' => 'International',
            'ru' => 'Международные',
            'onFrontPage' => false,
        ],
        [
            'ro' => 'Editorial',
            'en' => 'Editorial',
            'ru' => 'Редакция',
            'onFrontPage' => false,
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::CATEGORIES as $index => $categoryData) {
            $category = new Category();

            // Set Romanian (default locale)
            $category->setTitle($categoryData['ro']);
            $category->setStatus(CategoryStatus::ACTIVE);
            $category->setOnFrontPage($categoryData['onFrontPage']);
            $category->setTranslatableLocale('ro');

            $manager->persist($category);
            $manager->flush();

            // Set English translation
            $category->setTitle($categoryData['en']);
            $category->setTranslatableLocale('en');
            $manager->persist($category);
            $manager->flush();

            // Set Russian translation
            $category->setTitle($categoryData['ru']);
            $category->setTranslatableLocale('ru');
            $manager->persist($category);
            $manager->flush();

            // Reset to default locale
            $manager->refresh($category);
            $category->setTranslatableLocale('ro');

            // Add reference for ArticleFixtures
            $this->addReference('category_' . $index, $category);
        }

        echo "✅ Created " . count(self::CATEGORIES) . " categories with translations (ro/en/ru)\n";
    }
}
