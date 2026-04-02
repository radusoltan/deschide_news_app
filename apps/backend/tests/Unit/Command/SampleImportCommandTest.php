<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SampleImportCommand;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SampleImportCommandTest extends TestCase
{
    // ── Metadata Tests ──────────────────────────────────────────────────────

    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:sample-import', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('sample', strtolower($command->getDescription()));
    }

    public function testCommandHasAllOptions(): void
    {
        $command = $this->buildCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('categories'));
        $this->assertTrue($definition->hasOption('articles-per-category'));
        $this->assertTrue($definition->hasOption('with-translations'));
        $this->assertTrue($definition->hasOption('with-images'));
        $this->assertTrue($definition->hasOption('with-related'));
        $this->assertTrue($definition->hasOption('thumbnail-format'));
        $this->assertTrue($definition->hasOption('reset-db'));
    }

    public function testCategoriesOptionDefaultsTo6(): void
    {
        $command = $this->buildCommand();
        $this->assertSame(6, (int) $command->getDefinition()->getOption('categories')->getDefault());
    }

    public function testArticlesPerCategoryOptionDefaultsTo50(): void
    {
        $command = $this->buildCommand();
        $this->assertSame(50, (int) $command->getDefinition()->getOption('articles-per-category')->getDefault());
    }

    public function testThumbnailFormatDefaultsToWebp(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('webp', $command->getDefinition()->getOption('thumbnail-format')->getDefault());
    }

    // ── Execute Default Path ────────────────────────────────────────────────

    public function testExecuteWithDefaultOptionsAndSectionData(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Sample Import from Newscoop', $output);
        $this->assertStringContainsString('Import Completed Successfully', $output);
    }

    public function testExecuteDisplaysTitleAndSummary(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('6 categories', $output);
        $this->assertStringContainsString('300 articles', $output);
        $this->assertStringContainsString('Categories', $output);
        $this->assertStringContainsString('Authors', $output);
        $this->assertStringContainsString('Articles', $output);
    }

    // ── Exception Handling ──────────────────────────────────────────────────

    public function testExecuteHandlesExceptionOnConnectionFailure(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')
            ->willThrowException(new Exception('Connection refused'));
        $newscoopConn->method('fetchAllAssociative')
            ->willThrowException(new Exception('Connection refused'));

        $command = $this->buildCommand(newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Import failed', $output);
        $this->assertStringContainsString('Connection refused', $output);
    }

    public function testExecuteHandlesGenericException(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')
            ->willThrowException(new Exception('Unexpected error'));

        $command = $this->buildCommand(newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Unexpected error', $tester->getDisplay());
    }

    // ── Skipped Images ──────────────────────────────────────────────────────

    public function testExecuteWithoutWithImagesFlagSkipsImages(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipping images import', $output);
    }

    // ── Skipped Related ─────────────────────────────────────────────────────

    public function testExecuteWithoutWithRelatedFlagSkipsRelated(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipping related articles', $output);
    }

    // ── Skipped Database Reset ──────────────────────────────────────────────

    public function testExecuteWithoutResetDbSkipsDatabaseReset(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipping database reset', $output);
    }

    // ── Categories Import Phase ─────────────────────────────────────────────

    public function testExecuteImportsCategoriesWithTranslations(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Societate', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Importing categories with ALL translations', $output);
        $this->assertStringContainsString('Imported 6 categories', $output);
    }

    // ── Articles Import Phase ───────────────────────────────────────────────

    public function testExecuteImportsArticlesPhase(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Importing articles', $output);
        $this->assertStringContainsString('Imported 0 articles', $output);
    }

    // ── Authors Import Phase ────────────────────────────────────────────────

    public function testExecuteImportsAuthorsPhaseAfterArticles(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Importing authors', $output);
    }

    // ── Summary Table ───────────────────────────────────────────────────────

    public function testExecuteShowsSummaryTable(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        // Summary table should contain entity names
        $this->assertStringContainsString('Categories', $output);
        $this->assertStringContainsString('Authors', $output);
        $this->assertStringContainsString('Articles', $output);
        $this->assertStringContainsString('Images', $output);
        $this->assertStringContainsString('Related Articles', $output);
    }

    public function testExecuteShowsImageAndRelatedAsSkippedInSummary(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('skipped', $output);
    }

    // ── Execution Time ──────────────────────────────────────────────────────

    public function testExecuteShowsTotalTime(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Total time:', $output);
        $this->assertStringContainsString('seconds', $output);
    }

    // ── Step Numbering ──────────────────────────────────────────────────────

    public function testExecuteShowsStepNumbers(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, []);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('[1/6]', $output);
        $this->assertStringContainsString('[2/6]', $output);
        $this->assertStringContainsString('[3/6]', $output);
        $this->assertStringContainsString('[4/6]', $output);
        $this->assertStringContainsString('[5/6]', $output);
        $this->assertStringContainsString('[6/6]', $output);
    }

    // ── Article Import with Content ────────────────────────────────────────

    public function testExecuteImportsArticlesWithXstiriContent(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];
        $xstiriContent = [
            'NrArticle' => 100,
            'IdLanguage' => 2,
            'FTitlu' => 'Test Article Title',
            'Fsubtitlu' => 'Subtitle',
            'FContinut' => 'Article content body',
            'Flead' => 'Article lead text',
        ];
        $articleRow = [
            'Number' => 100,
            'IdLanguage' => 2,
            'Name' => 'Test Article',
            'PublishDate' => '2025-01-15 10:00:00',
            'NrSection' => 2, // maps to 'social' category's ro section ID
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                // First 18 calls are section fetches (6 categories x 3 locales)
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }
                // After that, it's Xstiri content fetch
                return $xstiriContent;
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRow) {
                // Articles query (contains 'RankedArticles')
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }
                // Authors query
                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                Author::class => $this->createStub(EntityRepository::class),
                Article::class => $this->createStub(EntityRepository::class),
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 1 articles', $output);
    }

    // ── Article Import: Content Not Found (Skip) ────────────────────────────

    public function testExecuteSkipsArticlesWithNoContent(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];
        $articleRow = [
            'Number' => 100,
            'IdLanguage' => 2,
            'Name' => 'Test Article',
            'PublishDate' => '2025-01-15 10:00:00',
            'NrSection' => 2,
        ];

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }
                // Return false/null for Xstiri content (no content found)
                return false;
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRow) {
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }

                return [];
            }
        );

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 0 articles', $output);
    }

    // ── With Translations Flag ──────────────────────────────────────────────

    public function testExecuteWithTranslationsFlag(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];
        $xstiriContent = [
            'NrArticle' => 100,
            'IdLanguage' => 2,
            'FTitlu' => 'Test Title',
            'Fsubtitlu' => 'Sub',
            'FContinut' => 'Content',
            'Flead' => 'Lead',
        ];
        $articleRow = [
            'Number' => 100,
            'IdLanguage' => 2,
            'Name' => 'Test Article',
            'PublishDate' => '2025-01-15 10:00:00',
            'NrSection' => 2,
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }

                return $xstiriContent;
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRow) {
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }

                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, [
            '--articles-per-category' => '1',
            '--with-translations' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 1 articles', $output);
    }

    // ── With Images Flag (No articles imported) ─────────────────────────────

    public function testExecuteWithImagesFlagSkipsWhenNoArticles(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--with-images' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 0 images', $output);
    }

    // ── With Related Flag (No articles imported) ────────────────────────────

    public function testExecuteWithRelatedFlagSkipsWhenNoArticles(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--with-related' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 0 relations', $output);
    }

    // ── Authors Import: Existing Author By Email ────────────────────────────

    public function testExecuteImportsAuthorsSkippingExisting(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];
        $xstiriContent = [
            'NrArticle' => 100,
            'IdLanguage' => 2,
            'FTitlu' => 'Test Title',
            'Fsubtitlu' => 'Sub',
            'FContinut' => 'Content',
            'Flead' => 'Lead',
        ];
        $articleRow = [
            'Number' => 100,
            'IdLanguage' => 2,
            'Name' => 'Test',
            'PublishDate' => '2025-01-15 10:00:00',
            'NrSection' => 2,
        ];
        $authorData = [
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@deschide.md',
            'biography' => 'A test author',
            'image' => null,
        ];
        $authorAssoc = [
            'fk_article_number' => 100,
            'fk_author_id' => 1,
            'order' => 0,
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $existingAuthor = $this->createStub(Author::class);
        $existingAuthor->method('getId')->willReturn(99);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }

                return $xstiriContent;
            }
        );
        $fetchAllCount = 0;
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use (&$fetchAllCount, $articleRow, $authorData, $authorAssoc) {
                ++$fetchAllCount;
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }
                if (str_contains($sql, 'Authors au')) {
                    return [$authorData];
                }
                if (str_contains($sql, 'ArticleAuthors aa')) {
                    return [$authorAssoc];
                }

                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $authorRepo = $this->createStub(EntityRepository::class);
        $authorRepo->method('findOneBy')->willReturn($existingAuthor);
        $authorRepo->method('find')->willReturn($existingAuthor);

        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->willReturn($article);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                Author::class => $authorRepo,
                Article::class => $articleRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        // 0 new authors because existing was found
        $this->assertStringContainsString('Imported 0 authors', $output);
    }

    // ── Article Import Exception Handling ───────────────────────────────────

    public function testExecuteHandlesExceptionDuringArticleImport(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];
        $articleRow = [
            'Number' => 100,
            'IdLanguage' => 2,
            'Name' => 'Test',
            'PublishDate' => '2025-01-15 10:00:00',
            'NrSection' => 2,
        ];
        $xstiriContent = [
            'NrArticle' => 100,
            'IdLanguage' => 2,
            'FTitlu' => 'Title',
            'Fsubtitlu' => null,
            'FContinut' => null,
            'Flead' => null,
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }

                return $xstiriContent;
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRow) {
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }

                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );
        // flush throws to simulate error during persist
        $em->method('flush')->willThrowException(new Exception('Integrity constraint'));

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '1']);

        // The exception is caught within the execute try/catch
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Import failed', $output);
    }

    // ── Custom Articles Per Category Option ─────────────────────────────────

    public function testExecuteWithCustomArticlesPerCategory(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturn($sectionRow);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '10']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('10 articles per category', $output);
    }

    // ── Authors Import: New Author (no existing) ────────────────────────────

    public function testExecuteImportsNewAuthorsWithBio(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];
        $xstiriContent = [
            'NrArticle' => 100,
            'IdLanguage' => 2,
            'FTitlu' => 'Test Title',
            'Fsubtitlu' => 'Sub',
            'FContinut' => 'Content',
            'Flead' => 'Lead',
        ];
        $articleRow = [
            'Number' => 100,
            'IdLanguage' => 2,
            'Name' => 'Test',
            'PublishDate' => '2025-01-15 10:00:00',
            'NrSection' => 2,
        ];
        $authorData = [
            'id' => 1,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => null, // Will be generated
            'biography' => 'A great journalist',
            'image' => null,
        ];
        $authorAssoc = [
            'fk_article_number' => 100,
            'fk_author_id' => 1,
            'order' => 0,
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }
                return $xstiriContent;
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRow, $authorData, $authorAssoc) {
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }
                if (str_contains($sql, 'Authors au')) {
                    return [$authorData];
                }
                if (str_contains($sql, 'ArticleAuthors aa')) {
                    return [$authorAssoc];
                }
                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $authorRepo = $this->createStub(EntityRepository::class);
        $authorRepo->method('findOneBy')->willReturn(null); // No existing author

        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->willReturn($article);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                Author::class => $authorRepo,
                Article::class => $articleRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 1 authors', $output);
    }

    // ── Article with BLOB content (resource type) ──────────────────────────

    public function testExecuteImportsArticleWithBlobResourceContent(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];
        // Simulate BLOB content as a resource
        $contentStream = fopen('php://memory', 'r+');
        fwrite($contentStream, 'Article body from BLOB');
        rewind($contentStream);

        $leadStream = fopen('php://memory', 'r+');
        fwrite($leadStream, 'Lead from BLOB');
        rewind($leadStream);

        $xstiriContent = [
            'NrArticle' => 200,
            'IdLanguage' => 2,
            'FTitlu' => 'BLOB Article',
            'Fsubtitlu' => null,
            'FContinut' => $contentStream,
            'Flead' => $leadStream,
        ];
        $articleRow = [
            'Number' => 200,
            'IdLanguage' => 2,
            'Name' => 'BLOB Test',
            'PublishDate' => '2025-02-01 12:00:00',
            'NrSection' => 2,
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }
                return $xstiriContent;
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRow) {
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }
                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 1 articles', $output);
    }

    // ── Article with null Lead and Content ──────────────────────────────────

    public function testExecuteImportsArticleWithNullLeadAndContent(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];
        $xstiriContent = [
            'NrArticle' => 300,
            'IdLanguage' => 2,
            'FTitlu' => 'Title Only',
            'Fsubtitlu' => null,
            'FContinut' => null,
            'Flead' => null,
        ];
        $articleRow = [
            'Number' => 300,
            'IdLanguage' => 2,
            'Name' => 'Null Lead',
            'PublishDate' => '2025-03-01 10:00:00',
            'NrSection' => 2,
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }
                return $xstiriContent;
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRow) {
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }
                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 1 articles', $output);
    }

    // ── Related Articles with mapped articles ──────────────────────────────

    public function testExecuteWithRelatedFlagImportsRelations(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];
        $xstiriContent = [
            'NrArticle' => 100,
            'IdLanguage' => 2,
            'FTitlu' => 'Test Title',
            'Fsubtitlu' => 'Sub',
            'FContinut' => 'Content',
            'Flead' => 'Lead',
        ];
        $articleRows = [
            [
                'Number' => 100,
                'IdLanguage' => 2,
                'Name' => 'Article 1',
                'PublishDate' => '2025-01-15 10:00:00',
                'NrSection' => 2,
            ],
            [
                'Number' => 200,
                'IdLanguage' => 2,
                'Name' => 'Article 2',
                'PublishDate' => '2025-01-16 10:00:00',
                'NrSection' => 2,
            ],
        ];
        $relationData = [
            ['main_article' => 100, 'related_article' => 200],
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $article1 = $this->createStub(Article::class);
        $article1->method('getId')->willReturn(1);
        $article2 = $this->createStub(Article::class);
        $article2->method('getId')->willReturn(2);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }
                return $xstiriContent;
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRows, $relationData) {
                if (str_contains($sql, 'RankedArticles')) {
                    return $articleRows;
                }
                if (str_contains($sql, 'context_boxes')) {
                    return $relationData;
                }
                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $articleRepo = $this->createStub(EntityRepository::class);
        $articleRepo->method('find')->willReturnOnConsecutiveCalls($article1, $article2);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                Article::class => $articleRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, [
            '--articles-per-category' => '2',
            '--with-related' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported', $output);
        $this->assertStringContainsString('relations', $output);
    }

    // ── Author import with exception ──────────────────────────────────────

    public function testExecuteHandlesAuthorImportException(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];
        $xstiriContent = [
            'NrArticle' => 100,
            'IdLanguage' => 2,
            'FTitlu' => 'Test Title',
            'Fsubtitlu' => 'Sub',
            'FContinut' => 'Content',
            'Flead' => 'Lead',
        ];
        $articleRow = [
            'Number' => 100,
            'IdLanguage' => 2,
            'Name' => 'Test',
            'PublishDate' => '2025-01-15 10:00:00',
            'NrSection' => 2,
        ];
        $authorData = [
            'id' => 1,
            'first_name' => 'Bad',
            'last_name' => 'Author',
            'email' => null,
            'biography' => null,
            'image' => null,
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }
                return $xstiriContent;
            }
        );

        $flushCount = 0;
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRow, $authorData) {
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }
                if (str_contains($sql, 'Authors au')) {
                    return [$authorData];
                }
                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $authorRepo = $this->createStub(EntityRepository::class);
        $authorRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                Author::class => $authorRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );
        // persist throws when persisting Author entities (simulating constraint violation)
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof Author) {
                throw new Exception('Duplicate key violation');
            }
        });

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Error importing author', $output);
    }

    // ── Translations import: no translation found ───────────────────────────

    public function testExecuteWithTranslationsSkipsMissingTranslations(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test Category', 'IdLanguage' => 2];
        $articleRow = [
            'Number' => 100,
            'IdLanguage' => 2,
            'Name' => 'Test Article',
            'PublishDate' => '2025-01-15 10:00:00',
            'NrSection' => 2,
        ];

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function (string $sql, array $params) use (&$fetchAssocCount, $sectionRow) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }
                // Return content only for Romanian (lang=2), false for others
                if (isset($params['lang']) && $params['lang'] === 2) {
                    return [
                        'NrArticle' => 100,
                        'IdLanguage' => 2,
                        'FTitlu' => 'Romanian Title',
                        'Fsubtitlu' => null,
                        'FContinut' => 'Romanian Content',
                        'Flead' => 'Romanian Lead',
                    ];
                }
                return false; // No translation
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRow) {
                if (str_contains($sql, 'RankedArticles')) {
                    return [$articleRow];
                }
                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, [
            '--articles-per-category' => '1',
            '--with-translations' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 1 articles', $output);
    }

    // ── Article batch flush every 10 ────────────────────────────────────────

    public function testExecuteFlushesEvery10Articles(): void
    {
        $sectionRow = ['id' => 1, 'Name' => 'Test', 'IdLanguage' => 2];
        $xstiriContent = [
            'NrArticle' => 100,
            'IdLanguage' => 2,
            'FTitlu' => 'Test Title',
            'Fsubtitlu' => null,
            'FContinut' => 'Content body',
            'Flead' => 'Lead text',
        ];

        // Generate 12 articles so we hit the "clear every 10" boundary
        $articleRows = [];
        for ($i = 0; $i < 12; ++$i) {
            $articleRows[] = [
                'Number' => 100 + $i,
                'IdLanguage' => 2,
                'Name' => "Article $i",
                'PublishDate' => '2025-01-15 10:00:00',
                'NrSection' => 2,
            ];
        }

        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(1);

        $fetchAssocCount = 0;
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAssociative')->willReturnCallback(
            function () use (&$fetchAssocCount, $sectionRow, $xstiriContent) {
                ++$fetchAssocCount;
                if ($fetchAssocCount <= 18) {
                    return $sectionRow;
                }
                return $xstiriContent;
            }
        );
        $newscoopConn->method('fetchAllAssociative')->willReturnCallback(
            function (string $sql) use ($articleRows) {
                if (str_contains($sql, 'RankedArticles')) {
                    return $articleRows;
                }
                return [];
            }
        );

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('find')->willReturn($category);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => match ($class) {
                Category::class => $categoryRepo,
                default => $this->createStub(EntityRepository::class),
            }
        );
        // clear() should be called at least once (when count % 10 === 0)
        $em->expects($this->atLeastOnce())->method('clear');

        $command = $this->buildCommand(em: $em, newscoopConnection: $newscoopConn);
        $tester = $this->runCommand($command, ['--articles-per-category' => '12']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Imported 12 articles', $output);
    }

    // ── Helper Methods ──────────────────────────────────────────────────────

    private function runCommand(SampleImportCommand $command, array $input): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:sample-import'));
        $tester->execute($input);

        return $tester;
    }

    private function buildCommand(
        ?EntityManagerInterface $em = null,
        ?Connection $newscoopConnection = null,
        ?SluggerInterface $slugger = null,
    ): SampleImportCommand {
        if ($slugger === null) {
            $unicodeString = $this->createStub(UnicodeString::class);
            $unicodeString->method('lower')->willReturnSelf();
            $unicodeString->method('toString')->willReturn('test-slug');

            $slugger = $this->createStub(SluggerInterface::class);
            $slugger->method('slug')->willReturn($unicodeString);
        }

        return new SampleImportCommand(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $newscoopConnection ?? $this->createStub(Connection::class),
            $slugger,
        );
    }
}
