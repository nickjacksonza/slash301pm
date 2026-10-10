<?php
declare(strict_types=1);

use App\View\VM\SystemVM;

function page_admin_system(SystemVM $vm): string
{
    $m = $vm->migrations;
    $card = 'rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm';
    ob_start(); ?>
<div class="flex max-w-4xl flex-col gap-6">
  <?php if ($vm->notice !== ''): ?>
    <p role="status" class="rounded-md bg-muted px-3 py-2 text-sm"><?= e($vm->notice) ?></p>
  <?php endif; ?>
  <?php if ($vm->demoMode): ?>
    <p role="alert" class="rounded-md border border-border bg-accent px-3 py-2 text-sm text-accent-foreground">
      <strong>Demo mode is ON</strong> (data/.demo_mode exists). Beta gate: delete that file on the server by SFTP and replace the seeded passwords.
    </p>
  <?php endif; ?>
<?= partial_beta_gate($vm->betaGate) ?>

  <section class="<?= attr($card) ?>">
    <h2 class="text-base font-semibold">Database schema</h2>
    <p class="mt-2 text-sm">
      Level <strong id="migration-level"><?= e($m->current) ?></strong> of <?= e($m->latest) ?>.
      <?= $m->isCurrent() ? 'Up to date.' : '<span class="text-destructive">Not up to date.</span>' ?>
    </p>
    <?php if ($m->modified !== []): ?>
      <p class="mt-2 text-sm text-destructive">Applied migration files changed after they ran (never edit a shipped migration): <?= e(implode(', ', array_map('strval', $m->modified))) ?></p>
    <?php endif; ?>
    <?php if ($m->failureJson !== null): ?>
      <h3 class="mt-4 text-sm font-semibold text-destructive">Last migration failed</h3>
      <pre class="mt-2 overflow-x-auto rounded-md bg-muted p-3 text-xs"><?= e($m->failureJson) ?></pre>
    <?php endif; ?>
    <?php if ($m->pending !== [] || $m->failureJson !== null): ?>
      <form method="post" action="<?= attr(url('/admin/system/migrate')) ?>" class="mt-4">
        <input type="hidden" name="_csrf" value="<?= attr($vm->csrf) ?>">
        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90">Back up and apply <?= e(count($m->pending)) ?> pending migration(s)</button>
      </form>
    <?php endif; ?>
    <table class="mt-4 w-full text-sm">
      <thead class="border-b border-border"><tr>
        <th class="py-2 text-left font-medium text-muted-foreground">Version</th>
        <th class="py-2 text-left font-medium text-muted-foreground">Name</th>
        <th class="py-2 text-left font-medium text-muted-foreground">Applied</th>
        <th class="py-2 text-right font-medium text-muted-foreground">ms</th>
      </tr></thead>
      <tbody>
      <?php foreach ($m->applied as $a): ?>
        <tr class="border-b border-border">
          <td class="py-2 font-mono text-xs"><?= e(sprintf('%04d', $a->version)) ?></td>
          <td class="py-2"><?= e($a->name) ?></td>
          <td class="py-2"><?= e($a->appliedAt) ?></td>
          <td class="py-2 text-right"><?= e($a->ms) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php foreach ($m->pending as $p): ?>
        <tr class="border-b border-border text-muted-foreground">
          <td class="py-2 font-mono text-xs"><?= e(sprintf('%04d', $p->version)) ?></td>
          <td class="py-2"><?= e($p->name) ?></td>
          <td class="py-2">pending</td>
          <td class="py-2"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="<?= attr($card) ?>">
    <h2 class="text-base font-semibold">Backups (data/backups, newest first, last 10 kept)</h2>
    <?php if ($m->backups === []): ?>
      <p class="mt-2 text-sm text-muted-foreground">No backups yet. One is made before every migration run.</p>
    <?php else: ?>
      <ul id="backup-list" class="mt-2 flex flex-col gap-1 text-sm">
        <?php foreach ($m->backups as $b): ?>
          <li><span class="font-mono text-xs"><?= e($b->name) ?></span> · <?= e(number_format($b->bytes / 1024, 1)) ?> KB · <?= e(date('Y-m-d H:i', $b->modifiedAt)) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="mt-2 text-xs text-muted-foreground">To roll back: download a backup by SFTP and upload it over data/slash301pm.db.</p>
    <?php endif; ?>
  </section>

  <section class="<?= attr($card) ?>">
    <h2 class="text-base font-semibold">Versions</h2>
    <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-sm md:grid-cols-[12rem_1fr]">
      <?php foreach ($vm->versions as $label => $value): ?>
        <dt class="text-muted-foreground"><?= e($label) ?></dt><dd class="font-mono text-xs"><?= e($value) ?></dd>
      <?php endforeach; ?>
      <dt class="text-muted-foreground">datastar.js pin</dt>
      <dd><?= $vm->datastarPinned ? 'matches public/js/datastar.js.sha256' : '<span class="text-destructive">does NOT match public/js/datastar.js.sha256</span>' ?></dd>
    </dl>
    <p class="mt-4 text-sm"><a class="underline" href="<?= attr(url('/system/spike')) ?>">Open the Datastar spike page</a> · <a class="underline" href="<?= attr(url('/healthz')) ?>">/healthz</a></p>
  </section>
</div>
<?php
    return (string) ob_get_clean();
}

/**
 * The beta gate banner (docs/beta-gate.md): the unmet items the app can
 * compute, or a green line when all of them pass. The predeploy check and the
 * legacy smoke test run outside the app and are listed as reminders.
 * @param list<\App\Domain\Types\BetaGateItem> $items
 */
function partial_beta_gate(array $items): string
{
    if ($items === []) {
        return '';
    }
    $unmet = \App\Domain\BetaChecklist::unmet($items);
    ob_start(); ?>
<section id="beta-gate" aria-labelledby="beta-gate-title" class="<?= $unmet === []
    ? 'rounded-xl border border-green-500/50 bg-green-50 p-4 text-sm text-green-900 dark:border-green-500/50 dark:bg-green-950 dark:text-green-50'
    : 'rounded-xl border border-destructive/60 bg-card p-4 text-sm text-card-foreground' ?>"<?= $unmet === [] ? '' : ' role="alert"' ?>>
  <h2 id="beta-gate-title" class="text-base font-semibold"><?= $unmet === []
      ? 'Beta gate: every check the app can make passes'
      : e('Beta gate: ' . count($unmet) . ' of ' . count($items) . ' checks not met') ?></h2>
  <?php if ($unmet !== []): ?>
    <ul class="mt-2 flex list-disc flex-col gap-1 pl-5">
      <?php foreach ($unmet as $i): ?>
        <li data-gate-item="<?= attr($i->key) ?>"><strong class="text-destructive"><?= e($i->label) ?></strong><?= $i->ownerAction ? ' <span class="text-muted-foreground">(owner action)</span>' : '' ?><?= $i->detail !== '' ? ': ' . e($i->detail) : '' ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p class="mt-2 text-xs">Also before the beta (outside the app): <span class="font-mono">php tools/predeploy.php</span> passes for the release, and the legacy smoke test passes on /legacy/. Full list: docs/beta-gate.md.</p>
</section>
<?php
    return (string) ob_get_clean();
}
