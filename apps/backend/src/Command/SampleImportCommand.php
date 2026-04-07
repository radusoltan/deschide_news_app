<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Image;
use App\Enum\ArticleStatus;
use App\Enum\AuthorStatus;
use App\Enum\CategoryStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:sample-import',
    description: '[DEPRECATED] Import sample data from Newscoop — use app:dev:reset instead',
)]
class SampleImportCommand extends Command
{
    /** Category mapping: [ro_id, en_id, ru_id, normalized_slug] */
    private const CATEGORY_MAPPING = [
        'social' => [2, 18, 28, 'social'],
        'international' => [4, 15, 25, 'international'],
        'politics' => [1, 17, 27, 'politics'],
        'culture' => [7, 16, 26, 'culture'],
        'sports' => [8, 14, 24, 'sports'],
        'investigations' => [6, 12, 22, 'investigations'],
    ];

    /** Language mapping: Newscoop language ID => locale */
    private const LANGUAGE_MAP = [
        1 => 'en',
        2 => 'ro',
        15 => 'ru',
    ];

    /** ID mapping for imported entities */
    private array $categoryIdMap = [];

    private array $authorIdMap = [];

    private array $articleIdMap = [];

    private array $imageIdMap = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $newscoopConnection,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('categories', null, InputOption::VALUE_REQUIRED, 'Number of categories to import', 6)
            ->addOption('articles-per-category', null, InputOption::VALUE_REQUIRED, 'Articles per category', 50)
            ->addOption('with-translations', null, InputOption::VALUE_NONE, 'Import article translations')
            ->addOption('with-images', null, InputOption::VALUE_NONE, 'Import images and generate thumbnails')
            ->addOption('with-related', null, InputOption::VALUE_NONE, 'Import related articles')
            ->addOption('thumbnail-format', null, InputOption::VALUE_REQUIRED, 'Thumbnail format (webp, jpeg)', 'webp')
            ->addOption('reset-db', null, InputOption::VALUE_NONE, 'Drop and recreate database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        trigger_deprecation('deschide/backend', '1.0', 'Command "app:sample-import" is deprecated. Use "app:dev:reset" instead.');

        $io->warning('DEPRECATED: Folosește app:dev:reset care include fixtures + import RSS + cache clear.');

        $startTime = microtime(true);

        $io->title('Sample Import from Newscoop');
        $io->writeln('Importing 6 categories with 300 articles (50 per category)');
        $io->newLine();

        $resetDb = $input->getOption('reset-db');
        $withTranslations = $input->getOption('with-translations');
        $withImages = $input->getOption('with-images');
        $withRelated = $input->getOption('with-related');
        $articlesPerCategory = (int) $input->getOption('articles-per-category');

        try {
            // Step 1: Drop and recreate database (if --reset-db)
            if ($resetDb) {
                $io->section('[1/6] Dropping and recreating database');
                $this->resetDatabase($io);
                $io->success('Database reset completed');
            } else {
                $io->writeln('[1/6] Skipping database reset (use --reset-db to enable)');
            }

            // Step 2: Import categories with translations
            $io->section('[2/6] Importing categories with ALL translations');
            $categoriesImported = $this->importCategories($io);
            $io->success(\sprintf('Imported %d categories with translations', $categoriesImported));

            // Step 3: Import articles (BEFORE authors)
            $io->section('[3/6] Importing articles');
            $articlesImported = $this->importArticles($io, $articlesPerCategory, $withTranslations);
            $io->success(\sprintf('Imported %d articles', $articlesImported));

            // Step 4: Import authors (AFTER articles, so we know which authors are needed)
            $io->section('[4/6] Importing authors for imported articles');
            $authorsImported = $this->importAuthors($io, $articlesPerCategory);
            $io->success(\sprintf('Imported %d authors', $authorsImported));

            // Step 5: Import images (if enabled)
            if ($withImages) {
                $io->section('[5/6] Importing images and generating thumbnails');
                $imagesImported = $this->importImages($io, $input->getOption('thumbnail-format'));
                $io->success(\sprintf('Imported %d images', $imagesImported));
            } else {
                $io->writeln('[5/6] Skipping images import (use --with-images to enable)');
                $imagesImported = 0;
            }

            // Step 6: Import related articles (if enabled)
            if ($withRelated) {
                $io->section('[6/6] Importing related articles');
                $relationsImported = $this->importRelatedArticles($io);
                $io->success(\sprintf('Imported %d relations', $relationsImported));
            } else {
                $io->writeln('[6/6] Skipping related articles (use --with-related to enable)');
                $relationsImported = 0;
            }

            // Summary
            $duration = round(microtime(true) - $startTime, 2);
            $io->title('Import Completed Successfully');
            $io->success(\sprintf('Total time: %.2f seconds (%.2f minutes)', $duration, $duration / 60));

            $io->table(['Entity', 'Count'], [
                ['Categories', $categoriesImported],
                ['Authors', $authorsImported],
                ['Articles', $articlesImported],
                ['Images', $withImages ? $imagesImported : 'skipped'],
                ['Related Articles', $withRelated ? $relationsImported : 'skipped'],
            ]);

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Import failed: ' . $e->getMessage());
            $io->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function resetDatabase(SymfonyStyle $io): void
    {
        $io->writeln('Dropping database...');
        exec('cd ' . \dirname(__DIR__, 2) . ' && php bin/console doctrine:database:drop --force --if-exists', $output, $returnCode);
        if ($returnCode !== 0) {
            throw new RuntimeException('Failed to drop database');
        }

        $io->writeln('Creating database...');
        exec('cd ' . \dirname(__DIR__, 2) . ' && php bin/console doctrine:database:create', $output, $returnCode);
        if ($returnCode !== 0) {
            throw new RuntimeException('Failed to create database');
        }

        $io->writeln('Creating schema...');
        exec('cd ' . \dirname(__DIR__, 2) . ' && php bin/console doctrine:schema:create', $output, $returnCode);
        if ($returnCode !== 0) {
            throw new RuntimeException('Failed to create schema');
        }

        $io->writeln('Loading fixtures (thumbnail profiles)...');
        exec('cd ' . \dirname(__DIR__, 2) . ' && php bin/console doctrine:fixtures:load --group=thumbnail_profiles --no-interaction --append', $output, $returnCode);
        if ($returnCode !== 0) {
            throw new RuntimeException('Failed to load fixtures');
        }
    }

    private function importCategories(SymfonyStyle $io): int
    {
        $count = 0;

        foreach (self::CATEGORY_MAPPING as $groupName => [$roId, $enId, $ruId, $slug]) {
            $io->writeln(\sprintf('Importing category: %s', $groupName));

            // Fetch sections from Newscoop
            $roSection = $this->fetchSection($roId);
            $enSection = $this->fetchSection($enId);
            $ruSection = $this->fetchSection($ruId);

            // Create category (default RO)
            $category = new Category();
            $category->setTitle($roSection['Name']);
            $category->setSlug($slug);
            $category->setStatus(CategoryStatus::ACTIVE);
            $category->setOnFrontPage(true);
            $category->setTranslatableLocale('ro'); // Set default locale explicitly

            $this->entityManager->persist($category);
            $this->entityManager->flush(); // Save RO version

            // Translation EN
            $category->setTranslatableLocale('en');
            $category->setTitle($enSection['Name']);
            $this->entityManager->persist($category);
            $this->entityManager->flush(); // Save EN translation

            // Translation RU
            $category->setTranslatableLocale('ru');
            $category->setTitle($ruSection['Name']);
            $this->entityManager->persist($category);
            $this->entityManager->flush(); // Save RU translation

            // Reset to default locale
            $category->setTranslatableLocale('ro');

            // Map all section IDs to this category
            $this->categoryIdMap[$roId] = $category->getId();
            $this->categoryIdMap[$enId] = $category->getId();
            $this->categoryIdMap[$ruId] = $category->getId();

            ++$count;
        }

        return $count;
    }

    private function fetchSection(int $sectionId): array
    {
        $sql = 'SELECT id, Name, IdLanguage FROM newscoop.Sections WHERE id = :id';

        return $this->newscoopConnection->fetchAssociative($sql, ['id' => $sectionId]);
    }

    private function importAuthors(SymfonyStyle $io, int $articlesPerCategory): int
    {
        if (empty($this->articleIdMap)) {
            $io->writeln('No articles imported yet. Import articles first.');

            return 0;
        }

        $count = 0;
        $newscoopArticleNumbers = array_keys($this->articleIdMap);

        $io->writeln(\sprintf('Finding authors for %d imported articles...', \count($newscoopArticleNumbers)));

        // Fetch unique authors for imported articles
        $sql = '
            SELECT DISTINCT
                au.id,
                au.first_name,
                au.last_name,
                au.email,
                au.biography,
                au.image
            FROM Authors au
            INNER JOIN ArticleAuthors aa ON aa.fk_author_id = au.id
            WHERE aa.fk_article_number IN (' . implode(',', $newscoopArticleNumbers) . ')
            ORDER BY au.last_name, au.first_name
        ';

        $authors = $this->newscoopConnection->fetchAllAssociative($sql);

        $io->writeln(\sprintf('Found %d unique authors to import', \count($authors)));
        $io->progressStart(\count($authors));

        foreach ($authors as $authorData) {
            try {
                // Email: if missing, generate fake unique
                $email = $authorData['email'] ??
                    strtolower(
                        $this->slugger->slug($authorData['first_name'] . ' ' . $authorData['last_name'])->toString()
                    ) . '@deschide.md';

                // Check if author already exists by email
                $existingAuthor = $this->entityManager->getRepository(Author::class)->findOneBy(['email' => $email]);

                if ($existingAuthor) {
                    // Author already exists, just map the ID
                    $this->authorIdMap[$authorData['id']] = $existingAuthor->getId();
                    $io->progressAdvance();
                    continue;
                }

                // Create new author
                $author = new Author();
                $author->setFirstName($authorData['first_name']);
                $author->setLastName($authorData['last_name']);
                $author->setEmail($email);

                // Bio (if exists)
                if ($authorData['biography']) {
                    $author->setBio($authorData['biography']);
                }

                $author->setSlug(
                    $this->slugger->slug($authorData['first_name'] . '-' . $authorData['last_name'])->lower()->toString()
                );
                $author->setStatus(AuthorStatus::ACTIVE);

                $this->entityManager->persist($author);
                $this->entityManager->flush();

                // Map author ID
                $this->authorIdMap[$authorData['id']] = $author->getId();

                ++$count;
                $io->progressAdvance();

            } catch (Exception $e) {
                $io->writeln("\nError importing author {$authorData['first_name']} {$authorData['last_name']}: " . $e->getMessage());
                $io->progressAdvance();
                continue;
            }
        }

        $io->progressFinish();

        // Now associate authors with articles
        $io->writeln('Associating authors with articles...');
        $this->associateAuthorsWithArticles($io, $newscoopArticleNumbers);

        return $count;
    }

    private function associateAuthorsWithArticles(SymfonyStyle $io, array $newscoopArticleNumbers): void
    {
        // Fetch article-author associations
        $sql = '
            SELECT
                aa.fk_article_number,
                aa.fk_author_id,
                aa.order
            FROM ArticleAuthors aa
            WHERE aa.fk_article_number IN (' . implode(',', $newscoopArticleNumbers) . ')
            ORDER BY aa.fk_article_number, aa.order
        ';

        $associations = $this->newscoopConnection->fetchAllAssociative($sql);

        $io->progressStart(\count($associations));

        foreach ($associations as $assoc) {
            try {
                $newscoopArticleNumber = $assoc['fk_article_number'];
                $newscoopAuthorId = $assoc['fk_author_id'];

                // Get news_app IDs
                if (!isset($this->articleIdMap[$newscoopArticleNumber])) {
                    continue;
                }
                if (!isset($this->authorIdMap[$newscoopAuthorId])) {
                    continue;
                }

                $articleId = $this->articleIdMap[$newscoopArticleNumber];
                $authorId = $this->authorIdMap[$newscoopAuthorId];

                // Get entities
                $article = $this->entityManager->getRepository(Article::class)->find($articleId);
                $author = $this->entityManager->getRepository(Author::class)->find($authorId);

                if ($article && $author) {
                    $article->addAuthor($author);
                    $this->entityManager->persist($article);
                }

                $io->progressAdvance();

            } catch (Exception $e) {
                $io->writeln("\nError associating author with article: " . $e->getMessage());
                continue;
            }
        }

        $io->progressFinish();
        $this->entityManager->flush();

        $io->writeln(\sprintf('Associated %d author-article relations', \count($associations)));
    }

    private function importArticles(SymfonyStyle $io, int $articlesPerCategory, bool $withTranslations): int
    {
        $count = 0;
        $sectionIds = array_map(fn ($mapping) => $mapping[0], self::CATEGORY_MAPPING); // RO section IDs

        $io->writeln(\sprintf('Fetching %d articles per category (total %d)...', $articlesPerCategory, $articlesPerCategory * \count($sectionIds)));

        // Fetch articles (50 per category)
        $sql = "
            WITH RankedArticles AS (
                SELECT
                    a.Number,
                    a.IdLanguage,
                    a.Name,
                    a.PublishDate,
                    a.NrSection,
                    ROW_NUMBER() OVER (PARTITION BY a.NrSection ORDER BY a.PublishDate DESC) as rn
                FROM Articles a
                WHERE a.Type = 'stiri'
                    AND a.Published = 'Y'
                    AND a.IdLanguage = 2
                    AND a.NrSection IN (" . implode(',', $sectionIds) . ')
            )
            SELECT
                Number,
                IdLanguage,
                Name,
                PublishDate,
                NrSection
            FROM RankedArticles
            WHERE rn <= :limit
            ORDER BY NrSection, PublishDate DESC
        ';

        $articles = $this->newscoopConnection->fetchAllAssociative($sql, ['limit' => $articlesPerCategory]);

        $io->writeln(\sprintf('Found %d articles to import', \count($articles)));
        $io->progressStart(\count($articles));

        foreach ($articles as $articleData) {
            try {
                // Fetch content from Xstiri
                $content = $this->fetchXstiriContent($articleData['Number'], $articleData['IdLanguage']);

                if (!$content) {
                    $io->writeln("\nSkipping article {$articleData['Number']} - no content found");
                    continue;
                }

                // Create article
                $article = new Article();
                $article->setTitle($content['FTitlu'] ?? $articleData['Name']);
                $article->setSlug($this->slugger->slug($content['FTitlu'] ?? $articleData['Name'])->lower()->toString());

                // Lead
                if ($content['Flead']) {
                    $article->setLead($content['Flead']);
                }

                // Content (process later for images)
                if ($content['FContinut']) {
                    $article->setContent($content['FContinut']);
                }

                $article->setPublishedAt(new DateTimeImmutable($articleData['PublishDate']));
                $article->setStatus(ArticleStatus::PUBLISHED);
                $article->setPublishedLocales(['ro']);
                $article->setViewCount(rand(100, 5000)); // Random for demo

                // Assign category
                $categoryId = $this->categoryIdMap[$articleData['NrSection']];
                $category = $this->entityManager->getRepository(Category::class)->find($categoryId);
                $article->setCategory($category);

                // Set default locale
                $article->setTranslatableLocale('ro');

                $this->entityManager->persist($article);
                $this->entityManager->flush();

                // Map article ID
                $this->articleIdMap[$articleData['Number']] = $article->getId();

                // Import translations (if enabled)
                if ($withTranslations) {
                    $this->importArticleTranslations($article, $articleData['Number']);
                }

                ++$count;
                $io->progressAdvance();

                // Flush every 10 articles to avoid memory issues
                if ($count % 10 === 0) {
                    $this->entityManager->clear();
                }

            } catch (Exception $e) {
                $io->writeln("\nError importing article {$articleData['Number']}: " . $e->getMessage());
                continue;
            }
        }

        $io->progressFinish();
        $this->entityManager->flush();

        return $count;
    }

    private function fetchXstiriContent(int $articleNumber, int $languageId): ?array
    {
        $sql = '
            SELECT
                NrArticle,
                IdLanguage,
                Fsocial_Titlu as FTitlu,
                Fsubtitlu,
                FContinut,
                Flead
            FROM Xstiri
            WHERE NrArticle = :number AND IdLanguage = :lang
        ';

        $content = $this->newscoopConnection->fetchAssociative($sql, [
            'number' => $articleNumber,
            'lang' => $languageId,
        ]);

        if (!$content) {
            return null;
        }

        // Decode BLOBs (handle both resource and string types)
        if (isset($content['FContinut']) && $content['FContinut']) {
            $content['FContinut'] = \is_resource($content['FContinut'])
                ? stream_get_contents($content['FContinut'])
                : $content['FContinut'];
        }
        if (isset($content['Flead']) && $content['Flead']) {
            $content['Flead'] = \is_resource($content['Flead'])
                ? stream_get_contents($content['Flead'])
                : $content['Flead'];
        }

        return $content;
    }

    private function importArticleTranslations(Article $article, int $newscoopNumber): void
    {
        // Check for translations (RU and EN)
        foreach ([15 => 'ru', 1 => 'en'] as $langId => $locale) {
            $content = $this->fetchXstiriContent($newscoopNumber, $langId);

            if (!$content) {
                continue;
            }

            // Set translation
            $article->setTranslatableLocale($locale);
            $article->setTitle($content['FTitlu']);
            $article->setSlug($this->slugger->slug($content['FTitlu'])->lower()->toString());

            if ($content['Flead']) {
                $article->setLead($content['Flead']);
            }
            if ($content['FContinut']) {
                $article->setContent($content['FContinut']);
            }

            $this->entityManager->persist($article);
            $this->entityManager->flush();
        }

        // Reset to default locale
        $article->setTranslatableLocale('ro');
    }

    private function importImages(SymfonyStyle $io, string $format): int
    {
        if (empty($this->articleIdMap)) {
            $io->writeln('No articles imported yet. Import articles first.');

            return 0;
        }

        $count = 0;
        $newscoopArticleNumbers = array_keys($this->articleIdMap);
        $sourceImagesPath = '/home/radu/ext-hdd/backups/alpha/newscoop/images';
        $targetImagesPath = \dirname(__DIR__, 2) . '/public/uploads/images';

        // Create target directory if not exists
        if (!is_dir($targetImagesPath)) {
            mkdir($targetImagesPath, 0o755, true);
        }

        $io->writeln(\sprintf('Finding images for %d imported articles...', \count($newscoopArticleNumbers)));

        // Fetch images for imported articles, grouped by article
        $sql = '
            SELECT
                img.Id,
                img.ImageFileName,
                img.Description,
                img.width,
                img.height,
                img.ContentType,
                ai.is_default,
                ai.NrArticle,
                ai.Number as image_order
            FROM Images img
            INNER JOIN ArticleImages ai ON ai.IdImage = img.Id
            WHERE ai.NrArticle IN (' . implode(',', $newscoopArticleNumbers) . ')
            ORDER BY ai.NrArticle, ai.Number ASC
        ';

        $images = $this->newscoopConnection->fetchAllAssociative($sql);

        // Group images by article
        $imagesByArticle = [];
        foreach ($images as $imageData) {
            $newscoopArticleId = $imageData['NrArticle'];
            if (!isset($imagesByArticle[$newscoopArticleId])) {
                $imagesByArticle[$newscoopArticleId] = [];
            }
            $imagesByArticle[$newscoopArticleId][] = $imageData;
        }

        $io->writeln(\sprintf('Found %d images for %d articles', \count($images), \count($imagesByArticle)));
        $io->progressStart(\count($images));

        foreach ($imagesByArticle as $newscoopArticleId => $articleImages) {
            if (!isset($this->articleIdMap[$newscoopArticleId])) {
                continue;
            }

            $articleId = $this->articleIdMap[$newscoopArticleId];
            $article = $this->entityManager->getRepository(Article::class)->find($articleId);

            if (!$article) {
                continue;
            }

            $isFirst = true;

            foreach ($articleImages as $imageData) {
                try {
                    $sourceFile = $sourceImagesPath . '/' . $imageData['ImageFileName'];

                    // Check if source file exists
                    if (!file_exists($sourceFile)) {
                        $io->writeln("\nWarning: Image file not found: {$imageData['ImageFileName']}");
                        $io->progressAdvance();
                        continue;
                    }

                    // Generate unique filename
                    $extension = pathinfo($imageData['ImageFileName'], PATHINFO_EXTENSION);
                    $newFilename = uniqid('img_') . '_' . time() . '.' . $extension;
                    $targetFile = $targetImagesPath . '/' . $newFilename;

                    // Copy file
                    if (!copy($sourceFile, $targetFile)) {
                        $io->writeln("\nError copying image: {$imageData['ImageFileName']}");
                        $io->progressAdvance();
                        continue;
                    }

                    // Create Image entity
                    $image = new Image();
                    $image->setFilename($newFilename);
                    $image->setOriginalFilename($imageData['ImageFileName']);
                    $image->setMimeType($imageData['ContentType'] ?? 'image/jpeg');
                    $image->setWidth($imageData['width']);
                    $image->setHeight($imageData['height']);
                    $image->setSize(filesize($targetFile));
                    $image->setAlt($imageData['Description'] ?? '');
                    $image->setCaption($imageData['Description'] ?? '');

                    $this->entityManager->persist($image);
                    $this->entityManager->flush();

                    // Map image ID
                    $this->imageIdMap[$imageData['Id']] = $image->getId();

                    // Create ArticleImage association
                    $articleImage = new ArticleImage();
                    $articleImage->setArticle($article);
                    $articleImage->setImage($image);
                    $articleImage->setPosition($imageData['image_order'] ?? 0);

                    // First image becomes featured
                    $articleImage->setIsFeatured($isFirst);

                    $this->entityManager->persist($articleImage);

                    $isFirst = false; // Only first image is featured
                    ++$count;
                    $io->progressAdvance();

                } catch (Exception $e) {
                    $io->writeln("\nError importing image {$imageData['ImageFileName']}: " . $e->getMessage());
                    $io->progressAdvance();
                    continue;
                }
            }

            // Flush after each article to save associations
            $this->entityManager->flush();
        }

        $io->progressFinish();

        $io->writeln(\sprintf('Imported %d images and associated with articles', $count));

        return $count;
    }

    private function importRelatedArticles(SymfonyStyle $io): int
    {
        if (empty($this->articleIdMap)) {
            $io->writeln('No articles imported yet. Import articles first.');

            return 0;
        }

        $count = 0;
        $newscoopArticleNumbers = array_keys($this->articleIdMap);

        $io->writeln(\sprintf('Finding related articles for %d imported articles...', \count($newscoopArticleNumbers)));

        // Fetch related articles through context_boxes and context_articles
        $sql = '
            SELECT
                cb.fk_article_no as main_article,
                ca.fk_article_no as related_article
            FROM context_boxes cb
            INNER JOIN context_articles ca ON ca.fk_context_id = cb.id
            WHERE cb.fk_article_no IN (' . implode(',', $newscoopArticleNumbers) . ')
            ORDER BY cb.fk_article_no, ca.order_number
        ';

        $relations = $this->newscoopConnection->fetchAllAssociative($sql);

        $io->writeln(\sprintf('Found %d related article relations', \count($relations)));
        $io->progressStart(\count($relations));

        foreach ($relations as $relation) {
            try {
                $mainArticleNumber = $relation['main_article'];
                $relatedArticleNumber = $relation['related_article'];

                // Check if both articles are imported
                if (!isset($this->articleIdMap[$mainArticleNumber])) {
                    $io->progressAdvance();
                    continue;
                }
                if (!isset($this->articleIdMap[$relatedArticleNumber])) {
                    $io->progressAdvance();
                    continue;
                }

                // Get article entities
                $mainArticleId = $this->articleIdMap[$mainArticleNumber];
                $relatedArticleId = $this->articleIdMap[$relatedArticleNumber];

                $mainArticle = $this->entityManager->getRepository(Article::class)->find($mainArticleId);
                $relatedArticle = $this->entityManager->getRepository(Article::class)->find($relatedArticleId);

                if ($mainArticle && $relatedArticle && $mainArticle !== $relatedArticle) {
                    $mainArticle->addRelatedArticle($relatedArticle);
                    $this->entityManager->persist($mainArticle);
                    ++$count;
                }

                $io->progressAdvance();

                // Flush every 50 relations
                if ($count % 50 === 0) {
                    $this->entityManager->flush();
                }

            } catch (Exception $e) {
                $io->writeln("\nError importing related article relation: " . $e->getMessage());
                continue;
            }
        }

        $io->progressFinish();
        $this->entityManager->flush();

        $io->writeln(\sprintf('Imported %d related article relations (only between imported articles)', $count));

        return $count;
    }
}
