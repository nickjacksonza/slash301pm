<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\SavedView;
use DateTimeImmutable;

/** saved_views (migration 0009). The caller checks Policy and SavedViews rules first. */
final class SavedViewStore
{
    private const SELECT = 'SELECT v.id, v.owner_id, COALESCE(u.name, \'\') AS owner_name, v.screen, v.name, v.state_json, v.is_shared, v.is_default, v.position
        FROM saved_views v LEFT JOIN users u ON u.id = v.owner_id';

    public function __construct(private readonly Db $db) {}

    public function get(string $id): ?SavedView
    {
        $r = $this->db->one(self::SELECT . ' WHERE v.id = :id', ['id' => $id]);
        return $r === null ? null : SavedView::fromRow($r);
    }

    /** The user's own views, then views others shared. @return list<SavedView> */
    public function listFor(string $userId, string $screen): array
    {
        $out = [];
        foreach ($this->db->query(
            self::SELECT . ' WHERE v.screen = :s AND (v.owner_id = :u OR v.is_shared = 1)
             ORDER BY CASE WHEN v.owner_id = :u THEN 0 ELSE 1 END, v.position, v.name COLLATE NOCASE, v.id',
            ['s' => $screen, 'u' => $userId],
        ) as $r) {
            $out[] = SavedView::fromRow($r);
        }
        return $out;
    }

    public function defaultFor(string $userId, string $screen): ?SavedView
    {
        $r = $this->db->one(self::SELECT . ' WHERE v.owner_id = :u AND v.screen = :s AND v.is_default = 1 ORDER BY v.updated_at DESC LIMIT 1', ['u' => $userId, 's' => $screen]);
        return $r === null ? null : SavedView::fromRow($r);
    }

    public function countFor(string $userId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM saved_views WHERE owner_id = :u', ['u' => $userId]);
    }

    /** Owner id always comes from the session. Returns the new id. */
    public function create(string $ownerId, string $screen, string $name, string $stateJson, bool $shared, bool $default, DateTimeImmutable $now): string
    {
        $id = Ids::new();
        $this->db->txImmediate(function (Db $tx) use ($id, $ownerId, $screen, $name, $stateJson, $shared, $default, $now): void {
            if ($default) {
                $tx->exec('UPDATE saved_views SET is_default = 0 WHERE owner_id = :u AND screen = :s AND is_default = 1', ['u' => $ownerId, 's' => $screen]);
            }
            $pos = (int) $tx->scalar('SELECT COALESCE(MAX(position), 0) + 1 FROM saved_views WHERE owner_id = :u AND screen = :s', ['u' => $ownerId, 's' => $screen]);
            $at = Ids::utc($now);
            $tx->exec(
                'INSERT INTO saved_views (id, owner_id, screen, name, state_json, is_shared, is_default, position, created_at, updated_at)
                 VALUES (:id, :u, :s, :n, :st, :sh, :df, :pos, :at, :at)',
                ['id' => $id, 'u' => $ownerId, 's' => $screen, 'n' => $name, 'st' => $stateJson, 'sh' => $shared ? 1 : 0, 'df' => $default ? 1 : 0, 'pos' => $pos, 'at' => $at],
            );
        });
        return $id;
    }

    /** Rewrite name, sharing, default flag and (optionally) the state. */
    public function update(SavedView $v, string $name, bool $shared, bool $default, ?string $stateJson, DateTimeImmutable $now): void
    {
        $this->db->txImmediate(function (Db $tx) use ($v, $name, $shared, $default, $stateJson, $now): void {
            if ($default && !$v->isDefault) {
                $tx->exec('UPDATE saved_views SET is_default = 0 WHERE owner_id = :u AND screen = :s AND is_default = 1', ['u' => $v->ownerId, 's' => $v->screen]);
            }
            $tx->exec(
                'UPDATE saved_views SET name = :n, is_shared = :sh, is_default = :df, state_json = COALESCE(:st, state_json), updated_at = :at WHERE id = :id',
                ['n' => $name, 'sh' => $shared ? 1 : 0, 'df' => $default ? 1 : 0, 'st' => $stateJson, 'at' => Ids::utc($now), 'id' => $v->id],
            );
        });
    }

    public function delete(string $id): void
    {
        $this->db->txImmediate(function (Db $tx) use ($id): void {
            $tx->exec('DELETE FROM saved_views WHERE id = :id', ['id' => $id]);
        });
    }
}
