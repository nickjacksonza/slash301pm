<?php
declare(strict_types=1);

use App\Config\Transport;
use App\Domain\GridField;
use App\Domain\JobQuery;
use App\Domain\MyDay;
use App\Domain\Role;
use App\Domain\Types\ActivityEntry;
use App\Http\Deps;
use App\Http\MemorySession;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Response;
use App\Store\Ids;

require_once dirname(__DIR__, 2) . '/support/jobs_fx.php';

/**
 * Phase 5: one hostile string in every user-controlled field (brand, campaign,
 * people and usernames, job number, titles, creative direction, mandatories,
 * reference labels and URLs, PDF and server links, deliverables, waiting
 * reason, change note, saved view name, activity text), then EVERY GET route
 * in app/routes.php is rendered, as a page and as a Datastar request, for an AM
 * and the COO. No output may contain the payload raw, a parsed <img>, an
 * inline event handler, an inline <script> or <style>, or a javascript:/data:
 * URL in href/src/action. Every page carries the security header set.
 * (There is no wiki in the new app yet; it comes later with server-side
 * sanitising, audit M11.)
 */
const HP = '<script>"\' & {{x}} </style><img src=x onerror=1>';

/** The HTML inside a response: page bodies as is; SSE element lines joined. */
function hp_html(string $body): string
{
    if (!str_starts_with($body, 'event: ')) {
        return $body;
    }
    $out = [];
    foreach (explode("\n", $body) as $line) {
        if (str_starts_with($line, 'data: elements ')) {
            $out[] = substr($line, strlen('data: elements '));
        }
    }
    return implode("\n", $out);
}

function hp_check(string $where, string $body): void
{
    t_not_contains(HP, $body, "$where: raw payload");
    foreach (['<img src=x', '</style><img', '<script>"', "\"' & {{x}}"] as $frag) {
        t_not_contains($frag, $body, "$where: unescaped fragment $frag");
    }
    $html = hp_html($body);
    if (trim($html) === '') {
        return;
    }
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $xp = new DOMXPath($doc);
    foreach ($xp->query('//*') ?: [] as $el) {
        if (!$el instanceof DOMElement) {
            continue;
        }
        $tag = strtolower($el->nodeName);
        t_true($tag !== 'style', "$where: inline <style> (the CSP blocks it)");
        t_true($tag !== 'script' || $el->hasAttribute('src'), "$where: inline <script> (the CSP blocks it)");
        t_true($tag !== 'img' || $el->getAttribute('src') !== 'x', "$where: injected <img>");
        foreach ($el->attributes ?? [] as $a) {
            $name = strtolower($a->nodeName);
            t_true(!str_starts_with($name, 'on'), "$where: inline handler $name on <$tag>");
            if (in_array($name, ['href', 'src', 'action', 'formaction', 'xlink:href'], true)) {
                $v = strtolower((string) preg_replace('/[\s\x00-\x1f]+/', '', $a->value));
                foreach (['javascript:', 'data:', 'vbscript:'] as $bad) {
                    t_true(!str_starts_with($v, $bad), "$where: $bad URL in $name");
                }
            }
        }
    }
}

function hp_headers(string $where, Response $r): void
{
    $out = $r->render(Transport::Sse);
    t_eq(SecurityHeaders::CSP, $out->header('Content-Security-Policy'), "$where: CSP");
    t_eq('DENY', $out->header('X-Frame-Options'), "$where: X-Frame-Options");
    t_eq('nosniff', $out->header('X-Content-Type-Options'), "$where: nosniff");
    t_eq('strict-origin-when-cross-origin', $out->header('Referrer-Policy'), "$where: Referrer-Policy");
    t_true($out->header('Permissions-Policy') !== '', "$where: Permissions-Policy");
}

