<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ZohoMailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:scan-inbox', description: 'Scan Zoho inbox for emails matching keywords')]
class ScanInboxCommand extends Command
{
    public function __construct(private readonly ZohoMailService $zohoMail)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('keyword', 'k', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Keywords to search for in subject/sender');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keywords = $input->getOption('keyword');

        $io->title('Zoho Inbox Scanner');

        $allEmails = [];
        for ($offset = 0; $offset < 500; $offset += 50) {
            $batch = $this->zohoMail->listEmails(50, $offset);
            if (empty($batch)) {
                break;
            }
            $allEmails = array_merge($allEmails, $batch);
            $io->text(sprintf('Fetched %d emails (offset %d)', count($allEmails), $offset));
        }

        $io->text(sprintf('Total emails in inbox: %d', count($allEmails)));

        // List all unique senders
        $senders = [];
        foreach ($allEmails as $e) {
            $senders[$e['fromAddress']] = ($senders[$e['fromAddress']] ?? 0) + 1;
        }
        arsort($senders);
        $io->section('All senders');
        foreach ($senders as $sender => $count) {
            $io->text(sprintf('  [%d] %s', $count, $sender));
        }

        // Filter by keywords
        if (!empty($keywords)) {
            $io->section('Matching emails');
            $matched = 0;
            foreach ($allEmails as $e) {
                $haystack = strtolower($e['subject'] . ' ' . $e['fromAddress']);
                foreach ($keywords as $kw) {
                    if (str_contains($haystack, strtolower($kw))) {
                        $io->text(sprintf('[%s] %s | %s', $e['fromAddress'], $e['subject'], $e['receivedTime']));
                        $matched++;
                        break;
                    }
                }
            }
            $io->text(sprintf('Matched: %d emails', $matched));
        }

        return Command::SUCCESS;
    }
}
