<?php
declare(strict_types=1);

use App\View\VM\LoginVM;

/** Plain HTML forms, so sign-in works before Datastar or the CSS load. */
function page_login(LoginVM $vm): string
{
    ob_start(); ?>
<div class="rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm">
  <h1 class="text-xl font-semibold">Sign in to Slash 301 PM</h1>
  <?php if ($vm->notice !== ''): ?>
    <p role="status" class="mt-3 rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground"><?= e($vm->notice) ?></p>
  <?php endif; ?>
  <?php if ($vm->error !== ''): ?>
    <p role="alert" id="login-error" class="mt-3 rounded-md border border-destructive px-3 py-2 text-sm text-destructive"><?= e($vm->error) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= attr(url('/login')) ?>" class="mt-4 flex flex-col gap-4">
    <input type="hidden" name="_csrf" value="<?= attr($vm->csrf) ?>">
    <div class="flex flex-col gap-2">
      <label for="username" class="text-sm font-medium">Username</label>
      <?= ui_input(new \App\View\ui\InputProps(type: 'text', id: 'username', name: 'username', value: $vm->username, required: true, attrs: ['autocomplete' => 'username', 'autofocus' => true])) ?>
    </div>
    <div class="flex flex-col gap-2">
      <label for="password" class="text-sm font-medium">Password</label>
      <?= ui_input(new \App\View\ui\InputProps(type: 'password', id: 'password', name: 'password', required: true, attrs: ['autocomplete' => 'current-password'])) ?>
    </div>
    <?= ui_button(new \App\View\ui\ButtonProps(type: 'submit'), 'Sign in') ?>
  </form>
</div>
<?php if ($vm->demoMode): ?>
<div class="rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm">
  <h2 class="text-base font-semibold">Demo mode: sign in as</h2>
  <p class="mt-1 text-sm text-muted-foreground">No password needed while demo mode is on.</p>
  <form method="post" action="<?= attr(url('/demo-login')) ?>" class="mt-4 flex flex-col gap-4">
    <input type="hidden" name="_csrf" value="<?= attr($vm->csrf) ?>">
    <?php foreach ($vm->demoGroups as $group): ?>
      <fieldset class="flex flex-col gap-2">
        <legend class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"><?= e($group->role) ?></legend>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($group->users as $u): ?>
            <?= ui_button(new \App\View\ui\ButtonProps(variant: 'outline', size: 'sm', type: 'submit', attrs: ['name' => 'user_id', 'value' => $u->id, 'title' => $u->username]), e($u->name)) ?>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
  </form>
</div>
<?php endif; ?>
<?php
    return layout_bare('Sign in', (string) ob_get_clean());
}
