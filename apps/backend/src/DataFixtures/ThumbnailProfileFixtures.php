<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ThumbnailProfile;
use App\Enum\ThumbnailCategory;
use App\Enum\ThumbnailMode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ThumbnailProfileFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $profiles = [
            // Article Card Thumbnails (for lists, grids)
            [
                'name' => 'article_card',
                'displayName' => 'Article Card',
                'description' => 'Card thumbnail 3:2 ratio for article lists',
                'width' => 640,
                'height' => 427,
                'aspectRatio' => '3:2',
                'mode' => ThumbnailMode::COVER,
                'quality' => 85,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],
            [
                'name' => 'article_card_small',
                'displayName' => 'Article Card Small',
                'description' => 'Small card thumbnail 3:2 ratio',
                'width' => 400,
                'height' => 267,
                'aspectRatio' => '3:2',
                'mode' => ThumbnailMode::COVER,
                'quality' => 85,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],

            // Article Hero/Banner Images
            [
                'name' => 'article_hero',
                'displayName' => 'Article Hero',
                'description' => 'Hero banner 8:3 ratio for article headers',
                'width' => 1600,
                'height' => 600,
                'aspectRatio' => '8:3',
                'mode' => ThumbnailMode::COVER,
                'quality' => 90,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],
            [
                'name' => 'article_hero_mobile',
                'displayName' => 'Article Hero Mobile',
                'description' => 'Hero banner for mobile devices',
                'width' => 768,
                'height' => 432,
                'aspectRatio' => '16:9',
                'mode' => ThumbnailMode::COVER,
                'quality' => 85,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],

            // Article Wide Formats
            [
                'name' => 'article_wide',
                'displayName' => 'Article Wide',
                'description' => 'Wide format 16:9 ratio for featured articles',
                'width' => 1920,
                'height' => 1080,
                'aspectRatio' => '16:9',
                'mode' => ThumbnailMode::COVER,
                'quality' => 90,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],
            [
                'name' => 'article_wide_medium',
                'displayName' => 'Article Wide Medium',
                'description' => 'Medium wide format 16:9 ratio',
                'width' => 1280,
                'height' => 720,
                'aspectRatio' => '16:9',
                'mode' => ThumbnailMode::COVER,
                'quality' => 85,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],

            // Square/Portrait formats
            [
                'name' => 'article_square',
                'displayName' => 'Article Square',
                'description' => 'Square 1:1 ratio for social media',
                'width' => 800,
                'height' => 800,
                'aspectRatio' => '1:1',
                'mode' => ThumbnailMode::COVER,
                'quality' => 85,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],
            [
                'name' => 'article_portrait',
                'displayName' => 'Article Portrait',
                'description' => 'Portrait 4:5 ratio for Instagram',
                'width' => 640,
                'height' => 800,
                'aspectRatio' => '4:5',
                'mode' => ThumbnailMode::COVER,
                'quality' => 85,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],

            // Thumbnail/Preview sizes
            [
                'name' => 'article_thumbnail',
                'displayName' => 'Article Thumbnail',
                'description' => 'Small thumbnail 16:9 ratio',
                'width' => 320,
                'height' => 180,
                'aspectRatio' => '16:9',
                'mode' => ThumbnailMode::COVER,
                'quality' => 80,
                'category' => ThumbnailCategory::ARTICLE,
                'isActive' => true,
            ],

            // Open Graph / Social Media
            [
                'name' => 'og_image',
                'displayName' => 'Open Graph Image',
                'description' => 'Open Graph 1.91:1 ratio for social sharing',
                'width' => 1200,
                'height' => 630,
                'aspectRatio' => '1.91:1',
                'mode' => ThumbnailMode::COVER,
                'quality' => 90,
                'category' => ThumbnailCategory::SOCIAL,
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
