<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\VideoShow;
use App\Entity\YouTubeVideo;
use App\Repository\YouTubeVideoRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for YouTubeVideoRepository.
 */
class YouTubeVideoRepositoryTest extends KernelTestCase
{
    private YouTubeVideoRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(YouTubeVideoRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findForHomepageSlider
    // =====================================================================

    public function testFindForHomepageSliderReturnsVisibleVideos(): void
    {
        $suffix = uniqid();

        $visible = new YouTubeVideo();
        $visible->setYoutubeId('vis' . $suffix);
        $visible->setTitle('Visible Video ' . $suffix);
        $visible->setIsHidden(false);
        $visible->setIsFeatured(true);
        $visible->setPosition(0);
        $visible->setPublishedAt(new DateTimeImmutable('+1 year'));
        $this->em->persist($visible);

        $hidden = new YouTubeVideo();
        $hidden->setYoutubeId('hid' . $suffix);
        $hidden->setTitle('Hidden Video ' . $suffix);
        $hidden->setIsHidden(true);
        $hidden->setPublishedAt(new DateTimeImmutable());
        $this->em->persist($hidden);

        $this->em->flush();

        $visibleId = $visible->getId();
        $hiddenId = $hidden->getId();

        $this->em->clear();

        $results = $this->repository->findForHomepageSlider(5000);

        $ids = array_map(fn (YouTubeVideo $v) => $v->getId(), $results);
        $this->assertContains($visibleId, $ids);
        $this->assertNotContains($hiddenId, $ids);
    }

    public function testFindForHomepageSliderRespectsLimit(): void
    {
        $results = $this->repository->findForHomepageSlider(2);

        $this->assertLessThanOrEqual(2, count($results));
    }

    // =====================================================================
    // findByYoutubeId
    // =====================================================================

    public function testFindByYoutubeIdReturnsMatchingVideo(): void
    {
        $ytId = 'test' . uniqid();

        $video = new YouTubeVideo();
        $video->setYoutubeId($ytId);
        $video->setTitle('Find by YT ID');
        $this->em->persist($video);
        $this->em->flush();

        $found = $this->repository->findByYoutubeId($ytId);

        $this->assertNotNull($found);
        $this->assertSame($ytId, $found->getYoutubeId());
    }

    public function testFindByYoutubeIdReturnsNullForNonExistent(): void
    {
        $found = $this->repository->findByYoutubeId('nonexistent' . uniqid());

        $this->assertNull($found);
    }

    // =====================================================================
    // findFeatured
    // =====================================================================

    public function testFindFeaturedReturnsOnlyFeaturedVisibleVideos(): void
    {
        $suffix = uniqid();

        $featured = new YouTubeVideo();
        $featured->setYoutubeId('feat' . $suffix);
        $featured->setTitle('Featured ' . $suffix);
        $featured->setIsFeatured(true);
        $featured->setIsHidden(false);
        $featured->setPosition(0);
        $this->em->persist($featured);

        $notFeatured = new YouTubeVideo();
        $notFeatured->setYoutubeId('nfeat' . $suffix);
        $notFeatured->setTitle('Not Featured ' . $suffix);
        $notFeatured->setIsFeatured(false);
        $notFeatured->setIsHidden(false);
        $this->em->persist($notFeatured);

        $this->em->flush();

        // Use a large enough limit to include our test video
        $results = $this->repository->findFeatured(1000);

        $ids = array_map(fn (YouTubeVideo $v) => $v->getId(), $results);
        $this->assertContains($featured->getId(), $ids);
        $this->assertNotContains($notFeatured->getId(), $ids);
    }

    // =====================================================================
    // findByVideoShow
    // =====================================================================

    public function testFindByVideoShowReturnsVideosForShow(): void
    {
        $suffix = uniqid();

        $show = new VideoShow();
        $show->setName('Test Show ' . $suffix);
        $show->setSlug('test-show-' . $suffix);
        $this->em->persist($show);

        $video = new YouTubeVideo();
        $video->setYoutubeId('shw' . $suffix);
        $video->setTitle('Show Video ' . $suffix);
        $video->setVideoShow($show);
        $video->setIsHidden(false);
        $video->setPublishedAt(new DateTimeImmutable());
        $this->em->persist($video);

        $this->em->flush();

        $results = $this->repository->findByVideoShow($show, 1, 100);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $ids = array_map(fn (YouTubeVideo $v) => $v->getId(), $results);
        $this->assertContains($video->getId(), $ids);
    }

    // =====================================================================
    // countByVideoShow
    // =====================================================================

    public function testCountByVideoShowReturnsCorrectCount(): void
    {
        $suffix = uniqid();

        $show = new VideoShow();
        $show->setName('Count Show ' . $suffix);
        $show->setSlug('count-show-' . $suffix);
        $this->em->persist($show);

        $video = new YouTubeVideo();
        $video->setYoutubeId('cnt' . $suffix);
        $video->setTitle('Count Video ' . $suffix);
        $video->setVideoShow($show);
        $video->setIsHidden(false);
        $this->em->persist($video);

        $this->em->flush();

        $count = $this->repository->countByVideoShow($show);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    // =====================================================================
    // findNeedingStatsUpdate
    // =====================================================================

    public function testFindNeedingStatsUpdateReturnsVideosNotRecentlySynced(): void
    {
        $suffix = uniqid();

        $stale = new YouTubeVideo();
        $stale->setYoutubeId('stl' . $suffix);
        $stale->setTitle('Stale ' . $suffix);
        $stale->setSyncedAt(new DateTimeImmutable('-2 days'));
        $this->em->persist($stale);

        $fresh = new YouTubeVideo();
        $fresh->setYoutubeId('fsh' . $suffix);
        $fresh->setTitle('Fresh ' . $suffix);
        $fresh->setSyncedAt(new DateTimeImmutable());
        $this->em->persist($fresh);

        $this->em->flush();

        // Use a large enough limit to include all stale videos
        $results = $this->repository->findNeedingStatsUpdate(new DateTimeImmutable('-1 day'), 10000);

        $ids = array_map(fn (YouTubeVideo $v) => $v->getId(), $results);
        $this->assertContains($stale->getId(), $ids);
        $this->assertNotContains($fresh->getId(), $ids);
    }

    // =====================================================================
    // getAllYoutubeIds
    // =====================================================================

    public function testGetAllYoutubeIdsReturnsStrings(): void
    {
        $suffix = uniqid();

        $video = new YouTubeVideo();
        $video->setYoutubeId('ids' . $suffix);
        $video->setTitle('IDs Test ' . $suffix);
        $this->em->persist($video);
        $this->em->flush();

        $ids = $this->repository->getAllYoutubeIds();

        $this->assertIsArray($ids);
        $this->assertContains('ids' . $suffix, $ids);
    }
}
