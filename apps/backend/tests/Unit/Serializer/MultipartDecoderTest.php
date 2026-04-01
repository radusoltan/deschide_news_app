<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer;

use App\Serializer\MultipartDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MultipartDecoderTest extends TestCase
{
    private RequestStack $requestStack;
    private MultipartDecoder $decoder;

    protected function setUp(): void
    {
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->decoder = new MultipartDecoder($this->requestStack);
    }

    public function testSupportsDecodingMultipartFormat(): void
    {
        $this->assertTrue($this->decoder->supportsDecoding('multipart'));
    }

    public function testDoesNotSupportOtherFormats(): void
    {
        $this->assertFalse($this->decoder->supportsDecoding('json'));
        $this->assertFalse($this->decoder->supportsDecoding('xml'));
        $this->assertFalse($this->decoder->supportsDecoding('csv'));
    }

    public function testDecodeReturnsNullWithoutRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $result = $this->decoder->decode('', 'multipart');
        $this->assertNull($result);
    }

    public function testDecodeReturnsFormData(): void
    {
        $request = new Request(
            query: [],
            request: ['title' => 'Test Image', 'alt' => 'Description'],
        );

        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->decoder->decode('', 'multipart');

        $this->assertIsArray($result);
        $this->assertSame('Test Image', $result['title']);
        $this->assertSame('Description', $result['alt']);
    }

    public function testDecodeDecodesJsonEncodedValues(): void
    {
        $request = new Request(
            query: [],
            request: ['metadata' => '{"width":800,"height":600}', 'title' => 'Simple String'],
        );

        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->decoder->decode('', 'multipart');

        $this->assertIsArray($result['metadata']);
        $this->assertSame(800, $result['metadata']['width']);
        $this->assertSame(600, $result['metadata']['height']);
        $this->assertSame('Simple String', $result['title']);
    }

    public function testDecodeIncludesUploadedFiles(): void
    {
        // Create a temporary file for the UploadedFile
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');

        $uploadedFile = new UploadedFile($tmpFile, 'image.png', 'image/png', null, true);

        $request = new Request(
            query: [],
            request: ['title' => 'With File'],
            files: ['file' => $uploadedFile],
        );

        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->decoder->decode('', 'multipart');

        $this->assertSame('With File', $result['title']);
        $this->assertInstanceOf(UploadedFile::class, $result['file']);

        @unlink($tmpFile);
    }

    public function testFormatConstant(): void
    {
        $this->assertSame('multipart', MultipartDecoder::FORMAT);
    }
}
