<?php
declare(strict_types=1);

use App\Domain\ChecklistItem;
use App\Domain\Platform;
use App\Domain\PublicationStatus;
use App\Domain\SocialTab;
use App\Domain\Types\Publication;
use App\Domain\Types\SocialAsset;
use App\View\ui\BadgeProps;
use App\View\ui\ButtonProps;
use App\View\ui\InputProps;
use App\View\ui\NativeSelectProps;
use App\View\ui\PartProps;
use App\View\VM\SocialDayItemVM;
use App\View\VM\SocialDayVM;
use App\View\VM\SocialJobVM;
use App\View\VM\SocialPermsVM;
use App\View\VM\SocialQueueVM;

/*
 * Social publishing screens. Signals:
 *   sq.*            the queue: tab, brand, platform, mine (GET /social/list)
 *   pub_<id>.*      one post card: rv, cl.{copy,image,link,hashtags,test_result}[_note],
 *                   scheduled_at, live_url, promoted, promoted_note, reason
 *   _pbusy_<id>     the card is saving (data-indicator); its inputs are disabled meanwhile
 * Publication ids are 32 hex characters (PublicationStore), so they are safe in
 * signal names and regular expressions. Every user value reaches an expression
 * through jobs_js() and then attr(); text through e(). Budget never appears.
 */

/** Publication status -> badge stage key (full literal keys in ui_badge). */
function social_status_key(PublicationStatus $s): string
{
    return match ($s) {
        PublicationStatus::Checking => 'approved_client',
        PublicationStatus::ReadyToSchedule => 'ready_to_schedule',
        PublicationStatus::Scheduled => 'scheduled',
        PublicationStatus::Live => 'live',
        PublicationStatus::Archived => 'archived',
    };
}

/** GET /social */
function page_social_queue(SocialQueueVM $vm): string
{
    $fetch = act_raw('get', url('/social/list'), 'filterSignals: {include: /^sq\\./}');
    $state = ['tab' => $vm->tab->value, 'brand' => $vm->brand, 'platform' => $vm->platform, 'mine' => $vm->mine];
    ob_start(); ?>
<div id="social-page" class="mx-auto flex w-full max-w-6xl flex-col gap-5" data-signals="<?= attr(jobs_js(['sq' => $state])) ?>">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h2 class="text-2xl font-semibold leading-tight">Social</h2>
      <p class="text-sm text-muted-foreground"><?= $vm->wholeQueue ? 'Every client-approved social deliverable: check it, schedule it, mark it live.' : 'The Social status of your client-approved jobs (read only).' ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <div class="w-44"><?= ui_native_select(new NativeSelectProps(id: 'sq-brand', options: $vm->brands, value: $vm->brand, attrs: ['aria-label' => 'Brand', 'data-bind' => 'sq.brand', 'data-on:change' => $fetch])) ?></div>
      <div class="w-44"><?= ui_native_select(new NativeSelectProps(id: 'sq-platform', options: $vm->platforms, value: $vm->platform, attrs: ['aria-label' => 'Platform', 'data-bind' => 'sq.platform', 'data-on:change' => $fetch])) ?></div>
      <?php if ($vm->offerMine): ?>
      <label class="flex h-9 items-center gap-2 rounded-md border border-border px-3 text-sm">
        <input type="checkbox" class="size-4 accent-primary" data-bind="sq.mine" data-on:change="<?= attr($fetch) ?>"<?= $vm->mine ? ' checked' : '' ?>> Mine only
      </label>
      <?php endif; ?>
    </div>
  </div>
  <?= partial_social_list($vm) ?>
</div>
<?php
    return (string) ob_get_clean();
}

