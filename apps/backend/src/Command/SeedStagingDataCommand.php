<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Enum\AuthorStatus;
use App\Enum\CategoryStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:staging-data',
    description: 'Seeds categories, authors, and tags with translations for staging environment'
)]
class SeedStagingDataCommand extends Command
{
    private Connection $conn;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('categories', null, InputOption::VALUE_NONE, 'Seed categories only')
            ->addOption('authors', null, InputOption::VALUE_NONE, 'Seed authors only')
            ->addOption('tags', null, InputOption::VALUE_NONE, 'Seed tags only')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Seed all staging data (default if no option specified)')
            ->addOption('cleanup-bad-slugs', null, InputOption::VALUE_NONE, 'Remove categories/tags with bad slugs before seeding')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('STG-07: Seeding Staging Data (Categories, Authors, Tags)');

        $this->conn = $this->entityManager->getConnection();

        $seedCategories = $input->getOption('categories');
        $seedAuthors = $input->getOption('authors');
        $seedTags = $input->getOption('tags');
        $seedAll = $input->getOption('all');

        // Default: seed all if no specific option given
        if (!$seedCategories && !$seedAuthors && !$seedTags && !$seedAll) {
            $seedAll = true;
        }

        if ($input->getOption('cleanup-bad-slugs')) {
            $this->cleanupBadSlugs($io);
        }

        if ($seedAll || $seedCategories) {
            $this->seedCategories($io);
        }

        if ($seedAll || $seedAuthors) {
            $this->seedAuthors($io);
        }

        if ($seedAll || $seedTags) {
            $this->seedTags($io);
        }

        // Print summary
        $this->printSummary($io);

        $io->success('Staging data seeding completed!');

