<?php
declare(strict_types=1);

use App\Domain\JobAction;
use App\Domain\WaitingOn;
use App\View\ui\BadgeProps;
use App\View\ui\ButtonProps;
use App\View\ui\ComboboxProps;
use App\View\ui\DialogCloseProps;
use App\View\ui\DialogProps;
use App\View\ui\InputProps;
use App\View\ui\LabelProps;
use App\View\ui\NativeSelectProps;
use App\View\ui\PartProps;
use App\View\ui\SelectOption;
use App\View\ui\TextareaProps;
use App\View\VM\BriefEditorVM;
use App\View\VM\BriefRailVM;
use App\View\VM\SendDialogVM;
use App\View\VM\TeamSlotVM;

/**
 * GET /jobs/{id}/brief. Signals: brief.* (the form, autosaved with PATCH),
 * dl.ln_<id>.* (one deliverable row each), new_line.template_id, reorder.*,
 * team_<role> (comboboxes), tr.* (stage move dialogs), send.* (send dialogs),
 * _saving (indicator). Every write answers with outer patches by id plus a
 * toast, so it also works with transport=html.
 */
function page_brief_editor(BriefEditorVM $vm): string
{
    $base = url('/jobs/' . rawurlencode($vm->jobId) . '/brief');
    ob_start(); ?>
<div id="brief-page" class="mx-auto flex w-full max-w-7xl flex-col gap-6"
     data-signals="<?= js(['tr' => ['action' => '', 'waiting_on' => '', 'reason' => '']]) ?>">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <p class="text-sm text-muted-foreground"><span class="font-mono"><?= e($vm->jobNumber) ?></span> · <?= e($vm->campaignLabel) ?></p>
      <?= partial_brief_heading($vm->title) ?>
    </div>
    <div class="flex flex-wrap gap-2">
      <?= ui_button(new ButtonProps(variant: 'outline', size: 'sm', href: $base . '/versions'), 'Versions') ?>
      <?= ui_button(new ButtonProps(variant: 'outline', size: 'sm', href: $base . '/print', target: '_blank'), 'Print') ?>
    </div>
  </div>
  <?php // The heading sits above the grid so the rail's first card lines up with the first content card. Two columns from xl (the sidebar takes 15rem of the width). ?>
  <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
  <div class="flex min-w-0 flex-col gap-6">
    <?php if (!$vm->canEdit): ?>
      <?= $vm->doc !== null ? ui_card(new PartProps(class: 'px-6'), partial_brief_doc($vm->doc)) : ui_card(new PartProps(class: 'px-6'), '<p class="text-sm text-muted-foreground">This brief has not been sent yet.</p>') ?>
      <?php if ($vm->team !== []): ?><?= partial_brief_team($vm->jobId, $vm->team, $vm->brief->isSent()) ?><?php endif; ?>
    <?php else: ?>
      <?= partial_brief_form($vm, $base) ?>
      <?= partial_brief_deliverables($vm->jobId, $vm->lines, $vm->templateOptions, true) ?>
      <?= partial_brief_team($vm->jobId, $vm->team, $vm->brief->isSent()) ?>
    <?php endif; ?>
  </div>
  <?= partial_brief_rail($vm->rail) ?>
  </div>
</div>
<?= partial_brief_dialogs($vm->jobId) ?>
<?php
    return (string) ob_get_clean();
}

/** The page heading; re-patched by autosave so a new title shows at once. */
function partial_brief_heading(string $title): string
{
    return '<h2 id="brief-heading" class="mt-1 truncate text-2xl font-semibold leading-tight">' . e($title) . '</h2>';
}

function brief_field(string $id, string $label, string $control, string $hint = ''): string
{
    return '<div class="flex flex-col gap-2">' . ui_label(new LabelProps(for: $id), e($label)) . $control
        . ($hint !== '' ? '<p class="text-xs text-muted-foreground">' . e($hint) . '</p>' : '') . '</div>';
}