/** #social-list: tab strip with counts and the rows of the current tab. */
function partial_social_list(SocialQueueVM $vm): string
{
    $fetch = act_raw('get', url('/social/list'), 'filterSignals: {include: /^sq\\./}');
    $empty = match ($vm->tab) {
        SocialTab::Check => 'Nothing to check. Client-approved social deliverables appear here.',
        SocialTab::Ready => 'Nothing is waiting to be scheduled.',
        SocialTab::Scheduled => 'Nothing is scheduled.',
        SocialTab::Live => 'No live posts yet.',
        SocialTab::Archived => 'No archived posts.',
    };
    ob_start(); ?>
<div id="social-list" class="flex flex-col gap-3">
  <div role="tablist" aria-label="Social status" class="inline-flex w-fit max-w-full flex-wrap items-center gap-1 rounded-lg bg-muted p-1 text-muted-foreground">
    <?php foreach (SocialTab::cases() as $t): $on = $t === $vm->tab; ?>
      <button type="button" role="tab" aria-selected="<?= $on ? 'true' : 'false' ?>" id="sq-tab-<?= attr($t->value) ?>"
        class="<?= attr($on ? 'inline-flex h-8 items-center gap-1.5 rounded-md bg-background px-3 text-sm font-medium text-foreground shadow-sm' : 'inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-sm font-medium hover:text-foreground') ?>"
        data-on:click="<?= attr('$sq.tab = ' . jobs_js($t->value) . '; ' . $fetch) ?>">
        <?= e($t->label()) ?> <span class="rounded-full bg-muted-foreground/15 px-1.5 text-xs tabular-nums"><?= (int) ($vm->counts[$t->value] ?? 0) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
  <?php if ($vm->rows === []): ?>
    <p class="rounded-md border border-dashed border-border px-3 py-6 text-center text-sm text-muted-foreground"><?= e($empty) ?></p>
  <?php else: ?>
  <div class="overflow-x-auto rounded-lg border border-border bg-card">
    <table class="w-full text-sm">
      <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
        <tr><th class="px-3 py-2 font-medium">Job</th><th class="px-3 py-2 font-medium">Brand</th><th class="px-3 py-2 font-medium">Asset</th><th class="px-3 py-2 font-medium">Platforms</th><th class="px-3 py-2 font-medium"><?= $vm->tab === SocialTab::Scheduled ? 'Scheduled' : 'Due' ?></th></tr>
      </thead>
      <tbody class="divide-y divide-border">
      <?php foreach ($vm->rows as $a): ?>
        <?= partial_social_row($a, $vm->tab) ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php
    return (string) ob_get_clean();
}

function partial_social_row(SocialAsset $a, SocialTab $tab): string
{
    $href = url('/social/jobs/' . rawurlencode($a->jobId)) . '#social-asset-' . rawurlencode($a->assetId);
    $chips = '';
    foreach ($a->publications as $p) {
        if ($tab !== SocialTab::Archived && $p->status === PublicationStatus::Archived) {
            continue;
        }
        $chips .= ui_badge(new BadgeProps(stage: social_status_key($p->status), attrs: ['title' => $p->status->label()]), e($p->platform->label()) . '<span class="sr-only">: ' . e($p->status->label()) . '</span>');
    }
    if ($chips === '') {
        $suggest = [];
        foreach (Platform::allFromChannel($a->channel) as $p) {
            $suggest[] = $p->label();
        }
        $chips = '<span class="text-xs text-muted-foreground">' . e($suggest !== [] ? 'Suggested: ' . implode(', ', $suggest) : 'No platform yet') . '</span>';
    }
    $when = '';
    if ($tab === SocialTab::Scheduled || $tab === SocialTab::Live) {
        foreach ($a->publications as $p) {
            if ($p->scheduledAt !== null && ($when === '' || $p->scheduledAt < $when)) {
                $when = $p->scheduledAt;
            }
        }
        $when = fmt_scheduled($when);
    }
    if ($when === '') {
        $when = fmt_date($a->jobDue);
    }
    $line = trim($a->lineLabel . ($a->channel !== '' ? ' · ' . $a->channel : '') . ($a->sizeFormat !== '' ? ' · ' . $a->sizeFormat : ''), ' ·');
    ob_start(); ?>
<tr class="align-top hover:bg-muted/50">
  <td class="px-3 py-2.5"><a class="font-mono text-xs text-muted-foreground" href="<?= attr($href) ?>"><?= e($a->jobNumber) ?></a><a class="block max-w-64 truncate font-medium hover:underline" href="<?= attr($href) ?>"><?= e($a->jobTitle) ?></a></td>
  <td class="px-3 py-2.5 whitespace-nowrap"><?= e($a->brandName) ?></td>
  <td class="px-3 py-2.5"><span class="block max-w-64 truncate"><?= e($a->assetName) ?></span><?php if ($line !== ''): ?><span class="block max-w-64 truncate text-xs text-muted-foreground"><?= e($line) ?></span><?php endif; ?></td>
  <td class="px-3 py-2.5"><span class="flex max-w-72 flex-wrap gap-1"><?= $chips ?></span></td>
  <td class="px-3 py-2.5 whitespace-nowrap"><?= e($when) ?></td>
</tr>
<?php
    return (string) ob_get_clean();
}

