<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Entity\Image;
use App\Factory\ImageFactory;
use PHPUnit\Framework\TestCase;

class ImageFactoryTest extends TestCase
{
    public function testClassReturnsImageClass(): void
    {
        $this->assertSame(Image::class, ImageFactory::class());
    }

    public function testFactoryCanBeInstantiated(): void
    {
        $factory = new ImageFactory();
        $this->assertInstanceOf(ImageFactory::class, $factory);
    }
}
