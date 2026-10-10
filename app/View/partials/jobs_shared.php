<?php
declare(strict_types=1);

use App\View\ui\AvatarProps;
use App\View\ui\BadgeProps;
use App\View\ui\ButtonProps;
use App\View\ui\DialogCloseProps;
use App\View\ui\DialogProps;
use App\View\ui\NativeSelectProps;
use App\View\ui\PartProps;
use App\View\ui\SelectOption;
use App\View\ui\SheetContentProps;
use App\View\ui\SheetProps;
use App\View\ui\TextareaProps;
use App\Domain\WaitingOn;
use App\View\VM\JobFiltersVM;
use App\View\VM\JobSheetVM;
use App\View\VM\MoveOptionVM;
use App\View\VM\ViewsMenuVM;

/*
 * Pieces shared by the job grid (/jobs) and the board (/jobs/board).
 * Signals (declared once on the page root by jobs_page_signals()):
 *   q.*     the JobQuery state (filters, sort, group, cols); sent with /jobs/rows and /jobs/board/columns
 *   move.*  a stage move: job_id, from, to, row_version, reason, waiting_on, page (jobs|board), sheet (bool)
 *   edit.*  the open grid cell editor (declared by the editor cell itself)
 *   view.*  the save-view dialog; vedit.op one action on a saved view
 *   _collapsed.<group>  grid group collapse (UI only)
 * Every user value reaches an expression through jobs_js() and then attr().
 */

