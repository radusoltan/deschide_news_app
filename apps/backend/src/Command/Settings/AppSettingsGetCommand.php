<?php

declare(strict_types=1);

namespace App\Command\Settings;

use App\Repository\AppSettingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read an AppSetting value by key (Sprint 56 T56.02.1).
 *
 * Thin wrapper over {@see AppSettingRepository::get()} used by operators
 * during incident response — see docs/Deploy_Runbook.md "Emergency halt
 * procedure" for the canonical operational flow.
 *
 * Exit codes:
 *   - 0 (SUCCESS) when the key exists; value printed verbatim to stdout
 *     (one line, no quotes) so the output is pipe/shell friendly.
 *   - 1 (FAILURE) when the key is missing; operator sees a `<comment>`
 *     line explaining the gap.
 */
#[AsCommand(
    name: 'app:settings:get',
    description: 'Get an AppSetting value by key',
)]
final class AppSettingsGetCommand extends Command
{
    public function __construct(
        private readonly AppSettingRepository $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'The AppSetting key (e.g. agent.emergency_halt)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $key */
        $key = $input->getArgument('key');

        $value = $this->settings->get($key);
        if ($value === null) {
            $output->writeln(sprintf("<comment>Setting '%s' is not set.</comment>", $key));

            return Command::FAILURE;
        }

        // Pipe-friendly: one line, verbatim value, no quoting.
        $output->writeln($value);

        return Command::SUCCESS;
    }
}
