<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\RelevanceKeyword;
use App\Enum\KeywordAddedBy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelevanceKeyword::class)]
class RelevanceKeywordTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $kw = new RelevanceKeyword();

        self::assertNull($kw->getId());
        self::assertTrue($kw->isActive());
        self::assertSame(KeywordAddedBy::MANUAL, $kw->getAddedBy());
        self::assertInstanceOf(\DateTimeImmutable::class, $kw->getCreatedAt());
    }

    public function testSettersAndGetters(): void
    {
        $kw = new RelevanceKeyword();
        $kw->setKeyword('Moldova');
        $kw->setTier(1);
        $kw->setLanguage('multi');
        $kw->setIsActive(false);
        $kw->setAddedBy(KeywordAddedBy::AI);

        self::assertSame('Moldova', $kw->getKeyword());
        self::assertSame(1, $kw->getTier());
        self::assertSame('multi', $kw->getLanguage());
        self::assertFalse($kw->isActive());
        self::assertSame(KeywordAddedBy::AI, $kw->getAddedBy());
    }

    public function testTier4Keyword(): void
    {
        $kw = new RelevanceKeyword();
        $kw->setKeyword('a declarat');
        $kw->setTier(4);
        $kw->setLanguage('ro');
        $kw->setAddedBy(KeywordAddedBy::MANUAL);

        self::assertSame(4, $kw->getTier());
        self::assertSame('ro', $kw->getLanguage());
    }

    public function testTrendKeyword(): void
    {
        $kw = new RelevanceKeyword();
        $kw->setKeyword('Transnistria');
        $kw->setTier(3);
        $kw->setLanguage('multi');
        $kw->setAddedBy(KeywordAddedBy::TREND);

        self::assertSame(KeywordAddedBy::TREND, $kw->getAddedBy());
    }
}
