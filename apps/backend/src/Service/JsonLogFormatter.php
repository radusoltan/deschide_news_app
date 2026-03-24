<?php

declare(strict_types=1);

namespace App\Service;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Custom JSON log formatter that enriches every log entry
 * with request_id and locale for structured logging.
 */
final class JsonLogFormatter extends BaseJsonFormatter
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
        parent::__construct();
    }

    public function format(LogRecord $record): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $extra = $record->extra;
        $extra['request_id'] = $request?->headers->get('X-Request-Id')
            ?? $request?->attributes->get('_request_id')
            ?? substr(bin2hex(random_bytes(8)), 0, 16);
        $extra['locale'] = $request?->getLocale() ?? 'ro';
        $extra['client_ip'] = $request?->getClientIp();
        $extra['method'] = $request?->getMethod();
        $extra['uri'] = $request?->getRequestUri();

        $enriched = new LogRecord(
            datetime: $record->datetime,
            channel: $record->channel,
            level: $record->level,
            message: $record->message,
            context: $record->context,
            extra: $extra,
            formatted: $record->formatted,
        );

        return parent::format($enriched);
    }
}
