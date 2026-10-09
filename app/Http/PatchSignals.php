<?php
declare(strict_types=1);

namespace App\Http;

/** JSON merge patch (RFC 7386) of the page signals; null deletes a key. */
final class PatchSignals
{
    /** @param array<string,mixed> $signals */
    public function __construct(
        public readonly array $signals,
        public readonly bool $onlyIfMissing = false,
    ) {}

    /** Encoded with an empty patch as {} (never []). */
    public function json(): string
    {
        return json_encode(
            $this->signals === [] ? new \stdClass() : $this->signals,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
