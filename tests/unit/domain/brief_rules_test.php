<?php
declare(strict_types=1);

use App\Domain\BriefRules;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

return [
    'brief rules: a complete brief with Traffic is ready' => function (): void {
        t_true(BriefRules::readyToSend(bf_brief(), [bf_line('l1', 3)], bf_team(['Traffic' => 't1']))->isEmpty());
    },
    'brief rules: each missing item is reported (table)' => function (): void {
        $team = bf_team(['Traffic' => 't1']);
        $lines = [bf_line('l1')];
        $cases = [
            'title' => [bf_brief(['title' => '  ']), $lines, $team],
            'campaign' => [bf_brief(['campaignId' => null]), $lines, $team],
            'due_date' => [bf_brief(['dueDate' => null]), $lines, $team],
            'creative_direction' => [bf_brief(['creativeDirection' => "\n "]), $lines, $team],
            'deliverables' => [bf_brief(), [], $team],
            'traffic' => [bf_brief(), $lines, bf_team(['CD' => 'cd1', 'Designer' => 'd1'])],
        ];
        foreach ($cases as $key => [$b, $l, $t]) {
            t_eq([$key], array_keys(BriefRules::readyToSend($b, $l, $t)->errors), $key);
        }
        $early = BriefRules::readyToSend(bf_brief(['briefDate' => '2026-10-21', 'dueDate' => '2026-10-20']), $lines, $team);
        t_eq(['due_date' => 'The due date must be on or after the brief date.'], $early->errors);
        t_true(BriefRules::readyToSend(bf_brief(['briefDate' => '2026-10-20', 'dueDate' => '2026-10-20']), $lines, $team)->isEmpty(), 'same day is fine');
        t_true(BriefRules::readyToSend(bf_brief(['briefDate' => null]), $lines, $team)->isEmpty(), 'brief date optional');
        t_eq(['traffic' => 'Traffic is required. Assign a Traffic person.'], BriefRules::readyToSend(bf_brief(), $lines, bf_team([]))->errors);
        $all = BriefRules::readyToSend(bf_brief(['title' => '', 'campaignId' => null, 'dueDate' => null, 'creativeDirection' => '']), [], bf_team([]));
        t_eq(['title', 'campaign', 'due_date', 'deliverables', 'creative_direction', 'traffic'], array_keys($all->errors));
    },
    'brief rules: checklist carries labels and states' => function (): void {
        $items = BriefRules::checklist(bf_brief(), [], bf_team([]));
        $states = [];
        foreach ($items as $i) {
            $states[$i->key] = $i->ok;
        }
        t_eq(['title' => true, 'campaign' => true, 'due_date' => true, 'deliverables' => false, 'creative_direction' => true, 'traffic' => false], $states);
    },
];
