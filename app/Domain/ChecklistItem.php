<?php
declare(strict_types=1);

namespace App\Domain;

/** The five things Social checks before a post is Ready to schedule. Go: type ChecklistItem string. */
enum ChecklistItem: string
{
    case Copy = 'copy';
    case Image = 'image';
    case Link = 'link';
    case Hashtags = 'hashtags';
    case TestResult = 'test_result';

    public function label(): string
    {
        return match ($this) {
            self::Copy => 'Copy',
            self::Image => 'Image',
            self::Link => 'Link',
            self::Hashtags => 'Hashtags',
            self::TestResult => 'Test result',
        };
    }
}
