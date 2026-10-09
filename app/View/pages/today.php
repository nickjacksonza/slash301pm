<?php
declare(strict_types=1);

/** Placeholder until Phase 4 builds the sections. */
function page_today(string $userName): string
{
    ob_start(); ?>
<section id="today" class="flex max-w-3xl flex-col gap-4">
  <div class="rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm">
    <h2 class="text-base font-semibold">Hello <?= e($userName) ?></h2>
    <p class="mt-2 text-sm text-muted-foreground">
      This is the new Slash 301 PM. "My day" (overdue, due soon, waiting on you, changed by others) arrives in a later phase.
      Until then, the full app is still at <a class="underline" href="<?= attr(url('/legacy/')) ?>">the old app</a>.
    </p>
  </div>
</section>
<?php
    return (string) ob_get_clean();
}