function partial_brief_form(BriefEditorVM $vm, string $base): string
{
    $b = $vm->brief;
    $refs = [];
    foreach ($b->references as $r) {
        $refs[] = $r->toLine();
    }
    $signals = [
        'title' => $b->title, 'campaign_id' => (string) $b->campaignId, 'brief_date' => (string) $b->briefDate, 'due_date' => (string) $b->dueDate,
        'first_go_live' => (string) $b->firstGoLive, 'last_go_live' => (string) $b->lastGoLive, 'creative_direction' => $b->creativeDirection,
        'mandatories_text' => implode("\n", $b->mandatories), 'references_text' => implode("\n", $refs), 'brief_pdf_url' => $b->briefPdfUrl,
        'server_link' => $b->serverLink, 'hours_estimate' => fmt_num($b->hoursEstimate), 'row_version' => $b->rowVersion,
    ];
    if ($vm->canViewBudget) {
        $signals['budget'] = fmt_num($b->budget);
    }
    $save = act('patch', $base, 'filterSignals: {include: /^brief\\./}');
    $card = 'bg-card border border-border flex flex-col gap-4 p-6 rounded-xl shadow-sm text-card-foreground';
    ob_start(); ?>
<form id="brief-form" class="flex flex-col gap-6" data-signals="<?= js(['brief' => $signals]) ?>" data-indicator:_saving
      data-on:input__debounce.800ms="<?= $save ?>" data-on:change__debounce.800ms="<?= $save ?>">
  <section class="<?= attr($card) ?>" aria-labelledby="sec-basics">
    <h3 id="sec-basics" class="text-base font-semibold">Basics</h3>
    <?= brief_field('brief-title', 'Title', ui_input(new InputProps(id: 'brief-title', type: 'text', attrs: ['data-bind' => 'brief.title', 'maxlength' => '200', 'autocomplete' => 'off']))) ?>
    <div class="grid gap-4 md:grid-cols-2">
      <?= brief_field('brief-campaign', 'Campaign', ui_native_select(new NativeSelectProps(id: 'brief-campaign', options: $vm->campaignOptions, value: (string) $b->campaignId, attrs: ['data-bind' => 'brief.campaign_id'])), 'Campaigns of the same brand (the job number belongs to the brand).') ?>
      <div class="grid grid-cols-2 gap-4">
        <?= brief_field('brief-date', 'Brief date', ui_input(new InputProps(id: 'brief-date', type: 'date', attrs: ['data-bind' => 'brief.brief_date']))) ?>
        <?= brief_field('brief-due', 'Due date', ui_input(new InputProps(id: 'brief-due', type: 'date', attrs: ['data-bind' => 'brief.due_date']))) ?>
      </div>
      <?= brief_field('brief-first-live', 'First go-live', ui_input(new InputProps(id: 'brief-first-live', type: 'date', attrs: ['data-bind' => 'brief.first_go_live']))) ?>
      <?= brief_field('brief-last-live', 'Last go-live', ui_input(new InputProps(id: 'brief-last-live', type: 'date', attrs: ['data-bind' => 'brief.last_go_live']))) ?>
    </div>
  </section>
  <section class="<?= attr($card) ?>" aria-labelledby="sec-cd">
    <h3 id="sec-cd" class="text-base font-semibold">Creative direction</h3>
    <?= ui_textarea(new TextareaProps(id: 'brief-cd', rows: 8, placeholder: 'The idea, tone, audience and what success looks like. Plain text; line breaks are kept.', attrs: ['data-bind' => 'brief.creative_direction', 'aria-label' => 'Creative direction'])) ?>
  </section>
  <section class="<?= attr($card) ?>" aria-labelledby="sec-mand">
    <h3 id="sec-mand" class="text-base font-semibold">Mandatories and references</h3>
    <div class="grid gap-4 md:grid-cols-2">
      <?= brief_field('brief-mand', 'Mandatories', ui_textarea(new TextareaProps(id: 'brief-mand', rows: 5, placeholder: "Logo lockup bottom right\nT&Cs apply", attrs: ['data-bind' => 'brief.mandatories_text'])), 'One per line.') ?>
      <?= brief_field('brief-refs', 'References', ui_textarea(new TextareaProps(id: 'brief-refs', rows: 5, placeholder: 'Moodboard | https://...', attrs: ['data-bind' => 'brief.references_text'])), 'One per line: label | https://link') ?>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
      <?= brief_field('brief-pdf', 'Brief PDF link', ui_input(new InputProps(id: 'brief-pdf', type: 'url', placeholder: 'https://', attrs: ['data-bind' => 'brief.brief_pdf_url']))) ?>
      <?= brief_field('brief-server', 'Server folder link', ui_input(new InputProps(id: 'brief-server', type: 'text', placeholder: '\\\\server\\jobs\\... or https://', attrs: ['data-bind' => 'brief.server_link']))) ?>
    </div>
  </section>
  <section class="<?= attr($card) ?>" aria-labelledby="sec-money">
    <h3 id="sec-money" class="text-base font-semibold"><?= $vm->canViewBudget ? 'Budget and hours' : 'Hours' ?></h3>
    <div class="grid gap-4 md:grid-cols-2">
      <?php if ($vm->canViewBudget): ?>
        <?= brief_field('brief-budget', 'Budget (ZAR)', ui_input(new InputProps(id: 'brief-budget', type: 'text', placeholder: '25000', attrs: ['data-bind' => 'brief.budget', 'inputmode' => 'decimal'])), 'Visible to the job owner, the COO and the ECD only.') ?>
      <?php endif; ?>
      <?= brief_field('brief-hours', 'Hours estimate', ui_input(new InputProps(id: 'brief-hours', type: 'text', placeholder: '30', attrs: ['data-bind' => 'brief.hours_estimate', 'inputmode' => 'decimal']))) ?>
    </div>
  </section>
</form>
<?php
    return (string) ob_get_clean();
}

