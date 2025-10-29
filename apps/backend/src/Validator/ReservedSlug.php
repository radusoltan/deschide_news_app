<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Reserved Slug Constraint
 *
 * Validates that a slug is not in the reserved slugs list.
 * Reserved slugs are used for system pages and cannot be used for categories.
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ReservedSlug extends Constraint
{
    public string $message = 'The slug "{{ slug }}" is reserved for system pages and cannot be used.';

    /**
     * List of reserved slugs that cannot be used for categories
     */
    public const RESERVED_SLUGS = [
        // Special pages
        'all',
        'search',
        'trending',
        'archive',

        // Static pages
        'about',
        'contact',
        'privacy',
        'terms',

        // User-related
        'author',
        'authors',
        'profile',

        // Admin
        'admin',
        'login',
        'logout',
        'register',

        // API
        'api',

        // Other
        'sitemap',
        'robots',
        'feed',
        'rss',
    ];

    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