/** GET /social/jobs/{id} */
function page_social_job(SocialJobVM $vm): string
{
    ob_start(); ?>
<div id="social-job" class="mx-auto flex w-full max-w-5xl flex-col gap-5">
  <?= partial_social_job_head($vm) ?>
  <?php if ($vm->doc !== null): ?>
  <details class="rounded-xl border border-border bg-card px-6 py-4 text-card-foreground shadow-sm">
    <summary class="cursor-pointer text-sm font-semibold">Sent brief <?= e($vm->versionLabel) ?> <span class="font-normal text-muted-foreground">(read only)</span></summary>
    <div class="mt-4"><?= partial_brief_doc($vm->doc) ?></div>
  </details>
  <?php endif; ?>
  <?php if ($vm->assets === []): ?>
    <p class="rounded-md border border-dashed border-border px-3 py-6 text-center text-sm text-muted-foreground">This job has no social assets.</p>
  <?php endif; ?>
  <?php foreach ($vm->assets as $a): ?>
    <?= partial_social_asset($a, $vm->perms) ?>
  <?php endforeach; ?>
</div>
<?php
    return (string) ob_get_clean();
}

/** #social-job-head: who, when, the stage and the brief-level action. */
function partial_social_job_head(SocialJobVM $vm): string
{
    $row = static fn (string $label, string $value): string => $value === '' ? '' : '<div><dt class="text-xs text-muted-foreground">' . e($label) . '</dt><dd class="text-sm">' . e($value) . '</dd></div>';
    ob_start(); ?>
<section id="social-job-head" class="flex flex-col gap-4 rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm" aria-labelledby="social-job-title">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <p class="text-sm text-muted-foreground"><a class="hover:underline" href="<?= attr(url('/social')) ?>">Social</a> · <span class="font-mono text-xs"><?= e($vm->jobNumber) ?></span> · <?= e(trim($vm->brandName . ($vm->campaignName !== '' ? ' · ' . $vm->campaignName : ''))) ?></p>
      <h2 id="social-job-title" class="text-2xl font-semibold leading-tight"><?= e($vm->title) ?></h2>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <?= ui_badge(new BadgeProps(stage: $vm->stage->value), e($vm->stage->label())) ?>
      <?php if ($vm->canMarkReady): ?>
        <?= ui_button(new ButtonProps(size: 'sm', attrs: ['id' => 'social-mark-ready', 'data-indicator' => '_jbusy', 'data-attr:disabled' => '$_jbusy',
            'data-on:click' => "confirm('Mark every post of this brief Ready to schedule? Each checklist must be complete. The AM is told.') && " . act_raw('post', url('/social/jobs/' . rawurlencode($vm->jobId) . '/ready'), 'filterSignals: {include: /^$/}, retryMaxCount: 0')]), 'Mark whole brief ready') ?>
      <?php endif; ?>
    </div>
  </div>
  <dl class="grid grid-cols-2 gap-3 md:grid-cols-5">
    <?= $row('AM', $vm->amName) ?>
    <?= $row('Social', $vm->socialName !== '' ? $vm->socialName : 'Nobody yet') ?>
    <?= $row('Due', fmt_date($vm->jobDue)) ?>
    <?= $row('Last go-live', fmt_date($vm->lastGoLive)) ?>
    <?php if ($vm->versionUrl !== ''): ?>
      <div><dt class="text-xs text-muted-foreground">Brief</dt><dd class="text-sm"><a class="text-primary underline-offset-4 hover:underline" href="<?= attr($vm->versionUrl) ?>">Sent <?= e($vm->versionLabel) ?></a></dd></div>
    <?php endif; ?>
  </dl>
  <?php if ($vm->note !== ''): ?><p class="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground"><?= e($vm->note) ?></p><?php endif; ?>
</section>
<?php
    return (string) ob_get_clean();
}

