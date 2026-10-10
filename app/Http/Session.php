<?php
declare(strict_types=1);

namespace App\Http;

/**
 * The server-side session shared with the legacy app. Keys in use (same as
 * api/auth.php): user_id, user_role, user_brand_id, last_activity, login_at,
 * csrf_token, demo_user_id. Go: an interface over the PHP session files.
 */
interface Session
{
    public function start(): void;

    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** New session id, data kept (login, identity change). */
    public function regenerate(): void;

    /** Clear data, expire the cookie, delete the session file (logout). */
    public function destroy(): void;

    /** Write and release the session lock before the response is sent. */
    public function close(): void;
}
