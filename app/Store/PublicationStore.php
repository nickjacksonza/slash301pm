<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\JobAction;
use App\Domain\Notifications;
use App\Domain\Platform;
use App\Domain\PublicationAction;
use App\Domain\PublicationChecklist;
use App\Domain\PublicationRules;
use App\Domain\PublicationStatus;
use App\Domain\Stage;
use App\Domain\Transitions;
use App\Domain\Types\ActivityEntry;
use App\Domain\Types\Assignment;
use App\Domain\Types\Publication;
use App\Domain\Types\PublicationOutcome;
use App\Domain\Types\SocialAsset;
use App\Domain\Types\SocialAssetState;
use App\Domain\Types\SocialWrite;
use App\Domain\Types\Team;
use App\Domain\Types\TransitionRequest;
use DateTimeImmutable;

/**
 * asset_publications (migration 0012) and the Social queue reads. Every write
 * is one BEGIN IMMEDIATE transaction that checks the publication's
 * row_version, writes an activity row with data.recipients (notification
 * catalogue N34 to N39), and moves the job's Social stage through
 * JobStore::applyTransitionTx, so the legacy status stays 'Approved (External)'.
 * Reads never select budget or hours.
 */
final class PublicationStore
{
    private const PUB_COLS = 'id, asset_id, job_id, platform, status, scheduled_at, live_url, promoted, promoted_at, promoted_note, checklist_json, updated_by, updated_at, row_version';

    /** The queue's asset rows: asset, deliverable line, job, brand, Social holder. No budget, no hours. */
    private const ASSET_SELECT = "SELECT a.id AS asset_id, a.name AS asset_name, a.template_id, a.status AS asset_status, a.due_date AS asset_due, a.sort_order,
            COALESCE(ua.name, '') AS assignee_name, COALESCE(ba.label, '') AS line_label, COALESCE(ba.channel, '') AS channel, COALESCE(ba.size_format, '') AS size_format,
            j.id AS job_id, j.job_number, COALESCE(NULLIF(br.title, ''), j.title) AS job_title, j.stage, b.id AS brand_id, COALESCE(b.name, '') AS brand_name,
            COALESCE(c.name, '') AS campaign_name, COALESCE(NULLIF(br.due_date, ''), j.delivery_date) AS job_due, br.last_go_live,
            COALESCE(soc.user_id, '') AS social_id, COALESCE(us.name, '') AS social_name
        FROM assets a
        JOIN jobs j ON j.id = a.job_id
        LEFT JOIN brief_assets ba ON ba.id = a.brief_asset_id
        LEFT JOIN briefs br ON br.job_id = j.id
        LEFT JOIN campaigns c ON c.id = j.campaign_id
        LEFT JOIN brands b ON b.id = c.brand_id
        LEFT JOIN users ua ON ua.id = a.assigned_to
        LEFT JOIN job_assignments soc ON soc.job_id = j.id AND soc.role_on_job = 'Social'
        LEFT JOIN users us ON us.id = soc.user_id";

    public function __construct(private readonly Db $db, private readonly ActivityStore $activity, private readonly JobStore $jobs) {}

    public function get(string $id): ?Publication
    {
        $r = $this->db->one('SELECT ' . self::PUB_COLS . ' FROM asset_publications WHERE id = :id', ['id' => $id]);
        return $r === null ? null : Publication::fromRow($r);
    }

    /** @return list<Publication> of one job, by asset then platform order */
    public function listByJob(string $jobId): array
    {
        return $this->publications($this->db->query('SELECT ' . self::PUB_COLS . ' FROM asset_publications WHERE job_id = :j', ['j' => $jobId]));
    }

