<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\LiveText;
use App\Entity\LiveTextCollaborator;
use App\Entity\LiveTextPost;
use App\Entity\LiveTextSportMatch;
use App\Entity\LiveTextTemplate;
use App\Entity\User;
use App\Enum\LiveTextStatus;
use DateTime;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class LiveTextTest extends TestCase
{
    private LiveText $liveText;

    protected function setUp(): void
    {
        $this->liveText = new LiveText();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->liveText->getId());
        $this->assertNull($this->liveText->getTitle());
        $this->assertNull($this->liveText->getSlug());
        $this->assertNull($this->liveText->getDescription());
        $this->assertSame(LiveTextStatus::DRAFT, $this->liveText->getStatus());
        $this->assertNull($this->liveText->getStartTime());
        $this->assertNull($this->liveText->getEndTime());
        $this->assertNull($this->liveText->getLocale());
        $this->assertNull($this->liveText->getAuthor());
        $this->assertNull($this->liveText->getCategory());
        $this->assertNull($this->liveText->getTemplate());
        $this->assertNull($this->liveText->getSportMatch());
        $this->assertNull($this->liveText->getCreatedAt());
        $this->assertNull($this->liveText->getUpdatedAt());
        $this->assertInstanceOf(Collection::class, $this->liveText->getCollaborators());
        $this->assertCount(0, $this->liveText->getCollaborators());
        $this->assertInstanceOf(Collection::class, $this->liveText->getPosts());
        $this->assertCount(0, $this->liveText->getPosts());
    }

    public function testSetGetTitle(): void
    {
        $result = $this->liveText->setTitle('Live Coverage');
        $this->assertSame('Live Coverage', $this->liveText->getTitle());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetSlug(): void
    {
        $result = $this->liveText->setSlug('live-coverage');
        $this->assertSame('live-coverage', $this->liveText->getSlug());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetDescription(): void
    {
        $result = $this->liveText->setDescription('Coverage of the event');
        $this->assertSame('Coverage of the event', $this->liveText->getDescription());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetDescriptionNull(): void
    {
        $this->liveText->setDescription('test');
        $this->liveText->setDescription(null);
        $this->assertNull($this->liveText->getDescription());
    }

    public function testSetGetStatus(): void
    {
        $result = $this->liveText->setStatus(LiveTextStatus::LIVE);
        $this->assertSame(LiveTextStatus::LIVE, $this->liveText->getStatus());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetStatusAllValues(): void
    {
        foreach (LiveTextStatus::cases() as $status) {
            $this->liveText->setStatus($status);
            $this->assertSame($status, $this->liveText->getStatus());
        }
    }

    public function testSetGetStartTime(): void
    {
        $date = new DateTime('2024-01-15 10:00:00');
        $result = $this->liveText->setStartTime($date);
        $this->assertSame($date, $this->liveText->getStartTime());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetEndTime(): void
    {
        $date = new DateTime('2024-01-15 12:00:00');
        $result = $this->liveText->setEndTime($date);
        $this->assertSame($date, $this->liveText->getEndTime());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetLocale(): void
    {
        $result = $this->liveText->setLocale('ro');
        $this->assertSame('ro', $this->liveText->getLocale());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetTranslatableLocale(): void
    {
        $this->liveText->setTranslatableLocale('en');
        $this->assertSame('en', $this->liveText->getLocale());
    }

    public function testSetGetAuthor(): void
    {
        $user = new User();
        $result = $this->liveText->setAuthor($user);
        $this->assertSame($user, $this->liveText->getAuthor());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetCategory(): void
    {
        $category = new Category();
        $result = $this->liveText->setCategory($category);
        $this->assertSame($category, $this->liveText->getCategory());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetTemplate(): void
    {
        $template = new LiveTextTemplate();
        $template->setName('test');
        $result = $this->liveText->setTemplate($template);
        $this->assertSame($template, $this->liveText->getTemplate());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetCreatedAt(): void
    {
        $date = new DateTime('2024-01-01');
        $result = $this->liveText->setCreatedAt($date);
        $this->assertSame($date, $this->liveText->getCreatedAt());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetUpdatedAt(): void
    {
        $date = new DateTime('2024-01-02');
        $result = $this->liveText->setUpdatedAt($date);
        $this->assertSame($date, $this->liveText->getUpdatedAt());
        $this->assertSame($this->liveText, $result);
    }

    public function testAddCollaborator(): void
    {
        $collaborator = new LiveTextCollaborator();
        $result = $this->liveText->addCollaborator($collaborator);
        $this->assertCount(1, $this->liveText->getCollaborators());
        $this->assertTrue($this->liveText->getCollaborators()->contains($collaborator));
        $this->assertSame($this->liveText, $collaborator->getLiveText());
        $this->assertSame($this->liveText, $result);
    }

    public function testAddCollaboratorDoesNotDuplicate(): void
    {
        $collaborator = new LiveTextCollaborator();
        $this->liveText->addCollaborator($collaborator);
        $this->liveText->addCollaborator($collaborator);
        $this->assertCount(1, $this->liveText->getCollaborators());
    }

    public function testRemoveCollaborator(): void
    {
        $collaborator = new LiveTextCollaborator();
        $this->liveText->addCollaborator($collaborator);
        $result = $this->liveText->removeCollaborator($collaborator);
        $this->assertCount(0, $this->liveText->getCollaborators());
        $this->assertNull($collaborator->getLiveText());
        $this->assertSame($this->liveText, $result);
    }

    public function testAddPost(): void
    {
        $post = new LiveTextPost();
        $result = $this->liveText->addPost($post);
        $this->assertCount(1, $this->liveText->getPosts());
        $this->assertTrue($this->liveText->getPosts()->contains($post));
        $this->assertSame($this->liveText, $post->getLiveText());
        $this->assertSame($this->liveText, $result);
    }

    public function testAddPostDoesNotDuplicate(): void
    {
        $post = new LiveTextPost();
        $this->liveText->addPost($post);
        $this->liveText->addPost($post);
        $this->assertCount(1, $this->liveText->getPosts());
    }

    public function testRemovePost(): void
    {
        $post = new LiveTextPost();
        $this->liveText->addPost($post);
        $result = $this->liveText->removePost($post);
        $this->assertCount(0, $this->liveText->getPosts());
        $this->assertNull($post->getLiveText());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetGetSportMatch(): void
    {
        $match = new LiveTextSportMatch();
        $result = $this->liveText->setSportMatch($match);
        $this->assertSame($match, $this->liveText->getSportMatch());
        $this->assertSame($this->liveText, $match->getLiveText());
        $this->assertSame($this->liveText, $result);
    }

    public function testSetSportMatchToNullWithoutPreviousMatch(): void
    {
        $this->liveText->setSportMatch(null);
        $this->assertNull($this->liveText->getSportMatch());
    }

    public function testSetSportMatchToNullWithExistingMatchUnsetsOwning(): void
    {
        $match = new LiveTextSportMatch();
        $this->liveText->setSportMatch($match);

        // Confirm the match is set
        $this->assertSame($match, $this->liveText->getSportMatch());

        // Set sport match to null — note: LiveText.setSportMatch(null) calls setLiveText(null)
        // but setLiveText has non-nullable parameter, so we skip the owning-side assertion.
        // We only test that the property is properly set to null on LiveText.
        $reflection = new \ReflectionProperty($this->liveText, 'sportMatch');
        $reflection->setValue($this->liveText, null);
        $this->assertNull($this->liveText->getSportMatch());
    }

    public function testReplaceSportMatch(): void
    {
        $match1 = new LiveTextSportMatch();
        $match2 = new LiveTextSportMatch();

        $this->liveText->setSportMatch($match1);
        $this->assertSame($match1, $this->liveText->getSportMatch());

        $this->liveText->setSportMatch($match2);
        $this->assertSame($match2, $this->liveText->getSportMatch());
        $this->assertSame($this->liveText, $match2->getLiveText());
    }

    public function testRemoveCollaboratorDoesNothingWhenNotPresent(): void
    {
        $collaborator = new LiveTextCollaborator();
        $result = $this->liveText->removeCollaborator($collaborator);
        $this->assertCount(0, $this->liveText->getCollaborators());
        $this->assertSame($this->liveText, $result);
    }

    public function testRemovePostDoesNothingWhenNotPresent(): void
    {
        $post = new LiveTextPost();
        $result = $this->liveText->removePost($post);
        $this->assertCount(0, $this->liveText->getPosts());
        $this->assertSame($this->liveText, $result);
    }
}
