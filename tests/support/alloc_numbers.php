<?php
declare(strict_types=1);

// Child process for the concurrent job number test: php alloc_numbers.php <db> <campaign id> <user id> <count>

use App\Clock\SystemClock;
use App\Store\ActivityStore;
use App\Store\CampaignStore;
use App\Store\Db;
use App\Store\JobStore;
use App\Store\UserStore;

require_once dirname(__DIR__, 2) . '/app/autoload.php';
date_default_timezone_set('Africa/Johannesburg');

[$_, $path, $campaignId, $userId, $count] = $argv;
$db = Db::open($path);
$activity = new ActivityStore($db);
$jobs = new JobStore($db, $activity);
$campaign = (new CampaignStore($db, $activity))->get($campaignId);
$user = (new UserStore($db, $activity))->findById($userId);
if ($campaign === null || $user === null) {
    fwrite(STDERR, "missing fixtures\n");
    exit(2);
}
$clock = new SystemClock();
for ($i = 0; $i < (int) $count; $i++) {
    $jobs->createDraft($campaign, 'Concurrent ' . $i, $user, $clock->now());
}
echo "ok\n";
