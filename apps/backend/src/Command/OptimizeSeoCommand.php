<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\OptimizeSeoMessage;
use App\MessageHandler\OptimizeSeoHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seo:optimize',
    description: 'Generate SEO metadata (metaTitle, metaDescription, tags) for an article using Gemini AI',
)]
final class OptimizeSeoCommand extends Command
{
    public function __construct(
        private readonly OptimizeSeoHandler $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('articleId', InputArgument::REQUIRED, 'Article ID to optimize')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing metaTitle/metaDescription')
            ->addOption('no-tags', null, InputOption::VALUE_NONE, 'Skip tag suggestions')
            ->addOption('no-meta', null, InputOption::VALUE_NONE, 'Skip metaTitle/metaDescription generation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $articleId = (int) $input->getArgument('articleId');
        $force = $input->getOption('force');
        $noTags = $input->getOption('no-tags');
        $noMeta = $input->getOption('no-meta');

        if ($noTags && $noMeta) {
            $io->error('Cannot use both --no-tags and --no-meta — nothing to do.');

            return Command::FAILURE;
        }

        $io->title(\sprintf('SEO Optimization for Article #%d', $articleId));

        $message = new OptimizeSeoMessage(
            articleId: $articleId,
            generateMeta: !$noMeta,
            suggestTags: !$noTags,
            force: $force,
        );

        $io->text('Calling Gemini CLI...');

        $result = $this->handler->handle($message);

        if ($result === null) {
            $io->warning('SEO optimization was skipped. Check logs for details (article not found, insufficient content, or already optimized).');

            return Command::FAILURE;
        }

        // Display results
        $io->section('Results');

        if ($result['metaTitle'] !== null) {
            $io->text(\sprintf(
                '<info>metaTitle</info> (%d chars): %s',
                mb_strlen($result['metaTitle']),
                $result['metaTitle']
            ));
        } else {
            $io->text('<comment>metaTitle</comment>: not generated (already set or --no-meta)');
        }

        if ($result['metaDescription'] !== null) {
            $io->text(\sprintf(
                '<info>metaDescription</info> (%d chars): %s',
                mb_strlen($result['metaDescription']),
                $result['metaDescription']
            ));
        } else {
            $io->text('<comment>metaDescription</comment>: not generated (already set or --no-meta)');
        }

        if ($result['tagsAdded'] !== []) {
            $io->text(\sprintf('<info>New tags created</info>: %s', implode(', ', $result['tagsAdded'])));
        }

        if ($result['tagsExisting'] !== []) {
            $io->text(\sprintf('<info>Existing tags linked</info>: %s', implode(', ', $result['tagsExisting'])));
        }

        if ($result['tagsAdded'] === [] && $result['tagsExisting'] === []) {
            $io->text('<comment>Tags</comment>: none suggested or --no-tags');
        }

        $io->success('SEO optimization completed.');

        return Command::SUCCESS;
    }
}
