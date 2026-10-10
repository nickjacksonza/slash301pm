<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * What a hand-in media link points at, derived from the link itself (never
 * typed by the maker): an image the review card may show inline (https only,
 * img-src https: in the CSP), a video it links to with a play label, or any
 * other link (a folder, a share path, a page). Go: type MediaKind string.
 */
enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Link = 'link';

    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
    private const VIDEO_EXT = ['mp4', 'mov', 'webm', 'm4v'];
    private const VIDEO_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com'];

    public static function fromUrl(string $url): self
    {
        if (!Links::isWebUrl($url)) {
            return self::Link;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (in_array($host, self::VIDEO_HOSTS, true)) {
            return self::Video;
        }
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $dot = strrpos($path, '.');
        $ext = $dot === false ? '' : substr($path, $dot + 1);
        if (in_array($ext, self::IMAGE_EXT, true)) {
            return self::Image;
        }
        if (in_array($ext, self::VIDEO_EXT, true)) {
            return self::Video;
        }
        return self::Link;
    }

    /** May the card render this link as an <img>? Only an https image link. */
    public static function showsInline(string $url, self $kind): bool
    {
        return $kind === self::Image && Links::isHttpsUrl($url);
    }
}
