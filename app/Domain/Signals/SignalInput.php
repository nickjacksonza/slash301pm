<?php
declare(strict_types=1);

namespace App\Domain\Signals;

/**
 * Typed reads of untrusted Datastar signals. Every function tolerates any JSON
 * shape (a number where a string is expected, an array, missing keys).
 */
final class SignalInput
{
    /** @return array<string,mixed> the object at $key, or [] */
    public static function obj(array $s, string $key): array
    {
        $v = $s[$key] ?? null;
        return is_array($v) && ($v === [] || !array_is_list($v)) ? $v : [];
    }

    public static function has(array $a, string $k): bool
    {
        return array_key_exists($k, $a);
    }

    /** Strings as is; ints and floats as their text; anything else ''. CRLF becomes LF. */
    public static function str(array $a, string $k): string
    {
        $v = $a[$k] ?? '';
        if (is_int($v) || is_float($v)) {
            $v = (string) $v;
        }
        if (!is_string($v)) {
            return '';
        }
        return str_replace(["\r\n", "\r"], "\n", $v);
    }

    public static function bool(array $a, string $k): bool
    {
        $v = $a[$k] ?? false;
        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    public static function int(array $a, string $k, int $default = 0): int
    {
        $v = $a[$k] ?? null;
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v) && floor($v) === $v) {
            return (int) $v;
        }
        if (is_string($v) && preg_match('/^\s*-?\d{1,9}\s*$/', $v) === 1) {
            return (int) trim($v);
        }
        return $default;
    }

    /** 'Y-m-d' that is a real date, or null for '' ; false when invalid. */
    public static function date(string $v): string|false|null
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return false;
        }
        return $v;
    }

    /** A non-negative number up to $max, null for '', false when invalid. Accepts "25 000" and "25,000.50". */
    public static function number(string $v, float $max): float|false|null
    {
        $v = trim(str_replace([' ', ','], '', $v));
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $v) !== 1) {
            return false;
        }
        $f = (float) $v;
        return $f > $max ? false : $f;
    }

    /** '' or an http(s) URL without spaces or control characters. */
    public static function httpUrl(string $v): bool
    {
        if ($v === '') {
            return true;
        }
        return strlen($v) <= 2000 && preg_match('#^https?://[^\s<>"\x00-\x1f\x7f]+$#i', $v) === 1;
    }

    public static function hasControlChars(string $v, bool $allowNewlines): bool
    {
        $re = $allowNewlines ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/';
        return preg_match($re, $v) === 1;
    }
}
