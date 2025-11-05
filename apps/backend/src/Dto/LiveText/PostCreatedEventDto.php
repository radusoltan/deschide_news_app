<?php

declare(strict_types=1);

namespace App\Dto\LiveText;

use App\Entity\LiveTextPost;
use DateTime;
use DateTimeInterface;

/**
 * DTO for post.created event.
 */
class PostCreatedEventDto extends LiveTextEventDto
{
    public function __construct(
        int $liveTextId,
        public readonly int $postId,
        public readonly string $content,
        public readonly string $contentHtml,
        public readonly array $author,
        public readonly bool $isKeyPoint,
        public readonly int $position,
        public readonly DateTimeInterface $publishedAt,
        DateTimeInterface $timestamp
    ) {
        parent::__construct('post.created', $liveTextId, $timestamp);
    }

    public static function fromEntity(LiveTextPost $post): self
    {
        return new self(
            liveTextId: $post->getLiveText()->getId(),
            postId: $post->getId(),
            content: $post->getContent(),
            contentHtml: $post->getContentHtml() ?? '',
            author: [
                'id' => $post->getAuthor()->getId(),
                'username' => $post->getAuthor()->getUsername(),
                'email' => $post->getAuthor()->getEmail(),
            ],
            isKeyPoint: $post->isKeyPoint(),
            position: $post->getPosition(),
            publishedAt: $post->getPublishedAt(),
            timestamp: new DateTime()
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'liveTextId' => $this->liveTextId,
            'timestamp' => $this->timestamp->format(DateTimeInterface::ATOM),
            'post' => [
                'id' => $this->postId,
                'content' => $this->content,
                'contentHtml' => $this->contentHtml,
                'author' => $this->author,
                'isKeyPoint' => $this->isKeyPoint,
                'position' => $this->position,
                'publishedAt' => $this->publishedAt->format(DateTimeInterface::ATOM),
            ],
        ];
    }
}
