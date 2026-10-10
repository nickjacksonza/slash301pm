<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\BoardMoves;
use App\Domain\Dates;
use App\Domain\DueBucket;
use App\Domain\DueWindow;
use App\Domain\GridField;
use App\Domain\JobAction;
use App\Domain\JobColumn;
use App\Domain\JobGroupBy;
use App\Domain\JobQuery;
use App\Domain\OwnerFilter;
use App\Domain\Policy;
use App\Domain\PolicyRule;
use App\Domain\Role;
use App\Domain\SavedViews;
use App\Domain\Stage;
use App\Domain\Types\JobAccess;
use App\Domain\Types\JobGridRow;
use App\Domain\Types\JobSearchResult;
use App\Domain\Types\SavedView;
use App\Domain\Types\Team;
use App\Domain\Types\User;
use App\Http\Deps;
use App\View\ui\SelectOption;
use App\View\VM\BoardCardVM;
use App\View\VM\BoardColumnsVM;
use App\View\VM\BoardColumnVM;
use App\View\VM\GridGroupVM;
use App\View\VM\GridHeaderVM;
use App\View\VM\GridRowVM;
use App\View\VM\JobFiltersVM;
use App\View\VM\JobsBodyVM;
use App\View\VM\MoveOptionVM;
use App\View\VM\ViewItemVM;
use App\View\VM\ViewsMenuVM;
use DateTimeImmutable;

/**
 * Builds the grid, board and views-menu view models from a search result.
 * Every per-row decision (which cells are editable, hours and budget, which
 * moves a card offers) is a Policy call on the row's JobAccess, built from the
 * page's two queries. Go: helper funcs in package web.
 */
final class JobsView
{
    // ---- search ------------------------------------------------------------------

    public static function search(Deps $d, User $u, JobQuery $q): JobSearchResult
    {
        $today = Dates::today($d->clock->now());
        return $d->jobQuery->search($q, Policy::jobViewer($u), $today, Dates::addBusinessDays($today, 3));
    }

    /** One job as $u sees it, or null when it does not exist or is not visible to them. */
    public static function one(Deps $d, User $u, string $jobId): ?JobSearchResult
    {
        $today = Dates::today($d->clock->now());
        return $d->jobQuery->one($jobId, Policy::jobViewer($u), $today, Dates::addBusinessDays($today, 3));
    }

    /** /jobs?... or /jobs/board?... for history.replaceState. */
    public static function pageUrl(JobQuery $q): string
    {
        return url($q->screen === JobQuery::SCREEN_BOARD ? '/jobs/board' : '/jobs', $q->toQuery());
    }

    // ---- grid --------------------------------------------------------------------

    /** The query's columns minus the ones this role can never see. @return list<JobColumn> */
    public static function columns(JobQuery $q, User $u): array
    {
        $out = [];
        foreach ($q->columns as $c) {
            if ($c === JobColumn::Hours && Policy::rule('view_hours', $u->role) === PolicyRule::Deny) {
                continue;
            }
            if ($c === JobColumn::Budget && Policy::rule('view_budget', $u->role) === PolicyRule::Deny) {
                continue;
            }
            $out[] = $c;
        }
        return $out;
    }

    public static function body(JobSearchResult $r, JobQuery $q, User $u, DateTimeImmutable $now): JobsBodyVM
    {
        $columns = self::columns($q, $u);
        $headers = [];
        foreach ($columns as $c) {
            $f = $c->sortField();
            $pos = $f !== null ? $q->sortPosition($f) : null;
            $headers[] = new GridHeaderVM($c, $c->label(), $f !== null, $pos === null ? '' : ($pos[1] ? 'desc' : 'asc'), $pos === null ? 0 : $pos[0],
                $f !== null ? $q->nextSort($f, false) : '', $f !== null ? $q->nextSort($f, true) : '');
        }
        $keys = [];
        $labels = [];
        $rows = [];
        foreach ($r->rows as $row) {
            [$key, $label] = self::groupOf($q->groupBy, $row, $now);
            if (!isset($rows[$key])) {
                $keys[] = $key;
                $labels[$key] = $label;
                $rows[$key] = [];
            }
            $rows[$key][] = self::row($row, $r->team($row->id), $u, $now);
        }
        $groups = [];
        foreach ($keys as $k) {
            $groups[] = new GridGroupVM($k, $labels[$k], $rows[$k]);
        }
        return new JobsBodyVM($columns, $headers, $groups, $q->groupBy !== JobGroupBy::None, $r->total, count($r->rows), $r->cap,
            self::pageUrl($q), url('/jobs/rows'));
    }

