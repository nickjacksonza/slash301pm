<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Types\ActivityEntry;
use App\Domain\Types\Brief;
use App\Domain\Types\BriefPatch;
use App\Domain\Types\BriefSnapshot;
use App\Domain\Types\BriefVersionRecord;
use App\Domain\Types\SendPlan;
use DateTimeImmutable;

/**
 * briefs and brief_versions. The briefs row is the working copy. While a brief
 * has never been sent, saves are mirrored to the legacy jobs columns at once;
 * after the first send the legacy columns follow the SENT version only (send()).
 *
 * Baseline: briefs backfilled by 0003 as sent 1.0.0 have no version row. Every
 * write takes an optional $baseline snapshot of the state before the write and
 * stores it as the 1.0.0 row first, so later diffs have something to compare.
 */
final class BriefStore
{
    private const COLS = 'id, job_id, title, campaign_id, brief_date, due_date, first_go_live, last_go_live, creative_direction, mandatories, references_json,
        brief_pdf_url, server_link, budget, hours_estimate, version_major, version_minor, version_patch, has_unsent_changes, sent_at, sent_by,
        created_by, created_at, updated_by, updated_at, row_version';

    /** Brief field -> briefs column -> legacy jobs column (null: not mirrored). */
    private const FIELDS = [
        'title' => 'title', 'campaign_id' => 'campaign_id', 'brief_date' => 'brief_date', 'due_date' => 'delivery_date',
        'first_go_live' => null, 'last_go_live' => null, 'creative_direction' => 'creative_direction', 'mandatories' => null,
        'references' => null, 'brief_pdf_url' => null, 'server_link' => null, 'budget' => null, 'hours_estimate' => 'hours_estimate',
    ];

    public function __construct(
        private readonly Db $db,
        private readonly ActivityStore $activity,
        private readonly AssetStore $assets,
    ) {}

    public function getByJob(string $jobId): ?Brief
    {
        $r = $this->db->one('SELECT ' . self::COLS . ' FROM briefs WHERE job_id = :j', ['j' => $jobId]);
        return $r === null ? null : Brief::fromRow($r);
    }

    /**
     * Write the fields in $patch. False when the brief changed since $current
     * was read (row_version). Never-sent briefs are mirrored to jobs.
     */
    public function save(Brief $current, BriefPatch $patch, ?BriefSnapshot $baseline, string $actorId, DateTimeImmutable $now): bool
    {
        if ($patch->isEmpty()) {
            return true;
        }
        return $this->db->txImmediate(function (Db $tx) use ($current, $patch, $baseline, $actorId, $now): bool {
            $sets = [];
            $params = ['id' => $current->id, 'rv' => $current->rowVersion, 'actor' => $actorId, 'at' => Ids::utc($now)];
            $jobSets = [];
            $jobParams = ['job' => $current->jobId, 'actor' => $actorId];
            foreach ($this->patchValues($patch) as $col => $value) {
                $sets[] = $col . ' = :' . $col;
                $params[$col] = $value;
            }
            foreach (self::FIELDS as $field => $jobCol) {
                if ($jobCol !== null && $patch->has($field)) {
                    $jobSets[] = $jobCol . ' = :j_' . $jobCol;
                    $jobParams['j_' . $jobCol] = $this->patchValues($patch)[$field === 'references' ? 'references_json' : $field];
                }
            }
            $n = $tx->exec(
                'UPDATE briefs SET ' . implode(', ', $sets) . ', has_unsent_changes = CASE WHEN sent_at IS NULL THEN 0 ELSE 1 END,
                        updated_by = :actor, updated_at = :at, row_version = row_version + 1
                 WHERE id = :id AND row_version = :rv',
                $params,
            );
            if ($n === 0) {
                return false;
            }
            $this->captureBaseline($tx, $current, $baseline, $now);
            if (!$current->isSent() && $jobSets !== []) {
                $tx->exec('UPDATE jobs SET ' . implode(', ', $jobSets) . ', updated_by = :actor, row_version = row_version + 1 WHERE id = :job', $jobParams);
            }
            return true;
        });
    }

    /**
     * Mark the brief edited from inside another store's transaction (deliverable
     * line changes): unsent flag, author, row_version, and the baseline.
     */
    public function touch(Db $tx, Brief $current, ?BriefSnapshot $baseline, string $actorId, DateTimeImmutable $now): void
    {
        $tx->exec(
            'UPDATE briefs SET has_unsent_changes = CASE WHEN sent_at IS NULL THEN 0 ELSE 1 END, updated_by = :actor, updated_at = :at, row_version = row_version + 1 WHERE id = :id',
            ['actor' => $actorId, 'at' => Ids::utc($now), 'id' => $current->id],
        );
        $this->captureBaseline($tx, $current, $baseline, $now);
    }

