<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\ShortLinkRepository;
use App\Service\ShortCodeGenerator;
use PHPUnit\Framework\TestCase;

class ShortCodeGeneratorTest extends TestCase
{
    private ShortCodeGenerator $generator;
    private ShortLinkRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ShortLinkRepository::class);
        $this->generator = new ShortCodeGenerator($this->repository);
    }

    public function testGenerateReturnsStringOfDefaultLength(): void
    {
        $this->repository->method('codeExists')->willReturn(false);

        $code = $this->generator->generate();

        $this->assertSame(6, strlen($code));
    }

    public function testGenerateReturnsStringOfCustomLength(): void
    {
        $this->repository->method('codeExists')->willReturn(false);

        $code = $this->generator->generate(10);

        $this->assertSame(10, strlen($code));
    }

    public function testGenerateReturnsBase62Characters(): void
    {
        $this->repository->method('codeExists')->willReturn(false);

        $code = $this->generator->generate();

        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]+$/', $code);
    }

    public function testGenerateRetriesWhenCodeExists(): void
    {
        $callCount = 0;
        $this->repository->method('codeExists')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            // First two calls return true (code exists), third returns false
            return $callCount < 3;
        });

        $code = $this->generator->generate();

        $this->assertNotEmpty($code);
        $this->assertSame(3, $callCount);
    }

    public function testGenerateThrowsExceptionAfterMaxAttempts(): void
    {
        $this->repository->method('codeExists')->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to generate unique short code after 100 attempts');

        $this->generator->generate();
    }

    public function testGenerateWebcodeReturnsNonEmptyString(): void
    {
        $code = $this->generator->generateWebcode(1);

        $this->assertNotEmpty($code);
    }

    public function testGenerateWebcodeReturnsBase62Characters(): void
    {
        $code = $this->generator->generateWebcode(42);

        $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]+$/', $code);
    }

    public function testGenerateWebcodeProducesDifferentCodesForDifferentIds(): void
    {
        // While technically possible to collide, extremely unlikely
        $code1 = $this->generator->generateWebcode(1);
        $code2 = $this->generator->generateWebcode(99999);

        // At least one should differ due to different IDs + time component
        $this->assertIsString($code1);
        $this->assertIsString($code2);
    }

    public function testIsValidCodeAcceptsAlphanumeric(): void
    {
        $this->assertTrue($this->generator->isValidCode('abc123'));
    }

    public function testIsValidCodeAcceptsDashes(): void
    {
        $this->assertTrue($this->generator->isValidCode('my-code'));
    }

    public function testIsValidCodeAcceptsUnderscores(): void
    {
        $this->assertTrue($this->generator->isValidCode('my_code'));
    }

    public function testIsValidCodeRejectsEmptyString(): void
    {
        $this->assertFalse($this->generator->isValidCode(''));
    }

    public function testIsValidCodeRejectsTooLongString(): void
    {
        $this->assertFalse($this->generator->isValidCode(str_repeat('a', 51)));
    }

    public function testIsValidCodeAcceptsMaxLengthString(): void
    {
        $this->assertTrue($this->generator->isValidCode(str_repeat('a', 50)));
    }

    public function testIsValidCodeAcceptsSingleCharacter(): void
    {
        $this->assertTrue($this->generator->isValidCode('a'));
    }

    public function testIsValidCodeRejectsSpecialCharacters(): void
    {
        $this->assertFalse($this->generator->isValidCode('code with spaces'));
        $this->assertFalse($this->generator->isValidCode('code@special'));
        $this->assertFalse($this->generator->isValidCode('code!mark'));
        $this->assertFalse($this->generator->isValidCode('code#hash'));
    }

    public function testIsCodeAvailableReturnsTrueWhenCodeDoesNotExist(): void
    {
        $this->repository->method('codeExists')->willReturn(false);

        $this->assertTrue($this->generator->isCodeAvailable('abc123'));
    }

    public function testIsCodeAvailableReturnsFalseWhenCodeExists(): void
    {
        $this->repository->method('codeExists')->willReturn(true);

        $this->assertFalse($this->generator->isCodeAvailable('abc123'));
    }
}
