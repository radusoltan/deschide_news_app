<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Repository\AppSettingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Write an AppSetting value by key (Sprint 56 T56.02.1).
 *
 * Wraps {@see AppSettingRepository::set()} with type coercion so an operator
 * at 3 AM does not have to remember the canonical string forms
 * (`'true'` / `'false'`, JSON quoting, integer conversion). Bool input
 * accepts anything {@see FILTER_VALIDATE_BOOLEAN} recognises and normalises
 * to the literal string `'true'` / `'false'` that
 * {@see AppSettingRepository::getBool()} expects downstream.
 *
 * Primary operator use case: emergency halt flip during incident response
 * (see docs/Deploy_Runbook.md).
 */
#[AsCommand(
    name: 'app:settings:set',
    description: 'Set an AppSetting value with type-aware coercion',
)]
final class AppSettingsSetCommand extends Command
{
    private const SUPPORTED_TYPES = ['string', 'bool', 'int', 'json'];

    public function __construct(
        private readonly AppSettingRepository $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'The AppSetting key (e.g. editorial.emergency_halt)')
            ->addArgument('value', InputArgument::REQUIRED, 'The value to store (as string; coerced by --type)')
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'Type hint for input validation: ' . implode('|', self::SUPPORTED_TYPES),
                'string',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $key */
        $key = $input->getArgument('key');
        /** @var string $value */
        $value = $input->getArgument('value');
        /** @var string $type */
        $type = $input->getOption('type');

        if (!\in_array($type, self::SUPPORTED_TYPES, true)) {
            $io->error(sprintf(
                "Unsupported --type '%s'. Supported: %s.",
                $type,
                implode(', ', self::SUPPORTED_TYPES),
            ));

            return Command::FAILURE;
        }

        $normalized = $this->coerce($type, $value, $io);
        if ($normalized === null) {
            // Coercion already rendered the error.
            return Command::FAILURE;
        }

        $this->settings->set($key, $normalized);

        $io->success(sprintf("Set '%s' = '%s' (type: %s)", $key, $normalized, $type));

        return Command::SUCCESS;
    }

    /**
     * Returns the canonical string to persist, or null on coercion failure
     * (caller treats null as FAILURE).
     */
    private function coerce(string $type, string $value, SymfonyStyle $io): ?string
    {
        switch ($type) {
            case 'string':
                return $value;

            case 'bool':
                // filter_var(..., FILTER_VALIDATE_BOOLEAN, NULL_ON_FAILURE)
                // returns null for unrecognised input so we can error loudly
                // instead of silently storing `'false'` for a typo.
                $coerced = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
                if ($coerced === null) {
                    $io->error(sprintf(
                        "Cannot coerce '%s' to bool. Expected one of: true, false, 1, 0, yes, no, on, off.",
                        $value,
                    ));

                    return null;
                }

                return $coerced ? 'true' : 'false';

            case 'int':
                if (!preg_match('/^-?\d+$/', $value)) {
                    $io->error(sprintf("Cannot coerce '%s' to int. Expected an integer literal.", $value));

                    return null;
                }

                return (string) (int) $value;

            case 'json':
                try {
                    json_decode($value, associative: true, flags: \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $io->error(sprintf('Invalid JSON: %s', $e->getMessage()));

                    return null;
                }

                return $value;
        }

        return null; // unreachable (validated upstream)
    }
}
