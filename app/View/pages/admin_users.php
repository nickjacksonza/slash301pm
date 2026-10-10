<?php
declare(strict_types=1);

use App\View\VM\AdminUsersVM;
use App\View\VM\UserRowVM;

function page_admin_users(AdminUsersVM $vm, string $notice): string
{
    return partial_admin_user_notice('') . partial_admin_users_panel($vm, $notice);
}

/** Patched whole after a create: re-running data-signals clears the form. */
function partial_admin_users_panel(AdminUsersVM $vm, string $notice): string
{
    $input = 'h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';
    ob_start(); ?>
<section id="users-panel" class="flex flex-col gap-6"
         data-signals="<?= js(['new_user' => ['name' => '', 'username' => '', 'email' => '', 'role' => 'AM', 'password' => '']]) ?>">
  <?php if ($notice !== ''): ?>
    <p role="status" class="rounded-md bg-muted px-3 py-2 text-sm"><?= e($notice) ?></p>
  <?php endif; ?>
  <div class="rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm">
    <h2 class="text-base font-semibold">Add a user</h2>
    <form class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2"
          data-indicator:_user_saving
          data-on:submit="<?= act('post', url('/admin/users'), 'filterSignals: {include: /^new_user\\./}') ?>">
      <div class="flex flex-col gap-2">
        <label for="nu-name" class="text-sm font-medium">Name</label>
        <input id="nu-name" type="text" required data-bind="new_user.name" class="<?= attr($input) ?>">
      </div>
      <div class="flex flex-col gap-2">
        <label for="nu-username" class="text-sm font-medium">Username</label>
        <input id="nu-username" type="text" required autocomplete="off" data-bind="new_user.username" class="<?= attr($input) ?>">
      </div>
      <div class="flex flex-col gap-2">
        <label for="nu-email" class="text-sm font-medium">Email (optional)</label>
        <input id="nu-email" type="email" data-bind="new_user.email" class="<?= attr($input) ?>">
      </div>
      <div class="flex flex-col gap-2">
        <label for="nu-role" class="text-sm font-medium">Role</label>
        <select id="nu-role" data-bind="new_user.role" class="<?= attr($input) ?>">
          <?php foreach ($vm->roles as $role): ?>
            <option value="<?= attr($role) ?>"><?= e($role) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex flex-col gap-2">
        <label for="nu-password" class="text-sm font-medium">First password (12 to 128 characters)</label>
        <input id="nu-password" type="password" required minlength="12" maxlength="128" autocomplete="new-password" data-bind="new_user.password" class="<?= attr($input) ?>">
      </div>
      <div class="flex items-end">
        <button type="submit" data-attr:disabled="$_user_saving" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90 disabled:opacity-50">Add user</button>
      </div>
    </form>
  </div>
  <div class="overflow-x-auto rounded-xl border border-border bg-card text-card-foreground shadow-sm">
    <table class="w-full caption-bottom text-sm">
      <thead class="border-b border-border">
        <tr>
          <th class="h-10 px-3 text-left font-medium text-muted-foreground">Name</th>
          <th class="h-10 px-3 text-left font-medium text-muted-foreground">Username</th>
          <th class="h-10 px-3 text-left font-medium text-muted-foreground">Role</th>
          <th class="h-10 px-3 text-left font-medium text-muted-foreground">Email</th>
          <th class="h-10 px-3 text-left font-medium text-muted-foreground">Status</th>
          <th class="h-10 px-3 text-right font-medium text-muted-foreground">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($vm->rows as $row): ?>
          <?= partial_admin_user_row($row) ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php
    return (string) ob_get_clean();
}

function partial_admin_user_row(UserRowVM $u): string
{
    $base = url('/admin/users/' . rawurlencode($u->id));
    $btn = 'inline-flex h-8 items-center rounded-md border border-border px-3 text-xs hover:bg-accent hover:text-accent-foreground';
    ob_start(); ?>
<tr id="user-row-<?= attr($u->id) ?>" class="border-b border-border hover:bg-muted/50">
  <td class="px-3 py-2"><?= e($u->name) ?><?= $u->isSelf ? ' <span class="text-xs text-muted-foreground">(you)</span>' : '' ?></td>
  <td class="px-3 py-2 font-mono text-xs"><?= e($u->username) ?></td>
  <td class="px-3 py-2"><?= e($u->role) ?></td>
  <td class="px-3 py-2"><?= e($u->email) ?></td>
  <td class="px-3 py-2"><?= $u->isActive ? 'Active' : '<span class="text-muted-foreground">Deactivated</span>' ?></td>
  <td class="px-3 py-2 text-right whitespace-nowrap">
    <button type="button" class="<?= attr($btn) ?>"
            data-on:click="confirm(<?= js('Reset the password for ' . $u->name . '?') ?>) &amp;&amp; <?= act('post', $base . '/reset') ?>">Reset password</button>
    <?php if ($u->isActive && !$u->isSelf): ?>
      <button type="button" class="<?= attr($btn) ?>"
              data-on:click="confirm(<?= js('Deactivate ' . $u->name . '? They will not be able to sign in.') ?>) &amp;&amp; <?= act('post', $base . '/deactivate') ?>">Deactivate</button>
    <?php elseif (!$u->isActive): ?>
      <button type="button" class="<?= attr($btn) ?>" data-on:click="<?= act('post', $base . '/activate') ?>">Activate</button>
    <?php endif; ?>
  </td>
</tr>
<?php
    return (string) ob_get_clean();
}

function partial_admin_user_notice(string $message): string
{
    if ($message === '') {
        return '<div id="user-notice"></div>';
    }
    return '<div id="user-notice" role="status" class="mb-6 rounded-md border border-border bg-accent px-3 py-2 font-mono text-sm text-accent-foreground">' . e($message) . '</div>';
}
