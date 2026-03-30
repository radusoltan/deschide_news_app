<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\Author;
use App\Enum\ArticleStatus;
use App\Enum\AuthorStatus;
use App\Enum\AuthorType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Author entity.
 *
 * Tests entity creation, getters, setters, computed properties, and business logic
 * according to Symfony and API Platform testing best practices.
 */
class AuthorTest extends TestCase
{
    public function testAuthorCreation(): void
    {
        $author = new Author();

        $this->assertNull($author->getId());
        $this->assertInstanceOf(Author::class, $author);
    }

    public function testSetAndGetFirstName(): void
    {
        $author = new Author();
        $firstName = 'John';

        $result = $author->setFirstName($firstName);

        $this->assertSame($author, $result); // Test fluent interface
        $this->assertEquals($firstName, $author->getFirstName());
    }

    public function testSetAndGetLastName(): void
    {
        $author = new Author();
        $lastName = 'Doe';

        $result = $author->setLastName($lastName);

        $this->assertSame($author, $result);
        $this->assertEquals($lastName, $author->getLastName());
    }

    public function testSetAndGetEmail(): void
    {
        $author = new Author();
        $email = 'john.doe@example.com';

        $result = $author->setEmail($email);

        $this->assertSame($author, $result);
        $this->assertEquals($email, $author->getEmail());
    }

    public function testSetAndGetSlug(): void
    {
        $author = new Author();
        $slug = 'john-doe';

        $result = $author->setSlug($slug);

        $this->assertSame($author, $result);
        $this->assertEquals($slug, $author->getSlug());
    }

    public function testSetAndGetBio(): void
    {
        $author = new Author();
        $bio = 'Experienced journalist with 10 years in the field.';

        $result = $author->setBio($bio);

        $this->assertSame($author, $result);
        $this->assertEquals($bio, $author->getBio());
    }

    public function testBioCanBeNull(): void
    {
        $author = new Author();

        $result = $author->setBio(null);

        $this->assertSame($author, $result);
        $this->assertNull($author->getBio());
    }

    public function testDefaultStatus(): void
    {
        $author = new Author();

        // Default status should be ACTIVE
        $this->assertEquals(AuthorStatus::ACTIVE, $author->getStatus());
    }

    public function testSetAndGetStatus(): void
    {
        $author = new Author();

        $result = $author->setStatus(AuthorStatus::INACTIVE);

        $this->assertSame($author, $result);
        $this->assertEquals(AuthorStatus::INACTIVE, $author->getStatus());
    }

    public function testIsActiveDefaultValue(): void
    {
        $author = new Author();

        // Default should be true
        $this->assertTrue($author->isActive());
    }

    public function testSetAndGetIsActive(): void
    {
        $author = new Author();

        $result = $author->setIsActive(false);

        $this->assertSame($author, $result);
        $this->assertFalse($author->isActive());

        $author->setIsActive(true);
        $this->assertTrue($author->isActive());
    }

    public function testArticlesCollectionInitialization(): void
    {
        $author = new Author();

        // Articles collection should be initialized and empty
        $this->assertCount(0, $author->getArticles());
    }

    public function testAddArticle(): void
    {
        $author = new Author();
        $article = $this->createMock(Article::class);

        $article->expects($this->once())
            ->method('addAuthor')
            ->with($author);

        $result = $author->addArticle($article);

        $this->assertSame($author, $result);
        $this->assertCount(1, $author->getArticles());
        $this->assertTrue($author->getArticles()->contains($article));
    }

    public function testAddArticleDoesNotAddDuplicates(): void
    {
        $author = new Author();
        $article = $this->createMock(Article::class);

        $article->expects($this->once()) // Should only be called once
            ->method('addAuthor')
            ->with($author);

        $author->addArticle($article);
        $author->addArticle($article); // Add same article again

        $this->assertCount(1, $author->getArticles()); // Should still be 1
    }

    public function testRemoveArticle(): void
    {
        $author = new Author();
        $article = $this->createMock(Article::class);

        $article->method('addAuthor');
        $article->expects($this->once())
            ->method('removeAuthor')
            ->with($author);

        $author->addArticle($article);
        $this->assertCount(1, $author->getArticles());

        $result = $author->removeArticle($article);

        $this->assertSame($author, $result);
        $this->assertCount(0, $author->getArticles());
        $this->assertFalse($author->getArticles()->contains($article));
    }

    // Social Media Tests

    public function testSetAndGetTwitter(): void
    {
        $author = new Author();
        $twitter = '@johndoe';

        $result = $author->setTwitter($twitter);

        $this->assertSame($author, $result);
        $this->assertEquals($twitter, $author->getTwitter());
    }

