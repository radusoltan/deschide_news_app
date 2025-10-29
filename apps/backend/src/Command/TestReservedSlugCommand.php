<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:test-reserved-slug',
    description: 'Test reserved slug validation'
)]
class TestReservedSlugCommand extends Command
{
    public function __construct(
        private readonly ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Reserved Slug Validation');

        // Test 1: Valid slug (should pass)
        $io->section('Test 1: Valid slug (should PASS)');
        $category1 = new Category();
        $category1->setTitle('Politică');
        $category1->setSlug('politica'); // Valid slug

        $errors1 = $this->validator->validate($category1);
        if (count($errors1) === 0) {
            $io->success('✓ Valid slug "politica" passed validation');
        } else {
            $io->error('✗ Valid slug "politica" failed validation (unexpected)');
            foreach ($errors1 as $error) {
                $io->writeln('  - ' . $error->getMessage());
            }
        }

        // Test 2: Reserved slug "all" (should fail)
        $io->section('Test 2: Reserved slug "all" (should FAIL)');
        $category2 = new Category();
        $category2->setTitle('All');
        $category2->setSlug('all'); // Reserved slug

        $errors2 = $this->validator->validate($category2);
        if (count($errors2) > 0) {
            $io->success('✓ Reserved slug "all" correctly rejected');
            foreach ($errors2 as $error) {
                $io->writeln('  Error: ' . $error->getMessage());
            }
        } else {
            $io->error('✗ Reserved slug "all" passed validation (should have failed)');
        }

        // Test 3: Reserved slug "admin" (should fail)
        $io->section('Test 3: Reserved slug "admin" (should FAIL)');
        $category3 = new Category();
        $category3->setTitle('Admin');
        $category3->setSlug('admin'); // Reserved slug

        $errors3 = $this->validator->validate($category3);
        if (count($errors3) > 0) {
            $io->success('✓ Reserved slug "admin" correctly rejected');
            foreach ($errors3 as $error) {
                $io->writeln('  Error: ' . $error->getMessage());
            }
        } else {
            $io->error('✗ Reserved slug "admin" passed validation (should have failed)');
        }

        // Test 4: Reserved slug "search" (should fail)
        $io->section('Test 4: Reserved slug "search" (should FAIL)');
        $category4 = new Category();
        $category4->setTitle('Search');
        $category4->setSlug('search'); // Reserved slug

        $errors4 = $this->validator->validate($category4);
        if (count($errors4) > 0) {
            $io->success('✓ Reserved slug "search" correctly rejected');
            foreach ($errors4 as $error) {
                $io->writeln('  Error: ' . $error->getMessage());
            }
        } else {
            $io->error('✗ Reserved slug "search" passed validation (should have failed)');
        }

        // Test 5: Another valid slug (should pass)
        $io->section('Test 5: Valid slug "economia" (should PASS)');
        $category5 = new Category();
        $category5->setTitle('Economie');
        $category5->setSlug('economia'); // Valid slug

        $errors5 = $this->validator->validate($category5);
        if (count($errors5) === 0) {
            $io->success('✓ Valid slug "economia" passed validation');
        } else {
            $io->error('✗ Valid slug "economia" failed validation (unexpected)');
            foreach ($errors5 as $error) {
                $io->writeln('  - ' . $error->getMessage());
            }
        }

        // Summary
        $io->title('Test Summary');
        $totalTests = 5;
        $passedTests = 0;

        if (count($errors1) === 0) $passedTests++;  // Test 1
        if (count($errors2) > 0) $passedTests++;    // Test 2
        if (count($errors3) > 0) $passedTests++;    // Test 3
        if (count($errors4) > 0) $passedTests++;    // Test 4
        if (count($errors5) === 0) $passedTests++;  // Test 5

        $io->writeln(sprintf('Passed: %d/%d tests', $passedTests, $totalTests));

        if ($passedTests === $totalTests) {
            $io->success('All tests passed! Reserved slug validation is working correctly.');
            return Command::SUCCESS;
        } else {
            $io->error('Some tests failed. Please review the validation implementation.');
            return Command::FAILURE;
        }
    }
}
