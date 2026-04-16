<?php

declare(strict_types=1);

namespace App\Enum;

enum TopicDetectionMethod: string
{
    case KEYWORD = 'keyword';
    case LLM = 'llm';
    case MANUAL = 'manual';
}
