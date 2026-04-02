<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LiveText;
use App\Entity\LiveTextAbTest;
use App\Entity\User;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class LiveTextAbTestTest extends TestCase
{
    private LiveTextAbTest $abTest;

    protected function setUp(): void
    {
        $this->abTest = new LiveTextAbTest();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->abTest->getId());
        $this->assertNull($this->abTest->getName());
        $this->assertNull($this->abTest->getDescription());
        $this->assertNull($this->abTest->getHypothesis());
        $this->assertSame('draft', $this->abTest->getStatus());
        $this->assertNull($this->abTest->getVariantType());
        $this->assertSame([], $this->abTest->getControlVariant());
        $this->assertSame([], $this->abTest->getTestVariants());
        $this->assertSame(100, $this->abTest->getTrafficAllocation());
        $this->assertNull($this->abTest->getTargetMetric());
        $this->assertNull($this->abTest->getMinSampleSize());
        $this->assertNull($this->abTest->getSignificanceLevel());
        $this->assertNull($this->abTest->getStartDate());
        $this->assertNull($this->abTest->getEndDate());
        $this->assertNull($this->abTest->getResults());
        $this->assertNull($this->abTest->getWinnerVariant());
        $this->assertNull($this->abTest->getConfidenceLevel());
        $this->assertNull($this->abTest->getCreatedBy());
        $this->assertInstanceOf(Collection::class, $this->abTest->getLiveTexts());
        $this->assertCount(0, $this->abTest->getLiveTexts());
    }

    public function testConstructorSetsTimestamps(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->abTest->getCreatedAt());
        $this->assertInstanceOf(DateTimeInterface::class, $this->abTest->getUpdatedAt());
    }

    public function testSetGetName(): void
    {
        $result = $this->abTest->setName('Test Layout Variant');
        $this->assertSame('Test Layout Variant', $this->abTest->getName());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetDescription(): void
    {
        $result = $this->abTest->setDescription('Testing new layout');
        $this->assertSame('Testing new layout', $this->abTest->getDescription());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetDescriptionNull(): void
    {
        $this->abTest->setDescription('test');
        $this->abTest->setDescription(null);
        $this->assertNull($this->abTest->getDescription());
    }

    public function testSetGetHypothesis(): void
    {
        $result = $this->abTest->setHypothesis('New layout improves engagement');
        $this->assertSame('New layout improves engagement', $this->abTest->getHypothesis());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetStatus(): void
    {
        $result = $this->abTest->setStatus('running');
        $this->assertSame('running', $this->abTest->getStatus());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetVariantType(): void
    {
        $result = $this->abTest->setVariantType('template');
        $this->assertSame('template', $this->abTest->getVariantType());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetControlVariant(): void
    {
        $variant = ['name' => 'control', 'config' => ['layout' => 'default']];
        $result = $this->abTest->setControlVariant($variant);
        $this->assertSame($variant, $this->abTest->getControlVariant());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetTestVariants(): void
    {
        $variants = [
            ['name' => 'variant_a', 'config' => ['layout' => 'compact']],
            ['name' => 'variant_b', 'config' => ['layout' => 'full']],
        ];
        $result = $this->abTest->setTestVariants($variants);
        $this->assertSame($variants, $this->abTest->getTestVariants());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetTrafficAllocation(): void
    {
        $result = $this->abTest->setTrafficAllocation(50);
        $this->assertSame(50, $this->abTest->getTrafficAllocation());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetTargetMetric(): void
    {
        $result = $this->abTest->setTargetMetric('engagement_rate');
        $this->assertSame('engagement_rate', $this->abTest->getTargetMetric());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetMinSampleSize(): void
    {
        $result = $this->abTest->setMinSampleSize(1000);
        $this->assertSame(1000, $this->abTest->getMinSampleSize());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetSignificanceLevel(): void
    {
        $result = $this->abTest->setSignificanceLevel('0.05');
        $this->assertSame('0.05', $this->abTest->getSignificanceLevel());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetStartDate(): void
    {
        $date = new DateTime('2024-06-01');
        $result = $this->abTest->setStartDate($date);
        $this->assertSame($date, $this->abTest->getStartDate());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetEndDate(): void
    {
        $date = new DateTime('2024-06-30');
        $result = $this->abTest->setEndDate($date);
        $this->assertSame($date, $this->abTest->getEndDate());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetResults(): void
    {
        $results = ['variant_a' => ['views' => 500, 'conversions' => 50]];
        $result = $this->abTest->setResults($results);
        $this->assertSame($results, $this->abTest->getResults());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetWinnerVariant(): void
    {
        $result = $this->abTest->setWinnerVariant('variant_a');
        $this->assertSame('variant_a', $this->abTest->getWinnerVariant());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetConfidenceLevel(): void
    {
        $result = $this->abTest->setConfidenceLevel('95.50');
        $this->assertSame('95.50', $this->abTest->getConfidenceLevel());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetCreatedBy(): void
    {
        $user = new User();
        $result = $this->abTest->setCreatedBy($user);
        $this->assertSame($user, $this->abTest->getCreatedBy());
        $this->assertSame($this->abTest, $result);
    }

    public function testAddLiveText(): void
    {
        $liveText = new LiveText();
        $result = $this->abTest->addLiveText($liveText);
        $this->assertCount(1, $this->abTest->getLiveTexts());
        $this->assertTrue($this->abTest->getLiveTexts()->contains($liveText));
        $this->assertSame($this->abTest, $result);
    }

    public function testAddLiveTextDoesNotDuplicate(): void
    {
        $liveText = new LiveText();
        $this->abTest->addLiveText($liveText);
        $this->abTest->addLiveText($liveText);
        $this->assertCount(1, $this->abTest->getLiveTexts());
    }

    public function testRemoveLiveText(): void
    {
        $liveText = new LiveText();
        $this->abTest->addLiveText($liveText);
        $result = $this->abTest->removeLiveText($liveText);
        $this->assertCount(0, $this->abTest->getLiveTexts());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetCreatedAt(): void
    {
        $date = new DateTime('2024-01-01');
        $result = $this->abTest->setCreatedAt($date);
        $this->assertSame($date, $this->abTest->getCreatedAt());
        $this->assertSame($this->abTest, $result);
    }

    public function testSetGetUpdatedAt(): void
    {
        $date = new DateTime('2024-01-02');
        $result = $this->abTest->setUpdatedAt($date);
        $this->assertSame($date, $this->abTest->getUpdatedAt());
        $this->assertSame($this->abTest, $result);
    }
}
