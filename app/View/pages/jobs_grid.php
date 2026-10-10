<?php
declare(strict_types=1);

use App\Domain\JobColumn;
use App\View\ui\BadgeProps;
use App\View\ui\PartProps;
use App\View\ui\TableHeaderProps;
use App\View\ui\TableProps;
use App\View\ui\TableRowProps;
use App\View\VM\GridCellEditorVM;
use App\View\VM\GridHeaderVM;
use App\View\VM\GridRowVM;
use App\View\VM\JobsBodyVM;
use App\View\VM\JobsPageVM;

/**
 * GET /jobs: the job grid. Filters are q.* signals; any change fetches
 * GET /jobs/rows (250 ms debounce on search) which patches #jobs-body, whose
 * data-url public/js/jobs.js mirrors into the address bar. Cells: click (or
 * Enter, see public/js/grid-keys.js) fetches an editor cell; Enter or blur
 * PATCHes edit.*; the answer is the row (outer patch by id) plus a toast.
 */
function page_jobs_grid(JobsPageVM $vm): string
{
    ob_start(); ?>
<div id="jobs-page" class="flex flex-col gap-4" data-signals="<?= jobs_page_signals($vm->state, 'jobs') ?>">
  <?= partial_jobs_toolbar($vm) ?>
  <?= partial_jobs_filters($vm->filters) ?>
  <?= $vm->body !== null ? partial_jobs_body($vm->body) : '' ?>
</div>
<?= partial_save_view_dialog($vm->views) ?>
<?= partial_move_dialog() ?>
<script type="module" data-cfasync="false" src="<?= attr(asset('js/jobs.js')) ?>"></script>
<script type="module" data-cfasync="false" src="<?= attr(asset('js/grid-keys.js')) ?>"></script>
<?php
    return (string) ob_get_clean();
}

/** Grid / Board switch and the views menu. */
function partial_jobs_toolbar(JobsPageVM $vm): string
{
    $tab = static fn (string $label, string $href, bool $on): string => '<a href="' . attr($href) . '" class="'
        . ($on ? 'rounded-md bg-background px-3 py-1 text-sm font-medium shadow-xs' : 'rounded-md px-3 py-1 text-sm text-muted-foreground hover:text-foreground')
        . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    return '<div class="flex flex-wrap items-center justify-between gap-3">'
        . '<nav aria-label="Job views" class="inline-flex rounded-lg bg-muted p-1">' . $tab('Grid', $vm->gridUrl, $vm->screen === 'jobs') . $tab('Board', $vm->boardUrl, $vm->screen === 'board') . '</nav>'
        . partial_views_menu($vm->views) . '</div>';
}

/** #jobs-body: count line and the table. Patched whole by GET /jobs/rows. */
function partial_jobs_body(JobsBodyVM $vm): string
{
    $collapsed = [];
    foreach ($vm->groups as $g) {
        if ($vm->grouped) {
            $collapsed[$g->key] = false;
        }
    }
    $head = '';
    foreach ($vm->headers as $h) {
        $head .= partial_grid_head($h, $vm->rowsUrl);
    }
    $bodies = '';
    $span = count($vm->columns);
    foreach ($vm->groups as $g) {
        if ($vm->grouped) {
            $sig = '$_collapsed.' . $g->key;
            $bodies .= ui_table_body(new PartProps(attrs: ['id' => 'grp-h-' . $g->key]),
                '<tr class="bg-muted/60"><td colspan="' . $span . '" class="!px-2 !py-1">'
                . '<button type="button" class="flex w-full items-center gap-2 text-left text-xs font-semibold" data-on:click="' . attr($sig . ' = !' . $sig) . '"'
                . ' data-attr:aria-expanded="' . attr('!' . $sig) . '">'
                . '<span aria-hidden="true" class="inline-block w-3 text-muted-foreground" data-text="' . attr($sig . " ? '+' : '-'") . '">-</span>'
                . e($g->label) . ' <span class="font-normal text-muted-foreground">' . count($g->rows) . '</span></button></td></tr>');
        }
        $rows = '';
        foreach ($g->rows as $row) {
            $rows .= partial_job_row($row, $vm->columns);
        }
        $attrs = ['id' => 'grp-' . $g->key];
        if ($vm->grouped) {
            $attrs['data-show'] = '!$_collapsed.' . $g->key;
        }
        $bodies .= ui_table_body(new PartProps(attrs: $attrs), $rows);
    }
    if ($vm->shown === 0) {
        $bodies = ui_table_body(new PartProps(attrs: ['id' => 'grp-empty']),
            '<tr><td colspan="' . $span . '" class="!py-10 text-center text-sm text-muted-foreground">No jobs match these filters.</td></tr>');
    }
    $count = $vm->total === 1 ? '1 job' : $vm->total . ' jobs';
    if ($vm->total > $vm->shown) {
        $count .= ', showing the first ' . $vm->shown . '. Narrow the filters to see the rest.';
    }
    $signals = $collapsed === [] ? '' : ' data-signals__ifmissing="' . js(['_collapsed' => $collapsed]) . '"';
    return '<div id="jobs-body" class="flex flex-col gap-2" data-url="' . attr($vm->url) . '"' . $signals . '>'
        . '<p id="jobs-count" class="text-sm text-muted-foreground" aria-live="polite">' . e($count) . '</p>'
        . ui_table(new TableProps(density: 'compact', containerClass: 'max-h-[calc(100vh-16rem)] min-h-48 overflow-auto rounded-md border border-border',
            attrs: ['data-grid' => true, 'aria-label' => 'Jobs', 'aria-rowcount' => (string) ($vm->shown + 1)]),
            ui_table_header(new TableHeaderProps(sticky: true), '<tr>' . $head . '</tr>') . $bodies)
        . '</div>';
}

