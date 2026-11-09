<?php
declare(strict_types=1);

namespace App\Auth;

use App\Clock\Clock;
use App\Domain\LoginRules;
use App\Domain\Types\User;
use App\Store\UserStore;

/**
 * The app's own username and password sign-in, shared with the legacy app
 * (api/auth.php): bcrypt hashes in users.password_hash, active users only,
 * 12 to 128 byte passwords. Throttling stays in AuthHandlers (login_attempts).
 * Go: type PasswordProvider struct.
 */
final class PasswordIdentityProvider implements IdentityProvider
{
    /** Real bcrypt (cost 12, like the live hashes) of a throwaway string, so unknown usernames cost the same time. */
    public const DUMMY_HASH = '$2y$12$MoMuqcrClPRL3B.gkzAdKe7.tV.9EdZHafEtcB2BZ6PGT55UKBTsm';

    public function __construct(private readonly UserStore $users, private readonly Clock $clock) {}

    public function name(): string
    {
        return 'password';
    }

    public function authenticate(string $username, string $secret): ?User
    {
        $user = LoginRules::passwordLengthOk($secret) ? $this->users->findByUsername($username) : null;
        $hash = $user !== null ? ($this->users->passwordHash($user->id) ?? self::DUMMY_HASH) : self::DUMMY_HASH;
        $valid = password_verify($secret, $hash);   // always runs, so timing does not reveal usernames
        if ($user === null || !$valid) {
            return null;
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            // A hash upgrade at sign-in is a system write: no activity row (the login route is exempt).
            $this->users->setPassword($user->id, password_hash($secret, PASSWORD_DEFAULT), null, 'password_rehashed', $this->clock->now());
        }
        return $user;
    }

    public function hashSecret(string $secret): string
    {
        return password_hash($secret, PASSWORD_DEFAULT);
    }
}