    /** @return array{0:string,1:string} [safe key, label] */
    public static function groupOf(JobGroupBy $g, JobGridRow $row, DateTimeImmutable $now): array
    {
        $hash = static fn (string $v): string => 'g' . substr(sha1($v), 0, 10);
        return match ($g) {
            JobGroupBy::None => ['all', ''],
            JobGroupBy::Stage => ['g_' . $row->stage->value, $row->stage->label()],
            JobGroupBy::Brand => [$hash((string) $row->brandId), $row->brandName !== '' ? $row->brandName : 'No brand'],
            JobGroupBy::Campaign => [$hash((string) $row->campaignId), $row->campaignName !== '' ? ($row->brandName !== '' ? $row->brandName . ' · ' : '') . $row->campaignName : 'No campaign'],
            JobGroupBy::Am => [$hash((string) $row->amId), $row->amName !== '' ? $row->amName : 'No AM'],
            JobGroupBy::DueBucket => ['g_' . Dates::bucket($row->dueDate, $now)->value, Dates::bucket($row->dueDate, $now)->label()],
        };
    }

    public static function row(JobGridRow $row, Team $team, User $u, DateTimeImmutable $now): GridRowVM
    {
        $a = $row->access($team);
        $traffic = $team->holder(Role::Traffic);
        $editable = [];
        $fields = [
            JobColumn::Title->value => GridField::Title, JobColumn::Campaign->value => GridField::CampaignId, JobColumn::Due->value => GridField::DueDate,
            JobColumn::Hours->value => GridField::HoursEstimate, JobColumn::Am->value => GridField::Am, JobColumn::Traffic->value => GridField::Traffic,
        ];
        if ($row->stage === Stage::Waiting) {
            $fields[JobColumn::Stage->value] = GridField::WaitingReason;
        }
        $hours = Policy::canViewHours($u, $a)->allowed;
        foreach ($fields as $col => $f) {
            if ($f === GridField::HoursEstimate && !$hours) {
                continue;
            }
            if (Policy::canEditJobField($u, $a, $f)->allowed) {
                $editable[$col] = $f->value;
            }
        }
        $id = rawurlencode($row->id);
        return new GridRowVM(
            $row->id, $row->jobNumber, $row->title !== '' ? $row->title : '(untitled)', url('/jobs/' . $id . '/brief'), url('/jobs/' . $id . '/sheet'),
            $row->brandName, $row->campaignName, $row->stage, self::waitingText($row), $row->amName, $traffic !== null ? $traffic->userName : '',
            fmt_date($row->dueDate), self::dueBadge($row->dueDate, $row->stage, $now),
            $hours ? fmt_num($row->hoursEstimate) : null,
            Policy::canViewBudget($u, $a)->allowed ? fmt_zar($row->budget) : null,
            self::versionLabel($row), $row->workingCopy && $row->hasUnsentChanges, fmt_when($row->updatedAt), $editable, url('/jobs/' . $id . '/row'),
        );
    }

    public static function versionLabel(JobGridRow $row): string
    {
        return $row->briefSent ? $row->briefVersion->label() : 'draft';
    }

    public static function waitingText(JobGridRow $row): string
    {
        if ($row->stage !== Stage::Waiting) {
            return '';
        }
        $on = $row->waitingOn !== null ? 'on ' . strtolower($row->waitingOn->label()) : '';
        return trim($on . ($row->waitingReason !== '' ? ': ' . $row->waitingReason : ''));
    }

