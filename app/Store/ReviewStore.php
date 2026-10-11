<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\JobAction;
use App\Domain\JobReviewStatus;
use App\Domain\Notifications;
use App\Domain\PartState;
use App\Domain\ReviewDecision;
use App\Domain\ReviewEvent;
use App\Domain\ReviewNotify;
use App\Domain\ReviewPart;
use App\Domain\ReviewRules;
use App\Domain\Role;
use App\Domain\Stage;
use App\Domain\Transitions;
use App\Domain\Types\ActivityEntry;
use App\Domain\Types\AssetReview;
use App\Domain\Types\Assignment;
use App\Domain\Types\JobReview;
use App\Domain\Types\ReviewJobState;
use App\Domain\Types\ReviewRecord;
use App\Domain\Types\ReviewWrite;
use App\Domain\Types\SubmissionPlan;
use App\Domain\Types\Team;
use App\Domain\Types\TransitionRequest;
use App\Domain\Types\User;
use DateTimeImmutable;

/**
 * Internal reviews (migration 0014): hand-ins (asset_submissions, one row per
 * asset and round), decisions per part (asset_reviews), who made each part
 * (asset_part_makers) and the job review row (job_reviews).
 *
 * Every write is one BEGIN IMMEDIATE transaction that checks a row_version
 * (the post's current round for hand-ins and decisions, job_reviews for the
 * job actions), writes activity rows with data.recipients (ReviewNotify), bumps
 * job_reviews.row_version (so the job action bar of an open page goes stale
 * after any change), mirrors the legacy assets.status, and moves the job stage
 * only through Transitions and JobStore::applyTransitionTx (legacy status
 * written alongside). Actor ids always come from the caller's session user.
 */
final class ReviewStore
{
    private const ASSET_SELECT = "SELECT a.id AS asset_id, a.job_id, a.name, a.type, a.template_id, a.status, a.assigned_to,
            COALESCE(ua.role, '') AS assignee_role, COALESCE(ua.name, '') AS assignee_name,
            COALESCE(ba.label, '') AS line_label, COALESCE(ba.channel, '') AS channel, a.sort_order,
            s.id AS sub_id, COALESCE(s.round, 0) AS round, s.copy_text, s.media_url, s.media_kind, s.hashtags, s.link_url, s.note,
            s.copy_state, s.media_state, s.submitted_by, s.submitted_at, s.review_requested_at, COALESCE(s.row_version, 0) AS row_version,
            CASE WHEN s.id IS NOT NULL AND EXISTS (SELECT 1 FROM asset_reviews rr WHERE rr.asset_id = a.id AND rr.round = s.round AND rr.decision <> 'approved')
                 THEN 1 ELSE 0 END AS rejected_in_round,
            cm.maker_user_id AS copy_maker, COALESCE(uc.name, '') AS copy_maker_name,
            mm.maker_user_id AS media_maker, COALESCE(um.name, '') AS media_maker_name
        FROM assets a
        LEFT JOIN brief_assets ba ON ba.id = a.brief_asset_id
        LEFT JOIN users ua ON ua.id = a.assigned_to
        LEFT JOIN asset_submissions s ON s.asset_id = a.id AND s.round = (SELECT MAX(s2.round) FROM asset_submissions s2 WHERE s2.asset_id = a.id)
        LEFT JOIN asset_part_makers cm ON cm.asset_id = a.id AND cm.part = 'copy'
        LEFT JOIN users uc ON uc.id = cm.maker_user_id
        LEFT JOIN asset_part_makers mm ON mm.asset_id = a.id AND mm.part = 'media'
        LEFT JOIN users um ON um.id = mm.maker_user_id";

    private const JR_SELECT = "SELECT r.job_id, r.job_round, r.all_done_at, r.ecd_requested_by, r.ecd_requested_at, r.cd_approved_by, r.cd_approved_at,
            r.ecd_approved_by, r.ecd_approved_at, r.client_ready_by, r.client_ready_at, r.sent_to_client_by, r.sent_to_client_at,
            r.review_assignee_id, r.row_version, COALESCE(uc.name, '') AS cd_name, COALESCE(ue.name, '') AS ecd_name,
            COALESCE(ua.name, '') AS assignee_name, COALESCE(ua.role, '') AS assignee_role
        FROM job_reviews r
        LEFT JOIN users uc ON uc.id = r.cd_approved_by
        LEFT JOIN users ue ON ue.id = r.ecd_approved_by
        LEFT JOIN users ua ON ua.id = r.review_assignee_id";

