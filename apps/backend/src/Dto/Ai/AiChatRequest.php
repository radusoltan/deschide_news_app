<?php

declare(strict_types=1);

namespace App\Dto\Ai;

use Symfony\Component\Validator\Constraints as Assert;

final class AiChatRequest
{
    #[Assert\NotBlank(message: 'Mesajul nu poate fi gol.')]
    #[Assert\Length(max: 5000, maxMessage: 'Mesajul nu poate depăși {{ limit }} caractere.')]
    public string $message = '';

    #[Assert\Uuid(message: 'conversationId trebuie să fie un UUID valid.')]
    public ?string $conversationId = null;

    #[Assert\Uuid(message: 'templateId trebuie să fie un UUID valid.')]
    public ?string $templateId = null;

    /** @var array<string, string>|null */
    public ?array $templateFields = null;
}
