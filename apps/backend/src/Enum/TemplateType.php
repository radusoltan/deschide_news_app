<?php

declare(strict_types=1);

namespace App\Enum;

enum TemplateType: string
{
    /**
     * Get all template types as array.
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get label for each template type.
     */
    public function getLabel(string $locale = 'ro'): string
    {
        return match ($this) {
            self::BREAKING_NEWS => match ($locale) {
                'en' => 'Breaking News',
                'ru' => 'Срочные новости',
                default => 'Știri de Ultimă Oră'
            },
            self::SPORT => match ($locale) {
                'en' => 'Sport Event',
                'ru' => 'Спортивное событие',
                default => 'Eveniment Sportiv'
            },
            self::CONFERENCE => match ($locale) {
                'en' => 'Conference',
                'ru' => 'Конференция',
                default => 'Conferință'
            },
            self::ELECTION => match ($locale) {
                'en' => 'Election',
                'ru' => 'Выборы',
                default => 'Alegeri'
            },
        };
    }

    /**
     * Get description for each template type.
     */
    public function getDescription(string $locale = 'ro'): string
    {
        return match ($this) {
            self::BREAKING_NEWS => match ($locale) {
                'en' => 'For urgent breaking news coverage with red theme',
                'ru' => 'Для срочных новостей с красной темой',
                default => 'Pentru știri urgente cu temă roșie'
            },
            self::SPORT => match ($locale) {
                'en' => 'For live sports events with score tracking',
                'ru' => 'Для спортивных событий с отслеживанием счета',
                default => 'Pentru evenimente sportive cu urmărire scor'
            },
            self::CONFERENCE => match ($locale) {
                'en' => 'For conferences and speeches with speaker tracking',
                'ru' => 'Для конференций и выступлений',
                default => 'Pentru conferințe și discursuri'
            },
            self::ELECTION => match ($locale) {
                'en' => 'For election coverage with results tracking',
                'ru' => 'Для освещения выборов с отслеживанием результатов',
                default => 'Pentru acoperire electorală cu urmărire rezultate'
            },
        };
    }

    /**
     * Get icon for each template type.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::BREAKING_NEWS => '🚨',
            self::SPORT => '⚽',
            self::CONFERENCE => '🎤',
            self::ELECTION => '🗳️',
        };
    }
    case BREAKING_NEWS = 'breaking_news';
    case SPORT = 'sport';
    case CONFERENCE = 'conference';
    case ELECTION = 'election';
}