/**
 * #brief-deliverables: one row per line, each row saves itself on change.
 * @param list<App\Domain\Types\BriefLine> $lines
 * @param list<SelectOption> $templates
 */
function partial_brief_deliverables(string $jobId, array $lines, array $templates, bool $canEdit): string
{
    $base = url('/jobs/' . rawurlencode($jobId) . '/brief/assets');
    $dl = [];
    $total = 0;
    foreach ($lines as $l) {
        $dl[brief_line_key($l->id)] = [
            'template_id' => (string) $l->templateId, 'label' => $l->label, 'qty' => $l->qty, 'channel' => $l->channel,
            'size_format' => $l->sizeFormat, 'specs' => $l->specs, 'copy_required' => $l->copyRequired, 'due_date' => (string) $l->dueDate,
        ];
        $total += $l->qty;
    }
    $cell = 'h-8 w-full min-w-0 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
    $iconBtn = 'inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground disabled:opacity-30';
    $count = count($lines);
    ob_start(); ?>
<section id="brief-deliverables" class="bg-card border border-border flex flex-col gap-4 p-6 rounded-xl shadow-sm text-card-foreground" aria-labelledby="sec-dl"
         data-signals="<?= js(['new_line' => ['template_id' => 'social-static'], 'reorder' => ['line_id' => '', 'dir' => '']]) ?>">
  <div class="flex flex-wrap items-baseline justify-between gap-2">
    <h3 id="sec-dl" class="text-base font-semibold">Deliverables</h3>
    <p class="text-sm text-muted-foreground"><?= $count ?> <?= $count === 1 ? 'line' : 'lines' ?>, <?= $total ?> <?= $total === 1 ? 'asset' : 'assets' ?> on send</p>
  </div>
  <?php if ($lines === []): ?>
    <p class="rounded-md border border-dashed border-border p-4 text-sm text-muted-foreground">No deliverables yet. Pick a template below and add it; each line becomes that many assets when the brief is sent.</p>
  <?php else: ?>
  <ol class="flex flex-col gap-3">
  <?php foreach ($lines as $i => $l): $k = brief_line_key($l->id); $p = 'dl.' . $k . '.'; $lineUrl = $base . '/' . rawurlencode($l->id); $fid = 'l' . $l->id; $dis = $canEdit ? '' : ' disabled'; ?>
    <li id="line-<?= attr($l->id) ?>" class="grid grid-cols-2 gap-x-3 gap-y-2 rounded-lg border border-border p-3 md:grid-cols-12" data-signals="<?= js(['dl' => [$k => $dl[$k]]]) ?>"
        aria-label="<?= attr('Deliverable ' . ($i + 1)) ?>"
        <?php if ($canEdit): ?>data-on:change="<?= act('patch', $lineUrl, 'filterSignals: {include: /^dl\\.' . $k . '\\./}') ?>"<?php endif; ?>>
      <div class="col-span-2 flex flex-col gap-1 md:col-span-3">
        <label for="<?= attr($fid . '-tpl') ?>" class="text-xs text-muted-foreground">Template</label>
        <?= ui_native_select(new NativeSelectProps(id: $fid . '-tpl', options: $templates, value: (string) $l->templateId, disabled: !$canEdit, attrs: ['data-bind' => $p . 'template_id'])) ?>
      </div>
      <div class="col-span-2 flex flex-col gap-1 md:col-span-5">
        <label for="<?= attr($fid . '-label') ?>" class="text-xs text-muted-foreground">Deliverable</label>
        <input id="<?= attr($fid . '-label') ?>" class="<?= attr($cell) ?>" data-bind="<?= attr($p . 'label') ?>" maxlength="120"<?= $dis ?>>
      </div>
      <div class="flex flex-col gap-1 md:col-span-1">
        <label for="<?= attr($fid . '-qty') ?>" class="text-xs text-muted-foreground">Qty</label>
        <input id="<?= attr($fid . '-qty') ?>" class="<?= attr($cell) ?>" type="number" min="1" max="500" data-bind="<?= attr($p . 'qty') ?>"<?= $dis ?>>
      </div>
      <div class="flex items-end justify-end gap-1 md:col-span-3">
        <?php if ($canEdit): ?>
        <button type="button" class="<?= attr($iconBtn) ?>" aria-label="Move up"<?= $i === 0 ? ' disabled' : '' ?>
                data-on:click="<?= e('$reorder.line_id = ' . json_encode($l->id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR) . "; \$reorder.dir = 'up'; ") . act('post', $base . '/order', 'filterSignals: {include: /^reorder\\./}') ?>">&uarr;</button>
        <button type="button" class="<?= attr($iconBtn) ?>" aria-label="Move down"<?= $i === $count - 1 ? ' disabled' : '' ?>
                data-on:click="<?= e('$reorder.line_id = ' . json_encode($l->id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR) . "; \$reorder.dir = 'down'; ") . act('post', $base . '/order', 'filterSignals: {include: /^reorder\\./}') ?>">&darr;</button>
        <button type="button" class="<?= attr($iconBtn) ?> hover:text-destructive" aria-label="Remove line"
                data-on:click="<?= e('confirm(' . json_encode('Remove "' . $l->label . '"?', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR) . ') && ') . act('delete', $lineUrl, 'filterSignals: {include: /^reorder\\./}') ?>">&times;</button>
        <?php endif; ?>
      </div>
      <div class="flex flex-col gap-1 md:col-span-3">
        <label for="<?= attr($fid . '-ch') ?>" class="text-xs text-muted-foreground">Channel</label>
        <input id="<?= attr($fid . '-ch') ?>" class="<?= attr($cell) ?>" placeholder="e.g. Instagram" data-bind="<?= attr($p . 'channel') ?>" maxlength="120"<?= $dis ?>>
      </div>
      <div class="flex flex-col gap-1 md:col-span-3">
        <label for="<?= attr($fid . '-size') ?>" class="text-xs text-muted-foreground">Size or format</label>
        <input id="<?= attr($fid . '-size') ?>" class="<?= attr($cell) ?>" placeholder="e.g. 1080x1350" data-bind="<?= attr($p . 'size_format') ?>" maxlength="120"<?= $dis ?>>
      </div>
      <div class="flex flex-col gap-1 md:col-span-3">
        <label for="<?= attr($fid . '-due') ?>" class="text-xs text-muted-foreground">Due (blank: brief due date)</label>
        <input id="<?= attr($fid . '-due') ?>" class="<?= attr($cell) ?>" type="date" data-bind="<?= attr($p . 'due_date') ?>"<?= $dis ?>>
      </div>
      <label class="flex items-center gap-2 self-end pb-1.5 text-sm md:col-span-3">
        <input type="checkbox" class="size-4 accent-primary" data-bind="<?= attr($p . 'copy_required') ?>"<?= $dis ?>> Copy required
      </label>
      <div class="col-span-2 flex flex-col gap-1 md:col-span-12">
        <label for="<?= attr($fid . '-specs') ?>" class="text-xs text-muted-foreground">Specs</label>
        <textarea id="<?= attr($fid . '-specs') ?>" class="<?= attr($cell) ?> h-auto min-h-8 py-1.5" rows="2" placeholder="Safe zones, file type, length, copy limits..." data-bind="<?= attr($p . 'specs') ?>"<?= $dis ?>></textarea>
      </div>
    </li>
  <?php endforeach; ?>
  </ol>
  <?php endif; ?>
  <?php if ($canEdit): ?>
  <div class="flex flex-wrap items-end gap-2 border-t border-border pt-4">
    <div class="flex w-full flex-col gap-2 sm:w-72">
      <?= ui_label(new LabelProps(for: 'new-line-template'), 'Add a deliverable') ?>
      <?= ui_native_select(new NativeSelectProps(id: 'new-line-template', options: $templates, value: 'social-static', attrs: ['data-bind' => 'new_line.template_id'])) ?>
    </div>
    <?= ui_button(new ButtonProps(variant: 'secondary', attrs: ['data-on:click' => act_raw('post', $base, 'filterSignals: {include: /^new_line\\./}')]), 'Add line') ?>
  </div>
  <?php endif; ?>
</section>
<?php
    return (string) ob_get_clean();
}

