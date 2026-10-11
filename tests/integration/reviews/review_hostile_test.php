<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/support/review_fx.php';
// hp_check() and HP: the hostile-string checks of every other page (already loaded by the runner, which sorts http/ first).
require_once dirname(__DIR__) . '/http/hostile_pages_test.php';

/**
 * Reviews: a hostile string in every field a person can type (copy, hashtags,
 * note, feedback, names of the brand, the people and the post) and
 * javascript:/data: links written straight into the rows (as legacy or a bad
 * import could) never reach the review page, the hand-in page, My day or the
 * Datastar answers unescaped, and never become an href or src.
 */
return [
    'reviews: hostile strings and javascript: links are escaped and never linked' => function (): void {
        $h = HP;
        [$d, $p, $job] = rx_world(2);
        [$a1, $a2] = rx_posts($d, $job);
        $copy = bh_session($d, $p['copy']);
        $des = bh_session($d, $p['designer']);
        $cd = bh_session($d, $p['cd']);
        sx_ok(rx_sub($d, $copy, $job, $a1, ['copy_text' => "Copy $h\nline two", 'hashtags' => "#tag $h", 'note' => "Note $h", 'link_url' => 'https://example.com/?q=%3Cscript%3E']));
        sx_ok(rx_sub($d, $des, $job, $a1, ['media_url' => 'https://cdn.example.com/a.png?x=1&y=2'], true));
        sx_ok(rx_rev($d, $cd, $job, $a1, 'rejected_with_feedback', 'copy', "Feedback $h"));
        sx_ok(rx_sub($d, $copy, $job, $a2, ['copy_text' => 'Plain']));
        // Links that the forms refuse, written straight to the rows.
        $d->db->exec("UPDATE asset_submissions SET media_url = 'javascript:alert(1)//.png', media_kind = 'image', link_url = ' JavaScript:alert(2)' WHERE asset_id = :a", ['a' => $a2]);
        $d->db->exec("UPDATE asset_submissions SET media_url = 'data:image/png;base64,AAAA', media_kind = 'image' WHERE asset_id = :a AND round = 1", ['a' => $a1]);
        $d->db->exec("UPDATE brands SET name = :n, logo_url = 'javascript:alert(3)'", ['n' => 'Brand ' . $h]);
        $d->db->exec('UPDATE users SET name = :n', ['n' => 'Person ' . $h]);
        $d->db->exec('UPDATE assets SET name = :n WHERE id = :a', ['n' => 'Post ' . $h, 'a' => $a2]);
        $d->db->exec('UPDATE jobs SET title = :n WHERE id = :j', ['n' => 'Job ' . $h, 'j' => $job]);
        $rendered = 0;
        foreach (['cd', 'copy', 'am', 'coo'] as $who) {
            $s = bh_session($d, $p[$who]);
            foreach (['/jobs/' . $job . '/review', '/jobs/' . $job . '/work', '/today', '/today/reviews'] as $path) {
                $page = (ts_app($s))(ts_request('GET', $path), $d);
                if ($page->status() === 200) {
                    hp_check("$who GET $path", ts_body($page));
                    hp_headers("$who GET $path", $page);
                    $rendered++;
                }
                hp_check("$who DS $path", ts_body(bh_ds($d, $s, 'GET', $path)));
            }
        }
        t_true($rendered >= 10, 'pages rendered: ' . $rendered);
        $review = ts_body((ts_app($cd))(ts_request('GET', '/jobs/' . $job . '/review'), $d));
        t_contains('Feedback &lt;script&gt;', $review, 'feedback shown, escaped');
        t_contains('javascript:alert(1)//.png', $review, 'a bad media link is shown as text');
        t_not_contains('href="javascript', strtolower($review));
        t_not_contains('src="data:', $review);
        t_not_contains('src="javascript', strtolower($review));
        // Datastar answers (cards, the job bar, the work form) are checked the same way.
        hp_check('decision answer', ts_body(rx_rev($d, $cd, $job, $a2, 'rejected_with_feedback', 'copy', "Again $h")));
        hp_check('hand-in answer', ts_body(rx_sub($d, $copy, $job, $a2, ['copy_text' => "New $h"])));
        hp_check('job step answer', ts_body(rx_job($d, $cd, $job, 'request-ecd')));
    },

    'reviews: answers work in the html transport too (one patch set per response)' => function (): void {
        [$d, $p, $job] = rx_world(1);
        [$a1] = rx_posts($d, $job);
        sx_ok(rx_sub($d, bh_session($d, $p['copy']), $job, $a1, ['copy_text' => 'Copy']));
        foreach ([
            rx_sub($d, bh_session($d, $p['designer']), $job, $a1, ['media_url' => 'https://cdn.example.com/a.png'], true),
            rx_rev($d, bh_session($d, $p['cd']), $job, $a1, 'approved'),
            rx_job($d, bh_session($d, $p['cd']), $job, 'approve'),
        ] as $i => $r) {
            $html = $r->render(\App\Config\Transport::Html);
            t_eq(200, $html->status, "answer $i");
            t_contains('data-toast="ok"', $html->body(), "answer $i");
        }
    },
];
