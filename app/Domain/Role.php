<?php
declare(strict_types=1);

namespace App\Domain;

/** Every value the users.role CHECK constraint allows (api/db.php). Go: type Role string. */
enum Role: string
{
    case COO = 'COO';
    case ECD = 'ECD';
    case Traffic = 'Traffic';
    case PM = 'PM';
    case Producer = 'Producer';
    case CD = 'CD';
    case Copywriter = 'Copywriter';
    case Designer = 'Designer';
    case QA = 'QA';
    case Client = 'Client';
    case AM = 'AM';
    case Developer = 'Developer';
    case SEO = 'SEO';
    case Social = 'Social';

    /** Display order for pickers: agency leadership first, clients last. */
    public static function ordered(): array
    {
        return [
            self::COO, self::ECD, self::AM, self::Traffic, self::PM, self::Producer, self::CD,
            self::Copywriter, self::Designer, self::Developer, self::SEO, self::Social, self::QA, self::Client,
        ];
    }
}
