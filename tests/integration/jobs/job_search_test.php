<?php
declare(strict_types=1);

use App\Clock\FixedClock;
use App\Domain\JobQuery;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\Types\BriefPatch;
use App\Http\Deps;
use App\Store\Db;
use App\Store\Ids;
use App\Store\JobQueryStore;

require_once dirname(__DIR__, 2) . '/support/jobs_fx.php';
require_once dirname(__DIR__, 2) . '/support/live_copy.php';

return [
    'job search: filters, mine, unowned, due windows, text, brand and campaign (table)' => function (): void {
        [$d, $p] = jf_world();
        $am = $d->users->findById($p['am']);
        $cases = [
            'open (default)' => [[], ['MERC-001', 'MERC-002', 'MERC-003', 'AURA-004', 'AURA-002', 'MERC-004', 'MERC-005', 'AURA-001']],
            'all stages' => [['stages' => 'all', 'sort' => 'job_number'], ['AURA-001', 'AURA-002', 'AURA-003', 'AURA-004', 'MERC-001', 'MERC-002', 'MERC-003', 'MERC-004', 'MERC-005']],
            'mine: AM slot, or creator when no AM' => [['owner' => 'mine', 'sort' => 'job_number'], ['MERC-001', 'MERC-002', 'MERC-003', 'MERC-005']],
            'unowned' => [['owner' => 'unowned', 'sort' => 'job_number'], ['AURA-001', 'AURA-002', 'AURA-004', 'MERC-003']],
            'overdue (open only)' => [['due' => 'overdue', 'stages' => 'all'], ['MERC-001']],
            'today' => [['due' => 'today'], ['MERC-002']],
            'this week (to Sunday)' => [['due' => 'this_week'], ['MERC-002', 'MERC-003']],
            'next 14 days' => [['due' => 'next_14'], ['MERC-002', 'MERC-003', 'AURA-004', 'AURA-002', 'MERC-004']],
            'no due date' => [['due' => 'none'], ['AURA-001']],
            'text: job number' => [['q' => 'merc-00'], ['MERC-001', 'MERC-002', 'MERC-003', 'MERC-004', 'MERC-005']],
            'text: literal percent' => [['q' => '50%'], ['MERC-002']],
            'text: underscore is literal' => [['q' => 'a_b'], []],
            'text: brand name' => [['q' => 'spa'], ['AURA-004', 'AURA-002', 'AURA-001']],
            'text: campaign name' => [['q' => 'summer'], ['MERC-003', 'MERC-004']],
            'brand' => [['brand' => 'brand_aura'], ['AURA-004', 'AURA-002', 'AURA-001']],
            'campaign' => [['campaign' => 'camp_merc2'], ['MERC-003', 'MERC-004']],
            'Traffic slot empty' => [['assignee' => 'none', 'role' => 'Traffic', 'sort' => 'job_number'], ['AURA-001', 'AURA-004', 'MERC-002', 'MERC-003', 'MERC-005']],
            'assignee + role' => [['assignee' => $p['traffic'], 'role' => 'Traffic', 'sort' => 'job_number'], ['AURA-002', 'MERC-001']],
            'assignee me' => [['assignee' => 'me', 'stages' => 'all', 'sort' => 'job_number'], ['AURA-003', 'MERC-001', 'MERC-002', 'MERC-005']],
            'stage set' => [['stages' => 'waiting,on_hold'], ['MERC-005', 'AURA-001']],
        ];
        foreach ($cases as $name => [$query, $want]) {
            $r = jf_search($d, $am, $query);
            t_eq($want, jf_numbers($r), $name);
            t_eq(count($want), $r->total, $name . ' total');
        }
    },
    'job search: sort (asc, desc, two keys, nulls last) and group order (table)' => function (): void {
        [$d, $p] = jf_world();
        $am = $d->users->findById($p['am']);
        $cases = [
            'title desc' => [['sort' => '-title'], ['MERC-004', 'AURA-001', 'MERC-005', 'MERC-001', 'MERC-003', 'MERC-002', 'AURA-002', 'AURA-004']],
            'hours, nulls last' => [['sort' => 'hours,job_number', 'brand' => 'brand_merc'], ['MERC-001', 'MERC-002', 'MERC-003', 'MERC-004', 'MERC-005']],
            'stage then number desc' => [['sort' => 'stage,-job_number'], ['MERC-003', 'AURA-004', 'MERC-004', 'MERC-002', 'MERC-001', 'AURA-001', 'MERC-005', 'AURA-002']],
            'group brand, due inside' => [['group' => 'brand'], ['AURA-004', 'AURA-002', 'AURA-001', 'MERC-001', 'MERC-002', 'MERC-003', 'MERC-004', 'MERC-005']],
            'group AM (none last)' => [['group' => 'am', 'sort' => 'job_number'], ['MERC-001', 'MERC-002', 'MERC-005', 'MERC-004', 'AURA-001', 'AURA-002', 'AURA-004', 'MERC-003']],
            'group due bucket' => [['group' => 'due_bucket', 'sort' => 'job_number'], ['MERC-001', 'MERC-002', 'AURA-004', 'MERC-003', 'AURA-002', 'MERC-004', 'MERC-005', 'AURA-001']],
        ];
        foreach ($cases as $name => [$query, $want]) {
            t_eq($want, jf_numbers(jf_search($d, $am, $query)), $name);
        }
    },
    'job search: viewer scope and working copy vs sent values' => function (): void {
        [$d, $p, , $j] = jf_world();
        // a sent brief (MERC-001 is In Progress, so 1.0.0) with an unsent title and due date in its working copy
        $brief = $d->briefs->getByJob($j['mine_overdue']);
        t_true($brief->isSent());
        $d->briefs->save($brief, new BriefPatch(['title', 'due_date'], title: 'Opening poster v2', dueDate: '2026-10-10'), null, $p['am'], $d->clock->now());
        $am = $d->users->findById($p['am']);
        $am2 = $d->users->findById($p['am2']);
        $traffic = $d->users->findById($p['traffic']);
        $row = static function ($r, string $id) {
            foreach ($r->rows as $x) {
                if ($x->id === $id) {
                    return $x;
                }
            }
            throw new TestFailure('row missing');
        };
        $mine = $row(jf_search($d, $am, []), $j['mine_overdue']);
        t_eq('Opening poster v2', $mine->title, 'the owner sees the working copy');
        t_eq('2026-10-10', $mine->dueDate);
        t_true($mine->workingCopy && $mine->hasUnsentChanges);
        foreach ([$am2, $traffic] as $other) {
            $x = $row(jf_search($d, $other, []), $j['mine_overdue']);
            t_eq('Opening poster', $x->title, $other->username . ' sees the sent title');
            t_eq('2026-10-05', $x->dueDate);
            t_true(!$x->workingCopy);
        }
        t_eq([], jf_numbers(jf_search($d, $traffic, ['q' => 'v2'])), 'search does not leak working-copy text');
        t_eq(['MERC-001'], jf_numbers(jf_search($d, $am, ['q' => 'v2'])));
        // view_all_jobs: makers see assigned jobs only (asset assignee counts), clients their brand only
        $designer = $d->users->findById($p['designer']);
        t_eq(['AURA-004'], jf_numbers(jf_search($d, $designer, ['stages' => 'all'])));
        $client = $d->users->findById(ts_user($d, 'client_c', Role::Client));
        t_eq([], jf_numbers(jf_search($d, $client, ['stages' => 'all'])));
        $d->db->exec("UPDATE users SET brand_id = 'brand_aura' WHERE id = :id", ['id' => $client->id]);
        $client = $d->users->findById($client->id);
        t_eq(['AURA-001', 'AURA-002', 'AURA-003', 'AURA-004'], jf_numbers(jf_search($d, $client, ['stages' => 'all', 'sort' => 'job_number'])));
        // access built from the page data agrees with JobStore::access
        $r = jf_search($d, $am, ['stages' => 'all']);
        foreach ($r->rows as $x) {
            $a = $x->access($r->team($x->id));
            $b = $d->jobs->access($x->id);
            t_eq($b->creatorId, $a->creatorId, $x->jobNumber . ' creator');
            t_eq($b->anyAssetStarted, $a->anyAssetStarted, $x->jobNumber . ' started');
            t_eq($b->assetAssigneeIds, $a->assetAssigneeIds, $x->jobNumber . ' asset assignees');
            t_eq(count($b->assignments), count($a->assignments), $x->jobNumber . ' slots');
            t_eq($b->briefSent, $a->briefSent, $x->jobNumber . ' sent');
        }
    },
    'job search: every request value is a bound parameter, never SQL text' => function (): void {
        [$d, $p] = jf_world();
        $am = $d->users->findById($p['am']);
        $hostile = "x' OR '1'='1"; // survives JobQuery text cleaning
        $q = JobQuery::fromQuery(['q' => $hostile, 'brand' => 'brand_aura', 'campaign' => 'camp_x', 'assignee' => $p['traffic'], 'role' => 'Traffic', 'due' => 'next_14'], 'jobs');
        [$sql, $params] = $d->jobQuery->sql($q, Policy::jobViewer($am), '2026-10-09', '2026-10-14');
        t_not_contains($hostile, $sql);
        t_not_contains('brand_aura', $sql);
        t_not_contains($p['traffic'], $sql);
        t_not_contains('2026-10', $sql);
        t_eq('%' . $hostile . '%', $params['text']);
        t_eq('brand_aura', $params['brand']);
        t_eq($p['traffic'], $params['fuser']);
        t_eq('2026-10-23', $params['dto']);
        preg_match_all('/:([a-z0-9]+)/', $sql, $m);
        foreach (array_unique($m[1]) as $name) {
            t_true(array_key_exists($name, $params), 'placeholder :' . $name . ' is bound');
        }
        t_eq([], jf_numbers(jf_search($d, $am, ['q' => $hostile])), 'and it finds nothing');
        t_eq(9, (int) $d->db->scalar('SELECT COUNT(*) FROM jobs'));
    },
    'job search: the cap reports the total and keeps whole result order' => function (): void {
        [$d, $p] = jf_world();
        $am = $d->users->findById($p['am']);
        $r = $d->jobQuery->search(JobQuery::fromQuery(['sort' => 'job_number'], 'jobs'), Policy::jobViewer($am), '2026-10-09', '2026-10-14', 3);
        t_eq(['AURA-001', 'AURA-002', 'AURA-004'], jf_numbers($r));
        t_eq(8, $r->total);
        t_true($r->capped());
        t_eq(3, count($r->teams), 'teams only for the page');
    },
    'job search: live copy plus 300 jobs, rows and teams under 100 ms' => function (): void {
        $m = lc_migrated();
        if ($m === null) {
            return;
        }
        [$db] = $m;
        $camps = $db->query('SELECT id FROM campaigns');
        $users = $db->query("SELECT id, role FROM users WHERE role IN ('AM', 'Traffic', 'CD', 'Designer', 'Copywriter')");
        $statuses = ['Inbox', 'To Do', 'In Progress', 'Today', 'Waiting', 'On Hold', 'In Review', 'Done'];
        $db->txImmediate(function (Db $tx) use ($camps, $users, $statuses): void {
            for ($i = 0; $i < 300; $i++) {
                $id = Ids::new();
                $tx->exec('INSERT INTO jobs (id, job_number, campaign_id, title, status, delivery_date) VALUES (:id, :n, :c, :t, :s, :due)', [
                    'id' => $id, 'n' => 'PERF-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'c' => $camps[$i % count($camps)]['id'],
                    't' => 'Perf job ' . $i, 's' => $statuses[$i % count($statuses)], 'due' => '2026-10-' . str_pad((string) (1 + $i % 28), 2, '0', STR_PAD_LEFT),
                ]);
                foreach ($users as $k => $u) {
                    if (($i + $k) % 3 === 0 && $u['role'] !== 'AM' || ($u['role'] === 'AM' && $i % 2 === 0)) {
                        $tx->exec('INSERT OR IGNORE INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :j, :u, :r)', ['id' => Ids::new(), 'j' => $id, 'u' => $u['id'], 'r' => $u['role']]);
                    }
                }
                $tx->exec("INSERT INTO assets (id, job_id, name, type, status) VALUES (:id, :j, 'a', 'image', 'In Progress')", ['id' => Ids::new(), 'j' => $id]);
            }
        });
        $store = new JobQueryStore($db);
        $am = $db->one("SELECT * FROM users WHERE role = 'AM' LIMIT 1");
        $viewer = Policy::jobViewer(App\Domain\Types\User::fromRow($am));
        $times = [];
        foreach ([['stages' => 'all'], ['stages' => 'open', 'sort' => 'title', 'group' => 'brand'], ['owner' => 'mine', 'q' => 'perf 1'], ['due' => 'next_14', 'sort' => '-updated,stage']] as $query) {
            $store->search(JobQuery::fromQuery($query, 'jobs'), $viewer, '2026-10-09', '2026-10-14'); // warm
            $t = hrtime(true);
            $r = $store->search(JobQuery::fromQuery($query, 'jobs'), $viewer, '2026-10-09', '2026-10-14');
            $ms = (hrtime(true) - $t) / 1e6;
            $times[] = sprintf('%s: %d rows %.1f ms', http_build_query($query), count($r->rows), $ms);
            t_true($ms < 100, 'rows + teams under 100 ms: ' . end($times));
        }
        t_true(count($store->search(JobQuery::fromQuery(['stages' => 'all'], 'jobs'), $viewer, '2026-10-09', '2026-10-14')->rows) >= 318);
        fwrite(STDERR, 'note: job search timings: ' . implode('; ', $times) . "\n");
    },
];
