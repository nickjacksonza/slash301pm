<?php
declare(strict_types=1);

use App\Domain\AssetTemplates;
use App\Domain\BriefDiff;
use App\View\VM\BriefDocVM;

/** A read-only brief (sent version or working copy). Used by the read-only view, versions and print. */
function partial_brief_doc(BriefDocVM $vm): string
{
    $s = $vm->snapshot;
    $dt = 'text-xs font-medium uppercase tracking-wide text-muted-foreground';
    ob_start(); ?>
<article id="brief-doc" class="flex flex-col gap-6 print:gap-4">
  <header class="flex flex-col gap-1 border-b border-border pb-4">
    <p class="text-sm text-muted-foreground"><?= e($vm->jobNumber) ?> · <?= e($s->brandName) ?> · <?= e($s->campaignName) ?></p>
    <h2 class="text-2xl font-semibold leading-tight"><?= e($s->title) ?></h2>
    <p class="text-sm text-muted-foreground">
      <?= $vm->isWorkingCopy ? 'Working copy (not sent)' : 'Brief ' . e($vm->versionLabel) ?><?= $vm->sentLine !== '' ? ' · ' . e($vm->sentLine) : '' ?>
    </p>
    <?php if ($vm->note !== ''): ?><p class="mt-1 text-sm"><span class="font-medium">Change note:</span> <?= e($vm->note) ?></p><?php endif; ?>
  </header>
  <dl class="grid grid-cols-2 gap-4 md:grid-cols-4">
    <div><dt class="<?= attr($dt) ?>">Brief date</dt><dd class="mt-1 text-sm"><?= e(fmt_date($s->briefDate)) ?: '<span class="text-muted-foreground">Not set</span>' ?></dd></div>
    <div><dt class="<?= attr($dt) ?>">Due date</dt><dd class="mt-1 text-sm"><?= e(fmt_date($s->dueDate)) ?: '<span class="text-muted-foreground">Not set</span>' ?></dd></div>
    <div><dt class="<?= attr($dt) ?>">First go-live</dt><dd class="mt-1 text-sm"><?= e(fmt_date($s->firstGoLive)) ?: '<span class="text-muted-foreground">Not set</span>' ?></dd></div>
    <div><dt class="<?= attr($dt) ?>">Last go-live</dt><dd class="mt-1 text-sm"><?= e(fmt_date($s->lastGoLive)) ?: '<span class="text-muted-foreground">Not set</span>' ?></dd></div>
    <?php if ($vm->showBudget): ?><div><dt class="<?= attr($dt) ?>">Budget</dt><dd class="mt-1 text-sm"><?= e(fmt_zar($s->budget)) ?: '<span class="text-muted-foreground">Not set</span>' ?></dd></div><?php endif; ?>
    <?php if ($vm->showHours): ?><div><dt class="<?= attr($dt) ?>">Hours estimate</dt><dd class="mt-1 text-sm"><?= e(fmt_num($s->hoursEstimate)) ?: '<span class="text-muted-foreground">Not set</span>' ?></dd></div><?php endif; ?>
  </dl>
  <section class="break-inside-avoid">
    <h3 class="text-base font-semibold">Creative direction</h3>
    <div class="mt-2 text-sm leading-relaxed"><?= $s->creativeDirection !== '' ? brief_text($s->creativeDirection) : '<span class="text-muted-foreground">None yet.</span>' ?></div>
  </section>
  <section class="break-inside-avoid">
    <h3 class="text-base font-semibold">Deliverables</h3>
    <?php if ($s->lines === []): ?>
      <p class="mt-2 text-sm text-muted-foreground">No deliverables.</p>
    <?php else: ?>
    <div class="mt-2 overflow-x-auto rounded-lg border border-border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left text-xs text-muted-foreground">
          <tr><th class="px-3 py-2 font-medium">Qty</th><th class="px-3 py-2 font-medium">Deliverable</th><th class="px-3 py-2 font-medium">Channel</th><th class="px-3 py-2 font-medium">Size or format</th><th class="px-3 py-2 font-medium">Specs</th><th class="px-3 py-2 font-medium">Copy</th><th class="px-3 py-2 font-medium">Due</th></tr>
        </thead>
        <tbody>
        <?php foreach ($s->lines as $l): $tpl = AssetTemplates::find($l->templateId); ?>
          <tr class="border-t border-border align-top">
            <td class="px-3 py-2 font-medium tabular-nums"><?= (int) $l->qty ?>x</td>
            <td class="px-3 py-2"><?= e($l->label) ?><?= $tpl !== null && $tpl->name !== $l->label ? '<div class="text-xs text-muted-foreground">' . e($tpl->name) . '</div>' : '' ?></td>
            <td class="px-3 py-2"><?= e($l->channel) ?></td>
            <td class="px-3 py-2"><?= e($l->sizeFormat) ?></td>
            <td class="px-3 py-2 whitespace-pre-line"><?= e($l->specs) ?></td>
            <td class="px-3 py-2"><?= $l->copyRequired ? 'Yes' : 'No' ?></td>
            <td class="px-3 py-2 whitespace-nowrap"><?= e(fmt_date($l->dueDate ?? $s->dueDate)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
  <div class="grid gap-6 md:grid-cols-2 print:grid-cols-2">
    <section class="break-inside-avoid">
      <h3 class="text-base font-semibold">Mandatories</h3>
      <?php if ($s->mandatories === []): ?><p class="mt-2 text-sm text-muted-foreground">None.</p><?php else: ?>
      <ul class="mt-2 list-disc pl-5 text-sm"><?php foreach ($s->mandatories as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
    <section class="break-inside-avoid">
      <h3 class="text-base font-semibold">References</h3>
      <?php if ($s->references === []): ?><p class="mt-2 text-sm text-muted-foreground">None.</p><?php else: ?>
      <ul class="mt-2 list-disc pl-5 text-sm"><?php foreach ($s->references as $r): ?><li><?= brief_link($r->url, $r->label) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
  </div>
  <div class="grid gap-6 md:grid-cols-2 print:grid-cols-2">
    <section class="break-inside-avoid">
      <h3 class="text-base font-semibold">Files</h3>
      <dl class="mt-2 flex flex-col gap-1 text-sm">
        <div><dt class="inline text-muted-foreground">Brief PDF:</dt> <dd class="inline"><?= $s->briefPdfUrl !== '' ? brief_link($s->briefPdfUrl) : 'None' ?></dd></div>
        <div><dt class="inline text-muted-foreground">Server folder:</dt> <dd class="inline break-all"><?= $s->serverLink !== '' ? brief_link($s->serverLink) : 'None' ?></dd></div>
      </dl>
    </section>
    <section class="break-inside-avoid">
      <h3 class="text-base font-semibold">Team</h3>
      <?php if ($s->team === []): ?><p class="mt-2 text-sm text-muted-foreground">Nobody assigned.</p><?php else: ?>
      <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <?php foreach ($s->team as $role => $h): ?><dt class="text-muted-foreground"><?= e((string) $role) ?></dt><dd><?= e($h['name']) ?></dd><?php endforeach; ?>
      </dl>
      <?php endif; ?>
    </section>
  </div>
</article>
<?php
    return (string) ob_get_clean();
}

/** The changes between two versions (or working copy vs last sent). */
function partial_brief_diff(BriefDiff $d, bool $showBudget, string $id = 'brief-diff'): string
{
    ob_start(); ?>
<div id="<?= attr($id) ?>" class="flex flex-col gap-3 text-sm">
  <?php if ($d->isEmpty()): ?>
    <p class="text-muted-foreground">No changes.</p>
  <?php endif; ?>
  <?php if ($d->fields !== []): ?>
  <ul class="flex flex-col gap-2">
    <?php foreach ($d->fields as $f): if ($f->field === 'budget' && !$showBudget) { echo '<li class="text-muted-foreground">Budget changed.</li>'; continue; } ?>
      <li class="rounded-md border border-border p-2">
        <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground"><?= e($f->label) ?></div>
        <?php if ($f->before !== ''): ?><div class="mt-1 whitespace-pre-line text-muted-foreground line-through decoration-destructive/60"><?= e(fmt_diff_value($f->before)) ?></div><?php endif; ?>
        <div class="mt-1 whitespace-pre-line"><?= $f->after !== '' ? e(fmt_diff_value($f->after)) : '<span class="text-muted-foreground">(cleared)</span>' ?></div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($d->lines !== []): ?>
  <div>
    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Deliverables</div>
    <ul class="mt-1 flex flex-col gap-1">
      <?php foreach ($d->lines as $l): ?>
        <li>
          <?= ui_badge(new App\View\ui\BadgeProps(variant: $l->kind === 'removed' ? 'destructive' : ($l->kind === 'added' ? 'default' : 'secondary')), e(ucfirst($l->kind))) ?>
          <span class="ml-1"><?= e($l->summary) ?></span>
          <?php if ($l->changes !== []): ?>
            <span class="text-muted-foreground">(<?php $parts = []; foreach ($l->changes as $c) { $parts[] = e($c->label) . ': ' . e($c->before !== '' ? fmt_diff_value($c->before) : 'empty') . ' → ' . e($c->after !== '' ? fmt_diff_value($c->after) : 'empty'); } echo implode('; ', $parts); ?>)</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
  <?php if ($d->team !== []): ?>
  <div>
    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Team</div>
    <ul class="mt-1 flex flex-col gap-1">
      <?php foreach ($d->team as $t): ?><li><?= e($t->label) ?>: <?= e($t->before !== '' ? $t->before : 'nobody') ?> → <?= e($t->after !== '' ? $t->after : 'nobody') ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
<?php
    return (string) ob_get_clean();
}
