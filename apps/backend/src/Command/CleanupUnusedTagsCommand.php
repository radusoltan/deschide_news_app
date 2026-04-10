<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\CleanupUnusedTagsMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:tags:cleanup',
    description: 'Cleanup unused tags older than specified days',
)]
class CleanupUnusedTagsCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Remove tags unused for this many days',
                '30'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be deleted without actually deleting'
            )
            ->addOption(
                'async',
                'a',
                InputOption::VALUE_NONE,
                'Execute asynchronously via message queue'
            )
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command cleans up unused tags.

                    <info>php %command.full_name%</info>

                    By default, removes tags that haven't been used in 30 days:
                    <info>php %command.full_name%</info>

                    Specify custom number of days:
                    <info>php %command.full_name% --days=60</info>

                    Preview what would be deleted (dry run):
                    <info>php %command.full_name% --dry-run</info>

                    Execute asynchronously via message queue:
                    <info>php %command.full_name% --async</info>

                    Combine options:
                    <info>php %command.full_name% --days=90 --dry-run</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = (bool) $input->getOption('dry-run');
        $async = (bool) $input->getOption('async');

        $io->title('Cleanup Unused Tags');

        if ($dryRun) {
            $io->note('Running in DRY RUN mode - no tags will be deleted');
        }

        $io->info(\sprintf(
            'Cleaning up tags unused for %d days or more...',
            $days
        ));

        try {
            // Dispatch message to message bus
            $message = new CleanupUnusedTagsMessage($days, $dryRun);
            $this->messageBus->dispatch($message);

            if ($async) {
                $io->success('Cleanup task dispatched to message queue. Check logs for results.');
            } else {
                // When not async, the handler is executed synchronously
                $io->success('Cleanup task completed. Check logs for details.');
            }

            $io->comment('Tip: Use --dry-run to preview what would be deleted');
            $io->comment('Tip: Use --async to run in background via message queue');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
