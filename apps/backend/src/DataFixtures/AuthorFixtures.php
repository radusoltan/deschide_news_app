<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Author;
use App\Enum\AuthorStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Creates authors for Moldovan press agencies and the Deschide.md editorial team.
 * Slugs must match LegacyAuthorMapper::CANONICAL_MAP values for CSV import compatibility.
 */
class AuthorFixtures extends Fixture
{
    private const AUTHORS = [
        // Main editorial account (maps to CSV "deschide-md", "Deschide.MD", "Editor")
        ['firstName' => 'Deschide', 'lastName' => 'MD', 'slug' => 'deschide-md', 'email' => 'redactia@deschide.md'],
        // Moldovan press agencies
        ['firstName' => 'Moldpres', 'lastName' => '', 'slug' => 'moldpres', 'email' => 'moldpres@imported.deschide.md'],
        ['firstName' => 'IPN', 'lastName' => '', 'slug' => 'ipn', 'email' => 'ipn@imported.deschide.md'],
        ['firstName' => 'Gov.md', 'lastName' => '', 'slug' => 'gov-md', 'email' => 'gov@imported.deschide.md'],
        ['firstName' => 'Infotag', 'lastName' => '', 'slug' => 'infotag', 'email' => 'infotag@imported.deschide.md'],
        // Key journalists from CSV
        ['firstName' => 'Cristina', 'lastName' => 'Vlah', 'slug' => 'cristina-vlah', 'email' => 'cristina.vlah@deschide.md'],
        ['firstName' => 'Igor', 'lastName' => 'Liubec', 'slug' => 'igor-liubec', 'email' => 'igor.liubec@deschide.md'],
        ['firstName' => 'Limbas', 'lastName' => 'Irina', 'slug' => 'limbas-irina', 'email' => 'irina.limbas@deschide.md'],
        ['firstName' => 'Tudor', 'lastName' => 'Ioniță', 'slug' => 'tudor-ionita', 'email' => 'tudor.ionita@deschide.md'],
        ['firstName' => 'Alla', 'lastName' => 'Tofan', 'slug' => 'alla-tofan', 'email' => 'alla.tofan@deschide.md'],
        ['firstName' => 'Iulian', 'lastName' => 'Chifu', 'slug' => 'iulian-chifu', 'email' => 'iulian.chifu@deschide.md'],
        ['firstName' => 'Alexandru', 'lastName' => 'Tănase', 'slug' => 'alexandru-tanase', 'email' => 'alexandru.tanase@deschide.md'],
        ['firstName' => 'Monica', 'lastName' => 'Scutaru', 'slug' => 'monica-scutaru', 'email' => 'monica.scutaru@deschide.md'],
        ['firstName' => 'Vitalie', 'lastName' => 'Vovc', 'slug' => 'vitalie-vovc', 'email' => 'vitalie.vovc@deschide.md'],
        ['firstName' => 'Mihai', 'lastName' => 'Isac', 'slug' => 'mihai-isac', 'email' => 'mihai.isac@deschide.md'],
        ['firstName' => 'Dumitru', 'lastName' => 'Crudu', 'slug' => 'dumitru-crudu', 'email' => 'dumitru.crudu@deschide.md'],
        ['firstName' => 'Anatol', 'lastName' => 'Țăranu', 'slug' => 'anatol-taranu', 'email' => 'anatol.taranu@deschide.md'],
        ['firstName' => 'Cristian', 'lastName' => 'Hrituc', 'slug' => 'cristian-hrituc', 'email' => 'cristian.hrituc@deschide.md'],
        ['firstName' => 'Iuliana', 'lastName' => 'Gorea-Costin', 'slug' => 'iuliana-gorea-costin', 'email' => 'iuliana.gorea-costin@deschide.md'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::AUTHORS as $index => $data) {
            $author = new Author();
            $author->setFirstName($data['firstName']);
            $author->setLastName($data['lastName']);
            $author->setSlug($data['slug']);
            $author->setEmail($data['email']);
            $author->setStatus(AuthorStatus::ACTIVE);
            $author->setIsActive(true);

            $manager->persist($author);

            $this->addReference('author_' . $index, $author);
        }

        $manager->flush();

        echo '✅ Created ' . \count(self::AUTHORS) . " authors (agencies + journalists)\n";
    }
}
