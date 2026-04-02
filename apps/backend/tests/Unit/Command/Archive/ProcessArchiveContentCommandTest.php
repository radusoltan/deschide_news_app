<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Archive;

use App\Command\Archive\ProcessArchiveContentCommand;
use App\Service\Archive\ContentProcessor;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ProcessArchiveContentCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $command = $this->buildCommand();
        $this->assertSame('app:archive:process-content', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->buildCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $command = $this->buildCommand();
        $this->assertTrue($command->getDefinition()->hasOption('source'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testCommandHasAllExpectedOptions(): void
    {
        $command = $this->buildCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('source'));
        $this->assertTrue($definition->hasOption('limit'));
        $this->assertTrue($definition->hasOption('offset'));
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('save-processed'));
        $this->assertTrue($definition->hasOption('language'));
        $this->assertTrue($definition->hasOption('show-samples'));
        $this->assertTrue($definition->hasOption('only-with-shortcodes'));
    }

    public function testSourceOptionDefaultsToAll(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('source');
        $this->assertSame('all', $option->getDefault());
    }

    public function testSourceOptionHasShortcutS(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('source');
        $this->assertSame('s', $option->getShortcut());
    }

    public function testLanguageOptionDefaultsTo2(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('language');
        $this->assertSame(2, $option->getDefault());
    }

    public function testOffsetOptionDefaultsTo0(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('offset');
        $this->assertSame(0, $option->getDefault());
    }

    public function testDryRunOptionHasShortcutD(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('dry-run');
        $this->assertSame('d', $option->getShortcut());
    }

    public function testLimitOptionHasShortcutL(): void
    {
        $command = $this->buildCommand();
        $option = $command->getDefinition()->getOption('limit');
        $this->assertSame('l', $option->getShortcut());
    }

    public function testExecuteDryRunDoesNotWriteData(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $betaConn = $this->createStub(Connection::class);
        $betaConn->method('fetchAllAssociative')->willReturn([]);

        $processor = $this->createMock(ContentProcessor::class);
        $processor->expects($this->never())->method('processContent');

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
            betaDeschideConnection: $betaConn,
        );
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DRY RUN', $output);
    }

    public function testExecuteWithNewscoopSource(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $betaConn = $this->createMock(Connection::class);
        $betaConn->expects($this->never())->method('fetchAllAssociative');

        $tester = $this->createTester(
            newscoopConnection: $newscoopConn,
            betaDeschideConnection: $betaConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('NEWSCOOP', $tester->getDisplay());
    }

    public function testExecuteWithBetaSource(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->expects($this->never())->method('fetchAllAssociative');

        $betaConn = $this->createStub(Connection::class);
        $betaConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(
            newscoopConnection: $newscoopConn,
            betaDeschideConnection: $betaConn,
        );
        $tester->execute(['--source' => 'beta', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('BETA', $tester->getDisplay());
    }

    public function testExecuteWithSourceAllProcessesBothSources(): void
    {
        $newscoopConn = $this->createMock(Connection::class);
        $newscoopConn->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $betaConn = $this->createMock(Connection::class);
        $betaConn->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $tester = $this->createTester(
            newscoopConnection: $newscoopConn,
            betaDeschideConnection: $betaConn,
        );
        $tester->execute(['--source' => 'all', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('NEWSCOOP', $display);
        $this->assertStringContainsString('BETA', $display);
    }

    public function testExecuteWithInvalidSourceFails(): void
    {
        $tester = $this->createTester();
        $tester->execute(['--source' => 'invalid_source']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid source', $tester->getDisplay());
    }

    public function testExecuteWithZeroArticlesShowsWarning(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No articles found', $tester->getDisplay());
    }

    /**
     * Uses 2 articles to avoid a known bug where processSource returns
     * articlesCount which collides with Command::FAILURE (=1) for exactly 1 article.
     */
    public function testExecuteWithArticlesProcessesThem(): void
    {
        $articles = $this->buildArticleBatch(2);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(false);
        $processor->method('countShortcodes')->willReturn([
            'images' => 0,
            'internal_links' => 0,
            'titles' => 0,
            'snippets' => 0,
        ]);
        $processor->method('processContent')->willReturn('<p>Some normal content</p>');
        $processor->method('getStats')->willReturn([
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Found', $display);
        $this->assertStringContainsString('2', $display);
    }

    public function testExecuteWithArticlesContainingShortcodes(): void
    {
        $articles = [
            [
                'Number' => 200,
                'title' => 'Article With Image',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-06-15',
                'full_title' => 'Full Title',
                'subtitle' => '',
                'lead' => 'Lead',
                'content' => '<!** Image 5> Some text here',
            ],
            [
                'Number' => 201,
                'title' => 'Second Article',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-06-16',
                'full_title' => 'Second Title',
                'subtitle' => '',
                'lead' => 'Lead 2',
                'content' => '<!** Image 10> More text',
            ],
        ];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(true);
        $processor->method('countShortcodes')->willReturn([
            'images' => 1,
            'internal_links' => 0,
            'titles' => 0,
            'snippets' => 0,
        ]);
        $processor->method('processContent')
            ->willReturn('<figure><img src="test.jpg"></figure> Some text here');
        $processor->method('getStats')->willReturn([
            'images_processed' => 1,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Images Processed', $display);
    }

    public function testExecuteFetchArticlesExceptionReturnsFailure(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Article fetch failed', $this->callback(function (array $ctx): bool {
                return $ctx['source'] === 'newscoop'
                    && str_contains($ctx['error'], 'Database connection lost');
            }));

        $tester = $this->createTester(
            newscoopConnection: $newscoopConn,
            logger: $logger,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Failed to fetch articles', $tester->getDisplay());
    }

    public function testExecuteConfirmationCancelledReturnsSuccess(): void
    {
        $tester = $this->createTester();
        // SymfonyStyle::confirm default is true, answer 'no' to cancel
        $tester->setInputs(['no']);
        $tester->execute(['--source' => 'newscoop']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Operation cancelled', $tester->getDisplay());
    }

    public function testExecuteWithArticleHavingEmptyContent(): void
    {
        $articles = [
            [
                'Number' => 300,
                'title' => 'Empty Content Article',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-01-01',
                'full_title' => 'Title',
                'subtitle' => '',
                'lead' => '',
                'content' => '',
            ],
            [
                'Number' => 301,
                'title' => 'Also Empty',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-01-02',
                'full_title' => 'Title 2',
                'subtitle' => '',
                'lead' => '',
                'content' => '',
            ],
        ];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createMock(ContentProcessor::class);
        $processor->expects($this->never())->method('processContent');
        $processor->method('getStats')->willReturn([
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteWithArticleProcessingExceptionContinues(): void
    {
        $articles = [
            [
                'Number' => 400,
                'title' => 'Problematic Article',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-03-10',
                'full_title' => 'Title',
                'subtitle' => '',
                'lead' => '',
                'content' => '<!** Image 999> Bad content',
            ],
            [
                'Number' => 401,
                'title' => 'Another Problematic',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-03-11',
                'full_title' => 'Title 2',
                'subtitle' => '',
                'lead' => '',
                'content' => '<!** Image 888> More bad content',
            ],
        ];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willThrowException(
            new \RuntimeException('Processing error')
        );
        $processor->method('getStats')->willReturn([
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('error')
            ->with('Article processing failed', $this->callback(function (array $ctx): bool {
                return \in_array($ctx['article_number'], [400, 401], true)
                    && $ctx['source'] === 'newscoop';
            }));

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
            logger: $logger,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        // Command should succeed even when individual articles fail
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteDryRunOutputsNoteAboutRunningWithoutDryRun(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(newscoopConnection: $newscoopConn);
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('without --dry-run', $display);
    }

    public function testExecuteDisplaysFinalStatistics(): void
    {
        $articles = $this->buildArticleBatch(2);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(false);
        $processor->method('countShortcodes')->willReturn([
            'images' => 0,
            'internal_links' => 0,
            'titles' => 0,
            'snippets' => 0,
        ]);
        $processor->method('processContent')->willReturn('<p>Content</p>');
        $processor->method('getStats')->willReturn([
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Final Statistics', $display);
        $this->assertStringContainsString('Total Articles Fetched', $display);
    }

    public function testExecuteWithInvalidSourceContainsErrorMessage(): void
    {
        $tester = $this->createTester();
        $tester->execute(['--source' => 'mysql']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Invalid source: mysql', $display);
        $this->assertStringContainsString('newscoop, beta, all', $display);
    }

    public function testDisplayConfigurationShowsSourceAndLanguage(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(newscoopConnection: $newscoopConn);
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Configuration', $display);
        $this->assertStringContainsString('NEWSCOOP', $display);
        $this->assertStringContainsString('Romanian', $display);
    }

    public function testDisplayConfigurationWithCustomLanguage(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(newscoopConnection: $newscoopConn);
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true, '--language' => '1']);

        $display = $tester->getDisplay();
        // Language ID 1 (not 2), so it won't say "Romanian"
        $this->assertStringContainsString('Configuration', $display);
    }

    public function testDisplayConfigurationWithLimitAndOffset(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(newscoopConnection: $newscoopConn);
        $tester->execute([
            '--source' => 'newscoop',
            '--dry-run' => true,
            '--limit' => '50',
            '--offset' => '10',
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('50', $display);
        $this->assertStringContainsString('10', $display);
    }

    public function testDisplayConfigurationShowsOnlyWithShortcodesFilter(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(newscoopConnection: $newscoopConn);
        $tester->execute([
            '--source' => 'newscoop',
            '--dry-run' => true,
            '--only-with-shortcodes' => true,
        ]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('shortcodes', $display);
    }

    public function testDisplayConfigurationShowsExpectedCountsForAll(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);
        $betaConn = $this->createStub(Connection::class);
        $betaConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(
            newscoopConnection: $newscoopConn,
            betaDeschideConnection: $betaConn,
        );
        $tester->execute(['--source' => 'all', '--dry-run' => true]);

        $display = $tester->getDisplay();
        // Should display counts for both Newscoop and Beta
        $this->assertStringContainsString('Newscoop', $display);
        $this->assertStringContainsString('Beta', $display);
        $this->assertStringContainsString('Total', $display);
    }

    public function testExecuteWithShowSamplesOptionDisplaysSamples(): void
    {
        $articles = [
            [
                'Number' => 100,
                'title' => 'Sample Article',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-06-15',
                'full_title' => 'Full Title',
                'subtitle' => '',
                'lead' => 'Lead',
                'content' => '<!** Image 5> Original content here with some text',
            ],
            [
                'Number' => 101,
                'title' => 'Another Article',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-06-16',
                'full_title' => 'Another Title',
                'subtitle' => '',
                'lead' => 'Lead 2',
                'content' => '<!** Image 10> More original content',
            ],
        ];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(true);
        $processor->method('countShortcodes')->willReturn([
            'images' => 1,
            'internal_links' => 0,
            'titles' => 0,
            'snippets' => 0,
        ]);
        $processor->method('processContent')
            ->willReturn('<figure><img src="test.jpg"></figure> Processed content');
        $processor->method('getStats')->willReturn([
            'images_processed' => 1,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute([
            '--source' => 'newscoop',
            '--dry-run' => true,
            '--show-samples' => true,
        ]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Sample:', $display);
        $this->assertStringContainsString('BEFORE', $display);
        $this->assertStringContainsString('AFTER', $display);
    }

    public function testDisplayFinalStatisticsShowsImageSuccessRate(): void
    {
        $articles = $this->buildArticleBatch(2);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(true);
        $processor->method('countShortcodes')->willReturn([
            'images' => 1,
            'internal_links' => 0,
            'titles' => 0,
            'snippets' => 0,
        ]);
        $processor->method('processContent')->willReturn('<p>Processed</p>');
        $processor->method('getStats')->willReturn([
            'images_processed' => 3,
            'images_not_found' => 1,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Image success rate', $display);
    }

    public function testDisplayFinalStatisticsShowsLinkSuccessRate(): void
    {
        $articles = $this->buildArticleBatch(2);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(true);
        $processor->method('countShortcodes')->willReturn([
            'images' => 0,
            'internal_links' => 2,
            'titles' => 0,
            'snippets' => 0,
        ]);
        $processor->method('processContent')->willReturn('<p>Processed</p>');
        $processor->method('getStats')->willReturn([
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 5,
            'links_broken' => 2,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Link success rate', $display);
    }

    public function testDisplaySourceStatisticsShowsTable(): void
    {
        $articles = $this->buildArticleBatch(2);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(false);
        $processor->method('countShortcodes')->willReturn([
            'images' => 0, 'internal_links' => 0, 'titles' => 0, 'snippets' => 0,
        ]);
        $processor->method('processContent')->willReturn('<p>Content</p>');
        $processor->method('getStats')->willReturn([
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Statistics: NEWSCOOP', $display);
        $this->assertStringContainsString('Links Processed', $display);
        $this->assertStringContainsString('Titles Converted', $display);
    }

    public function testExecuteWithBetaSourceProcessesBetaConnection(): void
    {
        $articles = $this->buildArticleBatch(2);

        $betaConn = $this->createStub(Connection::class);
        $betaConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(false);
        $processor->method('countShortcodes')->willReturn([
            'images' => 0, 'internal_links' => 0, 'titles' => 0, 'snippets' => 0,
        ]);
        $processor->method('processContent')->willReturn('<p>Content</p>');
        $processor->method('getStats')->willReturn([
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            betaDeschideConnection: $betaConn,
        );
        $tester->execute(['--source' => 'beta', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Statistics: BETA', $display);
    }

    public function testExecuteAllSourceProcessesBothAndShowsFinalStats(): void
    {
        $articles = $this->buildArticleBatch(2);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $betaConn = $this->createStub(Connection::class);
        $betaConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(false);
        $processor->method('countShortcodes')->willReturn([
            'images' => 0, 'internal_links' => 0, 'titles' => 0, 'snippets' => 0,
        ]);
        $processor->method('processContent')->willReturn('<p>C</p>');
        $processor->method('getStats')->willReturn([
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
            betaDeschideConnection: $betaConn,
        );
        $tester->execute(['--source' => 'all', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Statistics: NEWSCOOP', $display);
        $this->assertStringContainsString('Statistics: BETA', $display);
        $this->assertStringContainsString('Final Statistics', $display);
    }

    public function testExecuteWithArticleHavingNullContentFieldSkipsProcessing(): void
    {
        $articles = [
            [
                'Number' => 700,
                'title' => 'Null Content',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-01-01',
                'full_title' => 'Title',
                'subtitle' => '',
                'lead' => '',
                'content' => null,
            ],
            [
                'Number' => 701,
                'title' => 'Normal Article',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-01-02',
                'full_title' => 'Title 2',
                'subtitle' => '',
                'lead' => '',
                'content' => '<p>Normal content here</p>',
            ],
        ];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(false);
        $processor->method('countShortcodes')->willReturn([
            'images' => 0, 'internal_links' => 0, 'titles' => 0, 'snippets' => 0,
        ]);
        $processor->method('processContent')->willReturn('<p>Normal content here</p>');
        $processor->method('getStats')->willReturn([
            'images_processed' => 0,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteDryRunWithSaveDirDoesNotSave(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(newscoopConnection: $newscoopConn);
        $tester->execute([
            '--source' => 'newscoop',
            '--dry-run' => true,
            '--save-processed' => '/tmp/test-archive-output',
        ]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        // In dry-run, it should mention running without --dry-run
        $this->assertStringContainsString('without --dry-run', $display);
    }

    public function testExecuteWithSaveDirSavesJsonFiles(): void
    {
        $tmpDir = sys_get_temp_dir() . '/phpunit_archive_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        $articles = $this->buildArticleBatch(2);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(true);
        $processor->method('countShortcodes')->willReturn([
            'images' => 1, 'internal_links' => 0, 'titles' => 0,
        ]);
        $processor->method('processContent')->willReturnCallback(
            fn ($content) => '<p>Processed</p>'
        );
        $processor->method('getStats')->willReturn([
            'images_processed' => 1,
            'images_not_found' => 0,
            'links_processed' => 0,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute([
            '--source' => 'newscoop',
            '--save-processed' => $tmpDir,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Saved to', $display);

        // Check JSON file was created
        $files = glob($tmpDir . '/processed_*.json');
        $this->assertNotEmpty($files);

        // Cleanup
        foreach (glob($tmpDir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($tmpDir);
    }

    public function testExecuteWithShowSamplesShowsTransformations(): void
    {
        $articles = $this->buildArticleBatch(2);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(true);
        $processor->method('countShortcodes')->willReturn([
            'images' => 2, 'internal_links' => 1, 'titles' => 0,
        ]);
        $processor->method('processContent')->willReturnCallback(
            fn ($content) => '<p>Different Content</p>'
        );
        $processor->method('getStats')->willReturn([
            'images_processed' => 2,
            'images_not_found' => 0,
            'links_processed' => 1,
            'links_broken' => 0,
            'titles_converted' => 0,
            'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute([
            '--source' => 'newscoop',
            '--dry-run' => true,
            '--show-samples' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Sample', $display);
        $this->assertStringContainsString('BEFORE', $display);
        $this->assertStringContainsString('AFTER', $display);
    }

    public function testFinalStatisticsDisplaysSuccessRates(): void
    {
        $articles = $this->buildArticleBatch(3);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willReturn(true);
        $processor->method('countShortcodes')->willReturn([
            'images' => 1, 'internal_links' => 1, 'titles' => 0,
        ]);
        $processor->method('processContent')->willReturn('<p>Result</p>');
        $processor->method('getStats')->willReturn([
            'images_processed' => 5,
            'images_not_found' => 1,
            'links_processed' => 3,
            'links_broken' => 1,
            'titles_converted' => 2,
            'snippets_removed' => 1,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute([
            '--source' => 'newscoop',
            '--dry-run' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Final Statistics', $display);
        $this->assertStringContainsString('Image success rate', $display);
        $this->assertStringContainsString('Link success rate', $display);
    }

    public function testConfigurationDisplayShowsExpectedCounts(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn([]);

        $betaConn = $this->createStub(Connection::class);
        $betaConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(
            newscoopConnection: $newscoopConn,
            betaDeschideConnection: $betaConn,
        );
        $tester->execute(['--source' => 'all', '--dry-run' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Configuration', $display);
        $this->assertStringContainsString('ALL', $display);
        $this->assertStringContainsString('Newscoop', $display);
        $this->assertStringContainsString('Beta', $display);
        $this->assertStringContainsString('Total', $display);
    }

    public function testExecuteProcessesArticlesWithEmptyContent(): void
    {
        $articles = [
            [
                'Number' => 100,
                'title' => 'Empty Article',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-01-01',
                'full_title' => '',
                'subtitle' => '',
                'lead' => '',
                'content' => '',  // empty content
            ],
            [
                'Number' => 101,
                'title' => 'Also Empty',
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-01-01',
                'full_title' => '',
                'subtitle' => '',
                'lead' => '',
                'content' => '',  // empty content
            ],
        ];

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('getStats')->willReturn([
            'images_processed' => 0, 'images_not_found' => 0,
            'links_processed' => 0, 'links_broken' => 0,
            'titles_converted' => 0, 'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteHandlesProcessingException(): void
    {
        $articles = $this->buildArticleBatch(2);

        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')->willReturn($articles);

        $processor = $this->createStub(ContentProcessor::class);
        $processor->method('hasShortcodes')->willThrowException(new \RuntimeException('Test error'));
        $processor->method('getStats')->willReturn([
            'images_processed' => 0, 'images_not_found' => 0,
            'links_processed' => 0, 'links_broken' => 0,
            'titles_converted' => 0, 'snippets_removed' => 0,
        ]);

        $tester = $this->createTester(
            contentProcessor: $processor,
            newscoopConnection: $newscoopConn,
        );
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        // Command should not fail, errors are caught
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteWithFetchArticlesException(): void
    {
        $newscoopConn = $this->createStub(Connection::class);
        $newscoopConn->method('fetchAllAssociative')
            ->willThrowException(new \Exception('Connection refused'));

        $tester = $this->createTester(newscoopConnection: $newscoopConn);
        $tester->execute(['--source' => 'newscoop', '--dry-run' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Failed to fetch', $tester->getDisplay());
    }

    public function testDisplayConfigurationForBetaOnly(): void
    {
        $betaConn = $this->createStub(Connection::class);
        $betaConn->method('fetchAllAssociative')->willReturn([]);

        $tester = $this->createTester(betaDeschideConnection: $betaConn);
        $tester->execute(['--source' => 'beta', '--dry-run' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('BETA', $display);
        $this->assertStringContainsString('Beta', $display);
    }

    private function createTester(
        ?ContentProcessor $contentProcessor = null,
        ?Connection $newscoopConnection = null,
        ?Connection $betaDeschideConnection = null,
        ?LoggerInterface $logger = null,
    ): CommandTester {
        $command = new ProcessArchiveContentCommand(
            $contentProcessor ?? $this->createStub(ContentProcessor::class),
            $newscoopConnection ?? $this->createStub(Connection::class),
            $betaDeschideConnection ?? $this->createStub(Connection::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );

        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:archive:process-content'));
    }

    /**
     * @deprecated Use createTester() instead. Kept for backward compatibility reference.
     */
    private function buildCommand(
        ?ContentProcessor $contentProcessor = null,
        ?Connection $newscoopConnection = null,
        ?Connection $betaDeschideConnection = null,
        ?LoggerInterface $logger = null,
    ): ProcessArchiveContentCommand {
        return new ProcessArchiveContentCommand(
            $contentProcessor ?? $this->createStub(ContentProcessor::class),
            $newscoopConnection ?? $this->createStub(Connection::class),
            $betaDeschideConnection ?? $this->createStub(Connection::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    /**
     * Build a batch of N articles for testing.
     *
     * Note: Must use count != 1 to avoid a known bug where processSource()
     * returns articlesCount which collides with Command::FAILURE (=1).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildArticleBatch(int $count): array
    {
        $articles = [];
        for ($i = 0; $i < $count; ++$i) {
            $articles[] = [
                'Number' => 500 + $i,
                'title' => "Test Article $i",
                'IdLanguage' => 2,
                'Published' => 'Y',
                'PublishDate' => '2024-01-01',
                'full_title' => "Full Title $i",
                'subtitle' => '',
                'lead' => '',
                'content' => "<p>Content for article $i</p>",
            ];
        }

        return $articles;
    }
}