    /** 'overdue' or 'due_soon' (today to 3 business days) for open jobs; '' otherwise. */
    public static function dueBadge(?string $due, Stage $stage, DateTimeImmutable $now): string
    {
        if ($stage->isClosed()) {
            return '';
        }
        return match (Dates::bucket($due, $now)) {
            DueBucket::Overdue => 'overdue',
            DueBucket::Today, DueBucket::Next3BusinessDays => 'due_soon',
            default => '',
        };
    }

    // ---- board -------------------------------------------------------------------

    public static function board(JobSearchResult $r, JobQuery $q, User $u, DateTimeImmutable $now): BoardColumnsVM
    {
        $by = [];
        foreach ($r->rows as $row) {
            $by[$row->stage->value][] = $row;
        }
        $cols = [];
        // No Draft column for roles that never see drafts (Traffic, CD, makers, QA).
        $drafts = Policy::rule('view_brief_draft', $u->role) !== PolicyRule::Deny;
        foreach ($q->stages as $s) {
            if ($s === Stage::Draft && !$drafts) {
                continue;
            }
            $cols[] = self::column($s, $by[$s->value] ?? [], $r, $u, $now);
        }
        return new BoardColumnsVM($cols, $r->total, count($r->rows), $r->cap, self::pageUrl($q));
    }

    /** @param list<JobGridRow> $rows */
    public static function column(Stage $s, array $rows, JobSearchResult $r, User $u, DateTimeImmutable $now): BoardColumnVM
    {
        $cards = [];
        foreach ($rows as $row) {
            $cards[] = self::card($row, $r->team($row->id), $u, $now);
        }
        return new BoardColumnVM($s, $s === Stage::Waiting || $s === Stage::OnHold || $s === Stage::Cancelled, $cards);
    }

    public static function card(JobGridRow $row, Team $team, User $u, DateTimeImmutable $now): BoardCardVM
    {
        $id = rawurlencode($row->id);
        return new BoardCardVM(
            $row->id, $row->jobNumber, $row->title !== '' ? $row->title : '(untitled)', $row->brandName, fmt_date($row->dueDate),
            self::dueBadge($row->dueDate, $row->stage, $now), $row->amName, self::versionLabel($row), $row->workingCopy && $row->hasUnsentChanges,
            $row->stage, $row->rowVersion, self::waitingText($row), self::moves($u, $row->access($team), $row),
            url('/jobs/' . $id . '/brief'), url('/jobs/' . $id . '/sheet'), url('/jobs/' . $id . '/move'),
        );
    }

    /** @return list<MoveOptionVM> */
    public static function moves(User $u, JobAccess $a, JobGridRow $row): array
    {
        $out = [];
        foreach (BoardMoves::targets($u, $a, $row->resumeStage) as $t) {
            $label = match ($t->action) {
                JobAction::Send => 'Send to Traffic (in the brief)',
                JobAction::Resume => 'Resume (' . strtolower($t->to->label()) . ')',
                JobAction::Recall => 'Recall to draft',
                default => $t->action->label(),
            };
            // Social publishing: the Social stages are set on /social (a link, like Send).
            if (in_array($t->action, [JobAction::ReadyToSchedule, JobAction::Schedule, JobAction::GoLive], true)) {
                $out[] = new MoveOptionVM($t->to->value, $t->action->label() . ' (in Social)', false, false, true, url('/social/jobs/' . rawurlencode($row->id)));
                continue;
            }
            $out[] = new MoveOptionVM($t->to->value, $label, $t->needsReason, $t->needsWaitingOn, $t->viaBrief,
                $t->viaBrief ? url('/jobs/' . rawurlencode($row->id) . '/brief') : '');
        }
        return $out;
    }

    // ---- filters and views --------------------------------------------------------