    /** Columns a rejection clears: the job is no longer approved, ready or sent. */
    private const CLEARED_ON_REJECTION = [
        'all_done_at', 'ecd_requested_by', 'ecd_requested_at', 'cd_approved_by', 'cd_approved_at', 'ecd_approved_by', 'ecd_approved_at',
        'client_ready_by', 'client_ready_at', 'sent_to_client_by', 'sent_to_client_at',
    ];

    public function __construct(private readonly Db $db, private readonly ActivityStore $activity, private readonly JobStore $jobs) {}

    // ---- reads ---------------------------------------------------------------------

    /** Every post of a job (cancelled ones included; callers filter), in brief order. @return list<AssetReview> */
    public function assets(string $jobId): array
    {
        $out = [];
        foreach ($this->db->query(self::ASSET_SELECT . ' WHERE a.job_id = :j ORDER BY a.sort_order, a.created_at, a.id', ['j' => $jobId]) as $r) {
            $out[] = AssetReview::fromRow($r);
        }
        return $out;
    }

    public function asset(string $jobId, string $assetId): ?AssetReview
    {
        $r = $this->db->one(self::ASSET_SELECT . ' WHERE a.job_id = :j AND a.id = :a', ['j' => $jobId, 'a' => $assetId]);
        return $r === null ? null : AssetReview::fromRow($r);
    }

    /** The job review row, or an empty one (rowVersion 0) before the first review write. */
    public function jobReview(string $jobId): JobReview
    {
        $r = $this->db->one(self::JR_SELECT . ' WHERE r.job_id = :j', ['j' => $jobId]);
        return $r === null ? JobReview::empty($jobId) : JobReview::fromRow($r);
    }

    /** Every decision on the job's posts, newest first, by asset id. @return array<string,list<ReviewRecord>> */
    public function history(string $jobId): array
    {
        $out = [];
        foreach ($this->db->query(
            "SELECT r.id, r.asset_id, r.part, r.round, r.decision, r.feedback, r.reviewer_id, COALESCE(u.name, '') AS reviewer_name, r.reviewed_at, r.brief_version
             FROM asset_reviews r LEFT JOIN users u ON u.id = r.reviewer_id WHERE r.job_id = :j ORDER BY r.reviewed_at DESC, r.rowid DESC",
            ['j' => $jobId],
        ) as $r) {
            $rec = ReviewRecord::fromRow($r);
            if ($rec !== null) {
                $out[$rec->assetId][] = $rec;
            }
        }
        return $out;
    }

    /** Active ECD users (the fallback reviewers and the ECD notifications). @return list<string> */
    public function ecdIds(): array
    {
        $out = [];
        foreach ($this->db->query("SELECT id FROM users WHERE role = 'ECD' AND is_active = 1 ORDER BY name, id") as $r) {
            $out[] = (string) $r['id'];
        }
        return $out;
    }

    /** Is there an active ECD, or another active CD, who can review a part this CD handed in? */
    public function otherReviewerExists(string $userId): bool
    {
        return $this->db->scalar("SELECT 1 FROM users WHERE is_active = 1 AND id <> :u AND role IN ('CD', 'ECD') LIMIT 1", ['u' => $userId]) !== null;
    }

    /** People the review may be handed to: active CDs and ECDs, and the Client contacts of the brand. @return list<User> */
    public function reassignCandidates(?string $brandId): array
    {
        $out = [];
        foreach ($this->db->query(
            "SELECT id, username, name, email, role, color, brand_id, is_active FROM users
             WHERE is_active = 1 AND (role IN ('CD', 'ECD') OR (role = 'Client' AND brand_id = :b)) ORDER BY CASE role WHEN 'Client' THEN 1 ELSE 0 END, name, id",
            ['b' => $brandId ?? ''],
        ) as $r) {
            $out[] = User::fromRow($r);
        }
        return $out;
    }

