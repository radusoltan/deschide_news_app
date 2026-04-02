<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ShortLink;
use App\Repository\ShortLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for ShortLinkRepository.
 */
class ShortLinkRepositoryTest extends KernelTestCase
{
    private ShortLinkRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(ShortLinkRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findByCode
    // =====================================================================

    public function testFindByCodeReturnsShortLink(): void
    {
        $code = 'test' . uniqid();
        $link = $this->createShortLink($code);

        $found = $this->repository->findByCode($code);

        $this->assertNotNull($found);
        $this->assertSame($code, $found->getCode());
    }

    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $found = $this->repository->findByCode('nonexistent' . uniqid());

        $this->assertNull($found);
    }

    // =====================================================================
    // codeExists
    // =====================================================================

    public function testCodeExistsReturnsTrueWhenExists(): void
    {
        $code = 'exists' . uniqid();
        $this->createShortLink($code);

        $exists = $this->repository->codeExists($code);

        $this->assertTrue($exists);
    }

    public function testCodeExistsReturnsFalseWhenNotExists(): void
    {
        $exists = $this->repository->codeExists('nope' . uniqid());

        $this->assertFalse($exists);
    }

    // =====================================================================
    // findTopLinks
    // =====================================================================

    public function testFindTopLinksReturnsArray(): void
    {
        $this->createShortLink('top' . uniqid(), 100);

        $results = $this->repository->findTopLinks(10);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function testFindTopLinksRespectsLimit(): void
    {
        $results = $this->repository->findTopLinks(2);

        $this->assertLessThanOrEqual(2, count($results));
    }

    // =====================================================================
    // findRecent
    // =====================================================================

    public function testFindRecentReturnsArray(): void
    {
        $this->createShortLink('recent' . uniqid());

        $results = $this->repository->findRecent(10);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    // =====================================================================
    // findByArticle
    // =====================================================================

    public function testFindByArticleReturnsNullWhenNoArticleLinked(): void
    {
        $found = $this->repository->findByArticle(99999999);

        $this->assertNull($found);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function createShortLink(string $code, int $clickCount = 0): ShortLink
    {
        $link = new ShortLink();
        $link->setCode($code);
        $link->setOriginalUrl('https://example.com/article/' . $code);
        $link->setTitle('Test Link ' . $code);
        $link->setClickCount($clickCount);
        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }
}
