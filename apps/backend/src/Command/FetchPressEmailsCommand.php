<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\PressEmailParser;
use App\Service\ZohoMailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-press-emails',
    description: 'Fetch press release emails from Zoho Mail and create draft articles',
)]
class FetchPressEmailsCommand extends Command
{
    /** @var array<string, string> sender domain → allowed */
    private const WHITELIST_DOMAINS = [
        'ipn.md',
        'gov.md',
        'moldpres.md',
        'presidency.md',
        'parlament.md',
        'bnm.md',
        'pnru.md',
    ];

    public function __construct(
        private readonly ZohoMailService $zohoMail,
        private readonly PressEmailParser $parser,
        private readonly EntityManagerInterface $em,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max emails to process', '20')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse emails but do not create articles')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Press Email Fetcher');

        if ($dryRun) {
            $io->note('DRY RUN — no articles will be created');
        }

        // Step 1: List emails
        $io->section('[1/4] Fetching email list from Zoho Mail');
        try {
            $emails = $this->zohoMail->listEmails($limit);
        } catch (\Throwable $e) {
            $io->error('Failed to fetch emails: ' . $e->getMessage());
            $this->logger->error('Press fetcher: Zoho API error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        $io->text(sprintf('Found %d emails in inbox', count($emails)));

        // Step 2: Filter whitelisted senders
        $io->section('[2/4] Filtering whitelisted press sources');
        $filtered = array_filter($emails, fn(array $email) => $this->isWhitelisted($email['fromAddress']));
        $io->text(sprintf('%d emails from whitelisted sources', count($filtered)));

        if (empty($filtered)) {
            $io->success('No new press emails to process.');
            return Command::SUCCESS;
        }

        // Step 3: Process each email
        $io->section('[3/4] Processing emails');
        $created = 0;
        $skipped = 0;
        $errors = 0;
        $processedIds = [];

        // Pre-load category map: slug → Category entity
        $categoryMap = [];
        foreach ($this->categoryRepository->findAll() as $cat) {
            $categoryMap[$cat->getSlug()] = $cat;
        }

        foreach ($filtered as $email) {
            $messageId = $email['messageId'];
            $subject = $email['subject'];
            $from = $email['fromAddress'];

            $io->text(sprintf('  → [%s] %s', $from, mb_substr($subject, 0, 70)));

            // Deduplication check
            $existing = $this->articleRepository->findOneBy(['sourceEmail' => $messageId]);
            if ($existing !== null) {
                $io->text('    ⏭  Already processed (sourceEmail exists)');
                $skipped++;
                continue;
            }

            // Fetch full content
            try {
                $html = $this->zohoMail->getEmailContent($messageId, $email['folderId']);
            } catch (\Throwable $e) {
                $io->text('    ❌ Failed to fetch content: ' . $e->getMessage());
                $errors++;
                continue;
            }

            if (empty($html)) {
                $io->text('    ⏭  Empty email body');
                $skipped++;
                continue;
            }

            // Parse
            $parsed = $this->parser->parse($html, $subject, $from);
            if ($parsed === null) {
                $io->text('    ⏭  Not a press release or too short');
                $skipped++;
                continue;
            }

            $io->text(sprintf('    ✓  Parsed: "%s" (%s, %d chars)',
                mb_substr($parsed['title'], 0, 50),
                $parsed['categorySlug'],
                $parsed['textLength'],
            ));

            if ($dryRun) {
                $io->text('    📋 DRY RUN — would create article');
                $created++;
                $processedIds[] = $messageId;
                continue;
            }

            // Create article
            try {
                $article = new Article();
                $article->setTitle($parsed['title']);
                $article->setLead($parsed['lead']);
                $article->setContent($parsed['content']);
                $article->setStatus(ArticleStatus::NEW);
                $article->setSourceEmail($messageId);
                $article->setTranslatableLocale('ro');

                $category = $categoryMap[$parsed['categorySlug']] ?? $categoryMap['societate'] ?? null;
                if ($category !== null) {
                    $article->setCategory($category);
                }

                $this->em->persist($article);
                $this->em->flush();

                $io->text(sprintf('    ✅ Article #%d created', $article->getId()));
                $created++;
                $processedIds[] = $messageId;
            } catch (\Throwable $e) {
                $io->text('    ❌ Failed to create article: ' . $e->getMessage());
                $this->logger->error('Press fetcher: article creation failed', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        // Step 4: Mark processed emails as read
        $io->section('[4/4] Marking processed emails as read');
        if (!empty($processedIds) && !$dryRun) {
            try {
                $this->zohoMail->markAsRead($processedIds);
                $io->text(sprintf('Marked %d emails as read', count($processedIds)));
            } catch (\Throwable $e) {
                $io->warning('Failed to mark emails as read: ' . $e->getMessage());
            }
        }

        // Summary
        $io->newLine();
        $io->definitionList(
            ['Emails scanned' => count($filtered)],
            ['Articles created' => $created],
            ['Skipped' => $skipped],
            ['Errors' => $errors],
        );

        $this->logger->info('Press fetcher completed', [
            'scanned' => count($filtered),
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        if ($errors > 0) {
            $io->warning("Completed with {$errors} errors.");
            return Command::FAILURE;
        }

        $io->success("Done. {$created} articles created.");
        return Command::SUCCESS;
    }

    private function isWhitelisted(string $fromAddress): bool
    {
        foreach (self::WHITELIST_DOMAINS as $domain) {
            if (str_contains($fromAddress, $domain)) {
                return true;
            }
        }

        return false;
    }
}
