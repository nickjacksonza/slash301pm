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
        <?= ui_input(new \App\View\ui\InputProps(type: 'text', id: 'nu-name', required: true, attrs: ['data-bind' => 'new_user.name'])) ?>
      </div>
      <div class="flex flex-col gap-2">
        <label for="nu-username" class="text-sm font-medium">Username</label>
        <?= ui_input(new \App\View\ui\InputProps(type: 'text', id: 'nu-username', required: true, attrs: ['autocomplete' => 'off', 'data-bind' => 'new_user.username'])) ?>
      </div>
      <div class="flex flex-col gap-2">
        <label for="nu-email" class="text-sm font-medium">Email (optional)</label>
        <?= ui_input(new \App\View\ui\InputProps(type: 'email', id: 'nu-email', attrs: ['data-bind' => 'new_user.email'])) ?>
      </div>
      <div class="flex flex-col gap-2">
        <label for="nu-role" class="text-sm font-medium">Role</label>
        <?= ui_native_select(new \App\View\ui\NativeSelectProps(id: 'nu-role', value: 'AM', options: array_map(static fn (string $r): \App\View\ui\SelectOption => new \App\View\ui\SelectOption($r, $r), $vm->roles), attrs: ['data-bind' => 'new_user.role'])) ?>
      </div>
      <div class="flex flex-col gap-2">
        <label for="nu-password" class="text-sm font-medium">First password (12 to 128 characters)</label>
        <?= ui_input(new \App\View\ui\InputProps(type: 'password', id: 'nu-password', required: true, attrs: ['autocomplete' => 'new-password', 'minlength' => 12, 'maxlength' => 128, 'data-bind' => 'new_user.password'])) ?>
      </div>
      <div class="flex items-end">
        <?= ui_button(new \App\View\ui\ButtonProps(type: 'submit', attrs: ['data-attr:disabled' => '$_user_saving']), 'Add user') ?>
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
    ob_start(); ?>
<tr id="user-row-<?= attr($u->id) ?>" class="border-b border-border hover:bg-muted/50">
  <td class="px-3 py-2"><?= e($u->name) ?><?= $u->isSelf ? ' <span class="text-xs text-muted-foreground">(you)</span>' : '' ?></td>
  <td class="px-3 py-2 font-mono text-xs"><?= e($u->username) ?></td>
  <td class="px-3 py-2"><?= e($u->role) ?></td>
  <td class="px-3 py-2"><?= e($u->email) ?></td>
  <td class="px-3 py-2"><?= $u->isActive ? 'Active' : '<span class="text-muted-foreground">Deactivated</span>' ?></td>
  <td class="px-3 py-2 text-right whitespace-nowrap">
    <?= ui_button(new \App\View\ui\ButtonProps(variant: 'outline', size: 'sm', attrs: ['data-on:click' => 'confirm(' . js_raw('Reset the password for ' . $u->name . '?') . ') && ' . act_raw('post', $base . '/reset')]), 'Reset password') ?>
    <?php if ($u->isActive && !$u->isSelf): ?>
      <?= ui_button(new \App\View\ui\ButtonProps(variant: 'outline', size: 'sm', attrs: ['data-on:click' => 'confirm(' . js_raw('Deactivate ' . $u->name . '? They will not be able to sign in.') . ') && ' . act_raw('post', $base . '/deactivate')]), 'Deactivate') ?>
    <?php elseif (!$u->isActive): ?>
      <?= ui_button(new \App\View\ui\ButtonProps(variant: 'outline', size: 'sm', attrs: ['data-on:click' => act_raw('post', $base . '/activate')]), 'Activate') ?>
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
