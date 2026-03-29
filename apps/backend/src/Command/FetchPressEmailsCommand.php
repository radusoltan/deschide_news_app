<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PressRelease;
use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Repository\PressReleaseRepository;
use App\Service\NotificationService;
use App\Service\PressEmailParser;
use App\Service\ZohoMailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-press-emails',
    description: 'Fetch press release emails from Zoho Mail and queue them for editorial review',
)]
class FetchPressEmailsCommand extends Command
{
    private const WHITELIST_DOMAINS = [
        'ipn.md',
        'gov.md',
        'moldpres.md',
        'presidency.md',
        'parlament.md',
        'bnm.md',
        'pnru.md',
    ];

    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly ZohoMailService $zohoMail,
        private readonly PressEmailParser $parser,
        private readonly EntityManagerInterface $em,
        private readonly PressReleaseRepository $pressReleaseRepository,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max emails to process', '20')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse emails but do not queue them')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Press Email Fetcher');

        if ($dryRun) {
            $io->note('DRY RUN — no press releases will be queued');
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

        // Step 3: Process each email → PressRelease (pending)
        $io->section('[3/4] Processing emails');
        $queued = 0;
        $skipped = 0;
        $errors = 0;
        $processedIds = [];

        foreach ($filtered as $email) {
            $messageId = $email['messageId'];
            $subject = $email['subject'];
            $from = $email['fromAddress'];

            $io->text(sprintf('  → [%s] %s', $from, mb_substr($subject, 0, 70)));

            // Deduplication: check PressRelease table
            $existing = $this->pressReleaseRepository->findBySourceEmailId($messageId);
            if ($existing !== null) {
                $io->text('    ⏭  Already queued');
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
                $io->text('    📋 DRY RUN — would queue');
                $queued++;
                $processedIds[] = $messageId;
                continue;
            }

            // Create PressRelease (pending editorial review)
            try {
                $pr = new PressRelease();
                $pr->setTitle($parsed['title']);
                $pr->setLead($parsed['lead']);
                $pr->setContent($parsed['content']);
                $pr->setSourceEmailId($messageId);
                $pr->setSenderAddress($from);
                $pr->setSenderName($parsed['sourceName']);
                $pr->setSourceUrl($parsed['sourceUrl']);
                $pr->setCategorySlug($parsed['categorySlug']);
                $pr->setEmailSubject($subject);
                $pr->setReceivedAt(new \DateTimeImmutable(
                    '@' . (int) (((int) $email['receivedTime']) / 1000)
                ));

                // Download image attachment if present
                if ($email['hasAttachment'] || $email['hasInline']) {
                    $this->downloadImageAttachment($pr, $messageId, $email['folderId'], $io);
                }

                $this->em->persist($pr);
                $this->em->flush();

                $io->text(sprintf('    ✅ PressRelease #%d queued%s',
                    $pr->getId(),
                    $pr->hasAttachment() ? ' (+ image: ' . $pr->getAttachmentFilename() . ')' : '',
                ));
                $queued++;
                $processedIds[] = $messageId;
            } catch (\Throwable $e) {
                $io->text('    ❌ Failed to queue: ' . $e->getMessage());
                $this->logger->error('Press fetcher: queue failed', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        // Step 4: Mark processed emails as read + notify editors
        $io->section('[4/4] Marking emails as read & notifying editors');
        if (!empty($processedIds) && !$dryRun) {
            try {
                $this->zohoMail->markAsRead($processedIds);
                $io->text(sprintf('Marked %d emails as read', count($processedIds)));
            } catch (\Throwable $e) {
                $io->warning('Failed to mark emails as read: ' . $e->getMessage());
            }

            // Notify editors about new press releases
            if ($queued > 0) {
                try {
                    $this->notificationService->notify(
                        type: NotificationType::PRESS_QUEUE_NEW,
                        title: sprintf('%d comunicate de presă noi în coadă', $queued),
                        message: 'Verifică coada de comunicate și aprobă articolele relevante.',
                        importance: NotificationImportance::MEDIUM,
                        actionUrl: '/admin/press-queue',
                    );
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to send press queue notification', ['error' => $e->getMessage()]);
                }
            }
        }

        // Summary
        $io->newLine();
        $io->definitionList(
            ['Emails scanned' => count($filtered)],
            ['Queued for review' => $queued],
            ['Skipped' => $skipped],
            ['Errors' => $errors],
        );

        $this->logger->info('Press fetcher completed', [
            'scanned' => count($filtered),
            'queued' => $queued,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        if ($errors > 0) {
            $io->warning("Completed with {$errors} errors.");
            return Command::FAILURE;
        }

        $io->success("Done. {$queued} press releases queued for editorial review.");
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

    private function downloadImageAttachment(PressRelease $pr, string $messageId, string $folderId, SymfonyStyle $io): void
    {
        try {
            $attachments = $this->zohoMail->getAttachments($messageId, $folderId);

            // Find first image attachment
            $imageAtt = null;
            foreach ($attachments as $att) {
                $ext = strtolower(pathinfo($att['attachmentName'], PATHINFO_EXTENSION));
                if (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                    $imageAtt = $att;
                    break;
                }
            }

            if ($imageAtt === null) {
                return;
            }

            // Download binary
            $binary = $this->zohoMail->downloadAttachment($messageId, $imageAtt['attachmentId'], $folderId);
            if (empty($binary)) {
                $io->text('    ⚠  Empty attachment download');
                return;
            }

            // Save to press-attachments directory
            $storageDir = $this->projectDir . '/var/press-attachments';
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $ext = strtolower(pathinfo($imageAtt['attachmentName'], PATHINFO_EXTENSION));
            $filename = sprintf('press_%s_%s.%s', $messageId, bin2hex(random_bytes(4)), $ext);
            $filepath = $storageDir . '/' . $filename;

            file_put_contents($filepath, $binary);

            // Detect MIME type
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($filepath);

            if (!in_array($mimeType, self::IMAGE_MIME_TYPES, true)) {
                unlink($filepath);
                $io->text('    ⚠  Attachment is not an image: ' . $mimeType);
                return;
            }

            $pr->setAttachmentFilename($imageAtt['attachmentName']);
            $pr->setAttachmentPath('var/press-attachments/' . $filename);
            $pr->setAttachmentMimeType($mimeType);
            $pr->setAttachmentSize(strlen($binary));

            $io->text(sprintf('    📎 Image: %s (%d KB)',
                $imageAtt['attachmentName'],
                (int) (strlen($binary) / 1024),
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('Press fetcher: attachment download failed', [
                'messageId' => $messageId,
                'error' => $e->getMessage(),
            ]);
            $io->text('    ⚠  Attachment download failed: ' . $e->getMessage());
        }
    }
}
