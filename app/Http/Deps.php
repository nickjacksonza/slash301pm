<?php
declare(strict_types=1);

namespace App\Http;

use App\Clock\Clock;
use App\Config\Config;
use App\Store\ActivityStore;
use App\Store\AssetStore;
use App\Store\AssignmentStore;
use App\Store\BrandStore;
use App\Store\BriefAssetStore;
use App\Store\BriefStore;
use App\Store\CampaignStore;
use App\Store\Db;
use App\Store\JobFieldStore;
use App\Store\JobQueryStore;
use App\Store\JobStore;
use App\Store\LoginAttemptStore;
use App\Store\Migrator;
use App\Store\RateLimitStore;
use App\Store\SeedPasswordAudit;
use App\Store\SavedViewStore;
use App\Store\MyDayStore;
// Social publishing
use App\Store\PublicationStore;
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
        public readonly BrandStore $brands,
        public readonly CampaignStore $campaigns,
        public readonly JobStore $jobs,
        public readonly BriefStore $briefs,
        public readonly BriefAssetStore $briefAssets,
        public readonly AssignmentStore $assignments,
        public readonly AssetStore $assets,
        public readonly ActivityStore $activity,
        // Phase 4: my day
        public readonly MyDayStore $myDay,
        // Phase 3: jobs grid/board
        public readonly JobQueryStore $jobQuery,
        public readonly JobFieldStore $jobFields,
        public readonly SavedViewStore $savedViews,
        // Phase 5: hardening
        public readonly RateLimitStore $rateLimits,
        public readonly SeedPasswordAudit $seedPasswords,
        // Social publishing
        public readonly PublicationStore $publications,
    ) {}

    public static function build(Config $config, Clock $clock, string $driver = 'auto'): self
    {
        $db = Db::open($config->dbPath, $driver);
        $activity = new ActivityStore($db);
        $assets = new AssetStore($db);
        $briefs = new BriefStore($db, $activity, $assets);
        return new self(
            $config,
            $db,
            new UserStore($db, $activity),
            new LoginAttemptStore($db),
            new Migrator($db, $config->migrationsDir(), $config->backupsDir(), $config->migrateLockPath(), $config->migrateFailurePath(), $clock),
            $clock,
            new BrandStore($db),
            new CampaignStore($db, $activity),
            new JobStore($db, $activity),
            $briefs,
            new BriefAssetStore($db, $briefs),
            new AssignmentStore($db, $activity),
            $assets,
            $activity,
            // Phase 4: my day
            new MyDayStore($db),
            // Phase 3: jobs grid/board
            new JobQueryStore($db),
            new JobFieldStore($db, $activity),
            new SavedViewStore($db),
            // Phase 5: hardening
            new RateLimitStore($db),
            new SeedPasswordAudit($db, $config->dataDir . '/seed-password-check.json'),
            // Social publishing
            new PublicationStore($db, $activity, new JobStore($db, $activity)),
        );
    }
}
