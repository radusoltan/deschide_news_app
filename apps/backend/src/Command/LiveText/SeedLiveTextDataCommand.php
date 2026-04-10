<?php

declare(strict_types=1);

namespace App\Command\LiveText;

use App\Entity\Category;
use App\Entity\LiveText;
use App\Entity\LiveTextCollaborator;
use App\Entity\LiveTextPost;
use App\Entity\User;
use App\Enum\LiveTextStatus;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Faker\Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:livetext:seed',
    description: 'Seed test data for LiveText entities'
)]
class SeedLiveTextDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Seeding LiveText Test Data');

        try {
        // Get users
        $users = $this->entityManager->getRepository(User::class)->findAll();
        if (empty($users)) {
            $io->error('No users found in database. Please create users first.');

            return Command::FAILURE;
        }

        $author = $users[0];
        $io->info("Using author: {$author->getUsername()}");

        // Get categories
        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        $category = !empty($categories) ? $categories[0] : null;

        if ($category) {
            $io->info("Using category: {$category->getTitle()}");
        }

        $fakerRo = Factory::create('ro_RO');
        $fakerEn = Factory::create('en_US');
        $fakerRu = Factory::create('ru_RU');

        $statuses = [
            LiveTextStatus::DRAFT,
            LiveTextStatus::LIVE,
            LiveTextStatus::PAUSED,
            LiveTextStatus::ENDED,
        ];

        // Create 10 LiveTexts
        for ($i = 1; $i <= 10; ++$i) {
            $liveText = new LiveText();
            $liveText->setAuthor($author);

            if ($category) {
                $liveText->setCategory($category);
            }

            $status = $statuses[array_rand($statuses)];
            $liveText->setStatus($status);

            $startTime = new DateTime('-' . rand(1, 48) . ' hours');
            $liveText->setStartTime($startTime);

            if ($status === LiveTextStatus::ENDED) {
                $endTime = (clone $startTime)->modify('+' . rand(2, 24) . ' hours');
                $liveText->setEndTime($endTime);
            }

            // Set Romanian content
            $liveText->setLocale('ro');
            $liveText->setTitle("Live Text RO #$i: " . $fakerRo->sentence(4));
            $liveText->setDescription($fakerRo->paragraph(2));

            $this->entityManager->persist($liveText);
            $this->entityManager->flush();

            // Add English translation
            $liveText->setLocale('en');
            $liveText->setTitle("Live Text EN #$i: " . $fakerEn->sentence(4));
            $liveText->setDescription($fakerEn->paragraph(2));
            $this->entityManager->persist($liveText);
            $this->entityManager->flush();

            // Add Russian translation
            $liveText->setLocale('ru');
            $liveText->setTitle("Live Text RU #$i: " . $fakerRu->sentence(4));
            $liveText->setDescription($fakerRu->paragraph(2));
            $this->entityManager->persist($liveText);
            $this->entityManager->flush();

            // Reset to default locale
            $liveText->setLocale('ro');

            // Add collaborators
            if (\count($users) > 1) {
                $collaborator = new LiveTextCollaborator();
                $collaborator->setLiveText($liveText);
                $collaborator->setUser($users[1]);
                $collaborator->setRole('editor');
                $this->entityManager->persist($collaborator);
            }

            // Add posts
            $postCount = match ($status) {
                LiveTextStatus::DRAFT => rand(1, 3),
                LiveTextStatus::LIVE => rand(5, 10),
                LiveTextStatus::PAUSED => rand(3, 6),
                LiveTextStatus::ENDED => rand(10, 15),
            };

            for ($p = 0; $p < $postCount; ++$p) {
                $post = new LiveTextPost();
                $post->setLiveText($liveText);
                $post->setAuthor($author);
                $post->setContent($fakerRo->paragraph(rand(2, 4)));
                $post->setContentHtml('<p>' . $fakerRo->paragraph(rand(2, 4)) . '</p>');
                $post->setIsKeyPoint(rand(1, 100) <= 20);
                $post->setPosition($p);

                $publishedAt = (clone $startTime)->modify('+' . ($p * rand(5, 20)) . ' minutes');
                $post->setPublishedAt($publishedAt);

                $this->entityManager->persist($post);
            }

            $io->success("Created LiveText #$i ({$status->value}) with $postCount posts");
        }

        $this->entityManager->flush();

        $io->success('✅ Successfully seeded 10 LiveTexts with posts and collaborators!');

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
