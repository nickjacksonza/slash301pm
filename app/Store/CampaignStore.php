<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\ActivityEntry;
use App\Domain\Types\Campaign;
use DateTimeImmutable;

final class CampaignStore
{
    private const SELECT = 'SELECT c.id, c.brand_id, b.name AS brand_name, b.prefix AS brand_prefix, c.name, c.description, c.status,
            (SELECT COUNT(*) FROM jobs j WHERE j.campaign_id = c.id) AS job_count
        FROM campaigns c JOIN brands b ON b.id = c.brand_id';

    public function __construct(private readonly Db $db, private readonly ActivityStore $activity) {}

    /** Every campaign, by brand then name. @return list<Campaign> */
    public function listAll(): array
    {
        $out = [];
        foreach ($this->db->query(self::SELECT . ' ORDER BY b.name, c.name') as $r) {
            $out[] = Campaign::fromRow($r);
        }
        return $out;
    }

    /** @return list<Campaign> */
    public function listByBrand(string $brandId): array
    {
        $out = [];
        foreach ($this->db->query(self::SELECT . ' WHERE c.brand_id = :b ORDER BY c.name', ['b' => $brandId]) as $r) {
            $out[] = Campaign::fromRow($r);
        }
        return $out;
    }

    public function get(string $id): ?Campaign
    {
        $r = $this->db->one(self::SELECT . ' WHERE c.id = :id', ['id' => $id]);
        return $r === null ? null : Campaign::fromRow($r);
    }

    public function nameTaken(string $brandId, string $name): bool
    {
        return $this->db->scalar('SELECT 1 FROM campaigns WHERE brand_id = :b AND lower(name) = lower(:n)', ['b' => $brandId, 'n' => $name]) !== null;
    }

    /** Returns the new id. */
    public function create(string $brandId, string $name, string $description, string $actorId, DateTimeImmutable $now): string
    {
        $id = Ids::new();
        $this->db->txImmediate(function (Db $tx) use ($id, $brandId, $name, $description, $actorId, $now): void {
            $tx->exec(
                "INSERT INTO campaigns (id, brand_id, name, description, status, created_at) VALUES (:id, :b, :n, :d, 'active', :at)",
                ['id' => $id, 'b' => $brandId, 'n' => $name, 'd' => $description === '' ? null : $description, 'at' => Ids::utc($now)],
            );
            $this->activity->append($tx, new ActivityEntry(null, $actorId, 'campaign_created', 'campaign', $id, ['name' => $name, 'brand_id' => $brandId]), $now);
        });
        return $id;
    }
}