/** A raw JS literal (JSON with the HEX flags). Wrap the whole expression in attr() when it goes into an attribute. */
function jobs_js(mixed $v): string
{
    return json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

/** The page-root signals of both screens. @param array<string,mixed> $state */
function jobs_page_signals(array $state, string $page): string
{
    return js([
        'q' => $state,
        'move' => ['job_id' => '', 'from' => '', 'to' => '', 'row_version' => 0, 'reason' => '', 'waiting_on' => '', 'page' => $page, 'sheet' => false],
        'edit' => ['value' => '', 'row_version' => 0, 'brief_rv' => 0, 'waiting_on' => ''],
        'view' => ['name' => '', 'shared' => false, 'default' => false, 'screen' => $page],
        'vedit' => ['op' => '', 'name' => ''],
    ]);
}

/** POST /jobs/{$move.job_id}/move with the move and the current filters (raw JS). */
function jobs_move_post(): string
{
    return '@post(' . jobs_js(url('/jobs/')) . " + \$move.job_id + '/move', {headers: {'X-CSRF-Token': \$_csrf}, filterSignals: {include: /^(move|q)\\./}, retryMaxCount: 0})";
}

/**
 * Start a move from a menu (card "Move to..." or the sheet): set move.*, then
 * open the reason dialog or move the card on screen and post (raw JS).
 */
function jobs_move_expr(string $jobId, string $from, int $rowVersion, MoveOptionVM $m, bool $fromSheet): string
{
    $set = '$move.job_id = ' . jobs_js($jobId) . '; $move.from = ' . jobs_js($from) . '; $move.row_version = ' . $rowVersion
        . '; $move.to = ' . jobs_js($m->to) . '; $move.sheet = ' . ($fromSheet ? 'true' : 'false') . '; ';
    if ($m->needsReason) {
        return $set . "\$move.reason = ''; \$move.waiting_on = ''; \$move_dialog.open = true";
    }
    return $set . 'window.s301Jobs && s301Jobs.place($move.job_id, $move.to); ' . jobs_move_post();
}

/** Grid due badge or plain date. */
function jobs_due(string $text, string $badge): string
{
    if ($text === '') {
        return '<span class="text-muted-foreground">-</span>';
    }
    if ($badge === 'overdue' || $badge === 'due_soon') {
        return ui_badge(new BadgeProps(stage: $badge, attrs: ['title' => $badge === 'overdue' ? 'Overdue' : 'Due soon']), e($text));
    }
    return e($text);
}

function jobs_avatar(string $name): string
{
    if ($name === '') {
        return '';
    }
    return ui_avatar(new AvatarProps(name: $name, class: 'size-6 text-[10px] font-medium', attrs: ['title' => $name, 'aria-hidden' => 'true']));
}

/** The filter bar: search, stages, brand, campaign, owner, due, assignee + slot; grid only: group and columns. */
function partial_jobs_filters(JobFiltersVM $vm): string
{
    $fetch = act_raw('get', $vm->fetchUrl, 'filterSignals: {include: /^q\\./}');
    $grid = $vm->screen === 'jobs';
    $select = static fn (string $id, string $label, string $bind, array $options, string $extra = ''): string =>
        '<label class="flex min-w-36 flex-col gap-1 text-xs font-medium text-muted-foreground" for="' . attr($id) . '">' . e($label)
        . ui_native_select(new NativeSelectProps(id: $id, options: $options, attrs: ['data-bind' => $bind, 'data-on:change' => $extra . $fetch]))
        . '</label>';
    $menu = 'absolute left-0 z-40 mt-1 w-64 rounded-md border border-border bg-popover p-2 text-popover-foreground shadow-md';
    $summary = 'inline-flex h-9 cursor-pointer list-none items-center gap-2 rounded-md border border-input px-3 text-sm shadow-xs hover:bg-accent hover:text-accent-foreground';
    ob_start(); ?>
<div id="jobs-filters" class="flex flex-wrap items-end gap-3" role="search" aria-label="Filter jobs">
  <label class="flex min-w-56 flex-1 flex-col gap-1 text-xs font-medium text-muted-foreground" for="jf-text">Search
    <input id="jf-text" type="search" placeholder="Job number, title, campaign or brand" autocomplete="off"
           class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm text-foreground shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
           data-bind="q.text" data-on:input__debounce.250ms="<?= attr($fetch) ?>">
  </label>
  <details class="relative" data-filter-menu>
    <summary class="<?= attr($summary) ?>">Stages <span class="rounded-full bg-muted px-1.5 text-xs text-muted-foreground" data-text="$q.stages.filter(s => s).length"></span></summary>
    <div class="<?= attr($menu) ?>">
      <div class="mb-2 flex flex-wrap gap-1">
        <?php foreach ($vm->stageSets as $name => $stages): ?>
          <button type="button" class="rounded-md border border-border px-2 py-0.5 text-xs hover:bg-accent hover:text-accent-foreground"
                  data-on:click="<?= attr('$q.stages = ' . jobs_js($stages) . '; ' . $fetch) ?>"><?= e(ucfirst($name)) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="grid grid-cols-1 gap-1">
        <?php foreach ($vm->stageOptions as $o): ?>
          <label class="flex items-center gap-2 rounded-sm px-1 py-0.5 text-sm hover:bg-accent">
            <input type="checkbox" class="accent-primary" value="<?= attr($o->value) ?>" data-bind="q.stages" data-on:change="<?= attr($fetch) ?>"> <?= e($o->label) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </details>
  <?= $select('jf-owner', 'Owner', 'q.owner', $vm->ownerOptions) ?>
  <?= $select('jf-brand', 'Brand', 'q.brand', $vm->brandOptions, "\$q.campaign = ''; ") ?>
  <?= $select('jf-campaign', 'Campaign', 'q.campaign', $vm->campaignOptions) ?>
  <?= $select('jf-due', 'Due', 'q.due', $vm->dueOptions) ?>
  <?= $select('jf-assignee', 'Assigned to', 'q.assignee', $vm->assigneeOptions) ?>
  <?= $select('jf-role', 'As', 'q.role', $vm->roleOptions) ?>
  <?php if ($grid): ?>
    <?= $select('jf-group', 'Group by', 'q.group', $vm->groupOptions) ?>
    <details class="relative" data-filter-menu>
      <summary class="<?= attr($summary) ?>">Columns</summary>
      <div class="<?= attr($menu) ?> w-48">
        <?php foreach ($vm->columnOptions as $o): ?>
          <label class="flex items-center gap-2 rounded-sm px-1 py-0.5 text-sm hover:bg-accent<?= $o->disabled ? ' opacity-60' : '' ?>">
            <input type="checkbox" class="accent-primary" value="<?= attr($o->value) ?>" data-bind="q.cols"<?= $o->disabled ? ' disabled' : '' ?> data-on:change="<?= attr($fetch) ?>"> <?= e($o->label) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
  <?= ui_button(new ButtonProps(variant: 'ghost', href: $vm->resetUrl), 'Reset') ?>
</div>
<?php
    return (string) ob_get_clean();
}

/** #views-menu: built-in, own and shared views; save, default, share, delete. */
function partial_views_menu(ViewsMenuVM $vm): string
{
    $item = 'flex min-w-0 flex-1 items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground';
    $icon = 'rounded-sm px-1.5 py-1 text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground';
    $op = static fn (string $url, string $op, string $method = 'patch'): string =>
        '$vedit.op = ' . jobs_js($op) . '; ' . act_raw($method, $url, 'filterSignals: {include: /^(vedit|q)\\./}, retryMaxCount: 0');
    $link = static function ($v) use ($item): string {
        return '<a href="' . attr($v->href) . '" class="' . $item . ($v->active ? ' font-semibold' : '') . '"' . ($v->active ? ' aria-current="true"' : '') . '>'
            . '<span class="truncate">' . e($v->name) . '</span>'
            . ($v->isDefault ? '<span class="text-xs text-primary">default</span>' : '')
            . ($v->shared && !$v->builtin ? '<span class="text-xs text-muted-foreground">shared' . ($v->ownerName !== '' ? ' by ' . e($v->ownerName) : '') . '</span>' : '')
            . '</a>';
    };
    ob_start(); ?>
<div id="views-menu" class="flex items-center gap-2">
  <details class="relative" data-filter-menu>
    <summary class="inline-flex h-9 cursor-pointer list-none items-center gap-2 rounded-md border border-input px-3 text-sm shadow-xs hover:bg-accent hover:text-accent-foreground">
      <span class="text-muted-foreground">View:</span> <span class="max-w-48 truncate font-medium"><?= e($vm->activeName) ?></span>
    </summary>
    <div class="absolute right-0 z-40 mt-1 w-80 rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md">
      <p class="px-2 py-1 text-xs font-medium text-muted-foreground">Built in</p>
      <?php foreach ($vm->builtins as $v): ?><?= $link($v) ?><?php endforeach; ?>
      <?php if ($vm->mine !== []): ?>
        <p class="mt-1 border-t border-border px-2 pt-2 pb-1 text-xs font-medium text-muted-foreground">My views</p>
        <?php foreach ($vm->mine as $v): ?>
          <div class="flex items-center gap-1" data-view-id="<?= attr($v->id) ?>">
            <?= $link($v) ?>
            <button type="button" class="<?= $icon ?>" title="Save the current filters into this view" data-on:click="<?= attr($op($v->manageUrl, 'update')) ?>">Update</button>
            <button type="button" class="<?= $icon ?>" title="<?= $v->isDefault ? 'Stop using as default' : 'Open this view by default' ?>" data-on:click="<?= attr($op($v->manageUrl, $v->isDefault ? 'undefault' : 'default')) ?>"><?= $v->isDefault ? 'Undefault' : 'Default' ?></button>
            <?php if ($vm->canShare): ?>
              <button type="button" class="<?= $icon ?>" data-on:click="<?= attr($op($v->manageUrl, $v->shared ? 'unshare' : 'share')) ?>"><?= $v->shared ? 'Unshare' : 'Share' ?></button>
            <?php endif; ?>
            <button type="button" class="<?= $icon ?> text-destructive" aria-label="<?= attr('Delete view ' . $v->name) ?>" data-on:click="<?= attr('confirm(' . jobs_js('Delete the view "' . $v->name . '"?') . ') && ' . act_raw('delete', $v->manageUrl, 'filterSignals: {include: /^q\\./}, retryMaxCount: 0')) ?>">Delete</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if ($vm->shared !== []): ?>
        <p class="mt-1 border-t border-border px-2 pt-2 pb-1 text-xs font-medium text-muted-foreground">Shared with everyone</p>
        <?php foreach ($vm->shared as $v): ?>
          <div class="flex items-center gap-1" data-view-id="<?= attr($v->id) ?>">
            <?= $link($v) ?>
            <?php if ($v->canManage): ?>
              <button type="button" class="<?= $icon ?>" data-on:click="<?= attr($op($v->manageUrl, 'unshare')) ?>">Unshare</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </details>
  <?= ui_button(new ButtonProps(variant: 'outline', attrs: ['data-on:click' => "\$view.name = ''; \$view.shared = false; \$view.default = false; \$save_view.open = true"]), 'Save view') ?>
</div>
<?php
    return (string) ob_get_clean();
}

/** The save-view dialog (outside #views-menu so a menu patch never resets typing). */
function partial_save_view_dialog(ViewsMenuVM $vm): string
{
    // Close first: the answer patches #views-menu and a toast only (one patch kind, html transport safe).
    $save = "\$view.name.trim() !== '' && (\$save_view.open = false, " . act_raw('post', $vm->createUrl, 'filterSignals: {include: /^(view|q)\\./}, retryMaxCount: 0') . ')';
    return ui_dialog(new DialogProps(id: 'save-view', class: 'max-w-md'),
        ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(), 'Save view') . ui_dialog_description(new PartProps(), 'Saves the current filters, sort, grouping and columns.'))
        . ui_dialog_content(new PartProps(class: 'flex flex-col gap-3 py-4'),
            '<label class="flex flex-col gap-1 text-sm font-medium" for="sv-name">Name'
            . '<input id="sv-name" type="text" maxlength="60" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm font-normal shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30" data-bind="view.name" data-on:keydown="' . attr("evt.key === 'Enter' && (" . $save . ')') . '"></label>'
            . ($vm->canShare ? '<label class="flex items-center gap-2 text-sm"><input type="checkbox" class="accent-primary" data-bind="view.shared"> Share with everyone</label>' : '')
            . '<label class="flex items-center gap-2 text-sm"><input type="checkbox" class="accent-primary" data-bind="view.default"> Open this view by default</label>')
        . ui_dialog_footer(new PartProps(),
            ui_dialog_close(new DialogCloseProps(dialogId: 'save-view', variant: 'outline'), 'Close')
            . ui_button(new ButtonProps(attrs: ['data-on:click' => $save, 'data-attr:disabled' => "\$view.name.trim() === ''"]), 'Save')));
}