    /**
     * Social assets of jobs past client approval (approved_client, the three
     * Social stages, done), with their publications. Social detection is
     * PublicationRules::isSocialAsset (template or channel), done here in PHP.
     * $ownerId: only jobs this user holds any slot on or created (AM, PM, Producer).
     * $mineId: only jobs where this user holds the Social slot or an asset.
     * @return list<SocialAsset>
     */
    public function queue(?string $jobId = null, ?string $brandId = null, ?string $ownerId = null, ?string $mineId = null): array
    {
        $where = ["j.stage IN ('approved_client', 'ready_to_schedule', 'scheduled', 'live', 'done')", "a.status <> 'Cancelled'"];
        $params = [];
        if ($jobId !== null) {
            $where[] = 'j.id = :job';
            $params['job'] = $jobId;
        }
        if ($brandId !== null) {
            $where[] = 'c.brand_id = :brand';
            $params['brand'] = $brandId;
        }
        if ($ownerId !== null) {
            $where[] = '(EXISTS (SELECT 1 FROM job_assignments ow WHERE ow.job_id = j.id AND ow.user_id = :owner) OR COALESCE(br.created_by, j.created_by) = :owner)';
            $params['owner'] = $ownerId;
        }
        if ($mineId !== null) {
            $where[] = "(soc.user_id = :mine OR EXISTS (SELECT 1 FROM assets ma WHERE ma.job_id = j.id AND ma.assigned_to = :mine))";
            $params['mine'] = $mineId;
        }
        $rows = $this->db->query(self::ASSET_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY j.job_number, a.sort_order, a.created_at, a.id LIMIT 2000', $params);
        $jobIds = [];
        $assets = [];
        foreach ($rows as $r) {
            $tpl = $r['template_id'] !== null && $r['template_id'] !== '' ? (string) $r['template_id'] : null;
            if (!PublicationRules::isSocialAsset($tpl, (string) $r['channel'], (string) $r['asset_status'])) {
                continue;
            }
            $assets[] = $r;
            $jobIds[(string) $r['job_id']] = true;
        }
        $byAsset = [];
        if ($jobIds !== []) {
            $ids = array_keys($jobIds);
            $ph = implode(', ', array_fill(0, count($ids), '?'));
            foreach ($this->publications($this->db->query('SELECT ' . self::PUB_COLS . ' FROM asset_publications WHERE job_id IN (' . $ph . ')', $ids)) as $p) {
                $byAsset[$p->assetId][] = $p;
            }
        }
        $out = [];
        foreach ($assets as $r) {
            $out[] = SocialAsset::fromRow($r, $byAsset[(string) $r['asset_id']] ?? []);
        }
        return $out;
    }

    /** The job an asset belongs to, or null. */
    public function assetJobId(string $assetId): ?string
    {
        $v = $this->db->scalar('SELECT job_id FROM assets WHERE id = :a', ['a' => $assetId]);
        return $v === null ? null : (string) $v;
    }

    /** Does the user hold the Social slot on an open job (My day shows the Social section)? */
    public function holdsSocialSlot(string $userId): bool
    {
        return $this->db->scalar(
            "SELECT 1 FROM job_assignments ja JOIN jobs j ON j.id = ja.job_id
             WHERE ja.user_id = :u AND ja.role_on_job = 'Social' AND j.stage NOT IN ('done', 'archived', 'cancelled') LIMIT 1",
            ['u' => $userId],
        ) !== null;
    }

    /** One social asset of a job (with its publications), or null when it is not a social asset of that job. */
    public function asset(string $jobId, string $assetId): ?SocialAsset
    {
        foreach ($this->queue($jobId) as $a) {
            if ($a->assetId === $assetId) {
                return $a;
            }
        }
        return null;
    }

    // ---- writes --------------------------------------------------------------------

    /** Add a platform to an asset: a new 'checking' row. Refused when the platform is already there. */
    public function addPlatform(string $jobId, string $assetId, Platform $platform, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        return $this->db->txImmediate(function (Db $tx) use ($jobId, $assetId, $platform, $actorId, $now): SocialWrite {
            if ($tx->scalar('SELECT 1 FROM asset_publications WHERE asset_id = :a AND platform = :p', ['a' => $assetId, 'p' => $platform->value]) !== null) {
                return SocialWrite::refused($platform->label() . ' is already on this asset.');
            }
            $id = Ids::new();
            $at = Ids::utc($now);
            $tx->exec(
                "INSERT INTO asset_publications (id, asset_id, job_id, platform, status, promoted, checklist_json, created_by, updated_by, created_at, updated_at, row_version)
                 VALUES (:id, :a, :j, :p, 'checking', 0, :cl, :u, :u, :at, :at, 1)",
                ['id' => $id, 'a' => $assetId, 'j' => $jobId, 'p' => $platform->value, 'cl' => PublicationChecklist::empty()->toJson(), 'u' => $actorId, 'at' => $at],
            );
            $this->log($tx, $jobId, $actorId, 'publication_added', $id, ['asset_id' => $assetId, 'platform' => $platform->value, 'recipients' => []], $now);
            return SocialWrite::done(null, null);
        });
    }

    /** Remove a platform that is still being checked (nothing was scheduled, so nothing is lost). */
    public function removePlatform(Publication $p, int $rowVersion, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        return $this->db->txImmediate(function (Db $tx) use ($p, $rowVersion, $actorId, $now): SocialWrite {
            $n = $tx->exec("DELETE FROM asset_publications WHERE id = :id AND row_version = :rv AND status = 'checking'", ['id' => $p->id, 'rv' => $rowVersion]);
            if ($n === 0) {
                return SocialWrite::stale();
            }
            $this->log($tx, $p->jobId, $actorId, 'publication_removed', $p->id, ['asset_id' => $p->assetId, 'platform' => $p->platform->value, 'recipients' => []], $now);
            return SocialWrite::done(null, null);
        });
    }

    public function saveChecklist(Publication $p, int $rowVersion, PublicationChecklist $c, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        return $this->db->txImmediate(function (Db $tx) use ($p, $rowVersion, $c, $actorId, $now): SocialWrite {
            if (!$this->bump($tx, $p->id, $rowVersion, ['checklist_json' => $c->toJson()], $actorId, $now, "status = 'checking'")) {
                return SocialWrite::stale();
            }
            $this->activity->appendCoalesced($tx, new ActivityEntry($p->jobId, $actorId, 'publication_checked', 'publication', $p->id,
                ['platform' => $p->platform->value, 'ticked' => $c->tickedCount(), 'recipients' => []]), $now, 300);
            return SocialWrite::done(null, null);
        });
    }

    /** scheduled_at while ready or scheduled ('Y-m-d H:i' or null). */
    public function saveScheduledAt(Publication $p, int $rowVersion, ?string $scheduledAt, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        return $this->db->txImmediate(function (Db $tx) use ($p, $rowVersion, $scheduledAt, $actorId, $now): SocialWrite {
            if (!$this->bump($tx, $p->id, $rowVersion, ['scheduled_at' => $scheduledAt], $actorId, $now, "status IN ('ready_to_schedule', 'scheduled')")) {
                return SocialWrite::stale();
            }
            $this->log($tx, $p->jobId, $actorId, 'publication_rescheduled', $p->id, ['platform' => $p->platform->value, 'scheduled_at' => $scheduledAt, 'recipients' => []], $now);
            return SocialWrite::done(null, null);
        });
    }

    /** The live link, from scheduled on. A change after Live notifies Social and the AM (N39). */
    public function saveLiveUrl(Publication $p, int $rowVersion, string $url, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        return $this->db->txImmediate(function (Db $tx) use ($p, $rowVersion, $url, $actorId, $now): SocialWrite {
            if (!$this->bump($tx, $p->id, $rowVersion, ['live_url' => $url === '' ? null : $url], $actorId, $now, "status IN ('scheduled', 'live')")) {
                return SocialWrite::stale();
            }
            $afterLive = $p->status === PublicationStatus::Live;
            $data = ['platform' => $p->platform->value, 'live_url' => $url, 'recipients' => $afterLive
                ? Notifications::socialRecipients(Notifications::PUBLICATION_LINK_CHANGED, $this->team($tx, $p->jobId), $this->creator($tx, $p->jobId), $actorId) : []];
            $this->log($tx, $p->jobId, $actorId, $afterLive ? Notifications::PUBLICATION_LINK_CHANGED : 'publication_link_added', $p->id, $data, $now);
            return SocialWrite::done(null, null);
        });
    }

    /** Promoted (paid boost) per platform, with an optional note. Notifies the AM (N37). */
    public function savePromoted(Publication $p, int $rowVersion, bool $promoted, string $note, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        return $this->db->txImmediate(function (Db $tx) use ($p, $rowVersion, $promoted, $note, $actorId, $now): SocialWrite {
            $set = ['promoted' => $promoted ? 1 : 0, 'promoted_note' => $note === '' ? null : $note];
            if ($promoted !== $p->promoted) {
                $set['promoted_at'] = $promoted ? Ids::utc($now) : null;
            }
            if (!$this->bump($tx, $p->id, $rowVersion, $set, $actorId, $now, "status IN ('scheduled', 'live')")) {
                return SocialWrite::stale();
            }
            $recipients = $promoted !== $p->promoted
                ? Notifications::socialRecipients(Notifications::PUBLICATION_PROMOTED, $this->team($tx, $p->jobId), $this->creator($tx, $p->jobId), $actorId) : [];
            $this->log($tx, $p->jobId, $actorId, Notifications::PUBLICATION_PROMOTED, $p->id,
                ['platform' => $p->platform->value, 'promoted' => $promoted, 'note' => $note, 'recipients' => $recipients], $now);
            return SocialWrite::done(null, null);
        });
    }

    /**
     * Apply a planned status move (PublicationRules::plan) and let the job's
     * Social stage follow. The precondition is re-checked on the row inside the
     * transaction (status, and for Ready the stored checklist).
     */
    public function applyMove(Publication $p, int $rowVersion, PublicationOutcome $o, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        if (!$o->ok() || $o->to === null) {
            throw new \LogicException('applyMove needs an allowed outcome');
        }
        return $this->tx(function (Db $tx) use ($p, $rowVersion, $o, $actorId, $now): SocialWrite {
            $set = ['status' => $o->to->value, 'scheduled_at' => $o->scheduledAt, 'live_url' => $o->liveUrl === '' ? null : $o->liveUrl];
            $guard = 'status = :g_from';
            $gp = ['g_from' => $o->from->value];
            if ($o->action === PublicationAction::Ready) {
                // The checklist the plan saw is the one stored (row_version already pins it; belt and braces).
                $guard .= ' AND checklist_json = :g_cl';
                $gp['g_cl'] = $p->checklist->toJson();
            }
            if (!$this->bump($tx, $p->id, $rowVersion, $set, $actorId, $now, $guard, $gp)) {
                return SocialWrite::stale();
            }
            $verb = $o->action->verb();
            $data = ['platform' => $p->platform->value, 'asset_id' => $p->assetId, 'from' => $o->from->value, 'to' => $o->to->value,
                'recipients' => Notifications::socialRecipients($verb, $this->team($tx, $p->jobId), $this->creator($tx, $p->jobId), $actorId)];
            if ($o->reason !== '') {
                $data['reason'] = $o->reason;
            }
            if ($o->to === PublicationStatus::Scheduled) {
                $data['scheduled_at'] = $o->scheduledAt;
            }
            if ($o->to === PublicationStatus::Live) {
                $data['live_url'] = $o->liveUrl;
            }
            $this->log($tx, $p->jobId, $actorId, $verb, $p->id, $data, $now);
            return $this->followJob($tx, $p->jobId, $actorId, $now, $o->action === PublicationAction::Reopen || $o->action === PublicationAction::Recheck, $o->reason);
        });
    }

    /**
     * Override a post's status (Traffic, COO, ECD; owner decision 2026-10):
     * any status, no checklist, date or link precondition, with a reason. The
     * row's status and row_version are re-checked, the override is logged as
     * asset_status_overridden (asset assignee, AM or creator, CD), and the
     * job's Social stage follows in either direction, as after a reopen.
     */
    public function override(Publication $p, int $rowVersion, PublicationStatus $to, string $reason, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        return $this->tx(function (Db $tx) use ($p, $rowVersion, $to, $reason, $actorId, $now): SocialWrite {
            $asset = $tx->one('SELECT name, assigned_to FROM assets WHERE id = :a', ['a' => $p->assetId]);
            if (!$this->bump($tx, $p->id, $rowVersion, ['status' => $to->value], $actorId, $now, 'status = :g_from', ['g_from' => $p->status->value])) {
                return SocialWrite::stale();
            }
            $assignee = $asset !== null && $asset['assigned_to'] !== null && $asset['assigned_to'] !== '' ? (string) $asset['assigned_to'] : null;
            $this->log($tx, $p->jobId, $actorId, Notifications::ASSET_STATUS_OVERRIDDEN, $p->id, [
                'kind' => 'publication', 'asset_id' => $p->assetId, 'asset_name' => $asset !== null ? (string) $asset['name'] : '', 'platform' => $p->platform->value,
                'from' => $p->status->value, 'to' => $to->value, 'reason' => $reason,
                'recipients' => Notifications::overrideRecipients($this->team($tx, $p->jobId), $this->creator($tx, $p->jobId), $assignee, $actorId),
            ], $now);
            return $this->followJob($tx, $p->jobId, $actorId, $now, true, $reason);
        });
    }

    /**
     * "Mark whole brief ready": every social asset needs at least one platform,
     * and every platform still being checked needs a complete checklist; then
     * all of them become Ready to schedule in one transaction (one N34 to the AM).
     */
    public function markJobReady(string $jobId, string $actorId, DateTimeImmutable $now): SocialWrite
    {
        return $this->tx(function (Db $tx) use ($jobId, $actorId, $now): SocialWrite {
            $assets = $this->queue($jobId);
            if ($assets === []) {
                return SocialWrite::refused('This job has no social assets.');
            }
            $todo = [];
            foreach ($assets as $a) {
                $live = 0;
                foreach ($a->publications as $p) {
                    if ($p->status === PublicationStatus::Archived) {
                        continue;
                    }
                    $live++;
                    if ($p->status === PublicationStatus::Checking) {
                        if (!$p->checklist->isComplete()) {
                            return SocialWrite::refused($a->assetName . ' on ' . $p->platform->label() . ' still needs: ' . $p->checklist->missingText() . '.');
                        }
                        $todo[] = $p;
                    }
                }
                if ($live === 0 && $a->publications === []) {
                    return SocialWrite::refused('Choose at least one platform for ' . $a->assetName . '.');
                }
            }
            if ($todo === []) {
                return SocialWrite::refused('Every post is already Ready to schedule or further along.');
            }
            $platforms = [];
            foreach ($todo as $p) {
                $n = $tx->exec(
                    "UPDATE asset_publications SET status = 'ready_to_schedule', updated_by = :u, updated_at = :at, row_version = row_version + 1
                     WHERE id = :id AND row_version = :rv AND status = 'checking'",
                    ['u' => $actorId, 'at' => Ids::utc($now), 'id' => $p->id, 'rv' => $p->rowVersion],
                );
                if ($n === 0) {
                    throw new StaleWrite();
                }
                $platforms[] = $p->platform->value;
            }
            $this->log($tx, $jobId, $actorId, Notifications::PUBLICATION_READY, $jobId, [
                'scope' => 'brief', 'count' => count($todo), 'platforms' => array_values(array_unique($platforms)),
                'recipients' => Notifications::socialRecipients(Notifications::PUBLICATION_READY, $this->team($tx, $jobId), $this->creator($tx, $jobId), $actorId),
            ], $now, 'job');
            return $this->followJob($tx, $jobId, $actorId, $now, false, '');
        });
    }

    // ---- helpers ------------------------------------------------------------------

    /**
     * txImmediate where a StaleWrite thrown inside (a row changed between the
     * read and the write) rolls everything back and answers stale.
     * @param \Closure(Db): SocialWrite $fn
     */
    private function tx(\Closure $fn): SocialWrite
    {
        try {
            return $this->db->txImmediate($fn);
        } catch (StaleWrite $e) {
            return SocialWrite::stale();
        }
    }

    /**
     * Move the job to the stage its publications put it in, one named
     * Transitions move at a time through JobStore::applyTransitionTx. Only
     * inside the Social window; backward only on a reopen.
     */
    private function followJob(Db $tx, string $jobId, string $actorId, DateTimeImmutable $now, bool $allowBack, string $reason): SocialWrite
    {
        $job = $this->jobs->get($jobId);
        if ($job === null) {
            return SocialWrite::done(null, null);
        }
        $states = [];
        foreach ($this->queue($jobId) as $a) {
            $states[] = new SocialAssetState($a->assetId, $a->statuses());
        }
        $target = PublicationRules::jobTarget($states);
        $from = $job->stage;
        foreach (PublicationRules::jobSteps($job->stage, $target, $allowBack) as $action) {
            $o = Transitions::plan($job->stage, $job->resumeStage, new TransitionRequest($action, null, $action === JobAction::SocialStepBack ? $reason : ''), true);
            if (!$o->ok()) {
                break;
            }
            // The publication activity row carries the notification; the stage row is the log.
            if (!$this->jobs->applyTransitionTx($tx, $job, $o, $actorId, $now, Notifications::verbFor($action), ['recipients' => [], 'via' => 'social'])) {
                throw new StaleWrite();
            }
            $job = $this->jobs->get($jobId);
            if ($job === null) {
                break;
            }
        }
        return SocialWrite::done($from, $job !== null ? $job->stage : $from);
    }

    /**
     * UPDATE one row with the given columns when row_version still matches
     * (and the extra guard: SQL written in this file, values bound). Bumps row_version.
     * @param array<string,string|int|null> $set column => value (column names are code, never input)
     * @param array<string,string> $guardParams
     */
    private function bump(Db $tx, string $id, int $rowVersion, array $set, string $actorId, DateTimeImmutable $now, string $guard, array $guardParams = []): bool
    {
        $cols = [];
        $params = ['id' => $id, 'rv' => $rowVersion, 'u' => $actorId, 'at' => Ids::utc($now)] + $guardParams;
        $i = 0;
        foreach ($set as $col => $v) {
            $cols[] = $col . ' = :v' . $i;
            $params['v' . $i] = $v;
            $i++;
        }
        $sql = 'UPDATE asset_publications SET ' . implode(', ', $cols) . ', updated_by = :u, updated_at = :at, row_version = row_version + 1 WHERE id = :id AND row_version = :rv'
            . ($guard !== '' ? ' AND ' . $guard : '');
        return $tx->exec($sql, $params) > 0;
    }

    /** @param array<string,mixed> $data */
    private function log(Db $tx, string $jobId, string $actorId, string $verb, string $entityId, array $data, DateTimeImmutable $now, string $entityType = 'publication'): void
    {
        $this->activity->append($tx, new ActivityEntry($jobId, $actorId, $verb, $entityType, $entityId, $data), $now);
    }

    private function team(Db $tx, string $jobId): Team
    {
        $out = [];
        foreach ($tx->query(
            'SELECT ja.role_on_job, ja.user_id, u.name AS user_name, u.role AS user_role FROM job_assignments ja JOIN users u ON u.id = ja.user_id WHERE ja.job_id = :j',
            ['j' => $jobId],
        ) as $r) {
            $out[] = Assignment::fromRow($r);
        }
        return new Team($out);
    }

    private function creator(Db $tx, string $jobId): ?string
    {
        $v = $tx->scalar('SELECT COALESCE(br.created_by, j.created_by) FROM jobs j LEFT JOIN briefs br ON br.job_id = j.id WHERE j.id = :j', ['j' => $jobId]);
        return $v === null || $v === '' ? null : (string) $v;
    }

    /** @param list<array<string,mixed>> $rows @return list<Publication> sorted by platform order */
    private function publications(array $rows): array
    {
        $order = [];
        foreach (Platform::cases() as $i => $p) {
            $order[$p->value] = $i;
        }
        $out = [];
        foreach ($rows as $r) {
            $p = Publication::fromRow($r);
            if ($p !== null) {
                $out[] = $p;
            }
        }
        usort($out, static fn (Publication $a, Publication $b): int => strcmp($a->assetId, $b->assetId) ?: (($order[$a->platform->value] ?? 99) <=> ($order[$b->platform->value] ?? 99)));
        return $out;
    }
}
