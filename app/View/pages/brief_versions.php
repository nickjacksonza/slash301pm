<?php
declare(strict_types=1);

use App\View\ui\BadgeProps;
use App\View\ui\ButtonProps;
use App\View\ui\PartProps;
use App\View\VM\BriefVersionsVM;

/** GET /jobs/{id}/brief/versions[/{v}]: list on the left, the chosen snapshot and its diff on the right. */
function page_brief_versions(BriefVersionsVM $vm): string
{
    $base = url('/jobs/' . rawurlencode($vm->jobId) . '/brief');
    ob_start(); ?>
<div class="mx-auto grid w-full max-w-7xl gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
  <div class="flex flex-col gap-4">
    <div>
      <p class="text-sm text-muted-foreground"><span class="font-mono"><?= e($vm->jobNumber) ?></span></p>
      <h2 class="text-lg font-semibold leading-tight"><?= e($vm->title) ?></h2>
      <div class="mt-2 flex gap-2"><?= ui_button(new ButtonProps(variant: 'outline', size: 'sm', href: $base), 'Back to brief') ?></div>
    </div>
    <nav aria-label="Versions" class="flex flex-col gap-1">
      <?php if ($vm->rows === []): ?><p class="text-sm text-muted-foreground">Not sent yet, so there are no versions.</p><?php endif; ?>
      <?php foreach ($vm->rows as $row): ?>
        <a href="<?= attr($row->url) ?>" class="<?= attr($row->selected ? 'block rounded-md border border-primary bg-accent px-3 py-2 text-accent-foreground' : 'block rounded-md border border-border px-3 py-2 hover:bg-muted/50') ?>"<?= $row->selected ? ' aria-current="page"' : '' ?>>
          <span class="flex items-center gap-2"><span class="font-medium"><?= e($row->version) ?></span> <?= ui_badge(new BadgeProps(variant: 'secondary'), e($row->bump)) ?></span>
          <?php if ($row->note !== ''): ?><span class="mt-1 block text-sm"><?= e($row->note) ?></span><?php endif; ?>
          <span class="mt-1 block text-xs text-muted-foreground"><?= e($row->by) ?><?= $row->by !== '' ? ' · ' : '' ?><?= e($row->at) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="flex min-w-0 flex-col gap-6">
    <?php if ($vm->diff !== null): ?>
      <?= ui_card(new PartProps(class: 'px-6'), '<h3 class="text-base font-semibold">Changes since ' . e($vm->diffAgainst) . '</h3>' . partial_brief_diff($vm->diff, $vm->showBudget, 'brief-diff', $vm->showHours)) ?>
    <?php endif; ?>
    <?php if ($vm->doc !== null): ?>
      <?= ui_card(new PartProps(class: 'px-6'), partial_brief_doc($vm->doc)) ?>
    <?php endif; ?>
  </div>
</div>
<?php
    return (string) ob_get_clean();
}