function partial_grid_head(GridHeaderVM $h, string $rowsUrl): string
{
    $wide = $h->column === JobColumn::Title ? 'min-w-64' : '';
    if (!$h->sortable) {
        return ui_table_head(new PartProps(class: $wide), e($h->label));
    }
    $fetch = act_raw('get', $rowsUrl, 'filterSignals: {include: /^q\\./}');
    $click = '$q.sort = evt.shiftKey ? ' . jobs_js($h->nextSortAdd) . ' : ' . jobs_js($h->nextSort) . '; ' . $fetch;
    $arrow = $h->dir === 'asc' ? '&uarr;' : ($h->dir === 'desc' ? '&darr;' : '');
    $mark = $arrow !== '' ? '<span aria-hidden="true" class="text-primary">' . $arrow . ($h->pos === 2 ? '<sup>2</sup>' : '') . '</span>' : '';
    $attrs = $h->dir !== '' && $h->pos === 1 ? ['aria-sort' => $h->dir === 'asc' ? 'ascending' : 'descending'] : [];
    return ui_table_head(new PartProps(class: $wide, attrs: $attrs),
        '<button type="button" class="inline-flex items-center gap-1 hover:underline" title="Sort (shift-click adds a second key)" data-on:click="' . attr($click) . '">'
        . e($h->label) . $mark . '</button>');
}

/** One row (also the answer to an edit, a cancel and a move from the sheet). @param list<JobColumn> $columns */
function partial_job_row(GridRowVM $r, array $columns): string
{
    $cells = '';
    foreach ($columns as $c) {
        $cells .= partial_job_cell($r, $c);
    }
    return ui_table_row(new TableRowProps(attrs: ['id' => 'row-' . $r->id, 'data-row' => $r->id]), $cells);
}

function partial_job_cell(GridRowVM $r, JobColumn $c): string
{
    $html = match ($c) {
        JobColumn::JobNumber => '<button type="button" class="font-mono text-xs text-primary underline-offset-2 hover:underline" title="Open the job sheet"'
            . ' data-on:click="' . attr(act_raw('get', $r->sheetUrl, 'filterSignals: {include: /^q\\.cols/}')) . '">' . e($r->jobNumber) . '</button>',
        JobColumn::Title => '<a href="' . attr($r->briefUrl) . '" class="hover:underline">' . e($r->title) . '</a>'
            . ($r->unsent ? ' <span class="ml-1 inline-block size-1.5 rounded-full bg-primary align-middle" title="Unsent changes"></span>' : ''),
        JobColumn::Brand => e($r->brandName),
        JobColumn::Campaign => e($r->campaignName),
        JobColumn::Stage => ui_badge(new BadgeProps(stage: $r->stage->value), e($r->stage->label()))
            . ($r->waitingText !== '' ? ' <span class="ml-1 text-xs text-muted-foreground">' . e($r->waitingText) . '</span>' : ''),
        JobColumn::Am => $r->amName !== '' ? '<span class="inline-flex items-center gap-1.5">' . jobs_avatar($r->amName) . e($r->amName) . '</span>' : '<span class="text-muted-foreground">-</span>',
        JobColumn::Traffic => $r->trafficName !== '' ? e($r->trafficName) : '<span class="text-muted-foreground">-</span>',
        JobColumn::Due => jobs_due($r->dueText, $r->dueBadge),
        JobColumn::Hours => $r->hoursText === null ? '<span class="text-muted-foreground" title="Hidden">-</span>' : e($r->hoursText),
        JobColumn::Budget => $r->budgetText === null ? '<span class="text-muted-foreground" title="Hidden">-</span>' : e($r->budgetText),
        JobColumn::Version => e($r->versionLabel),
        JobColumn::Updated => '<span class="text-muted-foreground">' . e($r->updatedText) . '</span>',
    };
    $attrs = ['id' => 'cell-' . $r->id . '-' . $c->value, 'data-cell' => $c->value, 'tabindex' => '-1'];
    $class = match ($c) {
        JobColumn::Title => 'max-w-[26rem] truncate',
        JobColumn::Campaign, JobColumn::Brand => 'max-w-48 truncate',
        JobColumn::Stage => 'max-w-72 truncate',
        JobColumn::Hours, JobColumn::Budget => 'text-right tabular-nums',
        default => '',
    };
    $field = $r->editable[$c->value] ?? '';
    if ($field !== '') {
        $edit = url('/jobs/' . rawurlencode($r->id) . '/cells/' . $field . '/edit');
        $attrs['data-edit'] = $field;
        $attrs['title'] = 'Click or press Enter to edit';
        $attrs['data-on:click'] = "evt.target.closest('a, button') ? null : " . act_raw('get', $edit, 'filterSignals: {include: /^q\\.cols/}');
        $class = cx($class, 'cursor-cell hover:bg-accent/60');
    }
    return ui_table_cell(new PartProps(class: $class, attrs: $attrs), $html);
}