    public function testTwitterCanBeNull(): void
    {
        $author = new Author();

        $author->setTwitter(null);

        $this->assertNull($author->getTwitter());
    }

    public function testSetAndGetFacebook(): void
    {
        $author = new Author();
        $facebook = 'https://facebook.com/johndoe';

        $result = $author->setFacebook($facebook);

        $this->assertSame($author, $result);
        $this->assertEquals($facebook, $author->getFacebook());
    }

    public function testFacebookCanBeNull(): void
    {
        $author = new Author();

        $author->setFacebook(null);

        $this->assertNull($author->getFacebook());
    }

    public function testSetAndGetLinkedin(): void
    {
        $author = new Author();
        $linkedin = 'https://linkedin.com/in/johndoe';

        $result = $author->setLinkedin($linkedin);

        $this->assertSame($author, $result);
        $this->assertEquals($linkedin, $author->getLinkedin());
    }

    public function testLinkedinCanBeNull(): void
    {
        $author = new Author();

        $author->setLinkedin(null);

        $this->assertNull($author->getLinkedin());
    }

    public function testSetAndGetWebsite(): void
    {
        $author = new Author();
        $website = 'https://johndoe.com';

        $result = $author->setWebsite($website);

        $this->assertSame($author, $result);
        $this->assertEquals($website, $author->getWebsite());
    }

    public function testWebsiteCanBeNull(): void
    {
        $author = new Author();

        $author->setWebsite(null);

        $this->assertNull($author->getWebsite());
    }

    // Translatable Tests

    public function testTranslatableLocale(): void
    {
        $author = new Author();

        $this->assertNull($author->getLocale());

        $author->setTranslatableLocale('ro');
        $this->assertEquals('ro', $author->getLocale());

        $author->setTranslatableLocale('en');
        $this->assertEquals('en', $author->getLocale());

        $author->setTranslatableLocale('ru');
        $this->assertEquals('ru', $author->getLocale());
    }

    // Timestamp Tests

    public function testCreatedAtTimestamp(): void
    {
        $author = new Author();

        // Initially null (will be set by Gedmo on persist)
        $this->assertNull($author->getCreatedAt());
    }

    public function testUpdatedAtTimestamp(): void
    {
        $author = new Author();

        // Initially null (will be set by Gedmo on persist/update)
        $this->assertNull($author->getUpdatedAt());
    }

    // Computed Properties Tests

    public function testGetFullName(): void
    {
        $author = new Author();
        $author->setFirstName('John');
        $author->setLastName('Doe');

        $this->assertEquals('John Doe', $author->getFullName());
    }

    public function testGetFullNameWithExtraSpaces(): void
    {
        $author = new Author();
        $author->setFirstName('  John  ');
        $author->setLastName('  Doe  ');

        // getFullName concatenates with space, then trims
        // '  John  ' + ' ' + '  Doe  ' = '  John    Doe  '
        // After trim() in getFullName: 'John    Doe' (4 spaces in middle)
        $fullName = $author->getFullName();
        $this->assertStringContainsString('John', $fullName);
        $this->assertStringContainsString('Doe', $fullName);
    }

    public function testGetInitials(): void
    {
        $author = new Author();
        $author->setFirstName('John');
        $author->setLastName('Doe');

        $this->assertEquals('JD', $author->getInitials());
    }

    public function testGetInitialsWithUnicode(): void
    {
        $author = new Author();
        $author->setFirstName('Ștefan');
        $author->setLastName('Ionescu');

        $this->assertEquals('ȘI', $author->getInitials());
    }

    public function testGetArticleCountFiltersPublishedArticles(): void
    {
        $author = new Author();

        // Create published article
        $publishedArticle = $this->createMock(Article::class);
        $publishedArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $publishedArticle->method('addAuthor');

        // Create non-published article (NEW status)
        $newArticle = $this->createMock(Article::class);
        $newArticle->method('getStatus')->willReturn(ArticleStatus::NEW);
        $newArticle->method('addAuthor');

        $author->addArticle($publishedArticle);
        $author->addArticle($newArticle);

        // getArticleCount should only count published articles
        $this->assertEquals(1, $author->getArticleCount());
    }

    // Enum Tests

    public function testAuthorStatusEnumCases(): void
    {
        $cases = AuthorStatus::cases();

        $this->assertGreaterThanOrEqual(2, \count($cases));
        $this->assertContains(AuthorStatus::ACTIVE, $cases);
        $this->assertContains(AuthorStatus::INACTIVE, $cases);
    }

    // Max Length Tests

