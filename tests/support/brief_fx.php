<?php
declare(strict_types=1);

// Builders for Domain brief tests (pure objects, no database).

use App\Domain\BriefVersion;
use App\Domain\Role;
use App\Domain\Stage;
use App\Domain\Types\Asset;
use App\Domain\Types\Assignment;
use App\Domain\Types\Brief;
use App\Domain\Types\BriefLine;
use App\Domain\Types\BriefReference;
use App\Domain\Types\Job;
use App\Domain\Types\JobAccess;
use App\Domain\Types\Team;
use App\Domain\Types\User;

require_once __DIR__ . '/app.php';

/** @param array<string,mixed> $o overrides by constructor name */
function bf_brief(array $o = []): Brief
{
    $d = [
        'id' => 'b1', 'jobId' => 'j1', 'title' => 'Grand Opening Social', 'campaignId' => 'c1', 'briefDate' => '2026-10-01', 'dueDate' => '2026-10-20',
        'firstGoLive' => null, 'lastGoLive' => null, 'creativeDirection' => 'Warm, celebratory, gold accents.', 'mandatories' => ['Logo lockup'],
        'references' => [new BriefReference('Moodboard', 'https://example.com/mood')], 'briefPdfUrl' => '', 'serverLink' => '',
        'budget' => 25000.0, 'hoursEstimate' => 30.0, 'version' => BriefVersion::draft(), 'hasUnsentChanges' => false, 'sentAt' => null,
        'sentBy' => null, 'createdBy' => 'am1', 'createdAt' => '2026-10-01 09:00:00', 'updatedBy' => null, 'updatedAt' => '2026-10-01 09:00:00', 'rowVersion' => 1,
    ];
    $v = array_merge($d, $o);
    return new Brief(...$v);
}

function bf_line(string $id, int $qty = 1, array $o = []): BriefLine
{
    $d = ['id' => $id, 'briefId' => 'b1', 'jobId' => 'j1', 'templateId' => 'social-static', 'label' => 'Social Post (Static)', 'qty' => $qty,
        'channel' => 'Instagram', 'sizeFormat' => '1080x1350', 'specs' => '', 'copyRequired' => false, 'dueDate' => null, 'sortOrder' => 0];
    return new BriefLine(...array_merge($d, $o));
}

/** @param array<string,string> $slots role value => user id */
function bf_team(array $slots): Team
{
    $a = [];
    foreach ($slots as $role => $uid) {
        $r = Role::from($role);
        $a[] = new Assignment($r, $uid, 'Name ' . $uid, $r);
    }
    return new Team($a);
}

function bf_asset(string $id, ?string $line, string $status = 'Inbox', int $sort = 0, ?string $assignee = null): Asset
{
    return new Asset($id, 'j1', $line, 'Asset ' . $id, 'image', 'social-static', $status, $assignee, null, $sort);
}

function bf_job(string $stage = 'draft', array $o = []): Job
{
    $d = ['id' => 'j1', 'jobNumber' => 'MERC-004', 'campaignId' => 'c1', 'title' => 'Grand Opening Social', 'status' => Stage::from($stage)->toLegacy(),
        'stage' => Stage::from($stage), 'stageChangedAt' => null, 'waitingOn' => null, 'waitingReason' => '', 'resumeStage' => null, 'rowVersion' => 3,
        'amUserId' => null, 'createdBy' => 'am1', 'updatedBy' => null, 'deliveryDate' => null, 'updatedAt' => '', 'campaignName' => 'Grand Opening London',
        'brandId' => 'brand1', 'brandName' => 'The Meridian Collection', 'brandPrefix' => 'MERC'];
    return new Job(...array_merge($d, $o));
}

function bf_user(Role $role, string $id = 'u1', ?string $brand = null): User
{
    return new User($id, $id, 'User ' . $id, null, $role, '#000', $brand, true);
}

/** @param list<Assignment> $assignments */
function bf_access(string $stage = 'draft', array $o = []): JobAccess
{
    $d = ['jobId' => 'j1', 'stage' => Stage::from($stage), 'creatorId' => null, 'brandId' => 'brand1', 'assignments' => [], 'assetAssigneeIds' => [],
        'briefSent' => $stage !== 'draft', 'hasUnsentChanges' => false, 'anyAssetStarted' => false];
    return new JobAccess(...array_merge($d, $o));
}