/** @return array{0:Deps,1:array<string,string>,2:string,3:string} deps, people, the brief job, a legacy job */
function hp_world(): array
{
    $h = HP;
    [$d, $c, $p, $jobId] = bh_world();
    $amy = bh_session($d, $p['am']);
    $rv = static fn (): int => $d->briefs->getByJob($jobId)->rowVersion;
    bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => [
        'title' => 'Title ' . $h, 'due_date' => '2026-10-20', 'creative_direction' => "CD $h\nline two", 'mandatories_text' => "Mand $h",
        'references_text' => "Ref $h | https://example.com/a?b=1", 'row_version' => $rv(),
    ]]);
    bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/assets', ['new_line' => ['template_id' => 'social-static']]);
    $line = $d->briefAssets->listByBrief($d->briefs->getByJob($jobId)->id)[0];
    bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief/assets/' . $line->id, ['dl' => ['ln_' . $line->id => [
        'template_id' => 'social-static', 'label' => 'L ' . $h, 'qty' => 2, 'channel' => 'Ch ' . $h, 'size_format' => '1x1', 'specs' => 'Specs ' . $h]]]);
    bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/assignments/traffic', ['team_traffic' => ['value' => $p['traffic']]]);
    bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/send');
    t_eq('briefed', $d->jobs->get($jobId)->stage->value, 'world: brief sent');
    // working copy with links that must never become live links (written straight to the row, as legacy or an old import could)
    $d->db->exec('UPDATE briefs SET references_json = :r, brief_pdf_url = :pdf, server_link = :srv, has_unsent_changes = 1 WHERE job_id = :j', [
        'r' => json_encode([['label' => 'Evil ' . $h, 'url' => 'javascript:alert(1)'], ['label' => 'Data', 'url' => 'data:text/html,x'], ['label' => 'Fine', 'url' => 'https://example.com/ok']], JSON_THROW_ON_ERROR),
        'pdf' => 'javascript:alert(2)', 'srv' => ' JavaScript:alert(3)', 'j' => $jobId,
    ]);
    bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/brief/update', ['send' => ['bump' => 'patch', 'note' => 'Note ' . $h]]);
    bh_ds($d, $amy, 'POST', '/jobs/' . $jobId . '/transition', ['tr' => ['action' => 'wait', 'waiting_on' => 'client', 'reason' => 'Why ' . $h]]);
    // a legacy job with a hostile number, owned by Amy, plus one without an AM (claimable)
    $legacy = jf_job($d, 'MERC-' . $h, $c['campaign'], 'Legacy ' . $h, ['status' => 'In Progress', 'due' => '2026-10-05', 'slots' => ['AM' => $p['am'], 'Traffic' => $p['traffic']], 'asset' => $p['designer']]);
    jf_job($d, 'MERC-777', $c['campaign'], 'Unowned ' . $h, ['status' => 'In Review', 'due' => '2026-10-12']);
    // everything people can name, renamed hostile
    $d->db->exec('UPDATE brands SET name = :n', ['n' => 'Brand ' . $h]);
    // a logo link written straight to the row (never accepted by POST /brands/logo) must never become an <img src>
    $d->db->exec("UPDATE brands SET logo_url = 'javascript:alert(1)'");
    $d->db->exec('UPDATE campaigns SET name = :n, description = :n', ['n' => 'Camp ' . $h]);
    $d->db->exec('UPDATE users SET name = :n', ['n' => 'Person ' . $h]);
    $d->db->exec('UPDATE users SET username = :n WHERE id = :u', ['n' => 'user' . $h, 'u' => $p['copy']]);
    $d->db->exec(
        "INSERT INTO saved_views (id, owner_id, screen, name, state_json, is_shared, is_default, position, created_at, updated_at)
         VALUES (:id, :o, 'jobs', :n, '{\"group\":\"brand\"}', 1, 0, 0, '2026-10-09 06:00:00', '2026-10-09 06:00:00')",
        ['id' => Ids::new(), 'o' => $p['am'], 'n' => 'View ' . $h],
    );
    $d->db->exec(
        "INSERT INTO saved_views (id, owner_id, screen, name, state_json, is_shared, is_default, position, created_at, updated_at)
         VALUES (:id, :o, 'board', :n, '{}', 1, 0, 0, '2026-10-09 06:00:00', '2026-10-09 06:00:00')",
        ['id' => Ids::new(), 'o' => $p['am'], 'n' => 'Board ' . $h],
    );
    // activity text from another person, addressed to Amy (shows in My day, the sheet and the brief rail)
    $d->db->txImmediate(function ($tx) use ($d, $jobId, $legacy, $p, $h): void {
        foreach ([$jobId, $legacy] as $j) {
            $d->activity->append($tx, new ActivityEntry($j, $p['traffic'], 'verb ' . $h, 'job', $j, [
                'note' => 'Note ' . $h, 'reason' => 'Reason ' . $h, 'user_name' => 'Name ' . $h, 'recipients' => [$p['am']]]), new DateTimeImmutable('2026-10-09 08:00:00'));
            // an override with hostile values, for the feeds and the COO's /admin/overrides
            $d->activity->append($tx, new ActivityEntry($j, $p['traffic'], 'asset_status_overridden', 'asset', $j, [
                'kind' => 'asset', 'asset_name' => 'Asset ' . $h, 'from' => 'From ' . $h, 'to' => 'To ' . $h, 'reason' => 'Reason ' . $h, 'recipients' => [$p['am']]]),
                new DateTimeImmutable('2026-10-09 08:30:00'));
        }
    });
    return [$d, $p, $jobId, $legacy];
}

