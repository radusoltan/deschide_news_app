<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Author;
use App\Enum\AuthorStatus;
use App\Enum\AuthorType;
use App\Repository\AuthorRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for AuthorRepository.
 *
 * Tests the findByEmailDomain() method with actual database queries.
 */
class AuthorRepositoryTest extends KernelTestCase
{
    private AuthorRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(AuthorRepository::class);
    }

    public function testFindByEmailDomainReturnsMatchingAuthor(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $author = new Author();
        $author->setFirstName('IPN');
        $author->setLastName('Info-Prim Neo');
        $author->setEmail('contact-findbyemail-test@ipn.md');
        $author->setType(AuthorType::AGENCY);
        $author->setEmailDomain('ipn-test-unique.md');
        $author->setIsActive(true);
        $author->setStatus(AuthorStatus::ACTIVE);

        $entityManager->persist($author);
        $entityManager->flush();

        // Test
        $result = $this->repository->findByEmailDomain('ipn-test-unique.md');

        $this->assertNotNull($result);
        $this->assertSame('IPN', $result->getFirstName());
        $this->assertSame('Info-Prim Neo', $result->getLastName());
        $this->assertSame(AuthorType::AGENCY, $result->getType());
        $this->assertSame('ipn-test-unique.md', $result->getEmailDomain());

        // Cleanup
        $entityManager->remove($author);
        $entityManager->flush();
    }

    public function testFindByEmailDomainReturnsNullForUnknownDomain(): void
    {
        $result = $this->repository->findByEmailDomain('nonexistent-domain-xyz-test.com');

        $this->assertNull($result);
    }

    public function testFindByEmailDomainIgnoresInactiveAuthors(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $author = new Author();
        $author->setFirstName('Inactive');
        $author->setLastName('Agency');
        $author->setEmail('inactive-findbyemail-test@agency.md');
        $author->setType(AuthorType::AGENCY);
        $author->setEmailDomain('inactive-agency-test.md');
        $author->setIsActive(false);
        $author->setStatus(AuthorStatus::INACTIVE);

        $entityManager->persist($author);
        $entityManager->flush();

        // Test - should return null since the author is inactive
        $result = $this->repository->findByEmailDomain('inactive-agency-test.md');

        $this->assertNull($result);

        // Cleanup
        $entityManager->remove($author);
        $entityManager->flush();
    }

    public function testFindByEmailDomainReturnsOnlyFirstMatch(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $author1 = new Author();
        $author1->setFirstName('First');
        $author1->setLastName('Match');
        $author1->setEmail('first-findbyemail-dup@gov.md');
        $author1->setType(AuthorType::PRESS_OFFICE);
        $author1->setEmailDomain('dup-domain-test.md');
        $author1->setIsActive(true);
        $author1->setStatus(AuthorStatus::ACTIVE);

        $author2 = new Author();
        $author2->setFirstName('Second');
        $author2->setLastName('Match');
        $author2->setEmail('second-findbyemail-dup@gov.md');
        $author2->setType(AuthorType::PRESS_OFFICE);
        $author2->setEmailDomain('dup-domain-test.md');
        $author2->setIsActive(true);
        $author2->setStatus(AuthorStatus::ACTIVE);

        $entityManager->persist($author1);
        $entityManager->persist($author2);
        $entityManager->flush();

        // Test - should return exactly one author
        $result = $this->repository->findByEmailDomain('dup-domain-test.md');

        $this->assertNotNull($result);
        $this->assertInstanceOf(Author::class, $result);

        // Cleanup
        $entityManager->remove($author1);
        $entityManager->remove($author2);
        $entityManager->flush();
    }
}
