<?php
declare(strict_types=1);

use App\Domain\Types\BriefListItem;
use App\View\ui\BadgeProps;
use App\View\ui\ButtonProps;
use App\View\ui\DialogCloseProps;
use App\View\ui\DialogProps;
use App\View\ui\DialogTriggerProps;
use App\View\ui\InputProps;
use App\View\ui\NativeSelectProps;
use App\View\ui\PartProps;
use App\View\VM\MyBriefsVM;

/** GET /briefs: the AM's starting point until /today (Phase 4). */
function page_my_briefs(MyBriefsVM $vm): string
{
    ob_start(); ?>
<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-muted-foreground">Drafts, briefs with unsent changes and what you sent recently. Jobs you hold as AM, PM or Producer, or briefs you created.</p>
    <?php if ($vm->canCreate): ?>
      <?= ui_dialog_trigger(new DialogTriggerProps(dialogId: 'new-brief', asChild: true), ui_button(new ButtonProps(), 'New brief')) ?>
    <?php endif; ?>
  </div>
  <?= partial_brief_list('drafts', 'Drafts', 'Not sent yet.', $vm->drafts, 'No drafts. Start one with New brief.') ?>
  <?= partial_brief_list('unsent', 'Unsent changes', 'Edited after sending; the team still works from the last sent version.', $vm->unsent, 'Nothing waiting to be sent.') ?>
  <?= partial_brief_list('sent', 'Sent', 'Most recent first.', $vm->sent, 'Nothing sent yet.') ?>
  <?php if ($vm->canClaim): ?>
    <?= partial_brief_list('unowned', 'Jobs without an AM', 'Older jobs from the old app have no AM. Open one and choose "Make me AM" to take it over.', $vm->unowned, 'Every open job has an AM.') ?>
  <?php endif; ?>
</div>
<?php if ($vm->canCreate): ?>
<?= ui_dialog(new DialogProps(id: 'new-brief'),
    '<div data-signals="' . js(['nb' => ['campaign_id' => '', 'title' => '']]) . '">'
    . ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(), 'New brief') . ui_dialog_description(new PartProps(), 'Pick the campaign; you can fill in the rest on the next page.'))
    . ui_dialog_content(new PartProps(class: 'flex flex-col gap-4'),
        brief_field('nb-campaign', 'Campaign', ui_native_select(new NativeSelectProps(id: 'nb-campaign', options: $vm->campaignOptions, placeholder: 'Choose a campaign...', attrs: ['data-bind' => 'nb.campaign_id'])),
            'Missing one? Add it on the Campaigns page.')
        . brief_field('nb-title', 'Working title', ui_input(new InputProps(id: 'nb-title', type: 'text', placeholder: 'New brief', attrs: ['data-bind' => 'nb.title', 'maxlength' => '200']))))
    . ui_dialog_footer(new PartProps(), ui_dialog_close(new DialogCloseProps(dialogId: 'new-brief', variant: 'outline'), 'Close')
        . ui_button(new ButtonProps(attrs: ['data-indicator:_creating' => true, 'data-attr:disabled' => '$_creating', 'data-on:click' => act_raw('post', url('/briefs'), 'filterSignals: {include: /^nb\\./}, retryMaxCount: 0')]), 'Create draft'))
    . '</div>') ?>
<?php endif; ?>
<?php
    return (string) ob_get_clean();
}

/** @param list<BriefListItem> $items */
function partial_brief_list(string $id, string $title, string $intro, array $items, string $empty): string
{
    ob_start(); ?>
<section id="briefs-<?= attr($id) ?>" class="bg-card border border-border flex flex-col gap-3 p-6 rounded-xl shadow-sm text-card-foreground" aria-labelledby="h-<?= attr($id) ?>">
  <div class="flex items-baseline justify-between gap-2">
    <h2 id="h-<?= attr($id) ?>" class="text-base font-semibold"><?= e($title) ?> <span class="text-sm font-normal text-muted-foreground">(<?= count($items) ?>)</span></h2>
  </div>
  <p class="text-sm text-muted-foreground"><?= e($intro) ?></p>
  <?php if ($items === []): ?>
    <p class="rounded-md border border-dashed border-border px-3 py-4 text-sm text-muted-foreground"><?= e($empty) ?></p>
  <?php else: ?>
  <ul class="divide-y divide-border rounded-lg border border-border">
    <?php foreach ($items as $it): ?>
      <li>
        <a href="<?= attr(url('/jobs/' . rawurlencode($it->jobId) . '/brief')) ?>" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2.5 hover:bg-muted/50">
          <span class="w-20 shrink-0 font-mono text-xs text-muted-foreground"><?= e($it->jobNumber) ?></span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium"><?= e($it->title) ?></span>
            <span class="block truncate text-xs text-muted-foreground"><?= e($it->brandName) ?> · <?= e($it->campaignName) ?></span>
          </span>
          <span class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
            <?php if ($it->dueDate !== null): ?><span>Due <?= e(fmt_date($it->dueDate)) ?></span><?php endif; ?>
            <?php if ($it->sent): ?><?= ui_badge(new BadgeProps(variant: 'outline'), e($it->version->label())) ?><?php endif; ?>
            <?= ui_badge(new BadgeProps(stage: $it->stage->value), e($it->stage->label())) ?>
          </span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php
    return (string) ob_get_clean();
}
