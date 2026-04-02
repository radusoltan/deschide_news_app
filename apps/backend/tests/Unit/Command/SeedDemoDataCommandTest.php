<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SeedDemoDataCommand;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SeedDemoDataCommandTest extends TestCase
{
    // ── Metadata Tests ──────────────────────────────────────────────────────

    public function testCommandHasCorrectName(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedDemoDataCommand($em);
        $this->assertSame('app:seed-demo-data', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedDemoDataCommand($em);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasAllOptions(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new SeedDemoDataCommand($em);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('archive'));
        $this->assertTrue($definition->hasOption('sport'));
        $this->assertTrue($definition->hasOption('tags'));
        $this->assertTrue($definition->hasOption('all'));
    }

    // ── No Options Path ─────────────────────────────────────────────────────

    public function testExecuteWithNoOptionsShowsWarningAndReturnsSuccess(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('No option specified', $output);
        $this->assertStringContainsString('--archive', $output);
        $this->assertStringContainsString('--sport', $output);
        $this->assertStringContainsString('--tags', $output);
        $this->assertStringContainsString('--all', $output);
    }

    public function testExecuteWithNoOptionsDoesNotQueryEntities(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getRepository');
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $tester = $this->runCommand(new SeedDemoDataCommand($em), []);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // ── No Users ────────────────────────────────────────────────────────────

    public function testExecuteWithArchiveOptionFailsWhenNoUsers(): void
    {
        $em = $this->buildEmWithRepositories(users: []);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--archive' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No users found', $tester->getDisplay());
    }

    public function testExecuteWithTagsOptionFailsWhenNoUsers(): void
    {
        $em = $this->buildEmWithRepositories(users: []);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--tags' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No users found', $tester->getDisplay());
    }

    public function testExecuteWithSportOptionFailsWhenNoUsers(): void
    {
        $em = $this->buildEmWithRepositories(users: []);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--sport' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No users found', $tester->getDisplay());
    }

    public function testExecuteWithAllOptionFailsWhenNoUsers(): void
    {
        $em = $this->buildEmWithRepositories(users: []);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--all' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No users found', $tester->getDisplay());
    }

    // ── No Categories ───────────────────────────────────────────────────────

    public function testExecuteFailsWhenNoCategoriesFound(): void
    {
        $user = $this->createStub(User::class);
        $em = $this->buildEmWithRepositories(users: [$user], categories: []);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--archive' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No categories found', $tester->getDisplay());
    }

    public function testExecuteWithAllOptionFailsWhenNoCategories(): void
    {
        $user = $this->createStub(User::class);
        $em = $this->buildEmWithRepositories(users: [$user], categories: []);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--all' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No categories found', $tester->getDisplay());
    }

    // ── No Authors ──────────────────────────────────────────────────────────

    public function testExecuteFailsWhenNoAuthorsFound(): void
    {
        $user = $this->createStub(User::class);
        $category = $this->createStub(Category::class);
        $em = $this->buildEmWithRepositories(users: [$user], categories: [$category], authors: []);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--archive' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No authors found', $tester->getDisplay());
    }

    public function testExecuteWithAllOptionFailsWhenNoAuthors(): void
    {
        $user = $this->createStub(User::class);
        $category = $this->createStub(Category::class);
        $em = $this->buildEmWithRepositories(users: [$user], categories: [$category], authors: []);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--all' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No authors found', $tester->getDisplay());
    }

    // ── Tags Option ─────────────────────────────────────────────────────────

    public function testExecuteWithTagsOptionSeedsTagsAndReturnsSuccess(): void
    {
        $user = $this->createStub(User::class);
        $category = $this->createStub(Category::class);
        $author = $this->createStub(Author::class);

        $tagRepo = $this->createStub(EntityRepository::class);
        $tagRepo->method('findOneBy')->willReturn(null); // no existing tags
        $tagRepo->method('findAll')->willReturn([]);

        $em = $this->buildEmWithRepositories(
            users: [$user],
            categories: [$category],
            authors: [$author],
            tagRepo: $tagRepo,
        );

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--tags' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Tags', $output);
        $this->assertStringContainsString('tags with translations', $output);
        $this->assertStringContainsString('Demo data seeding completed', $output);
    }

    public function testExecuteWithTagsOptionSkipsExistingTags(): void
    {
        $user = $this->createStub(User::class);
        $category = $this->createStub(Category::class);
        $author = $this->createStub(Author::class);
        $existingTag = $this->createStub(Tag::class);

        $tagRepo = $this->createStub(EntityRepository::class);
        // All tags already exist
        $tagRepo->method('findOneBy')->willReturn($existingTag);
        $tagRepo->method('findAll')->willReturn([]);

        $em = $this->buildEmWithRepositories(
            users: [$user],
            categories: [$category],
            authors: [$author],
            tagRepo: $tagRepo,
        );

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--tags' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Created 0 tags', $tester->getDisplay());
    }

    // ── All Option ──────────────────────────────────────────────────────────

    public function testExecuteWithAllOptionSeedsEverything(): void
    {
        $user = $this->createStub(User::class);
        $category = $this->createStub(Category::class);
        $category->method('getTitle')->willReturn('Sport');
        $author1 = $this->createStub(Author::class);
        $author2 = $this->createStub(Author::class);

        $tagRepo = $this->createStub(EntityRepository::class);
        $tagRepo->method('findOneBy')->willReturn(null);
        $tagRepo->method('findAll')->willReturn([]);

        $em = $this->buildEmWithRepositories(
            users: [$user],
            categories: [$category],
            authors: [$author1, $author2],
            tagRepo: $tagRepo,
        );

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--all' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Tags', $output);
        $this->assertStringContainsString('Seeding Archive Articles', $output);
        $this->assertStringContainsString('Seeding LiveText Sport Matches', $output);
        $this->assertStringContainsString('Demo data seeding completed', $output);
    }

    // ── Archive Option ──────────────────────────────────────────────────────

    public function testExecuteWithArchiveOptionOnlySeedsArchive(): void
    {
        $user = $this->createStub(User::class);
        $category = $this->createStub(Category::class);
        $author1 = $this->createStub(Author::class);
        $author2 = $this->createStub(Author::class);

        $tagRepo = $this->createStub(EntityRepository::class);
        $tagRepo->method('findAll')->willReturn([]);

        $em = $this->buildEmWithRepositories(
            users: [$user],
            categories: [$category],
            authors: [$author1, $author2],
            tagRepo: $tagRepo,
        );

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--archive' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding Archive Articles', $output);
        $this->assertStringNotContainsString('Seeding Tags', $output);
        $this->assertStringNotContainsString('Seeding LiveText Sport Matches', $output);
    }

    // ── Sport Option ────────────────────────────────────────────────────────

    public function testExecuteWithSportOptionOnlySeedsSport(): void
    {
        $user = $this->createStub(User::class);
        $category = $this->createStub(Category::class);
        $category->method('getTitle')->willReturn('Sport');
        $author = $this->createStub(Author::class);

        $tagRepo = $this->createStub(EntityRepository::class);
        $tagRepo->method('findAll')->willReturn([]);

        $em = $this->buildEmWithRepositories(
            users: [$user],
            categories: [$category],
            authors: [$author],
            tagRepo: $tagRepo,
        );

        $tester = $this->runCommand(new SeedDemoDataCommand($em), ['--sport' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Seeding LiveText Sport Matches', $output);
        $this->assertStringNotContainsString('Seeding Tags', $output);
        $this->assertStringNotContainsString('Seeding Archive Articles', $output);
    }

    // ── Title Output ────────────────────────────────────────────────────────

    public function testExecuteAlwaysDisplaysTitle(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $tester = $this->runCommand(new SeedDemoDataCommand($em), []);

        $this->assertStringContainsString('Seeding Demo Data for Testing', $tester->getDisplay());
    }

    // ── Helper Methods ──────────────────────────────────────────────────────

    private function runCommand(SeedDemoDataCommand $command, array $input): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('app:seed-demo-data'));
        $tester->execute($input);

        return $tester;
    }

    /**
     * Build EntityManagerInterface with preconfigured repositories.
     *
     * When tagRepo is provided, it will be used for the Tag entity repository.
     * The EntityManager also supports repeated getRepository calls for
     * User, Category, Author, and Tag after em->clear() reloads.
     */
    private function buildEmWithRepositories(
        array $users = [],
        array $categories = [],
        array $authors = [],
        ?EntityRepository $tagRepo = null,
    ): EntityManagerInterface {
        $userRepo = $this->createStub(EntityRepository::class);
        $userRepo->method('findAll')->willReturn($users);

        $categoryRepo = $this->createStub(EntityRepository::class);
        $categoryRepo->method('findAll')->willReturn($categories);

        $authorRepo = $this->createStub(EntityRepository::class);
        $authorRepo->method('findAll')->willReturn($authors);

        if ($tagRepo === null) {
            $tagRepo = $this->createStub(EntityRepository::class);
            $tagRepo->method('findAll')->willReturn([]);
            $tagRepo->method('findOneBy')->willReturn(null);
        }

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            function (string $class) use ($userRepo, $categoryRepo, $authorRepo, $tagRepo) {
                return match ($class) {
                    User::class => $userRepo,
                    Category::class => $categoryRepo,
                    Author::class => $authorRepo,
                    Tag::class => $tagRepo,
                    default => $this->createStub(EntityRepository::class),
                };
            }
        );

        return $em;
    }
}
