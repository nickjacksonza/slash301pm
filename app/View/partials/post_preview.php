<?php
declare(strict_types=1);

use App\Domain\MediaKind;
use App\Domain\PartState;
use App\View\ui\AvatarProps;
use App\View\VM\PostPreviewVM;

/**
 * A post shown like a basic social media post, labelled in the owner's order:
 * brand logo and name, media, copy, hashtags, link. Reusable: the internal
 * review page, the hand-in page and (next phase) the client portal render it.
 * URLs reach href/src only when the view model says they may (checked in PHP
 * with Links); a server path is shown as text.
 */
function partial_post_preview(PostPreviewVM $vm): string
{
    $label = 'text-[0.6875rem] font-medium uppercase tracking-wide text-muted-foreground';
    ob_start(); ?>
<div id="<?= attr($vm->domId) ?>" class="overflow-hidden rounded-lg border border-border bg-background text-foreground">
  <div class="flex items-center gap-2 border-b border-border px-3 py-2">
    <?php if ($vm->brandLogoUrl !== ''): ?>
      <img src="<?= attr($vm->brandLogoUrl) ?>" alt="" class="size-8 shrink-0 rounded-full border border-border bg-white object-contain" width="32" height="32" loading="lazy" referrerpolicy="no-referrer">
    <?php else: ?>
      <?= ui_avatar(new AvatarProps(name: $vm->brandName !== '' ? $vm->brandName : '?', class: 'size-8 shrink-0 text-xs')) ?>
    <?php endif; ?>
    <span class="min-w-0 truncate text-sm font-semibold"><?= e($vm->brandName !== '' ? $vm->brandName : 'Brand') ?></span>
  </div>
  <?php if ($vm->needsMedia): ?>
  <div class="border-b border-border">
    <p class="<?= attr($label) ?> px-3 pt-2">Media</p>
    <?= post_preview_media($vm) ?>
  </div>
  <?php endif; ?>
  <div class="flex flex-col gap-3 px-3 py-3">
    <div>
      <p class="<?= attr($label) ?>">Copy</p>
      <?php if ($vm->copyText !== ''): ?>
        <p class="whitespace-pre-line break-words text-sm leading-relaxed"><?= e($vm->copyText) ?></p>
      <?php else: ?>
        <p class="text-sm text-muted-foreground">No copy handed in yet.</p>
      <?php endif; ?>
    </div>
    <div>
      <p class="<?= attr($label) ?>">Hashtags</p>
      <p class="break-words text-sm <?= $vm->hashtags !== '' ? 'text-blue-800 dark:text-blue-300' : 'text-muted-foreground' ?>"><?= $vm->hashtags !== '' ? e($vm->hashtags) : 'None' ?></p>
    </div>
    <div>
      <p class="<?= attr($label) ?>">Link</p>
      <?php if ($vm->linkUrl !== '' && $vm->linkLinkable): ?>
        <p class="truncate text-sm"><a class="text-primary underline underline-offset-4" href="<?= attr($vm->linkUrl) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($vm->linkUrl) ?></a></p>
      <?php elseif ($vm->linkUrl !== ''): ?>
        <p class="break-all font-mono text-xs"><?= e($vm->linkUrl) ?></p>
      <?php else: ?>
        <p class="text-sm text-muted-foreground">None</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
    return (string) ob_get_clean();
}

/** The media block: an https image inline, a video link with a play label, any other web link as "Open media", a server path as text. */
function post_preview_media(PostPreviewVM $vm): string
{
    if ($vm->mediaUrl === '') {
        return '<div class="flex aspect-video items-center justify-center bg-muted px-3 text-sm text-muted-foreground">No media handed in yet.</div>';
    }
    $open = static fn (string $text, string $class): string => '<a class="' . attr($class) . '" href="' . attr($vm->mediaUrl) . '" target="_blank" rel="noopener noreferrer nofollow">' . $text . '</a>';
    if ($vm->mediaInline) {
        return '<div class="bg-muted"><img src="' . attr($vm->mediaUrl) . '" alt="Post media" class="mx-auto max-h-[32rem] w-full object-contain" loading="lazy" referrerpolicy="no-referrer"></div>'
            . '<p class="px-3 py-1.5 text-xs">' . $open('Open media', 'text-primary underline underline-offset-4') . '</p>';
    }
    if (!$vm->mediaLinkable) {
        return '<div class="flex flex-col gap-1 bg-muted px-3 py-4"><span class="text-xs text-muted-foreground">On the server (copy the path):</span>'
            . '<span class="select-all break-all font-mono text-xs">' . e($vm->mediaUrl) . '</span></div>';
    }
    if ($vm->mediaKind === MediaKind::Video) {
        $play = '<svg aria-hidden="true" viewBox="0 0 24 24" class="size-10" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>';
        return $open('<span class="flex size-16 items-center justify-center rounded-full bg-black/70 text-white">' . $play . '</span><span class="text-sm font-medium">Play video</span>',
            'flex aspect-video flex-col items-center justify-center gap-2 bg-muted text-foreground hover:bg-accent');
    }
    return $open('<span class="text-sm font-medium">Open media</span><span class="max-w-full truncate px-3 text-xs text-muted-foreground">' . e($vm->mediaUrl) . '</span>',
        'flex min-h-32 flex-col items-center justify-center gap-1 bg-muted text-primary hover:bg-accent');
}

/** A small status chip for a part (copy or media). */
function partial_part_chip(string $label, PartState $s): string
{
    $class = match ($s) {
        PartState::Missing => 'border-border text-muted-foreground',
        PartState::Submitted => 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',
        PartState::Approved => 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200',
        PartState::ChangesRequested => 'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200',
        PartState::Rejected => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
    };
    return '<span class="' . attr('inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium ' . $class) . '">'
        . e($label) . ': ' . e($s->label()) . '</span>';
}
