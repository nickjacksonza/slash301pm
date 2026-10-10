<?php
declare(strict_types=1);

namespace App\Domain;

/** Who a waiting job waits for (jobs.waiting_on). Go: type WaitingOn string. */
enum WaitingOn: string
{
    case Am = 'am';
    case Client = 'client';
    case Creative = 'creative';
    case ThirdParty = 'third_party';

    public function label(): string
    {
        return match ($this) {
            self::Am => 'Account manager',
            self::Client => 'Client',
            self::Creative => 'Creative team',
            self::ThirdParty => 'Third party',
        };
    }
}
