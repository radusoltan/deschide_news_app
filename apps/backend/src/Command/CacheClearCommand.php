<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Cache\CacheService;
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
        private readonly CacheService $performance,
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

        $pattern = match ($type) {
            'articles' => 'api:articles:*',
            'categories' => 'api:categories:*',
            'all' => '*',
            default => throw new InvalidArgumentException("Invalid type: {$type}. Use: articles, categories, or all"),
        };

        try {
            $deleted = $this->performance->deleteCachedPattern($pattern);

            $io->success("Deleted {$deleted} cache keys");

            $this->logger->info('Cache cleared', [
                'type' => $type,
                'deleted_count' => $deleted,
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('Cache clear failed: ' . $e->getMessage(), [
                'exception' => $e,
                'command' => $this->getName(),
            ]);
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
