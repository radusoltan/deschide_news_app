<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Image;
use App\Entity\Thumbnail;
use App\Entity\ThumbnailProfile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use RuntimeException;

class ImageFixtures extends Fixture implements DependentFixtureInterface
{
    private const IMAGE_COUNT = 40;

    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/images';

    private const THUMBNAIL_DIR = __DIR__ . '/../../public/uploads/thumbnails';

    // Color palette for varied backgrounds
    private const COLORS = [
        '4a5568', '3b82f6', '10b981', 'f59e0b', 'ef4444',
        '8b5cf6', '06b6d4', 'ec4899', '6366f1', '14b8a6',
    ];

    private const DIMENSIONS = [
        ['width' => 1920, 'height' => 1080],
        ['width' => 1600, 'height' => 900],
        ['width' => 1280, 'height' => 720],
        ['width' => 1920, 'height' => 600],
        ['width' => 800, 'height' => 600],
    ];

    public function getDependencies(): array
    {
        return [
            ThumbnailProfileFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Ensure upload directories exist
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0o755, true);
        }
        if (!is_dir(self::THUMBNAIL_DIR)) {
            mkdir(self::THUMBNAIL_DIR, 0o755, true);
        }

        $fakerRo = Factory::create('ro_RO');
        $fakerEn = Factory::create('en_US');
        $fakerRu = Factory::create('ru_RU');

        // Get all thumbnail profiles (must match ThumbnailProfileFixtures)
        $thumbnailProfiles = [];
        $profileNames = [
            'article_thumbnail',
            'article_card',
            'article_square',
            'article_hero',
        ];
        foreach ($profileNames as $profileName) {
            $thumbnailProfiles[] = $this->getReference('thumbnail_profile_' . $profileName, ThumbnailProfile::class);
        }

        for ($i = 1; $i <= self::IMAGE_COUNT; ++$i) {
            // Select random dimensions and color
            $dimension = self::DIMENSIONS[($i - 1) % \count(self::DIMENSIONS)];
            $color = self::COLORS[($i - 1) % \count(self::COLORS)];

            // Download original image from dummyimage.com
            $imageData = $this->downloadDummyImage(
                $dimension['width'],
                $dimension['height'],
                $color,
                'Article+Image+' . $i
            );

            // Create Image entity
            $image = new Image();
            $image->setFilename($imageData['filename']);
            $image->setOriginalFilename('article_image_' . $i . '.png');
            $image->setPath('images/' . $imageData['filename']);
            $image->setMimeType('image/png');
            $image->setSize($imageData['size']);
            $image->setWidth($dimension['width']);
            $image->setHeight($dimension['height']);

            // Translatable fields (80% have alt, 60% caption, 40% description)
            if ($i % 10 <= 7) {
                $image->setAlt($fakerRo->sentence(3));
                $image->setTranslatableLocale('ro');
                $manager->persist($image);
                $manager->flush();

                $image->setAlt($fakerEn->sentence(3));
                $image->setTranslatableLocale('en');
                $manager->persist($image);
                $manager->flush();

                $image->setAlt($fakerRu->sentence(3));
                $image->setTranslatableLocale('ru');
                $manager->persist($image);
                $manager->flush();

                $manager->refresh($image);
                $image->setTranslatableLocale('ro');
            }

            if ($i % 10 <= 5) {
                $image->setCaption($fakerRo->sentence(6));
                $image->setTranslatableLocale('ro');
                $manager->persist($image);
                $manager->flush();

                $image->setCaption($fakerEn->sentence(6));
                $image->setTranslatableLocale('en');
                $manager->persist($image);
                $manager->flush();

                $image->setCaption($fakerRu->sentence(6));
                $image->setTranslatableLocale('ru');
                $manager->persist($image);
                $manager->flush();

                $manager->refresh($image);
                $image->setTranslatableLocale('ro');
            }

            if ($i % 10 <= 3) {
                $image->setDescription($fakerRo->sentence(10));
                $image->setTranslatableLocale('ro');
                $manager->persist($image);
                $manager->flush();

                $image->setDescription($fakerEn->sentence(10));
                $image->setTranslatableLocale('en');
                $manager->persist($image);
                $manager->flush();

                $image->setDescription($fakerRu->sentence(10));
                $image->setTranslatableLocale('ru');
                $manager->persist($image);
                $manager->flush();

                $manager->refresh($image);
                $image->setTranslatableLocale('ro');
            }

            // Image author (30%)
            if ($i % 10 <= 2) {
                $image->setImageAuthor($fakerRo->name());
            }

            $manager->persist($image);
            $manager->flush();

            // Generate thumbnails for all profiles
            foreach ($thumbnailProfiles as $profile) {
                $thumbnailData = $this->downloadThumbnail(
                    $profile,
                    $color,
                    'Thumbnail+' . $profile->getName()
                );

                $thumbnail = new Thumbnail();
                $thumbnail->setImage($image);
                $thumbnail->setProfile($profile);
                $thumbnail->setFilename($thumbnailData['filename']);
                $thumbnail->setPath('thumbnails/' . $thumbnailData['filename']);
                $thumbnail->setWidth($profile->getWidth());
                $thumbnail->setHeight($profile->getHeight());
                $thumbnail->setSize($thumbnailData['size']);

                $manager->persist($thumbnail);
            }

            $manager->flush();

            // Add reference for ArticleImageFixtures
            $this->addReference('image_' . $i, $image);

            if ($i % 10 === 0) {
                echo "  Generated $i/" . self::IMAGE_COUNT . " images with thumbnails...\n";
            }
        }

        echo '✅ Created ' . self::IMAGE_COUNT . ' images with ' . (self::IMAGE_COUNT * \count($thumbnailProfiles)) . " thumbnails\n";
    }

    private function downloadDummyImage(int $width, int $height, string $color, string $text): array
    {
        $url = \sprintf(
            'https://dummyimage.com/%dx%d/%s/ffffff&text=%s',
            $width,
            $height,
            $color,
            urlencode($text)
        );

        $filename = \sprintf('image_%s_%dx%d_%s.png', uniqid(), $width, $height, substr($color, 0, 6));
        $destinationPath = self::UPLOAD_DIR . '/' . $filename;

        $imageData = @file_get_contents($url);

        if ($imageData === false) {
            throw new RuntimeException("Failed to download image from $url");
        }

        file_put_contents($destinationPath, $imageData);

        return [
            'filename' => $filename,
            'size' => filesize($destinationPath),
        ];
    }

    private function downloadThumbnail(ThumbnailProfile $profile, string $color, string $text): array
    {
        $url = \sprintf(
            'https://dummyimage.com/%dx%d/%s/ffffff&text=%s',
            $profile->getWidth(),
            $profile->getHeight(),
            $color,
            urlencode($text)
        );

        $filename = \sprintf('thumb_%s_%s_%s.png', $profile->getName(), uniqid(), substr($color, 0, 6));
        $destinationPath = self::THUMBNAIL_DIR . '/' . $filename;

        $imageData = @file_get_contents($url);

        if ($imageData === false) {
            throw new RuntimeException("Failed to download thumbnail from $url");
        }

        file_put_contents($destinationPath, $imageData);

        return [
            'filename' => $filename,
            'size' => filesize($destinationPath),
        ];
    }
}
