<?php
declare(strict_types=1);

use App\Domain\PublicationStatus;
use App\View\VM\OverridesVM;

/**
 * GET /admin/overrides (COO): every asset or post status override in a date
 * range, newest first, for workflow reviews. A plain GET form (no signals).
 */
function page_admin_overrides(OverridesVM $vm): string
{
    $th = 'px-3 py-2 text-left text-xs font-medium text-muted-foreground';
    $td = 'px-3 py-2 align-top';
    $status = static function (string $kind, string $v): string {
        $p = $kind === 'publication' ? PublicationStatus::tryFrom($v) : null;
        return $p !== null ? $p->label() : $v;
    };
    ob_start(); ?>
<div id="overrides-report" class="mx-auto flex w-full max-w-6xl flex-col gap-6">
  <p class="text-sm text-muted-foreground">Status overrides by Traffic, the COO and the ECD: who forced which asset or post from one status to another, and why. Use it in workflow reviews.</p>
  <form method="get" action="<?= attr(url('/admin/overrides')) ?>" class="flex flex-wrap items-end gap-3">
    <div class="flex flex-col gap-1">
      <label for="ov-from" class="text-xs font-medium text-muted-foreground">From</label>
      <input id="ov-from" name="from" type="date" value="<?= attr($vm->fromDate) ?>" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs">
    </div>
    <div class="flex flex-col gap-1">
      <label for="ov-to" class="text-xs font-medium text-muted-foreground">To</label>
      <input id="ov-to" name="to" type="date" value="<?= attr($vm->toDate) ?>" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs">
    </div>
    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90">Show</button>
  </form>
  <section class="rounded-xl border border-border bg-card text-card-foreground shadow-sm" aria-labelledby="ov-title">
    <h2 id="ov-title" class="border-b border-border px-4 py-3 text-sm font-semibold"><?= count($vm->rows) ?><?= $vm->capped ? '+' : '' ?> <?= count($vm->rows) === 1 ? 'override' : 'overrides' ?>, <?= e(fmt_date($vm->fromDate)) ?> to <?= e(fmt_date($vm->toDate)) ?></h2>
    <?php if ($vm->rows === []): ?>
      <p class="px-4 py-6 text-sm text-muted-foreground">No overrides in this period.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="border-b border-border"><tr>
        <th scope="col" class="<?= attr($th) ?>">When</th>
        <th scope="col" class="<?= attr($th) ?>">Who</th>
        <th scope="col" class="<?= attr($th) ?>">Job</th>
        <th scope="col" class="<?= attr($th) ?>">Asset</th>
        <th scope="col" class="<?= attr($th) ?>">From → to</th>
        <th scope="col" class="<?= attr($th) ?>">Reason</th>
      </tr></thead>
      <tbody>
      <?php foreach ($vm->rows as $o): ?>
        <tr class="border-b border-border last:border-0">
          <td class="<?= attr($td) ?> whitespace-nowrap"><?= e(fmt_when($o->createdAt)) ?></td>
          <td class="<?= attr($td) ?>"><?= e($o->actorName !== '' ? $o->actorName : 'Someone') ?><?= $o->actorRole !== '' ? ' <span class="text-xs text-muted-foreground">' . e($o->actorRole) . '</span>' : '' ?></td>
          <td class="<?= attr($td) ?>">
            <?php if ($o->jobId !== null): ?>
              <a class="text-primary underline-offset-4 hover:underline" href="<?= attr(url('/jobs/' . rawurlencode($o->jobId) . '/assets')) ?>"><span class="font-mono text-xs"><?= e($o->jobNumber) ?></span></a>
              <span class="block text-xs text-muted-foreground"><?= e($o->jobTitle) ?></span>
            <?php endif; ?>
          </td>
          <td class="<?= attr($td) ?> font-mono text-xs break-all"><?= e($o->assetName) ?><?= $o->platform !== '' ? ' <span class="font-sans text-muted-foreground">(' . e(\App\Domain\Platform::tryFrom($o->platform)?->label() ?? $o->platform) . ' post)</span>' : '' ?></td>
          <td class="<?= attr($td) ?> whitespace-nowrap"><?= e($status($o->kind, $o->from)) ?> → <strong class="font-medium"><?= e($status($o->kind, $o->to)) ?></strong></td>
          <td class="<?= attr($td) ?>"><?= e($o->reason) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php
    return (string) ob_get_clean();
}
