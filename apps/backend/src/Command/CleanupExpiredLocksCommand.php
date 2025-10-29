<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ArticleLockRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-expired-locks',
    description: 'Remove expired article locks from the database'
)]
class CleanupExpiredLocksCommand extends Command
{
    public function __construct(
        private readonly ArticleLockRepository $lockRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Cleaning Up Expired Article Locks');

        try {
            $deletedCount = $this->lockRepository->deleteExpiredLocks();

            if ($deletedCount > 0) {
                $io->success(sprintf('Deleted %d expired lock(s)', $deletedCount));
            } else {
                $io->info('No expired locks found');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to cleanup locks: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
