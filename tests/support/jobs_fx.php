<?php
declare(strict_types=1);

// Seed helpers for the job grid, board and saved view tests (Phase 3).

use App\Domain\Policy;
use App\Domain\Types\JobSearchResult;
use App\Domain\Types\User;
use App\Domain\JobQuery;
use App\Http\Deps;
use App\Store\Ids;

require_once __DIR__ . '/brief_http.php';

/**
 * A job inserted the way legacy add_job does (the 0002/0003 triggers fill the
 * stage and the brief). $o: status, due, created_by, hours, slots (role => user id),
 * asset (assignee id or ''), asset_status.
 */
function jf_job(Deps $d, string $number, string $campaignId, string $title, array $o = []): string
{
    $id = Ids::new();
    $d->db->exec(
        'INSERT INTO jobs (id, job_number, campaign_id, title, status, delivery_date, hours_estimate, created_by) VALUES (:id, :n, :c, :t, :s, :due, :h, :by)',
        ['id' => $id, 'n' => $number, 'c' => $campaignId, 't' => $title, 's' => $o['status'] ?? 'In Progress', 'due' => $o['due'] ?? null,
            'h' => $o['hours'] ?? null, 'by' => $o['created_by'] ?? null],
    );
    $d->db->exec('UPDATE briefs SET created_by = :by WHERE job_id = :j', ['by' => $o['created_by'] ?? null, 'j' => $id]);
    foreach ($o['slots'] ?? [] as $role => $uid) {
        $d->db->exec('INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :j, :u, :r)', ['id' => Ids::new(), 'j' => $id, 'u' => $uid, 'r' => $role]);
        if ($role === 'AM') {
            $d->db->exec('UPDATE jobs SET am_user_id = :u WHERE id = :j', ['u' => $uid, 'j' => $id]);
        }
    }
    if (isset($o['asset'])) {
        $d->db->exec("INSERT INTO assets (id, job_id, campaign_id, name, type, status, assigned_to) VALUES (:id, :j, :c, 'A', 'image', :st, :u)",
            ['id' => Ids::new(), 'j' => $id, 'c' => $campaignId, 'st' => $o['asset_status'] ?? 'Inbox', 'u' => $o['asset'] === '' ? null : $o['asset']]);
    }
    return $id;
}

/**
 * Two brands, three campaigns, the bd_seed_people() team and nine jobs on
 * Friday 2026-10-09. @return array{0:Deps,1:array<string,string>,2:array<string,string>,3:array<string,string>}
 *   [deps, people, campaigns (merc, merc2, aura), jobs by key]
 */
function jf_world(): array
{
    $d = ts_deps();
    $m = bd_seed_campaign($d);
    $a = bd_seed_campaign($d, 'AURA', 'Aura Spa', 'Winter Glow');
    $d->db->exec("INSERT INTO campaigns (id, brand_id, name, status) VALUES ('camp_merc2', 'brand_merc', 'Summer Menu', 'active')");
    $p = bd_seed_people($d);
    $c = ['merc' => $m['campaign'], 'merc2' => 'camp_merc2', 'aura' => $a['campaign']];
    $j = [];
    $j['mine_overdue'] = jf_job($d, 'MERC-001', $c['merc'], 'Opening poster', ['due' => '2026-10-05', 'slots' => ['AM' => $p['am'], 'Traffic' => $p['traffic']], 'hours' => 12.5]);
    $j['mine_today'] = jf_job($d, 'MERC-002', $c['merc'], 'Launch email 50% off', ['due' => '2026-10-09', 'slots' => ['AM' => $p['am']]]);
    $j['created_no_am'] = jf_job($d, 'MERC-003', $c['merc2'], 'Menu cards', ['status' => 'Inbox', 'due' => '2026-10-11', 'created_by' => $p['am']]);
    $j['other_am'] = jf_job($d, 'MERC-004', $c['merc2'], 'Summer social', ['due' => '2026-10-20', 'slots' => ['AM' => $p['am2'], 'Traffic' => $p['traffic2']]]);
    $j['unowned'] = jf_job($d, 'AURA-001', $c['aura'], 'Spa brochure', ['status' => 'Waiting', 'due' => null]);
    $j['aura_review'] = jf_job($d, 'AURA-002', $c['aura'], 'Glow video', ['status' => 'In Review', 'due' => '2026-10-15', 'slots' => ['Traffic' => $p['traffic']]]);
    $j['done'] = jf_job($d, 'AURA-003', $c['aura'], 'Old campaign', ['status' => 'Done', 'due' => '2026-09-01', 'slots' => ['AM' => $p['am']]]);
    $j['designer_job'] = jf_job($d, 'AURA-004', $c['aura'], 'Designer only', ['status' => 'To Do', 'due' => '2026-10-12', 'asset' => $p['designer'], 'asset_status' => 'In Progress']);
    $j['onhold'] = jf_job($d, 'MERC-005', $c['merc'], 'Paused banner', ['status' => 'On Hold', 'due' => '2026-10-30', 'slots' => ['AM' => $p['am']]]);
    return [$d, $p, $c, $j];
}

function jf_search(Deps $d, User $u, array $query, string $screen = 'jobs'): JobSearchResult
{
    return $d->jobQuery->search(JobQuery::fromQuery($query, $screen), Policy::jobViewer($u), '2026-10-09', '2026-10-14');
}

/** @return list<string> job numbers in result order */
function jf_numbers(JobSearchResult $r): array
{
    $out = [];
    foreach ($r->rows as $row) {
        $out[] = $row->jobNumber;
    }
    return $out;
}
