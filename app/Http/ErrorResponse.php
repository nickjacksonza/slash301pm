<?php
declare(strict_types=1);

namespace App\Http;

/**
 * What the user sees when something unexpected fails: a generic message and
 * the request id, never the exception, a path or a trace (those go to
 * data/logs via App\Logs\AppLog). Datastar actions get HTTP 200 with an error
 * toast, because Datastar ignores the body of any other status (ADR 0005).
 */
final class ErrorResponse
{
    public static function message(string $requestId): string
    {
        return 'Something went wrong on our side. Try again in a moment. If it keeps happening, quote reference ' . $requestId . '.';
    }

    public static function for(bool $datastar, string $requestId): Response
    {
        if ($datastar) {
            return Response::events(Toast::error(self::message($requestId)))->withHeader('X-Request-Id', $requestId);
        }
        return Response::page(page_error(500, 'Server error', self::message($requestId)), 500)->withHeader('X-Request-Id', $requestId);
    }
}
