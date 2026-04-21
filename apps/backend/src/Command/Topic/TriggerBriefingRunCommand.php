<?php

declare(strict_types=1);

namespace App\Command\Topic;

use App\Enum\BriefingCadence;
use App\Message\Topic\TriggerTopicBriefingRunMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:briefing:trigger',
    description: 'Manually dispatch TriggerTopicBriefingRunMessage for the given cadence (hourly|daily|weekly).',
)]
final class TriggerBriefingRunCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('cadence', InputArgument::OPTIONAL, 'hourly | daily | weekly', 'hourly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cadence = BriefingCadence::from((string) $input->getArgument('cadence'));
        $this->bus->dispatch(new TriggerTopicBriefingRunMessage($cadence));
        $output->writeln(sprintf('Dispatched TriggerTopicBriefingRunMessage(%s)', $cadence->value));

        return Command::SUCCESS;
    }
}