        return Command::SUCCESS;
    }

    /**
     * Remove categories and tags with Cyrillic or otherwise corrupted slugs
     * that were created by Gedmo Sluggable when translations were applied via entity lifecycle.
     */
    private function cleanupBadSlugs(SymfonyStyle $io): void
    {
        $io->section('Cleaning up bad slugs');

        // Known good slugs that should be kept
        $goodSlugs = [
            'politica', 'economie', 'societate', 'sport', 'cultura', 'externe',
            'stiinta', 'tehnologie', 'sanatate', 'educatie', 'justitie', 'mediu',
            'opinii', 'investigatii', 'interviu', 'reportaj', 'diaspora', 'lifestyle',
            'fotbal', 'baschet', 'politica-interna', 'politica-externa',
        ];

        // Find duplicate/bad categories: any category whose slug is NOT in the good list
        // and where a good-slug version already exists
        $allCategories = $this->conn->fetchAllAssociative(
            'SELECT id, slug, title FROM categories ORDER BY id'
        );

        $goodSlugIds = [];
        $badCategories = [];
        foreach ($allCategories as $row) {
            if (\in_array($row['slug'], $goodSlugs, true)) {
                $goodSlugIds[$row['slug']] = $row['id'];
            }
        }

        // Categories with bad slugs: not in the good list
        foreach ($allCategories as $row) {
            if (!\in_array($row['slug'], $goodSlugs, true)) {
                $badCategories[] = $row;
            }
        }

        // Map old bad slugs to good slugs for article reassignment
        $slugMapping = [
            'politika' => 'politica',
            'ekonomika' => 'economie',
            'kul-tura' => 'cultura',
            'tehnologia' => 'tehnologie',
            'zdorov-e' => 'sanatate',
            'mezdunarodnye' => 'externe',
            'obsestvo' => 'societate',
            'nauka' => 'stiinta',
            'tehnologii' => 'tehnologie',
            'obrazovanie' => 'educatie',
            'pravosudie' => 'justitie',
            'ekologia' => 'mediu',
            'mnenia' => 'opinii',
            'rassledovania' => 'investigatii',
            'interv-u' => 'interviu',
            'reportaz' => 'reportaj',
            'lajfstajl' => 'lifestyle',
            'futbol' => 'fotbal',
            'basketbol' => 'baschet',
        ];

        $deletedCats = 0;
        foreach ($badCategories as $row) {
            $catId = $row['id'];
            $badSlug = $row['slug'];

            // Reassign articles to the good category if mapping exists
            if (isset($slugMapping[$badSlug]) && isset($goodSlugIds[$slugMapping[$badSlug]])) {
                $goodId = $goodSlugIds[$slugMapping[$badSlug]];
                $reassigned = $this->conn->executeStatement(
                    'UPDATE articles SET category_id = ? WHERE category_id = ?',
                    [$goodId, $catId]
                );
                if ($reassigned > 0) {
                    $io->text("  [MOVE] $reassigned articles from category '$badSlug' -> '{$slugMapping[$badSlug]}'");
                }
            } else {
                // No mapping; just nullify
                $this->conn->executeStatement(
                    'UPDATE articles SET category_id = NULL WHERE category_id = ?',
                    [$catId]
                );
            }

            // Remove ext_translations for this category
            $this->conn->executeStatement(
                "DELETE FROM ext_translations WHERE object_class = 'App\\Entity\\Category' AND foreign_key = ?",
                [(string) $catId]
            );
            // Reassign live_texts category references
            if (isset($slugMapping[$badSlug]) && isset($goodSlugIds[$slugMapping[$badSlug]])) {
                $goodId = $goodSlugIds[$slugMapping[$badSlug]];
                $this->conn->executeStatement(
                    'UPDATE live_texts SET category_id = ? WHERE category_id = ?',
                    [$goodId, $catId]
                );
            } else {
                $this->conn->executeStatement(
                    'UPDATE live_texts SET category_id = NULL WHERE category_id = ?',
                    [$catId]
                );
            }
            // Nullify page_views references
            $this->conn->executeStatement(
                'UPDATE page_views SET category_id = NULL WHERE category_id = ?',
                [$catId]
            );
            // Remove children references
            $this->conn->executeStatement(
                'UPDATE categories SET parent_id = NULL WHERE parent_id = ?',
                [$catId]
            );
            // Remove the category
            $this->conn->executeStatement(
                'DELETE FROM categories WHERE id = ?',
                [$catId]
            );
            $io->text("  [DEL] Category id={$catId} slug='$badSlug' removed");
            ++$deletedCats;
        }

        // Find tags with non-ASCII slugs
        $badTags = $this->conn->fetchAllAssociative(
            "SELECT id, slug, name FROM tags WHERE slug ~ '[^a-z0-9\\-]'"
        );

        $deletedTags = 0;
        foreach ($badTags as $row) {
            $tagId = $row['id'];
            // Remove from article_tag join table
            $this->conn->executeStatement(
                'DELETE FROM article_tag WHERE tag_id = ?',
                [$tagId]
            );
            // Remove ext_translations
            $this->conn->executeStatement(
                "DELETE FROM ext_translations WHERE object_class = 'App\\Entity\\Tag' AND foreign_key = ?",
                [(string) $tagId]
            );
            // Remove the tag
            $this->conn->executeStatement(
                'DELETE FROM tags WHERE id = ?',
                [$tagId]
            );
            $io->text("  [DEL] Tag id={$tagId} slug='{$row['slug']}' removed");
            ++$deletedTags;
        }

        $io->success("Cleanup: $deletedCats categories and $deletedTags tags with bad slugs removed");

        // Clear EntityManager to reload fresh state
        $this->entityManager->clear();
    }

    /**
     * Seed categories using DBAL to avoid Gedmo slug corruption on translation persist.
     */
    private function seedCategories(SymfonyStyle $io): void
    {
        $io->section('Seeding Categories');

        // Category definitions: slug => [ro_title, en_title, ru_title, onFrontPage, inMenu]
        $categories = [
            'politica' => ['Politică', 'Politics', 'Политика', true, true],
            'economie' => ['Economie', 'Economy', 'Экономика', true, true],
            'societate' => ['Societate', 'Society', 'Общество', true, true],
            'sport' => ['Sport', 'Sports', 'Спорт', true, true],
            'cultura' => ['Cultură', 'Culture', 'Культура', true, true],
            'externe' => ['Externe', 'International', 'Международные', true, true],
            'stiinta' => ['Știință', 'Science', 'Наука', true, true],
            'tehnologie' => ['Tehnologie', 'Technology', 'Технологии', true, true],
            'sanatate' => ['Sănătate', 'Health', 'Здоровье', true, false],
            'educatie' => ['Educație', 'Education', 'Образование', true, false],
            'justitie' => ['Justiție', 'Justice', 'Правосудие', true, false],
            'mediu' => ['Mediu', 'Environment', 'Экология', false, false],
            'opinii' => ['Opinii', 'Opinions', 'Мнения', true, true],
            'investigatii' => ['Investigații', 'Investigations', 'Расследования', true, true],
            'interviu' => ['Interviu', 'Interview', 'Интервью', false, false],
            'reportaj' => ['Reportaj', 'Reportage', 'Репортаж', false, false],
            'diaspora' => ['Diaspora', 'Diaspora', 'Диаспора', true, false],
            'lifestyle' => ['Lifestyle', 'Lifestyle', 'Лайфстайл', false, false],
        ];

        // Child categories: child_slug => [parent_slug, ro_title, en_title, ru_title]
        $childCategories = [
            'fotbal' => ['sport', 'Fotbal', 'Football', 'Футбол'],
            'baschet' => ['sport', 'Baschet', 'Basketball', 'Баскетбол'],
            'politica-interna' => ['politica', 'Politică internă', 'Domestic Politics', 'Внутренняя политика'],
            'politica-externa' => ['politica', 'Politică externă', 'Foreign Policy', 'Внешняя политика'],
        ];

        $created = 0;
        $skipped = 0;

        // Seed main categories
        foreach ($categories as $slug => [$titleRo, $titleEn, $titleRu, $onFrontPage, $inMenu]) {
            $existing = $this->conn->fetchOne(
                'SELECT id FROM categories WHERE slug = ?',
                [$slug]
            );
            if ($existing) {
                $io->text("  [SKIP] Category '$slug' already exists (id=$existing)");
                ++$skipped;

                // Ensure translations exist for skipped categories
                $this->ensureCategoryTranslation($existing, 'en', $titleEn);
                $this->ensureCategoryTranslation($existing, 'ru', $titleRu);

                continue;
            }

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $this->conn->executeStatement(
                'INSERT INTO categories (title, slug, status, on_front_page, in_menu, in_footer_menu, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$titleRo, $slug, 'active', $onFrontPage ? 'true' : 'false', $inMenu ? 'true' : 'false', 'false', $now, $now]
            );
            $catId = $this->conn->lastInsertId();

            // Insert EN/RU translations directly into ext_translations
            $this->insertTranslation('App\\Entity\\Category', (string) $catId, 'en', 'title', $titleEn);
            $this->insertTranslation('App\\Entity\\Category', (string) $catId, 'ru', 'title', $titleRu);

            $io->text("  [NEW] Category '$slug' created (id=$catId) with EN/RU translations");
            ++$created;
        }

        // Seed child categories
        foreach ($childCategories as $slug => [$parentSlug, $titleRo, $titleEn, $titleRu]) {
            $existing = $this->conn->fetchOne(
                'SELECT id FROM categories WHERE slug = ?',
                [$slug]
            );
            if ($existing) {
                $io->text("  [SKIP] Child category '$slug' already exists");
                ++$skipped;

                continue;
            }

            $parentId = $this->conn->fetchOne(
                'SELECT id FROM categories WHERE slug = ?',
                [$parentSlug]
            );
            if (!$parentId) {
                $io->warning("  [WARN] Parent '$parentSlug' not found for child '$slug', skipping");

                continue;
            }

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $this->conn->executeStatement(
                'INSERT INTO categories (title, slug, status, on_front_page, in_menu, in_footer_menu, parent_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$titleRo, $slug, 'active', 'false', 'false', 'false', $parentId, $now, $now]
            );
            $catId = $this->conn->lastInsertId();

            $this->insertTranslation('App\\Entity\\Category', (string) $catId, 'en', 'title', $titleEn);
            $this->insertTranslation('App\\Entity\\Category', (string) $catId, 'ru', 'title', $titleRu);

            $io->text("  [NEW] Child category '$slug' (parent: '$parentSlug', id=$catId) created with translations");
            ++$created;
        }

        $io->success("Categories: $created created, $skipped skipped");
    }

    private function seedAuthors(SymfonyStyle $io): void
    {
        $io->section('Seeding Authors');

        // Author definitions: [firstName, lastName, email, bioRo, bioEn, bioRu]
        $authors = [
            ['Ion', 'Munteanu', 'ion.munteanu@deschide.md', 'Jurnalist de investigație cu peste 15 ani experiență.', 'Investigative journalist with over 15 years of experience.', 'Журналист-расследователь с более чем 15-летним опытом.'],
            ['Maria', 'Popescu', 'maria.popescu@deschide.md', 'Reporter economic, specialist în piețe financiare.', 'Economic reporter, specialist in financial markets.', 'Экономический репортер, специалист по финансовым рынкам.'],
            ['Andrei', 'Cojocaru', 'andrei.cojocaru@deschide.md', 'Corespondent politic în Parlamentul Republicii Moldova.', 'Political correspondent in the Parliament of Moldova.', 'Политический корреспондент в Парламенте Республики Молдова.'],
            ['Elena', 'Rotaru', 'elena.rotaru@deschide.md', 'Editor de știri internaționale și analist de politică externă.', 'International news editor and foreign policy analyst.', 'Редактор международных новостей и аналитик внешней политики.'],
            ['Vasile', 'Ciobanu', 'vasile.ciobanu@deschide.md', 'Reporter de sport, pasionat de fotbal și atletism.', 'Sports reporter, passionate about football and athletics.', 'Спортивный репортер, увлечённый футболом и лёгкой атлетикой.'],
            ['Natalia', 'Ungureanu', 'natalia.ungureanu@deschide.md', 'Jurnalist cultural, critic literar și de artă.', 'Cultural journalist, literary and art critic.', 'Культурный журналист, литературный и художественный критик.'],
            ['Dumitru', 'Moraru', 'dumitru.moraru@deschide.md', 'Reporter de teren, specialist în reportaje sociale.', 'Field reporter, specialist in social reporting.', 'Полевой репортер, специалист по социальным репортажам.'],
            ['Ana', 'Bălan', 'ana.balan@deschide.md', 'Jurnalist de mediu și sănătate publică.', 'Environmental and public health journalist.', 'Журналист по вопросам экологии и общественного здоровья.'],
            ['Gheorghe', 'Negru', 'gheorghe.negru@deschide.md', 'Editor-șef adjunct, specializat în verificarea faptelor.', 'Deputy editor-in-chief, specialized in fact-checking.', 'Заместитель главного редактора, специализируется на проверке фактов.'],
            ['Cristina', 'Damaschin', 'cristina.damaschin@deschide.md', 'Reporter de justiție și afaceri juridice.', 'Justice and legal affairs reporter.', 'Репортер по вопросам юстиции и правовых дел.'],
            ['Mihai', 'Colesnic', 'mihai.colesnic@deschide.md', 'Corespondent în diaspora, acoperă comunitățile moldovenești.', 'Diaspora correspondent, covering Moldovan communities.', 'Корреспондент диаспоры, освещающий молдавские общины.'],
            ['Svetlana', 'Rusu', 'svetlana.rusu@deschide.md', 'Jurnalist bilingv (ro/ru), specialist în educație.', 'Bilingual journalist (ro/ru), education specialist.', 'Двуязычный журналист (ро/ру), специалист по образованию.'],
            ['Vitalie', 'Sprînceană', 'vitalie.sprinceana@deschide.md', 'Analist politic și comentator de televiziune.', 'Political analyst and TV commentator.', 'Политический аналитик и телевизионный комментатор.'],
            ['Dorina', 'Chicu', 'dorina.chicu@deschide.md', 'Reporter de știință și tehnologie.', 'Science and technology reporter.', 'Репортер по вопросам науки и технологий.'],
            ['Alexandru', 'Solcan', 'alexandru.solcan@deschide.md', 'Fotojurnalist și editor multimedia.', 'Photojournalist and multimedia editor.', 'Фотожурналист и мультимедийный редактор.'],
            ['Liliana', 'Barbaroșie', 'liliana.barbarosie@deschide.md', 'Editor de opinii și interviuri.', 'Opinion and interview editor.', 'Редактор мнений и интервью.'],
            ['Radu', 'Marian', 'radu.marian@deschide.md', 'Reporter economic, analist de piață.', 'Economic reporter, market analyst.', 'Экономический репортер, рыночный аналитик.'],
            ['Irina', 'Corobceanu', 'irina.corobceanu@deschide.md', 'Jurnalist de investigație în domeniul sănătății.', 'Investigative journalist in the health sector.', 'Журналист-расследователь в сфере здравоохранения.'],
            ['Tudor', 'Iașcenco', 'tudor.iascenco@deschide.md', 'Corespondent militar și de securitate.', 'Military and security correspondent.', 'Военный корреспондент и корреспондент по безопасности.'],
            ['Valentina', 'Ursu', 'valentina.ursu@deschide.md', 'Prezentatoare de știri și reporter senior.', 'News presenter and senior reporter.', 'Ведущая новостей и старший репортер.'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($authors as [$firstName, $lastName, $email, $bioRo, $bioEn, $bioRu]) {
            // Check if author exists by email (unique field)
            $existing = $this->conn->fetchOne(
                'SELECT id FROM authors WHERE email = ?',
                [$email]
            );
            if ($existing) {
                $io->text("  [SKIP] Author '$firstName $lastName' ($email) already exists");
                ++$skipped;

                continue;
            }

            // Generate slug from names
            $slug = $this->generateSlug($firstName . ' ' . $lastName);

            // Check slug uniqueness; append suffix if needed
            $slugBase = $slug;
            $counter = 1;
            while ($this->conn->fetchOne('SELECT id FROM authors WHERE slug = ?', [$slug])) {
                $slug = $slugBase . '-' . $counter;
                ++$counter;
            }

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $this->conn->executeStatement(
                'INSERT INTO authors (first_name, last_name, email, slug, bio, status, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$firstName, $lastName, $email, $slug, $bioRo, 'active', 'true', $now, $now]
            );
            $authorId = $this->conn->lastInsertId();

            // Insert bio translations
            $this->insertTranslation('App\\Entity\\Author', (string) $authorId, 'en', 'bio', $bioEn);
            $this->insertTranslation('App\\Entity\\Author', (string) $authorId, 'ru', 'bio', $bioRu);

            $io->text("  [NEW] Author '$firstName $lastName' created (id=$authorId, slug=$slug) with EN/RU bios");
            ++$created;
        }

        $io->success("Authors: $created created, $skipped skipped");
    }

    private function seedTags(SymfonyStyle $io): void
    {
        $io->section('Seeding Tags');

        // Tag definitions: slug => [nameRo, nameEn, nameRu]
        $tags = [
            'alegeri' => ['Alegeri', 'Elections', 'Выборы'],
            'covid' => ['COVID', 'COVID', 'COVID'],
            'ue' => ['UE', 'EU', 'ЕС'],
            'nato' => ['NATO', 'NATO', 'НАТО'],
            'chisinau' => ['Chișinău', 'Chisinau', 'Кишинёв'],
            'parlament' => ['Parlament', 'Parliament', 'Парламент'],
            'guvern' => ['Guvern', 'Government', 'Правительство'],
            'presedintie' => ['Președinție', 'Presidency', 'Президентство'],
            'coruptie' => ['Corupție', 'Corruption', 'Коррупция'],
            'justitie' => ['Justiție', 'Justice', 'Правосудие'],
            'educatie' => ['Educație', 'Education', 'Образование'],
            'sanatate' => ['Sănătate', 'Health', 'Здоровье'],
            'moldova' => ['Moldova', 'Moldova', 'Молдова'],
            'romania' => ['România', 'Romania', 'Румыния'],
            'ucraina' => ['Ucraina', 'Ukraine', 'Украина'],
            'rusia' => ['Rusia', 'Russia', 'Россия'],
            'economie' => ['Economie', 'Economy', 'Экономика'],
            'inflatie' => ['Inflație', 'Inflation', 'Инфляция'],
            'energie' => ['Energie', 'Energy', 'Энергетика'],
            'gaze' => ['Gaze', 'Gas', 'Газ'],
            'agricultura' => ['Agricultură', 'Agriculture', 'Сельское хозяйство'],
            'transport' => ['Transport', 'Transport', 'Транспорт'],
            'infrastructura' => ['Infrastructură', 'Infrastructure', 'Инфраструктура'],
            'mediu' => ['Mediu', 'Environment', 'Экология'],
            'cultura' => ['Cultură', 'Culture', 'Культура'],
            'sport' => ['Sport', 'Sport', 'Спорт'],
            'fotbal' => ['Fotbal', 'Football', 'Футбол'],
            'tehnologie' => ['Tehnologie', 'Technology', 'Технологии'],
            'diaspora' => ['Diaspora', 'Diaspora', 'Диаспора'],
            'securitate' => ['Securitate', 'Security', 'Безопасность'],
            'razboi' => ['Război', 'War', 'Война'],
            'integrare-europeana' => ['Integrare europeană', 'European Integration', 'Европейская интеграция'],
            'drepturile-omului' => ['Drepturile omului', 'Human Rights', 'Права человека'],
            'pensii' => ['Pensii', 'Pensions', 'Пенсии'],
            'salarii' => ['Salarii', 'Salaries', 'Зарплаты'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($tags as $slug => [$nameRo, $nameEn, $nameRu]) {
            // Check if tag already exists by slug
            $existing = $this->conn->fetchOne(
                'SELECT id FROM tags WHERE slug = ?',
                [$slug]
            );
            if ($existing) {
                $io->text("  [SKIP] Tag '$slug' already exists");
                ++$skipped;

                // Ensure translations exist
                $this->ensureTagTranslation($existing, 'en', $nameEn, $slug);
                $this->ensureTagTranslation($existing, 'ru', $nameRu, $slug);

                continue;
            }

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $this->conn->executeStatement(
                'INSERT INTO tags (name, slug, usage_count, created_at, updated_at)
                 VALUES (?, ?, 0, ?, ?)',
                [$nameRo, $slug, $now, $now]
            );
            $tagId = $this->conn->lastInsertId();

            // Insert translations for name and slug
            $this->insertTranslation('App\\Entity\\Tag', (string) $tagId, 'en', 'name', $nameEn);
            $this->insertTranslation('App\\Entity\\Tag', (string) $tagId, 'en', 'slug', $slug);
            $this->insertTranslation('App\\Entity\\Tag', (string) $tagId, 'ru', 'name', $nameRu);
            $this->insertTranslation('App\\Entity\\Tag', (string) $tagId, 'ru', 'slug', $slug);

            ++$created;
        }

        $io->success("Tags: $created created, $skipped skipped");
    }

    /**
     * Insert a translation row into ext_translations.
     * Uses INSERT ... ON CONFLICT DO NOTHING to avoid duplicates.
     */
    private function insertTranslation(string $objectClass, string $foreignKey, string $locale, string $field, string $content): void
    {
        // Check if translation already exists
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

    /**
     * Ensure a category translation exists, adding it if missing.
     */
    private function ensureCategoryTranslation(int|string $categoryId, string $locale, string $title): void
    {
        $this->insertTranslation('App\\Entity\\Category', (string) $categoryId, $locale, 'title', $title);
    }

    /**
     * Ensure tag translations exist (both name and slug).
     */
    private function ensureTagTranslation(int|string $tagId, string $locale, string $name, string $slug): void
    {
        $this->insertTranslation('App\\Entity\\Tag', (string) $tagId, $locale, 'name', $name);
        $this->insertTranslation('App\\Entity\\Tag', (string) $tagId, $locale, 'slug', $slug);
    }

    /**
     * Generate a URL-safe slug from a string (ASCII transliteration).
     */
    private function generateSlug(string $text): string
    {
        // Romanian character map
        $map = [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's',
            'ț' => 't', 'ţ' => 't',
            'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's',
            'Ț' => 't', 'Ţ' => 't',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        ];

        $slug = strtr($text, $map);
        $slug = mb_strtolower($slug);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }

    private function printSummary(SymfonyStyle $io): void
    {
        $io->section('Summary');

        $categoryCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM categories');
        $authorCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM authors');
        $tagCount = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM tags');

        $catTranslations = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM ext_translations WHERE object_class LIKE '%Category%'"
        );
        $authorTranslations = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM ext_translations WHERE object_class LIKE '%Author%'"
        );
        $tagTranslations = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM ext_translations WHERE object_class LIKE '%Tag%'"
        );
        $totalTranslations = (int) $this->conn->fetchOne('SELECT COUNT(*) FROM ext_translations');

        $io->table(
            ['Entity', 'Count', 'Translations'],
            [
                ['Categories', (string) $categoryCount, (string) $catTranslations],
                ['Authors', (string) $authorCount, (string) $authorTranslations],
                ['Tags', (string) $tagCount, (string) $tagTranslations],
                ['---', '---', '---'],
                ['Total Translations', '', (string) $totalTranslations],
            ]
        );
    }
}
