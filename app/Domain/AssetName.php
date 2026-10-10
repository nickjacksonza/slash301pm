<?php
declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Exact port of legacy src/utils.js generateAssetName():
 *   ${jobNumber}-${clientShort}-${campaignShort}-${typeWithSeq}-v${version}-${YYYYMMDD}${_size}
 * clientShort: non [a-zA-Z0-9] removed, first 10. campaignShort: split on single
 * spaces, each word capitalised (first char upper, rest lower), joined, non
 * alphanumerics removed, first 15. typeWithSeq: assetType, or "Asset<seq>" when
 * empty. Date is the UTC calendar date of $now (JS toISOString). Empty client or
 * campaign fall back to "Client" / "Campaign", like the JS || operator.
 */
final class AssetName
{
    public static function generate(
        string $jobNumber,
        ?string $client,
        ?string $campaignName,
        ?string $assetType,
        int $sequence,
        int $version,
        ?string $size,
        DateTimeImmutable $now,
    ): string {
        $clientShort = substr((string) preg_replace('/[^a-zA-Z0-9]/', '', ($client === null || $client === '') ? 'Client' : $client), 0, 10);
        $words = explode(' ', ($campaignName === null || $campaignName === '') ? 'Campaign' : $campaignName);
        $cap = [];
        foreach ($words as $w) {
            $cap[] = mb_strtoupper(mb_substr($w, 0, 1)) . mb_strtolower(mb_substr($w, 1));
        }
        $campaignShort = substr((string) preg_replace('/[^a-zA-Z0-9]/', '', implode('', $cap)), 0, 15);
        $dateStr = $now->setTimezone(new DateTimeZone('UTC'))->format('Ymd');
        $typeWithSeq = ($assetType === null || $assetType === '') ? 'Asset' . $sequence : $assetType;
        $sizeStr = ($size === null || $size === '') ? '' : '_' . $size;
        return $jobNumber . '-' . $clientShort . '-' . $campaignShort . '-' . $typeWithSeq . '-v' . $version . '-' . $dateStr . $sizeStr;
    }

    /** The legacy modal's assetType: the template (or line) name with non-alphanumerics removed. */
    public static function typeToken(string $name): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9]/', '', $name);
    }

    /** "1080 x 1350 px, IG" -> "1080x1350" (the size part legacy validateAssetName accepts), else ''. */
    public static function sizeToken(string $sizeFormat): string
    {
        if (preg_match('/(\d{1,5})\s*[x×X]\s*(\d{1,5})/u', $sizeFormat, $m) === 1) {
            return $m[1] . 'x' . $m[2];
        }
        return '';
    }
}
