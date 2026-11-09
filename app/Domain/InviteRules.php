<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\Invite;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Client invite links (docs/portal.md). A link carries a 32-byte random token
 * (base64url, 43 characters, made by App\Auth\InviteTokens); only its sha256
 * is stored. A link works once, for 7 days. Every failure looks the same to
 * the visitor (unknown, used, expired, malformed), and the accept page is rate
 * limited per IP through rate_limits. Pure: the caller passes the time.
 * Go: package domain.
 */
final class InviteRules
{
    public const TOKEN_BYTES = 32;
    public const TTL_SECONDS = 7 * 86400;
    /** rate_limits bucket for GET and POST /invite/{token}, per client IP. */
    public const BUCKET = 'invite';
    public const ATTEMPTS_PER_WINDOW = 20;
    public const WINDOW_SECONDS = 900;

    public const VERB_INVITED = 'client_invited';
    public const VERB_REISSUED = 'client_invite_reissued';
    public const VERB_ACCEPTED = 'client_invite_accepted';

    /** The one message for every link that cannot be used. */
    public const GENERIC_ERROR = 'This invite link cannot be used. It may have expired or been used already. Ask your account manager for a new one.';

    /** A token as InviteTokens makes it: 43 base64url characters. */
    public static function tokenShapeOk(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1;
    }

    /** sha256 hex, the only form of the token that is stored. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function expiresAt(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->modify('+' . self::TTL_SECONDS . ' seconds');
    }

    /**
     * May this stored invite be accepted with this token now? Constant-time
     * comparison of the hashes; expiry compared in UTC.
     */
    public static function usable(?Invite $i, string $token, DateTimeImmutable $now): bool
    {
        if ($i === null || !self::tokenShapeOk($token)) {
            return false;
        }
        if (!hash_equals($i->tokenHash, self::hash($token))) {
            return false;
        }
        if ($i->usedAt !== null) {
            return false;
        }
        $nowUtc = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        return $i->expiresAt > $nowUtc;
    }

    /** The link sent to the client: the configured public base URL, never the request's Host header. */
    public static function link(string $publicBaseUrl, string $token): string
    {
        return rtrim($publicBaseUrl, '/') . '/invite/' . $token;
    }

    /** Request paths as they may be written to a log: the token of /invite/{token} is replaced. */
    public static function redactPath(string $path): string
    {
        return (string) preg_replace('#^/invite/[^/?]*#', '/invite/[token]', $path);
    }

    /**
     * The invite form (inv.name, inv.email, inv.persona). The email becomes
     * the username, lower-cased by the caller.
     */
    public static function validateNew(string $name, string $email, string $persona): ValidationErrors
    {
        $e = new ValidationErrors();
        $n = mb_strlen($name);
        if ($n < 2 || $n > 100 || preg_match('/[\x00-\x1f\x7f]/', $name) === 1) {
            $e = $e->with('name', 'Enter the contact\'s name (2 to 100 characters).');
        }
        if (strlen($email) > 200 || preg_match('/[\s\x00-\x1f\x7f]/', $email) === 1 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $e = $e->with('email', 'Enter a valid email address.');
        }
        if (ClientPersona::tryFrom($persona) === null) {
            $e = $e->with('persona', 'Choose Marketing Manager or Brand Director.');
        }
        return $e;
    }

    public static function emailSubject(string $brandName): string
    {
        return self::oneLine('Your Slash 301 client portal invite' . ($brandName !== '' ? ' for ' . $brandName : ''));
    }

    /** Plain text. Names are reduced to one line so they cannot add headers or fake paragraphs. */
    public static function emailBody(string $contactName, string $brandName, string $inviterName, string $link, DateTimeImmutable $expiresAt): string
    {
        $when = $expiresAt->setTimezone(new DateTimeZone('Africa/Johannesburg'))->format('j F Y, H:i');
        return 'Hello ' . self::oneLine($contactName) . ",\n\n"
            . self::oneLine($inviterName) . ' at Slash 301 has invited you to the client portal'
            . ($brandName !== '' ? ' for ' . self::oneLine($brandName) : '') . ", where you can review and approve work.\n\n"
            . "Open this link to set your password (it works once, until $when SAST):\n\n"
            . $link . "\n\n"
            . "Your username is this email address.\n\n"
            . "If you did not expect this email, you can ignore it.\n\n"
            . "Slash 301\n";
    }

    private static function oneLine(string $v): string
    {
        return trim((string) preg_replace('/[\x00-\x1f\x7f]+/', ' ', $v));
    }
}
