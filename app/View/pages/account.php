<?php
declare(strict_types=1);

function page_account_password(string $csrf, string $notice, string $error = ''): string
{
    return '<div class="max-w-md">' . partial_account_password_panel($csrf, $notice, $error) . '</div>';
}

/**
 * The whole panel is one patch target: re-patching it re-runs data-signals,
 * which clears the password signals after a successful change.
 */
function partial_account_password_panel(string $csrf, string $notice, string $error): string
{
    $input = 'h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';
    ob_start(); ?>
<section id="pw-panel" class="rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm"
         data-signals="<?= js(['pw' => ['current' => '', 'new' => '', 'confirm' => '']]) ?>">
  <h2 class="text-base font-semibold">Change your password</h2>
  <p class="mt-1 text-sm text-muted-foreground">12 to 128 characters. The same password works in the old app.</p>
  <?php if ($notice !== ''): ?>
    <p role="status" class="mt-3 rounded-md bg-muted px-3 py-2 text-sm"><?= e($notice) ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p role="alert" class="mt-3 rounded-md border border-destructive px-3 py-2 text-sm text-destructive"><?= e($error) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= attr(url('/account/password')) ?>" class="mt-4 flex flex-col gap-4"
        data-indicator:_pw_saving
        data-on:submit="<?= act('post', url('/account/password'), 'filterSignals: {include: /^pw\\./}') ?>">
    <input type="hidden" name="_csrf" value="<?= attr($csrf) ?>">
    <div class="flex flex-col gap-2">
      <label for="pw-current" class="text-sm font-medium">Current password</label>
      <input id="pw-current" name="current" type="password" autocomplete="current-password" required data-bind="pw.current" class="<?= attr($input) ?>">
    </div>
    <div class="flex flex-col gap-2">
      <label for="pw-new" class="text-sm font-medium">New password</label>
      <input id="pw-new" name="new" type="password" autocomplete="new-password" required minlength="12" maxlength="128" data-bind="pw.new" class="<?= attr($input) ?>">
    </div>
    <div class="flex flex-col gap-2">
      <label for="pw-confirm" class="text-sm font-medium">New password again</label>
      <input id="pw-confirm" name="confirm" type="password" autocomplete="new-password" required minlength="12" maxlength="128" data-bind="pw.confirm" class="<?= attr($input) ?>">
    </div>
    <button type="submit" data-attr:disabled="$_pw_saving" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90 disabled:opacity-50">Change password</button>
  </form>
</section>
<?php
    return (string) ob_get_clean();
}