/** The editor that replaces one cell (same id). Enter or blur saves, Escape cancels. */
function partial_grid_cell_editor(GridCellEditorVM $vm): string
{
    $save = act_raw('patch', $vm->saveUrl, 'filterSignals: {include: /^(edit|q)\\./}, retryMaxCount: 0');
    $cancel = act_raw('get', $vm->cancelUrl, 'filterSignals: {include: /^q\\.cols/}');
    $keys = "evt.key === 'Enter' ? (evt.preventDefault(), el.dataset.done = '1', " . $save . ') : '
        . "(evt.key === 'Escape' ? (evt.preventDefault(), evt.stopPropagation(), el.dataset.done = '1', " . $cancel . ') : null)';
    $input = 'h-7 w-full min-w-28 rounded-sm border border-ring bg-background px-2 text-[13px] text-foreground outline-none ring-2 ring-ring/40 dark:bg-input/30';
    $signals = js(['edit' => ['value' => $vm->value, 'row_version' => $vm->rowVersion, 'brief_rv' => $vm->briefRowVersion, 'waiting_on' => $vm->waitingOn]]);
    $label = ' aria-label="' . attr($vm->label) . '"';
    if ($vm->kind === 'select') {
        $opts = '';
        foreach ($vm->options as $o) {
            $opts .= '<option value="' . attr($o->value) . '"' . ($o->value === $vm->value ? ' selected' : '') . '>' . e($o->label) . '</option>';
        }
        $control = '<select class="' . $input . '"' . $label . ' data-bind="edit.value" data-init="el.focus()"'
            . ' data-on:change="' . attr("el.dataset.done = '1'; " . $save) . '"'
            . ' data-on:keydown="' . attr("evt.key === 'Escape' ? (evt.preventDefault(), evt.stopPropagation(), el.dataset.done = '1', " . $cancel . ') : null') . '"'
            . ' data-on:blur="' . attr("el.dataset.done ? null : (el.dataset.done = '1', " . $cancel . ')') . '">' . $opts . '</select>';
    } elseif ($vm->kind === 'waiting') {
        $opts = '';
        foreach ($vm->options as $o) {
            $opts .= '<option value="' . attr($o->value) . '"' . ($o->value === $vm->waitingOn ? ' selected' : '') . '>' . e($o->label) . '</option>';
        }
        $control = '<div class="flex items-center gap-1" data-on:keydown="' . attr($keys) . '">'
            . '<select class="' . $input . ' w-36" aria-label="Waiting on" data-bind="edit.waiting_on">' . $opts . '</select>'
            . '<input type="text" maxlength="1000" class="' . $input . ' min-w-48"' . $label . ' data-bind="edit.value" data-init="el.focus(); el.select()">'
            . '<button type="button" class="rounded-sm border border-border px-2 text-xs hover:bg-accent" data-on:click="' . attr($save) . '">Save</button></div>';
    } else {
        $type = match ($vm->kind) {
            'date' => 'date',
            'number' => 'number',
            default => 'text',
        };
        $extra = $vm->kind === 'number' ? ' min="0" step="0.5" inputmode="decimal"' : ($vm->kind === 'text' ? ' maxlength="200"' : '');
        $control = '<input type="' . $type . '" class="' . $input . '"' . $label . $extra . ' data-bind="edit.value" data-init="el.focus(); el.select && el.type === \'text\' && el.select()"'
            . ' data-on:keydown="' . attr($keys) . '"'
            . ' data-on:blur="' . attr("el.dataset.done ? null : (el.dataset.done = '1', " . $save . ')') . '">';
    }
    return '<td id="' . attr($vm->cellId) . '" data-slot="table-cell" data-cell="editing" data-editing="' . attr($vm->field) . '" class="!p-0.5 align-middle" data-signals="' . $signals . '">'
        . $control . '</td>';
}
