<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Types\User;

final class UserRowVM
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $username,
        public readonly string $email,
        public readonly string $role,
        public readonly bool $isActive,
        public readonly bool $isSelf,
    ) {}

    public static function from(User $u, string $actorId): self
    {
        return new self($u->id, $u->name, $u->username, $u->email ?? '', $u->role->value, $u->isActive, $u->id === $actorId);
    }
}
