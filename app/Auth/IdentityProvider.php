<?php
declare(strict_types=1);

namespace App\Auth;

use App\Domain\Types\User;

/**
 * The identity-provider seam (ADR 0006). An identity provider only proves who
 * someone is; roles, brands and permissions stay in our database, and the
 * session is always ours (AuthHandlers::startLogin).
 *
 * Today one implementation serves staff and clients: PasswordIdentityProvider
 * (users.password_hash, bcrypt). A later OIDC provider (Authorizer) for the
 * Client role would implement this interface too: its callback resolves the
 * verified subject to an active Client user through users.auth_subject
 * (migration 0015), never creating users, and the login page sends Client
 * contacts to it instead of the password form. Nothing of Authorizer is built.
 * Go: type IdentityProvider interface.
 */
interface IdentityProvider
{
    /** A short stable name ('password', later 'oidc'). */
    public function name(): string;

    /**
     * The active user these credentials prove, or null. Unknown users and
     * wrong secrets cost the same work, so timing does not reveal usernames.
     */
    public function authenticate(string $username, string $secret): ?User;

    /**
     * The value to store for a new secret the user chose (invite accept). The
     * caller validates the secret first (LoginRules).
     */
    public function hashSecret(string $secret): string;
}
