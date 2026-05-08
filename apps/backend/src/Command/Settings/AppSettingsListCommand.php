<?php

declare(strict_types=1);

namespace App\Command\Settings;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List AppSettings with optional prefix filter (Sprint 56 T56.02.1).
 *
 * Operational recon during incident response: shows the flag surface without
 * requiring a DB client. Long values (>80 chars, e.g. JSON blobs) are
 * truncated with an ellipsis so the table stays readable — use
 * `app:settings:get` to see the full value.
 */
#[AsCommand(
    name: 'app:settings:list',
    description: 'List all AppSettings, optionally filtered by key prefix',
)]
final class AppSettingsListCommand extends Command
{
    private const VALUE_TRUNCATE_AT = 80;

    public function __construct(
        private readonly AppSettingRepository $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'prefix',
                null,
                InputOption::VALUE_REQUIRED,
                'Filter keys by prefix (e.g. editorial. or agent.)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $prefix */
        $prefix = $input->getOption('prefix');

        /** @var list<AppSetting> $rows */
        $rows = $this->settings->findAll();

        if ($prefix !== null && $prefix !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (AppSetting $s): bool => str_starts_with($s->getKey(), $prefix),
            ));
        }

        usort($rows, static fn (AppSetting $a, AppSetting $b): int => strcmp($a->getKey(), $b->getKey()));

        $table = new Table($output);
        $table->setHeaders(['Key', 'Value']);
        foreach ($rows as $row) {
            $value = $row->getValue();
            if (mb_strlen($value) > self::VALUE_TRUNCATE_AT) {
                $value = mb_substr($value, 0, self::VALUE_TRUNCATE_AT - 3) . '...';
            }
            $table->addRow([$row->getKey(), $value]);
        }
        $table->render();

        $output->writeln(sprintf(
            '<comment>Total: %d setting(s)%s</comment>',
            \count($rows),
            $prefix !== null && $prefix !== '' ? sprintf(" (prefix='%s')", $prefix) : '',
        ));

        return Command::SUCCESS;
    }
}