    /**
     * Open jobs with review activity (a hand-in or a job review row), with
     * their team, review row and posts, for My day and the nav badge. Four
     * queries whatever the number of jobs. Titles are the sent ones (jobs.title).
     * @return list<ReviewJobState>
     */
    public function dayStates(int $limit = 300): array
    {
        $jobs = $this->db->query(
            "SELECT j.id, j.job_number, j.title, j.stage, j.status, COALESCE(br.created_by, j.created_by) AS creator
             FROM jobs j LEFT JOIN briefs br ON br.job_id = j.id
             WHERE j.stage NOT IN ('draft', 'done', 'archived', 'cancelled')
               AND (EXISTS (SELECT 1 FROM asset_submissions s WHERE s.job_id = j.id) OR EXISTS (SELECT 1 FROM job_reviews jr WHERE jr.job_id = j.id))
             ORDER BY j.job_number LIMIT :n",
            ['n' => max(1, min(500, $limit))],
        );
        if ($jobs === []) {
            return [];
        }
        $ids = [];
        foreach ($jobs as $j) {
            $ids[] = (string) $j['id'];
        }
        $ph = implode(', ', array_fill(0, count($ids), '?'));
        $assets = [];
        foreach ($this->db->query(self::ASSET_SELECT . ' WHERE a.job_id IN (' . $ph . ') ORDER BY a.sort_order, a.created_at, a.id', $ids) as $r) {
            $assets[(string) $r['job_id']][] = AssetReview::fromRow($r);
        }
        $reviews = [];
        foreach ($this->db->query(self::JR_SELECT . ' WHERE r.job_id IN (' . $ph . ')', $ids) as $r) {
            $reviews[(string) $r['job_id']] = JobReview::fromRow($r);
        }
        $teams = [];
        foreach ($this->db->query(
            'SELECT ja.job_id, ja.role_on_job, ja.user_id, u.name AS user_name, u.role AS user_role FROM job_assignments ja JOIN users u ON u.id = ja.user_id WHERE ja.job_id IN (' . $ph . ')',
            $ids,
        ) as $r) {
            $role = Role::tryFrom((string) $r['role_on_job']);
            $userRole = Role::tryFrom((string) $r['user_role']);
            if ($role !== null && $userRole !== null) {
                $teams[(string) $r['job_id']][] = new Assignment($role, (string) $r['user_id'], (string) $r['user_name'], $userRole);
            }
        }
        $out = [];
        foreach ($jobs as $j) {
            $id = (string) $j['id'];
            $stage = Stage::tryFrom((string) $j['stage']) ?? Stage::fromLegacy((string) $j['status']) ?? Stage::InProgress;
            $out[] = new ReviewJobState(
                $id, (string) $j['job_number'], (string) $j['title'], $stage,
                $j['creator'] !== null && $j['creator'] !== '' ? (string) $j['creator'] : null,
                new Team($teams[$id] ?? []), $reviews[$id] ?? JobReview::empty($id), $assets[$id] ?? [],
            );
        }
        return $out;
    }

    // ---- writes: posts ---------------------------------------------------------------

