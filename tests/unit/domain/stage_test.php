<?php
declare(strict_types=1);

use App\Domain\Stage;

require_once dirname(__DIR__, 2) . '/support/app.php';

return [
    'stage: every legacy status maps to a stage (ADR 0002 table)' => function (): void {
        $cases = [
            'Inbox' => 'draft', 'Brief' => 'draft', 'To Do' => 'briefed', 'In Progress' => 'in_progress', 'Today' => 'in_progress',
            'This Week' => 'in_progress', 'Waiting' => 'waiting', 'On Hold' => 'on_hold', 'In Review' => 'in_review',
            'Approved (Internal)' => 'approved_internal', 'Approved (External)' => 'approved_client', 'Done' => 'done',
            'Archived' => 'archived', 'Cancelled' => 'cancelled',
        ];
        foreach ($cases as $status => $stage) {
            $s = Stage::fromLegacy($status);
            t_true($s !== null, $status);
            t_eq($stage, $s->value, $status);
        }
        foreach (['Scheduled', 'Live', 'Backlog', '', 'inbox'] as $bad) {
            t_eq(null, Stage::fromLegacy($bad), $bad);
        }
    },
    'stage: canonical legacy status per stage, social stages mirror Approved (External)' => function (): void {
        $cases = [
            ['draft', 'Inbox'], ['briefed', 'To Do'], ['in_progress', 'In Progress'], ['waiting', 'Waiting'], ['on_hold', 'On Hold'],
            ['in_review', 'In Review'], ['approved_internal', 'Approved (Internal)'], ['approved_client', 'Approved (External)'],
            ['ready_to_schedule', 'Approved (External)'], ['scheduled', 'Approved (External)'], ['live', 'Approved (External)'],
            ['done', 'Done'], ['archived', 'Archived'], ['cancelled', 'Cancelled'],
        ];
        foreach ($cases as [$stage, $status]) {
            t_eq($status, Stage::from($stage)->toLegacy(), $stage);
            t_eq($status, Stage::from($stage)->toLegacy(null), $stage);
        }
        // every stage round trips to itself, except the social stages which read back as approved_client
        foreach (Stage::cases() as $s) {
            $back = Stage::fromLegacy($s->toLegacy());
            $want = in_array($s, [Stage::ReadyToSchedule, Stage::Scheduled, Stage::Live], true) ? Stage::ApprovedClient : $s;
            t_eq($want, $back, $s->value);
        }
    },
    'stage: a no-op write keeps Today, This Week and Brief' => function (): void {
        $cases = [
            ['in_progress', 'Today', 'Today'], ['in_progress', 'This Week', 'This Week'], ['in_progress', 'In Progress', 'In Progress'],
            ['draft', 'Brief', 'Brief'], ['draft', 'Inbox', 'Inbox'], ['in_progress', 'Waiting', 'In Progress'],
            ['waiting', 'Today', 'Waiting'], ['briefed', 'Inbox', 'To Do'], ['scheduled', 'Approved (External)', 'Approved (External)'],
            ['done', 'Approved (External)', 'Done'], ['draft', 'Nonsense', 'Inbox'],
        ];
        foreach ($cases as [$stage, $current, $want]) {
            t_eq($want, Stage::from($stage)->toLegacy($current), "$stage from $current");
        }
    },
    'stage: labels and sets' => function (): void {
        t_eq('Ready to schedule', Stage::ReadyToSchedule->label());
        t_eq('Scheduled', Stage::Scheduled->label());
        t_eq('Live', Stage::Live->label());
        t_eq('In progress', Stage::InProgress->label());
        $order = array_map(static fn (Stage $s): string => $s->value, Stage::cases());
        t_eq(['draft', 'briefed', 'in_progress', 'waiting', 'on_hold', 'in_review', 'approved_internal', 'approved_client',
            'ready_to_schedule', 'scheduled', 'live', 'done', 'archived', 'cancelled'], $order);
        $m = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/roles/policy-matrix.json'), true);
        foreach (['active' => Stage::active(), 'workable' => Stage::workable(), 'open' => Stage::open(), 'closed' => Stage::closed()] as $set => $stages) {
            t_eq($m['stage_sets'][$set], array_map(static fn (Stage $s): string => $s->value, $stages), "stage set $set matches the matrix");
        }
        foreach (Stage::cases() as $s) {
            t_true($s->isOpen() !== $s->isClosed(), 'open xor closed: ' . $s->value);
            t_eq(in_array($s, [Stage::Waiting, Stage::OnHold], true), $s->isPaused(), $s->value);
            t_eq($s->isWorkable() && $s !== Stage::Draft, $s->isActive(), $s->value);
        }
    },
];
