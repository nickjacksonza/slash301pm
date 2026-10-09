<?php
declare(strict_types=1);

namespace App\Http;

final class RouteMatch
{
    /**
     * @param array<string,string> $values path values (decoded)
     * @param list<string> $allowed methods, for a 405
     */
    public function __construct(
        public readonly int $status,
        public readonly mixed $handler,
        public readonly array $values,
        public readonly array $allowed,
    ) {}
}