/** #social-asset-<id>: the asset, its platform picker and one card per platform. */
function partial_social_asset(SocialAsset $a, SocialPermsVM $perms): string
{
    $line = trim($a->lineLabel . ($a->channel !== '' ? ' · ' . $a->channel : '') . ($a->sizeFormat !== '' ? ' · ' . $a->sizeFormat : ''), ' ·');
    $heading = 'h-social-asset-' . $a->assetId;
    $suggested = Platform::allFromChannel($a->channel);
    ob_start(); ?>
<section id="<?= attr('social-asset-' . $a->assetId) ?>" class="flex flex-col gap-4 rounded-xl border border-border bg-card p-6 text-card-foreground shadow-sm" aria-labelledby="<?= attr($heading) ?>">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h3 id="<?= attr($heading) ?>" class="text-base font-semibold"><?= e($a->assetName) ?></h3>
      <p class="text-sm text-muted-foreground"><?= e($line !== '' ? $line : 'Social deliverable') ?><?= $a->assigneeName !== '' ? ' · made by ' . e($a->assigneeName) : '' ?></p>
    </div>
  </div>
  <div class="flex flex-wrap items-center gap-2" aria-label="Platforms">
    <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Platforms</span>
    <?php foreach (Platform::cases() as $p): $pub = $a->publicationFor($p); ?>
      <?php if ($pub !== null): ?>
        <?= ui_badge(new BadgeProps(stage: social_status_key($pub->status)), e($p->label()) . ' · ' . e($pub->status->label())) ?>
      <?php elseif ($perms->checklist): ?>
        <button type="button" class="<?= attr(in_array($p, $suggested, true) ? 'inline-flex h-6 items-center rounded-full border border-primary px-2 text-xs font-medium text-primary hover:bg-accent' : 'inline-flex h-6 items-center rounded-full border border-dashed border-border px-2 text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground') ?>"
          data-indicator="_abusy" data-attr:disabled="$_abusy"
          data-on:click="<?= attr(act_raw('post', url('/social/assets/' . rawurlencode($a->assetId) . '/platforms/' . $p->value), 'filterSignals: {include: /^$/}, retryMaxCount: 0')) ?>">+ <?= e($p->label()) ?></button>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php if ($a->publications === []): ?>
    <p class="rounded-md border border-dashed border-border px-3 py-4 text-sm text-muted-foreground"><?= $perms->checklist ? 'Add the platforms this post goes out on. Each one gets its own checklist.' : 'No platform chosen yet.' ?></p>
  <?php else: ?>
  <div class="grid gap-4 lg:grid-cols-2">
    <?php foreach ($a->publications as $pub): ?>
      <?= partial_social_card($pub, $perms) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php
    return (string) ob_get_clean();
}

