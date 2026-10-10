<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\AssetTemplate;

/**
 * The 18 deliverable templates moved from legacy src/constants.js
 * ASSET_TEMPLATES (same ids, names, types), plus three role tasks (owner
 * decision 2026-10) whose assets go to the job's Developer, SEO or Producer.
 * Legacy reads assets.template_id only for display, so new ids are safe there.
 */
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
            new AssetTemplate('utm-links', 'UTM link generation', 'document', Role::Developer),
            new AssetTemplate('campaign-hashtags', 'Campaign hashtags', 'copy', Role::SEO),
            new AssetTemplate('asset-test-report', 'Asset test report', 'document', Role::Producer),
        ];
    }

    /** The templates that carry a default role (the demo "role tasks"). @return list<AssetTemplate> */
    public static function roleTasks(): array
    {
        $out = [];
        foreach (self::all() as $t) {
            if ($t->defaultRole !== null) {
                $out[] = $t;
            }
        }
        return $out;
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