/**
 * #brief-team: one combobox per slot the user may set (picking a person saves
 * at once), the holder's name otherwise. @param list<TeamSlotVM> $slots
 */
function partial_brief_team(string $jobId, array $slots, bool $sent = false): string
{
    $intro = $sent
        ? 'Traffic assigns the CD, creatives and QA. Changes save at once and the person is told.'
        : 'Traffic is required to send. Traffic assigns the CD and creatives; anything you pick here is a suggestion.';
    ob_start(); ?>
<section id="brief-team" class="bg-card border border-border flex flex-col gap-4 p-6 rounded-xl shadow-sm text-card-foreground" aria-labelledby="sec-team">
  <div>
    <h3 id="sec-team" class="text-base font-semibold">Team</h3>
    <p class="mt-1 text-sm text-muted-foreground"><?= e($intro) ?></p>
  </div>
  <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  <?php foreach ($slots as $slot): $sig = str_replace('-', '_', $slot->comboId); ?>
    <div class="flex flex-col gap-2">
      <?= ui_label(new LabelProps(for: $slot->comboId), e($slot->label) . ($slot->required ? ' <span class="text-destructive" aria-hidden="true">*</span><span class="sr-only">(required)</span>' : '')) ?>
      <?php if ($slot->canEdit): ?>
        <div data-on:click="<?= e("evt.target.closest('[data-combobox-item]') && ") . act('post', url('/jobs/' . rawurlencode($jobId) . '/assignments/' . $slot->routeRole), 'filterSignals: {include: /^' . $sig . '\\.value$/}') ?>">
          <?= ui_combobox(new ComboboxProps(id: $slot->comboId, options: $slot->options, value: $slot->holderId, placeholder: 'Not assigned', searchPlaceholder: 'Search people...', emptyText: 'Nobody found', attrs: ['aria-label' => $slot->label])) ?>
        </div>
      <?php else: ?>
        <p id="<?= attr($slot->comboId) ?>" class="flex h-9 items-center rounded-md border border-border bg-muted/40 px-3 text-sm"><?= $slot->holderName !== '' ? e($slot->holderName) : '<span class="text-muted-foreground">Not assigned</span>' ?></p>
      <?php endif; ?>
      <?php if ($slot->hint !== ''): ?><p class="text-xs text-muted-foreground"><?= e($slot->hint) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
</section>
<?php
    return (string) ob_get_clean();
}

