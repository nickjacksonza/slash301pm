<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\AssetTemplates;
use App\Domain\Signals\LineSignals;
use App\Domain\Signals\SignalInput;
use App\Domain\Types\BriefLineInput;
use App\Domain\Types\User;
use App\Http\BriefState;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;

/**
 * Deliverable lines: POST /jobs/{id}/brief/assets (add from new_line.template_id),
 * PATCH /jobs/{id}/brief/assets/{aid} (dl.ln_<aid>.*), DELETE .../{aid},
 * POST /jobs/{id}/brief/assets/order (reorder.line_id + reorder.dir).
 * Each answers with #brief-deliverables and #brief-rail.
 */
final class BriefLineHandlers
{
    public static function add(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $s = BriefHandlers::writable($r, $d);
        if ($s instanceof Response) {
            return $s;
        }
        $tplId = trim(SignalInput::str(SignalInput::obj($r->signals(), 'new_line'), 'template_id'));
        $tpl = AssetTemplates::find($tplId === '' ? null : $tplId);
        if ($tplId !== '' && $tpl === null) {
            return Response::events(Toast::error('Unknown template.'));
        }
        if (count($s->lines) >= 100) {
            return Response::events(Toast::error('A brief can have up to 100 deliverable lines.'));
        }
        $d->briefAssets->add($s->brief, BriefLineInput::fromTemplate($tpl), $s->baseline, $u->id, $d->clock->now());
        return self::answer($d, $s->job->id, $u, '');
    }

    public static function update(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $s = BriefHandlers::writable($r, $d);
        if ($s instanceof Response) {
            return $s;
        }
        $lineId = $r->pathValue('aid');
        $in = LineSignals::fromSignals($r->signals(), $lineId);
        if ($in->input === null) {
            return Response::events(Toast::error($in->errors->first()));
        }
        if (!$d->briefAssets->update($s->brief, $lineId, $in->input, $s->baseline, $u->id, $d->clock->now())) {
            return Response::events(Toast::error('That deliverable is not on this brief any more.'));
        }
        return self::answer($d, $s->job->id, $u, '');
    }

    public static function remove(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $s = BriefHandlers::writable($r, $d);
        if ($s instanceof Response) {
            return $s;
        }
        if (!$d->briefAssets->remove($s->brief, $r->pathValue('aid'), $s->baseline, $u->id, $d->clock->now())) {
            return Response::events(Toast::error('That deliverable is not on this brief any more.'));
        }
        return self::answer($d, $s->job->id, $u, $s->brief->isSent() ? 'Removed. Its unstarted assets are cancelled when you send the update.' : 'Removed.');
    }

    public static function reorder(Request $r, Deps $d): Response
    {
        $u = BriefHandlers::user($r);
        $s = BriefHandlers::writable($r, $d);
        if ($s instanceof Response) {
            return $s;
        }
        $sig = SignalInput::obj($r->signals(), 'reorder');
        $lineId = SignalInput::str($sig, 'line_id');
        $dir = SignalInput::str($sig, 'dir');
        $ids = [];
        foreach ($s->lines as $l) {
            $ids[] = $l->id;
        }
        $i = array_search($lineId, $ids, true);
        if ($i === false || ($dir !== 'up' && $dir !== 'down')) {
            return Response::events(Toast::error('Nothing to move.'));
        }
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j < 0 || $j >= count($ids)) {
            return self::answer($d, $s->job->id, $u, '');
        }
        $tmp = $ids[$i];
        $ids[$i] = $ids[$j];
        $ids[$j] = $tmp;
        $d->briefAssets->reorder($s->brief, $ids, $s->baseline, $u->id, $d->clock->now());
        return self::answer($d, $s->job->id, $u, '');
    }

    private static function answer(Deps $d, string $jobId, User $u, string $toast): Response
    {
        $s = BriefHandlers::reconcile($d, $jobId);
        $events = [
            PatchElements::html(partial_brief_deliverables($s->job->id, $s->lines, BriefView::templateOptions(), true)),
            PatchElements::html(partial_brief_rail(BriefView::rail($s, $u, $d))),
        ];
        if ($toast !== '') {
            $events[] = Toast::ok($toast);
        }
        return Response::events(...$events);
    }
}
