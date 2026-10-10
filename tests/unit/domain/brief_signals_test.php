<?php
declare(strict_types=1);

use App\Domain\Signals\BriefSignals;
use App\Domain\Signals\CampaignSignals;
use App\Domain\Signals\LineSignals;
use App\Domain\Signals\NewBriefSignals;
use App\Domain\Signals\SendSignals;
use App\Domain\Signals\TransitionSignals;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

return [
    'brief signals: only present keys are patched; valid ones survive invalid neighbours' => function (): void {
        $s = BriefSignals::fromSignals(['brief' => [
            'title' => "  Grand   Opening \n", 'due_date' => '2026-02-30', 'budget' => '25 000', 'hours_estimate' => 7.5,
            'mandatories_text' => "Logo\r\n\n  T&Cs  \n", 'references_text' => "Moodboard | https://example.com/m\nhttps://x.example/y\nStyle guide: http://s.example",
            'creative_direction' => "Line one\r\nLine two\n\n", 'brief_pdf_url' => 'javascript:alert(1)', 'row_version' => '4',
        ]]);
        t_eq(['title', 'creative_direction', 'mandatories', 'references', 'budget', 'hours_estimate'], $s->patch->set);
        t_eq(['due_date', 'brief_pdf_url'], array_keys($s->errors->errors));
        t_eq('Grand Opening', $s->patch->title);
        t_eq(25000.0, $s->patch->budget);
        t_eq(7.5, $s->patch->hoursEstimate);
        t_eq(['Logo', 'T&Cs'], $s->patch->mandatories);
        t_eq("Line one\nLine two", $s->patch->creativeDirection);
        t_eq([['Moodboard', 'https://example.com/m'], ['https://x.example/y', 'https://x.example/y'], ['Style guide', 'http://s.example']],
            array_map(static fn ($r) => [$r->label, $r->url], $s->patch->references));
        t_eq(4, $s->rowVersion);
    },
    'brief signals: clearing and junk shapes' => function (): void {
        $s = BriefSignals::fromSignals(['brief' => ['due_date' => '', 'budget' => '', 'server_link' => '\\\\srv\\jobs\\MERC-004']]);
        t_eq(['due_date', 'server_link', 'budget'], $s->patch->set);
        t_eq(null, $s->patch->dueDate);
        t_eq(null, $s->patch->budget);
        $junk = BriefSignals::fromSignals(['brief' => ['title' => ['x'], 'budget' => '-5', 'hours_estimate' => '1e9', 'references_text' => 'no link here']]);
        t_eq(['title', 'references', 'budget', 'hours_estimate'], array_keys($junk->errors->errors));
        t_true(BriefSignals::fromSignals(['brief' => 'nope'])->patch->isEmpty());
        t_true(BriefSignals::fromSignals([])->patch->isEmpty());
    },
    'line signals: validation (table)' => function (): void {
        $ok = ['template_id' => 'social-static', 'label' => 'Hero post', 'qty' => '3', 'channel' => 'IG', 'size_format' => '1080x1350', 'specs' => "No text\nSafe zones", 'copy_required' => true, 'due_date' => '2026-10-10'];
        $r = LineSignals::fromSignals(['dl' => ['ln_abc' => $ok]], 'abc');
        t_true($r->errors->isEmpty(), $r->errors->first());
        t_eq([3, true, 'social-static'], [$r->input->qty, $r->input->copyRequired, $r->input->templateId]);
        $bad = [
            'qty' => ['qty' => 0], 'label' => ['label' => ' '], 'template_id' => ['template_id' => 'nope'], 'due_date' => ['due_date' => '10/10/2026'],
        ];
        foreach ($bad as $key => $override) {
            $x = LineSignals::fromSignals(['dl' => ['ln_abc' => array_merge($ok, $override)]], 'abc');
            t_eq(null, $x->input, $key);
            t_eq([$key], array_keys($x->errors->errors), $key);
        }
        t_eq(['line'], array_keys(LineSignals::fromSignals(['dl' => []], 'abc')->errors->errors));
        t_eq('ln_abc', LineSignals::signalKey('abc'));
    },
    'small signals: transition, campaign, new brief, send' => function (): void {
        $t = TransitionSignals::fromSignals(['tr' => ['action' => 'wait', 'waiting_on' => 'client', 'reason' => ' Images ']]);
        t_eq(['wait', 'client', 'Images'], [$t->action->value, $t->waitingOn->value, $t->reason]);
        t_eq(null, TransitionSignals::fromSignals(['tr' => ['action' => 'approve_internal']]), 'not a Phase 2 action');
        t_eq(null, TransitionSignals::fromSignals(['tr' => ['action' => 'send']]), 'send has its own route');
        t_eq(null, TransitionSignals::fromSignals([]));
        $c = CampaignSignals::fromSignals(['nc' => ['brand_id' => 'brand1', 'name' => ' Summer  Launch ', 'description' => '']]);
        t_true($c->validate()->isEmpty());
        t_eq('Summer Launch', $c->name);
        t_eq(['brand_id', 'name'], array_keys(CampaignSignals::fromSignals(['nc' => ['name' => 'x']])->validate()->errors));
        t_eq('New brief', NewBriefSignals::fromSignals(['nb' => ['campaign_id' => 'proj1']])->title);
        t_eq(['campaign_id'], array_keys(NewBriefSignals::fromSignals([])->validate()->errors));
        $s = SendSignals::fromSignals(['send' => ['bump' => 'minor', 'note' => ' More posts ']]);
        t_eq(['minor', 'More posts'], [$s->bump->value, $s->note]);
        t_eq(null, SendSignals::fromSignals(['send' => ['bump' => 'huge']])->bump);
    },
];
