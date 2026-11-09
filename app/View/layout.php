<?php
declare(strict_types=1);

use App\View\VM\LayoutVM;
use App\View\VM\NavItem;

/**
 * The app shell in the legacy frame: top bar, pill tab bar with a More menu,
 * demo banner, #toasts, #sheet and the
 * Datastar script. $content is already-escaped HTML for <main>.
 * Readable without app.css: plain semantic HTML in a sensible order.
 */
function layout_page(LayoutVM $vm, string $content): string
{
    ob_start(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($vm->title) ?> · Slash 301 PM</title>
<?= layout_head_assets() ?>
</head>
<body class="min-h-screen bg-background text-foreground antialiased"
      data-signals="<?= js(['_csrf' => $vm->csrf, '_net_error' => 0, '_redirect' => '']) ?>"
      data-effect="<?= attr(layout_redirect_effect()) ?>"
      data-on:datastar-fetch="evt.detail.type === 'error' &amp;&amp; ($_net_error = evt.detail.argsRaw.status || 1)">
<div class="flex min-h-screen flex-col">
  <?php if ($vm->demoMode): ?>
    <div role="alert" class="border-b border-border bg-accent px-4 py-2 text-sm text-accent-foreground md:px-6">
      <strong>Demo mode is on.</strong> Anyone can sign in as any user without a password. Turn it off before the beta.
    </div>
  <?php endif; ?>
  <header class="flex items-center justify-between gap-3 border-b border-border bg-card px-4 py-3 md:gap-6 md:px-6">
    <div class="flex min-w-0 items-baseline gap-3">
      <a href="<?= attr(url('/today')) ?>" class="shrink-0 text-lg font-bold tracking-tight hover:text-primary">Slash 301 PM</a>
      <h1 class="truncate text-sm font-medium text-muted-foreground"><?= e($vm->title) ?></h1>
    </div>
    <?php if ($vm->nav !== []): ?>
      <form role="search" method="get" action="<?= attr(url('/jobs')) ?>" class="hidden min-w-0 flex-1 justify-center md:flex">
        <input type="search" name="q" aria-label="Search jobs" placeholder="Search jobs..." class="h-10 w-full max-w-md rounded-full border border-border bg-muted px-5 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50">
      </form>
    <?php endif; ?>
    <div class="flex shrink-0 items-center gap-2">
      <button type="button" data-theme-toggle aria-label="Toggle dark mode" class="inline-flex h-9 items-center rounded-full border border-border bg-card px-4 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground">Theme</button>
      <details class="relative">
        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-border bg-card py-1 pr-4 pl-1 text-left hover:bg-accent hover:text-accent-foreground">
          <?= ui_avatar(new App\View\ui\AvatarProps(name: $vm->userName, class: 'size-8 text-xs')) ?>
          <span class="hidden leading-tight sm:block">
            <span class="block text-sm font-medium"><?= e($vm->userName) ?></span>
            <span class="block text-xs text-muted-foreground"><?= e($vm->userRole) ?></span>
          </span>
        </summary>
        <div class="absolute right-0 z-50 mt-2 w-60 rounded-xl border border-border bg-popover p-1.5 text-popover-foreground shadow-md">
          <div class="px-3 py-2 text-sm sm:hidden"><span class="font-medium"><?= e($vm->userName) ?></span> <span class="text-muted-foreground">(<?= e($vm->userRole) ?>)</span></div>
          <a href="<?= attr(url('/account/password')) ?>" class="block rounded-lg px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground">Change password</a>
          <?php if ($vm->switchGroups !== []): ?>
            <?= layout_switch_menu($vm) ?>
          <?php endif; ?>
          <form method="post" action="<?= attr(url('/logout')) ?>">
            <input type="hidden" name="_csrf" value="<?= attr($vm->csrf) ?>">
            <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground">Sign out</button>
          </form>
        </div>
      </details>
    </div>
  </header>
  <?php if ($vm->nav !== [] || $vm->adminNav !== []): ?>
    <nav aria-label="Main" class="flex items-center gap-1.5 border-b border-border bg-card px-4 py-2 md:px-6">
      <div class="flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto">
        <?php foreach ($vm->nav as $item): ?>
          <?= layout_nav_item($item, $vm->active) ?>
        <?php endforeach; ?>
      </div>
      <?= layout_more_menu($vm) ?>
    </nav>
  <?php endif; ?>
  <div role="alert" style="display: none" data-show="$_net_error" class="border-b border-border bg-muted px-4 py-2 text-sm text-destructive md:px-6">
    A request failed (<span data-text="$_net_error"></span>). Check your connection and try again.
    <button type="button" class="ml-2 underline" data-on:click="$_net_error = 0">Dismiss</button>
  </div>
  <main id="main" class="flex-1 p-4 md:p-6">
<?= $content ?>
  </main>
</div>
<?= partial_toasts_region('') ?>
<div id="sheet"></div>
</body>
</html>
<?php
    return (string) ob_get_clean();
}

/**
 * Demo user switcher inside the user menu. Only rendered when the VM carries
 * groups (App\Domain\DemoSwitch decided). One POST form per user, CSRF field included.
 */
function layout_switch_menu(LayoutVM $vm): string
{
    ob_start(); ?>
<div data-switch-menu class="my-1 max-h-80 overflow-y-auto border-y border-border py-1">
  <div class="px-3 pt-1 text-xs font-semibold text-foreground">Switch user (demo)</div>
  <?php foreach ($vm->switchGroups as $group): ?>
    <div class="px-3 pt-2 pb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"><?= e($group->role) ?></div>
    <?php foreach ($group->users as $u): ?>
      <form method="post" action="<?= attr(url('/account/switch/' . rawurlencode($u->id))) ?>">
        <input type="hidden" name="_csrf" value="<?= attr($vm->csrf) ?>">
        <?php if ($u->current): ?>
          <button type="submit" disabled aria-current="true" class="block w-full rounded-lg bg-accent px-3 py-1.5 text-left text-sm font-medium text-accent-foreground"><?= e($u->name) ?> <span class="text-xs text-muted-foreground">(you)</span></button>
        <?php else: ?>
          <button type="submit" title="<?= attr($u->username) ?>" class="block w-full rounded-lg px-3 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"><?= e($u->name) ?></button>
        <?php endif; ?>
      </form>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>
<?php
    return (string) ob_get_clean();
}

function layout_nav_item(NavItem $item, string $active): string
{
    if (!$item->enabled) {
        return '<span aria-disabled="true" class="inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-muted-foreground">'
            . e($item->label) . ' <span class="text-xs">soon</span></span>';
    }
    // The Work tab holds the grid and the board (the toggle is on the page).
    $current = $item->key === $active || ($item->key === 'jobs' && $active === 'board');
    $class = $current
        ? 'inline-flex shrink-0 items-center gap-2 rounded-full bg-accent px-4 py-2 text-sm font-medium text-accent-foreground'
        : 'inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground';
    $badge = $item->count > 0
        ? '<span class="inline-flex min-w-6 items-center justify-center rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground" data-nav-count="' . $item->count . '">'
            . ($item->count > 99 ? '99+' : (string) $item->count) . '<span class="sr-only"> need attention</span></span>'
        : '';
    return '<a href="' . attr($item->href) . '" class="' . $class . '"' . ($current ? ' aria-current="page"' : '') . '>' . e($item->label) . $badge . '</a>';
}

/** The More menu: admin pages and the old app. Sits outside the scrolling tab row so the list is never clipped. */
function layout_more_menu(LayoutVM $vm): string
{
    $current = false;
    foreach ($vm->adminNav as $item) {
        $current = $current || $item->key === $vm->active;
    }
    $summary = $current
        ? 'inline-flex cursor-pointer list-none items-center gap-2 rounded-full bg-accent px-4 py-2 text-sm font-medium text-accent-foreground'
        : 'inline-flex cursor-pointer list-none items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground';
    ob_start(); ?>
<details class="relative shrink-0" data-more-menu>
  <summary class="<?= attr($summary) ?>">More <span aria-hidden="true" class="text-xs">&#9662;</span></summary>
  <div class="absolute right-0 z-50 mt-2 w-56 rounded-xl border border-border bg-popover p-1.5 text-popover-foreground shadow-md">
    <?php foreach ($vm->adminNav as $item): ?>
      <a href="<?= attr($item->href) ?>"<?= $item->key === $vm->active ? ' aria-current="page"' : '' ?> class="<?= $item->key === $vm->active
          ? 'block rounded-lg bg-accent px-3 py-2 text-sm font-medium text-accent-foreground'
          : 'block rounded-lg px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground' ?>"><?= e($item->label) ?></a>
    <?php endforeach; ?>
    <a href="<?= attr(url('/legacy/')) ?>" class="block rounded-lg px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground">Open the old app</a>
  </div>
</details>
<?php
    return (string) ob_get_clean();
}

/**
 * Navigation for Datastar answers (App\Http\Redirect patches _redirect). Only
 * same-origin paths are followed. A signal effect instead of a patched script,
 * because the CSP blocks inline scripts.
 */
function layout_redirect_effect(): string
{
    // Cleared after use, so a page restored from the back/forward cache follows the next redirect to the same URL too.
    return "\$_redirect && (\$_redirect.startsWith('/') && !\$_redirect.startsWith('//') ? window.location.assign(\$_redirect) : null, \$_redirect = '')";
}

/** Theme first (no flash), then CSS, then Datastar. Shared by the shell and bare pages. */
function layout_head_assets(): string
{
    return '<script data-cfasync="false" src="' . attr(asset('js/theme.js')) . '"></script>' . "\n"
        . '<link rel="stylesheet" href="' . attr(asset('css/app.css')) . '">' . "\n"
        . '<script type="module" data-cfasync="false" src="' . attr(asset('js/datastar.js')) . '"></script>';
}

/** A page without the app shell (login, errors, maintenance). */
function layout_bare(string $title, string $content, string $bodySignalsJs = ''): string
{
    ob_start(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Slash 301 PM</title>
<?= layout_head_assets() ?>
</head>
<body class="min-h-screen bg-background text-foreground antialiased"<?= $bodySignalsJs !== '' ? ' data-signals="' . $bodySignalsJs . '"' : '' ?>
      data-signals:_redirect="''" data-effect="<?= attr(layout_redirect_effect()) ?>">
<main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center gap-6 p-4">
<?= $content ?>
</main>
<?= partial_toasts_region('') ?>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
