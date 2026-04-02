<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->user->getId());
        $this->assertNull($this->user->getUsername());
        $this->assertNull($this->user->getEmail());
        $this->assertNull($this->user->getFirstName());
        $this->assertNull($this->user->getLastName());
        $this->assertNull($this->user->getPassword());
        $this->assertNull($this->user->getPlainPassword());
        $this->assertTrue($this->user->isActive());
    }

    public function testSetGetUsername(): void
    {
        $result = $this->user->setUsername('johndoe');
        $this->assertSame('johndoe', $this->user->getUsername());
        $this->assertSame($this->user, $result);
    }

    public function testSetGetEmail(): void
    {
        $result = $this->user->setEmail('john@example.com');
        $this->assertSame('john@example.com', $this->user->getEmail());
        $this->assertSame($this->user, $result);
    }

    public function testSetGetFirstName(): void
    {
        $result = $this->user->setFirstName('John');
        $this->assertSame('John', $this->user->getFirstName());
        $this->assertSame($this->user, $result);
    }

    public function testSetGetFirstNameNull(): void
    {
        $this->user->setFirstName('John');
        $this->user->setFirstName(null);
        $this->assertNull($this->user->getFirstName());
    }

    public function testSetGetLastName(): void
    {
        $result = $this->user->setLastName('Doe');
        $this->assertSame('Doe', $this->user->getLastName());
        $this->assertSame($this->user, $result);
    }

    public function testSetGetLastNameNull(): void
    {
        $this->user->setLastName('Doe');
        $this->user->setLastName(null);
        $this->assertNull($this->user->getLastName());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testSetGetRoles(): void
    {
        $result = $this->user->setRoles(['ROLE_ADMIN']);
        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
        $this->assertSame($this->user, $result);
    }

    public function testRolesAreUnique(): void
    {
        $this->user->setRoles(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);
        $roles = $this->user->getRoles();
        $this->assertCount(2, $roles);
    }

    public function testSetGetPassword(): void
    {
        $result = $this->user->setPassword('hashed_password');
        $this->assertSame('hashed_password', $this->user->getPassword());
        $this->assertSame($this->user, $result);
    }

    public function testSetGetPlainPassword(): void
    {
        $result = $this->user->setPlainPassword('my_secret');
        $this->assertSame('my_secret', $this->user->getPlainPassword());
        $this->assertSame($this->user, $result);
    }

    public function testSetGetPlainPasswordNull(): void
    {
        $this->user->setPlainPassword('test');
        $this->user->setPlainPassword(null);
        $this->assertNull($this->user->getPlainPassword());
    }

    public function testGetUserIdentifier(): void
    {
        $this->user->setUsername('testuser');
        $this->assertSame('testuser', $this->user->getUserIdentifier());
    }

    public function testGetUserIdentifierWhenUsernameNull(): void
    {
        $this->assertSame('', $this->user->getUserIdentifier());
    }

    public function testSetGetIsActive(): void
    {
        $result = $this->user->setIsActive(false);
        $this->assertFalse($this->user->isActive());
        $this->assertSame($this->user, $result);
    }

    public function testEraseCredentials(): void
    {
        $this->user->setPlainPassword('secret');
        $this->user->eraseCredentials();
        $this->assertNull($this->user->getPlainPassword());
    }

    public function testSerialize(): void
    {
        $this->user->setPassword('real_hash');
        $data = $this->user->__serialize();
        $this->assertIsArray($data);
        // The serialized password should be a CRC32C hash, not the actual password
        $passwordKey = "\0" . User::class . "\0password";
        $this->assertArrayHasKey($passwordKey, $data);
        $this->assertNotSame('real_hash', $data[$passwordKey]);
    }

    public function testSetGetEmptyStringUsername(): void
    {
        $this->user->setUsername('');
        $this->assertSame('', $this->user->getUsername());
    }
}
