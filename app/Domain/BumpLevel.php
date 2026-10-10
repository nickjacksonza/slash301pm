<?php
declare(strict_types=1);

namespace App\Domain;

/** How much a brief update changes the version (ADR 0003). Go: type BumpLevel string. */
enum BumpLevel: string
{
    case Major = 'major';
    case Minor = 'minor';
    case Patch = 'patch';

    public function label(): string
    {
        return match ($this) {
            self::Major => 'Major',
            self::Minor => 'Minor',
            self::Patch => 'Patch',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Major => 'Change of scope or a re-brief',
            self::Minor => 'Deliverables, dates, budget or team changed',
            self::Patch => 'Wording, references or fixes',
        };
    }
}
