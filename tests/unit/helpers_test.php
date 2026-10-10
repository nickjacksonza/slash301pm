<?php
declare(strict_types=1);

return [
    'e escapes html and quotes' => function (): void {
        t_eq('&lt;b&gt;&quot;x&quot; &amp; &apos;y&apos;&lt;/b&gt;', e('<b>"x" & \'y\'</b>'));
        t_eq('', e(null));
        t_eq('42', e(42));
    },
    'js produces a safe attribute-embedded JS literal' => function (): void {
        $out = js("it's </script><img onerror=x>");
        t_not_contains('<', $out);
        t_not_contains("'", $out);
        t_not_contains('"', $out);
        // Decoding the attribute gives valid JSON for the original string.
        $decoded = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        t_eq("it's </script><img onerror=x>", json_decode($decoded, true));
    },
    'js encodes structures' => function (): void {
        $decoded = html_entity_decode(js(['a' => 1, 'b' => [true, null]]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        t_eq(['a' => 1, 'b' => [true, null]], json_decode($decoded, true));
    },
    'url uses base path' => function (): void {
        $old = app_base_path();
        app_base_path('/slash301pm');
        t_eq('/slash301pm/jobs', url('/jobs'));
        t_eq('/slash301pm/jobs?stage=draft&q=a%20b', url('jobs', ['stage' => 'draft', 'q' => 'a b']));
        app_base_path($old);
    },
    'cx joins literal classes' => function (): void {
        t_eq('a b', cx('a', '', 'b'));
    },
];