/** Reason dialog for moves into waiting, on hold or cancelled (board drops and menus). */
function partial_move_dialog(): string
{
    $options = [];
    foreach (WaitingOn::cases() as $w) {
        $options[] = new SelectOption($w->value, $w->label());
    }
    $confirm = 'window.s301Jobs && s301Jobs.place($move.job_id, $move.to); $move_dialog.open = false; ' . jobs_move_post();
    return ui_dialog(new DialogProps(id: 'move-dialog', class: 'max-w-md'),
        ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(),
            '<span data-text="' . attr("\$move.to === 'waiting' ? 'Put on waiting' : (\$move.to === 'on_hold' ? 'Put on hold' : 'Cancel job')") . '">Move</span>'))
        . ui_dialog_content(new PartProps(class: 'flex flex-col gap-3 py-4'),
            '<div class="flex flex-col gap-1" data-show="' . attr("\$move.to === 'waiting'") . '"><label class="text-sm font-medium" for="mv-on">Waiting on</label>'
            . ui_native_select(new NativeSelectProps(id: 'mv-on', options: $options, placeholder: 'Choose...', attrs: ['data-bind' => 'move.waiting_on'])) . '</div>'
            . '<div class="flex flex-col gap-1"><label class="text-sm font-medium" for="mv-reason">Reason</label>'
            . ui_textarea(new TextareaProps(id: 'mv-reason', rows: 3, placeholder: 'What is it waiting for, or why?', attrs: ['data-bind' => 'move.reason', 'maxlength' => '1000'])) . '</div>')
        . ui_dialog_footer(new PartProps(),
            ui_dialog_close(new DialogCloseProps(dialogId: 'move-dialog', variant: 'outline'), 'Close')
            . ui_button(new ButtonProps(attrs: ['data-on:click' => $confirm, 'data-attr:disabled' => "\$move.reason.trim() === '' || (\$move.to === 'waiting' && \$move.waiting_on === '')"]), 'Move')));
}

