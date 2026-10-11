<?php
declare(strict_types=1);

// Reviews fixtures: a brief sent through the real flow (sx_seed), the
// creative team put on it by Traffic, and helpers that hand in, review and
// take job steps through the full Kernel with the page's own signals.

use App\Domain\Role;
use App\Domain\Signals\ReviewSignals;
use App\Domain\Signals\SubmissionSignals;
use App\Http\Deps;
use App\Http\MemorySession;
use App\Http\Response;

require_once __DIR__ . '/social_fx.php';

/**
 * A sent brief with $qty Instagram statics, Traffic, CD, Copywriter and
 * Designer slots, plus an ECD, a second CD and a client contact of the brand.
 * @return array{0:Deps,1:array<string,string>,2:string} [deps, people, job id]
 */
function rx_world(int $qty = 2): array
{
    [$d, $c, $p] = bh_world();
    $p['ecd'] = ts_user($d, 'ecd_dominic', Role::ECD);
    $p['cd2'] = ts_user($d, 'cd_james', Role::CD);
    $p['producer'] = ts_user($d, 'prod_elena', Role::Producer);
    $p['client'] = $d->users->create('client_sophie', password_hash('x', PASSWORD_BCRYPT, ['cost' => 4]), 'Sophie Client', null, Role::Client, '#3b82f6', $c['brand'], null, $d->clock->now());
    $jobId = sx_seed($d, $c['campaign'], $p['am'], $p['traffic'], $qty, 'Grand Opening Posts');
    foreach (['CD' => 'cd', 'Copywriter' => 'copy', 'Designer' => 'designer'] as $role => $who) {
        $d->assignments->set($jobId, Role::from($role), $p[$who], $p['traffic'], $d->clock->now());
    }
    return [$d, $p, $jobId];
}

/** The posts of a job, in brief order. @return list<string> */
function rx_posts(Deps $d, string $jobId): array
{
    $out = [];
    foreach ($d->reviews->assets($jobId) as $a) {
        if ($a->assetStatus !== 'Cancelled') {
            $out[] = $a->assetId;
        }
    }
    return $out;
}

/**
 * Hand in as $s: the form's signals are the stored values with $fields over
 * them (copy_text, media_url, hashtags, link_url, note; 'rv' to force one).
 */
function rx_sub(Deps $d, MemorySession $s, string $jobId, string $assetId, array $fields, bool $request = false): Response
{
    $a = $d->reviews->asset($jobId, $assetId);
    if ($a === null) {
        throw new TestFailure('no post ' . $assetId);
    }
    $sig = ['copy_text' => $a->copyText, 'media_url' => $a->mediaUrl, 'hashtags' => $a->hashtags, 'link_url' => $a->linkUrl, 'note' => $a->note, 'rv' => $a->rowVersion];
    $path = '/jobs/' . $jobId . '/work/' . $assetId . ($request ? '/request' : '');
    return bh_ds($d, $s, 'POST', $path, [SubmissionSignals::root($assetId) => array_replace($sig, $fields)]);
}

/** A reviewer's decision as $s ($part: both | copy | media). */
function rx_rev(Deps $d, MemorySession $s, string $jobId, string $assetId, string $decision, string $part = 'both', string $feedback = '', ?int $rv = null): Response
{
    $a = $d->reviews->asset($jobId, $assetId);
    if ($a === null) {
        throw new TestFailure('no post ' . $assetId);
    }
    return bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/review/posts/' . $assetId, [ReviewSignals::root($assetId) => [
        'rv' => $rv ?? $a->rowVersion, 'part' => $part, 'decision' => $decision, 'feedback' => $feedback]]);
}

/** A job step as $s: approve | request-ecd | ecd-approve | reassign | ready | send. */
function rx_job(Deps $d, MemorySession $s, string $jobId, string $step, string $to = '', ?int $rv = null): Response
{
    return bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/review/' . $step, ['jr' => ['rv' => $rv ?? $d->reviews->jobReview($jobId)->rowVersion, 'to' => $to]]);
}

/** The body must carry an error toast containing $needle. */
function rx_err(Response $r, string $needle): void
{
    $body = ts_body($r);
    t_contains('data-toast="error"', $body, 'expected an error toast');
    t_contains($needle, $body);
}

/** data.recipients of the newest activity row with this verb on the job. @return list<string> */
function rx_to(Deps $d, string $jobId, string $verb): array
{
    $rows = $d->activity->listByVerb($jobId, $verb);
    if ($rows === []) {
        throw new TestFailure('no activity ' . $verb);
    }
    $r = $rows[count($rows) - 1]->data['recipients'] ?? [];
    return is_array($r) ? array_values($r) : [];
}

function rx_stage(Deps $d, string $jobId): string
{
    return sx_stage($d, $jobId);
}
