<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\ClientPersona;

/**
 * A client contact of one brand, for the staff page /brands/{id}/clients.
 * pending: invited but never accepted (inactive, no used invite).
 * Go: type ClientContact struct.
 */
final class ClientContact
{
    public function __construct(
        public readonly string $userId,
        public readonly string $name,
        public readonly string $email,
        public readonly ClientPersona $persona,
        public readonly bool $isActive,
        public readonly bool $pending,
        /** Newest unused invite's expiry (UTC), or null. */
        public readonly ?string $inviteExpiresAt,
    ) {}

    public static function fromRow(array $r): self
    {
        $email = $r['email'] !== null && $r['email'] !== '' ? (string) $r['email'] : (string) $r['username'];
        return new self(
            (string) $r['id'], (string) $r['name'], $email,
            ClientPersona::fromSignoff($r['client_signoff'] === null ? null : (int) $r['client_signoff']),
            (int) $r['is_active'] === 1,
            (int) $r['is_active'] === 0 && (int) $r['invites'] > 0 && (int) $r['used'] === 0,
            $r['open_expires'] !== null ? (string) $r['open_expires'] : null,
        );
    }
}
