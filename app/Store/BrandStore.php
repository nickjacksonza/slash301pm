<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\Brand;

final class BrandStore
{
    public function __construct(private readonly Db $db) {}

    /** @return list<Brand> by name */
    public function list(): array
    {
        $out = [];
        foreach ($this->db->query('SELECT id, name, prefix FROM brands ORDER BY name') as $r) {
            $out[] = Brand::fromRow($r);
        }
        return $out;
    }

    public function get(string $id): ?Brand
    {
        $r = $this->db->one('SELECT id, name, prefix FROM brands WHERE id = :id', ['id' => $id]);
        return $r === null ? null : Brand::fromRow($r);
    }
}
