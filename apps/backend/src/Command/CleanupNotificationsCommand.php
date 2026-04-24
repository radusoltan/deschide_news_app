<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AdminNotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:notifications:cleanup',
    description: 'Delete old admin notifications based on retention policy',
)]
final class CleanupNotificationsCommand extends Command
{
    public function __construct(
        private readonly AdminNotificationRepository $repository,
        #[Autowire('%app.notification_retention_days%')]
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $cutoff = new \DateTimeImmutable(\sprintf('-%d days', $this->retentionDays));
            $deleted = $this->repository->deleteOlderThan($cutoff);

            $io->success(\sprintf(
                'Deleted %d notification(s) older than %d days (before %s).',
                $deleted,
                $this->retentionDays,
                $cutoff->format('Y-m-d'),
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
