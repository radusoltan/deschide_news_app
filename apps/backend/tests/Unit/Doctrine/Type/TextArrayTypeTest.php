<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine\Type;

use App\Doctrine\Type\TextArrayType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

class TextArrayTypeTest extends TestCase
{
    private TextArrayType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new TextArrayType();
        $this->platform = $this->createMock(PostgreSQLPlatform::class);
    }

    public function testGetSQLDeclaration(): void
    {
        $this->assertSame('TEXT[]', $this->type->getSQLDeclaration([], $this->platform));
    }

    public function testConvertToPHPValueNull(): void
    {
        $this->assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertToPHPValueEmptyArray(): void
    {
        $this->assertSame([], $this->type->convertToPHPValue('{}', $this->platform));
    }

    public function testConvertToPHPValueSingleElement(): void
    {
        $this->assertSame(['ro'], $this->type->convertToPHPValue('{ro}', $this->platform));
    }

    public function testConvertToPHPValueMultipleElements(): void
    {
        $this->assertSame(['ro', 'en', 'ru'], $this->type->convertToPHPValue('{ro,en,ru}', $this->platform));
    }

    public function testConvertToPHPValueQuotedElements(): void
    {
        $result = $this->type->convertToPHPValue('{"hello world","foo,bar"}', $this->platform);
        $this->assertSame(['hello world', 'foo,bar'], $result);
    }

    public function testConvertToPHPValueAlreadyArray(): void
    {
        $this->assertSame(['ro', 'en'], $this->type->convertToPHPValue(['ro', 'en'], $this->platform));
    }

    public function testConvertToDatabaseValueNull(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testConvertToDatabaseValueEmptyArray(): void
    {
        $this->assertSame('{}', $this->type->convertToDatabaseValue([], $this->platform));
    }

    public function testConvertToDatabaseValueSimple(): void
    {
        $this->assertSame('{ro,en}', $this->type->convertToDatabaseValue(['ro', 'en'], $this->platform));
    }

    public function testConvertToDatabaseValueThreeLocales(): void
    {
        $this->assertSame('{ro,en,ru}', $this->type->convertToDatabaseValue(['ro', 'en', 'ru'], $this->platform));
    }

    public function testConvertToDatabaseValueSpecialChars(): void
    {
        $result = $this->type->convertToDatabaseValue(['hello world', 'foo,bar'], $this->platform);
        $this->assertSame('{"hello world","foo,bar"}', $result);
    }

    public function testRequiresSQLCommentHint(): void
    {
        $this->assertTrue($this->type->requiresSQLCommentHint($this->platform));
    }

    public function testGetMappedDatabaseTypes(): void
    {
        $this->assertSame(['_text'], $this->type->getMappedDatabaseTypes($this->platform));
    }

    public function testRoundTrip(): void
    {
        $original = ['ro', 'en', 'ru'];
        $dbValue = $this->type->convertToDatabaseValue($original, $this->platform);
        $phpValue = $this->type->convertToPHPValue($dbValue, $this->platform);
        $this->assertSame($original, $phpValue);
    }
}
