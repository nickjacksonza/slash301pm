<?php
declare(strict_types=1);

use App\Domain\JobQuery;
use App\View\ui\BadgeProps;
use App\View\ui\DropdownContentProps;
use App\View\ui\DropdownItemProps;
use App\View\ui\DropdownLabelProps;
use App\View\ui\DropdownLinkItemProps;
use App\View\ui\DropdownProps;
use App\View\ui\DropdownTriggerProps;
use App\View\VM\BoardCardVM;
use App\View\VM\BoardColumnsVM;
use App\View\VM\BoardColumnVM;
use App\View\VM\JobsPageVM;

/**
 * GET /jobs/board: Kanban by stage. Native drag and drop: dragstart fills
 * move.*, a column's drop sets move.to and either opens the reason dialog
 * (waiting, on hold, cancelled) or moves the card on screen (s301Jobs.place)
 * and POSTs /jobs/{id}/move. The answer re-renders the affected columns from
 * the database, so a refused move snaps back. Every card also has a
 * "Move to..." menu listing only the moves Policy allows (touch, keyboard).
 */
function page_jobs_board(JobsPageVM $vm): string
{
    $fetch = act_raw('get', url('/jobs/board/columns'), 'filterSignals: {include: /^q\\./}');
    // q.stages is aligned to JobQuery::pipeline() (one slot per stage checkbox, '' when off), so a toggle flips slots by index.
    $pipe = [];
    foreach (JobQuery::pipeline() as $st) {
        $pipe[] = $st->value;
    }
    $toggle = static function (string $label, array $stages) use ($pipe, $fetch): string {
        $idx = [];
        foreach ($stages as $v) {
            $idx[] = (int) array_search($v, $pipe, true);
        }
        $on = '$q.stages[' . $idx[0] . "] !== ''";
        return '<button type="button" class="rounded-md border border-border px-3 py-1 text-sm hover:bg-accent hover:text-accent-foreground"'
            . ' data-class="' . attr("{'bg-accent': " . $on . '}') . '" data-attr:aria-pressed="' . attr($on) . '"'
            . ' data-on:click="' . attr('$q.stages = ((on) => $q.stages.map((v, i) => ' . jobs_js($idx) . ".includes(i) ? (on ? '' : " . jobs_js($pipe) . '[i]) : v))(' . $on . '); ' . $fetch) . '">'
            . e($label) . '</button>';
    };
    ob_start(); ?>
<div id="board-page" class="flex flex-col gap-4" data-signals="<?= jobs_page_signals($vm->state, 'board') ?>">
  <?= partial_jobs_toolbar($vm) ?>
  <?= partial_jobs_filters($vm->filters) ?>
  <div class="flex flex-wrap items-center gap-2 text-sm">
    <span class="text-muted-foreground">Columns:</span>
    <?= $toggle('Paused', ['waiting', 'on_hold']) ?>
    <?= $toggle('Closed', ['done', 'archived', 'cancelled']) ?>
    <?= $toggle('Social', ['ready_to_schedule', 'scheduled', 'live']) ?>
  </div>
  <?= $vm->board !== null ? partial_board_columns($vm->board) : '' ?>
</div>
<?= partial_save_view_dialog($vm->views) ?>
<?= partial_move_dialog() ?>
<script type="module" data-cfasync="false" src="<?= attr(asset('js/jobs.js')) ?>"></script>
<?php
    return (string) ob_get_clean();
}

/** #board-columns: count line and every visible column (GET /jobs/board/columns patches it whole). */
function partial_board_columns(BoardColumnsVM $vm): string
{
    $count = $vm->total === 1 ? '1 job' : $vm->total . ' jobs';
    if ($vm->total > $vm->shown) {
        $count .= ', showing the first ' . $vm->shown . '. Narrow the filters to see the rest.';
    }
    $cols = '';
    foreach ($vm->columns as $c) {
        $cols .= partial_board_column($c);
    }
    return '<div id="board-columns" class="flex flex-col gap-2" data-url="' . attr($vm->url) . '">'
        . '<p id="board-count" class="text-sm text-muted-foreground" aria-live="polite">' . e($count) . '</p>'
        . '<div class="flex min-h-[60vh] gap-3 overflow-x-auto pb-4">' . $cols . '</div></div>';
}

