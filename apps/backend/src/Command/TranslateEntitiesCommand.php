<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\TranslatableEntityType;
use App\Message\TranslateEntityMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:translate:entities',
    description: 'Dispatch entity translation messages for categories or authors',
)]
final class TranslateEntitiesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'Entity type: category or author')
            ->addArgument('ids', InputArgument::REQUIRED, 'Comma-separated entity IDs')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force retranslation even if already translated')
            ->addOption('locales', 'l', InputOption::VALUE_OPTIONAL, 'Comma-separated locales (default: ru,en)', 'ru,en');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $typeStr = $input->getArgument('type');
        $entityType = TranslatableEntityType::tryFrom($typeStr);

        if ($entityType === null) {
            $io->error(\sprintf('Invalid entity type "%s". Use "category" or "author".', $typeStr));

            return Command::FAILURE;
        }

        $idsStr = $input->getArgument('ids');
        $ids = array_filter(array_map('intval', explode(',', $idsStr)));

        if ($ids === []) {
            $io->error('No valid IDs provided.');

            return Command::FAILURE;
        }

        $force = $input->getOption('force');
        $locales = array_filter(explode(',', $input->getOption('locales')));

        if ($locales === []) {
            $io->error('No valid locales provided.');

            return Command::FAILURE;
        }

        $io->title(\sprintf('Dispatching %s translation for %d entities', $entityType->value, \count($ids)));
        $io->table(['Option', 'Value'], [
            ['Entity type', $entityType->value],
            ['IDs', implode(', ', $ids)],
            ['Locales', implode(', ', $locales)],
            ['Force', $force ? 'yes' : 'no'],
        ]);

        $dispatched = 0;
        foreach ($ids as $id) {
            $this->bus->dispatch(new TranslateEntityMessage(
                entityType: $entityType,
                entityId: $id,
                locales: $locales,
                force: $force,
            ));

            $io->writeln(\sprintf('  Dispatched %s #%d', $entityType->value, $id));
            ++$dispatched;
        }

        $io->success(\sprintf('Dispatched %d translation messages to queue.', $dispatched));
        $io->note('Run "symfony console messenger:consume translations -vv" to process them.');

        return Command::SUCCESS;
    }
}
