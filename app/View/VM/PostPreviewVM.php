<?php
declare(strict_types=1);

namespace App\View\VM;

use App\Domain\MediaKind;

/**
 * A post as a basic social media post, in the owner's order: brand logo and
 * name, media, copy, hashtags, link (partial_post_preview). Shared by the
 * internal review page, the hand-in page and, next phase, the client portal.
 * Every link flag is decided in PHP from the stored value (Links), so the
 * template never writes an unchecked URL into href or src.
 */
final class PostPreviewVM
{
    public function __construct(
        public readonly string $domId,
        public readonly string $brandName,
        /** https logo link (Links::isHttpsUrl re-checked), or '' for initials. */
        public readonly string $brandLogoUrl,
        public readonly string $mediaUrl,
        public readonly MediaKind $mediaKind,
        /** Render the media as <img> (an https image link). */
        public readonly bool $mediaInline,
        /** The media link may be an href (an http(s) link); otherwise it is shown as text (a server path). */
        public readonly bool $mediaLinkable,
        public readonly string $copyText,
        public readonly string $hashtags,
        public readonly string $linkUrl,
        /** The post link may be an href (https). */
        public readonly bool $linkLinkable,
        public readonly bool $needsMedia,
    ) {}
}
