<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Types\BetaGateItem;
use App\Store\MigrationStatus;

final class SystemVM
{
    /**
     * @param array<string,string> $versions label => value
     * @param list<BetaGateItem> $betaGate every computed gate item (met or not)
     */
    public function __construct(
        public readonly MigrationStatus $migrations,
        public readonly array $versions,
        public readonly bool $demoMode,
        public readonly bool $datastarPinned,
        public readonly string $csrf,
        public readonly string $notice,
        public readonly array $betaGate = [],
    ) {}
}