/** The job sheet, patched over #sheet (outer, by id). The nonce makes a re-fetch reopen it after a close. */
function partial_job_sheet(JobSheetVM $vm): string
{
    return '<div id="sheet" data-signals="' . js(['job_sheet' => ['open' => true], '_sheet_nonce' => $vm->nonce]) . '" data-job-id="' . attr($vm->id) . '">'
        . ui_sheet(new SheetProps(id: 'job-sheet', defaultOpen: true, modal: true, attrs: ['aria-label' => 'Job ' . $vm->jobNumber]),
            ui_sheet_content(new SheetContentProps(sheetId: 'job-sheet', class: 'h-full'), partial_job_sheet_body($vm)))
        . '</div>';
}

function partial_job_sheet_body(JobSheetVM $vm): string
{
    $row = static fn (string $label, string $html): string => '<div class="flex justify-between gap-4 py-1"><dt class="text-muted-foreground">' . e($label) . '</dt><dd class="text-right">' . $html . '</dd></div>';
    ob_start(); ?>
  <div class="-mt-8 flex h-full flex-col gap-5 overflow-y-auto pr-1 text-sm">
    <header class="flex flex-col gap-1 pr-8">
      <p class="font-mono text-xs text-muted-foreground"><?= e($vm->jobNumber) ?> · <?= e($vm->brandName) ?></p>
      <h2 class="text-lg leading-tight font-semibold"><?= e($vm->title) ?></h2>
      <div class="flex flex-wrap items-center gap-2">
        <?= ui_badge(new BadgeProps(stage: $vm->stage->value), e($vm->stage->label())) ?>
        <?php if ($vm->waitingText !== ''): ?><span class="text-xs text-muted-foreground"><?= e($vm->waitingText) ?></span><?php endif; ?>
      </div>
    </header>
    <dl class="divide-y divide-border">
      <?= $row('Campaign', e($vm->campaignName !== '' ? $vm->campaignName : '-')) ?>
      <?= $row('Due', jobs_due($vm->dueText, $vm->dueBadge)) ?>
      <?php if ($vm->hoursText !== null): ?><?= $row('Hours', $vm->hoursText !== '' ? e($vm->hoursText) : '<span class="text-muted-foreground">-</span>') ?><?php endif; ?>
      <?php if ($vm->budgetText !== null): ?><?= $row('Budget', $vm->budgetText !== '' ? e($vm->budgetText) : '<span class="text-muted-foreground">-</span>') ?><?php endif; ?>
    </dl>
    <section>
      <h3 class="mb-1 font-semibold">Team</h3>
      <?php if ($vm->team === []): ?><p class="text-muted-foreground">Nobody is assigned.</p><?php else: ?>
      <ul class="flex flex-col gap-1">
        <?php foreach ($vm->team as [$role, $name]): ?>
          <li class="flex items-center gap-2"><?= jobs_avatar($name) ?><span><?= e($name) ?></span><span class="ml-auto text-xs text-muted-foreground"><?= e($role) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($vm->social !== null): /* Social publishing: the Social slot after the send */ ?>
        <div class="mt-3 flex flex-col gap-1" data-signals="<?= attr(jobs_js(['sheet_social' => ['value' => $vm->social->holderId]])) ?>">
          <label for="sheet-social" class="text-xs text-muted-foreground">Social (posts after client approval)</label>
          <?= ui_native_select(new NativeSelectProps(id: 'sheet-social', options: $vm->social->options, value: $vm->social->holderId, attrs: ['data-bind' => 'sheet_social.value',
              'data-on:change' => act_raw('post', url('/jobs/' . rawurlencode($vm->id) . '/assignments/social'), 'filterSignals: {include: /^sheet_social\\./}, retryMaxCount: 0')])) ?>
        </div>
      <?php endif; ?>
    </section>
    <section>
      <h3 class="mb-1 font-semibold">Deliverables</h3>
      <?php if ($vm->deliverables === []): ?><p class="text-muted-foreground">None listed yet.</p><?php else: ?>
      <ul class="list-disc pl-5"><?php foreach ($vm->deliverables as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
    <section>
      <h3 class="mb-1 font-semibold">Brief</h3>
      <p><?= e($vm->versionLabel) ?><?= $vm->unsent ? ' <span class="text-xs text-primary">(unsent changes)</span>' : '' ?></p>
      <?php if ($vm->versionNote !== ''): ?><p class="text-muted-foreground"><?= e($vm->versionNote) ?></p><?php endif; ?>
      <p class="mt-1 flex flex-wrap gap-3">
        <a class="text-primary underline-offset-4 hover:underline" href="<?= attr($vm->briefUrl) ?>">Open the brief</a>
        <?php if ($vm->versionUrl !== ''): ?><a class="text-primary underline-offset-4 hover:underline" href="<?= attr($vm->versionUrl) ?>">Sent version</a><?php endif; ?>
      </p>
    </section>
    <?php if ($vm->moves !== []): ?>
    <section>
      <h3 class="mb-2 font-semibold">Actions</h3>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($vm->moves as $m): ?>
          <?php if ($m->viaBrief): ?>
            <?= ui_button(new ButtonProps(variant: 'outline', size: 'sm', href: $m->href), e($m->label)) ?>
          <?php else: ?>
            <?= ui_button(new ButtonProps(variant: $m->to === 'cancelled' ? 'destructive' : 'outline', size: 'sm', attrs: ['data-on:click' => jobs_move_expr($vm->id, $vm->stage->value, $vm->rowVersion, $m, true)]), e($m->label)) ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
    <section>
      <h3 class="mb-1 font-semibold">Activity</h3>
      <?php if ($vm->activity === []): ?><p class="text-muted-foreground">Nothing yet.</p><?php else: ?>
      <ol class="flex flex-col gap-2">
        <?php foreach ($vm->activity as $a): ?>
          <li><span class="font-medium"><?= e($a->actor) ?></span> <?= e($a->text) ?><div class="text-xs text-muted-foreground"><?= e($a->at) ?></div></li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </section>
  </div>
<?php
    return (string) ob_get_clean();
}
