<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\OverrideRange;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Asset status overrides (owner decision 2026-10): Traffic, the COO and the
 * ECD may force an asset's legacy status, or a Social post's status, to any
 * value, with a reason, outside the normal flow. Who may is Policy; this class
 * only says whether the values make sense. Every override is logged as
 * asset_status_overridden (data: from, to, reason) for the COO's report.
 * Pure. Go: package domain.
 */
final class AssetOverride
{
    public const MAX_REASON = 1000;

    /** Report range when none is given: the last 30 days. */
    public const DEFAULT_DAYS = 30;

    /** '' when the reason is fine (required, one line, at most 1000 characters). */
    public static function reasonProblem(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            return 'Say why you are overriding the status.';
        }
        if (mb_strlen($reason) > self::MAX_REASON) {
            return 'Keep the reason under 1000 characters.';
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $reason) === 1) {
            return 'Keep the reason on one line.';
        }
        return '';
    }

    /** '' when an asset may go from $from to $to (any legacy value, AssetStatus::OVERRIDE_VALUES, but not the same one). */
    public static function assetProblem(string $from, string $to): string
    {
        if (!AssetStatus::isOverrideValue($to)) {
            return 'Choose one of the listed statuses.';
        }
        if ($to === $from) {
            return 'The asset is already ' . $to . '.';
        }
        return '';
    }

    /** '' when a post may be set to $to (any PublicationStatus but the current one). */
    public static function publicationProblem(PublicationStatus $from, ?PublicationStatus $to): string
    {
        if ($to === null) {
            return 'Choose one of the listed statuses.';
        }
        if ($to === $from) {
            return 'The post is already ' . strtolower($to->label()) . '.';
        }
        return '';
    }

    /**
     * The report's date range: 'Y-m-d' SAST days, inclusive, from the query
     * (bad or missing values fall back to the last DEFAULT_DAYS days; a reversed
     * range is swapped), and the matching UTC bounds for activity.created_at.
     */
    public static function range(string $from, string $to, DateTimeImmutable $now): OverrideRange
    {
        $today = Dates::today($now);
        $t = Dates::normalize(trim($to)) ?? $today;
        $f = Dates::normalize(trim($from));
        if ($f === null) {
            $f = (new DateTimeImmutable($t . ' 00:00:00', new DateTimeZone('UTC')))->modify('-' . (self::DEFAULT_DAYS - 1) . ' days')->format('Y-m-d');
        }
        if ($f > $t) {
            [$f, $t] = [$t, $f];
        }
        $tz = new DateTimeZone(Dates::TZ);
        $utc = new DateTimeZone('UTC');
        $fromUtc = (new DateTimeImmutable($f . ' 00:00:00', $tz))->setTimezone($utc)->format('Y-m-d H:i:s');
        $toUtc = (new DateTimeImmutable($t . ' 00:00:00', $tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
        return new OverrideRange($f, $t, $fromUtc, $toUtc);
    }
}
