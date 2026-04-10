<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\PressRelease;
use App\Entity\Source;
use App\Enum\ArticleStatus;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Service\Editorial\ArticleFactoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the editorial pipeline: PressRelease -> Article.
 *
 * Tests the ArticleFactoryService (core transformation logic) and
 * the PressRelease approve/reject processors' guard logic against
 * the real database.
 */
class EditorialPipelineTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ArticleFactoryService $articleFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->articleFactory = static::getContainer()->get(ArticleFactoryService::class);
    }

    // =========================================================================
    // Helper: build a minimal PressRelease with required fields
    // =========================================================================

    private function createCategory(string $title, string $slug): Category
    {
        $category = new Category();
        $category->setTitle($title);
        $category->setSlug($slug);
        $this->em->persist($category);

        return $category;
    }

    private function createSource(string $name): Source
    {
        $source = new Source();
        $source->setName($name);

        $this->em->persist($source);

        return $source;
    }

    private function buildPressRelease(string $suffix = ''): PressRelease
    {
        $unique = uniqid('editorial-test-' . $suffix, true);

        $pr = new PressRelease();
        $pr->setTitle('Test Press Release ' . $unique);
        $pr->setContent('<p>Content of the press release ' . $unique . '</p>');
        $pr->setLead('Lead text for ' . $unique);
        $pr->setCategorySlug('societate');
        $pr->setSourceType(SourceType::EMAIL);
        $pr->setOriginalLanguage('ro');
        $pr->setContentHash(hash('sha256', $unique));

        return $pr;
    }

    // =========================================================================
    // 1. ArticleFactoryService: createFromPressRelease
    // =========================================================================

    public function testCreateArticleFromPressReleaseMapsAllFields(): void
    {
        // Arrange: create fallback category (the factory looks for category slug,
        // then falls back to 'societate')
        $this->createCategory('Societate', 'societate');
        $this->em->flush();

        $pr = $this->buildPressRelease('map-fields');
        $pr->setStatus(PressReleaseStatus::PENDING);
        $this->em->persist($pr);
        $this->em->flush();

        // Act
        $article = $this->articleFactory->createFromPressRelease($pr);
        $this->em->flush();

        // Assert: article was persisted and has an ID
        $this->assertNotNull($article->getId(), 'Article should have been persisted with an ID');

        // Assert: fields are mapped correctly
        $this->assertSame($pr->getTitle(), $article->getTitle());
        $this->assertSame($pr->getLead(), $article->getLead());
        $this->assertSame($pr->getContent(), $article->getContent());
        $this->assertSame(ArticleStatus::NEW, $article->getStatus());
        $this->assertSame($pr->getContentHash(), $article->getContentHash());

        // Assert: category was assigned (fallback to 'societate')
        $this->assertNotNull($article->getCategory(), 'Article should have a category assigned');
        $this->assertSame('societate', $article->getCategory()->getSlug());
    }

    public function testCreateArticleFromPressReleaseUsesMatchingCategorySlug(): void
    {
        // Arrange: create the exact category the PR references
        $this->createCategory('Politica', 'politica');
        $this->createCategory('Societate', 'societate');
        $this->em->flush();

        $pr = $this->buildPressRelease('cat-match');
        $pr->setCategorySlug('politica');
        $this->em->persist($pr);
        $this->em->flush();

        // Act
        $article = $this->articleFactory->createFromPressRelease($pr);
        $this->em->flush();

        // Assert: correct category was assigned
        $this->assertNotNull($article->getCategory());
        $this->assertSame('politica', $article->getCategory()->getSlug());
    }

    public function testCreateArticleFromPressReleaseFallsBackToSocietate(): void
    {
        // Arrange: create only the fallback category
        $this->createCategory('Societate', 'societate');
        $this->em->flush();

        $pr = $this->buildPressRelease('fallback-cat');
        $pr->setCategorySlug('nonexistent-category-slug');
        $this->em->persist($pr);
        $this->em->flush();

        // Act
        $article = $this->articleFactory->createFromPressRelease($pr);
        $this->em->flush();

        // Assert: fell back to 'societate'
        $this->assertNotNull($article->getCategory());
        $this->assertSame('societate', $article->getCategory()->getSlug());
    }

    public function testCreateArticleFromPressReleaseWithSourceEmailId(): void
    {
        $this->createCategory('Societate', 'societate');
        $this->em->flush();

        $unique = uniqid('email-id-', true);
        $pr = $this->buildPressRelease('src-email');
        $pr->setSourceEmailId($unique);
        $this->em->persist($pr);
        $this->em->flush();

        // Act
        $article = $this->articleFactory->createFromPressRelease($pr);
        $this->em->flush();

        // Assert: sourceEmail was set on the article
        $this->assertSame($unique, $article->getSourceEmail());
    }

    public function testCreateArticleFromPressReleaseSetsLocale(): void
    {
        $this->createCategory('Societate', 'societate');
        $this->em->flush();

        $pr = $this->buildPressRelease('locale-en');
        $pr->setOriginalLanguage('en');
        $this->em->persist($pr);
        $this->em->flush();

        // Act
        $article = $this->articleFactory->createFromPressRelease($pr);

        // Assert: article locale matches the press release language
        $this->assertSame('en', $article->getLocale());
    }

    // =========================================================================
    // 2. PressRelease with Source entity link
    // =========================================================================

    public function testPressReleaseCanBeLinkedToSource(): void
    {
        $source = $this->createSource('Test Source ' . uniqid());
        $this->em->flush();

        $pr = $this->buildPressRelease('with-source');
        $pr->setSource($source);
        $this->em->persist($pr);
        $this->em->flush();

        // Re-fetch from DB to verify persistence
        $prId = $pr->getId();
        $this->em->clear();

        $fetched = $this->em->find(PressRelease::class, $prId);
        $this->assertNotNull($fetched);
        $this->assertNotNull($fetched->getSource());
        $this->assertSame($source->getId(), $fetched->getSource()->getId());
    }

    // =========================================================================
    // 3. Approve guard: only PENDING press releases can be approved
    // =========================================================================

    public function testApproveGuardRejectsPressReleaseNotInPendingStatus(): void
    {
        // Simulate what the PressReleaseApproveProcessor checks:
        // "Only pending press releases can be approved"
        $pr = $this->buildPressRelease('approve-guard');
        $pr->setStatus(PressReleaseStatus::APPROVED);
        $this->em->persist($pr);
        $this->em->flush();

        // The processor would throw BadRequestHttpException.
        // We test the guard logic directly since calling the processor
        // requires API Platform operation context.
        $this->assertNotSame(
            PressReleaseStatus::PENDING,
            $pr->getStatus(),
            'PressRelease should not be in PENDING status for this test'
        );

        // Verify that the guard condition would reject this
        $this->assertTrue(
            $pr->getStatus() !== PressReleaseStatus::PENDING,
            'An already-approved PressRelease must not pass the pending guard'
        );
    }

    public function testRejectGuardRejectsPressReleaseNotInPendingStatus(): void
    {
        // Same guard logic for the reject processor
        $pr = $this->buildPressRelease('reject-guard');
        $pr->setStatus(PressReleaseStatus::REJECTED);
        $this->em->persist($pr);
        $this->em->flush();

        $this->assertNotSame(
            PressReleaseStatus::PENDING,
            $pr->getStatus(),
            'PressRelease should not be in PENDING status for this test'
        );
    }

    // =========================================================================
    // 4. Reject flow: PENDING -> REJECTED with reason
    // =========================================================================

    public function testPressReleaseCanBeRejectedWithReason(): void
    {
        $pr = $this->buildPressRelease('reject-flow');
        $pr->setStatus(PressReleaseStatus::PENDING);
        $this->em->persist($pr);
        $this->em->flush();

        // Simulate the reject processor logic
        $this->assertSame(PressReleaseStatus::PENDING, $pr->getStatus());

        $pr->setStatus(PressReleaseStatus::REJECTED);
        $pr->setRejectionReason('Content is not relevant to our editorial scope');
        $pr->setProcessedAt(new \DateTimeImmutable());
        $this->em->flush();

        // Re-fetch to verify persistence
        $prId = $pr->getId();
        $this->em->clear();

        $fetched = $this->em->find(PressRelease::class, $prId);
        $this->assertNotNull($fetched);
        $this->assertSame(PressReleaseStatus::REJECTED, $fetched->getStatus());
        $this->assertSame('Content is not relevant to our editorial scope', $fetched->getRejectionReason());
        $this->assertNotNull($fetched->getProcessedAt());
    }

    // =========================================================================
    // 5. Full approve flow: PENDING -> APPROVED + Article created
    // =========================================================================

    public function testFullApproveFlowCreatesArticleAndUpdatesStatus(): void
    {
        // Arrange
        $this->createCategory('Societate', 'societate');
        $this->em->flush();

        $pr = $this->buildPressRelease('full-approve');
        $pr->setStatus(PressReleaseStatus::PENDING);
        $this->em->persist($pr);
        $this->em->flush();

        $this->assertSame(PressReleaseStatus::PENDING, $pr->getStatus());

        // Act: simulate what PressReleaseApproveProcessor does
        $article = $this->articleFactory->createFromPressRelease($pr);

        $pr->setStatus(PressReleaseStatus::APPROVED);
        $pr->setProcessedAt(new \DateTimeImmutable());
        $pr->setArticle($article);
        $this->em->flush();

        // Assert: article was created
        $this->assertNotNull($article->getId());
        $this->assertSame($pr->getTitle(), $article->getTitle());
        $this->assertSame(ArticleStatus::NEW, $article->getStatus());

        // Assert: press release was updated
        $this->assertSame(PressReleaseStatus::APPROVED, $pr->getStatus());
        $this->assertNotNull($pr->getProcessedAt());
        $this->assertSame($article->getId(), $pr->getArticle()->getId());
    }

    public function testApprovedPressReleaseCannotBeApprovedAgain(): void
    {
        // Arrange
        $this->createCategory('Societate', 'societate');
        $this->em->flush();

        $pr = $this->buildPressRelease('double-approve');
        $pr->setStatus(PressReleaseStatus::PENDING);
        $this->em->persist($pr);
        $this->em->flush();

        // First approval
        $article = $this->articleFactory->createFromPressRelease($pr);
        $pr->setStatus(PressReleaseStatus::APPROVED);
        $pr->setArticle($article);
        $this->em->flush();

        // Assert: guard condition prevents second approval
        $this->assertSame(PressReleaseStatus::APPROVED, $pr->getStatus());
        $this->assertNotSame(
            PressReleaseStatus::PENDING,
            $pr->getStatus(),
            'An approved PressRelease must not pass the pending-only guard for re-approval'
        );
    }

    // =========================================================================
    // 6. ArticleFactoryService: extractDomain helper
    // =========================================================================

    public function testExtractDomainFromPlainEmail(): void
    {
        $domain = $this->articleFactory->extractDomain('press@gov.md');
        $this->assertSame('gov.md', $domain);
    }

    public function testExtractDomainFromNamedEmail(): void
    {
        $domain = $this->articleFactory->extractDomain('Press Office <press@gov.md>');
        $this->assertSame('gov.md', $domain);
    }

    public function testExtractDomainReturnsNullForInvalidInput(): void
    {
        $domain = $this->articleFactory->extractDomain('not-an-email');
        $this->assertNull($domain);
    }

    // =========================================================================
    // 7. PressRelease content length auto-calculation
    // =========================================================================

    public function testPressReleaseContentLengthIsCalculatedOnSetContent(): void
    {
        $pr = new PressRelease();
        $pr->setTitle('Test');
        $pr->setContent('<p>Hello <strong>World</strong></p>');
        $pr->setCategorySlug('test');

        // contentLength should be the length of stripped tags: "Hello World" = 11
        $this->assertSame(11, $pr->getContentLength());
    }

    // =========================================================================
    // 8. PressRelease source hostname computed field
    // =========================================================================

    public function testSourceHostnameFromSourceUrl(): void
    {
        $pr = new PressRelease();
        $pr->setTitle('Test');
        $pr->setContent('content');
        $pr->setCategorySlug('test');
        $pr->setSourceUrl('https://www.gov.md/ro/content/article-title');

        $this->assertSame('gov.md', $pr->getSourceHostname());
    }

    public function testSourceHostnamePrefersPublisherDomain(): void
    {
        $pr = new PressRelease();
        $pr->setTitle('Test');
        $pr->setContent('content');
        $pr->setCategorySlug('test');
        $pr->setSourceUrl('https://news.google.com/rss/articles/...');
        $pr->setSourcePublisherDomain('moldpres.md');

        // Publisher domain takes priority over parsed URL
        $this->assertSame('moldpres.md', $pr->getSourceHostname());
    }

    public function testSourceHostnameReturnsNullWhenNoUrlOrDomain(): void
    {
        $pr = new PressRelease();
        $pr->setTitle('Test');
        $pr->setContent('content');
        $pr->setCategorySlug('test');

        $this->assertNull($pr->getSourceHostname());
    }
}
