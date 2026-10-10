<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\PolicyRule;

/**
 * Who is searching jobs, as the Store needs it: which jobs they may list
 * (view_all_jobs) and whether they see a brief's working copy or the last
 * sent values (view_brief_draft). Built by Policy::jobViewer(). Go: struct.
 */
final class JobViewer
{
    public function __construct(
        public readonly string $userId,
        public readonly PolicyRule $scope,
        public readonly PolicyRule $draftView,
        public readonly ?string $brandId,
    ) {}
}
