<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * The two kinds of client contact (docs/roles.md 2.14, Q3). One Client role;
 * the persona is stored as users.client_signoff (migration 0008):
 * 0 Marketing Manager (feedback and post approvals), 1 Brand Director (also
 * the final job sign-off). Go: type ClientPersona string.
 */
enum ClientPersona: string
{
    case MarketingManager = 'marketing_manager';
    case BrandDirector = 'brand_director';

    public function label(): string
    {
        return match ($this) {
            self::MarketingManager => 'Marketing Manager',
            self::BrandDirector => 'Brand Director',
        };
    }

    /** users.client_signoff */
    public function signoff(): int
    {
        return $this === self::BrandDirector ? 1 : 0;
    }

    /** NULL reads as 1 (migration 0008 default). */
    public static function fromSignoff(?int $v): self
    {
        return $v === 0 ? self::MarketingManager : self::BrandDirector;
    }
}
