<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\ActivityEntry;
use App\Domain\Types\Brand;
use DateTimeImmutable;

final class BrandStore
{
    private const COLS = 'id, name, prefix, logo_url';

    public function __construct(private readonly Db $db, private readonly ActivityStore $activity) {}

    /** @return list<Brand> by name */
    public function list(): array
    {
        $out = [];
        foreach ($this->db->query('SELECT ' . self::COLS . ' FROM brands ORDER BY name') as $r) {
            $out[] = Brand::fromRow($r);
        }
        return $out;
    }

    public function get(string $id): ?Brand
    {
        $r = $this->db->one('SELECT ' . self::COLS . ' FROM brands WHERE id = :id', ['id' => $id]);
        return $r === null ? null : Brand::fromRow($r);
    }

    /** Set or clear ('') the logo link, validated by the caller (Links::logoUrlProblem). Logged as brand_logo_changed. */
    public function setLogo(string $brandId, string $url, string $actorId, DateTimeImmutable $now): void
    {
        $this->db->txImmediate(function (Db $tx) use ($brandId, $url, $actorId, $now): void {
            $old = $tx->scalar('SELECT logo_url FROM brands WHERE id = :id', ['id' => $brandId]);
            $tx->exec('UPDATE brands SET logo_url = :u WHERE id = :id', ['u' => $url === '' ? null : $url, 'id' => $brandId]);
            $this->activity->append($tx, new ActivityEntry(null, $actorId, 'brand_logo_changed', 'brand', $brandId,
                ['from' => $old === null ? '' : (string) $old, 'to' => $url, 'recipients' => []]), $now);
        });
    }
}