/** One post card (one asset on one platform). */
function partial_social_card(Publication $p, SocialPermsVM $perms): string
{
    $root = 'pub_' . $p->id;
    $busy = '_pbusy_' . $p->id;
    $base = url('/social/publications/' . rawurlencode($p->id));
    $only = 'filterSignals: {include: /^' . $root . '\\./}, retryMaxCount: 0';
    $act = static fn (string $method, string $path): string => act_raw($method, $base . $path, $only);
    $cl = [];
    foreach (ChecklistItem::cases() as $item) {
        $en = $p->checklist->entry($item);
        $cl[$item->value] = $en->ok;
        $cl[$item->value . '_note'] = $en->note;
    }
    $signals = [$root => [
        'rv' => $p->rowVersion, 'cl' => $cl,
        'scheduled_at' => $p->scheduledAt !== null ? str_replace(' ', 'T', $p->scheduledAt) : '',
        'live_url' => $p->liveUrl, 'promoted' => $p->promoted, 'promoted_note' => $p->promotedNote, 'reason' => '',
    ]];
    $s = '$' . $root;
    $st = $p->status;
    $editChecklist = $perms->checklist && $st === PublicationStatus::Checking;
    // The job's Producer: only the Test result item (asset test reports).
    $editTest = !$editChecklist && $perms->testResult && $st === PublicationStatus::Checking;
    $complete = [];
    foreach (ChecklistItem::cases() as $item) {
        $complete[] = $s . '.cl.' . $item->value;
    }
    $allTicked = implode(' && ', $complete);
    $id = 'pub-' . $p->id;
    $btn = 'inline-flex h-8 items-center justify-center rounded-md px-3 text-sm font-medium disabled:pointer-events-none disabled:opacity-50';
    ob_start(); ?>
<article id="<?= attr($id) ?>" class="flex flex-col gap-3 rounded-lg border border-border p-4" data-signals="<?= attr(jobs_js($signals)) ?>" aria-label="<?= attr($p->platform->label()) ?>">
  <header class="flex flex-wrap items-center justify-between gap-2">
    <h4 class="text-sm font-semibold"><?= e($p->platform->label()) ?></h4>
    <span class="flex items-center gap-2">
      <?= ui_badge(new BadgeProps(stage: social_status_key($st)), e($st->label())) ?>
      <?php if ($p->promoted): ?><?= ui_badge(new BadgeProps(variant: 'outline'), 'Promoted') ?><?php endif; ?>
    </span>
  </header>

  <fieldset class="flex flex-col gap-2" <?= $editChecklist || $editTest ? '' : 'disabled' ?>>
    <legend class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Final check <span class="normal-case tracking-normal">(<?= $p->checklist->tickedCount() ?>/5)</span></legend>
    <?php foreach (ChecklistItem::cases() as $item): $en = $p->checklist->entry($item); $cid = $id . '-' . $item->value; $editItem = $editChecklist || ($editTest && $item === ChecklistItem::TestResult); ?>
      <div class="grid grid-cols-[8rem_1fr] items-center gap-2">
        <label for="<?= attr($cid) ?>" class="flex items-center gap-2 text-sm">
          <input id="<?= attr($cid) ?>" type="checkbox" class="size-4 accent-primary" data-bind="<?= attr($root . '.cl.' . $item->value) ?>"<?= $en->ok ? ' checked' : '' ?>
            <?php if ($editItem): ?> data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>" data-on:change="<?= attr($act('patch', '/checklist')) ?>"<?php else: ?> disabled<?php endif; ?>>
          <?= e($item->label()) ?>
        </label>
        <?php if ($editItem): ?>
          <?= ui_input(new InputProps(type: 'text', class: 'h-8', placeholder: 'Note', attrs: ['aria-label' => $item->label() . ' note', 'maxlength' => '500', 'data-bind' => $root . '.cl.' . $item->value . '_note', 'data-indicator' => $busy, 'data-on:change' => $act('patch', '/checklist')])) ?>
        <?php else: ?>
          <span class="truncate text-xs text-muted-foreground"><?= e($en->note) ?></span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </fieldset>

  <?php if ($st === PublicationStatus::Checking && ($perms->checklist || $perms->ready)): ?>
    <div class="flex flex-wrap gap-2">
      <?php if ($editChecklist): ?>
        <button type="button" class="<?= attr($btn . ' border border-border hover:bg-accent hover:text-accent-foreground') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>"
          data-on:click="<?= attr(implode('; ', array_map(static fn (string $x): string => $x . ' = true', $complete)) . '; ' . $act('patch', '/checklist')) ?>">Tick all</button>
        <button type="button" class="<?= attr($btn . ' border border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>"
          data-on:click="<?= attr('confirm(' . jobs_js('Remove ' . $p->platform->label() . ' from this asset?') . ') && ' . $act('delete', '')) ?>">Remove</button>
      <?php endif; ?>
      <?php if ($perms->ready): ?>
        <button type="button" class="<?= attr($btn . ' bg-primary text-primary-foreground hover:bg-primary/90') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy . ' || !(' . $allTicked . ')') ?>"
          data-on:click="<?= attr($act('post', '/ready')) ?>">Ready to schedule</button>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($st === PublicationStatus::ReadyToSchedule || $st === PublicationStatus::Scheduled): ?>
    <div class="flex flex-col gap-1.5">
      <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground" for="<?= attr($id . '-at') ?>">Scheduled for (SA time)</label>
      <?php if ($perms->schedule): ?>
      <div class="flex flex-wrap items-center gap-2">
        <?= ui_input(new InputProps(type: 'datetime-local', id: $id . '-at', class: 'h-8 w-56', attrs: ['data-bind' => $root . '.scheduled_at'])) ?>
        <?php if ($st === PublicationStatus::ReadyToSchedule): ?>
          <button type="button" class="<?= attr($btn . ' bg-primary text-primary-foreground hover:bg-primary/90') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy . ' || !' . $s . '.scheduled_at') ?>" data-on:click="<?= attr($act('post', '/scheduled')) ?>">Mark scheduled</button>
        <?php else: ?>
          <button type="button" class="<?= attr($btn . ' border border-border hover:bg-accent hover:text-accent-foreground') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>" data-on:click="<?= attr($act('patch', '/schedule')) ?>">Save time</button>
        <?php endif; ?>
      </div>
      <?php else: ?>
        <p id="<?= attr($id . '-at') ?>" class="text-sm"><?= e(fmt_scheduled($p->scheduledAt) ?: 'Not set') ?></p>
      <?php endif; ?>
    </div>
  <?php elseif ($p->scheduledAt !== null): ?>
    <p class="text-sm"><span class="text-muted-foreground">Scheduled for</span> <?= e(fmt_scheduled($p->scheduledAt)) ?></p>
  <?php endif; ?>

  <?php if ($st === PublicationStatus::Scheduled || $st === PublicationStatus::Live): ?>
    <div class="flex flex-col gap-1.5">
      <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground" for="<?= attr($id . '-url') ?>">Live link</label>
      <?php if ($perms->liveLink || ($st === PublicationStatus::Scheduled && $perms->live)): ?>
      <div class="flex flex-wrap items-center gap-2">
        <div class="min-w-0 flex-1"><?= ui_input(new InputProps(type: 'url', id: $id . '-url', class: 'h-8', placeholder: 'https://...', attrs: ['data-bind' => $root . '.live_url', 'maxlength' => '2000'])) ?></div>
        <?php if ($st === PublicationStatus::Scheduled && $perms->live): ?>
          <button type="button" class="<?= attr($btn . ' bg-primary text-primary-foreground hover:bg-primary/90') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy . ' || !' . $s . '.live_url') ?>" data-on:click="<?= attr($act('post', '/live')) ?>">Mark live</button>
        <?php elseif ($perms->liveLink): ?>
          <button type="button" class="<?= attr($btn . ' border border-border hover:bg-accent hover:text-accent-foreground') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>" data-on:click="<?= attr($act('patch', '/live-link')) ?>">Save link</button>
        <?php endif; ?>
      </div>
      <?php elseif ($p->liveUrl !== ''): ?>
        <a id="<?= attr($id . '-url') ?>" class="truncate text-sm text-primary underline-offset-4 hover:underline" href="<?= attr($p->liveUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($p->liveUrl) ?></a>
      <?php else: ?>
        <p id="<?= attr($id . '-url') ?>" class="text-sm text-muted-foreground">Not added yet</p>
      <?php endif; ?>
      <?php if ($st === PublicationStatus::Live && $p->liveUrl !== '' && ($perms->liveLink)): ?>
        <a class="truncate text-xs text-primary underline-offset-4 hover:underline" href="<?= attr($p->liveUrl) ?>" target="_blank" rel="noopener noreferrer">Open the post</a>
      <?php endif; ?>
    </div>
    <div class="flex flex-col gap-1.5">
      <?php if ($perms->promote): ?>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" class="size-4 accent-primary" data-bind="<?= attr($root . '.promoted') ?>"<?= $p->promoted ? ' checked' : '' ?> data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>" data-on:change="<?= attr($act('patch', '/promoted')) ?>">
          Promoted (paid boost on <?= e($p->platform->label()) ?>)
        </label>
        <?= ui_input(new InputProps(type: 'text', class: 'h-8', placeholder: 'Promoted note (optional, no amounts)', attrs: ['aria-label' => 'Promoted note', 'maxlength' => '500', 'data-bind' => $root . '.promoted_note', 'data-indicator' => $busy, 'data-on:change' => $act('patch', '/promoted')])) ?>
      <?php else: ?>
        <p class="text-sm"><span class="text-muted-foreground">Promoted:</span> <?= $p->promoted ? 'Yes' . ($p->promotedNote !== '' ? ' (' . e($p->promotedNote) . ')' : '') : 'No' ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php $canBack = $perms->reopen && $st->previous() !== null; $canArchive = $perms->archive && $st !== PublicationStatus::Archived && $st !== PublicationStatus::Checking;
        // Back to checking: the Producer's test report found a problem (shown when "Move back" does not already land there).
        $canRecheck = $perms->recheck && ($st === PublicationStatus::Scheduled || ($st === PublicationStatus::ReadyToSchedule && !$canBack)); ?>
  <?php if ($canBack || $canArchive || $canRecheck): ?>
  <details class="rounded-md border border-dashed border-border px-3 py-2 text-sm">
    <summary class="cursor-pointer text-muted-foreground">Move back or archive</summary>
    <div class="mt-2 flex flex-col gap-2">
      <?= ui_input(new InputProps(type: 'text', class: 'h-8', placeholder: 'Reason (required)', attrs: ['aria-label' => 'Reason', 'maxlength' => '1000', 'data-bind' => $root . '.reason'])) ?>
      <div class="flex flex-wrap gap-2">
        <?php if ($canBack): ?>
          <button type="button" class="<?= attr($btn . ' border border-border hover:bg-accent hover:text-accent-foreground') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy . ' || !' . $s . '.reason') ?>" data-on:click="<?= attr($act('post', '/reopen')) ?>">Move back to <?= e(strtolower($st->previous()?->label() ?? '')) ?></button>
        <?php endif; ?>
        <?php if ($canRecheck): ?>
          <button type="button" class="<?= attr($btn . ' border border-border hover:bg-accent hover:text-accent-foreground') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy . ' || !' . $s . '.reason') ?>" data-on:click="<?= attr($act('post', '/recheck')) ?>">Back to checking</button>
        <?php endif; ?>
        <?php if ($canArchive): ?>
          <button type="button" class="<?= attr($btn . ' bg-destructive text-white hover:bg-destructive/90') ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy . ' || !' . $s . '.reason') ?>" data-on:click="<?= attr($act('post', '/archive')) ?>">Archive post</button>
        <?php endif; ?>
      </div>
    </div>
  </details>
  <?php endif; ?>
</article>
<?php
    return (string) ob_get_clean();
}