    /**
     * A maker's hand-in (Save, or Request review), planned by
     * ReviewRules::planSubmission from $cur. Refused as stale when the post's
     * current round changed since $cur was read.
     */
    public function submit(AssetReview $cur, SubmissionPlan $plan, string $actorId, int $roundLimit, DateTimeImmutable $now): ReviewWrite
    {
        if (!$plan->ok()) {
            throw new \LogicException('submit needs a plan without errors');
        }
        return $this->tx(function (Db $tx) use ($cur, $plan, $actorId, $roundLimit, $now): ReviewWrite {
            $row = $tx->one('SELECT id, round, row_version, copy_at, media_at, review_requested_by, review_requested_at FROM asset_submissions WHERE asset_id = :a ORDER BY round DESC LIMIT 1', ['a' => $cur->assetId]);
            if (($row === null ? 0 : (int) $row['row_version']) !== $cur->rowVersion || ($row === null ? 0 : (int) $row['round']) !== $cur->round) {
                return ReviewWrite::stale();
            }
            $status = $tx->scalar('SELECT status FROM assets WHERE id = :a AND job_id = :j', ['a' => $cur->assetId, 'j' => $cur->jobId]);
            if ($status === null || (string) $status === 'Cancelled') {
                return ReviewWrite::refused('This post was cancelled.');
            }
            $at = Ids::utc($now);
            $copyAt = $plan->changes(ReviewPart::Copy) ? $at : ($row !== null ? $row['copy_at'] : null);
            $mediaAt = $plan->changes(ReviewPart::Media) ? $at : ($row !== null ? $row['media_at'] : null);
            $reqBy = $plan->requestReview ? $actorId : ($row !== null && !$plan->newRound ? $row['review_requested_by'] : null);
            $reqAt = $plan->requestReview ? $at : ($row !== null && !$plan->newRound ? $row['review_requested_at'] : null);
            $values = [
                'copy' => $plan->copyText === '' ? null : $plan->copyText, 'media' => $plan->mediaUrl === '' ? null : $plan->mediaUrl,
                'kind' => $plan->mediaUrl === '' ? null : $plan->mediaKind->value, 'tags' => $plan->hashtags === '' ? null : $plan->hashtags,
                'link' => $plan->linkUrl === '' ? null : $plan->linkUrl, 'note' => $plan->note === '' ? null : $plan->note,
                'cs' => $plan->copyState->value, 'ms' => $plan->mediaState->value, 'cat' => $copyAt, 'mat' => $mediaAt,
                'by' => $actorId, 'at' => $at, 'rby' => $reqBy, 'rat' => $reqAt,
            ];
            if ($plan->newRound || $row === null) {
                $tx->exec(
                    'INSERT INTO asset_submissions (id, asset_id, job_id, round, copy_text, media_url, media_kind, hashtags, link_url, note, copy_state, media_state,
                        copy_at, media_at, submitted_by, submitted_at, review_requested_by, review_requested_at, row_version)
                     VALUES (:id, :a, :j, :round, :copy, :media, :kind, :tags, :link, :note, :cs, :ms, :cat, :mat, :by, :at, :rby, :rat, :rv)',
                    $values + ['id' => Ids::new(), 'a' => $cur->assetId, 'j' => $cur->jobId, 'round' => $plan->round, 'rv' => $cur->rowVersion + 1],
                );
            } else {
                $n = $tx->exec(
                    'UPDATE asset_submissions SET copy_text = :copy, media_url = :media, media_kind = :kind, hashtags = :tags, link_url = :link, note = :note,
                        copy_state = :cs, media_state = :ms, copy_at = :cat, media_at = :mat, submitted_by = :by, submitted_at = :at,
                        review_requested_by = :rby, review_requested_at = :rat, row_version = row_version + 1
                     WHERE id = :id AND row_version = :rv',
                    $values + ['id' => (string) $row['id'], 'rv' => $cur->rowVersion],
                );
                if ($n === 0) {
                    throw new StaleWrite();
                }
            }
            foreach ($plan->changed as $p) {
                $tx->exec('INSERT OR REPLACE INTO asset_part_makers (asset_id, part, maker_user_id, set_at) VALUES (:a, :p, :u, :at)',
                    ['a' => $cur->assetId, 'p' => $p->value, 'u' => $actorId, 'at' => $at]);
            }
            $team = $this->team($tx, $cur->jobId);
            $creator = $this->creator($tx, $cur->jobId);
            $ecds = $this->ecdIds();
            $jr = $this->jobReview($cur->jobId);
            $after = $this->assets($cur->jobId);
            $completes = ReviewRules::completesJob($jr, $after);
            $this->touchJob($tx, $cur->jobId, ['job_round' => ReviewRules::jobRound($after)] + ($completes ? ['all_done_at' => $at] : []), $now);
            $parts = [];
            foreach ($plan->changed as $p) {
                $parts[] = $p->value;
            }
            $base = ['asset_id' => $cur->assetId, 'asset_name' => $cur->assetName, 'round' => $plan->round];
            if ($plan->changed !== [] || $plan->note !== $cur->note) {
                $this->log($tx, $cur->jobId, $actorId, ReviewNotify::ASSET_SUBMITTED, 'asset', $cur->assetId, $base + ['parts' => $parts, 'recipients' => []], $now);
            }
            if ($plan->requestReview) {
                $this->log($tx, $cur->jobId, $actorId, ReviewNotify::REVIEW_REQUESTED, 'asset', $cur->assetId, $base + ['scope' => 'asset',
                    'recipients' => ReviewNotify::recipients(ReviewNotify::REVIEW_REQUESTED, $team, $jr, $ecds, $creator, [], $actorId)], $now);
            } elseif ($completes) {
                $this->log($tx, $cur->jobId, $actorId, ReviewNotify::REVIEW_REQUESTED, 'job', $cur->jobId, ['scope' => 'job', 'count' => count($after),
                    'recipients' => ReviewNotify::recipients(ReviewNotify::REVIEW_REQUESTED, $team, $jr, $ecds, $creator, [], $actorId)], $now);
            }
            $warned = ReviewRules::passesRoundLimit($plan->round, $plan->newRound, $roundLimit);
            if ($warned) {
                $this->log($tx, $cur->jobId, $actorId, ReviewNotify::ROUND_LIMIT_WARNING, 'asset', $cur->assetId, $base + ['limit' => $roundLimit,
                    'recipients' => ReviewNotify::recipients(ReviewNotify::ROUND_LIMIT_WARNING, $team, $jr, $ecds, $creator, [], $actorId)], $now);
            }
            $this->mirrorAsset($tx, $cur->assetId, (string) $status, $plan->requestReview ? ReviewEvent::ReviewRequested : ReviewEvent::Submitted, false, $at);
            [$from, $to] = $this->follow($tx, $cur->jobId, $plan->requestReview || $completes ? ReviewEvent::ReviewRequested : ReviewEvent::Submitted, '', $actorId, $now);
            return ReviewWrite::done($from, $to, $warned, false, $plan->round);
        });
    }

    /**
     * A reviewer's decision on parts of a post (checked by ReviewRules::
     * reviewProblems on $cur). A rejection goes to the maker of each part,
     * clears the CD and ECD approvals and the client steps, and sends an
     * in-review or internally approved job back to in_progress.
     * @param list<ReviewPart> $parts
     */
    public function review(AssetReview $cur, array $parts, ReviewDecision $d, string $feedback, string $actorId, DateTimeImmutable $now): ReviewWrite
    {
        return $this->tx(function (Db $tx) use ($cur, $parts, $d, $feedback, $actorId, $now): ReviewWrite {
            $row = $tx->one('SELECT id, round, row_version FROM asset_submissions WHERE asset_id = :a ORDER BY round DESC LIMIT 1', ['a' => $cur->assetId]);
            if ($row === null || (int) $row['row_version'] !== $cur->rowVersion || (int) $row['round'] !== $cur->round) {
                return ReviewWrite::stale();
            }
            if ($d->isRejection()) {
                $job = $this->jobs->get($cur->jobId);
                $paused = $job === null ? '' : ReviewRules::pausedRejectionProblem($job->stage, $job->resumeStage);
                if ($paused !== '') {
                    return ReviewWrite::refused($paused);
                }
            }
            $status = (string) ($tx->scalar('SELECT status FROM assets WHERE id = :a', ['a' => $cur->assetId]) ?? '');
            $copy = $cur->copyState;
            $media = $cur->mediaState;
            foreach ($parts as $p) {
                if ($p === ReviewPart::Copy) {
                    $copy = $d->state();
                } else {
                    $media = $d->state();
                }
            }
            $n = $tx->exec('UPDATE asset_submissions SET copy_state = :c, media_state = :m, row_version = row_version + 1 WHERE id = :id AND row_version = :rv',
                ['c' => $copy->value, 'm' => $media->value, 'id' => (string) $row['id'], 'rv' => $cur->rowVersion]);
            if ($n === 0) {
                throw new StaleWrite();
            }
            $at = Ids::utc($now);
            $version = $this->briefVersion($tx, $cur->jobId);
            $partNames = [];
            foreach ($parts as $p) {
                $partNames[] = $p->value;
                $tx->exec(
                    'INSERT INTO asset_reviews (id, asset_id, job_id, submission_id, part, round, decision, feedback, reviewer_id, reviewed_at, brief_version)
                     VALUES (:id, :a, :j, :s, :p, :r, :d, :f, :u, :at, :v)',
                    ['id' => Ids::new(), 'a' => $cur->assetId, 'j' => $cur->jobId, 's' => (string) $row['id'], 'p' => $p->value, 'r' => $cur->round,
                        'd' => $d->value, 'f' => $feedback === '' ? null : $feedback, 'u' => $actorId, 'at' => $at, 'v' => $version === '' ? null : $version],
                );
            }
            $team = $this->team($tx, $cur->jobId);
            $jr = $this->jobReview($cur->jobId);
            $base = ['asset_id' => $cur->assetId, 'asset_name' => $cur->assetName, 'parts' => $partNames, 'decision' => $d->value, 'round' => $cur->round,
                'brief_version' => $version];
            if (!$d->isRejection()) {
                $this->touchJob($tx, $cur->jobId, [], $now);
                $this->log($tx, $cur->jobId, $actorId, ReviewNotify::PART_APPROVED, 'asset', $cur->assetId, $base + ['recipients' => []], $now);
                $allApproved = true;
                foreach ($cur->requiredParts() as $p) {
                    $allApproved = $allApproved && ($p === ReviewPart::Copy ? $copy : $media) === PartState::Approved;
                }
                $this->mirrorAsset($tx, $cur->assetId, $status, ReviewEvent::Approved, $allApproved, $at);
                $stage = $this->jobs->get($cur->jobId)?->stage;
                return ReviewWrite::done($stage, $stage, false, false, $cur->round);
            }
            $makers = [];
            $makerByPart = [];
            foreach ($parts as $p) {
                $m = ReviewRules::maker($cur, $p, $team);
                $makerByPart[$p->value] = $m;
                if ($m !== null) {
                    $makers[] = $m;
                }
            }
            $cleared = ReviewRules::rejectionClears($jr);
            $set = [];
            foreach (self::CLEARED_ON_REJECTION as $col) {
                $set[$col] = null;
            }
            $this->touchJob($tx, $cur->jobId, $set, $now);
            $this->log($tx, $cur->jobId, $actorId, ReviewNotify::PART_REJECTED, 'asset', $cur->assetId, $base + [
                'feedback' => $feedback, 'makers' => $makerByPart, 'cleared_approvals' => $cleared,
                'recipients' => ReviewNotify::recipients(ReviewNotify::PART_REJECTED, $team, $jr, $this->ecdIds(), $this->creator($tx, $cur->jobId), $makers, $actorId),
            ], $now);
            $this->mirrorAsset($tx, $cur->assetId, $status, ReviewEvent::Rejected, false, $at);
            $reason = $cur->assetName . ': ' . implode(' and ', $partNames) . ' ' . strtolower($d->label()) . ($feedback !== '' ? ' (' . $feedback . ')' : '');
            [$from, $to] = $this->follow($tx, $cur->jobId, ReviewEvent::Rejected, mb_substr($reason, 0, Transitions::MAX_REASON), $actorId, $now);
            return ReviewWrite::done($from, $to, false, $cleared, $cur->round);
        });
    }

    // ---- writes: the job -------------------------------------------------------------

    /** The CD approves the job (every post approved). Notifies every ECD; an in-progress job moves to in review. */
    public function approveJobCd(string $jobId, int $rv, string $actorId, DateTimeImmutable $now): ReviewWrite
    {
        return $this->jobWrite($jobId, $rv, $actorId, $now, true, ['cd_approved_by' => $actorId, 'cd_approved_at' => Ids::utc($now)],
            ReviewNotify::JOB_CD_APPROVED, [], ReviewEvent::ReviewRequested, static fn (JobReview $jr, Stage $stage): string => $jr->cdApproved() ? 'The job is already approved by the CD.' : '');
    }

    /** The CD asks the ECD to review the job, even before every post is done. Notifies every ECD. */
    public function requestEcdReview(string $jobId, int $rv, string $actorId, DateTimeImmutable $now): ReviewWrite
    {
        return $this->jobWrite($jobId, $rv, $actorId, $now, false, ['ecd_requested_by' => $actorId, 'ecd_requested_at' => Ids::utc($now)],
            ReviewNotify::JOB_REVIEW_REQUESTED, [], ReviewEvent::ReviewRequested, static fn (JobReview $jr, Stage $stage): string => $jr->ecdApproved() ? 'The ECD has already approved this job.' : '');
    }

    /** The ECD approves a CD-approved job. Notifies the AM and the CD; the job moves to approved (internal). */
    public function approveJobEcd(string $jobId, int $rv, string $actorId, DateTimeImmutable $now): ReviewWrite
    {
        return $this->jobWrite($jobId, $rv, $actorId, $now, true, ['ecd_approved_by' => $actorId, 'ecd_approved_at' => Ids::utc($now)],
            ReviewNotify::JOB_ECD_APPROVED, [], ReviewEvent::EcdApproved,
            static fn (JobReview $jr, Stage $stage): string => $jr->ecdApproved() ? 'The ECD has already approved this job.' : (!$jr->cdApproved() ? 'The CD approves the job first.'
                : ReviewRules::pausedProblem($stage, 'the ECD approval moves it to Approved (internal)')));
    }

    /** Hand the review to someone else (checked by ReviewPolicy::reassignTargetProblem). Notifies them (not a client). */
    public function reassign(string $jobId, int $rv, User $target, string $actorId, DateTimeImmutable $now): ReviewWrite
    {
        return $this->jobWrite($jobId, $rv, $actorId, $now, false,
            ['review_assignee_id' => $target->id, 'review_assigned_by' => $actorId, 'review_assigned_at' => Ids::utc($now)],
            ReviewNotify::REVIEW_REASSIGNED, ['to' => $target->id, 'to_name' => $target->name, 'to_role' => $target->role->value], null,
            static fn (JobReview $jr, Stage $stage): string => $target->role === Role::Client && !$jr->ecdApproved() ? 'The client can review only after the ECD has approved the job.' : '',
            $target->role === Role::Client ? [] : [$target->id]);
    }

    /** Ready for client review (CD and ECD approved, stage approved_internal). Notifies the AM and Traffic. */
    public function markReady(string $jobId, int $rv, string $actorId, DateTimeImmutable $now): ReviewWrite
    {
        return $this->jobWrite($jobId, $rv, $actorId, $now, true, ['client_ready_by' => $actorId, 'client_ready_at' => Ids::utc($now)],
            ReviewNotify::JOB_READY_FOR_CLIENT, [], null,
            static fn (JobReview $jr, Stage $stage): string => !$jr->internallyApproved() ? 'The CD and the ECD must both approve the job first.'
                : ($stage !== Stage::ApprovedInternal ? 'The job must be approved (internal) to go to the client.' : ($jr->readyForClient() ? 'The job is already ready for client review.' : '')));
    }

    /** Sent to the client by hand (no client portal yet). Notifies the AM and Traffic. */
    public function sendToClient(string $jobId, int $rv, string $actorId, DateTimeImmutable $now): ReviewWrite
    {
        return $this->jobWrite($jobId, $rv, $actorId, $now, true, ['sent_to_client_by' => $actorId, 'sent_to_client_at' => Ids::utc($now)],
            ReviewNotify::JOB_SENT_TO_CLIENT, [], null,
            static fn (JobReview $jr, Stage $stage): string => !$jr->readyForClient() ? 'Mark the job ready for client review first.'
                : ($stage !== Stage::ApprovedInternal ? 'The job must be approved (internal) to go to the client.' : ($jr->sentToClient() ? 'The job was already sent to the client.' : '')));
    }

    // ---- helpers ------------------------------------------------------------------

    /**
     * One job-level step: the job_reviews row must still be at $rv (0: no row
     * yet), the fresh state must pass $problem ('' = fine) and, when
     * $needsAllApproved, every post must be approved. Then $set, one activity
     * row ($verb, recipients from ReviewNotify) and the stage moves of $event.
     * @param array<string,string|null> $set column => value (columns are code)
     * @param array<string,string> $data extra activity data
     * @param \Closure(JobReview, Stage): string $problem
     * @param list<string> $extra event-specific recipients (the new reviewer)
     */
    private function jobWrite(string $jobId, int $rv, string $actorId, DateTimeImmutable $now, bool $needsAllApproved, array $set, string $verb, array $data,
        ?ReviewEvent $event, \Closure $problem, array $extra = []): ReviewWrite
    {
        return $this->tx(function (Db $tx) use ($jobId, $rv, $actorId, $now, $needsAllApproved, $set, $verb, $data, $event, $problem, $extra): ReviewWrite {
            $jr = $this->jobReview($jobId);
            if ($jr->rowVersion !== $rv) {
                return ReviewWrite::stale();
            }
            $job = $this->jobs->get($jobId);
            if ($job === null) {
                return ReviewWrite::refused('This job no longer exists.');
            }
            $why = $problem($jr, $job->stage);
            if ($why !== '') {
                return ReviewWrite::refused($why);
            }
            if ($needsAllApproved && ReviewRules::aggregate($this->assets($jobId)) !== JobReviewStatus::AllApproved) {
                return ReviewWrite::refused('Every post (copy and media) must be approved first.');
            }
            if ($rv === 0) {
                $tx->exec('INSERT OR IGNORE INTO job_reviews (job_id, job_round, row_version, updated_at) VALUES (:j, 1, 0, :at)', ['j' => $jobId, 'at' => Ids::utc($now)]);
            }
            $cols = [];
            $params = ['j' => $jobId, 'rv' => $rv, 'at' => Ids::utc($now)];
            $i = 0;
            foreach ($set as $col => $v) {
                $cols[] = $col . ' = :v' . $i;
                $params['v' . $i] = $v;
                $i++;
            }
            $n = $tx->exec('UPDATE job_reviews SET ' . implode(', ', $cols) . ', updated_at = :at, row_version = row_version + 1 WHERE job_id = :j AND row_version = :rv', $params);
            if ($n === 0) {
                throw new StaleWrite();
            }
            $team = $this->team($tx, $jobId);
            $after = $this->jobReview($jobId);
            $this->log($tx, $jobId, $actorId, $verb, 'job', $jobId, $data + [
                'recipients' => ReviewNotify::recipients($verb, $team, $after, $this->ecdIds(), $this->creator($tx, $jobId), $extra, $actorId),
            ], $now);
            if ($event === null) {
                return ReviewWrite::done($job->stage, $job->stage);
            }
            [$from, $to] = $this->follow($tx, $jobId, $event, '', $actorId, $now);
            return ReviewWrite::done($from, $to);
        });
    }

    /**
     * Upsert the job review row and bump its row_version (every review write
     * does, so an open action bar goes stale), with optional column values.
     * @param array<string,string|int|null> $set
     */
    private function touchJob(Db $tx, string $jobId, array $set, DateTimeImmutable $now): void
    {
        $at = Ids::utc($now);
        $tx->exec('INSERT OR IGNORE INTO job_reviews (job_id, job_round, row_version, updated_at) VALUES (:j, 1, 0, :at)', ['j' => $jobId, 'at' => $at]);
        $cols = [];
        $params = ['j' => $jobId, 'at' => $at];
        $i = 0;
        foreach ($set as $col => $v) {
            $cols[] = $col . ' = :v' . $i;
            $params['v' . $i] = $v;
            $i++;
        }
        $tx->exec('UPDATE job_reviews SET ' . ($cols !== [] ? implode(', ', $cols) . ', ' : '') . 'updated_at = :at, row_version = row_version + 1 WHERE job_id = :j', $params);
    }

    /**
     * The named stage moves a review event implies (ReviewRules::stageMoves),
     * one Transitions move at a time through JobStore::applyTransitionTx.
     * @return array{0:?Stage,1:?Stage} stage before and after
     */
    private function follow(Db $tx, string $jobId, ReviewEvent $e, string $reason, string $actorId, DateTimeImmutable $now): array
    {
        $job = $this->jobs->get($jobId);
        if ($job === null) {
            return [null, null];
        }
        $from = $job->stage;
        foreach (ReviewRules::stageMoves($job->stage, $e) as $action) {
            $o = Transitions::plan($job->stage, $job->resumeStage, new TransitionRequest($action, null, $action === JobAction::SendBack ? ($reason !== '' ? $reason : 'Rejected in review') : ''), true);
            if (!$o->ok()) {
                break;
            }
            // The review activity row carries the notification; the stage row is the log.
            if (!$this->jobs->applyTransitionTx($tx, $job, $o, $actorId, $now, Notifications::verbFor($action), ['recipients' => [], 'via' => 'review'])) {
                throw new StaleWrite();
            }
            $job = $this->jobs->get($jobId);
            if ($job === null) {
                break;
            }
        }
        return [$from, $job !== null ? $job->stage : $from];
    }

    /** Write the legacy assets.status a review event implies (ReviewRules::legacyAssetStatus). */
    private function mirrorAsset(Db $tx, string $assetId, string $current, ReviewEvent $e, bool $allApproved, string $at): void
    {
        $to = ReviewRules::legacyAssetStatus($current, $e, $allApproved);
        if ($to !== null) {
            $tx->exec('UPDATE assets SET status = :s, updated_at = :at WHERE id = :a AND status = :cur', ['s' => $to, 'at' => $at, 'a' => $assetId, 'cur' => $current]);
        }
    }

    /** The sent brief version ("1.2.0") the reviewer checked against; '' before the first send. */
    private function briefVersion(Db $tx, string $jobId): string
    {
        $r = $tx->one('SELECT version_major, version_minor, version_patch, sent_at FROM briefs WHERE job_id = :j', ['j' => $jobId]);
        if ($r === null || $r['sent_at'] === null) {
            return '';
        }
        return (int) $r['version_major'] . '.' . (int) $r['version_minor'] . '.' . (int) $r['version_patch'];
    }

    /**
     * txImmediate where a StaleWrite thrown inside rolls everything back and answers stale.
     * @param \Closure(Db): ReviewWrite $fn
     */
    private function tx(\Closure $fn): ReviewWrite
    {
        try {
            return $this->db->txImmediate($fn);
        } catch (StaleWrite $e) {
            return ReviewWrite::stale();
        }
    }

    /** @param array<string,mixed> $data */
    private function log(Db $tx, string $jobId, string $actorId, string $verb, string $entityType, string $entityId, array $data, DateTimeImmutable $now): void
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
}
