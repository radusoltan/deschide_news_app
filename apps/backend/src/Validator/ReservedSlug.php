<?php

declare(strict_types=1);

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Reserved Slug Constraint.
 *
 * Validates that a slug is not in the reserved slugs list.
 * Reserved slugs are used for system pages and cannot be used for categories.
 *
 * @Annotation
 *
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ReservedSlug extends Constraint
{
    /**
     * List of reserved slugs that cannot be used for categories.
     *
     * These slugs are reserved for system pages as defined in url-structure-APPROVED.md
     * Total: 18 reserved slugs
     */
    public const RESERVED_SLUGS = [
        's',        // Short link redirects (/s/{code})
        'all',
        'search',
        'trending',
        'archive',
        'about',
        'contact',
        'author',
        'authors',
        'admin',
        'login',
        'api',
        'sitemap',
        'robots',
        'feed',
        'rss',
        'privacy',
        'terms',
    ];

    public string $message = 'The slug "{{ slug }}" is reserved for system pages and cannot be used.';

    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
