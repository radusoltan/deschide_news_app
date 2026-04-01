<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer;

use App\Entity\Image;
use App\Serializer\ImageDenormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ImageDenormalizerTest extends TestCase
{
    private ImageDenormalizer $denormalizer;
    private DenormalizerInterface $innerDenormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new ImageDenormalizer();
        $this->innerDenormalizer = $this->createMock(DenormalizerInterface::class);
        $this->denormalizer->setDenormalizer($this->innerDenormalizer);
    }

    public function testSupportsDenormalizationWithFile(): void
    {
        $data = ['file' => $this->createStub(File::class), 'alt' => 'test'];

        $result = $this->denormalizer->supportsDenormalization($data, Image::class);
        $this->assertTrue($result);
    }

    public function testDoesNotSupportDenormalizationWithoutFile(): void
    {
        $data = ['alt' => 'test'];

        $result = $this->denormalizer->supportsDenormalization($data, Image::class);
        $this->assertFalse($result);
    }

    public function testDoesNotSupportDenormalizationForOtherTypes(): void
    {
        $data = ['file' => $this->createStub(File::class)];

        $result = $this->denormalizer->supportsDenormalization($data, \stdClass::class);
        $this->assertFalse($result);
    }

    public function testPreventsInfiniteRecursion(): void
    {
        $data = ['file' => $this->createStub(File::class)];
        $context = ['IMAGE_DENORMALIZER_ALREADY_CALLED' => true];

        $result = $this->denormalizer->supportsDenormalization($data, Image::class, null, $context);
        $this->assertFalse($result);
    }

    public function testDenormalizeExtractsFileAndSetsOnImage(): void
    {
        $file = $this->createStub(File::class);
        $image = $this->createMock(Image::class);

        $image->expects($this->once())
            ->method('setFile')
            ->with($file);

        $this->innerDenormalizer->expects($this->once())
            ->method('denormalize')
            ->with(
                ['alt' => 'test image'], // file should be removed from data
                Image::class,
                null,
                $this->callback(function ($context) {
                    return $context['IMAGE_DENORMALIZER_ALREADY_CALLED'] === true;
                })
            )
            ->willReturn($image);

        $data = ['file' => $file, 'alt' => 'test image'];
        $result = $this->denormalizer->denormalize($data, Image::class);

        $this->assertSame($image, $result);
    }

    public function testDenormalizeWithoutFile(): void
    {
        $image = $this->createStub(Image::class);

        $this->innerDenormalizer->expects($this->once())
            ->method('denormalize')
            ->willReturn($image);

        $data = ['alt' => 'no file'];
        $result = $this->denormalizer->denormalize($data, Image::class);

        $this->assertSame($image, $result);
    }

    public function testGetSupportedTypes(): void
    {
        $types = $this->denormalizer->getSupportedTypes(null);

        $this->assertArrayHasKey(Image::class, $types);
        $this->assertTrue($types[Image::class]);
    }
}
