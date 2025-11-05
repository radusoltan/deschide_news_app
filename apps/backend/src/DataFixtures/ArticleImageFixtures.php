<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ArticleImage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ArticleImageFixtures extends Fixture implements DependentFixtureInterface
{
    private const ARTICLE_COUNT = 80;

    private const IMAGE_COUNT = 40;

    public function getDependencies(): array
    {
        return [
            ArticleFixtures::class,
            ImageFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $totalAssociations = 0;

        for ($i = 1; $i <= self::ARTICLE_COUNT; ++$i) {
            $article = $this->getReference('article_' . $i, \App\Entity\Article::class);

            // All articles have at least 1 image (featured)
            $imageCount = $this->getRandomImageCount();

            // Select random images (no duplicates within same article)
            $availableImages = range(1, self::IMAGE_COUNT);
            shuffle($availableImages);
            $selectedImages = \array_slice($availableImages, 0, $imageCount);

            foreach ($selectedImages as $position => $imageIndex) {
                $image = $this->getReference('image_' . $imageIndex, \App\Entity\Image::class);

                $articleImage = new ArticleImage(
                    $article,
                    $image,
                    $position,
                    $position === 0  // First image is always featured
                );

                $manager->persist($articleImage);
                ++$totalAssociations;
            }

            if ($i % 20 === 0) {
                $manager->flush();
                echo "  Associated images with $i/" . self::ARTICLE_COUNT . " articles...\n";
            }
        }

        $manager->flush();

        echo "✅ Created $totalAssociations article-image associations (all articles have at least 1 featured image)\n";
    }

    private function getRandomImageCount(): int
    {
        $rand = rand(1, 100);

        if ($rand <= 40) {
            return 1;
        }  // 40% - 1 image only
        if ($rand <= 70) {
            return 2;
        }  // 30% - 2 images
        if ($rand <= 90) {
            return 3;
        }  // 20% - 3 images
        if ($rand <= 97) {
            return 4;
        }  // 7% - 4 images

        return 5;                    // 3% - 5 images
    }
}
