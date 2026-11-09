<?php
declare(strict_types=1);

namespace App\Auth;

use App\Domain\InviteRules;

/**
 * Makes invite tokens: 32 random bytes, base64url without padding (43
 * characters). The token is shown once and mailed; only InviteRules::hash()
 * of it is stored. Never log a token. Go: func NewInviteToken() string.
 */
final class InviteTokens
{
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(InviteRules::TOKEN_BYTES)), '+/', '-_'), '=');
    }

    /** An unusable password hash for an invited user who has not chosen a password yet. */
    public static function unusablePasswordHash(): string
    {
        return password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    }
}