/** Every GET route with its path values filled in. @return list<string> */
function hp_get_paths(string $jobId, string $legacy): array
{
    $values = [
        'id' => [$jobId, $legacy],
        'section' => MyDay::sectionKeys(),
        'v' => ['1.0.0', '1.0.1'],
        'field' => array_map(static fn (GridField $f): string => $f->value, GridField::cases()),
        'mode' => ['outer', 'inner', 'remove'],
        'kind' => ['elements', 'toast'],
    ];
    $out = [];
    foreach (require dirname(__DIR__, 3) . '/app/routes.php' as $entry) {
        [$method, $pattern] = explode(' ', $entry[0], 2);
        if ($method !== 'GET') {
            continue;
        }
        $paths = [str_replace('{$}', '', $pattern)];
        while (preg_match('/\{([a-z]+)\}/', $paths[0], $m) === 1) {
            $next = [];
            foreach ($paths as $path) {
                foreach ($values[$m[1]] ?? [] as $v) {
                    $next[] = str_replace('{' . $m[1] . '}', rawurlencode($v), $path);
                }
            }
            t_true($next !== [], "no test values for {{$m[1]}} in $pattern");
            $paths = $next;
        }
        foreach ($paths as $path) {
            $out[] = $path;
        }
    }
    // grouped and filtered variants, so group headers and filter menus render the hostile names too
    foreach (['brand', 'campaign', 'am', 'stage'] as $g) {
        $out[] = '/jobs?group=' . $g;
        $out[] = '/jobs/board?group=' . $g;
    }
    return $out;
}

