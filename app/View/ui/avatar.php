<?php
declare(strict_types=1);

use App\View\ui\AvatarProps;

/**
 * Ported from DatastarUI components/avatar/avatar.templ
 * Source: github.com/coreycole/datastarui @ feb9af0c58ade31f8fefc1443b7e7e15ad413242 (MIT)
 * Fixtures: tests/fixtures/ui/avatar*.html
 * Added: name derives initials and a stable background from a fixed palette (all white-on-colour pairs are AA).
 * A class containing size- replaces the default 32px box. backgroundColor must be #rgb/#rrggbb or hsl(...); anything else throws.
 */
function ui_avatar_initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts === []) {
        return '?';
    }
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** Stable colour for a name. Deep tones so white text keeps contrast of 4.5 or better. */
function ui_avatar_color(string $name): string
{
    $palette = ['#3b6b5a', '#4a5f8a', '#7a4f6d', '#8a5a2b', '#5b6b2e', '#2f6f7a', '#85485a', '#5f5a8a'];
    return $palette[crc32(mb_strtolower(trim($name))) % count($palette)];
}

function ui_avatar(AvatarProps $p, string $children = ''): string
{
    $bg = $p->backgroundColor;
    if ($bg === '' && $p->name !== '') {
        $bg = ui_avatar_color($p->name);
    }
    if ($children === '' && $p->name !== '') {
        $children = e(ui_avatar_initials($p->name));
    }
    $style = '';
    if ($bg !== '') {
        $text = $p->textColor !== '' ? $p->textColor : '#ffffff';
        foreach ([$bg, $text] as $c) {
            if (preg_match('/^(#[0-9a-fA-F]{3,8}|hsla?\([0-9.,%\s\/deg]+\)|rgba?\([0-9.,%\s\/]+\))$/', $c) !== 1) {
                throw new InvalidArgumentException('ui_avatar: invalid colour ' . json_encode($c));
            }
        }
        $style = ' style="' . attr('background-color: ' . $bg . '; color: ' . $text . ';') . '"';
    }
    $classes = cx('inline-flex items-center justify-center overflow-hidden rounded-full', str_contains($p->class, 'size-') ? '' : 'h-[32px] w-[32px]', $p->class);
    return '<div data-slot="avatar" class="' . attr($classes) . '"' . $style . ui_attrs($p->attrs) . '>' . $children . '</div>';
}