    public static function filters(string $screen, Deps $d): JobFiltersVM
    {
        $board = $screen === JobQuery::SCREEN_BOARD;
        $stages = [];
        foreach (JobQuery::pipeline() as $s) {
            $stages[] = new SelectOption($s->value, $s->label());
        }
        $sets = [];
        foreach ($board ? ['board', 'open', 'all'] : ['open', 'active', 'closed', 'all'] as $name) {
            $sets[$name === 'board' ? 'default' : $name] = JobQuery::alignedStages(JobQuery::stageSet($name));
        }
        $brands = [new SelectOption('', 'All brands')];
        foreach ($d->brands->list() as $b) {
            $brands[] = new SelectOption($b->id, $b->name);
        }
        $campaigns = [new SelectOption('', 'All campaigns')];
        foreach ($d->campaigns->listAll() as $c) {
            $campaigns[] = new SelectOption($c->id, $c->name, false, $c->brandName);
        }
        $owners = [];
        foreach (OwnerFilter::cases() as $o) {
            $owners[] = new SelectOption($o->value, $o->label());
        }
        $due = [new SelectOption('', 'Any date')];
        foreach (DueWindow::cases() as $w) {
            $due[] = new SelectOption($w->value, $w->label());
        }
        $people = [new SelectOption('', 'Anyone'), new SelectOption('me', 'Me'), new SelectOption('none', 'Nobody (slot empty)')];
        foreach ($d->users->listActive() as $p) {
            if ($p->role !== Role::Client) {
                $people[] = new SelectOption($p->id, $p->name . ' (' . $p->role->value . ')');
            }
        }
        $roles = [new SelectOption('', 'Any slot')];
        foreach ([Role::AM, Role::Traffic, Role::PM, Role::Producer, Role::CD, Role::Copywriter, Role::Designer, Role::Developer, Role::SEO, Role::Social, Role::QA, Role::Client] as $r) {
            $roles[] = new SelectOption($r->value, $r->value);
        }
        $groups = [];
        foreach (JobGroupBy::cases() as $g) {
            $groups[] = new SelectOption($g->value, $g->label());
        }
        $cols = [];
        foreach (JobColumn::cases() as $c) {
            $cols[] = new SelectOption($c->value, $c->label(), $c->isRequired());
        }
        return new JobFiltersVM($screen, url($board ? '/jobs/board/columns' : '/jobs/rows'), url($board ? '/jobs/board' : '/jobs', ['view' => 'none']),
            $stages, $sets, $brands, $campaigns, $owners, $due, $people, $roles, $groups, $cols);
    }

    /** @param list<SavedView> $saved the user's own and shared views */
    public static function views(string $screen, User $u, array $saved, string $activeId, string $activeName): ViewsMenuVM
    {
        $base = $screen === JobQuery::SCREEN_BOARD ? '/jobs/board' : '/jobs';
        $item = static fn (SavedView $v, bool $manage): ViewItemVM => new ViewItemVM(
            $v->id, $v->name, url($base, ['view' => $v->id]), $v->builtin, $v->isShared, $v->isDefault, $manage, $v->ownerName,
            $v->builtin ? '' : url('/views/' . rawurlencode($v->id)), $v->id === $activeId,
        );
        $builtins = [];
        // "My jobs" and "Unowned" are about the AM slot: only for roles that own jobs.
        $owns = Policy::rule('create_brief', $u->role) !== PolicyRule::Deny;
        foreach (SavedViews::builtins($screen) as $v) {
            if (!$owns && ($v->id === 'builtin:mine' || $v->id === 'builtin:unowned')) {
                continue;
            }
            $builtins[] = $item($v, false);
        }
        $mine = [];
        $shared = [];
        foreach ($saved as $v) {
            if ($v->ownerId === $u->id) {
                $mine[] = $item($v, true);
            } else {
                $shared[] = $item($v, Policy::canManageView($u, $v)->allowed);
            }
        }
        return new ViewsMenuVM($screen, $builtins, $mine, $shared, $activeName !== '' ? $activeName : 'Custom', Policy::canShareView($u)->allowed, url('/views'));
    }
}
