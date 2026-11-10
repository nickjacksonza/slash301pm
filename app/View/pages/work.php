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
        <?= ui_input(new \App\View\ui\InputProps(type: 'text', id: ($id . '-media'), placeholder: 'https://... or a server path', value: ($it->mediaUrl), attrs: ['inputmode' => 'url', 'data-bind' => ($root . '.media_url'), 'disabled' => $copyLocked])) ?>
        <p class="text-xs text-muted-foreground">An image link (https, .jpg/.png/.webp) shows in the post; video and other links open in a new tab.</p>
      </div>
      <?php endif; ?>
      <div class="flex flex-col gap-1">
        <label for="<?= attr($id . '-copy') ?>" class="text-sm font-medium">Copy</label>
        <?= ui_textarea(new \App\View\ui\TextareaProps(id: ($id . '-copy'), value: $it->copyText, disabled: $copyLocked, attrs: ['rows' => 6, 'maxlength' => 10000, 'data-bind' => ($root . '.copy_text')])) ?>
      </div>
      <div class="flex flex-col gap-1">
        <label for="<?= attr($id . '-tags') ?>" class="text-sm font-medium">Hashtags</label>
        <?= ui_input(new \App\View\ui\InputProps(type: 'text', id: ($id . '-tags'), placeholder: '#launch #grandopening', value: ($it->hashtags), attrs: ['data-bind' => ($root . '.hashtags'), 'disabled' => $copyLocked])) ?>
      </div>
      <div class="flex flex-col gap-1">
        <label for="<?= attr($id . '-link') ?>" class="text-sm font-medium">Link</label>
        <?= ui_input(new \App\View\ui\InputProps(type: 'url', id: ($id . '-link'), placeholder: 'https://...', value: ($it->linkUrl), attrs: ['data-bind' => ($root . '.link_url'), 'disabled' => $copyLocked])) ?>
      </div>
      <div class="flex flex-col gap-1">
        <label for="<?= attr($id . '-note') ?>" class="text-sm font-medium">Note for the reviewer <span class="font-normal text-muted-foreground">(optional)</span></label>
        <?= ui_input(new \App\View\ui\InputProps(type: 'text', id: ($id . '-note'), value: ($it->note), attrs: ['maxlength' => '1000', 'data-bind' => ($root . '.note')])) ?>
      </div>
      <?php if ($it->readOnlyReason !== ''): ?><p class="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground"><?= e($it->readOnlyReason) ?></p><?php endif; ?>
      <div class="flex flex-wrap gap-2">
        <?= ui_button(new \App\View\ui\ButtonProps(variant: 'outline', attrs: ['data-indicator' => $busy, 'data-attr:disabled' => '$' . $busy, 'data-on:click' => act_raw('post', $it->saveUrl, $only)]), 'Save') ?>
        <?= ui_button(new \App\View\ui\ButtonProps(variant: 'default', attrs: ['data-indicator' => $busy, 'data-attr:disabled' => '$' . $busy, 'data-on:click' => act_raw('post', $it->requestUrl, $only)]), 'Request review') ?>
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
