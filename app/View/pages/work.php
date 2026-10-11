<?php
declare(strict_types=1);

use App\View\ui\BadgeProps;
use App\View\VM\WorkItemVM;
use App\View\VM\WorkPageVM;

/**
 * GET /jobs/{id}/work: the posts this person makes on the job. Per post a
 * form under sub_<key>.{copy_text, media_url, hashtags, link_url, note, rv}
 * (only the parts they make are editable), Save and Request review, the
 * reviewer's earlier feedback, and the post as the reviewer will see it.
 */
function page_work(WorkPageVM $vm): string
{
    ob_start(); ?>
<div id="work" class="mx-auto flex w-full max-w-4xl flex-col gap-5">
  <header class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <p class="text-xs text-muted-foreground"><span class="font-mono"><?= e($vm->jobNumber) ?></span><?= $vm->brandName !== '' ? ' · ' . e($vm->brandName) : '' ?><?= $vm->versionLabel !== '' ? ' · brief ' . e($vm->versionLabel) : '' ?></p>
      <h2 class="text-xl font-semibold leading-tight"><?= e($vm->title) ?></h2>
    </div>
    <div class="flex flex-wrap items-center gap-2 text-sm">
      <?= ui_badge(new BadgeProps(stage: $vm->stage->value), e($vm->stage->label())) ?>
      <a class="text-primary underline-offset-4 hover:underline" href="<?= attr($vm->briefUrl) ?>">Brief</a>
      <a class="text-primary underline-offset-4 hover:underline" href="<?= attr($vm->reviewUrl) ?>">All posts in review</a>
    </div>
  </header>
  <p class="text-sm text-muted-foreground">Hand in the copy (with hashtags and link) or the media link for each post. Save keeps your work; Request review tells the CD it is ready. A part the CD approved is locked unless it is sent back.</p>
  <?php if ($vm->items === []): ?>
    <p class="rounded-md border border-dashed border-border px-3 py-6 text-center text-sm text-muted-foreground">No post on this job is yours to hand in.</p>
  <?php endif; ?>
  <?php foreach ($vm->items as $it): ?>
    <?= partial_work_item($it) ?>
  <?php endforeach; ?>
  <?php if ($vm->otherCount > 0): ?>
    <p class="text-sm text-muted-foreground"><?= (int) $vm->otherCount ?> other <?= $vm->otherCount === 1 ? 'post is' : 'posts are' ?> made by someone else.</p>
  <?php endif; ?>
</div>
<?php
    return (string) ob_get_clean();
}

/** #work-<key>: one post's hand-in form. */
function partial_work_item(WorkItemVM $it): string
{
    $root = 'sub_' . $it->key;
    $busy = '_subbusy_' . $it->key;
    $only = 'filterSignals: {include: /^' . $root . '\\./}, retryMaxCount: 0';
    $signals = [$root => ['copy_text' => $it->copyText, 'media_url' => $it->mediaUrl, 'hashtags' => $it->hashtags, 'link_url' => $it->linkUrl,
        'note' => $it->note, 'rv' => $it->rowVersion]];
    $input = 'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60';
    $area = 'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60';
    $id = 'work-' . $it->key;
    $copyLocked = !$it->editCopy;
    $mediaLocked = !$it->editMedia;
    ob_start(); ?>
<section id="<?= attr($id) ?>" class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm md:p-5" aria-labelledby="<?= attr($id . '-h') ?>"
  data-signals="<?= attr(jobs_js($signals)) ?>">
  <header class="flex flex-wrap items-start justify-between gap-2">
    <div class="min-w-0">
      <p class="text-xs text-muted-foreground">Round <?= (int) $it->round ?><?= $it->reviewRequested ? ' · review requested' : '' ?></p>
      <h3 id="<?= attr($id . '-h') ?>" class="break-words text-base font-semibold"><?= e($it->assetName) ?></h3>
      <?php if ($it->lineLabel !== ''): ?><p class="text-xs text-muted-foreground"><?= e($it->lineLabel) ?></p><?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-1.5">
      <?= partial_part_chip('Copy', $it->copyState) ?>
      <?php if ($it->needsMedia): ?><?= partial_part_chip('Media', $it->mediaState) ?><?php endif; ?>
    </div>
  </header>
  <?= partial_review_history($it->history) ?>
  <div class="grid gap-4 md:grid-cols-2">
    <div class="flex flex-col gap-3">
      <?php if ($it->needsMedia): ?>
      <div class="flex flex-col gap-1">
        <label for="<?= attr($id . '-media') ?>" class="text-sm font-medium">Media link</label>
        <input id="<?= attr($id . '-media') ?>" type="text" inputmode="url" class="<?= attr($input) ?>" placeholder="https://... or a server path" value="<?= attr($it->mediaUrl) ?>" data-bind="<?= attr($root . '.media_url') ?>"<?= $mediaLocked ? ' disabled' : '' ?>>
        <p class="text-xs text-muted-foreground">An image link (https, .jpg/.png/.webp) shows in the post; video and other links open in a new tab.</p>
      </div>
      <?php endif; ?>
      <div class="flex flex-col gap-1">
        <label for="<?= attr($id . '-copy') ?>" class="text-sm font-medium">Copy</label>
        <textarea id="<?= attr($id . '-copy') ?>" rows="6" maxlength="10000" class="<?= attr($area) ?>" data-bind="<?= attr($root . '.copy_text') ?>"<?= $copyLocked ? ' disabled' : '' ?>><?= e($it->copyText) ?></textarea>
      </div>
      <div class="flex flex-col gap-1">
        <label for="<?= attr($id . '-tags') ?>" class="text-sm font-medium">Hashtags</label>
        <input id="<?= attr($id . '-tags') ?>" type="text" class="<?= attr($input) ?>" placeholder="#launch #grandopening" value="<?= attr($it->hashtags) ?>" data-bind="<?= attr($root . '.hashtags') ?>"<?= $copyLocked ? ' disabled' : '' ?>>
      </div>
      <div class="flex flex-col gap-1">
        <label for="<?= attr($id . '-link') ?>" class="text-sm font-medium">Link</label>
        <input id="<?= attr($id . '-link') ?>" type="url" class="<?= attr($input) ?>" placeholder="https://..." value="<?= attr($it->linkUrl) ?>" data-bind="<?= attr($root . '.link_url') ?>"<?= $copyLocked ? ' disabled' : '' ?>>
      </div>
      <div class="flex flex-col gap-1">
        <label for="<?= attr($id . '-note') ?>" class="text-sm font-medium">Note for the reviewer <span class="font-normal text-muted-foreground">(optional)</span></label>
        <input id="<?= attr($id . '-note') ?>" type="text" maxlength="1000" class="<?= attr($input) ?>" value="<?= attr($it->note) ?>" data-bind="<?= attr($root . '.note') ?>">
      </div>
      <?php if ($it->readOnlyReason !== ''): ?><p class="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground"><?= e($it->readOnlyReason) ?></p><?php endif; ?>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50"
          data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>" data-on:click="<?= attr(act_raw('post', $it->saveUrl, $only)) ?>">Save</button>
        <button type="button" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50"
          data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>" data-on:click="<?= attr(act_raw('post', $it->requestUrl, $only)) ?>">Request review</button>
      </div>
    </div>
    <div class="flex flex-col gap-1">
      <p class="text-sm font-medium">As the reviewer sees it (last saved)</p>
      <?= partial_post_preview($it->preview) ?>
    </div>
  </div>
</section>
<?php
    return (string) ob_get_clean();
}
