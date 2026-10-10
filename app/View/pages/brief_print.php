<?php
declare(strict_types=1);

use App\View\VM\BriefDocVM;

/** GET /jobs/{id}/brief/print: a standalone A4 page, no app shell. */
function page_brief_print(BriefDocVM $vm, string $backUrl): string
{
    ob_start(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($vm->jobNumber . ' ' . $vm->snapshot->title) ?> · Brief <?= e($vm->versionLabel) ?></title>
<?= layout_head_assets() ?>
<style>@page { size: A4; margin: 16mm 14mm; }</style>
</head>
<body class="min-h-screen bg-muted/40 text-foreground antialiased print:bg-white print:text-black">
<div class="mx-auto flex max-w-[210mm] items-center justify-between gap-2 px-4 py-4 print:hidden">
  <a href="<?= attr($backUrl) ?>" class="text-sm underline underline-offset-4">Back to the brief</a>
  <button type="button" data-on:click="window.print()" class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">Print</button>
</div>
<main class="mx-auto mb-8 max-w-[210mm] bg-background p-[14mm] shadow-sm print:m-0 print:max-w-none print:bg-white print:p-0 print:shadow-none">
  <p class="mb-4 text-xs uppercase tracking-wide text-muted-foreground">Slash 301 · Creative brief</p>
<?= partial_brief_doc($vm) ?>
</main>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