/** One stage column; also the answer to a move (outer patch by id). */
function partial_board_column(BoardColumnVM $c): string
{
    $s = $c->stage->value;
    $set = '$move.to = ' . jobs_js($s) . '; ';
    $drop = $c->needsReason
        ? $set . "\$move.reason = ''; \$move.waiting_on = ''; \$move_dialog.open = true"
        : $set . 'window.s301Jobs && s301Jobs.place($move.job_id, $move.to); ' . jobs_move_post();
    $cards = '';
    foreach ($c->cards as $card) {
        $cards .= partial_board_card($card);
    }
    if ($cards === '') {
        $cards = '<p class="px-2 py-6 text-center text-xs text-muted-foreground" data-empty>Drop a job here</p>';
    }
    return '<section id="col-' . attr($s) . '" data-col="' . attr($s) . '" aria-label="' . attr($c->stage->label()) . '"'
        . ' class="flex w-64 shrink-0 flex-col rounded-lg border border-border bg-muted/40"'
        . ' data-on:dragover__prevent="1"'
        . ' data-on:dragenter="' . attr("el.classList.add('ring-2', 'ring-ring')") . '"'
        . ' data-on:dragleave="' . attr("el.contains(evt.relatedTarget) || el.classList.remove('ring-2', 'ring-ring')") . '"'
        . ' data-on:drop__prevent="' . attr("el.classList.remove('ring-2', 'ring-ring'); if (\$move.job_id && \$move.from !== " . jobs_js($s) . ') { ' . $drop . ' }') . '">'
        . '<header id="colh-' . attr($s) . '" class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">'
        . ui_badge(new BadgeProps(stage: $s), e($c->stage->label()))
        . '<span class="text-xs text-muted-foreground tabular-nums" data-col-count>' . count($c->cards) . '</span></header>'
        . '<div class="flex min-h-24 flex-1 flex-col gap-2 overflow-y-auto p-2" data-cards="' . attr($s) . '">' . $cards . '</div>'
        . '</section>';
}

function partial_board_card(BoardCardVM $c): string
{
    $start = '$move.job_id = ' . jobs_js($c->id) . '; $move.from = ' . jobs_js($c->stage->value) . '; $move.row_version = ' . $c->rowVersion
        . "; \$move.sheet = false; evt.dataTransfer.effectAllowed = 'move'; evt.dataTransfer.setData('text/plain', " . jobs_js($c->id) . "); el.classList.add('opacity-50')";
    $menuId = 'mv-' . $c->id;
    $items = '';
    foreach ($c->moves as $m) {
        if ($m->viaBrief) {
            $items .= ui_dropdown_link_item(new DropdownLinkItemProps(id: $menuId, href: $m->href), e($m->label));
            continue;
        }
        $items .= ui_dropdown_item(new DropdownItemProps(id: $menuId, variant: $m->to === 'cancelled' ? 'destructive' : '',
            onClick: jobs_move_expr($c->id, $c->stage->value, $c->rowVersion, $m, false), attrs: ['data-on:keydown' => "evt.key === 'Enter' && el.click()", 'data-move-to' => $m->to]), e($m->label));
    }
    $menu = $c->moves === [] ? '' : ui_dropdown(new DropdownProps(id: $menuId),
        ui_dropdown_trigger(new DropdownTriggerProps(id: $menuId, class: 'rounded-sm px-1.5 text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground', attrs: ['aria-label' => 'Move ' . $c->jobNumber . ' to...']), 'Move')
        . ui_dropdown_content(new DropdownContentProps(id: $menuId, align: 'end', class: 'w-56'),
            ui_dropdown_label(new DropdownLabelProps(class: 'text-xs text-muted-foreground'), 'Move to...') . $items));
    return '<article id="card-' . attr($c->id) . '" data-card="' . attr($c->id) . '" data-stage="' . attr($c->stage->value) . '" draggable="true"'
        . ' class="flex cursor-grab flex-col gap-2 rounded-md border border-border bg-card p-3 text-sm text-card-foreground shadow-xs active:cursor-grabbing"'
        . ' data-on:dragstart="' . attr($start) . '" data-on:dragend="' . attr("el.classList.remove('opacity-50')") . '">'
        . '<div class="flex items-center justify-between gap-2">'
        . '<button type="button" class="font-mono text-xs text-primary underline-offset-2 hover:underline" title="Open the job sheet" data-on:click="'
        . attr(act_raw('get', $c->sheetUrl, 'filterSignals: {include: /^q\\.cols/}')) . '">' . e($c->jobNumber) . '</button>' . $menu . '</div>'
        . '<a href="' . attr($c->briefUrl) . '" class="leading-snug font-medium hover:underline">' . e($c->title) . '</a>'
        . ($c->waitingText !== '' ? '<p class="text-xs text-muted-foreground">' . e($c->waitingText) . '</p>' : '')
        . '<div class="flex items-center justify-between gap-2 text-xs text-muted-foreground"><span class="truncate">' . e($c->brandName) . '</span>'
        . '<span>' . e($c->versionLabel) . ($c->unsent ? ' <span class="inline-block size-1.5 rounded-full bg-primary align-middle" title="Unsent changes"></span>' : '') . '</span></div>'
        . '<div class="flex items-center justify-between gap-2"><span class="text-xs">' . jobs_due($c->dueText, $c->dueBadge) . '</span>' . jobs_avatar($c->amName) . '</div>'
        . '</article>';
}
