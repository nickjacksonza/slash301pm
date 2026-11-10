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
      <?= ui_input(new \App\View\ui\InputProps(type: 'password', id: 'pw-current', name: 'current', required: true, attrs: ['autocomplete' => 'current-password', 'data-bind' => 'pw.current'])) ?>
    </div>
    <div class="flex flex-col gap-2">
      <label for="pw-new" class="text-sm font-medium">New password</label>
      <?= ui_input(new \App\View\ui\InputProps(type: 'password', id: 'pw-new', name: 'new', required: true, attrs: ['autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 128, 'data-bind' => 'pw.new'])) ?>
    </div>
    <div class="flex flex-col gap-2">
      <label for="pw-confirm" class="text-sm font-medium">New password again</label>
      <?= ui_input(new \App\View\ui\InputProps(type: 'password', id: 'pw-confirm', name: 'confirm', required: true, attrs: ['autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 128, 'data-bind' => 'pw.confirm'])) ?>
    </div>
    <?= ui_button(new \App\View\ui\ButtonProps(type: 'submit', attrs: ['data-attr:disabled' => '$_pw_saving']), 'Change password') ?>
  </form>
</section>
<?php
    return (string) ob_get_clean();
}
