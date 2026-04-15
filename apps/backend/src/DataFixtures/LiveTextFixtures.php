<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\LiveText;
use App\Entity\LiveTextCollaborator;
use App\Entity\LiveTextPost;
use App\Enum\LiveTextStatus;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class LiveTextFixtures extends Fixture implements DependentFixtureInterface
{
    private const LIVE_TEXT_COUNT = 10;

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            CategoryFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $fakerRo = Factory::create('ro_RO');
        $fakerEn = Factory::create('en_US');
        $fakerRu = Factory::create('ru_RU');

        // Get admin user for author
        $adminUser = $manager->getRepository(\App\Entity\User::class)->findOneBy(['username' => 'admin']);
        $editorUser = $manager->getRepository(\App\Entity\User::class)->findOneBy(['username' => 'editor']);
        $regularUser = $manager->getRepository(\App\Entity\User::class)->findOneBy(['username' => 'ai_asistent']);

        // Get some categories
        $categories = $manager->getRepository(\App\Entity\Category::class)->findAll();

        for ($i = 1; $i <= self::LIVE_TEXT_COUNT; ++$i) {
            $liveText = new LiveText();

            // Set author (alternating between admin and editor)
            $author = ($i % 2 === 0) ? $adminUser : $editorUser;
            $liveText->setAuthor($author);

            // Assign random category
            if (!empty($categories)) {
                $category = $categories[array_rand($categories)];
                $liveText->setCategory($category);
            }

            // Status distribution: 20% DRAFT, 30% LIVE, 20% PAUSED, 30% ENDED
            $statusRand = rand(1, 100);
            if ($statusRand <= 20) {
                $status = LiveTextStatus::DRAFT;
            } elseif ($statusRand <= 50) {
                $status = LiveTextStatus::LIVE;
            } elseif ($statusRand <= 70) {
                $status = LiveTextStatus::PAUSED;
            } else {
                $status = LiveTextStatus::ENDED;
            }
            $liveText->setStatus($status);

            // Set times
            $startTime = new DateTime('-' . rand(1, 48) . ' hours');
            $liveText->setStartTime($startTime);

            if ($status === LiveTextStatus::ENDED) {
                $endTime = (clone $startTime)->modify('+' . rand(2, 24) . ' hours');
                $liveText->setEndTime($endTime);
            }

            // Set locale and translatable fields for Romanian
            $liveText->setLocale('ro');
            $titleRo = $fakerRo->sentence(rand(4, 8));
            $liveText->setTitle($titleRo);
            $liveText->setDescription($fakerRo->paragraph(rand(2, 4)));

            $manager->persist($liveText);

            // Create Romanian translations
            $manager->flush();

            // Create English translation
            $liveText->setLocale('en');
            $liveText->setTitle($fakerEn->sentence(rand(4, 8)));
            $liveText->setDescription($fakerEn->paragraph(rand(2, 4)));
            $manager->persist($liveText);
            $manager->flush();

            // Create Russian translation
            $liveText->setLocale('ru');
            $liveText->setTitle($fakerRu->sentence(rand(4, 8)));
            $liveText->setDescription($fakerRu->paragraph(rand(2, 4)));
            $manager->persist($liveText);
            $manager->flush();

            // Reset to default locale
            $liveText->setLocale('ro');

            // Add collaborators (1-2 collaborators per live text)
            $collaboratorCount = rand(1, 2);
            $collaboratorUsers = [$editorUser, $regularUser];

            for ($c = 0; $c < $collaboratorCount; ++$c) {
                $collaboratorUser = $collaboratorUsers[$c % \count($collaboratorUsers)];

                // Don't add the same user twice or the author as collaborator
                if ($collaboratorUser !== $author) {
                    $collaborator = new LiveTextCollaborator();
                    $collaborator->setLiveText($liveText);
                    $collaborator->setUser($collaboratorUser);
                    $collaborator->setRole($c === 0 ? 'editor' : 'contributor');

                    $manager->persist($collaborator);
                }
            }

            // Add posts (3-15 posts per live text, depending on status)
            $postCount = match ($status) {
                LiveTextStatus::DRAFT => rand(1, 3),
                LiveTextStatus::LIVE => rand(5, 15),
                LiveTextStatus::PAUSED => rand(3, 8),
                LiveTextStatus::ENDED => rand(10, 20),
            };

            for ($p = 0; $p < $postCount; ++$p) {
                $post = new LiveTextPost();
                $post->setLiveText($liveText);

                // Alternate author between liveText author and collaborators
                $postAuthor = ($p % 3 === 0) ? $author : ($p % 3 === 1 ? $editorUser : $regularUser);
                $post->setAuthor($postAuthor);

                $post->setContent($fakerRo->paragraph(rand(2, 5)));
                $post->setContentHtml('<p>' . $fakerRo->paragraph(rand(2, 5)) . '</p>');

                // 20% chance of being a key point
                $post->setIsKeyPoint(rand(1, 100) <= 20);

                $post->setPosition($p);

                // Published time: spread across the live text duration
                $publishedAt = (clone $startTime)->modify('+' . ($p * rand(5, 30)) . ' minutes');
                $post->setPublishedAt($publishedAt);

                $manager->persist($post);
            }

            // Store reference for potential future use
            $this->addReference('live_text_' . $i, $liveText);
        }

        $manager->flush();
    }
}
