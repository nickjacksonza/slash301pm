<?php
declare(strict_types=1);

/**
 * Drift guard: pages are built from the shared parts (ui_button, ui_input,
 * ui_textarea, ui_native_select), so every control looks the same. A page may
 * not carry a hand-written <button>, <select>, <textarea> or <input> (hidden
 * inputs are fine). The allow-list is a ceiling per file: it may only shrink.
 * Each entry says why the controls remain; converting them is open work
 * (docs/PLAN.md step 7).
 */

const PAGE_PARTS_ALLOW = [
    'spike.php' => [99, 'Diagnostics page, not user-facing.'],
    'brief_print.php' => [99, 'Print view.'],
    'brief_editor.php' => [11, 'Compact deliverables-grid cell inputs, icon buttons and the send dialog radios.'],
    'jobs_grid.php' => [8, 'Inline cell editors and text-link buttons in the Airtable-style grid.'],
    'jobs_board.php' => [2, 'Stage filter chip and job-number link button on cards.'],
    'social.php' => [5, 'Queue tab, platform chip, and three checkboxes (mine only, checklist, promoted).'],
];

return [
    'pages carry no hand-written controls beyond the allow-list' => function (): void {
        $dir = dirname(__DIR__, 3) . '/app/View/pages';
        $bad = [];
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $name = basename($file);
            $count = 0;
            foreach (file($file) ?: [] as $line) {
                $t = ltrim($line);
                if (str_starts_with($t, '*') || str_starts_with($t, '//')) {
                    continue;
                }
                if (preg_match('/<(button|select|textarea|input)\b/', $line) === 1 && !str_contains($line, 'type="hidden"')) {
                    $count++;
                }
            }
            $max = PAGE_PARTS_ALLOW[$name][0] ?? 0;
            if ($count > $max) {
                $bad[] = "$name has $count hand-written controls (allowed $max)";
            }
        }
        t_eq([], $bad, 'use ui_button, ui_input, ui_textarea or ui_native_select');
    },
];
