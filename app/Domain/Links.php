<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Which user-supplied links the app accepts and which it renders as links.
 * - Web links (references, brief PDF): http or https with a host, no spaces,
 *   quotes, angle brackets or control characters, at most MAX_LENGTH bytes.
 * - Server folder links: a web link, smb:// or afp:// share, a UNC path
 *   (\\server\share), a drive path (X:\...) or an absolute path
 *   (/Volumes/...). Any other scheme (javascript:, data:, vbscript:, file:
 *   ...) is refused.
 * Only web links ever become <a href>; share paths are shown as text, because
 * browsers do not open them and a scheme in an href is how javascript: gets in.
 * Pure. Go: package domain, func IsWebURL / IsServerLink.
 */
final class Links
{
    public const MAX_LENGTH = 2000;

    public static function isWebUrl(string $v): bool
    {
        if ($v === '' || strlen($v) > self::MAX_LENGTH) {
            return false;
        }
        if (preg_match('#^https?://[^\s<>"\'`\x00-\x1f\x7f\\\\]+$#i', $v) !== 1) {
            return false;
        }
        $host = parse_url($v, PHP_URL_HOST);
        return is_string($host) && $host !== '' && preg_match('/^[A-Za-z0-9.\-\[\]:]+$/', $host) === 1;
    }

    /** A web link that is https only (brand logos: the page loads them, so never plain http). */
    public static function isHttpsUrl(string $v): bool
    {
        return self::isWebUrl($v) && strncasecmp($v, 'https://', 8) === 0;
    }

    /** '' when $v is fine as a brand logo link ('' clears the logo), else the reason. */
    public static function logoUrlProblem(string $v): string
    {
        if ($v === '' || self::isHttpsUrl($v)) {
            return '';
        }
        return strlen($v) > self::MAX_LENGTH
            ? 'The logo link is too long.'
            : 'The logo must be an https:// link to an image (no other kinds of link).';
    }

    public static function isServerLink(string $v): bool
    {
        if ($v === '' || strlen($v) > self::MAX_LENGTH || preg_match('/[\x00-\x1f\x7f<>"]/', $v) === 1) {
            return false;
        }
        if (self::isWebUrl($v)) {
            return true;
        }
        if (preg_match('#^(smb|afp)://[A-Za-z0-9._\-]+(/|$)#i', $v) === 1) {
            return true;
        }
        if (preg_match('#^\\\\\\\\[A-Za-z0-9._\-]+\\\\#', $v) === 1) {
            return true;   // \\server\share\...
        }
        if (preg_match('#^[A-Za-z]:\\\\#', $v) === 1) {
            return true;   // X:\Clients\...
        }
        return preg_match('#^/[^/]#', $v) === 1;   // /Volumes/Clients/... (never //host, which is a URL)
    }

    /** True only for links that may be written into an href. */
    public static function isLinkable(string $v): bool
    {
        return self::isWebUrl($v);
    }
}
