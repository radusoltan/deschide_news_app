<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\MenuItem;
use App\Enum\MenuItemType;
use App\Enum\MenuType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Gedmo\Translatable\Entity\Translation;

/**
 * Seeds the main menu structure matching the deschide.md layout:
 *
 *   Știri (dropdown)
 *     ├── Politică
 *     ├── Societate
 *     ├── Externe
 *     ├── Economie
 *     ├── Cultură
 *     └── Sport
 *   România
 *   Editoriale
 *   Opinii
 *   Advertorial
 *   Anti-Fake
 *
 * Total: 12 rows (1 dropdown parent + 6 children + 5 top-level).
 * Labels derived from Category translations (not hardcoded duplicates).
 */
class MenuItemFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    /** Dropdown children — order defines sort_order within the dropdown */
    private const DROPDOWN_SLUGS = ['politica', 'societate', 'externe', 'economie', 'cultura', 'sport'];

    /** Top-level items (not under Știri) — order defines sort_order */
    private const TOP_LEVEL_SLUGS = ['romania', 'editoriale', 'opinii', 'advertorial', 'anti-fake'];

    public static function getGroups(): array
    {
        return ['dev', 'test', 'menu'];
    }

    public function getDependencies(): array
    {
        return [CategoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $translationRepo = $manager->getRepository(Translation::class);

        // 1. Create "Știri" dropdown parent (no category, position=1)
        $dropdown = new MenuItem();
        $dropdown->setMenu(MenuType::MAIN);
        $dropdown->setType(MenuItemType::DROPDOWN);
        $dropdown->setTranslatableLocale('ro');
        $dropdown->setLabel('Știri');
        $dropdown->setPosition(1);
        $dropdown->setIsActive(true);

        $manager->persist($dropdown);
        $manager->flush();

        $translationRepo->translate($dropdown, 'label', 'en', 'News');
        $translationRepo->translate($dropdown, 'label', 'ru', 'Новости');
        $manager->flush();

        // 2. Dropdown children: 6 categories under Știri
        foreach (self::DROPDOWN_SLUGS as $index => $slug) {
            $category = $this->getReference("category-{$slug}", Category::class);
            $this->createCategoryItem(
                $manager,
                $translationRepo,
                $category,
                $index + 1,
                $dropdown,
            );
        }

        // 3. Top-level items: 5 categories
        foreach (self::TOP_LEVEL_SLUGS as $index => $slug) {
            $category = $this->getReference("category-{$slug}", Category::class);
            $this->createCategoryItem(
                $manager,
                $translationRepo,
                $category,
                $index + 2, // +2 because Știri occupies position 1
                null,
            );
        }
    }

    private function createCategoryItem(
        ObjectManager $manager,
        mixed $translationRepo,
        Category $category,
        int $position,
        ?MenuItem $parent,
    ): void {
        $item = new MenuItem();
        $item->setMenu(MenuType::MAIN);
        $item->setType(MenuItemType::CATEGORY);
        $item->setTranslatableLocale('ro');
        $item->setLabel($category->getTitle());
        $item->setCategory($category);
        $item->setPosition($position);
        $item->setIsActive(true);

        if ($parent !== null) {
            $item->setParent($parent);
        }

        $manager->persist($item);
        $manager->flush();

        // Derive EN/RU labels from category translations
        $catTranslations = $translationRepo->findTranslations($category);
        foreach (['en', 'ru'] as $locale) {
            $translatedTitle = $catTranslations[$locale]['title'] ?? null;
            if ($translatedTitle !== null) {
                $translationRepo->translate($item, 'label', $locale, $translatedTitle);
            }
        }
        $manager->flush();
    }
}
