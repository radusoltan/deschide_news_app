<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\MenuItem;
use App\Enum\MenuItemType;
use App\Enum\MenuType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Gedmo\Translatable\Entity\Translation;

/**
 * Seeds the main menu structure matching the legacy deschide.md layout:
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
 */
class MenuItemFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [CategoryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $categoryRepo = $manager->getRepository(Category::class);
        $translationRepo = $manager->getRepository(Translation::class);

        // 1. Create "Știri" dropdown
        $dropdown = new MenuItem();
        $dropdown->setMenu(MenuType::MAIN);
        $dropdown->setType(MenuItemType::DROPDOWN);
        $dropdown->setLabel('Știri');
        $dropdown->setPosition(1);
        $dropdown->setIsActive(true);
        $dropdown->setTranslatableLocale('ro');

        $manager->persist($dropdown);
        $manager->flush();

        $translationRepo->translate($dropdown, 'label', 'en', 'News');
        $translationRepo->translate($dropdown, 'label', 'ru', 'Новости');
        $manager->flush();

        // 2. Dropdown children: Politică, Societate, Externe, Economie
        $dropdownSlugs = ['politica', 'societate', 'externe', 'economie'];
        $childPos = 1;
        foreach ($dropdownSlugs as $slug) {
            $category = $categoryRepo->findOneBy(['slug' => $slug]);
            if (!$category) {
                continue;
            }

            $item = $this->createCategoryItem($manager, $translationRepo, $category, MenuType::MAIN, $childPos, $dropdown);
            $childPos++;
        }

        // 3. Top-level items
        $topLevelSlugs = ['romania', 'cultura', 'sport', 'editoriale', 'opinii', 'advertorial', 'anti-fake'];
        $topPos = 2; // position 1 = Știri dropdown
        foreach ($topLevelSlugs as $slug) {
            $category = $categoryRepo->findOneBy(['slug' => $slug]);
            if (!$category) {
                continue;
            }

            $this->createCategoryItem($manager, $translationRepo, $category, MenuType::MAIN, $topPos);
            $topPos++;
        }

        $manager->flush();

        echo "✅ Created main menu: 1 dropdown + 4 children + 7 top-level items with translations\n";
    }

    private function createCategoryItem(
        ObjectManager $manager,
        mixed $translationRepo,
        Category $category,
        MenuType $menu,
        int $position,
        ?MenuItem $parent = null,
    ): MenuItem {
        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setType(MenuItemType::CATEGORY);
        $item->setLabel($category->getTitle());
        $item->setCategory($category);
        $item->setPosition($position);
        $item->setIsActive(true);
        $item->setTranslatableLocale('ro');

        if ($parent) {
            $item->setParent($parent);
        }

        $manager->persist($item);
        $manager->flush();

        // Add EN/RU translations from category
        $catTranslations = $translationRepo->findTranslations($category);
        foreach (['en', 'ru'] as $locale) {
            $translatedTitle = $catTranslations[$locale]['title'] ?? null;
            if ($translatedTitle) {
                $translationRepo->translate($item, 'label', $locale, $translatedTitle);
            }
        }
        $manager->flush();

        return $item;
    }
}
