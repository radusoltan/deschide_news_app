<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SeedStagingDataCommand;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SeedStagingDataCommandTest extends TestCase
{
    // ── Metadata Tests ──────────────────────────────────────────────────────

    public function testCommandHasCorrectName(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedStagingDataCommand($em);
        $this->assertSame('app:seed:staging-data', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedStagingDataCommand($em);
        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('staging', strtolower($command->getDescription()));
    }

    public function testCommandHasAllOptions(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedStagingDataCommand($em);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('categories'));
        $this->assertTrue($definition->hasOption('authors'));
        $this->assertTrue($definition->hasOption('tags'));
        $this->assertTrue($definition->hasOption('all'));
        $this->assertTrue($definition->hasOption('cleanup-bad-slugs'));
    }

    // ── Default Execution (seeds all) ───────────────────────────────────────

    public function testExecuteWithDefaultOptionsSeedsAll(): void
    {
        $em = $this->createEntityManagerWithConnection();

        $tester = $this->runCommand($em, []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Staging Data', $output);
        $this->assertStringContainsString('Seeding Categories', $output);
        $this->assertStringContainsString('Seeding Authors', $output);
        $this->assertStringContainsString('Seeding Tags', $output);
        $this->assertStringContainsString('Summary', $output);
        $this->assertStringContainsString('completed', $output);
    }

    public function testExecuteWithExplicitAllOption(): void
    {
        $em = $this->createEntityManagerWithConnection();

        $tester = $this->runCommand($em, ['--all' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Categories', $output);
        $this->assertStringContainsString('Seeding Authors', $output);
        $this->assertStringContainsString('Seeding Tags', $output);
    }

    // ── Categories Only ─────────────────────────────────────────────────────

    public function testExecuteSeedsCategoriesOnly(): void
    {
        $em = $this->createEntityManagerWithConnection();

        $tester = $this->runCommand($em, ['--categories' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Categories', $output);
        $this->assertStringNotContainsString('Seeding Authors', $output);
        $this->assertStringNotContainsString('Seeding Tags', $output);
    }

    public function testCategoriesSeedingSkipsExistingCategories(): void
    {
        $conn = $this->createMock(Connection::class);
        // fetchOne returns an existing ID for every slug check
        $conn->method('fetchOne')->willReturn(42);
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeStatement')->willReturn(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--categories' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('SKIP', $output);
    }

    public function testCategoriesSeedingCreatesNewCategories(): void
    {
        $conn = $this->createMock(Connection::class);
        // fetchOne returns false for slug check (no existing category), 0 for summary counts
        $conn->method('fetchOne')->willReturn(false);
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--categories' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('NEW', $output);
    }

    // ── Authors Only ────────────────────────────────────────────────────────

    public function testExecuteSeedsAuthorsOnly(): void
    {
        $em = $this->createEntityManagerWithConnection();

        $tester = $this->runCommand($em, ['--authors' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Authors', $output);
        $this->assertStringNotContainsString('Seeding Categories', $output);
        $this->assertStringNotContainsString('Seeding Tags', $output);
    }

    public function testAuthorsSeedingSkipsExistingAuthors(): void
    {
        $conn = $this->createMock(Connection::class);
        // fetchOne returns existing author ID for email check
        $conn->method('fetchOne')->willReturn(99);
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeStatement')->willReturn(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--authors' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('SKIP', $output);
    }

    public function testAuthorsSeedingCreatesNewAuthors(): void
    {
        $callCount = 0;
        $conn = $this->createMock(Connection::class);
        // First fetchOne for email check returns false (not existing),
        // second for slug uniqueness check returns false
        $conn->method('fetchOne')->willReturnCallback(function () use (&$callCount) {
            return false;
        });
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--authors' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('NEW', $output);
    }

    // ── Tags Only ───────────────────────────────────────────────────────────

    public function testExecuteSeedsTagsOnly(): void
    {
        $em = $this->createEntityManagerWithConnection();

        $tester = $this->runCommand($em, ['--tags' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Tags', $output);
        $this->assertStringNotContainsString('Seeding Categories', $output);
        $this->assertStringNotContainsString('Seeding Authors', $output);
    }

    public function testTagsSeedingSkipsExistingTags(): void
    {
        $conn = $this->createMock(Connection::class);
        // fetchOne returns existing tag ID for slug check
        $conn->method('fetchOne')->willReturn(55);
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeStatement')->willReturn(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--tags' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('SKIP', $output);
    }

    public function testTagsSeedingCreatesNewTags(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchOne')->willReturn(false);
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--tags' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // ── Cleanup Bad Slugs ───────────────────────────────────────────────────

    public function testExecuteWithCleanupBadSlugsOption(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('fetchOne')->willReturn(false);
        $conn->method('executeStatement')->willReturn(0);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--cleanup-bad-slugs' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Cleaning up bad slugs', $output);
        $this->assertStringContainsString('Cleanup:', $output);
    }

    public function testCleanupBadSlugsDeletesBadCategories(): void
    {
        $conn = $this->createMock(Connection::class);

        // fetchAllAssociative: first call returns categories, second returns bad tags
        $conn->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                ['id' => 1, 'slug' => 'politica', 'title' => 'Politica'],
                ['id' => 99, 'slug' => 'politika', 'title' => 'Политика'],
            ],
            [] // No bad tags
        );

        $conn->method('fetchOne')->willReturn(false);
        $conn->method('executeStatement')->willReturn(0);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--cleanup-bad-slugs' => true, '--categories' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DEL', $output);
    }

    public function testCleanupBadSlugsWithCombinedOptions(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('fetchOne')->willReturn(false);
        $conn->method('executeStatement')->willReturn(0);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        // Cleanup runs before seeding
        $tester = $this->runCommand($em, [
            '--cleanup-bad-slugs' => true,
            '--all' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Cleaning up bad slugs', $output);
        $this->assertStringContainsString('Seeding Categories', $output);
    }

    // ── Summary Output ──────────────────────────────────────────────────────

    public function testExecuteAlwaysShowsSummary(): void
    {
        $em = $this->createEntityManagerWithConnection();

        $tester = $this->runCommand($em, []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Summary', $output);
        $this->assertStringContainsString('Categories', $output);
        $this->assertStringContainsString('Authors', $output);
        $this->assertStringContainsString('Tags', $output);
    }

    public function testExecuteShowsTitle(): void
    {
        $em = $this->createEntityManagerWithConnection();

        $tester = $this->runCommand($em, []);

        $this->assertStringContainsString('STG-07', $tester->getDisplay());
    }

    // ── Multiple Options ────────────────────────────────────────────────────

    public function testExecuteWithCategoriesAndTagsOptions(): void
    {
        $em = $this->createEntityManagerWithConnection();

        $tester = $this->runCommand($em, [
            '--categories' => true,
            '--tags' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Categories', $output);
        $this->assertStringContainsString('Seeding Tags', $output);
        $this->assertStringNotContainsString('Seeding Authors', $output);
    }

    public function testExecuteWithAuthorsAndTagsOptions(): void
    {
        $em = $this->createEntityManagerWithConnection();

        $tester = $this->runCommand($em, [
            '--authors' => true,
            '--tags' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Authors', $output);
        $this->assertStringContainsString('Seeding Tags', $output);
        $this->assertStringNotContainsString('Seeding Categories', $output);
    }

    // ── Cleanup Bad Slugs: Reassignment with Mapping ──────────────────────

    public function testCleanupBadSlugsReassignsArticlesWithMapping(): void
    {
        $conn = $this->createMock(Connection::class);

        // fetchAllAssociative: first call returns categories with a good and a bad slug with mapping
        // second call returns empty bad tags
        $conn->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                ['id' => 1, 'slug' => 'politica', 'title' => 'Politica'],
                ['id' => 99, 'slug' => 'politika', 'title' => 'Политика'], // maps to 'politica'
            ],
            [] // No bad tags
        );

        // executeStatement returns > 0 for article reassignment
        $conn->method('executeStatement')->willReturnCallback(
            function (string $sql) {
                if (str_contains($sql, 'UPDATE articles SET category_id')) {
                    return 3; // 3 articles reassigned
                }
                return 0;
            }
        );
        $conn->method('fetchOne')->willReturn(false);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--cleanup-bad-slugs' => true, '--categories' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('MOVE', $output);
        $this->assertStringContainsString('DEL', $output);
    }

    // ── Cleanup Bad Slugs: No Mapping (Nullify) ──────────────────────────

    public function testCleanupBadSlugsNullifiesArticlesWithoutMapping(): void
    {
        $conn = $this->createMock(Connection::class);

        $conn->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                ['id' => 1, 'slug' => 'politica', 'title' => 'Politica'],
                ['id' => 50, 'slug' => 'unknown-slug', 'title' => 'Unknown'], // no mapping
            ],
            [] // No bad tags
        );

        $conn->method('executeStatement')->willReturn(0);
        $conn->method('fetchOne')->willReturn(false);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--cleanup-bad-slugs' => true, '--categories' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DEL', $output);
    }

    // ── Cleanup Bad Slugs: Bad Tags ──────────────────────────────────────

    public function testCleanupBadSlugsDeletesBadTags(): void
    {
        $conn = $this->createMock(Connection::class);

        $conn->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [], // No categories
            [['id' => 10, 'slug' => 'кириллица', 'name' => 'Bad Tag']] // Bad tag with Cyrillic
        );

        $conn->method('executeStatement')->willReturn(0);
        $conn->method('fetchOne')->willReturn(false);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--cleanup-bad-slugs' => true, '--tags' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DEL', $output);
        $this->assertStringContainsString('Tag', $output);
    }

    // ── Child Categories: Parent Not Found ──────────────────────────────

    public function testChildCategorySkippedWhenParentNotFound(): void
    {
        $fetchOneCallCount = 0;
        $conn = $this->createMock(Connection::class);
        // First round: main categories not existing, created
        // Then child categories: slug check returns false, parent lookup also returns false
        $conn->method('fetchOne')->willReturnCallback(function (string $sql, array $params) use (&$fetchOneCallCount) {
            ++$fetchOneCallCount;
            // For main category slug checks (first 18 calls): return false so they get created
            if ($fetchOneCallCount <= 18) {
                return false;
            }
            // For child category slug check: return false (new)
            if ($fetchOneCallCount <= 22) {
                return false;
            }
            // For parent lookup: return false (not found) - this triggers the WARN path
            return false;
        });

        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--categories' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // ── Author Slug Uniqueness ──────────────────────────────────────────

    public function testAuthorSlugUniquenessAppendsSuffix(): void
    {
        $callCount = 0;
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchOne')->willReturnCallback(function (string $sql) use (&$callCount) {
            ++$callCount;
            // Email check: return false (new author)
            if (str_contains($sql, 'email')) {
                return false;
            }
            // Slug check: return existing for first attempt, false for second
            if (str_contains($sql, 'slug')) {
                // First slug check returns existing, second returns false
                static $slugChecks = 0;
                ++$slugChecks;
                return $slugChecks % 2 === 1 ? 1 : false;
            }
            return false;
        });
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeStatement')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        $tester = $this->runCommand($em, ['--authors' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('NEW', $output);
    }

    // ── GenerateSlug helper coverage ──────────────────────────────────────

    public function testGenerateSlugHandlesRomanianCharacters(): void
    {
        $method = new \ReflectionMethod(SeedStagingDataCommand::class, 'generateSlug');

        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedStagingDataCommand($em);

        // Test Romanian diacritics transliteration
        $result = $method->invoke($command, 'Ștefan Țepeș');
        $this->assertSame('stefan-tepes', $result);
    }

    public function testGenerateSlugHandlesSpecialCharacters(): void
    {
        $method = new \ReflectionMethod(SeedStagingDataCommand::class, 'generateSlug');

        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedStagingDataCommand($em);

        $result = $method->invoke($command, 'Hello World!');
        $this->assertSame('hello-world', $result);
    }

    public function testGenerateSlugTrimsHyphens(): void
    {
        $method = new \ReflectionMethod(SeedStagingDataCommand::class, 'generateSlug');

        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedStagingDataCommand($em);

        $result = $method->invoke($command, '  Hello  ');
        $this->assertSame('hello', $result);
    }

    // ── Helper Methods ──────────────────────────────────────────────────────

    private function runCommand(EntityManagerInterface $em, array $input): CommandTester
    {
        $command = new SeedStagingDataCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:seed:staging-data'));
        $tester->execute($input);

        return $tester;
    }

    private function createEntityManagerWithConnection(): EntityManagerInterface
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('fetchOne')->willReturn(false);
        $conn->method('executeStatement')->willReturn(0);
        $conn->method('lastInsertId')->willReturn('1');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);

        return $em;
    }
}
