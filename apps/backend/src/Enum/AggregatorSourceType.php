<?php

declare(strict_types=1);

namespace App\Enum;

enum AggregatorSourceType: string
{
    case GOOGLE_NEWS_RSS = 'google_news_rss';
    case GOOGLE_ALERTS = 'google_alerts';
    case NEWS_API = 'news_api';
    case BING_NEWS = 'bing_news';
    case TELEGRAM = 'telegram';
    case FACEBOOK_RSS = 'facebook_rss';
    case DIRECT_PORTAL = 'direct_portal';
    case GNEWS = 'gnews';
}
