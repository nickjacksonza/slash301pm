<?php
declare(strict_types=1);

namespace App\Domain\Signals;

/** Signals under pw.* from the change password page. Untrusted input; never trimmed. */
final class PasswordChangeSignals
{
    public function __construct(
        public readonly string $current,
        public readonly string $new,
        public readonly string $confirm,
    ) {}

    public static function fromSignals(array $s): self
    {
        $p = isset($s['pw']) && is_array($s['pw']) ? $s['pw'] : [];
        return new self(self::str($p, 'current'), self::str($p, 'new'), self::str($p, 'confirm'));
    }

    private static function str(array $a, string $k): string
    {
        return isset($a[$k]) && is_string($a[$k]) ? $a[$k] : '';
    }
}
