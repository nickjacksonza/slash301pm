<?php
declare(strict_types=1);

use App\View\ui\BadgeProps;
use App\View\ui\ButtonProps;
use App\View\ui\DialogCloseProps;
use App\View\ui\DialogProps;
use App\View\ui\InputProps;
use App\View\ui\NativeSelectProps;
use App\View\ui\PartProps;
use App\View\VM\AssetRowVM;
use App\View\VM\JobAssetsVM;

/**
 * GET /jobs/{id}/assets (Traffic, COO, ECD): every deliverable of a sent job,
 * its assets (status, assignee, due) and their Social posts. With override
 * rights each row opens a dialog that sets ova.* (asset) or ovp.* (post) and
 * posts it; the answer re-renders #job-assets, and a one-off element closes the
 * dialogs after a successful override.
 */
function page_job_assets(JobAssetsVM $vm): string
{
    return partial_job_assets($vm);
}

function partial_job_assets(JobAssetsVM $vm): string
{
    $th = 'px-3 py-2 text-left text-xs font-medium text-muted-foreground';
    $td = 'px-3 py-2 align-top';
    $base = '/jobs/' . rawurlencode($vm->jobId);
    ob_start(); ?>
<div id="job-assets" class="mx-auto flex w-full max-w-5xl flex-col gap-6">
  <header class="flex flex-wrap items-end justify-between gap-3">
    <div class="flex flex-col gap-1">
      <p class="font-mono text-xs text-muted-foreground"><?= e($vm->jobNumber) ?><?= $vm->brandName !== '' ? ' · ' . e($vm->brandName) : '' ?></p>
      <h2 class="text-xl font-semibold leading-tight"><?= e($vm->title) ?></h2>
      <div><?= ui_badge(new BadgeProps(stage: $vm->stage->value), e($vm->stage->label())) ?></div>
    </div>
    <p class="flex flex-wrap gap-3 text-sm">
      <a class="text-primary underline-offset-4 hover:underline" href="<?= attr(url($base . '/brief')) ?>">Open the brief</a>
      <a class="text-primary underline-offset-4 hover:underline" href="<?= attr(url('/jobs')) ?>">Back to Jobs</a>
    </p>
  </header>
  <p class="text-sm text-muted-foreground">Every asset of this job, by deliverable.<?= $vm->canOverride ? ' Override a status when work has to move on outside the normal flow; a reason is required and the override is logged for the COO.' : '' ?></p>
  <?php if ($vm->groups === []): ?>
    <p class="rounded-md border border-dashed border-border px-3 py-4 text-sm text-muted-foreground">This job has no assets yet.</p>
  <?php endif; ?>
  <?php foreach ($vm->groups as $gi => $g): $hid = 'assets-group-' . $gi; ?>
    <section class="rounded-xl border border-border bg-card text-card-foreground shadow-sm" aria-labelledby="<?= attr($hid) ?>">
      <h3 id="<?= attr($hid) ?>" class="border-b border-border px-4 py-3 text-sm font-semibold"><?= e($g->label) ?> <span class="font-normal text-muted-foreground">(<?= count($g->assets) ?>)</span></h3>
      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-border"><tr>
          <th scope="col" class="<?= attr($th) ?>">Asset</th>
          <th scope="col" class="<?= attr($th) ?>">Status</th>
          <th scope="col" class="<?= attr($th) ?>">Assignee</th>
          <th scope="col" class="<?= attr($th) ?>">Due</th>
          <?php if ($vm->canOverride): ?><th scope="col" class="<?= attr($th) ?>"><span class="sr-only">Override</span></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($g->assets as $a): ?>
          <tr class="border-b border-border last:border-0">
            <td class="<?= attr($td) ?> font-mono text-xs break-all"><?= e($a->name) ?></td>
            <td class="<?= attr($td) ?>"><?= ui_badge(new BadgeProps(variant: 'outline'), e($a->status)) ?></td>
            <td class="<?= attr($td) ?>"><?= $a->assigneeName !== '' ? e($a->assigneeName) : '<span class="text-muted-foreground">Unassigned</span>' ?></td>
            <td class="<?= attr($td) ?> whitespace-nowrap"><?= $a->dueText !== '' ? e($a->dueText) : '<span class="text-muted-foreground">-</span>' ?></td>
            <?php if ($vm->canOverride): ?><td class="<?= attr($td) ?> text-right"><?= job_assets_override_button($a) ?></td><?php endif; ?>
          </tr>
          <?php foreach ($a->posts as $p): ?>
          <tr class="border-b border-border bg-muted/30 last:border-0">
            <td class="<?= attr($td) ?> pl-8 text-xs text-muted-foreground">Post on <?= e($p->platformLabel) ?></td>
            <td class="<?= attr($td) ?>"><?= ui_badge(new BadgeProps(variant: 'outline'), e($p->statusLabel)) ?></td>
            <td class="<?= attr($td) ?>"></td>
            <td class="<?= attr($td) ?>"></td>
            <?php if ($vm->canOverride): ?><td class="<?= attr($td) ?> text-right">
              <?= ui_button(new ButtonProps(variant: 'ghost', size: 'sm', attrs: ['aria-label' => 'Override the ' . $p->platformLabel . ' post of ' . $a->name,
                  'data-on:click' => '$ovp.pub_id = ' . jobs_js($p->id) . '; $ovp.rv = ' . (int) $p->rowVersion . '; $ovp.name = ' . jobs_js($p->platformLabel . ' post of ' . $a->name)
                  . '; $ovp.from = ' . jobs_js($p->status) . '; $ovp.to = ' . jobs_js($p->status) . "; \$ovp.reason = ''; \$override_post.open = true"]), 'Override') ?>
            </td><?php endif; ?>
          </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </section>
  <?php endforeach; ?>
  <?php if ($vm->savedNonce !== ''): ?>
    <div id="assets-saved-<?= attr($vm->savedNonce) ?>" hidden data-init="$override_asset.open = false; $override_post.open = false"></div>
  <?php endif; ?>
  <?php if ($vm->canOverride): ?>
    <?= job_assets_dialog('override-asset', 'ova', 'Override asset status', $vm->assetStatuses, url($base . '/assets/override'),
        ['asset_id' => '', 'name' => '', 'from' => '', 'to' => '', 'reason' => '']) ?>
    <?= job_assets_dialog('override-post', 'ovp', 'Override post status', $vm->postStatuses, url($base . '/publications/override'),
        ['pub_id' => '', 'rv' => 0, 'name' => '', 'from' => '', 'to' => '', 'reason' => '']) ?>
  <?php endif; ?>
</div>
<?php
    return (string) ob_get_clean();
}

