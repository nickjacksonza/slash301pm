<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\ClientPersona;

/**
 * An invites row (migration 0015) with the invited contact's details. Times
 * are UTC 'Y-m-d H:i:s'. Never carries the token, only its hash.
 * Go: type Invite struct.
 */
final class Invite
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $tokenHash,
        public readonly string $expiresAt,
        public readonly ?string $usedAt,
        public readonly string $userName,
        public readonly string $username,
        public readonly ?string $brandId,
        public readonly string $brandName,
        public readonly ClientPersona $persona,
    ) {}

    public static function fromRow(array $r): self
    {
        return new self(
            (string) $r['id'], (string) $r['user_id'], (string) $r['token_hash'], (string) $r['expires_at'],
            $r['used_at'] !== null ? (string) $r['used_at'] : null,
            (string) $r['user_name'], (string) $r['username'],
            $r['brand_id'] !== null && $r['brand_id'] !== '' ? (string) $r['brand_id'] : null,
            (string) ($r['brand_name'] ?? ''),
            ClientPersona::fromSignoff($r['client_signoff'] === null ? null : (int) $r['client_signoff']),
        );
    }
}