/** #brief-rail: status, version, saved state, readiness, actions, activity. */
function partial_brief_rail(BriefRailVM $vm): string
{
    $base = url('/jobs/' . rawurlencode($vm->jobId));
    $card = 'bg-card border border-border flex flex-col gap-3 p-4 rounded-xl shadow-sm text-card-foreground';
    $tr = static fn (string $action, string $confirm): string => e(($confirm !== '' ? 'confirm(' . json_encode($confirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR) . ') && (' : '(') . "\$tr.action = '" . $action . "', ")
        . act('post', $base . '/transition', 'filterSignals: {include: /^tr\\./}') . ')';
    ob_start(); ?>
<aside id="brief-rail" class="flex flex-col gap-4 self-start xl:sticky xl:top-20 xl:max-h-[calc(100vh-6rem)] xl:overflow-y-auto xl:pb-1" aria-label="Brief status">
  <div class="<?= attr($card) ?>">
    <div class="flex flex-wrap items-center gap-2">
      <?= ui_badge(new BadgeProps(stage: $vm->stage), e($vm->stageLabel)) ?>
      <?= ui_badge(new BadgeProps(variant: 'outline'), e($vm->versionLabel)) ?>
    </div>
    <?php if ($vm->stageNote !== ''): ?><p class="text-sm"><?= e($vm->stageNote) ?></p><?php endif; ?>
    <?php if ($vm->canEdit): ?>
    <p id="brief-saved" class="text-xs text-muted-foreground" aria-live="polite" data-signals:brief.row_version="<?= (int) $vm->rowVersion ?>">
      <span data-show="$_saving" style="display: none">Saving...</span><span data-show="!$_saving"><?= $vm->savedAt !== '' ? 'Saved ' . e($vm->savedAt) : 'All changes saved' ?></span>
    </p>
    <?php endif; ?>
    <?php if ($vm->isSent && $vm->hasChanges): ?>
      <p class="rounded-md bg-accent px-3 py-2 text-sm text-accent-foreground"><strong class="font-semibold">Unsent changes.</strong> The team still works from <?= e($vm->versionLabel) ?> until you send an update.</p>
    <?php endif; ?>
  </div>

  <?php if ($vm->canEdit): ?>
  <div class="<?= attr($card) ?>">
    <h3 class="text-sm font-semibold">Ready to send</h3>
    <ul class="flex flex-col gap-1.5 text-sm">
      <?php foreach ($vm->checklist as $item): ?>
        <li class="flex items-start gap-2">
          <?php if ($item->ok): ?>
            <span class="mt-0.5 inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-primary text-[10px] text-primary-foreground" aria-hidden="true">&#10003;</span><span><?= e($item->label) ?><span class="sr-only"> done</span></span>
          <?php else: ?>
            <span class="mt-0.5 inline-flex size-4 shrink-0 rounded-full border border-muted-foreground" aria-hidden="true"></span><span class="text-muted-foreground"><?= e($item->message) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="<?= attr($card) ?>">
    <h3 class="text-sm font-semibold">Actions</h3>
    <div class="flex flex-col gap-2">
      <?php if ($vm->canSend): ?>
        <?= ui_button(new ButtonProps(class: 'w-full', attrs: ['data-on:click' => '$send_dialog.open = true; ' . act_raw('get', $base . '/brief/send', 'filterSignals: {include: /^send\\./}')]), 'Send to Traffic') ?>
      <?php endif; ?>
      <?php if ($vm->canUpdate): ?>
        <?= ui_button(new ButtonProps(class: 'w-full', disabled: !$vm->hasChanges, attrs: ['data-on:click' => '$update_dialog.open = true; ' . act_raw('get', $base . '/brief/update', 'filterSignals: {include: /^send\\./}')]), $vm->stage === 'draft' ? 'Re-send to Traffic' : ($vm->hasChanges ? 'Send update' : 'No changes to send')) ?>
      <?php endif; ?>
      <?php if ($vm->canClaimAm): ?>
        <?= ui_button(new ButtonProps(variant: 'secondary', class: 'w-full', attrs: ['data-on:click' => act_raw('post', $base . '/claim-am', 'filterSignals: {include: /^tr\\./}')]), 'Make me AM') ?>
      <?php endif; ?>
      <?php foreach ($vm->actions as $a): ?>
        <?php if ($a === JobAction::Wait): ?>
          <?= ui_button(new ButtonProps(variant: 'outline', size: 'sm', class: 'w-full', attrs: ['data-on:click' => "\$tr.reason = ''; \$wait_dialog.open = true"]), 'Put on waiting...') ?>
        <?php elseif ($a === JobAction::Hold): ?>
          <?= ui_button(new ButtonProps(variant: 'outline', size: 'sm', class: 'w-full', attrs: ['data-on:click' => "\$tr.reason = ''; \$hold_dialog.open = true"]), 'Put on hold...') ?>
        <?php elseif ($a === JobAction::Cancel): ?>
          <?= ui_button(new ButtonProps(variant: 'ghost', size: 'sm', class: 'w-full text-destructive', attrs: ['data-on:click' => "\$tr.reason = ''; \$cancel_dialog.open = true"]), 'Cancel job...') ?>
        <?php elseif ($a === JobAction::Recall): ?>
          <button type="button" class="<?= attr('inline-flex h-8 w-full items-center justify-center rounded-md border border-border px-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground') ?>" data-on:click="<?= $tr('recall', 'Recall the brief to draft? Traffic and the team are told to stop planning.') ?>">Recall to draft</button>
        <?php elseif ($a === JobAction::Resume): ?>
          <button type="button" class="<?= attr('inline-flex h-8 w-full items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90') ?>" data-on:click="<?= $tr('resume', '') ?>">Resume</button>
        <?php elseif ($a === JobAction::Start): ?>
          <button type="button" class="<?= attr('inline-flex h-8 w-full items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90') ?>" data-on:click="<?= $tr('start', 'Start work? The job moves to In progress.') ?>">Start work</button>
        <?php elseif ($a === JobAction::MarkDone): ?>
          <button type="button" class="<?= attr('inline-flex h-8 w-full items-center justify-center rounded-md border border-border px-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground') ?>" data-on:click="<?= $tr('done', 'Mark this job done?') ?>">Mark done</button>
        <?php elseif ($a === JobAction::Archive): ?>
          <button type="button" class="<?= attr('inline-flex h-8 w-full items-center justify-center rounded-md border border-border px-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground') ?>" data-on:click="<?= $tr('archive', 'Archive this job?') ?>">Archive</button>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$vm->canSend && !$vm->canUpdate && !$vm->canClaimAm && $vm->actions === []): ?>
        <p class="text-sm text-muted-foreground">Nothing for you to do here right now.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="<?= attr($card) ?>">
    <h3 class="text-sm font-semibold">Activity</h3>
    <?php if ($vm->activity === []): ?>
      <p class="text-sm text-muted-foreground">Nothing yet.</p>
    <?php else: ?>
    <ol class="flex flex-col gap-2 text-sm">
      <?php foreach ($vm->activity as $a): ?>
        <li><span class="font-medium"><?= e($a->actor) ?></span> <?= e($a->text) ?><div class="text-xs text-muted-foreground"><?= e($a->at) ?></div></li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </div>
