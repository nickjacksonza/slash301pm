<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\Stage;
use App\Domain\Types\SocialAsset;

/** GET /social/jobs/{id}: the job's social assets and their posts. No budget, no hours. */
final class SocialJobVM
{
    /** @param list<SocialAsset> $assets */
    public function __construct(
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $title,
        public readonly string $brandName,
        public readonly string $campaignName,
        public readonly Stage $stage,
        public readonly string $amName,
        public readonly string $socialName,
        public readonly ?string $jobDue,
        public readonly ?string $lastGoLive,
        public readonly string $versionLabel,
        public readonly string $versionUrl,
        public readonly ?BriefDocVM $doc,
        public readonly SocialPermsVM $perms,
        public readonly array $assets,
        public readonly bool $canMarkReady,
        public readonly string $note,
    ) {}
}
