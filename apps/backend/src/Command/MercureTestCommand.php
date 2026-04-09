<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsCommand(
    name: 'app:mercure:test',
    description: 'Test Mercure Hub connectivity by publishing a test update',
)]
class MercureTestCommand extends Command
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Mercure Hub Connectivity');

        try {
            $update = new Update(
                'test/mercure-bundle',
                json_encode([
                    'type' => 'test',
                    'message' => 'MercureBundle installed and functional!',
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ]),
            );

            $messageId = $this->hub->publish($update);

            $io->success(sprintf(
                'Update published successfully! Message ID: %s',
                $messageId,
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf(
                'Publish error: %s (%s)',
                $e->getMessage(),
                $e::class,
            ));

            return Command::FAILURE;
        }
    }
}
