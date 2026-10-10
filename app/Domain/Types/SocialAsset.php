<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Stage;

/**
 * A social asset in the queue: the asset, its deliverable line, the job it
 * belongs to (no budget, no hours) and its publications. Go: type SocialAsset struct.
 */
final class SocialAsset
{
    /** @param list<Publication> $publications by platform order */
    public function __construct(
        public readonly string $assetId,
        public readonly string $assetName,
        public readonly ?string $templateId,
        public readonly string $assetStatus,
        public readonly string $assigneeName,
        public readonly ?string $assetDue,
        public readonly string $lineLabel,
        public readonly string $channel,
        public readonly string $sizeFormat,
        public readonly string $jobId,
        public readonly string $jobNumber,
        public readonly string $jobTitle,
        public readonly Stage $jobStage,
        public readonly ?string $brandId,
        public readonly string $brandName,
        public readonly string $campaignName,
        public readonly ?string $jobDue,
        public readonly ?string $lastGoLive,
        public readonly string $socialHolderId,
        public readonly string $socialHolderName,
        public readonly array $publications,
    ) {}

    /** @param list<Publication> $publications */
    public static function fromRow(array $r, array $publications): self
    {
        $s = static fn (string $k): string => isset($r[$k]) && $r[$k] !== null ? (string) $r[$k] : '';
        $n = static fn (string $k): ?string => isset($r[$k]) && $r[$k] !== null && $r[$k] !== '' ? (string) $r[$k] : null;
        return new self(
            $s('asset_id'), $s('asset_name'), $n('template_id'), $s('asset_status'), $s('assignee_name'), $n('asset_due'),
            $s('line_label'), $s('channel'), $s('size_format'),
            $s('job_id'), $s('job_number'), $s('job_title'), Stage::tryFrom($s('stage')) ?? Stage::ApprovedClient,
            $n('brand_id'), $s('brand_name'), $s('campaign_name'), $n('job_due'), $n('last_go_live'),
            $s('social_id'), $s('social_name'), $publications,
        );
    }

    /** @param list<Publication> $publications */
    public function withPublications(array $publications): self
    {
        return new self(
            $this->assetId, $this->assetName, $this->templateId, $this->assetStatus, $this->assigneeName, $this->assetDue,
            $this->lineLabel, $this->channel, $this->sizeFormat, $this->jobId, $this->jobNumber, $this->jobTitle, $this->jobStage,
            $this->brandId, $this->brandName, $this->campaignName, $this->jobDue, $this->lastGoLive, $this->socialHolderId, $this->socialHolderName, $publications,
        );
    }

    /** @return list<\App\Domain\PublicationStatus> */
    public function statuses(): array
    {
        $out = [];
        foreach ($this->publications as $p) {
            $out[] = $p->status;
        }
        return $out;
    }

    public function publicationFor(\App\Domain\Platform $p): ?Publication
    {
        foreach ($this->publications as $pub) {
            if ($pub->platform === $p) {
                return $pub;
            }
        }
        return null;
    }
}