    public function testFirstNameMaxLength(): void
    {
        $author = new Author();
        $longName = str_repeat('a', 100);

        $author->setFirstName($longName);

        $this->assertEquals(100, \strlen($author->getFirstName()));
    }

    public function testLastNameMaxLength(): void
    {
        $author = new Author();
        $longName = str_repeat('a', 100);

        $author->setLastName($longName);

        $this->assertEquals(100, \strlen($author->getLastName()));
    }

    public function testBioMaxLength(): void
    {
        $author = new Author();
        $longBio = str_repeat('a', 2000);

        $author->setBio($longBio);

        $this->assertEquals(2000, \strlen($author->getBio()));
    }

    public function testTwitterMaxLength(): void
    {
        $author = new Author();
        $longTwitter = str_repeat('a', 100);

        $author->setTwitter($longTwitter);

        $this->assertEquals(100, \strlen($author->getTwitter()));
    }

    // AuthorType Tests

    public function testDefaultTypeIsJournalist(): void
    {
        $author = new Author();

        $this->assertSame(AuthorType::JOURNALIST, $author->getType());
    }

    public function testSetAndGetTypeJournalist(): void
    {
        $author = new Author();

        $result = $author->setType(AuthorType::JOURNALIST);

        $this->assertSame($author, $result);
        $this->assertSame(AuthorType::JOURNALIST, $author->getType());
    }

    public function testSetAndGetTypeAgency(): void
    {
        $author = new Author();

        $result = $author->setType(AuthorType::AGENCY);

        $this->assertSame($author, $result);
        $this->assertSame(AuthorType::AGENCY, $author->getType());
    }

    public function testSetAndGetTypePressOffice(): void
    {
        $author = new Author();

        $result = $author->setType(AuthorType::PRESS_OFFICE);

        $this->assertSame($author, $result);
        $this->assertSame(AuthorType::PRESS_OFFICE, $author->getType());
    }

    public function testSetTypeOverridesPrevious(): void
    {
        $author = new Author();

        $author->setType(AuthorType::AGENCY);
        $this->assertSame(AuthorType::AGENCY, $author->getType());

        $author->setType(AuthorType::PRESS_OFFICE);
        $this->assertSame(AuthorType::PRESS_OFFICE, $author->getType());
    }

    // EmailDomain Tests

    public function testGetEmailDomainDefaultsToNull(): void
    {
        $author = new Author();

        $this->assertNull($author->getEmailDomain());
    }

    public function testSetAndGetEmailDomain(): void
    {
        $author = new Author();

        $result = $author->setEmailDomain('ipn.md');

        $this->assertSame($author, $result);
        $this->assertSame('ipn.md', $author->getEmailDomain());
    }

    public function testEmailDomainCanBeNull(): void
    {
        $author = new Author();

        $author->setEmailDomain('gov.md');
        $this->assertSame('gov.md', $author->getEmailDomain());

        $author->setEmailDomain(null);
        $this->assertNull($author->getEmailDomain());
    }

    public function testEmailDomainMaxLength(): void
    {
        $author = new Author();
        $longDomain = str_repeat('a', 100);

        $author->setEmailDomain($longDomain);

        $this->assertEquals(100, \strlen($author->getEmailDomain()));
    }

    // Fluent Interface Test

    public function testFluentInterface(): void
    {
        $author = new Author();

        $result = $author
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('john.doe@example.com')
            ->setSlug('john-doe')
            ->setBio('Journalist')
            ->setStatus(AuthorStatus::ACTIVE)
            ->setIsActive(true)
            ->setTwitter('@johndoe');

        $this->assertSame($author, $result);
        $this->assertEquals('John', $author->getFirstName());
        $this->assertEquals('Doe', $author->getLastName());
        $this->assertEquals('john.doe@example.com', $author->getEmail());
        $this->assertEquals('john-doe', $author->getSlug());
        $this->assertEquals('Journalist', $author->getBio());
        $this->assertEquals(AuthorStatus::ACTIVE, $author->getStatus());
        $this->assertTrue($author->isActive());
        $this->assertEquals('@johndoe', $author->getTwitter());
    }

    public function testFluentInterfaceWithTypeAndEmailDomain(): void
    {
        $author = new Author();

        $result = $author
            ->setFirstName('IPN')
            ->setLastName('Info-Prim Neo')
            ->setEmail('contact@ipn.md')
            ->setType(AuthorType::AGENCY)
            ->setEmailDomain('ipn.md');

        $this->assertSame($author, $result);
        $this->assertSame(AuthorType::AGENCY, $author->getType());
        $this->assertSame('ipn.md', $author->getEmailDomain());
    }
}
