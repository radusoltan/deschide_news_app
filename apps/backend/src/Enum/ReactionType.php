<?php

declare(strict_types=1);

namespace App\Enum;

enum ReactionType: string
{
    /**
     * Get all reaction types as array.
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get emoji representation for each reaction.
     */
    public function getEmoji(): string
    {
        return match ($this) {
            self::LIKE => '👍',
            self::LOVE => '❤️',
            self::WOW => '😮',
            self::SAD => '😢',
            self::ANGRY => '😠',
        };
    }

    /**
     * Get label for each reaction.
     */
    public function getLabel(string $locale = 'ro'): string
    {
        return match ($this) {
            self::LIKE => match ($locale) {
                'en' => 'Like',
                'ru' => 'Нравится',
                default => 'Like'
            },
            self::LOVE => match ($locale) {
                'en' => 'Love',
                'ru' => 'Любовь',
                default => 'Iubire'
            },
            self::WOW => match ($locale) {
                'en' => 'Wow',
                'ru' => 'Вау',
                default => 'Uau'
            },
            self::SAD => match ($locale) {
                'en' => 'Sad',
                'ru' => 'Грустно',
                default => 'Trist'
            },
            self::ANGRY => match ($locale) {
                'en' => 'Angry',
                'ru' => 'Злюсь',
                default => 'Furios'
            },
        };
    }
    case LIKE = 'like';
    case LOVE = 'love';
    case WOW = 'wow';
    case SAD = 'sad';
    case ANGRY = 'angry';
}
