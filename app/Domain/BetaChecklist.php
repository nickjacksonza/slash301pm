<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\BetaGateItem;
use App\Domain\Types\SeedPasswordReport;

/**
 * The beta gate items the app can compute (docs/beta-gate.md). The rest
 * (predeploy check, legacy smoke test) are run outside the app. Pure: the
 * caller gathers the facts.
 */
final class BetaChecklist
{
    public const DEMO_OFF = 'demo_off';
    public const SEED_PASSWORDS = 'seed_passwords';
    public const AM_USER = 'am_user';
    public const MIGRATIONS = 'migrations';
    public const BACKUP = 'backup';
    public const DATASTAR_PIN = 'datastar_pin';

    /** @return list<BetaGateItem> in the order of docs/beta-gate.md */
    public static function items(
        bool $demoMode,
        SeedPasswordReport $seed,
        int $activeAmUsers,
        bool $migrationFailed,
        int $pendingMigrations,
        int $modifiedMigrations,
        int $backups,
        bool $datastarPinned,
    ): array {
        $seedMet = $seed->usernames === [] && $seed->unchecked === 0;
        $seedDetail = '';
        if ($seed->usernames !== []) {
            $names = array_slice($seed->usernames, 0, 12);
            $more = count($seed->usernames) - count($names);
            $seedDetail = count($seed->usernames) . ' active user(s) still sign in with the seeded password: ' . implode(', ', $names) . ($more > 0 ? ' and ' . $more . ' more' : '')
                . '. Reset each one at /admin/users.';
        }
        if ($seed->unchecked > 0) {
            $seedDetail .= ($seedDetail !== '' ? ' ' : '') . $seed->unchecked . ' user(s) not checked yet; reload this page to continue the check.';
        }
        $migrationDetail = '';
        if ($migrationFailed) {
            $migrationDetail = 'A migration failed (data/migrate-failed.json). See the schema section below.';
        } elseif ($pendingMigrations > 0) {
            $migrationDetail = $pendingMigrations . ' migration(s) pending. Open /healthz or apply them below.';
        } elseif ($modifiedMigrations > 0) {
            $migrationDetail = $modifiedMigrations . ' applied migration file(s) changed after they ran.';
        }
        return [
            new BetaGateItem(self::DEMO_OFF, 'Demo mode off', !$demoMode,
                $demoMode ? 'data/.demo_mode exists: anyone can sign in as anyone. Delete it on the server by SFTP.' : '', true),
            new BetaGateItem(self::SEED_PASSWORDS, 'Seeded passwords replaced', $seedMet, $seedDetail, true),
            new BetaGateItem(self::AM_USER, 'An active Account Manager user exists', $activeAmUsers > 0,
                $activeAmUsers > 0 ? '' : 'Create the AM user(s) at /admin/users.', true),
            new BetaGateItem(self::MIGRATIONS, 'Migrations applied, none failed', !$migrationFailed && $pendingMigrations === 0 && $modifiedMigrations === 0, $migrationDetail, false),
            new BetaGateItem(self::BACKUP, 'A backup exists in data/backups', $backups > 0,
                $backups > 0 ? '' : 'No backup yet. One is written before every migration run.', false),
            new BetaGateItem(self::DATASTAR_PIN, 'datastar.js matches its pinned sha256', $datastarPinned,
                $datastarPinned ? '' : 'public/js/datastar.js does not match public/js/datastar.js.sha256. Re-upload both from the release.', false),
        ];
    }

    /** @param list<BetaGateItem> $items @return list<BetaGateItem> */
    public static function unmet(array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            if (!$i->met) {
                $out[] = $i;
            }
        }
        return $out;
    }
}
