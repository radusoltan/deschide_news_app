<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\RecalculateTagCountsMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:tags:recalculate-counts',
    description: 'Recalculate usage counts for all tags',
)]
class RecalculateTagCountsCommand extends Command
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
                'tag-id',
                't',
                InputOption::VALUE_OPTIONAL,
                'Recalculate count for specific tag ID only'
            )
            ->addOption(
                'async',
                'a',
                InputOption::VALUE_NONE,
                'Execute asynchronously via message queue'
            )
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command recalculates usage counts for tags.

                    Recalculate all tags:
                    <info>php %command.full_name%</info>

                    Recalculate specific tag:
                    <info>php %command.full_name% --tag-id=5</info>

                    Execute asynchronously:
                    <info>php %command.full_name% --async</info>

                    This command ensures data consistency by counting actual article relationships
                    and updating the usageCount field accordingly.
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tagId = $input->getOption('tag-id') ? (int) $input->getOption('tag-id') : null;
        $async = (bool) $input->getOption('async');

        $io->title('Recalculate Tag Usage Counts');

        if ($tagId !== null) {
            $io->info(\sprintf('Recalculating usage count for tag ID: %d', $tagId));
        } else {
            $io->info('Recalculating usage counts for all tags...');
        }

        // Dispatch message
        $message = new RecalculateTagCountsMessage($tagId);
        $this->messageBus->dispatch($message);

        if ($async) {
            $io->success('Recalculation task dispatched to message queue. Check logs for results.');
        } else {
            $io->success('Recalculation task completed. Check logs for details.');
        }

        $io->comment('This command is useful after:');
        $io->listing([
            'Bulk data imports',
            'Manual database modifications',
            'Data integrity issues',
            'Migration from legacy system',
        ]);

        return Command::SUCCESS;
    }
}