return [
    'hostile strings: every GET route, as page and Datastar fragment, for AM and COO, escaped and CSP-clean' => function (): void {
        [$d, $p, $jobId, $legacy] = hp_world();
        $rendered = 0;
        foreach (['am' => bh_session($d, $p['am']), 'coo' => bh_session($d, $p['coo'])] as $who => $s) {
            foreach (hp_get_paths($jobId, $legacy) as $path) {
                $parts = explode('?', $path, 2);
                $query = [];
                parse_str($parts[1] ?? '', $query);
                $page = (ts_app($s))(ts_request('GET', $parts[0], [], '', [], $query), $d);
                hp_headers("$who GET $path", $page);
                hp_check("$who GET $path", ts_body($page));
                $q = JobQuery::fromQuery($query, str_contains($parts[0], 'board') ? 'board' : 'jobs')->toSignalState();
                $ds = (ts_app($s))(ts_ds_request('GET', $parts[0], $s, ['q' => $q, 'edit' => ['value' => '']]), $d);
                hp_headers("$who DS $path", $ds);
                hp_check("$who DS $path", ts_body($ds));
                if (!str_starts_with($parts[0], '/system/spike')) {   // the spike tests SSE-only multi-event answers on purpose
                    hp_check("$who DS html $path", ts_body($ds, Transport::Html));
                }
                $rendered += ($page->status() === 200 ? 1 : 0) + ($ds->status() === 200 ? 1 : 0);
            }
        }
        t_true($rendered > 150, "rendered $rendered responses");
        // the javascript: links stored on the row are shown as text, the https one stays a link
        $editor = ts_body((ts_app(bh_session($d, $p['am'])))(ts_request('GET', '/jobs/' . $jobId . '/brief/versions/1.0.1'), $d));
        t_contains('href="https://example.com/ok"', $editor);
        t_contains('Evil &lt;script&gt;', $editor);
    },
    'hostile strings: the sign-in page lists demo users safely' => function (): void {
        $d = ts_deps(true);
        ts_user($d, 'demo_am', Role::AM);
        $d->db->exec('UPDATE users SET name = :n', ['n' => 'Person ' . HP]);
        $anon = new MemorySession(['csrf_token' => 'x']);
        $page = (ts_app($anon))(ts_request('GET', '/login'), $d);
        hp_headers('login', $page);
        hp_check('login', ts_body($page));
        t_contains('Person &lt;script&gt;', ts_body($page));
    },
    'links: javascript:, data: and other schemes are refused on input; share paths are kept as text' => function (): void {
        [$d, , $p, $jobId] = bh_world();
        $amy = bh_session($d, $p['am']);
        $rv = static fn (): int => $d->briefs->getByJob($jobId)->rowVersion;
        $cases = [
            ['brief_pdf_url', 'javascript:alert(1)', 'The brief PDF must be an http(s) link.'],
            ['brief_pdf_url', 'data:text/html,x', 'The brief PDF must be an http(s) link.'],
            ['server_link', 'javascript:alert(1)', 'The server folder must be'],
            ['server_link', 'vbscript:x', 'The server folder must be'],
            ['server_link', 'file:///etc/passwd', 'The server folder must be'],
            ['references_text', 'Evil | javascript:alert(1)', ''],
        ];
        foreach ($cases as [$field, $value, $msg]) {
            $body = ts_body(bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => [$field => $value, 'row_version' => $rv()]]));
            if ($msg !== '') {
                t_contains($msg, $body, "$field = $value");
            }
            $b = $d->briefs->getByJob($jobId);
            t_eq('', $b->briefPdfUrl, "$field = $value: pdf unchanged");
            t_eq('', $b->serverLink, "$field = $value: server link unchanged");
            t_eq([], $b->references, "$field = $value: no reference stored");
        }
        foreach (['\\\\fileserver\\Clients\\Meridian', 'smb://fileserver/Clients', '/Volumes/Clients/Meridian', 'https://drive.example.com/x'] as $ok) {
            bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['server_link' => $ok, 'row_version' => $rv()]]);
            t_eq($ok, $d->briefs->getByJob($jobId)->serverLink, "server link $ok accepted");
        }
        $html = ts_body((ts_app($amy))(ts_request('GET', '/jobs/' . $jobId . '/brief/print'), $d));
        t_contains('href="https://drive.example.com/x"', $html);
        bh_ds($d, $amy, 'PATCH', '/jobs/' . $jobId . '/brief', ['brief' => ['server_link' => '\\\\fileserver\\Clients', 'row_version' => $rv()]]);
        $html = ts_body((ts_app($amy))(ts_request('GET', '/jobs/' . $jobId . '/brief/print'), $d));
        t_contains('\\\\fileserver\\Clients', $html);
        t_not_contains('href="\\\\fileserver', $html, 'share paths are text, not links');
    },
];
