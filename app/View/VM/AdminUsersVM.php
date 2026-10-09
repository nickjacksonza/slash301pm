<?php
declare(strict_types=1);

namespace App\View\VM;

final class AdminUsersVM
{
    /**
     * @param list<UserRowVM> $rows
     * @param list<string> $roles
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $roles,
    ) {}
}
