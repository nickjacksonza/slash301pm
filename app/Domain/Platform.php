<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Social platforms a publication can go out on (docs/roles.md Q20). A plain
 * list in code, not a CHECK, so the owner can add one without a rebuild.
 * Go: type Platform string.
 */
enum Platform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case GoogleBusiness = 'google_business';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
            self::X => 'X',
            self::TikTok => 'TikTok',
            self::YouTube => 'YouTube',
            self::GoogleBusiness => 'Google Business',
        };
    }

    /**
     * The platform a deliverable channel names ("Instagram", "IG", "FB, IG"
     * gives the first match), or null. Case and punctuation are ignored.
     */
    public static function fromChannel(string $channel): ?self
    {
        $found = self::allFromChannel($channel);
        return $found === [] ? null : $found[0];
    }

    /** Every platform a channel text names, in the order they appear. @return list<self> */
    public static function allFromChannel(string $channel): array
    {
        $parts = preg_split('/[^a-z0-9]+/', strtolower($channel)) ?: [];
        $out = [];
        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            $w = $parts[$i];
            $next = $i + 1 < $count ? $parts[$i + 1] : '';
            $p = match ($w) {
                'facebook', 'fb', 'meta' => self::Facebook,
                'instagram', 'ig', 'insta', 'reel', 'reels' => self::Instagram,
                'linkedin', 'li' => self::LinkedIn,
                'x', 'twitter' => self::X,
                'tiktok' => self::TikTok,
                'youtube', 'yt', 'shorts' => self::YouTube,
                'gbp', 'gmb' => self::GoogleBusiness,
                'google' => ($next === 'business' || $next === 'my') ? self::GoogleBusiness : null,
                default => null,
            };
            if ($p !== null && !in_array($p, $out, true)) {
                $out[] = $p;
            }
        }
        return $out;
    }
}
