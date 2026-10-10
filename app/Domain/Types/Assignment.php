<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Role;

/** One job_assignments slot: a role on a job held by one user. */
final class Assignment
{
    public function __construct(
        public readonly Role $role,
        public readonly string $userId,
        public readonly string $userName,
        public readonly Role $userRole,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(Role::from((string) $r['role_on_job']), (string) $r['user_id'], (string) $r['user_name'], Role::from((string) $r['user_role']));
    }
}
