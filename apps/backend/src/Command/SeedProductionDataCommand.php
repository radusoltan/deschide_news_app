<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MenuItem;
use App\Entity\User;
use App\Enum\MenuItemType;
use App\Enum\MenuType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:production',
    description: 'Seed categories, users, and menu items for production staging'
)]
class SeedProductionDataCommand extends Command
{
    private Connection $conn;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Production Staging: Seed Data');

        $this->conn = $this->em->getConnection();

        try {
            $this->seedCategories($io);
            $this->seedUsers($io);
            $this->seedMenuItems($io);
            $this->printSummary($io);

            $io->success('Production staging data seeded successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function seedCategories(SymfonyStyle $io): void
    {
        $io->section('Seeding 11 Categories (RO/EN/RU)');

        // slug => [titleRo, titleEn, titleRu, onFrontPage, frontPagePosition, frontPageLayout, inMenu, inFooterMenu]
        $categories = [
            'politica'    => ['Politică',    'Politics',       'Политика',           true,  1,  'featured',      true,  true],
            'societate'   => ['Societate',   'Society',        'Общество',           true,  2,  'grid-4-cols',   true,  true],
            'externe'     => ['Externe',     'International',  'Международные',      true,  3,  'featured',      true,  true],
            'economie'    => ['Economie',    'Economy',        'Экономика',          true,  4,  'compact-list',  true,  true],
            'romania'     => ['România',     'Romania',        'Румыния',            false, 5,  null,            true,  false],
            'cultura'     => ['Cultură',     'Culture',        'Культура',           true,  6,  'grid-3-cols',   true,  true],
            'sport'       => ['Sport',       'Sport',          'Спорт',              true,  7,  'compact-list',  true,  true],
            'editoriale'  => ['Editoriale',  'Editorials',     'Редакционные',       true,  8,  'featured',      true,  false],
            'opinii'      => ['Opinii',      'Opinions',       'Мнения',             true,  9,  null,            true,  false],
            'advertorial' => ['Advertorial', 'Advertorial',    'Рекламная статья',   true,  10, 'featured',      false, false],
            'anti-fake'   => ['Anti-Fake',   'Anti-Fake',      'Антифейк',           true,  11, 'featured',      true,  false],
        ];

        // Slug translations: slug => [enSlug, ruSlug]
        $slugTranslations = [
            'politica'    => ['politics',      'politika'],
            'societate'   => ['society',       'obshchestvo'],
            'externe'     => ['international', 'mezhdunarodnye'],
            'economie'    => ['economy',       'ekonomika'],
            'romania'     => ['romania',       'rumyniya'],
            'cultura'     => ['culture',       'kultura'],
            'sport'       => ['sport',         'sport'],
            'editoriale'  => ['editorials',    'redakcionnye'],
            'opinii'      => ['opinions',      'mneniya'],
            'advertorial' => ['advertorial',   'reklamnaya-statya'],
            'anti-fake'   => ['anti-fake',     'antifejk'],
        ];

        $created = 0;

        foreach ($categories as $slug => [$titleRo, $titleEn, $titleRu, $onFrontPage, $fpPos, $fpLayout, $inMenu, $inFooterMenu]) {
            $existing = $this->conn->fetchOne('SELECT id FROM categories WHERE slug = ?', [$slug]);
            if ($existing) {
                $io->text("  [SKIP] '$slug' already exists");
                continue;
            }

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $this->conn->executeStatement(
                'INSERT INTO categories (title, slug, status, on_front_page, front_page_position, front_page_layout, in_menu, in_footer_menu, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$titleRo, $slug, 'active', $onFrontPage ? 'true' : 'false', $fpPos, $fpLayout, $inMenu ? 'true' : 'false', $inFooterMenu ? 'true' : 'false', $now, $now]
            );
            $catId = (string) $this->conn->lastInsertId();

            // EN translations (title + slug)
            $this->insertTranslation('App\\Entity\\Category', $catId, 'en', 'title', $titleEn);
            $this->insertTranslation('App\\Entity\\Category', $catId, 'en', 'slug', $slugTranslations[$slug][0]);

            // RU translations (title + slug)
            $this->insertTranslation('App\\Entity\\Category', $catId, 'ru', 'title', $titleRu);
            $this->insertTranslation('App\\Entity\\Category', $catId, 'ru', 'slug', $slugTranslations[$slug][1]);

            $io->text(\sprintf('  [NEW] %s (id=%s) — RO/EN/RU', $slug, $catId));
            ++$created;
        }

        $io->success("Categories: $created created");
    }

    private function seedUsers(SymfonyStyle $io): void
    {
        $io->section('Seeding Users');

        $users = [
            ['admin', 'admin@news-app.local', 'Admin', 'User', ['ROLE_ADMIN'], 'password'],
            ['editor', 'editor@news-app.local', 'Editor', 'User', ['ROLE_EDITOR'], 'password'],
        ];

        $created = 0;

        foreach ($users as [$username, $email, $firstName, $lastName, $roles, $plainPassword]) {
            $existing = $this->conn->fetchOne('SELECT id FROM "user" WHERE email = ?', [$email]);
            if ($existing) {
                $io->text("  [SKIP] $email already exists");
                continue;
            }

            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setRoles($roles);
            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

            $this->em->persist($user);
            $this->em->flush();

            $io->text(\sprintf('  [NEW] %s (%s) — %s', $username, $email, implode(', ', $roles)));
            ++$created;
        }

        $io->success("Users: $created created");
    }

    private function seedMenuItems(SymfonyStyle $io): void
    {
        $io->section('Seeding Main Menu Items');

        // Get all categories with inMenu=true, ordered by front_page_position
        $menuCategories = $this->conn->fetchAllAssociative(
            "SELECT id, slug, title FROM categories WHERE in_menu = true ORDER BY front_page_position ASC"
        );

        // Get EN/RU title translations for menu labels
        $catTranslations = [];
        foreach ($menuCategories as $cat) {
            $catId = (string) $cat['id'];
            $en = $this->conn->fetchOne(
                "SELECT content FROM ext_translations WHERE object_class = 'App\\Entity\\Category' AND foreign_key = ? AND locale = 'en' AND field = 'title'",
                [$catId]
            );
            $ru = $this->conn->fetchOne(
                "SELECT content FROM ext_translations WHERE object_class = 'App\\Entity\\Category' AND foreign_key = ? AND locale = 'ru' AND field = 'title'",
                [$catId]
            );
            $catTranslations[$catId] = ['en' => $en ?: null, 'ru' => $ru ?: null];
        }

        $this->em->clear();

        $catRepo = $this->em->getRepository(\App\Entity\Category::class);
        $position = 0;
        $created = 0;

        foreach ($menuCategories as $cat) {
            $category = $catRepo->find($cat['id']);
            if (!$category) {
                continue;
            }

            // Check if menu item already exists
            $existing = $this->em->getRepository(MenuItem::class)->findOneBy([
                'menu' => MenuType::MAIN,
                'type' => MenuItemType::CATEGORY,
                'category' => $category,
            ]);
            if ($existing) {
                $io->text("  [SKIP] Menu item for '{$cat['slug']}' already exists");
                $position++;
                continue;
            }

            $menuItem = new MenuItem();
            $menuItem->setMenu(MenuType::MAIN);
            $menuItem->setType(MenuItemType::CATEGORY);
            $menuItem->setLabel($cat['title']);
            $menuItem->setCategory($category);
            $menuItem->setPosition($position);
            $menuItem->setIsActive(true);

            $this->em->persist($menuItem);
            $this->em->flush();

            // Add EN/RU label translations via DBAL
            $menuItemId = (string) $menuItem->getId();
            $catId = (string) $cat['id'];

            if ($catTranslations[$catId]['en']) {
                $this->insertTranslation('App\\Entity\\MenuItem', $menuItemId, 'en', 'label', $catTranslations[$catId]['en']);
            }
            if ($catTranslations[$catId]['ru']) {
                $this->insertTranslation('App\\Entity\\MenuItem', $menuItemId, 'ru', 'label', $catTranslations[$catId]['ru']);
            }

            $io->text(\sprintf('  [NEW] %s → position %d', $cat['slug'], $position));
            $position++;
            $created++;
        }

        // Also seed footer menu items
        $footerCategories = $this->conn->fetchAllAssociative(
            "SELECT id, slug, title FROM categories WHERE in_footer_menu = true ORDER BY front_page_position ASC"
        );

        $footerCreated = 0;
        $footerPos = 0;

        $this->em->clear();

        foreach ($footerCategories as $cat) {
            $category = $catRepo->find($cat['id']);
            if (!$category) {
                continue;
            }

            $existing = $this->em->getRepository(MenuItem::class)->findOneBy([
                'menu' => MenuType::FOOTER,
                'type' => MenuItemType::CATEGORY,
                'category' => $category,
            ]);
            if ($existing) {
                $footerPos++;
                continue;
            }

            $menuItem = new MenuItem();
            $menuItem->setMenu(MenuType::FOOTER);
            $menuItem->setType(MenuItemType::CATEGORY);
            $menuItem->setLabel($cat['title']);
            $menuItem->setCategory($category);
            $menuItem->setPosition($footerPos);
            $menuItem->setIsActive(true);

            $this->em->persist($menuItem);
            $this->em->flush();

            $menuItemId = (string) $menuItem->getId();
            $catId = (string) $cat['id'];

            if (isset($catTranslations[$catId]['en']) && $catTranslations[$catId]['en']) {
                $this->insertTranslation('App\\Entity\\MenuItem', $menuItemId, 'en', 'label', $catTranslations[$catId]['en']);
            }
            if (isset($catTranslations[$catId]['ru']) && $catTranslations[$catId]['ru']) {
                $this->insertTranslation('App\\Entity\\MenuItem', $menuItemId, 'ru', 'label', $catTranslations[$catId]['ru']);
            }

            $footerPos++;
            $footerCreated++;
        }

        $io->success("Menu items: $created main + $footerCreated footer created");
    }

    private function insertTranslation(string $objectClass, string $foreignKey, string $locale, string $field, string $content): void
    {
        $exists = $this->conn->fetchOne(
            'SELECT id FROM ext_translations WHERE object_class = ? AND foreign_key = ? AND locale = ? AND field = ?',
            [$objectClass, $foreignKey, $locale, $field]
        );

        if ($exists) {
            return;
        }

        $this->conn->executeStatement(
            'INSERT INTO ext_translations (locale, object_class, field, foreign_key, content) VALUES (?, ?, ?, ?, ?)',
            [$locale, $objectClass, $field, $foreignKey, $content]
        );
    }

    private function printSummary(SymfonyStyle $io): void
    {
        $io->section('Summary');

        $categoryCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM categories');
        $userCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM "user"');
        $menuCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM menu_items');
        $translationCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM ext_translations');

        $io->table(
            ['Entity', 'Count'],
            [
                ['Categories', (string) $categoryCount],
                ['Users', (string) $userCount],
                ['Menu Items', (string) $menuCount],
                ['Translations (ext_translations)', (string) $translationCount],
            ]
        );
    }
}