</aside>
<?php
    return (string) ob_get_clean();
}

/** Dialog shells, rendered once outside the patched regions. Bodies of send/update are fetched on open. */
function partial_brief_dialogs(string $jobId): string
{
    $base = url('/jobs/' . rawurlencode($jobId));
    $post = static fn (string $action): string => "\$tr.action = '" . $action . "'; " . act_raw('post', $base . '/transition', 'filterSignals: {include: /^tr\\./}');
    $reason = static fn (string $id, string $label, string $ph): string => brief_field($id, $label, ui_textarea(new TextareaProps(id: $id, rows: 3, placeholder: $ph, attrs: ['data-bind' => 'tr.reason', 'maxlength' => '1000'])));
    $waitOptions = [];
    foreach (WaitingOn::cases() as $w) {
        $waitOptions[] = new SelectOption($w->value, $w->label());
    }
    $footer = static fn (string $dialogId, string $label, string $expr, string $variant = 'default'): string => ui_dialog_footer(new PartProps(),
        ui_dialog_close(new DialogCloseProps(dialogId: $dialogId, variant: 'outline'), 'Close') . ui_button(new ButtonProps(variant: $variant, attrs: ['data-on:click' => $expr]), e($label)));
    ob_start(); ?>
<div id="brief-dialogs">
  <?= ui_dialog(new DialogProps(id: 'send-dialog', class: 'max-w-xl'),
      ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(), 'Send to Traffic') . ui_dialog_description(new PartProps(), 'Creates version 1.0.0, turns the deliverables into assets and tells Traffic.'))
      . '<div id="send-dialog-body" class="py-4 text-sm text-muted-foreground">Loading...</div>') ?>
  <?= ui_dialog(new DialogProps(id: 'update-dialog', class: 'max-w-2xl'),
      ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(), 'Send update') . ui_dialog_description(new PartProps(), 'The team keeps working from the last sent version until you send this.'))
      . '<div id="update-dialog-body" class="py-4 text-sm text-muted-foreground">Loading...</div>') ?>
  <?= ui_dialog(new DialogProps(id: 'wait-dialog'),
      ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(), 'Put on waiting'))
      . ui_dialog_content(new PartProps(class: 'flex flex-col gap-4'),
          brief_field('wait-on', 'Waiting on', ui_native_select(new NativeSelectProps(id: 'wait-on', options: $waitOptions, placeholder: 'Choose...', attrs: ['data-bind' => 'tr.waiting_on'])))
          . $reason('wait-reason', 'What is it waiting for?', 'Awaiting hero image from the client'))
      . $footer('wait-dialog', 'Put on waiting', $post('wait'))) ?>
  <?= ui_dialog(new DialogProps(id: 'hold-dialog'),
      ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(), 'Put on hold'))
      . ui_dialog_content(new PartProps(), $reason('hold-reason', 'Why is it on hold?', 'Client paused the campaign'))
      . $footer('hold-dialog', 'Put on hold', $post('hold'))) ?>
  <?= ui_dialog(new DialogProps(id: 'cancel-dialog'),
      ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(), 'Cancel job') . ui_dialog_description(new PartProps(), 'Started assets are kept and flagged. The team is told.'))
      . ui_dialog_content(new PartProps(), $reason('cancel-reason', 'Reason', 'Client cancelled the launch'))
      . $footer('cancel-dialog', 'Cancel job', $post('cancel'), 'destructive')) ?>
