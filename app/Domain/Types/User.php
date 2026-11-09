<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Role;

/**
 * A row of users without the password hash. Go: type User struct.
 * clientSignoff: users.client_signoff (migration 0008; NULL reads as 1), true
 * for a Brand Director. Only meaningful for the Client role, and only read
 * from rows that select the column (UserStore does); otherwise false (deny).
 */
final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $name,
        public readonly ?string $email,
        public readonly Role $role,
        public readonly string $color,
        public readonly ?string $brandId,
        public readonly bool $isActive,
        public readonly bool $clientSignoff = false,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (string) $r['id'],
            (string) $r['username'],
            (string) $r['name'],
            $r['email'] !== null ? (string) $r['email'] : null,
            Role::from((string) $r['role']),
            $r['color'] !== null ? (string) $r['color'] : '#3b82f6',
            $r['brand_id'] !== null && $r['brand_id'] !== '' ? (string) $r['brand_id'] : null,
            (int) $r['is_active'] === 1,
            array_key_exists('client_signoff', $r) && ($r['client_signoff'] === null || (int) $r['client_signoff'] === 1),
        );
    }
}
