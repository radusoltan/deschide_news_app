<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PerformanceService;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cache:clear',
    description: 'Clear application cache'
)]
class CacheClearCommand extends Command
{
    public function __construct(
        private readonly PerformanceService $performance,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Cache type: articles, categories, all', 'all')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getOption('type');
        $force = $input->getOption('force');

        $io->title("Clearing cache: {$type}");

        // Confirmation
        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Continue? (yes/no) ', false);

            if (!$helper->ask($input, $output, $question)) {
                $io->note('Operation cancelled');

                return Command::SUCCESS;
            }
        }

        $deleted = match ($type) {
            'articles' => $this->performance->deleteCachedPattern('api:articles:*'),
            'categories' => $this->performance->deleteCachedPattern('api:categories:*'),
            'all' => $this->performance->deleteCachedPattern('*'),
            default => throw new InvalidArgumentException("Invalid type: {$type}. Use: articles, categories, or all"),
        };

        $io->success("Deleted {$deleted} cache keys");

        $this->logger->info('Cache cleared', [
            'type' => $type,
            'deleted_count' => $deleted,
        ]);

        return Command::SUCCESS;
    }
}
