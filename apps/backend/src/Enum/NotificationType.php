<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case ARTICLE_PUBLISHED = 'article_published';
    case ARTICLE_UPDATED = 'article_updated';
    case USER_LOGIN = 'user_login';
    case USER_ACTION = 'user_action';
    case SYSTEM_ERROR = 'system_error';
    case JOB_FAILED = 'job_failed';
    case ARTICLE_AUTO_CREATED = 'article_auto_created';
    case ARTICLE_TRANSLATED = 'article_translated';
}