</div>
<?php
    return (string) ob_get_clean();
}

/** #send-dialog-body or #update-dialog-body. */
function partial_send_dialog_body(SendDialogVM $vm): string
{
    $id = $vm->isUpdate ? 'update-dialog-body' : 'send-dialog-body';
    $dialogId = $vm->isUpdate ? 'update-dialog' : 'send-dialog';
    $base = url('/jobs/' . rawurlencode($vm->jobId) . '/brief');
    $missing = [];
    foreach ($vm->checklist as $item) {
        if (!$item->ok) {
            $missing[] = $item->message;
        }
    }
    ob_start(); ?>
<div id="<?= attr($id) ?>" class="flex flex-col gap-4 py-4 text-sm"<?= $vm->isUpdate ? ' data-signals="' . js(['send' => ['bump' => $vm->suggested, 'note' => '']]) . '"' : '' ?>>
  <?php if ($vm->problem !== ''): ?>
    <p role="alert" class="rounded-md border border-destructive px-3 py-2 text-destructive"><?= e($vm->problem) ?></p>
  <?php endif; ?>
  <?php if ($missing !== []): ?>
    <div role="alert" class="rounded-md border border-destructive/60 px-3 py-2">
      <p class="font-medium text-destructive">Not ready yet:</p>
      <ul class="mt-1 list-disc pl-5"><?php foreach ($missing as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
    </div>
  <?php elseif (!$vm->isUpdate): ?>
    <p>Send to <strong><?= e($vm->trafficName) ?></strong> as <strong>v1.0.0</strong>. This creates <?= $vm->assetsToCreate ?> <?= $vm->assetsToCreate === 1 ? 'asset' : 'assets' ?> and moves the job to Briefed. Later edits go to a working copy until you send an update.</p>
  <?php endif; ?>
  <?php if ($vm->isUpdate && $vm->diff !== null): ?>
    <div>
      <h3 class="mb-2 text-sm font-semibold">Changes since <?= e($vm->fromVersion) ?></h3>
      <div class="max-h-64 overflow-y-auto rounded-md border border-border p-3"><?= partial_brief_diff($vm->diff, $vm->showBudget, $id . '-diff') ?></div>
    </div>
    <fieldset class="flex flex-col gap-2">
      <legend class="mb-1 text-sm font-semibold">Version</legend>
      <?php foreach ($vm->bumps as $level => $label): $bl = App\Domain\BumpLevel::from($level); ?>
        <label class="flex items-start gap-2 rounded-md border border-border p-2 has-[:checked]:border-primary has-[:checked]:bg-accent/50">
          <input type="radio" name="bump" value="<?= attr($level) ?>" class="mt-1 accent-primary" data-bind="send.bump">
          <span><span class="font-medium"><?= e($bl->label()) ?>: <?= e($label) ?></span><?= $level === $vm->suggested ? ' <span class="text-xs text-primary">(suggested)</span>' : '' ?><br><span class="text-muted-foreground"><?= e($bl->description()) ?></span></span>
        </label>
      <?php endforeach; ?>
      <p class="text-xs text-muted-foreground">Suggested because: <?= e($vm->suggestedReason) ?></p>
    </fieldset>
    <?= brief_field('update-note', 'Change note for the team (required)', ui_textarea(new TextareaProps(id: 'update-note', rows: 3, placeholder: 'Two more statics; due date moved to 25 Oct', attrs: ['data-bind' => 'send.note', 'maxlength' => '1000']))) ?>
    <?php if ($vm->assetsToCreate > 0 || $vm->assetsToCancel > 0): ?>
      <p>This adds <?= $vm->assetsToCreate ?> and cancels <?= $vm->assetsToCancel ?> unstarted <?= $vm->assetsToCancel === 1 ? 'asset' : 'assets' ?>.</p>
    <?php endif; ?>
  <?php endif; ?>
  <?php foreach ($vm->warnings as $w): ?>
    <p class="rounded-md bg-accent px-3 py-2 text-accent-foreground"><?= e($w->message) ?></p>
  <?php endforeach; ?>
  <?= ui_dialog_footer(new PartProps(),
      ui_dialog_close(new DialogCloseProps(dialogId: $dialogId, variant: 'outline'), 'Close')
      . ui_button(new ButtonProps(disabled: !$vm->ready, attrs: ['data-indicator:_sending' => true, 'data-attr:disabled' => $vm->ready ? '$_sending' : 'true',
          'data-on:click' => act_raw('post', $base . ($vm->isUpdate ? '/update' : '/send'), 'filterSignals: {include: /^send\\./}, retryMaxCount: 0')]),
          $vm->isUpdate ? 'Send update' : 'Send to Traffic')) ?>
</div>
<?php
    return (string) ob_get_clean();
}
