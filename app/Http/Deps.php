<?php
declare(strict_types=1);

namespace App\Http;

use App\Clock\Clock;
use App\Config\Config;
use App\Store\Db;
use App\Store\LoginAttemptStore;
use App\Store\Migrator;
use App\Store\UserStore;

/** Everything a handler may use, passed explicitly. Go: type Deps struct. */
final class Deps
{
    public function __construct(
        public readonly Config $config,
        public readonly Db $db,
        public readonly UserStore $users,
        public readonly LoginAttemptStore $loginAttempts,
        public readonly Migrator $migrator,
        public readonly Clock $clock,
    ) {}

    public static function build(Config $config, Clock $clock, string $driver = 'auto'): self
    {
        $db = Db::open($config->dbPath, $driver);
        return new self(
            $config,
            $db,
            new UserStore($db),
            new LoginAttemptStore($db),
            new Migrator($db, $config->migrationsDir(), $config->backupsDir(), $config->migrateLockPath(), $config->migrateFailurePath(), $clock),
            $clock,
        );
    }
}
