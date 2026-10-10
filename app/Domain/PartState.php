<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Where one part of a post stands in review (asset_submissions.copy_state and
 * media_state, no CHECK). Go: type PartState string.
 */
enum PartState: string
{
    case Missing = 'missing';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case ChangesRequested = 'rejected_with_feedback';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Missing => 'Not handed in',
            self::Submitted => 'Waiting for review',
            self::Approved => 'Approved',
            self::ChangesRequested => 'Changes requested',
            self::Rejected => 'Rejected',
        };
    }

    public function isRejected(): bool
    {
        return $this === self::ChangesRequested || $this === self::Rejected;
    }

    /** Handed in (anything but missing). */
    public function hasContent(): bool
    {
        return $this !== self::Missing;
    }

    /** Unknown stored values read as missing (a hand-edited row never becomes "approved"). */
    public static function read(?string $v): self
    {
        return self::tryFrom((string) $v) ?? self::Missing;
    }
}
