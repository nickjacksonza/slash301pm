<?php
declare(strict_types=1);

use App\Domain\BriefDiff;
use App\Domain\Types\BriefSnapshot;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

function bdt_snap(array $brief = [], ?array $lines = null, array $team = ['Traffic' => 't1']): BriefSnapshot
{
    return BriefSnapshot::of(bf_brief($brief), $lines ?? [bf_line('l1', 3), bf_line('l2', 1, ['templateId' => 'email-copy', 'label' => 'Email Copy', 'sortOrder' => 1])], bf_team($team), 'Grand Opening London', 'The Meridian Collection');
}

return [
    'diff: identical snapshots are empty and suggest patch' => function (): void {
        $d = BriefDiff::between(bdt_snap(), bdt_snap());
        t_true($d->isEmpty());
        t_eq('patch', BriefDiff::suggestBump($d, 2)->level->value);
    },
    'diff: field changes are listed with labels and display values' => function (): void {
        $d = BriefDiff::between(bdt_snap(), bdt_snap(['title' => 'New title', 'budget' => 30000.5, 'mandatories' => ['Logo lockup', 'T&Cs']]));
        $f = [];
        foreach ($d->fields as $c) {
            $f[$c->field] = [$c->label, $c->before, $c->after];
        }
        t_eq(['title' => ['Title', 'Grand Opening Social', 'New title'], 'mandatories' => ['Mandatories', 'Logo lockup', "Logo lockup\nT&Cs"], 'budget' => ['Budget', '25000', '30000.5']], $f);
        t_true($d->contentIsEmpty() === false);
    },
    'diff: deliverables added, removed and changed' => function (): void {
        $old = bdt_snap();
        $new = bdt_snap([], [bf_line('l1', 5, ['channel' => 'IG + FB']), bf_line('l3', 2, ['label' => 'Story/Reel', 'templateId' => 'social-story'])]);
        $d = BriefDiff::between($old, $new);
        $kinds = [];
        foreach ($d->lines as $l) {
            $kinds[$l->lineId] = $l->kind;
        }
        t_eq(['l1' => 'changed', 'l3' => 'added', 'l2' => 'removed'], $kinds);
        $l1 = $d->lines[0];
        t_eq(['qty', 'channel'], array_map(static fn ($c) => $c->field, $l1->changes));
        t_eq(['3', '5'], [$l1->changes[0]->before, $l1->changes[0]->after]);
        t_eq('2x Story/Reel · 1080x1350 · Instagram', $d->lines[1]->summary);
    },
    'diff: team changes count separately from content' => function (): void {
        $d = BriefDiff::between(bdt_snap(), bdt_snap([], null, ['Traffic' => 't2', 'CD' => 'cd1']));
        t_true($d->contentIsEmpty());
        t_true(!$d->isEmpty());
        $t = [];
        foreach ($d->team as $c) {
            $t[$c->field] = [$c->before, $c->after];
        }
        t_eq(['Traffic' => ['Name t1', 'Name t2'], 'CD' => ['', 'Name cd1']], $t);
        t_eq('minor', BriefDiff::suggestBump($d, 2)->level->value);
    },
    'diff: suggested bump (table)' => function (): void {
        $base = bdt_snap();
        $cases = [
            'campaign change is major' => [bdt_snap(['campaignId' => 'c2']), 'major'],
            'half the lines removed is major' => [bdt_snap([], [bf_line('l1', 3)]), 'major'],
            'creative direction rewritten is major' => [bdt_snap(['creativeDirection' => 'Moody, nocturnal, silver tones only.']), 'major'],
            'qty change is minor' => [bdt_snap([], [bf_line('l1', 4), bf_line('l2', 1, ['templateId' => 'email-copy', 'label' => 'Email Copy', 'sortOrder' => 1])]), 'minor'],
            'added line is minor' => [bdt_snap([], [bf_line('l1', 3), bf_line('l2', 1, ['templateId' => 'email-copy', 'label' => 'Email Copy', 'sortOrder' => 1]), bf_line('l9')]), 'minor'],
            'due date is minor' => [bdt_snap(['dueDate' => '2026-10-25']), 'minor'],
            'budget is minor' => [bdt_snap(['budget' => 1.0]), 'minor'],
            'hours is minor' => [bdt_snap(['hoursEstimate' => 31.0]), 'minor'],
            'go-live is minor' => [bdt_snap(['firstGoLive' => '2026-11-01']), 'minor'],
            'small wording fix is patch' => [bdt_snap(['creativeDirection' => 'Warm, celebratory, with gold accents.']), 'patch'],
            'title is patch' => [bdt_snap(['title' => 'Grand Opening Social (IG)']), 'patch'],
            'references are patch' => [bdt_snap(['references' => []]), 'patch'],
            'pdf link is patch' => [bdt_snap(['briefPdfUrl' => 'https://example.com/brief.pdf']), 'patch'],
        ];
        foreach ($cases as $name => [$new, $want]) {
            t_eq($want, BriefDiff::suggestBump(BriefDiff::between($base, $new), 2)->level->value, $name);
        }
    },
    'diff and snapshot survive a JSON round trip' => function (): void {
        $old = bdt_snap();
        $new = bdt_snap(['title' => 'X', 'references' => []], [bf_line('l1', 5)]);
        $d = BriefDiff::between($old, $new);
        $back = BriefDiff::fromArray(json_decode((string) json_encode($d->toArray()), true));
        t_eq($d->toArray(), $back->toArray());
        $snap = BriefSnapshot::fromArray(json_decode((string) json_encode($old->toArray()), true), 'b1', 'j1');
        t_true(BriefDiff::between($old, $snap)->isEmpty(), 'snapshot round trip');
        t_eq(0.75, BriefDiff::wordOverlap('a b c d', 'a b c x'));
    },
];
