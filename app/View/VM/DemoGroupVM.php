<?php
declare(strict_types=1);

namespace App\View\VM;

final class DemoGroupVM
{
    /** @param list<DemoUserVM> $users */
    public function __construct(
        public readonly string $role,
        public readonly array $users,
    ) {}
}
