<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;

class SourceAuthorResolver
{
    /**
     * Map source slugs to display names for auto-created authors.
     */
    private const SOURCE_NAME_MAP = [
        'scrape:gov_md' => ['Gov.md', 'Guvernul Republicii Moldova'],
        'scrape:moldpres' => ['Moldpres', 'Agenția de Știri'],
        'scrape:ipn' => ['IPN', 'Info-Prim Neo'],
        'scrape:presidency' => ['Președinția', 'Republicii Moldova'],
        'scrape:parlament' => ['Parlamentul', 'Republicii Moldova'],
        'scrape:bnm' => ['BNM', 'Banca Națională'],
    ];

    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function resolve(string $sourceName): Author
    {
        // Try to find by email domain extracted from sourceName
        $domain = $this->extractDomain($sourceName);
        if ($domain !== null) {
            $existing = $this->authorRepository->findByEmailDomain($domain);
            if ($existing !== null) {
                return $existing;
            }
        }

        // Try to find by slug derived from sourceName
        $slug = $this->toSlug($sourceName);
        $existing = $this->authorRepository->findOneBy(['slug' => $slug]);
        if ($existing !== null) {
            return $existing;
        }

        // Create new author
        $names = self::SOURCE_NAME_MAP[$sourceName] ?? $this->parseSourceName($sourceName);
        $author = new Author();
        $author->setFirstName($names[0]);
        $author->setLastName($names[1]);
        $author->setSlug($slug);
        $author->setEmail($slug . '@source.deschide.md');
        if ($domain !== null) {
            $author->setEmailDomain($domain);
        }

        $this->em->persist($author);
        $this->em->flush();

        return $author;
    }

    private function extractDomain(string $sourceName): ?string
    {
        // "scrape:gov_md" → "gov.md"
        if (str_starts_with($sourceName, 'scrape:')) {
            $slug = substr($sourceName, 7);

            return str_replace('_', '.', $slug);
        }

        return null;
    }

    private function toSlug(string $sourceName): string
    {
        $slug = mb_strtolower($sourceName);
        $slug = str_replace([':', '_', '.', ' '], '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug) ?? $slug;
        $slug = preg_replace('/-+/', '-', trim($slug, '-')) ?? $slug;

        return mb_substr($slug, 0, 128);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseSourceName(string $sourceName): array
    {
        // "scrape:some_source" → ["Some Source", ""]
        $name = str_replace(['scrape:', 'email:', '_'], ['', '', ' '], $sourceName);
        $name = ucwords(trim($name));

        return [$name, ''];
    }
}