function job_assets_override_button(AssetRowVM $a): string
{
    return ui_button(new ButtonProps(variant: 'outline', size: 'sm', attrs: ['aria-label' => 'Override the status of ' . $a->name,
        'data-on:click' => '$ova.asset_id = ' . jobs_js($a->id) . '; $ova.name = ' . jobs_js($a->name) . '; $ova.from = ' . jobs_js($a->status)
            . '; $ova.to = ' . jobs_js($a->status) . "; \$ova.reason = ''; \$override_asset.open = true"]), 'Override');
}

/**
 * One override dialog. $root is the signal namespace (ova or ovp); the save
 * button stays disabled until a reason is typed and the status changed.
 * @param list<\App\View\ui\SelectOption> $options
 * @param array<string,string|int> $signals
 */
function job_assets_dialog(string $id, string $root, string $title, array $options, string $postUrl, array $signals): string
{
    $s = '$' . $root;
    return ui_dialog(new DialogProps(id: $id),
        '<div data-signals="' . js([$root => $signals]) . '">'
        . ui_dialog_header(new PartProps(), ui_dialog_title(new PartProps(), e($title))
            . ui_dialog_description(new PartProps(), '<span class="font-mono text-xs" data-text="' . attr($s . '.name') . '"></span>'))
        . ui_dialog_content(new PartProps(class: 'flex flex-col gap-4'),
            brief_field($id . '-to', 'New status', ui_native_select(new NativeSelectProps(id: $id . '-to', options: $options, attrs: ['data-bind' => $root . '.to'])))
            . brief_field($id . '-reason', 'Reason (required, shown in the activity log and the COO report)',
                ui_input(new InputProps(id: $id . '-reason', type: 'text', placeholder: 'Client deadline moved; publishing now', attrs: ['data-bind' => $root . '.reason', 'maxlength' => '1000']))))
        . ui_dialog_footer(new PartProps(), ui_dialog_close(new DialogCloseProps(dialogId: $id, variant: 'outline'), 'Cancel')
            . ui_button(new ButtonProps(attrs: [
                'data-attr:disabled' => '!' . $s . '.reason.trim() || ' . $s . '.to === ' . $s . '.from',
                'data-on:click' => act_raw('post', $postUrl, 'filterSignals: {include: /^' . $root . '\\./}, retryMaxCount: 0'),
            ]), 'Override status'))
        . '</div>');
}
