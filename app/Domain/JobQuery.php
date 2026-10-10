<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\JobSort;

/**
 * What the job grid or board shows: filters, sort (up to two keys), grouping
 * and visible columns. Every value is whitelisted here; the Store turns it
 * into SQL with bound parameters. Three interchangeable forms:
 *   URL query   ?stages=open&owner=mine&due=this_week&q=launch&sort=due,-updated&group=brand&cols=job_number,title,due
 *   state       ['stages' => ['draft', ...], 'owner' => 'mine', 'sort' => 'due,-updated', 'cols' => [...], ...]
 *               (the q.* signals and saved_views.state_json use this shape)
 *   object      this class
 * Unknown or invalid values are dropped (never an error): a bookmarked URL or
 * an old saved view always opens. Pure. Go: type JobQuery struct + parse funcs.
 */
final class JobQuery
{
    public const SCREEN_JOBS = 'jobs';
    public const SCREEN_BOARD = 'board';
    public const MAX_TEXT = 100;
    public const MAX_SORTS = 2;

    /**
     * @param list<Stage> $stages never empty, in pipeline() order
     * @param list<JobSort> $sorts 1 to MAX_SORTS keys, distinct fields
     * @param list<JobColumn> $columns in JobColumn::cases() order, required ones included
     */
    public function __construct(
        public readonly string $screen,
        public readonly array $stages,
        public readonly string $brandId,
        public readonly string $campaignId,
        public readonly OwnerFilter $owner,
        public readonly string $assigneeId,
        public readonly ?Role $role,
        public readonly ?DueWindow $due,
        public readonly string $text,
        public readonly array $sorts,
        public readonly JobGroupBy $groupBy,
        public readonly array $columns,
    ) {}

    public static function defaults(string $screen): self
    {
        $board = $screen === self::SCREEN_BOARD;
        return new self(
            $board ? self::SCREEN_BOARD : self::SCREEN_JOBS,
            $board ? self::stageSet('board') : self::stageSet('open'),
            '', '', OwnerFilter::All, '', null, null, '',
            [new JobSort(JobSortField::Due), new JobSort(JobSortField::JobNumber)],
            JobGroupBy::None,
            JobColumn::cases(),
        );
    }

    /**
     * Stages in the order the board and the stage sort use: the Stage enum's
     * order, so Waiting and On hold sit next to In progress (the usual drop target).
     * @return list<Stage>
     */
    public static function pipeline(): array
    {
        return Stage::cases();
    }

    /** Named stage sets accepted in ?stages= and saved state. @return list<string> */
    public static function stageSetNames(): array
    {
        return ['open', 'active', 'workable', 'board', 'closed', 'all'];
    }

    /**
     * board: the open stages without the social ones (they appear when chosen).
     * @return list<Stage>
     */
    public static function stageSet(string $name): array
    {
        $want = match ($name) {
            'open' => Stage::open(),
            'active' => Stage::active(),
            'workable' => Stage::workable(),
            'board' => [Stage::Draft, Stage::Briefed, Stage::InProgress, Stage::InReview, Stage::ApprovedInternal, Stage::ApprovedClient, Stage::Waiting, Stage::OnHold],
            'closed' => Stage::closed(),
            'all' => Stage::cases(),
            default => [],
        };
        return self::ordered($want);
    }

    /** @param array<string,mixed> $query decoded URL query ($_GET shape) */
    public static function fromQuery(array $query, string $screen): self
    {
        $str = static fn (string $k): string => isset($query[$k]) && is_string($query[$k]) ? $query[$k] : '';
        return self::build($screen, [
            'stages' => $str('stages'), 'brand' => $str('brand'), 'campaign' => $str('campaign'), 'owner' => $str('owner'),
            'assignee' => $str('assignee'), 'role' => $str('role'), 'due' => $str('due'), 'text' => $str('q'),
            'sort' => $str('sort'), 'group' => $str('group'), 'cols' => $str('cols'),
        ]);
    }

    /** The q.* signals or a saved view's decoded state_json. @param array<string,mixed> $state */
    public static function fromState(array $state, string $screen): self
    {
        $parts = [];
        foreach (['stages', 'brand', 'campaign', 'owner', 'assignee', 'role', 'due', 'text', 'sort', 'group', 'cols'] as $k) {
            $v = $state[$k] ?? '';
            if (is_array($v) && ($k === 'stages' || $k === 'cols')) {
                $items = [];
                foreach ($v as $item) {
                    if (is_string($item)) {
                        $items[] = $item;
                    }
                }
                $parts[$k] = implode(',', $items);
            } else {
                $parts[$k] = is_string($v) ? $v : '';
            }
        }
        return self::build($screen, $parts);
    }

    /** A stored state_json; null when it is not a JSON object. */
    public static function fromSavedState(string $json, string $screen): ?self
    {
        $d = json_decode($json, true, 8);
        if (!is_array($d) || ($d !== [] && array_is_list($d))) {
            return null;
        }
        return self::fromState($d, $screen);
    }

