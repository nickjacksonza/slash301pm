<?php
declare(strict_types=1);

use App\Config\Transport;

require_once dirname(__DIR__, 2) . '/support/brief_http.php';

/**
 * One hostile string in every user-controlled field that reaches the brief
 * pages (brand, campaign, people, title, creative direction, mandatories,
 * references, deliverable fields, change note, waiting reason). No page or
 * Datastar answer may contain it raw, and no extra <script tag may appear.
 */
const BRIEF_HOSTILE = '<script>alert("x")</script>\'"&{{x}}</textarea>';

function bhs_check(string $where, string $html, int $allowedScripts): void
{
    t_not_contains(BRIEF_HOSTILE, $html, "$where: raw hostile string");
    t_not_contains('<script>alert', $html, "$where: script injection");
    t_not_contains('</textarea>\'"', $html, "$where: textarea breakout");
    t_eq($allowedScripts, substr_count(strtolower($html), '<script'), "$where: script tags");
}

return [
    'hostile strings are escaped on every brief page and patch' => function (): void {
        $h = BRIEF_HOSTILE;
        [$d, $c, $p, $jobId] = bh_world();
        $d->db->exec('UPDATE brands SET name = :n', ['n' => 'Brand ' . $h]);
        $d->db->exec('UPDATE campaigns SET name = :n, description = :n', ['n' => 'Camp ' . $h]);
        $d->db->exec('UPDATE users SET name = :n', ['n' => 'Person ' . $h]);
        $s = bh_session($d, $p['am']);
        $rv = $d->briefs->getByJob($jobId)->rowVersion;
        bh_ds($d, $s, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => [
            'title' => 'Title ' . $h, 'due_date' => '2026-10-20', 'creative_direction' => "CD $h\nline two", 'mandatories_text' => "Mand $h",
            'references_text' => "Ref $h | https://example.com/a?b=\"<x>", 'server_link' => 'srv ' . $h, 'row_version' => $rv,
        ]]);
        bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => 'social-static']]);
        $line = $d->briefAssets->listByBrief($d->briefs->getByJob($jobId)->id)[0];
        bh_ds($d, $s, 'PATCH', '/jobs/' . $jobId . '/brief/assets/' . $line->id, ['dl' => ['ln_' . $line->id => [
            'template_id' => 'social-static', 'label' => 'L ' . substr($h, 0, 100), 'qty' => 2, 'channel' => 'Ch ' . substr($h, 0, 100), 'size_format' => '1x1', 'specs' => 'Specs ' . $h]]]);
        bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/assignments/traffic', ['team_traffic' => ['value' => $p['traffic']]]);
        $b = $d->briefs->getByJob($jobId);
        t_true(str_contains($b->title, '<script>'), 'stored as typed (escaping happens on output)');

        $pages = static fn (): array => [
            '/briefs', '/campaigns', '/jobs/' . $jobId . '/brief', '/jobs/' . $jobId . '/brief/versions', '/jobs/' . $jobId . '/brief/print',
        ];
        foreach ($pages() as $path) {
            bhs_check($path, ts_body((ts_app($s))(ts_request('GET', $path), $d)), 2);
        }
        bhs_check('send dialog', ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $jobId . '/brief/send')), 0);
        bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/brief/send');
        t_eq('briefed', $d->jobs->get($jobId)->stage->value);
        $rv = $d->briefs->getByJob($jobId)->rowVersion;
        bhs_check('autosave patch', ts_body(bh_ds($d, $s, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['title' => 'New ' . $h, 'row_version' => $rv]])), 0);
        bhs_check('update dialog', ts_body(bh_ds($d, $s, 'GET', '/jobs/' . $jobId . '/brief/update')), 0);
        bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/brief/update', ['send' => ['bump' => 'patch', 'note' => 'Note ' . $h]]);
        bh_ds($d, $s, 'POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'wait', 'waiting_on' => 'client', 'reason' => 'Why ' . $h]]);
        foreach ($pages() as $path) {
            bhs_check($path . ' after send', ts_body((ts_app($s))(ts_request('GET', $path), $d)), 2);
        }
        bhs_check('versions 1.0.1', ts_body((ts_app($s))(ts_request('GET', '/jobs/' . $jobId . '/brief/versions/1.0.1'), $d)), 2);
        bhs_check('error toast html', ts_body(bh_ds($d, $s, 'POST', '/campaigns', ['nc' => ['brand_id' => $c['brand'], 'name' => 'Camp ' . substr($h, 0, 80)]]), Transport::Html), 0);
        // a hostile line id never reaches a regex or signal name
        $odd = ts_body(bh_ds($d, $s, 'PATCH', '/jobs/' . $jobId . '/brief/assets/' . rawurlencode('x/../"<'), ['dl' => ['ln_x' => ['label' => 'a', 'qty' => 1]]]));
        t_contains('Nothing to save for this deliverable.', $odd);
        bhs_check('odd line id', $odd, 0);
    },
];
