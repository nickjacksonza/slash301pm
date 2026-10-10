<?php
declare(strict_types=1);

namespace App\View\VM;

final class LoginVM
{
    /** @param list<DemoGroupVM> $demoGroups empty unless demo mode is on */
    public function __construct(
        public readonly string $csrf,
        public readonly string $username,
        public readonly string $error,
        public readonly string $notice,
        public readonly bool $demoMode,
        public readonly array $demoGroups,
    ) {}
}