    /** Only the values that differ from the screen's defaults. @return array<string,string> */
    public function toQuery(): array
    {
        $def = self::defaults($this->screen);
        $out = [];
        if ($this->stageToken() !== $def->stageToken()) {
            $out['stages'] = $this->stageToken();
        }
        if ($this->brandId !== '') {
            $out['brand'] = $this->brandId;
        }
        if ($this->campaignId !== '') {
            $out['campaign'] = $this->campaignId;
        }
        if ($this->owner !== OwnerFilter::All) {
            $out['owner'] = $this->owner->value;
        }
        if ($this->assigneeId !== '') {
            $out['assignee'] = $this->assigneeId;
        }
        if ($this->role !== null) {
            $out['role'] = $this->role->value;
        }
        if ($this->due !== null) {
            $out['due'] = $this->due->value;
        }
        if ($this->text !== '') {
            $out['q'] = $this->text;
        }
        if ($this->screen === self::SCREEN_JOBS) {
            if ($this->sortToken() !== $def->sortToken()) {
                $out['sort'] = $this->sortToken();
            }
            if ($this->groupBy !== JobGroupBy::None) {
                $out['group'] = $this->groupBy->value;
            }
            if ($this->columnToken() !== $def->columnToken()) {
                $out['cols'] = $this->columnToken();
            }
        }
        return $out;
    }

    /** Signal / saved-state form (every key, arrays for sets). @return array<string,mixed> */
    public function toState(): array
    {
        $stages = [];
        foreach ($this->stages as $s) {
            $stages[] = $s->value;
        }
        $cols = [];
        foreach ($this->columns as $c) {
            $cols[] = $c->value;
        }
        return [
            'stages' => $stages, 'brand' => $this->brandId, 'campaign' => $this->campaignId, 'owner' => $this->owner->value,
            'assignee' => $this->assigneeId, 'role' => $this->role !== null ? $this->role->value : '', 'due' => $this->due !== null ? $this->due->value : '',
            'text' => $this->text, 'sort' => $this->sortToken(), 'group' => $this->groupBy->value, 'cols' => $cols,
        ];
    }

    /**
     * The q.* signal form for the page: like toState(), but stages and cols are
     * aligned to the checkbox order (pipeline(), JobColumn::cases()), with ''
     * for an unticked box. Datastar binds an array signal to same-named
     * checkboxes by position, so a compact list would tick the wrong boxes.
     * fromState() ignores the '' entries.
     * @return array<string,mixed>
     */
    public function toSignalState(): array
    {
        $st = $this->toState();
        $stages = [];
        foreach (self::pipeline() as $s) {
            $stages[] = $this->hasStage($s) ? $s->value : '';
        }
        $cols = [];
        foreach (JobColumn::cases() as $c) {
            $cols[] = $this->hasColumn($c) ? $c->value : '';
        }
        $st['stages'] = $stages;
        $st['cols'] = $cols;
        return $st;
    }

    /** A stage list as the aligned signal array. @param list<Stage> $stages @return list<string> */
    public static function alignedStages(array $stages): array
    {
        $out = [];
        foreach (self::pipeline() as $s) {
            $out[] = in_array($s, $stages, true) ? $s->value : '';
        }
        return $out;
    }

