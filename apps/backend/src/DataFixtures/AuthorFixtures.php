<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Author;
use App\Enum\AuthorStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AuthorFixtures extends Fixture
{
    private const AUTHOR_COUNT = 12;

    public function load(ObjectManager $manager): void
    {
        $fakerRo = Factory::create('ro_RO');
        $fakerEn = Factory::create('en_US');
        $fakerRu = Factory::create('ru_RU');

        for ($i = 0; $i < self::AUTHOR_COUNT; $i++) {
            $author = new Author();

            // Basic info (non-translatable)
            $firstName = $fakerRo->firstName();
            $lastName = $fakerRo->lastName();
            $author->setFirstName($firstName);
            $author->setLastName($lastName);
            $author->setEmail(strtolower($firstName . '.' . $lastName . '@deschide.local'));
            $author->setStatus(AuthorStatus::ACTIVE);
            $author->setIsActive(true);

            // Social media (50% of authors)
            if ($i % 2 === 0) {
                $author->setTwitter('@' . strtolower($firstName . $lastName));
                $author->setFacebook('https://facebook.com/' . strtolower($firstName . '.' . $lastName));
            }

            // Romanian bio (default locale)
            $author->setBio($fakerRo->paragraphs(2, true));
            $author->setTranslatableLocale('ro');
            $manager->persist($author);
            $manager->flush();

            // English bio
            $author->setBio($fakerEn->paragraphs(2, true));
            $author->setTranslatableLocale('en');
            $manager->persist($author);
            $manager->flush();

            // Russian bio
            $author->setBio($fakerRu->paragraphs(2, true));
            $author->setTranslatableLocale('ru');
            $manager->persist($author);
            $manager->flush();

            // Reset to default locale
            $manager->refresh($author);
            $author->setTranslatableLocale('ro');

            // Add reference for ArticleFixtures
            $this->addReference('author_' . $i, $author);
        }

        echo "✅ Created " . self::AUTHOR_COUNT . " authors with bio translations (ro/en/ru)\n";
    }
}
