<?php

declare(strict_types=1);

namespace App\Dto\LiveText;

use App\Entity\LiveTextPost;
use DateTime;
use DateTimeInterface;

/**
 * DTO for post.updated event.
 */
class PostUpdatedEventDto extends LiveTextEventDto
{
    public function __construct(
        int $liveTextId,
        public readonly int $postId,
        public readonly string $content,
        public readonly string $contentHtml,
        public readonly bool $isKeyPoint,
        public readonly int $position,
        DateTimeInterface $timestamp
    ) {
        parent::__construct('post.updated', $liveTextId, $timestamp);
    }

    public static function fromEntity(LiveTextPost $post): self
    {
        return new self(
            liveTextId: $post->getLiveText()->getId(),
            postId: $post->getId(),
            content: $post->getContent(),
            contentHtml: $post->getContentHtml() ?? '',
            isKeyPoint: $post->isKeyPoint(),
            position: $post->getPosition(),
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
                'isKeyPoint' => $this->isKeyPoint,
                'position' => $this->position,
            ],
        ];
    }
}
