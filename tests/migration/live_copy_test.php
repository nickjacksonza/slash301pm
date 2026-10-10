<?php
declare(strict_types=1);

use App\Clock\FixedClock;
use App\Domain\Stage;
use App\Store\Db;
use App\Store\Migrator;

require_once dirname(__DIR__) . '/support/app.php';

/**
 * Rehearsal on the owner's download of the live database (data/live-copy.db,
 * gitignored). Works on a temp COPY; the download itself is only read.
 * Skips (passes with a note) when the file is absent.
 */
function lc_copy(): ?string
{
    $src = dirname(__DIR__, 2) . '/data/live-copy.db';
    if (!is_file($src)) {
        fwrite(STDERR, "note: data/live-copy.db not present, live-copy rehearsal skipped\n");
        return null;
    }
    $dir = ts_temp_dir();
    copy($src, $dir . '/live.db');
    if (is_file($src . '-wal') && filesize($src . '-wal') > 0) {
        copy($src . '-wal', $dir . '/live.db-wal');
    }
    return $dir;
}

function lc_migrator(Db $db, string $dir): Migrator
{
    return new Migrator($db, dirname(__DIR__, 2) . '/migrations', $dir . '/backups', $dir . '/migrate.lock', $dir . '/migrate-failed.json', new FixedClock(new DateTimeImmutable('2026-10-09 10:00:00')));
}

/** @return array<string,int> */
function lc_counts(Db $db): array
{
    $out = [];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'schema_migrations' ORDER BY name") as $r) {
        $out[(string) $r['name']] = (int) $db->scalar('SELECT COUNT(*) FROM "' . str_replace('"', '""', (string) $r['name']) . '"');
    }
    return $out;
}

/** Migrated temp copy, or null when the download is absent. @return array{0:Db,1:string,2:array<string,int>}|null */
function lc_migrated(): ?array
{
    $dir = lc_copy();
    if ($dir === null) {
        return null;
    }
    $db = Db::open($dir . '/live.db');
    $before = lc_counts($db);
    $res = lc_migrator($db, $dir)->run();
    t_true($res->ok, $res->error);
    return [$db, $dir, $before];
}

