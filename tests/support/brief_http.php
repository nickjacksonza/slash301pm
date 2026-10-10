<?php
declare(strict_types=1);

// Helpers for brief HTTP tests: sessions, Datastar calls through the Kernel, a seeded draft.

use App\Http\Deps;
use App\Http\Handlers\AuthHandlers;
use App\Http\MemorySession;
use App\Http\Response;

require_once __DIR__ . '/brief_db.php';

function bh_session(Deps $d, string $userId): MemorySession
{
    $s = new MemorySession(['csrf_token' => 'x']);
    AuthHandlers::startLogin($s, $d->users->findById($userId), $d->clock->now()->getTimestamp());
    return $s;
}

/** Run a Datastar request through the full Kernel. */
function bh_ds(Deps $d, MemorySession $s, string $method, string $path, array $signals = []): Response
{
    return (ts_app($s))(ts_ds_request($method, $path, $s, $signals), $d);
}

/** A seeded world: campaign, people, an AM-owned draft with a line and Traffic. @return array{0:Deps,1:array,2:array,3:string} */
function bh_world(): array
{
    $d = ts_deps();
    $c = bd_seed_campaign($d);
    $p = bd_seed_people($d);
    $s = bh_session($d, $p['am']);
    $resp = bh_ds($d, $s, 'POST', '/briefs', ['nb' => ['campaign_id' => $c['campaign'], 'title' => 'Launch']]);
    $body = ts_body($resp);
    t_true(preg_match('#/slash301pm/jobs/([0-9a-f]{32})/brief#', $body, $m) === 1, 'create redirects to the editor: ' . $body);
    return [$d, $c, $p, $m[1]];
}

