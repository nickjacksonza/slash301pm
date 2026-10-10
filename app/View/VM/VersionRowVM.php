<?php
declare(strict_types=1);

namespace App\View\VM;

final class VersionRowVM
{
    public function __construct(
        public readonly string $version,
        public readonly string $bump,
        public readonly string $note,
        public readonly string $by,
        public readonly string $at,
        public readonly string $url,
        public readonly bool $selected,
    ) {}
}
