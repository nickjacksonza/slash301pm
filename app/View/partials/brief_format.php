<?php
declare(strict_types=1);

// Formatting helpers for brief pages. Pure functions of their input (no clock).

/** '2026-10-20' -> '20 Oct 2026'; '' for null or junk. */
function fmt_date(?string $ymd): string
{
    if ($ymd === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
        return '';
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new DateTimeZone('Africa/Johannesburg'));
    return $d === false ? '' : $d->format('j M Y');
}

/** A stored UTC 'Y-m-d H:i:s' -> '9 Oct 2026, 11:03' in SAST. */
function fmt_when(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
    if ($d === false) {
        // legacy rows may hold a bare date or an ISO string; show the date part
        return fmt_date(substr($utc, 0, 10)) !== '' ? fmt_date(substr($utc, 0, 10)) : $utc;
    }
    return $d->setTimezone(new DateTimeZone('Africa/Johannesburg'))->format('j M Y, H:i');
}

/** A diff value: dates read like the rest of the page, everything else unchanged. */
function fmt_diff_value(string $v): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && fmt_date($v) !== '' ? fmt_date($v) : $v;
}

/** Just the SAST time of a stored UTC timestamp: '11:03'. */
function fmt_time(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
    return $d === false ? '' : $d->setTimezone(new DateTimeZone('Africa/Johannesburg'))->format('H:i');
}

/** 25000.0 -> 'R 25 000'; 1234.5 -> 'R 1 234.50'. */
function fmt_zar(?float $v): string
{
    if ($v === null) {
        return '';
    }
    $dec = floor($v) === $v ? 0 : 2;
    return 'R ' . number_format($v, $dec, '.', ' ');
}

/** 30.0 -> '30', 7.5 -> '7.5'. */
function fmt_num(?float $v): string
{
    if ($v === null) {
        return '';
    }
    return floor($v) === $v ? number_format($v, 0, '.', '') : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}

/** An <a> for http(s) URLs only; anything else is plain escaped text. */
function brief_link(string $url, string $label = ''): string
{
    $text = e($label !== '' ? $label : $url);
    if (preg_match('#^https?://#i', $url) !== 1) {
        return $text;
    }
    return '<a href="' . attr($url) . '" target="_blank" rel="noopener noreferrer" class="text-primary underline underline-offset-4 break-all">' . $text . '</a>';
}

/** Plain text with line breaks kept (creative direction is stored as text, never HTML). */
function brief_text(string $s): string
{
    return nl2br(e($s), false);
}

/** A deliverable line id is used inside signal names and regexes: hex/word characters only. */
function brief_line_key(string $lineId): string
{
    if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $lineId) !== 1) {
        throw new InvalidArgumentException('Unsafe line id');
    }
    return 'ln_' . $lineId;
}
