<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Image;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizer for Image entity with file upload support.
 *
 * This prevents the File object from being processed through standard serialization,
 * allowing VichUploaderBundle to handle it properly.
 */
final class ImageDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'IMAGE_DENORMALIZER_ALREADY_CALLED';

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Image
    {
        // Prevent infinite recursion
        $context[self::ALREADY_CALLED] = true;

        // Extract the file from data if present
        $file = null;
        if (isset($data['file']) && $data['file'] instanceof File) {
            $file = $data['file'];
            // Remove file from data array to prevent serializer errors
            unset($data['file']);
        }

        // Denormalize the rest of the data using the default denormalizer
        /** @var Image $object */
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        // Set the file after denormalization
        if ($file instanceof File) {
            $object->setFile($file);
        }

        return $object;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // Avoid infinite recursion
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return Image::class === $type && isset($data['file']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Image::class => true,
        ];
    }
}
