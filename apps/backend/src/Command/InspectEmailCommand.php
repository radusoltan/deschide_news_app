<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ZohoMailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:inspect-email', description: 'Inspect a specific email by scanning inbox for keyword match')]
class InspectEmailCommand extends Command
{
    public function __construct(private readonly ZohoMailService $zohoMail)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('keyword', InputArgument::REQUIRED, 'Keyword to match in subject');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keyword = strtolower($input->getArgument('keyword'));

        $allEmails = [];
        for ($offset = 0; $offset < 200; $offset += 50) {
            $batch = $this->zohoMail->listEmails(50, $offset);
            if (empty($batch)) break;
            $allEmails = array_merge($allEmails, $batch);
        }

        foreach ($allEmails as $e) {
            if (!str_contains(strtolower($e['subject'] . ' ' . $e['fromAddress']), $keyword)) {
                continue;
            }

            $io->section('Found: ' . $e['subject']);
            $io->text('From: ' . $e['fromAddress']);
            $io->text('MessageId: ' . $e['messageId']);
            $io->text('HasAttachment: ' . ($e['hasAttachment'] ? 'YES' : 'no'));

            // Get content preview
            $content = $this->zohoMail->getEmailContent($e['messageId'], $e['folderId']);
            $text = strip_tags($content);
            $io->text('Body length: ' . mb_strlen($text) . ' chars');
            $io->text('Body preview: ' . mb_substr(trim($text), 0, 300));

            // Get attachments
            if ($e['hasAttachment']) {
                $attachments = $this->zohoMail->getAttachments($e['messageId'], $e['folderId']);
                $io->text('Attachments: ' . count($attachments));
                foreach ($attachments as $att) {
                    $io->text(sprintf('  → %s (%d bytes)', $att['attachmentName'], $att['attachmentSize']));
                }
            }
        }

        return Command::SUCCESS;
    }
}
