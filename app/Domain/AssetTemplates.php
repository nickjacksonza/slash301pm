<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\AssetTemplate;

/** The 18 deliverable templates, moved from legacy src/constants.js ASSET_TEMPLATES (same ids, names, types). */
final class AssetTemplates
{
    /** @return list<AssetTemplate> */
    public static function all(): array
    {
        return [
            new AssetTemplate('social-static', 'Social Post (Static)', 'image'),
            new AssetTemplate('social-video', 'Social Post (Video)', 'video'),
            new AssetTemplate('social-carousel', 'Social Carousel', 'image'),
            new AssetTemplate('social-story', 'Story/Reel', 'video'),
            new AssetTemplate('banner-display', 'Display Banner', 'image'),
            new AssetTemplate('banner-animated', 'Animated Banner', 'video'),
            new AssetTemplate('email-template', 'Email Template', 'document'),
            new AssetTemplate('email-copy', 'Email Copy', 'copy'),
            new AssetTemplate('landing-page', 'Landing Page', 'document'),
            new AssetTemplate('video-edit-short', 'Video Edit (Short)', 'video'),
            new AssetTemplate('video-edit-long', 'Video Edit (Long)', 'video'),
            new AssetTemplate('print-ad', 'Print Ad', 'document'),
            new AssetTemplate('ooh-billboard', 'OOH/Billboard', 'image'),
            new AssetTemplate('radio-spot', 'Radio Spot', 'audio'),
            new AssetTemplate('podcast-ad', 'Podcast Ad', 'audio'),
            new AssetTemplate('blog-post', 'Blog Post', 'copy'),
            new AssetTemplate('press-release', 'Press Release', 'copy'),
            new AssetTemplate('presentation', 'Presentation', 'document'),
        ];
    }

    public static function find(?string $id): ?AssetTemplate
    {
        if ($id === null) {
            return null;
        }
        foreach (self::all() as $t) {
            if ($t->id === $id) {
                return $t;
            }
        }
        return null;
    }
}
