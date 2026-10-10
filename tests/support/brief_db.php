<?php
declare(strict_types=1);

// Seed helpers for integration tests on a migrated temp database.

use App\Domain\Role;
use App\Http\Deps;

require_once __DIR__ . '/app.php';

/** Brand MERC + campaign "Grand Opening London", like the live data. @return array{brand:string,campaign:string} */
function bd_seed_campaign(Deps $d, string $prefix = 'MERC', string $brandName = 'The Meridian Collection', string $campaign = 'Grand Opening London'): array
{
    $bid = 'brand_' . strtolower($prefix);
    $cid = 'camp_' . strtolower($prefix);
    $d->db->exec('INSERT INTO brands (id, name, prefix) VALUES (:id, :n, :p)', ['id' => $bid, 'n' => $brandName, 'p' => $prefix]);
    $d->db->exec("INSERT INTO campaigns (id, brand_id, name, status) VALUES (:id, :b, :n, 'active')", ['id' => $cid, 'b' => $bid, 'n' => $campaign]);
    return ['brand' => $bid, 'campaign' => $cid];
}

/** @return array<string,string> handle => user id */
function bd_seed_people(Deps $d): array
{
    return [
        'am' => ts_user($d, 'am_amy', Role::AM),
        'am2' => ts_user($d, 'am_ben', Role::AM),
        'traffic' => ts_user($d, 'traffic_morgan', Role::Traffic),
        'traffic2' => ts_user($d, 'traffic_ryan', Role::Traffic),
        'designer' => ts_user($d, 'design_kim', Role::Designer),
        'copy' => ts_user($d, 'copy_alex', Role::Copywriter),
        'cd' => ts_user($d, 'cd_isabelle', Role::CD),
        'coo' => ts_user($d, 'coo_priya', Role::COO),
    ];
}