return [
    'live copy: existing rows unchanged, backup written, second run does nothing, checks clean' => function (): void {
        $dir = lc_copy();
        if ($dir === null) {
            return;
        }
        $db = Db::open($dir . '/live.db');
        $before = lc_counts($db);
        $fkBefore = count($db->query('PRAGMA foreign_key_check'));
        $m = lc_migrator($db, $dir);
        $res = $m->run();
        t_true($res->ok, $res->error);
        t_eq($m->latestVersion(), $m->currentVersion());
        $after = lc_counts($db);
        foreach ($before as $table => $n) {
            t_eq($n, $after[$table] ?? -1, "rows in $table");
        }
        t_true($res->backupPath !== null && is_file($res->backupPath), 'backup');
        t_eq($before, lc_counts(Db::open((string) $res->backupPath)), 'backup holds the pre-migration rows');
        t_eq('ok', (string) $db->scalar('PRAGMA integrity_check'));
        t_true(count($db->query('PRAGMA foreign_key_check')) <= $fkBefore, 'no new foreign key problems');
        $again = $m->run();
        t_true($again->ok);
        t_eq([], $again->applied, 'second run');
        t_eq($after, lc_counts($db), 'second run changed nothing');
    },
    'live copy: every job has a stage matching its status and exactly one brief' => function (): void {
        $m = lc_migrated();
        if ($m === null) {
            return;
        }
        [$db] = $m;
        $jobs = $db->query('SELECT j.id, j.status, j.stage, j.row_version, j.resume_stage, j.am_user_id, (SELECT COUNT(*) FROM briefs b WHERE b.job_id = j.id) AS briefs FROM jobs j');
        t_true(count($jobs) > 0, 'snapshot has jobs');
        foreach ($jobs as $j) {
            $want = Stage::fromLegacy((string) $j['status']);
            t_true($want !== null, 'known status ' . $j['status']);
            t_eq($want->value, $j['stage'], 'stage of ' . $j['id']);
            t_eq(1, (int) $j['briefs'], 'brief of ' . $j['id']);
            t_eq(1, (int) $j['row_version']);
            t_eq(null, $j['am_user_id'], 'no AM backfill (owner decision)');
            if (in_array($j['stage'], ['waiting', 'on_hold'], true)) {
                t_eq('in_progress', $j['resume_stage']);
            }
        }
        // drafts are 0.1.0 and unsent; everything past the brief stage is sent 1.0.0
        foreach ($db->query('SELECT j.stage, b.version_major, b.version_minor, b.sent_at, b.title, j.title AS job_title FROM briefs b JOIN jobs j ON j.id = b.job_id') as $b) {
            $draft = $b['stage'] === 'draft';
            t_eq($draft ? 0 : 1, (int) $b['version_major']);
            t_eq($draft ? 1 : 0, (int) $b['version_minor']);
            t_eq($draft, $b['sent_at'] === null);
            t_eq($b['job_title'], $b['title']);
        }
    },
    'live copy: assets with a template are linked to a deliverable line whose qty matches' => function (): void {
        $m = lc_migrated();
        if ($m === null) {
            return;
        }
        [$db] = $m;
        t_eq(0, (int) $db->scalar("SELECT COUNT(*) FROM assets WHERE template_id IS NOT NULL AND template_id <> '' AND brief_asset_id IS NULL"), 'unlinked assets');
        foreach ($db->query('SELECT x.id, x.qty, x.job_id, (SELECT COUNT(*) FROM assets a WHERE a.brief_asset_id = x.id) AS n, (SELECT COUNT(*) FROM assets a WHERE a.brief_asset_id = x.id AND a.job_id <> x.job_id) AS wrong FROM brief_assets x') as $l) {
            t_eq((int) $l['qty'], (int) $l['n'], 'qty of line ' . $l['id']);
            t_eq(0, (int) $l['wrong'], 'line links only its own job');
        }
        t_eq('Social Post (Static)', (string) $db->scalar("SELECT label FROM brief_assets WHERE template_id = 'social-static' LIMIT 1"));
    },
    'live copy: job counters continue after the highest number; client_signoff defaults to 1' => function (): void {
        $m = lc_migrated();
        if ($m === null) {
            return;
        }
        [$db] = $m;
        t_eq((int) $db->scalar('SELECT COUNT(*) FROM brands'), (int) $db->scalar('SELECT COUNT(*) FROM job_counters'));
        foreach ($db->query('SELECT b.prefix, c.next FROM brands b JOIN job_counters c ON c.prefix = b.prefix') as $c) {
            $max = 0;
            foreach ($db->query('SELECT job_number FROM jobs WHERE job_number LIKE :p', ['p' => $c['prefix'] . '-%']) as $j) {
                $max = max($max, (int) substr((string) $j['job_number'], strlen((string) $c['prefix']) + 1));
            }
            t_eq($max + 1, (int) $c['next'], 'counter ' . $c['prefix']);
        }
        t_eq(0, (int) $db->scalar('SELECT COUNT(*) FROM users WHERE client_signoff IS NOT 1'));
    },
    'live copy: a legacy status write moves the stage once, stores the resume stage and bumps row_version once' => function (): void {
        $m = lc_migrated();
        if ($m === null) {
            return;
        }
        [$db] = $m;
        $id = (string) $db->scalar("SELECT id FROM jobs WHERE status = 'In Progress' LIMIT 1");
        $row = static fn () => $db->one('SELECT status, stage, resume_stage, row_version, waiting_on FROM jobs WHERE id = :id', ['id' => $id]);
        // simulated legacy update_job: plain UPDATE of status (no stage, no row_version)
        $db->exec("UPDATE jobs SET status = 'Waiting' WHERE id = :id", ['id' => $id]);
        $r = $row();
        t_eq('waiting', $r['stage']);
        t_eq('in_progress', $r['resume_stage']);
        t_eq(2, (int) $r['row_version'], 'exactly one bump');
        // Waiting -> Today: back to in_progress, resume cleared
        $db->exec("UPDATE jobs SET status = 'Today' WHERE id = :id", ['id' => $id]);
        $r = $row();
        t_eq('in_progress', $r['stage']);
        t_eq(null, $r['resume_stage']);
        t_eq(3, (int) $r['row_version']);
        // Today -> This Week stays in_progress (stage untouched) but the row did change
        $changedAt = $db->scalar('SELECT stage_changed_at FROM jobs WHERE id = :id', ['id' => $id]);
        $db->exec("UPDATE jobs SET stage_changed_at = 'marker' WHERE id = :id", ['id' => $id]);
        $db->exec("UPDATE jobs SET status = 'This Week' WHERE id = :id", ['id' => $id]);
        $r = $row();
        t_eq('in_progress', $r['stage']);
        t_eq('marker', $db->scalar('SELECT stage_changed_at FROM jobs WHERE id = :id', ['id' => $id]), 'stage trigger did not fire');
        t_eq(4, (int) $r['row_version']);
        t_true($changedAt !== null);
        // a no-op write and an updated_at-only write do not bump
        $db->exec("UPDATE jobs SET status = 'This Week' WHERE id = :id", ['id' => $id]);
        $db->exec("UPDATE jobs SET updated_at = '2000-01-01' WHERE id = :id", ['id' => $id]);
        t_eq(4, (int) $row()['row_version']);
        // a new-app write that sets stage and status together and bumps itself: one bump, no trigger override
        $db->exec("UPDATE jobs SET stage = 'on_hold', status = 'On Hold', resume_stage = 'in_progress', row_version = row_version + 1 WHERE id = :id", ['id' => $id]);
        $r = $row();
        t_eq('on_hold', $r['stage']);
        t_eq(5, (int) $r['row_version']);
        t_eq('in_progress', $r['resume_stage']);
    },
    'live copy: legacy add_job and update_job statements still work after migrating' => function (): void {
        $m = lc_migrated();
        if ($m === null) {
            return;
        }
        [$db, $dir] = $m;
        $campaign = $db->one("SELECT c.id, b.prefix FROM campaigns c JOIN brands b ON b.id = c.brand_id WHERE b.prefix = 'MERC'");
        t_true($campaign !== null);
        $prefix = (string) $campaign['prefix'];
        // The legacy connection (api/db.php style: SQLite3, foreign keys on) runs api/api.php's exact SQL.
        $legacy = new SQLite3($dir . '/live.db');
        $legacy->enableExceptions(true);
        $legacy->busyTimeout(5000);
        $legacy->exec('PRAGMA foreign_keys = ON');
        $legacy->exec('BEGIN IMMEDIATE');
        $seed = $legacy->prepare("INSERT OR IGNORE INTO job_counters (prefix, next)
                SELECT :prefix, COALESCE(MAX(CAST(SUBSTR(job_number, LENGTH(:prefix) + 2) AS INTEGER)), 0) + 1
                FROM jobs WHERE job_number LIKE :like");
        $seed->bindValue(':prefix', $prefix, SQLITE3_TEXT);
        $seed->bindValue(':like', $prefix . '-%', SQLITE3_TEXT);
        $seed->execute();
        $cnt = $legacy->prepare('SELECT next FROM job_counters WHERE prefix = :prefix');
        $cnt->bindValue(':prefix', $prefix, SQLITE3_TEXT);
        $number = (int) $cnt->execute()->fetchArray(SQLITE3_ASSOC)['next'];
        $upd = $legacy->prepare('UPDATE job_counters SET next = next + 1 WHERE prefix = :prefix');
        $upd->bindValue(':prefix', $prefix, SQLITE3_TEXT);
        $upd->execute();
        $jobNumber = $prefix . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        t_eq('MERC-004', $jobNumber);
        $stmt = $legacy->prepare('INSERT INTO jobs (id, job_number, campaign_id, title, description, status, creative_direction, brief_date, delivery_date, hours_estimate, sort_order, created_by)
            VALUES (:id, :job_number, :campaign_id, :title, :description, :status, :creative_direction, :brief_date, :delivery_date, :hours_estimate, :sort_order, :created_by)');
        $vals = [':id' => 'legacyjob1', ':job_number' => $jobNumber, ':campaign_id' => $campaign['id'], ':title' => 'Legacy made', ':description' => null, ':status' => 'Inbox',
            ':creative_direction' => 'From legacy', ':brief_date' => '2026-10-01', ':delivery_date' => '2026-10-20', ':hours_estimate' => 4.5, ':sort_order' => 0, ':created_by' => 'p1'];
        foreach ($vals as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $t = $legacy->prepare('INSERT INTO tasks (id, job_id, type, status, assigned_to, sort_order) VALUES (:id, :job_id, :type, :status, :assigned_to, :sort_order)');
        $t->bindValue(':id', 'legacytask1');
        $t->bindValue(':job_id', 'legacyjob1');
        $t->bindValue(':type', 'copy');
        $t->bindValue(':status', 'Not Started');
        $t->bindValue(':assigned_to', null, SQLITE3_NULL);
        $t->bindValue(':sort_order', 0);
        $t->execute();
        $legacy->exec('COMMIT');

        $j = $db->one("SELECT stage, row_version FROM jobs WHERE id = 'legacyjob1'");
        t_eq('draft', $j['stage'], 'insert trigger set the stage');
        $b = $db->one("SELECT title, due_date, creative_direction, hours_estimate, version_major, version_minor, created_by FROM briefs WHERE job_id = 'legacyjob1'");
        t_true($b !== null, 'insert trigger made a brief');
        t_eq(['Legacy made', '2026-10-20', 'From legacy', 4.5, 0, 1, 'p1'], array_values($b));
        t_eq(5, (int) $db->scalar("SELECT next FROM job_counters WHERE prefix = 'MERC'"));

        // update_job: allowed fields plus the assignments delete/insert, in a deferred transaction
        $legacy->exec('BEGIN TRANSACTION');
        $u = $legacy->prepare('UPDATE jobs SET title = :title, status = :status, delivery_date = :delivery_date WHERE id = :id');
        $u->bindValue(':title', 'Legacy renamed');
        $u->bindValue(':status', 'In Progress');
        $u->bindValue(':delivery_date', '2026-10-22');
        $u->bindValue(':id', 'legacyjob1');
        $u->execute();
        $d = $legacy->prepare('DELETE FROM job_assignments WHERE job_id = :job_id AND role_on_job = :role');
        $d->bindValue(':job_id', 'legacyjob1');
        $d->bindValue(':role', 'Traffic');
        $d->execute();
        $i = $legacy->prepare('INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :job_id, :user_id, :role_on_job)');
        $i->bindValue(':id', 'legacyasg1');
        $i->bindValue(':job_id', 'legacyjob1');
        $i->bindValue(':user_id', 'p5');
        $i->bindValue(':role_on_job', 'Traffic');
        $i->execute();
        $legacy->exec('COMMIT');
        $j = $db->one("SELECT stage, row_version, title FROM jobs WHERE id = 'legacyjob1'");
        t_eq('in_progress', $j['stage']);
        t_eq(2, (int) $j['row_version']);
        // update_asset (legacy) still writes an asset row
        t_eq(1, $legacy->exec("UPDATE assets SET status = 'In Progress' WHERE id = 'a1'") ? $legacy->changes() : 0);
        // a legacy-style job delete still succeeds: briefs, lines and versions cascade
        $legacy->exec("DELETE FROM jobs WHERE id = 'legacyjob1'");
        t_eq(0, (int) $db->scalar("SELECT COUNT(*) FROM briefs WHERE job_id = 'legacyjob1'"));
        $legacy->close();
        t_eq('ok', (string) $db->scalar('PRAGMA integrity_check'));
    },
];
