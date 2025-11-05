<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ThumbnailProfile;
use App\Enum\ThumbnailCategory;
use App\Enum\ThumbnailMode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ThumbnailProfileFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['import', 'thumbnail'];
    }

    public function load(ObjectManager $manager): void
    {
        // Only 4 essential thumbnail profiles for optimal performance
        // These profiles match config/packages/images.yaml configuration
        $profiles = [
            // Profile 1: article_thumbnail (16:9)
            [
                'name' => 'article_thumbnail',
                'displayName' => 'Article Thumbnail',
                'description' => 'Small thumbnail 16:9 ratio for previews and lists',
                'width' => 320,
                'height' => 180,
                'aspectRatio' => '16:9',
                'mode' => ThumbnailMode::COVER,
                'quality' => 80,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],

            // Profile 2: article_card (3:2)
            [
                'name' => 'article_card',
                'displayName' => 'Article Card',
                'description' => 'Card thumbnail 3:2 ratio for article lists and grids',
                'width' => 640,
                'height' => 427,
                'aspectRatio' => '3:2',
                'mode' => ThumbnailMode::COVER,
                'quality' => 85,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],

            // Profile 3: article_square (1:1)
            [
                'name' => 'article_square',
                'displayName' => 'Article Square',
                'description' => 'Square 1:1 ratio for social media sharing',
                'width' => 800,
                'height' => 800,
                'aspectRatio' => '1:1',
                'mode' => ThumbnailMode::COVER,
                'quality' => 85,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],

            // Profile 4: article_hero (8:3)
            [
                'name' => 'article_hero',
                'displayName' => 'Article Hero',
                'description' => 'Hero banner 8:3 ratio for article headers and featured content',
                'width' => 1600,
                'height' => 600,
                'aspectRatio' => '8:3',
                'mode' => ThumbnailMode::COVER,
                'quality' => 90,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],
        ];

        foreach ($profiles as $profileData) {
            $profile = new ThumbnailProfile();
            $profile->setName($profileData['name'])
                ->setDisplayName($profileData['displayName'])
                ->setDescription($profileData['description'])
                ->setWidth($profileData['width'])
                ->setHeight($profileData['height'])
                ->setAspectRatio($profileData['aspectRatio'])
                ->setMode($profileData['mode'])
                ->setQuality($profileData['quality'])
                ->setCategory($profileData['category'])
                ->setIsActive($profileData['isActive'])
                ->setTranslatableLocale('ro'); // Default locale

            $manager->persist($profile);

            // Add reference for other fixtures if needed
            $this->addReference('thumbnail_profile_' . $profileData['name'], $profile);
        }

        $manager->flush();
    }
}
