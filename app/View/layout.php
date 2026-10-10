<?php
declare(strict_types=1);

use App\View\VM\LayoutVM;
use App\View\VM\NavItem;

/**
 * The app shell: sidebar nav, top bar, demo banner, #toasts, #sheet and the
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
      data-signals="<?= js(['_csrf' => $vm->csrf, '_net_error' => 0]) ?>"
      data-on:datastar-fetch="evt.detail.type === 'error' &amp;&amp; ($_net_error = evt.detail.argsRaw.status || 1)">
<div class="flex min-h-screen flex-col md:flex-row">
  <aside class="w-full shrink-0 border-b border-border bg-sidebar text-sidebar-foreground md:w-60 md:border-r md:border-b-0">
    <div class="flex h-14 items-center px-4 text-base font-semibold">
      <a href="<?= attr(url('/today')) ?>" class="hover:underline">Slash 301 PM</a>
    </div>
    <nav aria-label="Main" class="flex flex-row flex-wrap gap-1 px-2 pb-2 md:flex-col md:pb-4">
      <?php foreach ($vm->nav as $item): ?>
        <?= layout_nav_item($item, $vm->active) ?>
      <?php endforeach; ?>
      <?php if ($vm->adminNav !== []): ?>
        <div class="mt-2 hidden px-3 text-xs font-medium uppercase tracking-wide text-muted-foreground md:block">Admin</div>
        <?php foreach ($vm->adminNav as $item): ?>
          <?= layout_nav_item($item, $vm->active) ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>
  </aside>
  <div class="flex min-w-0 flex-1 flex-col">
    <?php if ($vm->demoMode): ?>
      <div role="alert" class="border-b border-border bg-accent px-4 py-2 text-sm text-accent-foreground">
        <strong>Demo mode is on.</strong> Anyone can sign in as any user without a password. Turn it off before the beta.
      </div>
    <?php endif; ?>
    <div role="alert" style="display: none" data-show="$_net_error" class="border-b border-border bg-muted px-4 py-2 text-sm text-destructive">
      A request failed (<span data-text="$_net_error"></span>). Check your connection and try again.
      <button type="button" class="ml-2 underline" data-on:click="$_net_error = 0">Dismiss</button>
    </div>
    <header class="flex h-14 items-center justify-between gap-4 border-b border-border px-4">
      <h1 class="truncate text-lg font-semibold"><?= e($vm->title) ?></h1>
      <div class="flex items-center gap-2">
        <button type="button" data-theme-toggle aria-label="Toggle dark mode" class="inline-flex h-9 items-center rounded-md border border-border px-3 text-sm hover:bg-accent hover:text-accent-foreground">Theme</button>
        <details class="relative">
          <summary class="inline-flex h-9 cursor-pointer list-none items-center rounded-md border border-border px-3 text-sm hover:bg-accent hover:text-accent-foreground">
            <?= e($vm->userName) ?> <span class="ml-1 text-muted-foreground">(<?= e($vm->userRole) ?>)</span>
          </summary>
          <div class="absolute right-0 z-50 mt-1 w-56 rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md">
            <a href="<?= attr(url('/account/password')) ?>" class="block rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground">Change password</a>
            <a href="<?= attr(url('/legacy/')) ?>" class="block rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground">Open the old app</a>
            <form method="post" action="<?= attr(url('/logout')) ?>">
              <input type="hidden" name="_csrf" value="<?= attr($vm->csrf) ?>">
              <button type="submit" class="block w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground">Sign out</button>
            </form>
          </div>
        </details>
      </div>
    </header>
    <main id="main" class="flex-1 p-4 md:p-6">
<?= $content ?>
    </main>
  </div>
</div>
<?= partial_toasts_region('') ?>
<div id="sheet"></div>
</body>
</html>
<?php
    return (string) ob_get_clean();
}

function layout_nav_item(NavItem $item, string $active): string
{
    if (!$item->enabled) {
        return '<span aria-disabled="true" class="flex items-center justify-between rounded-md px-3 py-2 text-sm text-muted-foreground">'
            . e($item->label) . ' <span class="text-xs">soon</span></span>';
    }
    $current = $item->key === $active;
    $class = $current
        ? 'flex items-center rounded-md bg-sidebar-accent px-3 py-2 text-sm font-medium text-sidebar-accent-foreground'
        : 'flex items-center rounded-md px-3 py-2 text-sm hover:bg-sidebar-accent hover:text-sidebar-accent-foreground';
    // Phase 4: my day (count badge)
    $badge = $item->count > 0
        ? '<span class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-destructive px-1.5 text-xs font-medium text-white" data-nav-count="' . $item->count . '">'
            . ($item->count > 99 ? '99+' : (string) $item->count) . '<span class="sr-only"> need attention</span></span>'
        : '';
    return '<a href="' . attr($item->href) . '" class="' . $class . '"' . ($current ? ' aria-current="page"' : '') . '>' . e($item->label) . $badge . '</a>';
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
<body class="min-h-screen bg-background text-foreground antialiased"<?= $bodySignalsJs !== '' ? ' data-signals="' . $bodySignalsJs . '"' : '' ?>>
<main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center gap-6 p-4">
<?= $content ?>
</main>
<?= partial_toasts_region('') ?>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
