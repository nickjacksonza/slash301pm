<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Login and password rules, shared with the legacy app (api/auth.php):
 * 12 to 128 bytes, 10 failed attempts per 15 minutes.
 */
final class LoginRules
{
    public const MIN_PASSWORD = 12;
    public const MAX_PASSWORD = 128;
    public const MAX_FAILURES = 10;
    public const WINDOW_SECONDS = 900;

    /** Same check as legacy handleLogin(): strlen, so bytes not characters. */
    public static function passwordLengthOk(string $password): bool
    {
        $n = strlen($password);
        return $n >= self::MIN_PASSWORD && $n <= self::MAX_PASSWORD;
    }

    /**
     * Seconds until another attempt is allowed, or 0.
     * $oldestFailureAt is the oldest failure inside the window (unix seconds).
     */
    public static function retryAfter(int $failuresInWindow, ?int $oldestFailureAt, int $now): int
    {
        if ($failuresInWindow < self::MAX_FAILURES || $oldestFailureAt === null) {
            return 0;
        }
        return max(1, ($oldestFailureAt + self::WINDOW_SECONDS) - $now);
    }

    /** Key for the per-username counter, stored in login_attempts.ip next to plain IPs. */
    public static function usernameKey(string $username): string
    {
        return 'user:' . strtolower(trim($username));
    }

    public static function validateNewPassword(string $new, string $confirm): ValidationErrors
    {
        $errors = new ValidationErrors();
        if (!self::passwordLengthOk($new)) {
            $errors = $errors->with('new', 'The new password must be 12 to 128 characters.');
        }
        if ($new !== $confirm) {
            $errors = $errors->with('confirm', 'The two new passwords do not match.');
        }
        return $errors;
    }
}