    /** Clear or set the unsent flag after the caller compared the working copy with the last sent version. */
    public function setUnsent(string $briefId, bool $unsent): void
    {
        $this->db->txImmediate(function (Db $tx) use ($briefId, $unsent): void {
            $tx->exec('UPDATE briefs SET has_unsent_changes = :u WHERE id = :id AND sent_at IS NOT NULL', ['u' => $unsent ? 1 : 0, 'id' => $briefId]);
        });
    }

    /**
     * Apply a send plan (first send or update) in one transaction: version row,
     * brief version and flags, job stage + legacy status + legacy mirror
     * columns from the sent snapshot, assets, activity. False on a row_version
     * conflict (nothing written).
     * @return array{ok:bool,created:list<string>,cancelled:list<string>}
     */
    public function send(SendPlan $p, ?BriefSnapshot $baseline, ?string $campaignId, string $actorId, DateTimeImmutable $now): array
    {
        return $this->db->txImmediate(function (Db $tx) use ($p, $baseline, $campaignId, $actorId, $now): array {
            $jobRv = $tx->scalar('SELECT row_version FROM jobs WHERE id = :j', ['j' => $p->jobId]);
            $briefRow = $tx->one('SELECT ' . self::COLS . ' FROM briefs WHERE id = :b', ['b' => $p->briefId]);
            if ($jobRv === null || $briefRow === null || (int) $jobRv !== $p->jobRowVersion || (int) $briefRow['row_version'] !== $p->briefRowVersion) {
                return ['ok' => false, 'created' => [], 'cancelled' => []];
            }
            $at = Ids::utc($now);
            if (!$p->isFirst) {
                $this->captureBaseline($tx, Brief::fromRow($briefRow), $baseline, $now);
            }
            $this->insertVersion($tx, $p->briefId, $p->jobId, $p->version->major, $p->version->minor, $p->version->patch, $p->bumpLevel, $p->note,
                $p->snapshot->toArray(), $p->diff?->toArray(), $actorId, $at);
            $tx->exec(
                'UPDATE briefs SET version_major = :ma, version_minor = :mi, version_patch = :pa, has_unsent_changes = 0, sent_at = :at, sent_by = :actor,
                        updated_by = :actor, updated_at = :at, row_version = row_version + 1 WHERE id = :id',
                ['ma' => $p->version->major, 'mi' => $p->version->minor, 'pa' => $p->version->patch, 'at' => $at, 'actor' => $actorId, 'id' => $p->briefId],
            );
            $s = $p->snapshot;
            $stageChanged = $p->toStage !== $p->fromStage;
            $tx->exec(
                'UPDATE jobs SET stage = :stage, status = :status, stage_changed_at = CASE WHEN :changed = 1 THEN :at ELSE stage_changed_at END,
                        title = :title, campaign_id = COALESCE(:camp, campaign_id), creative_direction = :cd, brief_date = :bd, delivery_date = :due,
                        hours_estimate = :hours, updated_by = :actor, row_version = row_version + 1
                 WHERE id = :id',
                ['stage' => $p->toStage->value, 'status' => $p->legacyStatus, 'changed' => $stageChanged ? 1 : 0, 'at' => $at, 'title' => $s->title,
                    'camp' => $s->campaignId, 'cd' => $s->creativeDirection === '' ? null : $s->creativeDirection, 'bd' => $s->briefDate, 'due' => $s->dueDate,
                    'hours' => $s->hoursEstimate, 'actor' => $actorId, 'id' => $p->jobId],
            );
            [$created, $cancelled] = $this->assets->applyPlan($tx, $p->jobId, $campaignId, $p->assets, $now);
            $data = [
                'version' => $p->version->format(), 'bump' => $p->bumpLevel, 'recipients' => $p->recipients,
                'assets_created' => count($created), 'assets_cancelled' => count($cancelled),
            ];
            if ($p->note !== '') {
                $data['note'] = $p->note;
            }
            if ($p->diff !== null) {
                $data['changes'] = ['fields' => count($p->diff->fields), 'lines' => count($p->diff->lines), 'team' => count($p->diff->team)];
            }
            $this->activity->append($tx, new ActivityEntry($p->jobId, $actorId, $p->isFirst ? 'brief_sent' : 'brief_updated', 'brief', $p->briefId, $data), $now);
            if ($cancelled !== []) {
                $this->activity->append($tx, new ActivityEntry($p->jobId, $actorId, 'deliverable_cancelled', 'brief', $p->briefId,
                    ['asset_ids' => $cancelled, 'version' => $p->version->format(), 'recipients' => $p->recipients]), $now);
            }
            foreach ($p->assets->warnings as $w) {
                $this->activity->append($tx, new ActivityEntry($p->jobId, $actorId, 'started_asset_conflict', 'brief_asset', $w->lineId,
                    ['message' => $w->message, 'kept' => $w->startedKept, 'version' => $p->version->format(), 'recipients' => $p->recipients]), $now);
            }
            return ['ok' => true, 'created' => $created, 'cancelled' => $cancelled];
        });
    }

