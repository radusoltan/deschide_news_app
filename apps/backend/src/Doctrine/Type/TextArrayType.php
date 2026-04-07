<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine custom type for PostgreSQL native TEXT[] arrays.
 *
 * Converts between PHP string arrays and PostgreSQL text[] column type.
 */
final class TextArrayType extends Type
{
    public const NAME = 'text_array';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TEXT[]';
    }

    /**
     * @return string[]|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if ($value === null) {
            return null;
        }

        if (\is_array($value)) {
            return $value;
        }

        $value = trim((string) $value);

        // Empty array
        if ($value === '{}') {
            return [];
        }

        // Strip outer braces
        if (!str_starts_with($value, '{') || !str_ends_with($value, '}')) {
            return [$value];
        }

        $inner = substr($value, 1, -1);

        if ($inner === '' || $inner === false) {
            return [];
        }

        // Parse PostgreSQL array format: handles quoted and unquoted values
        $result = [];
        $length = \strlen($inner);
        $i = 0;

        while ($i < $length) {
            // Skip whitespace
            while ($i < $length && $inner[$i] === ' ') {
                ++$i;
            }

            if ($i >= $length) {
                break;
            }

            if ($inner[$i] === '"') {
                // Quoted value
                ++$i;
                $element = '';
                while ($i < $length && $inner[$i] !== '"') {
                    if ($inner[$i] === '\\' && $i + 1 < $length) {
                        ++$i;
                    }
                    $element .= $inner[$i];
                    ++$i;
                }
                ++$i; // skip closing quote
                $result[] = $element;
            } else {
                // Unquoted value — read until comma or end
                $element = '';
                while ($i < $length && $inner[$i] !== ',') {
                    $element .= $inner[$i];
                    ++$i;
                }
                $trimmed = trim($element);
                if (strcasecmp($trimmed, 'NULL') !== 0) {
                    $result[] = $trimmed;
                }
            }

            // Skip comma separator
            if ($i < $length && $inner[$i] === ',') {
                ++$i;
            }
        }

        return $result;
    }

    /**
     * @param string[]|null $value
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!\is_array($value)) {
            return '{' . (string) $value . '}';
        }

        if ($value === []) {
            return '{}';
        }

        $escaped = array_map(static function (string $element): string {
            // Quote if contains special characters
            if (str_contains($element, ',') || str_contains($element, '"') || str_contains($element, '\\') || str_contains($element, '{') || str_contains($element, '}') || str_contains($element, ' ')) {
                return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $element) . '"';
            }

            return $element;
        }, $value);

        return '{' . implode(',', $escaped) . '}';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
