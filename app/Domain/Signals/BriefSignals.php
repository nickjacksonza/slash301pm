<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\Links;

use App\Domain\Types\BriefPatch;
use App\Domain\Types\BriefReference;
use App\Domain\ValidationErrors;

/**
 * brief.* from the editor (autosave). Only keys present in the signals are
 * patched. Invalid fields are left out of the patch and reported; valid ones
 * are still saved. mandatories_text is one item per line; references_text is
 * one per line as "label | https://link" or just the link.
 */
final class BriefSignals
{
    public const MAX_TITLE = 200;
    public const MAX_TEXT = 20000;
    public const MAX_ITEMS = 50;
    public const MAX_ITEM = 500;

    public function __construct(
        public readonly BriefPatch $patch,
        public readonly ValidationErrors $errors,
        public readonly int $rowVersion,
    ) {}

    public static function fromSignals(array $s): self
    {
        $b = SignalInput::obj($s, 'brief');
        $errors = new ValidationErrors();
        $set = [];
        $title = null;
        $campaignId = null;
        $dates = ['brief_date' => null, 'due_date' => null, 'first_go_live' => null, 'last_go_live' => null];
        $cd = null;
        $mand = null;
        $refs = null;
        $pdf = null;
        $server = null;
        $budget = null;
        $hours = null;

        if (SignalInput::has($b, 'title')) {
            $t = trim(preg_replace('/\s+/u', ' ', SignalInput::str($b, 'title')) ?? '');
            if ($t === '') {
                $errors = $errors->with('title', 'The title cannot be empty.');
            } elseif (mb_strlen($t) > self::MAX_TITLE) {
                $errors = $errors->with('title', 'Keep the title under 200 characters.');
            } else {
                $title = $t;
                $set[] = 'title';
            }
        }
        if (SignalInput::has($b, 'campaign_id')) {
            $c = trim(SignalInput::str($b, 'campaign_id'));
            if ($c === '' || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $c) !== 1) {
                $errors = $errors->with('campaign_id', 'Choose a campaign.');
            } else {
                $campaignId = $c;
                $set[] = 'campaign_id';
            }
        }
        foreach (array_keys($dates) as $k) {
            if (SignalInput::has($b, $k)) {
                $d = SignalInput::date(SignalInput::str($b, $k));
                if ($d === false) {
                    $errors = $errors->with($k, 'Dates must be real dates (YYYY-MM-DD).');
                } else {
                    $dates[$k] = $d;
                    $set[] = $k;
                }
            }
        }
        if (SignalInput::has($b, 'creative_direction')) {
            $v = rtrim(SignalInput::str($b, 'creative_direction'));
            if (mb_strlen($v) > self::MAX_TEXT || SignalInput::hasControlChars($v, true)) {
                $errors = $errors->with('creative_direction', 'The creative direction is too long (20000 characters at most).');
            } else {
                $cd = $v;
                $set[] = 'creative_direction';
            }
        }
        if (SignalInput::has($b, 'mandatories_text')) {
            $items = self::lines(SignalInput::str($b, 'mandatories_text'));
            $bad = count($items) > self::MAX_ITEMS;
            foreach ($items as $i) {
                if (mb_strlen($i) > self::MAX_ITEM) {
                    $bad = true;
                }
            }
            if ($bad) {
                $errors = $errors->with('mandatories', 'Up to 50 mandatories of up to 500 characters each.');
            } else {
                $mand = $items;
                $set[] = 'mandatories';
            }
        }
        if (SignalInput::has($b, 'references_text')) {
            $parsed = [];
            $bad = '';
            $items = self::lines(SignalInput::str($b, 'references_text'));
            if (count($items) > self::MAX_ITEMS) {
                $bad = 'Up to 50 references.';
            }
            foreach ($items as $line) {
                $r = self::reference($line);
                if ($r === null) {
                    $bad = 'Each reference needs an http(s) link, written as "label | https://link".';
                    break;
                }
                $parsed[] = $r;
            }
            if ($bad !== '') {
                $errors = $errors->with('references', $bad);
            } else {
                $refs = $parsed;
                $set[] = 'references';
            }
        }
        if (SignalInput::has($b, 'brief_pdf_url')) {
            $v = trim(SignalInput::str($b, 'brief_pdf_url'));
            if (!SignalInput::httpUrl($v)) {
                $errors = $errors->with('brief_pdf_url', 'The brief PDF must be an http(s) link.');
            } else {
                $pdf = $v;
                $set[] = 'brief_pdf_url';
            }
        }
        if (SignalInput::has($b, 'server_link')) {
            $v = trim(SignalInput::str($b, 'server_link'));
            if ($v !== '' && !Links::isServerLink($v)) {
                $errors = $errors->with('server_link', 'The server folder must be a web link (https://...), an smb:// or afp:// share, or a path like \\\\server\\share or /Volumes/... (up to 2000 characters).');
            } else {
                $server = $v;
                $set[] = 'server_link';
            }
        }
        if (SignalInput::has($b, 'budget')) {
            $n = SignalInput::number(SignalInput::str($b, 'budget'), 1000000000.0);
            if ($n === false) {
                $errors = $errors->with('budget', 'The budget must be a number in rand (for example 25000).');
            } else {
                $budget = $n;
                $set[] = 'budget';
            }
        }
        if (SignalInput::has($b, 'hours_estimate')) {
            $n = SignalInput::number(SignalInput::str($b, 'hours_estimate'), 10000.0);
            if ($n === false) {
                $errors = $errors->with('hours_estimate', 'Hours must be a number (for example 30 or 7.5).');
            } else {
                $hours = $n;
                $set[] = 'hours_estimate';
            }
        }
        $patch = new BriefPatch($set, $title, $campaignId, $dates['brief_date'], $dates['due_date'], $dates['first_go_live'], $dates['last_go_live'],
            $cd, $mand, $refs, $pdf, $server, $budget, $hours);
        return new self($patch, $errors, SignalInput::int($b, 'row_version'));
    }

    /** @return list<string> trimmed non-empty lines */
    public static function lines(string $text): array
    {
        $out = [];
        foreach (explode("\n", $text) as $l) {
            $l = trim($l);
            if ($l !== '' && !SignalInput::hasControlChars($l, false)) {
                $out[] = $l;
            }
        }
        return $out;
    }

    /** "label | https://x", "label https://x" or "https://x"; null without a valid http(s) link. */
    public static function reference(string $line): ?BriefReference
    {
        if (preg_match('#^(.*?)\s*(https?://\S+)\s*$#i', $line, $m) !== 1) {
            return null;
        }
        $url = $m[2];
        if (!SignalInput::httpUrl($url)) {
            return null;
        }
        $label = trim($m[1], " \t|:-–—");
        if (mb_strlen($label) > 200) {
            return null;
        }
        return new BriefReference($label === '' ? $url : $label, $url);
    }
}
