<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ReactionType;
use PHPUnit\Framework\TestCase;

class ReactionTypeTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = ReactionType::cases();
        $this->assertCount(5, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('like', ReactionType::LIKE->value);
        $this->assertSame('love', ReactionType::LOVE->value);
        $this->assertSame('wow', ReactionType::WOW->value);
        $this->assertSame('sad', ReactionType::SAD->value);
        $this->assertSame('angry', ReactionType::ANGRY->value);
    }

    public function testStaticValues(): void
    {
        $values = ReactionType::values();
        $this->assertCount(5, $values);
        $this->assertContains('like', $values);
        $this->assertContains('love', $values);
        $this->assertContains('wow', $values);
        $this->assertContains('sad', $values);
        $this->assertContains('angry', $values);
    }

    public function testGetEmoji(): void
    {
        foreach (ReactionType::cases() as $case) {
            $emoji = $case->getEmoji();
            $this->assertNotEmpty($emoji, sprintf('Emoji for %s should not be empty', $case->value));
            $this->assertIsString($emoji);
        }
    }

    public function testGetLabelDefaultLocale(): void
    {
        $this->assertSame('Like', ReactionType::LIKE->getLabel());
        $this->assertSame('Iubire', ReactionType::LOVE->getLabel());
        $this->assertSame('Uau', ReactionType::WOW->getLabel());
        $this->assertSame('Trist', ReactionType::SAD->getLabel());
        $this->assertSame('Furios', ReactionType::ANGRY->getLabel());
    }

    public function testGetLabelEnglish(): void
    {
        $this->assertSame('Like', ReactionType::LIKE->getLabel('en'));
        $this->assertSame('Love', ReactionType::LOVE->getLabel('en'));
        $this->assertSame('Wow', ReactionType::WOW->getLabel('en'));
        $this->assertSame('Sad', ReactionType::SAD->getLabel('en'));
        $this->assertSame('Angry', ReactionType::ANGRY->getLabel('en'));
    }

    public function testGetLabelRussian(): void
    {
        // Verify all Russian labels are non-empty strings
        foreach (ReactionType::cases() as $case) {
            $label = $case->getLabel('ru');
            $this->assertNotEmpty($label, sprintf('Russian label for %s should not be empty', $case->value));
            $this->assertIsString($label);
        }
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(ReactionType::LIKE, ReactionType::from('like'));
        $this->assertSame(ReactionType::ANGRY, ReactionType::from('angry'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        ReactionType::from('invalid');
    }
}
