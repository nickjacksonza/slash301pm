<?php
declare(strict_types=1);

// Social publishing fixtures: a brief sent through the real flow, its assets
// created by the send, then client approval written the way legacy
// approve_client does it (status 'Approved (External)'; the 0002 trigger maps
// the stage to approved_client).

use App\Domain\Role;
use App\Http\Deps;
use App\Http\MemorySession;
use App\Http\Response;

require_once __DIR__ . '/brief_http.php';

/**
 * A sent brief with $qty Instagram statics, Traffic and (optionally) Social
 * slots, approved by the client through legacy SQL. Budget 25000 is set so
 * tests can prove it never leaks.
 * @return array{0:Deps,1:array<string,string>,2:string} [deps, people (bd_seed_people + social, social2, pm), job id]
 */
function sx_world(int $qty = 2, bool $socialSlot = true, bool $approve = true): array
{
    [$d, $c, $p] = bh_world();
    $p['social'] = ts_user($d, 'social_sol', Role::Social);
    $p['social2'] = ts_user($d, 'social_sam', Role::Social);
    $p['pm'] = ts_user($d, 'pm_pat', Role::PM);
    $jobId = sx_seed($d, $c['campaign'], $p['am'], $p['traffic'], $qty, 'Grand Opening Social');
    if ($socialSlot) {
        $d->db->exec("INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :j, :u, 'Social')", ['id' => bin2hex(random_bytes(16)), 'j' => $jobId, 'u' => $p['social']]);
    }
    if ($approve) {
        sx_legacy_approve($d, $jobId);
    }
    return [$d, $p, $jobId];
}

/** On an existing world: the AM creates, fills and sends a brief with $qty Instagram statics. Returns the job id. */
function sx_seed(Deps $d, string $campaignId, string $amId, string $trafficId, int $qty, string $title): string
{
    $amy = bh_session($d, $amId);
    $body = ts_body(bh_ds($d, $amy, 'POST', '/briefs', ['nb' => ['campaign_id' => $campaignId, 'title' => $title]]));
    if (preg_match('#/slash301pm/jobs/([0-9a-f]{32})/brief#', $body, $m) !== 1) {
        throw new TestFailure('brief not created: ' . substr($body, 0, 300));
    }
    $jobId = $m[1];
    $rv = static fn (): int => $d->briefs->getByJob($jobId)->rowVersion;
    sx_ok(bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => [
        'title' => $title, 'due_date' => '2026-10-20', 'last_go_live' => '2026-10-25', 'creative_direction' => 'Warm and gold.', 'budget' => '25000', 'row_version' => $rv()]]));
    sx_ok(bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => 'social-static']]));
    $line = $d->briefAssets->listByBrief($d->briefs->getByJob($jobId)->id)[0];
    sx_ok(bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief/assets/' . $line->id, ['dl' => ['ln_' . $line->id => [
        'template_id' => 'social-static', 'label' => 'Social Post (Static)', 'qty' => $qty, 'channel' => 'Instagram', 'size_format' => '1080x1350', 'specs' => '']]]));
    sx_ok(bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/assignments/traffic', ['team_traffic' => ['value' => $trafficId]]));
    sx_ok(bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/send'));
    return $jobId;
}

/** What legacy approve_client writes (api/api.php): status and the client approval stamp. */
function sx_legacy_approve(Deps $d, string $jobId): void
{
    $d->db->exec("UPDATE jobs SET status = 'Approved (External)', client_approved_at = datetime('now') WHERE id = :j", ['j' => $jobId]);
}

function sx_ok(Response $r): void
{
    $body = ts_body($r);
    if (str_contains($body, 'data-toast="error"')) {
        throw new TestFailure('setup request failed: ' . substr($body, 0, 600));
    }
}

/** @return list<string> asset ids of the job, in order */
function sx_assets(Deps $d, string $jobId): array
{
    $out = [];
    foreach ($d->assets->listByJob($jobId) as $a) {
        $out[] = $a->id;
    }
    return $out;
}

/** The card signals of a publication, as the page holds them. @param array<string,mixed> $o overrides */
function sx_sig(Deps $d, string $pid, array $o = []): array
{
    $p = $d->publications->get($pid);
    if ($p === null) {
        throw new TestFailure('no publication ' . $pid);
    }
    $cl = [];
    foreach (\App\Domain\ChecklistItem::cases() as $item) {
        $cl[$item->value] = $p->checklist->entry($item)->ok;
        $cl[$item->value . '_note'] = $p->checklist->entry($item)->note;
    }
    $base = ['rv' => $p->rowVersion, 'cl' => $cl, 'scheduled_at' => $p->scheduledAt !== null ? str_replace(' ', 'T', $p->scheduledAt) : '',
        'live_url' => $p->liveUrl, 'promoted' => $p->promoted, 'promoted_note' => $p->promotedNote, 'reason' => ''];
    return ['pub_' . $pid => array_replace_recursive($base, $o)];
}

/** Add a platform to an asset as $s and return the new publication id. */
function sx_add(Deps $d, MemorySession $s, string $assetId, string $platform): string
{
    sx_ok(bh_ds($d, $s, 'POST', '/social/assets/' . $assetId . '/platforms/' . $platform));
    $id = $d->db->scalar('SELECT id FROM asset_publications WHERE asset_id = :a AND platform = :p', ['a' => $assetId, 'p' => $platform]);
    if ($id === null) {
        throw new TestFailure("platform $platform was not added");
    }
    return (string) $id;
}

/** Tick all five and save, as $s. */
function sx_tick_all(Deps $d, MemorySession $s, string $pid): void
{
    $all = ['copy' => true, 'image' => true, 'link' => true, 'hashtags' => true, 'test_result' => true];
    sx_ok(bh_ds($d, $s, 'PATCH', '/social/publications/' . $pid . '/checklist', sx_sig($d, $pid, ['cl' => $all])));
}

function sx_stage(Deps $d, string $jobId): string
{
    return (string) $d->db->scalar('SELECT stage FROM jobs WHERE id = :j', ['j' => $jobId]);
}

function sx_status(Deps $d, string $jobId): string
{
    return (string) $d->db->scalar('SELECT status FROM jobs WHERE id = :j', ['j' => $jobId]);
}
