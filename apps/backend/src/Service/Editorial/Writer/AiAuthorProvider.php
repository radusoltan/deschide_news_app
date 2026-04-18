<?php

declare(strict_types=1);

namespace App\Service\Editorial\Writer;

use App\Entity\Author;
use App\Enum\AuthorStatus;
use App\Enum\AuthorType;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves (or lazily creates) the `deschide-ai` author row used by writers
 * emitting AI-generated Articles (Sprint 55 T55.3, T55.4).
 *
 * Rationale: writers persist Articles via the standard ORM flow, which
 * requires a valid Author. Rather than seed the row via a fixture (which
 * couples dev reset to S55 code), we resolve-or-create it on first writer
 * invocation and cache the entity for the remainder of the request.
 *
 * The row is typed as {@see AuthorType::AGENCY} so editorial dashboards
 * and the public site can distinguish machine-authored output at a glance.
 */
class AiAuthorProvider
{
    private const SLUG = 'deschide-ai';
    private const FIRST_NAME = 'Deschide';
    private const LAST_NAME = 'AI';
    private const EMAIL = 'ai@deschide.md';

    private ?Author $cached = null;

    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function getOrCreate(): Author
    {
        if ($this->cached !== null && $this->cached->getId() !== null) {
            return $this->cached;
        }

        $existing = $this->authorRepository->findOneBy(['slug' => self::SLUG]);
        if ($existing !== null) {
            $this->cached = $existing;

            return $existing;
        }

        $author = new Author();
        $author->setFirstName(self::FIRST_NAME);
        $author->setLastName(self::LAST_NAME);
        $author->setSlug(self::SLUG);
        $author->setEmail(self::EMAIL);
        $author->setType(AuthorType::AGENCY);
        $author->setStatus(AuthorStatus::ACTIVE);

        $this->em->persist($author);
        // Flush here so callers that persist an Article with this Author as a
        // ManyToMany relation do not race the INSERT order.
        $this->em->flush();

        $this->cached = $author;

        return $author;
    }
}