/** #today-section-social: the Social block of My day (refreshed every 60 seconds). */
function partial_today_social(SocialDayVM $vm): string
{
    $list = static function (string $title, array $items, string $empty, string $more): string {
        /** @var list<SocialDayItemVM> $items */
        $h = '<div class="flex flex-col gap-2"><h4 class="text-sm font-semibold">' . e($title) . ' <span class="font-normal text-muted-foreground">(' . count($items) . ')</span></h4>';
        if ($items === []) {
            return $h . '<p class="rounded-md border border-dashed border-border px-3 py-3 text-sm text-muted-foreground">' . e($empty) . '</p></div>';
        }
        $h .= '<ul class="divide-y divide-border rounded-lg border border-border">';
        foreach (array_slice($items, 0, 8) as $it) {
            $h .= '<li><a href="' . attr($it->href) . '" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2.5 hover:bg-muted/50">'
                . '<span class="w-20 shrink-0 font-mono text-xs text-muted-foreground">' . e($it->jobNumber) . '</span>'
                . '<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">' . e($it->title) . '</span><span class="block truncate text-xs text-muted-foreground">' . e($it->detail) . '</span></span>'
                . ($it->when !== '' ? '<span class="shrink-0 text-xs text-muted-foreground">' . e($it->when) . '</span>' : '') . '</a></li>';
        }
        $h .= '</ul>';
        if (count($items) > 8) {
            $h .= '<p class="text-sm text-muted-foreground">' . (count($items) - 8) . ' more. <a class="underline" href="' . attr($more) . '">Open the Social queue</a>.</p>';
        }
        return $h . '</div>';
    };
    $body = '<div class="flex flex-wrap items-center justify-between gap-2"><h3 id="h-today-section-social" class="text-base font-semibold">Social</h3>'
        . '<a class="text-sm text-primary underline-offset-4 hover:underline" href="' . attr(url('/social')) . '">Open the queue</a></div>'
        . $list('Approved, to check', $vm->toCheck, 'Nothing is waiting for a final check.', url('/social'))
        . $list('Scheduled today', $vm->scheduledToday, 'Nothing is scheduled for today.', url('/social', ['tab' => 'scheduled']))
        . $list('Live without a link', $vm->needLink, 'Every post that went out has its link.', url('/social', ['tab' => 'scheduled']));
    return ui_card(new PartProps(class: 'px-6', attrs: ['id' => 'today-section-social', 'aria-labelledby' => 'h-today-section-social',
        'data-on-interval__duration.60s' => act_raw('get', url('/today/social'))]), '<div class="flex flex-col gap-4">' . $body . '</div>');
}

/** scheduled_at ('Y-m-d H:i', SA time) -> '12 Oct 2026, 09:30'; '' for null. */
function fmt_scheduled(?string $at): string
{
    if ($at === null || strlen($at) < 16) {
        return '';
    }
    return trim(fmt_date(substr($at, 0, 10)) . ', ' . substr($at, 11, 5), ', ');
}