    /** Oldest first. @return list<BriefVersionRecord> */
    public function versions(string $briefId): array
    {
        $out = [];
        foreach ($this->db->query(
            'SELECT v.*, COALESCE(u.name, \'\') AS created_by_name FROM brief_versions v LEFT JOIN users u ON u.id = v.created_by
             WHERE v.brief_id = :b ORDER BY v.major, v.minor, v.patch',
            ['b' => $briefId],
        ) as $r) {
            $out[] = BriefVersionRecord::fromRow($r);
        }
        return $out;
    }

    public function version(string $briefId, string $version): ?BriefVersionRecord
    {
        $r = $this->db->one(
            'SELECT v.*, COALESCE(u.name, \'\') AS created_by_name FROM brief_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.brief_id = :b AND v.version = :v',
            ['b' => $briefId, 'v' => $version],
        );
        return $r === null ? null : BriefVersionRecord::fromRow($r);
    }

    public function latestVersion(string $briefId): ?BriefVersionRecord
    {
        $r = $this->db->one(
            'SELECT v.*, COALESCE(u.name, \'\') AS created_by_name FROM brief_versions v LEFT JOIN users u ON u.id = v.created_by
             WHERE v.brief_id = :b ORDER BY v.major DESC, v.minor DESC, v.patch DESC LIMIT 1',
            ['b' => $briefId],
        );
        return $r === null ? null : BriefVersionRecord::fromRow($r);
    }

    /** column => value for every field the patch sets (JSON built here, in PHP). @return array<string,mixed> */
    private function patchValues(BriefPatch $p): array
    {
        $out = [];
        if ($p->has('title')) {
            $out['title'] = (string) $p->title;
        }
        if ($p->has('campaign_id')) {
            $out['campaign_id'] = $p->campaignId;
        }
        foreach (['brief_date' => $p->briefDate, 'due_date' => $p->dueDate, 'first_go_live' => $p->firstGoLive, 'last_go_live' => $p->lastGoLive] as $k => $v) {
            if ($p->has($k)) {
                $out[$k] = $v;
            }
        }
        if ($p->has('creative_direction')) {
            $out['creative_direction'] = (string) $p->creativeDirection;
        }
        if ($p->has('mandatories')) {
            $out['mandatories'] = json_encode($p->mandatories ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($p->has('references')) {
            $refs = [];
            foreach ($p->references ?? [] as $r) {
                $refs[] = $r->toArray();
            }
            $out['references_json'] = json_encode($refs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($p->has('brief_pdf_url')) {
            $out['brief_pdf_url'] = (string) $p->briefPdfUrl;
        }
        if ($p->has('server_link')) {
            $out['server_link'] = (string) $p->serverLink;
        }
        if ($p->has('budget')) {
            $out['budget'] = $p->budget;
        }
        if ($p->has('hours_estimate')) {
            $out['hours_estimate'] = $p->hoursEstimate;
        }
        return $out;
    }

    private function captureBaseline(Db $tx, Brief $before, ?BriefSnapshot $baseline, DateTimeImmutable $now): void
    {
        if ($baseline === null || !$before->isSent()) {
            return;
        }
        if ($tx->scalar('SELECT 1 FROM brief_versions WHERE brief_id = :b', ['b' => $before->id]) !== null) {
            return;
        }
        $v = $before->version;
        $this->insertVersion($tx, $before->id, $before->jobId, $v->major, $v->minor, $v->patch, 'initial', 'Imported from the old app (state before the first edit in the new app).',
            $baseline->toArray(), null, $before->sentBy, $before->sentAt ?? Ids::utc($now));
    }

    private function insertVersion(Db $tx, string $briefId, string $jobId, int $major, int $minor, int $patch, string $bump, string $note, array $snapshot, ?array $diff, ?string $by, string $at): void
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;
        $tx->exec(
            'INSERT INTO brief_versions (id, brief_id, job_id, version, major, minor, patch, bump_level, note, snapshot_json, diff_json, created_by, created_at)
             VALUES (:id, :b, :j, :v, :ma, :mi, :pa, :bump, :note, :snap, :diff, :by, :at)',
            ['id' => Ids::new(), 'b' => $briefId, 'j' => $jobId, 'v' => $major . '.' . $minor . '.' . $patch, 'ma' => $major, 'mi' => $minor, 'pa' => $patch,
                'bump' => $bump, 'note' => $note === '' ? null : $note, 'snap' => json_encode($snapshot, $flags),
                'diff' => $diff === null ? null : json_encode($diff, $flags), 'by' => $by, 'at' => $at],
        );
    }
}
