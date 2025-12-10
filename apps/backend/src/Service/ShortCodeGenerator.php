<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ShortLinkRepository;

/**
 * Generates unique short codes for short links.
 *
 * Uses Base62 encoding (alphanumeric characters) for compact,
 * URL-safe codes. Ensures uniqueness by checking against existing codes.
 */
class ShortCodeGenerator
{
    /**
     * Base62 character set (0-9, a-z, A-Z).
     */
    private const CHARSET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Default length for generated codes.
     */
    private const DEFAULT_LENGTH = 6;

    /**
     * Maximum attempts to generate a unique code.
     */
    private const MAX_ATTEMPTS = 100;

    public function __construct(
        private readonly ShortLinkRepository $shortLinkRepository
    ) {
    }

    /**
     * Generate a unique short code.
     *
     * @param int $length Length of the generated code (default: 6)
     *
     * @return string A unique short code
     *
     * @throws \RuntimeException If unable to generate a unique code after max attempts
     */
    public function generate(int $length = self::DEFAULT_LENGTH): string
    {
        $attempts = 0;

        do {
            $code = $this->generateRandomCode($length);
            ++$attempts;

            if ($attempts >= self::MAX_ATTEMPTS) {
                throw new \RuntimeException('Unable to generate unique short code after ' . self::MAX_ATTEMPTS . ' attempts');
            }
        } while ($this->shortLinkRepository->codeExists($code));

        return $code;
    }

    /**
     * Generate a unique short code for an article webcode.
     *
     * This uses a slightly different approach - it generates based on
     * article ID with some randomness to ensure uniqueness even for
     * concurrent article creation.
     *
     * @param int $articleId The article ID (used as seed)
     *
     * @return string A unique webcode
     */
    public function generateWebcode(int $articleId): string
    {
        // Combine article ID with timestamp for uniqueness
        $seed = $articleId . time() . random_int(0, 999);

        // Base62 encode a hash of the seed
        $hash = abs(crc32($seed));

        return $this->encodeBase62($hash);
    }

    /**
     * Validate a custom short code.
     *
     * @param string $code The code to validate
     *
     * @return bool True if valid, false otherwise
     */
    public function isValidCode(string $code): bool
    {
        // Check length
        if (\strlen($code) < 1 || \strlen($code) > 50) {
            return false;
        }

        // Check characters (only alphanumeric, dashes, underscores)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a code is available (not already used).
     *
     * @param string $code The code to check
     *
     * @return bool True if available, false if already in use
     */
    public function isCodeAvailable(string $code): bool
    {
        return !$this->shortLinkRepository->codeExists($code);
    }

    /**
     * Generate a random Base62 code.
     *
     * @param int $length Length of the code
     *
     * @return string Random Base62 code
     */
    private function generateRandomCode(int $length): string
    {
        $charsetLength = \strlen(self::CHARSET);
        $code = '';

        for ($i = 0; $i < $length; ++$i) {
            $code .= self::CHARSET[random_int(0, $charsetLength - 1)];
        }

        return $code;
    }

    /**
     * Encode a number in Base62.
     *
     * @param int $number The number to encode
     *
     * @return string Base62 encoded string
     */
    private function encodeBase62(int $number): string
    {
        if ($number === 0) {
            return self::CHARSET[0];
        }

        $result = '';
        $base = \strlen(self::CHARSET);

        while ($number > 0) {
            $result = self::CHARSET[$number % $base] . $result;
            $number = (int) ($number / $base);
        }

        return $result;
    }
}
