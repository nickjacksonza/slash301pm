<?php
declare(strict_types=1);

use App\View\ui\ButtonProps;
use App\View\ui\DialogCloseProps;
use App\View\ui\DialogProps;
use App\View\ui\DialogTriggerProps;
use App\View\ui\InputProps;
use App\View\ui\NativeSelectProps;
use App\View\ui\PartProps;
use App\View\ui\TextareaProps;
use App\View\VM\CampaignsVM;

/** GET /campaigns. The panel (with its dialog) is re-patched after a create, which also closes the dialog. */
function page_campaigns(CampaignsVM $vm): string
{
    return partial_campaigns_panel($vm);
}

function partial_campaigns_panel(CampaignsVM $vm): string
{
    ob_start(); ?>
<div id="campaigns-panel" class="mx-auto flex w-full max-w-5xl flex-col gap-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-muted-foreground">Every brand and its campaigns. Start a brief from a campaign.</p>
    <?php if ($vm->canManage): ?>
      <?= ui_dialog_trigger(new DialogTriggerProps(dialogId: 'new-campaign', asChild: true), ui_button(new ButtonProps(), 'New campaign')) ?>
    <?php endif; ?>
  </div>
  <?php if ($vm->notice !== ''): ?><p role="status" class="rounded-md bg-muted px-3 py-2 text-sm"><?= e($vm->notice) ?></p><?php endif; ?>
  <?php foreach ($vm->groups as $g): ?>
    <section class="bg-card border border-border flex flex-col gap-3 p-6 rounded-xl shadow-sm text-card-foreground" aria-labelledby="brand-<?= attr($g->brandId) ?>">
      <h2 id="brand-<?= attr($g->brandId) ?>" class="text-base font-semibold"><?= e($g->brandName) ?> <span class="ml-1 font-mono text-xs font-normal text-muted-foreground"><?= e($g->prefix) ?></span></h2>
      <?php if ($g->campaigns === []): ?>
        <p class="text-sm text-muted-foreground">No campaigns yet.</p>
      <?php else: ?>
      <ul class="divide-y divide-border rounded-lg border border-border">
        <?php foreach ($g->campaigns as $c): ?>
          <li class="flex flex-wrap items-center gap-3 px-3 py-2.5">
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium"><?= e($c->name) ?></span>
              <?php if ($c->description !== ''): ?><span class="block truncate text-xs text-muted-foreground"><?= e($c->description) ?></span><?php endif; ?>
            </span>
            <span class="text-xs text-muted-foreground"><?= $c->jobCount ?> <?= $c->jobCount === 1 ? 'job' : 'jobs' ?></span>
            <?php if ($vm->canCreateBrief): ?>
              <?= ui_button(new ButtonProps(variant: 'outline', size: 'sm', attrs: ['data-on:click' => '$nb.campaign_id = ' . json_encode($c->id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR) . '; ' . act_raw('post', url('/briefs'), 'filterSignals: {include: /^nb\\./}, retryMaxCount: 0')]), 'New brief') ?>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  <div data-signals="<?= js(['nb' => ['campaign_id' => '', 'title' => '']]) ?>"></div>
  <?php if ($vm->createdId !== ''): ?>
    <?php /* A new element runs data-init once; an unchanged data-signals attribute would not re-apply, so this is how the dialog closes. */ ?>
    <div id="campaign-created-<?= attr($vm->createdId) ?>" hidden data-init="$new_campaign.open = false"></div>
  <?php endif; ?>
  <?php if ($vm->canManage): ?>
  <?= ui_dialog(new DialogProps(id: 'new-campaign'),
      '<div data-signals="' . js(['nc' => ['brand_id' => '', 'name' => '', 'description' => '']]) . '">'
      . ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(), 'New campaign'))
      . ui_dialog_content(new PartProps(class: 'flex flex-col gap-4'),
          brief_field('nc-brand', 'Brand', ui_native_select(new NativeSelectProps(id: 'nc-brand', options: $vm->brandOptions, placeholder: 'Choose a brand...', attrs: ['data-bind' => 'nc.brand_id'])))
          . brief_field('nc-name', 'Name', ui_input(new InputProps(id: 'nc-name', type: 'text', placeholder: 'Summer Launch 2027', attrs: ['data-bind' => 'nc.name', 'maxlength' => '100'])))
          . brief_field('nc-desc', 'Description (optional)', ui_textarea(new TextareaProps(id: 'nc-desc', rows: 3, attrs: ['data-bind' => 'nc.description', 'maxlength' => '2000']))))
      . ui_dialog_footer(new PartProps(), ui_dialog_close(new DialogCloseProps(dialogId: 'new-campaign', variant: 'outline'), 'Close')
          . ui_button(new ButtonProps(attrs: ['data-on:click' => act_raw('post', url('/campaigns'), 'filterSignals: {include: /^nc\\./}, retryMaxCount: 0')]), 'Create campaign'))
      . '</div>') ?>
  <?php endif; ?>
</div>
<?php
    return (string) ob_get_clean();
}
