<?php
declare(strict_types=1);

use App\Domain\BoardMoves;
use App\Domain\GridField;
use App\Domain\JobAction;
use App\Domain\Policy;
use App\Domain\Role;
use App\Domain\SavedViews;
use App\Domain\Stage;
use App\Domain\Types\SavedView;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

return [
    'board: a drop maps to the one named move landing there (table)' => function (): void {
        $cases = [
            ['in_progress', 'waiting', null, JobAction::Wait],
            ['draft', 'waiting', null, JobAction::Wait],
            ['briefed', 'on_hold', null, JobAction::Hold],
            ['waiting', 'in_review', 'in_review', JobAction::Resume],
            ['waiting', 'in_progress', null, JobAction::Resume],
            ['on_hold', 'briefed', 'briefed', JobAction::Resume],
            ['in_review', 'cancelled', null, JobAction::Cancel],
            ['waiting', 'cancelled', 'in_progress', JobAction::Cancel],
            ['done', 'archived', null, JobAction::Archive],
            ['cancelled', 'archived', null, JobAction::Archive],
            ['approved_client', 'done', null, JobAction::MarkDone],
            ['draft', 'briefed', null, JobAction::Send],
            ['briefed', 'draft', null, JobAction::Recall],
            ['briefed', 'in_progress', null, JobAction::Start],
            ['in_review', 'in_progress', null, JobAction::SendBack],
            ['waiting', 'briefed', 'in_review', null],
            ['in_progress', 'done', null, null],
            ['draft', 'archived', null, null],
            ['in_progress', 'in_progress', null, null],
            ['waiting', 'on_hold', 'in_progress', null],
        ];
        foreach ($cases as [$from, $to, $resume, $want]) {
            $got = BoardMoves::actionFor(Stage::from($from), Stage::from($to), $resume !== null ? Stage::from($resume) : null);
            t_eq($want, $got, "$from -> $to");
        }
        t_contains('resumes to in review', BoardMoves::refusal(Stage::Waiting, Stage::Briefed, Stage::InReview));
        t_contains('cannot move from in progress to done', BoardMoves::refusal(Stage::InProgress, Stage::Done, null));
    },
    'board: "Move to" lists only what Policy and the stage machine allow (table)' => function (): void {
        $am = bf_user(Role::AM, 'am1');
        $coo = bf_user(Role::COO, 'coo1');
        $traffic = bf_user(Role::Traffic, 't1');
        $copy = bf_user(Role::Copywriter, 'cw1');
        $owned = static fn (string $stage, array $o = []) => bf_access($stage, $o + ['creatorId' => 'am1']);
        $targets = static function ($u, $a, ?Stage $resume = null): array {
            $out = [];
            foreach (BoardMoves::targets($u, $a, $resume) as $t) {
                $out[] = $t->action->value . '>' . $t->to->value;
            }
            return $out;
        };
        $cases = [
            'AM, own draft' => [$am, $owned('draft'), null, ['send>briefed', 'wait>waiting', 'hold>on_hold', 'cancel>cancelled']],
            'AM, own briefed' => [$am, $owned('briefed'), null, ['recall>draft', 'wait>waiting', 'hold>on_hold', 'cancel>cancelled']],
            'AM, own briefed, asset started' => [$am, $owned('briefed', ['anyAssetStarted' => true]), null, ['wait>waiting', 'hold>on_hold', 'cancel>cancelled']],
            'AM, own waiting' => [$am, $owned('waiting'), Stage::InReview, ['resume>in_review', 'cancel>cancelled']],
            'AM, own done' => [$am, $owned('done'), null, ['archive>archived']],
            'AM, someone else\'s job' => [$am, bf_access('in_progress', ['creatorId' => 'am2']), null, []],
            'COO, any in_progress' => [$coo, bf_access('in_progress'), null, ['wait>waiting', 'hold>on_hold', 'cancel>cancelled']],
            'Traffic, draft' => [$traffic, bf_access('draft'), null, []],
            'Traffic, briefed' => [$traffic, bf_access('briefed'), null, ['wait>waiting', 'hold>on_hold']],
            'Copywriter, assigned' => [$copy, bf_access('in_progress', ['assetAssigneeIds' => ['cw1']]), null, []],
        ];
        foreach ($cases as $name => [$u, $a, $resume, $want]) {
            t_eq($want, $targets($u, $a, $resume), $name);
        }
        $send = BoardMoves::targets($am, $owned('draft'), null)[0];
        t_true($send->viaBrief && !$send->needsReason, 'send goes through the brief');
        $wait = BoardMoves::targets($am, $owned('briefed'), null)[1];
        t_true($wait->needsReason && $wait->needsWaitingOn, 'waiting asks for who and why');
    },
    'grid fields: Policy per field, stage and relation (table)' => function (): void {
        $am = bf_user(Role::AM, 'am1');
        $traffic = bf_user(Role::Traffic, 't1');
        $cases = [
            ['AM own title', $am, bf_access('in_progress', ['creatorId' => 'am1']), GridField::Title, true],
            ['AM other title', $am, bf_access('in_progress', ['creatorId' => 'x']), GridField::Title, false],
            ['AM own title, done', $am, bf_access('done', ['creatorId' => 'am1']), GridField::Title, false],
            ['AM own waiting reason, waiting', $am, bf_access('waiting', ['creatorId' => 'am1']), GridField::WaitingReason, true],
            ['AM own waiting reason, not waiting', $am, bf_access('in_progress', ['creatorId' => 'am1']), GridField::WaitingReason, false],
            ['Traffic waiting_on', $traffic, bf_access('waiting'), GridField::WaitingOn, true],
            ['Traffic due date', $traffic, bf_access('briefed'), GridField::DueDate, false],
            ['Traffic slot', $traffic, bf_access('briefed'), GridField::Traffic, true],
            ['Traffic AM slot', $traffic, bf_access('briefed'), GridField::Am, false],
            ['AM own AM slot', $am, bf_access('briefed', ['creatorId' => 'am1']), GridField::Am, true],
        ];
        foreach ($cases as [$name, $u, $a, $f, $want]) {
            t_eq($want, Policy::canEditJobField($u, $a, $f)->allowed, $name);
        }
    },
    'saved views: sharing and managing rules, built-ins (table)' => function (): void {
        $mine = new SavedView('v1', 'am1', 'Amy', 'jobs', 'Mine', '{}', false, false, 0);
        $shared = new SavedView('v2', 'am2', 'Ben', 'jobs', 'Team', '{}', true, false, 0);
        $cases = [
            ['AM shares', Policy::canShareView(bf_user(Role::AM, 'am1')), true],
            ['Traffic shares', Policy::canShareView(bf_user(Role::Traffic)), true],
            ['Designer shares', Policy::canShareView(bf_user(Role::Designer)), false],
            ['Client shares', Policy::canShareView(bf_user(Role::Client)), false],
            ['owner manages', Policy::canManageView(bf_user(Role::AM, 'am1'), $mine), true],
            ['other AM, private', Policy::canManageView(bf_user(Role::AM, 'am2'), $mine), false],
            ['other AM, shared', Policy::canManageView(bf_user(Role::AM, 'am1'), $shared), false],
            ['ECD, shared', Policy::canManageView(bf_user(Role::ECD, 'e1'), $shared), true],
            ['ECD, private', Policy::canManageView(bf_user(Role::ECD, 'e1'), $mine), false],
        ];
        foreach ($cases as [$name, $d, $want]) {
            t_eq($want, $d->allowed, $name);
        }
        t_eq(['builtin:mine', 'builtin:unowned', 'builtin:due_week', 'builtin:all_open'], array_map(static fn (SavedView $v): string => $v->id, SavedViews::builtins('jobs')));
        t_eq('builtin:mine', SavedViews::fallbackId(bf_user(Role::PM)));
        t_eq('builtin:all_open', SavedViews::fallbackId(bf_user(Role::COO)));
        t_eq(['Q3 launches', ''], SavedViews::cleanName("  Q3 \t launches "));
        t_eq('', SavedViews::cleanName('   ')[0]);
        t_eq('', SavedViews::cleanName(str_repeat('x', 61))[0]);
    },
];
