<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Maps CSV author strings from the legacy deschide.md export to Author entities.
 * Handles the inconsistent naming (slugs vs display names, trailing spaces, case variations).
 */
class LegacyAuthorMapper
{
    /**
     * Canonical mapping: normalized lowercase name → author slug in DB.
     * Multiple CSV variants map to the same canonical slug.
     */
    private const CANONICAL_MAP = [
        'deschide-md' => 'deschide-md',
        'deschide.md' => 'deschide-md',
        'editor' => 'deschide-md',
        'cristina-vlah' => 'cristina-vlah',
        'cristina vlah' => 'cristina-vlah',
        'igor-liubec' => 'igor-liubec',
        'igor liubec' => 'igor-liubec',
        'limbas irina' => 'limbas-irina',
        'irina limbas' => 'limbas-irina',
        'iulian-chifu' => 'iulian-chifu',
        'iulian chifu' => 'iulian-chifu',
        'tudor ionita' => 'tudor-ionita',
        'tudor-ionita' => 'tudor-ionita',
        'alexandru-tanase' => 'alexandru-tanase',
        'alexandru tanase' => 'alexandru-tanase',
        'alla tofan' => 'alla-tofan',
        'monica-scutaru' => 'monica-scutaru',
        'monica scutaru' => 'monica-scutaru',
        'vitalie-vovc' => 'vitalie-vovc',
        'vitalie vovc' => 'vitalie-vovc',
        'mihai-isac' => 'mihai-isac',
        'mihai isac' => 'mihai-isac',
        'dumitru-crudu' => 'dumitru-crudu',
        'dumitru crudu' => 'dumitru-crudu',
        'anatol-taranu' => 'anatol-taranu',
        'anatol taranu' => 'anatol-taranu',
        'cristian-hrituc' => 'cristian-hrituc',
        'cristian hrituc' => 'cristian-hrituc',
        'iuliana-gorea-costin' => 'iuliana-gorea-costin',
        'iuliana gorea-costin' => 'iuliana-gorea-costin',
        'yoram elron' => 'yoram-elron',
        'valentin' => 'valentin',
        'constantin uzdris' => 'constantin-uzdris',
    ];

    /** @var array<string, Author> */
    private array $cache = [];

    /** @var string[] Slugs of unknown authors encountered during import */
    private array $unknownAuthors = [];

    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Map a CSV author string to an Author entity.
     */
    public function map(string $csvAuthor): ?Author
    {
        $normalized = $this->normalize($csvAuthor);

        if (empty($normalized)) {
            return null;
        }

        // Check cache
        if (isset($this->cache[$normalized])) {
            return $this->cache[$normalized];
        }

        // Lookup canonical slug
        $canonicalSlug = self::CANONICAL_MAP[$normalized] ?? null;

        if ($canonicalSlug !== null) {
            $author = $this->findBySlug($canonicalSlug);
            if ($author !== null) {
                $this->cache[$normalized] = $author;

                return $author;
            }
            // Canonical slug not in DB — create the author
            $author = $this->createAuthor($csvAuthor, $canonicalSlug);
            $this->cache[$normalized] = $author;

            return $author;
        }

        // Not in canonical map — try to find by slug generated from the CSV name
        $generatedSlug = $this->generateSlug($csvAuthor);
        $author = $this->findBySlug($generatedSlug);
        if ($author !== null) {
            $this->cache[$normalized] = $author;

            return $author;
        }

        // Author doesn't exist — create new
        $this->unknownAuthors[] = $csvAuthor;
        $this->logger->info('Creating new author from CSV: {name} (slug: {slug})', [
            'name' => $csvAuthor,
            'slug' => $generatedSlug,
        ]);

        $author = $this->createAuthor($csvAuthor, $generatedSlug);
        $this->cache[$normalized] = $author;

        return $author;
    }

    private function normalize(string $name): string
    {
        // Trim whitespace (including trailing spaces found in CSV)
        $name = trim($name);
        // Normalize diacritics for comparison
        $name = str_replace(
            ["\u{015F}", "\u{0163}", "\u{015E}", "\u{0162}"],
            ["\u{0219}", "\u{021B}", "\u{0218}", "\u{021A}"],
            $name,
        );
        // Lowercase for matching
        $name = mb_strtolower($name);
        // Collapse multiple spaces
        $name = (string) preg_replace('/\s+/', ' ', $name);

        return $name;
    }

    private function findBySlug(string $slug): ?Author
    {
        return $this->authorRepository->findOneBy(['slug' => $slug]);
    }

    private function createAuthor(string $displayName, string $slug): Author
    {
        $displayName = trim($displayName);
        $parts = explode(' ', $displayName, 2);

        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        // If the display name looks like a slug, try to make it prettier
        if (str_contains($firstName, '-') && empty($lastName)) {
            $humanized = str_replace('-', ' ', $displayName);
            $parts = explode(' ', $humanized, 2);
            $firstName = ucfirst($parts[0]);
            $lastName = isset($parts[1]) ? ucfirst($parts[1]) : '';
        }

        $author = new Author();
        $author->setFirstName($firstName);
        $author->setLastName($lastName);
        $author->setSlug($slug);
        $author->setEmail($slug . '@imported.deschide.md');

        $this->entityManager->persist($author);
        $this->entityManager->flush();

        return $author;
    }

    private function generateSlug(string $name): string
    {
        $name = trim($name);

        // If already a slug format (contains hyphens, no spaces, lowercase)
        if (preg_match('/^[a-z0-9-]+$/', $name)) {
            return $name;
        }

        return $this->slugger->slug($name)->lower()->toString();
    }

    /**
     * Get list of unknown authors encountered during import.
     */
    public function getUnknownAuthors(): array
    {
        return array_unique($this->unknownAuthors);
    }

    /**
     * Clear the internal cache (call after EntityManager::clear()).
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
