<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:slug-generation',
    description: 'Test slug generation for Romanian characters',
)]
class TestSlugGenerationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Slug Generation for Romanian Characters');

        $testCases = [
            'Știri Locale' => 'stiri-locale',
            'Șeful Țării' => 'seful-tarii',
            'Întâmplări din România' => 'intamplari-din-romania',
            'Știri despre educație' => 'stiri-despre-educatie',
            'Ăsta e un test' => 'asta-e-un-test',
        ];

        $io->section('Creating test categories with Romanian titles');

        try {
        $results = [];
        foreach ($testCases as $title => $expectedSlug) {
            // Create a test category (don't persist, just test slug generation)
            $category = new Category();
            $category->setTitle($title);

            // Force slug generation by flushing
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $actualSlug = $category->getSlug();
            $status = $actualSlug === $expectedSlug ? '✅' : '❌';

            $results[] = [
                'Title' => $title,
                'Expected' => $expectedSlug,
                'Actual' => $actualSlug,
                'Status' => $status,
            ];

            // Remove the test category
            $this->entityManager->remove($category);
            $this->entityManager->flush();
        }

        $io->table(
            ['Title', 'Expected Slug', 'Actual Slug', 'Status'],
            array_map(fn ($r) => [$r['Title'], $r['Expected'], $r['Actual'], $r['Status']], $results)
        );

        $allPassed = !\in_array('❌', array_column($results, 'Status'), true);

        if ($allPassed) {
            $io->success('All slug generation tests passed! Romanian characters are properly transliterated.');

            return Command::SUCCESS;
        }

        $io->error('Some slug generation tests failed! Check the results above.');

        return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