    public function toJson(): string
    {
        return json_encode($this->toState(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** A named set when the stages equal one, else the comma list. */
    public function stageToken(): string
    {
        foreach (self::stageSetNames() as $name) {
            if (self::stageSet($name) === $this->stages) {
                return $name;
            }
        }
        $v = [];
        foreach ($this->stages as $s) {
            $v[] = $s->value;
        }
        return implode(',', $v);
    }

    public function sortToken(): string
    {
        $t = [];
        foreach ($this->sorts as $s) {
            $t[] = $s->token();
        }
        return implode(',', $t);
    }

    public function columnToken(): string
    {
        $t = [];
        foreach ($this->columns as $c) {
            $t[] = $c->value;
        }
        return implode(',', $t);
    }

    public function hasColumn(JobColumn $c): bool
    {
        return in_array($c, $this->columns, true);
    }

    public function hasStage(Stage $s): bool
    {
        return in_array($s, $this->stages, true);
    }

    /** The direction of $f in the sort, or null when it is not a key. 1 = primary, 2 = secondary. @return array{0:int,1:bool}|null */
    public function sortPosition(JobSortField $f): ?array
    {
        foreach ($this->sorts as $i => $s) {
            if ($s->field === $f) {
                return [$i + 1, $s->desc];
            }
        }
        return null;
    }

    /**
     * The sort token after clicking a header. Plain click: sort by $f only,
     * flipping the direction when $f is already primary. $add (shift-click):
     * keep the primary and make $f the secondary key (flipping it if it is one).
     */
    public function nextSort(JobSortField $f, bool $add): string
    {
        $primary = $this->sorts[0];
        if (!$add || $primary->field === $f) {
            $desc = $primary->field === $f ? !$primary->desc : false;
            $keys = [new JobSort($f, $desc)];
            if ($add && isset($this->sorts[1])) {
                $keys[] = $this->sorts[1];
            }
            return self::tokens($keys);
        }
        $second = $this->sorts[1] ?? null;
        $desc = $second !== null && $second->field === $f ? !$second->desc : false;
        return self::tokens([$primary, new JobSort($f, $desc)]);
    }

    /** Same query with other stages (board column toggles). @param list<Stage> $stages */
    public function withStages(array $stages): self
    {
        $s = self::ordered($stages);
        return new self($this->screen, $s === [] ? $this->stages : $s, $this->brandId, $this->campaignId, $this->owner, $this->assigneeId,
            $this->role, $this->due, $this->text, $this->sorts, $this->groupBy, $this->columns);
    }

    // ---- parsing ---------------------------------------------------------------

    /** @param array<string,string> $p raw strings (lists comma separated) */
    private static function build(string $screen, array $p): self
    {
        $def = self::defaults($screen);
        $stages = self::parseStages($p['stages'] ?? '');
        $sorts = [];
        foreach (explode(',', $p['sort'] ?? '') as $tok) {
            $s = JobSort::fromToken($tok);
            if ($s === null || count($sorts) >= self::MAX_SORTS) {
                continue;
            }
            $dup = false;
            foreach ($sorts as $have) {
                if ($have->field === $s->field) {
                    $dup = true;
                }
            }
            if (!$dup) {
                $sorts[] = $s;
            }
        }
        $cols = [];
        $wanted = [];
        foreach (explode(',', $p['cols'] ?? '') as $tok) {
            $c = JobColumn::tryFrom(trim($tok));
            if ($c !== null) {
                $wanted[] = $c;
            }
        }
        if ($wanted !== []) {
            foreach (JobColumn::cases() as $c) {
                if ($c->isRequired() || in_array($c, $wanted, true)) {
                    $cols[] = $c;
                }
            }
        }
        $role = Role::tryFrom(trim($p['role'] ?? ''));
        if ($role === Role::COO || $role === Role::ECD) {
            $role = null;
        }
        $brand = self::id($p['brand'] ?? '');
        return new self(
            $def->screen,
            $stages === [] ? $def->stages : $stages,
            $brand,
            self::id($p['campaign'] ?? ''),
            OwnerFilter::tryFrom(trim($p['owner'] ?? '')) ?? OwnerFilter::All,
            self::assignee($p['assignee'] ?? ''),
            $role,
            DueWindow::tryFrom(trim($p['due'] ?? '')),
            self::text($p['text'] ?? ''),
            $sorts === [] ? $def->sorts : $sorts,
            $screen === self::SCREEN_BOARD ? JobGroupBy::None : (JobGroupBy::tryFrom(trim($p['group'] ?? '')) ?? JobGroupBy::None),
            $cols === [] ? $def->columns : $cols,
        );
    }

    /** @return list<Stage> */
    private static function parseStages(string $raw): array
    {
        $raw = trim($raw);
        if (in_array($raw, self::stageSetNames(), true)) {
            return self::stageSet($raw);
        }
        $want = [];
        foreach (explode(',', $raw) as $tok) {
            $t = trim($tok);
            if (in_array($t, self::stageSetNames(), true)) {
                foreach (self::stageSet($t) as $s) {
                    $want[] = $s;
                }
                continue;
            }
            $s = Stage::tryFrom($t);
            if ($s !== null) {
                $want[] = $s;
            }
        }
        return self::ordered($want);
    }

    /** @param list<Stage> $stages @return list<Stage> distinct, pipeline order */
    private static function ordered(array $stages): array
    {
        $out = [];
        foreach (self::pipeline() as $s) {
            if (in_array($s, $stages, true)) {
                $out[] = $s;
            }
        }
        return $out;
    }

    /** @param list<JobSort> $keys */
    private static function tokens(array $keys): string
    {
        $t = [];
        foreach ($keys as $k) {
            $t[] = $k->token();
        }
        return implode(',', $t);
    }

    /** A brand, campaign or user id ('' when it does not look like one). */
    private static function id(string $v): string
    {
        $v = trim($v);
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $v) === 1 ? $v : '';
    }

    /** '' any, 'me', 'none' (slot empty; needs a role) or a user id. */
    private static function assignee(string $v): string
    {
        $v = trim($v);
        return $v === 'me' || $v === 'none' ? $v : self::id($v);
    }

    private static function text(string $v): string
    {
        $v = preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $v) ?? '';
        $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
        return mb_substr($v, 0, self::MAX_TEXT);
    }
}
