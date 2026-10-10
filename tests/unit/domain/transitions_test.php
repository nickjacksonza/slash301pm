<?php
declare(strict_types=1);

use App\Domain\JobAction;
use App\Domain\Stage;
use App\Domain\Transitions;
use App\Domain\Types\TransitionRequest;
use App\Domain\WaitingOn;

require_once dirname(__DIR__, 2) . '/support/app.php';

function trt_plan(string $from, JobAction $a, ?WaitingOn $on = null, string $reason = '', ?string $resume = null, bool $started = false): array
{
    $o = Transitions::plan(Stage::from($from), $resume !== null ? Stage::from($resume) : null, new TransitionRequest($a, $on, $reason), $started);
    return [$o->to?->value, array_keys($o->errors->errors), $o->resumeStage?->value, $o->waitingOn?->value];
}

return [
    'transitions: send and recall' => function (): void {
        t_eq(['briefed', [], null, null], trt_plan('draft', JobAction::Send));
        t_eq([null, ['stage'], null, null], trt_plan('briefed', JobAction::Send));
        t_eq(['draft', [], null, null], trt_plan('briefed', JobAction::Recall));
        t_eq(['draft', ['assets'], null, null], trt_plan('briefed', JobAction::Recall, null, '', null, true), 'recall blocked once an asset started');
        foreach (['draft', 'in_progress', 'waiting', 'done'] as $s) {
            t_eq(null, trt_plan($s, JobAction::Recall)[0], "recall from $s");
        }
    },
    'transitions: wait and hold store the resume stage and need reasons' => function (): void {
        t_eq(['waiting', [], 'in_progress', 'client'], trt_plan('in_progress', JobAction::Wait, WaitingOn::Client, 'Awaiting hero image'));
        t_eq(['waiting', [], 'draft', 'am'], trt_plan('draft', JobAction::Wait, WaitingOn::Am, 'x'));
        t_eq(['waiting', ['waiting_on', 'reason'], 'briefed', null], trt_plan('briefed', JobAction::Wait));
        t_eq(['on_hold', [], 'in_review', null], trt_plan('in_review', JobAction::Hold, null, 'Budget review'));
        t_eq(['on_hold', ['reason'], 'in_progress', null], trt_plan('in_progress', JobAction::Hold, null, '   '));
        foreach (['waiting', 'on_hold', 'done', 'cancelled', 'archived'] as $s) {
            t_eq(null, trt_plan($s, JobAction::Wait, WaitingOn::Client, 'r')[0], "wait from $s");
            t_eq(null, trt_plan($s, JobAction::Hold, null, 'r')[0], "hold from $s");
        }
        t_eq(['waiting', ['reason'], 'in_progress', 'client'], trt_plan('in_progress', JobAction::Wait, WaitingOn::Client, str_repeat('x', 1001)));
    },
    'transitions: resume goes back to the stored stage' => function (): void {
        $cases = [
            ['waiting', 'in_progress', 'in_progress'], ['waiting', 'in_review', 'in_review'], ['on_hold', 'briefed', 'briefed'],
            ['on_hold', 'draft', 'draft'], ['waiting', null, 'in_progress'], ['on_hold', 'waiting', 'in_progress'], ['waiting', 'done', 'in_progress'],
        ];
        foreach ($cases as [$from, $resume, $want]) {
            t_eq($want, trt_plan($from, JobAction::Resume, null, '', $resume)[0], "$from resume $resume");
        }
        t_eq(null, trt_plan('in_progress', JobAction::Resume)[0]);
    },
    'transitions: cancel needs a reason from any open stage; archive only done or cancelled' => function (): void {
        foreach (Stage::cases() as $s) {
            $o = trt_plan($s->value, JobAction::Cancel, null, 'Client pulled it');
            t_eq($s->isOpen() ? 'cancelled' : null, $o[0], 'cancel from ' . $s->value);
            $a = trt_plan($s->value, JobAction::Archive);
            t_eq(in_array($s, [Stage::Done, Stage::Cancelled], true) ? 'archived' : null, $a[0], 'archive from ' . $s->value);
        }
        t_eq(['cancelled', ['reason'], null, null], trt_plan('briefed', JobAction::Cancel));
    },
    'transitions: social stages and done' => function (): void {
        t_eq('ready_to_schedule', trt_plan('approved_client', JobAction::ReadyToSchedule)[0]);
        t_eq('scheduled', trt_plan('ready_to_schedule', JobAction::Schedule)[0]);
        t_eq('live', trt_plan('scheduled', JobAction::GoLive)[0]);
        t_eq('done', trt_plan('live', JobAction::MarkDone)[0]);
        t_eq('done', trt_plan('approved_client', JobAction::MarkDone)[0], 'non-social jobs still finish from approved_client');
        t_eq(null, trt_plan('scheduled', JobAction::MarkDone)[0]);
        t_eq(null, trt_plan('approved_internal', JobAction::ReadyToSchedule)[0]);
    },
    'transitions: matrix row ids' => function (): void {
        $cases = [
            [JobAction::Send, 'draft', 'transition:draft->briefed'], [JobAction::Recall, 'briefed', 'transition:briefed->draft'],
            [JobAction::Wait, 'live', 'transition:workable->waiting'], [JobAction::Resume, 'waiting', 'transition:waiting->resume'],
            [JobAction::Resume, 'on_hold', 'transition:on_hold->resume'], [JobAction::Hold, 'draft', 'transition:workable->on_hold'],
            [JobAction::Cancel, 'on_hold', 'transition:open->cancelled'], [JobAction::Archive, 'done', 'transition:done->archived'],
            [JobAction::Archive, 'cancelled', 'transition:cancelled->archived'], [JobAction::MarkDone, 'live', 'transition:live->done'],
            [JobAction::MarkDone, 'approved_client', 'transition:approved_client->done'], [JobAction::GoLive, 'scheduled', 'transition:scheduled->live'],
            [JobAction::Archive, 'in_progress', null], [JobAction::Cancel, 'done', null], [JobAction::Resume, 'draft', null],
        ];
        foreach ($cases as [$a, $from, $want]) {
            t_eq($want, Transitions::matrixId($a, Stage::from($from)), $a->value . ' from ' . $from);
        }
    },
];
