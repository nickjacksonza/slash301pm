<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\Brief;
use App\Domain\Types\BriefLine;
use App\Domain\Types\BriefLineInput;
use App\Domain\Types\BriefSnapshot;
use DateTimeImmutable;

/** brief_assets: the deliverables list of a brief. Every write also touches the brief (unsent flag, row_version). */
final class BriefAssetStore
{
    private const COLS = 'id, brief_id, job_id, template_id, label, qty, channel, size_format, specs, copy_required, due_date, sort_order';

    public function __construct(private readonly Db $db, private readonly BriefStore $briefs) {}

    /** @return list<BriefLine> in display order */
    public function listByBrief(string $briefId): array
    {
        $out = [];
        foreach ($this->db->query('SELECT ' . self::COLS . ' FROM brief_assets WHERE brief_id = :b ORDER BY sort_order, created_at, id', ['b' => $briefId]) as $r) {
            $out[] = BriefLine::fromRow($r);
        }
        return $out;
    }

    public function get(string $briefId, string $lineId): ?BriefLine
    {
        $r = $this->db->one('SELECT ' . self::COLS . ' FROM brief_assets WHERE brief_id = :b AND id = :id', ['b' => $briefId, 'id' => $lineId]);
        return $r === null ? null : BriefLine::fromRow($r);
    }

    /** Append a line. Returns its id. */
    public function add(Brief $brief, BriefLineInput $in, ?BriefSnapshot $baseline, string $actorId, DateTimeImmutable $now): string
    {
        $id = Ids::new();
        $this->db->txImmediate(function (Db $tx) use ($id, $brief, $in, $baseline, $actorId, $now): void {
            $sort = (int) $tx->scalar('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM brief_assets WHERE brief_id = :b', ['b' => $brief->id]);
            $at = Ids::utc($now);
            $tx->exec(
                'INSERT INTO brief_assets (id, brief_id, job_id, template_id, label, qty, channel, size_format, specs, copy_required, due_date, sort_order, created_at, updated_at)
                 VALUES (:id, :b, :j, :tpl, :label, :qty, :ch, :size, :specs, :copy, :due, :sort, :at, :at)',
                ['id' => $id, 'b' => $brief->id, 'j' => $brief->jobId, 'tpl' => $in->templateId, 'label' => $in->label, 'qty' => $in->qty, 'ch' => $in->channel,
                    'size' => $in->sizeFormat, 'specs' => $in->specs, 'copy' => $in->copyRequired ? 1 : 0, 'due' => $in->dueDate, 'sort' => $sort, 'at' => $at],
            );
            $this->briefs->touch($tx, $brief, $baseline, $actorId, $now, 'deliverable_added', $id, ['label' => $in->label, 'qty' => $in->qty]);
        });
        return $id;
    }

    /** False when the line is not on this brief. */
    public function update(Brief $brief, string $lineId, BriefLineInput $in, ?BriefSnapshot $baseline, string $actorId, DateTimeImmutable $now): bool
    {
        return $this->db->txImmediate(function (Db $tx) use ($brief, $lineId, $in, $baseline, $actorId, $now): bool {
            $n = $tx->exec(
                'UPDATE brief_assets SET template_id = :tpl, label = :label, qty = :qty, channel = :ch, size_format = :size, specs = :specs,
                        copy_required = :copy, due_date = :due, updated_at = :at WHERE id = :id AND brief_id = :b',
                ['tpl' => $in->templateId, 'label' => $in->label, 'qty' => $in->qty, 'ch' => $in->channel, 'size' => $in->sizeFormat, 'specs' => $in->specs,
                    'copy' => $in->copyRequired ? 1 : 0, 'due' => $in->dueDate, 'at' => Ids::utc($now), 'id' => $lineId, 'b' => $brief->id],
            );
            if ($n === 0) {
                return false;
            }
            $this->briefs->touch($tx, $brief, $baseline, $actorId, $now, 'deliverable_updated', $lineId, ['label' => $in->label, 'qty' => $in->qty]);
            return true;
        });
    }

    /** Removes the line; its assets are cancelled by the next send (AssetPlan). False when not on this brief. */
    public function remove(Brief $brief, string $lineId, ?BriefSnapshot $baseline, string $actorId, DateTimeImmutable $now): bool
    {
        return $this->db->txImmediate(function (Db $tx) use ($brief, $lineId, $baseline, $actorId, $now): bool {
            if ($tx->exec('DELETE FROM brief_assets WHERE id = :id AND brief_id = :b', ['id' => $lineId, 'b' => $brief->id]) === 0) {
                return false;
            }
            $this->briefs->touch($tx, $brief, $baseline, $actorId, $now, 'deliverable_removed', $lineId);
            return true;
        });
    }

    /** Rewrite sort_order to the given order. Ids not on the brief are ignored. @param list<string> $orderedIds */
    public function reorder(Brief $brief, array $orderedIds, ?BriefSnapshot $baseline, string $actorId, DateTimeImmutable $now): void
    {
        $this->db->txImmediate(function (Db $tx) use ($brief, $orderedIds, $baseline, $actorId, $now): void {
            foreach ($orderedIds as $i => $id) {
                $tx->exec('UPDATE brief_assets SET sort_order = :s WHERE id = :id AND brief_id = :b', ['s' => $i, 'id' => $id, 'b' => $brief->id]);
            }
            $this->briefs->touch($tx, $brief, $baseline, $actorId, $now, 'deliverables_reordered', '', ['count' => count($orderedIds)]);
        });
    }
}
