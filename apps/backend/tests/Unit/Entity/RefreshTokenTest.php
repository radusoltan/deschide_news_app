<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;
use PHPUnit\Framework\TestCase;

class RefreshTokenTest extends TestCase
{
    public function testRefreshTokenExtendsBaseRefreshToken(): void
    {
        $refreshToken = new RefreshToken();

        $this->assertInstanceOf(BaseRefreshToken::class, $refreshToken);
    }

    public function testDefaultValues(): void
    {
        $refreshToken = new RefreshToken();

        $this->assertNull($refreshToken->getId());
        $this->assertNull($refreshToken->getRefreshToken());
        $this->assertNull($refreshToken->getUsername());
        $this->assertNull($refreshToken->getValid());
    }

    public function testSetAndGetRefreshToken(): void
    {
        $refreshToken = new RefreshToken();
        $token = 'abc123def456';

        $result = $refreshToken->setRefreshToken($token);

        $this->assertSame($refreshToken, $result);
        $this->assertSame($token, $refreshToken->getRefreshToken());
    }

    public function testSetAndGetUsername(): void
    {
        $refreshToken = new RefreshToken();

        $result = $refreshToken->setUsername('admin@example.com');

        $this->assertSame($refreshToken, $result);
        $this->assertSame('admin@example.com', $refreshToken->getUsername());
    }

    public function testSetAndGetValid(): void
    {
        $refreshToken = new RefreshToken();
        $validDate = new \DateTime('+1 hour');

        $result = $refreshToken->setValid($validDate);

        $this->assertSame($refreshToken, $result);
        $this->assertSame($validDate, $refreshToken->getValid());
    }

    public function testIsValidWithFutureDate(): void
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setValid(new \DateTime('+1 hour'));

        $this->assertTrue($refreshToken->isValid());
    }

    public function testIsValidWithPastDate(): void
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setValid(new \DateTime('-1 hour'));

        $this->assertFalse($refreshToken->isValid());
    }

    public function testIsValidWithNullDate(): void
    {
        $refreshToken = new RefreshToken();

        $this->assertFalse($refreshToken->isValid());
    }

    public function testToStringWithToken(): void
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setRefreshToken('my-token-value');

        $this->assertSame('my-token-value', (string) $refreshToken);
    }

    public function testToStringWithoutToken(): void
    {
        $refreshToken = new RefreshToken();

        $this->assertSame('', (string) $refreshToken);
    }
}
