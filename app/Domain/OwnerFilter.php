<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Whose jobs: all, mine (I hold the AM, PM or Producer slot, or created the
 * brief; the plan's "who counts as the AM" rule) or unowned (no AM slot).
 * Go: type OwnerFilter string.
 */
enum OwnerFilter: string
{
    case All = 'all';
    case Mine = 'mine';
    case Unowned = 'unowned';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Everyone',
            self::Mine => 'My jobs',
            self::Unowned => 'No AM',
        };
    }
}
