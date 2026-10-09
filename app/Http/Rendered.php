<?php
declare(strict_types=1);

namespace App\Http;

/**
 * A Response turned into bytes, before anything is sent. Tests read it;
 * Response::send() writes it. Each chunk is flushed on its own.
 */
final class Rendered
{
    /**
     * @param array<string,string> $headers
     * @param list<string> $chunks
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly array $chunks,
        public readonly int $pauseMs,
    ) {}

    public function body(): string
    {
        return implode('', $this->chunks);
    }

    public function header(string $name): string
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return $v;
            }
        }
        return '';
    }
}
