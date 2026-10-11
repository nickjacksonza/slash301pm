<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * The two parts of a post a reviewer judges separately (owner spec 2026-10):
 * copy (copy text, hashtags and link, made by the copy maker) and media (the
 * media link, made by the media maker). Go: type ReviewPart string.
 */
enum ReviewPart: string
{
    case Copy = 'copy';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::Copy => 'Copy',
            self::Media => 'Media',
        };
    }
}
