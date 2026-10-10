<?php
declare(strict_types=1);

namespace App\Domain;

/** A permission-matrix cell (tests/fixtures/roles/policy-matrix.json "decisions"). Go: type PolicyRule string. */
enum PolicyRule: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case Assigned = 'assigned';
    case Creator = 'creator';
    case AssignedOrCreator = 'assigned_or_creator';
    case OwnBrand = 'own_brand';

    /** The short code used in docs/roles.md section 4: Y - A C AC B. */
    public static function fromCode(string $code): self
    {
        return match ($code) {
            'Y' => self::Allow,
            'A' => self::Assigned,
            'C' => self::Creator,
            'AC' => self::AssignedOrCreator,
            'B' => self::OwnBrand,
            default => self::Deny,
        };
    }
}
