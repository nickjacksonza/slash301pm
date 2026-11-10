<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\InviteRules;
use App\Domain\ValidationErrors;

/** Signals under inv.* from /brands/{id}/clients (invite a client contact). Untrusted input. */
final class InviteSignals
{
    public function __construct(
        public readonly string $name,
        /** Lower-cased; becomes the username. */
        public readonly string $email,
        public readonly string $persona,
    ) {}

    public static function fromSignals(array $s): self
    {
        $n = isset($s['inv']) && is_array($s['inv']) ? $s['inv'] : [];
        return new self(self::str($n, 'name'), strtolower(self::str($n, 'email')), self::str($n, 'persona'));
    }

    public function validate(): ValidationErrors
    {
        return InviteRules::validateNew($this->name, $this->email, $this->persona);
    }

    private static function str(array $a, string $k): string
    {
        return isset($a[$k]) && is_string($a[$k]) ? trim($a[$k]) : '';
    }
}
