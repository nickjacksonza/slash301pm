<?php
declare(strict_types=1);

use App\Domain\DueBucket;
use App\Domain\MyDay;
use App\Domain\MyDayMode;
use App\Domain\Types\MyDayChange;
use App\Domain\Types\MyDayItem;
use App\Domain\Types\MyDaySection;
use App\Domain\Types\MyDayStrip;
use App\View\ui\AvatarProps;
use App\View\ui\BadgeProps;
use App\View\ui\ButtonProps;
use App\View\ui\PartProps;
use App\View\VM\MyDayVM;

/**
 * GET /today. One signal, today.brand (the brand row's filter). Each section is
 * a card with id today-section-<key> that GET /today/sections/<key> patches;
 * every 60 seconds the page asks for #today-body (brand row and sections).
 */
function page_today(MyDayVM $vm, ?\App\View\VM\SocialDayVM $social = null): string
{
    // One request refreshes the brand row and every section (they all follow today.brand).
    $refresh = act_raw('get', url('/today/body'), 'filterSignals: {include: /^today\\./}');
    ob_start(); ?>
<div id="today" class="mx-auto flex w-full max-w-4xl flex-col gap-6" data-signals="<?= attr(jobs_js(['today' => ['brand' => $vm->brandId]])) ?>" data-on-interval__duration.60s="<?= attr($refresh) ?>">
  <div>
    <h2 class="text-2xl font-semibold leading-tight"><?= e($vm->greeting) ?></h2>
    <p class="text-sm text-muted-foreground"><?= e($vm->dateLabel) ?></p>
  </div>
  <?php // Social publishing: role Social or a Social slot holder (refreshes itself via GET /today/social) ?>
  <?php if ($social !== null): ?><?= partial_today_social($social) ?><?php endif; ?>
  <?= partial_today_body($vm) ?>
</div>
<?php
    return (string) ob_get_clean();
}

/** #today-body: the brand row and every section, patched as one by GET /today/body. */
function partial_today_body(MyDayVM $vm): string
{
    $out = '<div id="today-body" class="flex flex-col gap-6">' . partial_today_brands($vm);
    foreach (MyDay::sectionKeys($vm->result->mode) as $key) {
        $out .= partial_today_section($key, $vm);
    }
    return $out . '</div>';
}

/**
 * The brand filter row (owner decision 2026-10): one button per brand with
 * open jobs in this My day, showing its logo (an https link checked again
 * here) or a coloured initials badge, with the job count. A button sets
 * today.brand and fetches #today-body; "All" clears it. Buttons are real
 * <button>s with aria-pressed and the brand name as the accessible label.
 */
function partial_today_brands(MyDayVM $vm): string
{
    if ($vm->brands === []) {
        return '';
    }
    $fetch = act_raw('get', url('/today/body'), 'filterSignals: {include: /^today\\./}');
    $total = 0;
    foreach ($vm->brands as $b) {
        $total += $b->jobCount;
    }
    $count = static fn (int $n): string => '<span class="rounded-full bg-muted px-1.5 text-xs font-medium tabular-nums text-muted-foreground">' . $n . '</span>';
    $all = $vm->brandId === '';
    $html = ui_button(new ButtonProps(variant: $all ? 'default' : 'outline', size: 'sm', attrs: [
        'aria-pressed' => $all ? 'true' : 'false', 'aria-label' => 'All brands, ' . $total . ($total === 1 ? ' job' : ' jobs'),
        'data-on:click' => "\$today.brand = ''; " . $fetch,
    ]), 'All ' . $count($total));
    foreach ($vm->brands as $b) {
        $on = $vm->brandId === $b->id;
        $logo = \App\Domain\Links::isHttpsUrl($b->logoUrl)
            ? '<img src="' . attr($b->logoUrl) . '" alt="" class="size-6 rounded-sm bg-white object-contain" loading="lazy" referrerpolicy="no-referrer" width="24" height="24">'
            : ui_avatar(new AvatarProps(name: $b->name, class: 'size-6 text-[10px] font-semibold', attrs: ['aria-hidden' => 'true']));
        $html .= ui_button(new ButtonProps(variant: $on ? 'default' : 'outline', size: 'sm', attrs: [
            'aria-pressed' => $on ? 'true' : 'false', 'aria-label' => $b->name . ', ' . $b->jobCount . ($b->jobCount === 1 ? ' job' : ' jobs'), 'title' => $b->name,
            'data-on:click' => '$today.brand = ' . jobs_js($b->id) . '; ' . $fetch,
        ]), $logo . '<span class="max-w-32 truncate">' . e($b->name) . '</span>' . $count($b->jobCount));
    }
    return '<nav id="today-brands" aria-label="Filter My day by brand" class="flex flex-wrap items-center gap-2">' . $html . '</nav>';
}

/** One refreshable block of the page, by section key. Top-level id: today-section-<key>. */
function partial_today_section(string $key, MyDayVM $vm): string
{
    $res = $vm->result;
    $briefs = url('/briefs');
    $create = $vm->canCreate ? ' <a class="underline" href="' . attr($briefs) . '">Create a brief</a>.' : '';
    if ($res->mode !== MyDayMode::Owner) {
        $jobs = url('/jobs');
        $traffic = $res->mode === MyDayMode::Traffic;
        $none = new MyDaySection($key, [], 0);
        return match ($key) {
            MyDay::STRIP => partial_today_strip($res->strip, $res->mode),
            MyDay::TEAM => partial_today_jobs($res->team ?? $none, 'Briefs waiting for Traffic', 'Briefed jobs on which you are Traffic that nobody makes yet, and briefs sent or updated since you last cleared "Changed by others".',
                'No brief is waiting for a team.', true, null, $jobs, 'See all in Jobs'),
            MyDay::BRIEFS => partial_today_jobs($res->briefs ?? $none, 'New and updated briefs', 'Briefs on your jobs sent or updated since you last cleared "Changed by others". Open one to read the sent version.',
                'No new or updated briefs on your jobs.', true, null, $jobs, 'See all in Jobs'),
            MyDay::OVERDUE => partial_today_jobs($res->overdue, 'Overdue', ($traffic ? 'Open jobs you are on' : 'Your assigned jobs') . ' past their due date (South African time).',
                'No overdue jobs. Nice work.', false, null, $jobs, 'See all in Jobs'),
            MyDay::DUE_SOON => partial_today_jobs($res->dueSoon, 'Due soon', 'Due today or in the next 3 business days.',
                $traffic ? 'Nothing due in the next 3 business days.' : 'Nothing due in the next 3 business days. When Traffic assigns you to a job it shows up here.', false, null, $jobs, 'See all in Jobs'),
            MyDay::CHANGED => partial_today_changed($res->changed, $res->changedTotal),
            default => throw new InvalidArgumentException('Unknown section ' . $key),
        };
    }
    return match ($key) {
        MyDay::STRIP => partial_today_strip($res->strip, $res->mode),
        MyDay::OVERDUE => partial_today_jobs($res->overdue, 'Overdue', 'Your open jobs past their due date (South African time).',
            'No overdue jobs. Nice work.'),
        MyDay::DUE_SOON => partial_today_jobs($res->dueSoon, 'Due soon', 'Due today or in the next 3 business days.',
            'Nothing due in the next 3 business days.' . $create, true),
        MyDay::WAITING => partial_today_jobs($res->waiting, 'Waiting on me', 'Jobs waiting on you, drafts and briefs with changes still to send.',
            'Nothing is waiting on you.' . $create, true, $res->claimable),
        MyDay::CHANGED => partial_today_changed($res->changed, $res->changedTotal),
        default => throw new InvalidArgumentException('Unknown section ' . $key),
    };
}

function partial_today_strip(MyDayStrip $s, MyDayMode $mode = MyDayMode::Owner): string
{
    $third = match ($mode) {
        MyDayMode::Owner => ['Waiting on me', $s->waitingOnMe, 'Waiting, drafts and unsent changes'],
        MyDayMode::Traffic => ['Waiting for Traffic', $s->waitingOnMe, 'Briefs that need a team, and new or updated briefs'],
        MyDayMode::Assigned => ['New or updated briefs', $s->waitingOnMe, 'Briefs sent or updated since you last looked'],
    };
    $tiles = [
        ['Due by Sunday', $s->dueThisWeek, 'Open jobs due from today to Sunday'],
        ['Overdue', $s->overdue, 'Open jobs past their due date'],
        $third,
        [$mode === MyDayMode::Owner ? 'Sent this week' : 'Briefed this week', $s->sentThisWeek, $mode === MyDayMode::Owner ? 'Briefs sent since Monday' : 'Briefs sent or updated on your jobs since Monday'],
    ];
    ob_start(); ?>
<section id="today-section-strip" aria-label="This week" class="grid grid-cols-2 gap-3 md:grid-cols-4">
  <?php foreach ($tiles as [$label, $count, $hint]): ?>
    <div class="rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm" title="<?= attr($hint) ?>">
      <p class="text-2xl font-semibold leading-none"><?= (int) $count ?></p>
      <p class="mt-1 text-xs text-muted-foreground"><?= e($label) ?></p>
    </div>
  <?php endforeach; ?>
</section>
<?php
    return (string) ob_get_clean();
}

/** @param string $emptyHtml already-escaped HTML (it may hold the create link) */
function partial_today_jobs(MyDaySection $sec, string $title, string $intro, string $emptyHtml, bool $showReason = false, ?MyDaySection $claimable = null,
    string $moreUrl = '', string $moreLabel = 'See all in Briefs'): string
{
    $moreUrl = $moreUrl !== '' ? $moreUrl : url('/briefs');
    $id = 'today-section-' . $sec->key;
    $heading = 'h-' . $id;
    $more = $sec->total - count($sec->items);
    $body = '<p class="text-sm text-muted-foreground">' . e($intro) . '</p>';
    if ($sec->items === []) {
        $body .= '<p class="rounded-md border border-dashed border-border px-3 py-4 text-sm text-muted-foreground">' . $emptyHtml . '</p>';
    } else {
        $body .= '<ul class="divide-y divide-border rounded-lg border border-border">';
        foreach ($sec->items as $it) {
            $body .= '<li>' . partial_today_job_row($it, $showReason) . '</li>';
        }
        $body .= '</ul>';
        if ($more > 0) {
            $body .= '<p class="text-sm text-muted-foreground">' . $more . ' more. <a class="underline" href="' . attr($moreUrl) . '">' . e($moreLabel) . '</a>.</p>';
        }
    }
    if ($claimable !== null && $claimable->total > 0) {
        $body .= '<h4 class="mt-2 text-sm font-semibold">No AM yet <span class="font-normal text-muted-foreground">(' . $claimable->total . ')</span></h4>'
            . '<p class="text-sm text-muted-foreground">Open jobs nobody holds as AM. Open one and choose "Make me AM" to take it over.</p>'
            . '<ul class="divide-y divide-border rounded-lg border border-border">';
        foreach ($claimable->items as $it) {
            $body .= '<li>' . partial_today_job_row($it, false) . '</li>';
        }
        $body .= '</ul>';
        if ($claimable->total > count($claimable->items)) {
            $body .= '<p class="text-sm text-muted-foreground">' . ($claimable->total - count($claimable->items)) . ' more. <a class="underline" href="' . attr(url('/briefs')) . '">See all in Briefs</a>.</p>';
        }
    }
    return ui_card(new PartProps(class: 'px-6', attrs: ['id' => $id, 'aria-labelledby' => $heading]),
        '<div class="flex flex-col gap-3"><h3 id="' . attr($heading) . '" class="text-base font-semibold">' . e($title)
        . ' <span class="text-sm font-normal text-muted-foreground">(' . $sec->total . ')</span></h3>' . $body . '</div>');
}

function partial_today_job_row(MyDayItem $it, bool $showReason): string
{
    $due = fmt_due($it);
    $badges = '';
    if ($it->bucket === DueBucket::Overdue) {
        $badges .= ui_badge(new BadgeProps(stage: 'overdue'), e($due));
    } elseif ($it->bucket === DueBucket::Today || $it->bucket === DueBucket::Next3BusinessDays) {
        $badges .= ui_badge(new BadgeProps(stage: 'due_soon'), e($due));
    } elseif ($due !== '') {
        $badges .= ui_badge(new BadgeProps(variant: 'outline'), e($due));
    }
    if ($it->sent) {
        $badges .= ui_badge(new BadgeProps(variant: 'outline'), e($it->version->label()));
    }
    $badges .= ui_badge(new BadgeProps(stage: $it->stage->value), e($it->stage->label()));
    $context = trim($it->brandName . ($it->brandName !== '' && $it->campaignName !== '' ? ' · ' : '') . $it->campaignName);
    ob_start(); ?>
<a href="<?= attr(url('/jobs/' . rawurlencode($it->jobId) . '/brief')) ?>" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2.5 hover:bg-muted/50">
  <span class="w-20 shrink-0 font-mono text-xs text-muted-foreground"><?= e($it->jobNumber) ?></span>
  <span class="min-w-0 flex-1">
    <span class="block truncate text-sm font-medium"><?= e($it->title) ?></span>
    <?php if ($context !== ''): ?><span class="block truncate text-xs text-muted-foreground"><?= e($context) ?></span><?php endif; ?>
    <?php if ($showReason && $it->reason !== ''): ?><span class="block text-xs text-foreground"><?= e($it->reason) ?></span><?php endif; ?>
  </span>
  <span class="flex max-w-full flex-wrap items-center gap-2"><?= $badges ?></span>
</a>
<?php
    return (string) ob_get_clean();
}

/** @param list<MyDayChange> $changes */
function partial_today_changed(array $changes, int $total): string
{
    $id = 'today-section-' . MyDay::CHANGED;
    $more = $total - count($changes);
    $head = '<div class="flex flex-wrap items-center justify-between gap-2"><h3 id="h-' . attr($id) . '" class="text-base font-semibold">Changed by others'
        . ' <span class="text-sm font-normal text-muted-foreground">(' . $total . ')</span></h3>';
    if ($changes !== []) {
        $head .= ui_button(new ButtonProps(variant: 'outline', size: 'sm', attrs: ['data-on:click' => act_raw('post', url('/today/seen'), 'retryMaxCount: 0')]), 'Mark all seen');
    }
    $head .= '</div>';
    $body = '<p class="text-sm text-muted-foreground">Updates to your jobs and anything addressed to you since you last cleared this list.</p>';
    if ($changes === []) {
        $body .= '<p class="rounded-md border border-dashed border-border px-3 py-4 text-sm text-muted-foreground">Nothing has changed since you last looked.</p>';
    } else {
        $body .= '<ul class="divide-y divide-border rounded-lg border border-border">';
        foreach ($changes as $c) {
            $a = $c->activity;
            $who = $a->actorName !== '' ? $a->actorName : 'Someone';
            $link = $a->jobId !== null ? url('/jobs/' . rawurlencode($a->jobId) . '/brief') : url('/briefs');
            $body .= '<li><a href="' . attr($link) . '" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-3 py-2.5 hover:bg-muted/50">'
                . '<span class="min-w-0 flex-1 text-sm"><strong class="font-medium">' . e($who) . '</strong> ' . e(MyDay::verbPhrase($a->verb))
                . ($c->jobNumber !== '' ? ' on <span class="font-mono text-xs">' . e($c->jobNumber) . '</span> ' . e($c->jobTitle) : '') . '</span>'
                . '<span class="shrink-0 text-xs text-muted-foreground">' . e(fmt_when($a->createdAt)) . '</span></a></li>';
        }
        $body .= '</ul>';
        if ($more > 0) {
            $body .= '<p class="text-sm text-muted-foreground">' . $more . ' more. Mark all seen to clear them.</p>';
        }
    }
    return ui_card(new PartProps(class: 'px-6', attrs: ['id' => $id, 'aria-labelledby' => 'h-' . $id]), '<div class="flex flex-col gap-3">' . $head . $body . '</div>');
}

/** 'Overdue 3 days', 'Due today', 'Due Tue 13 Oct', 'Due 20 Oct 2026'; '' without a date. */
function fmt_due(MyDayItem $it): string
{
    if ($it->dueDate === null) {
        return '';
    }
    return match ($it->bucket) {
        DueBucket::Overdue => 'Overdue ' . abs($it->daysToDue) . (abs($it->daysToDue) === 1 ? ' day' : ' days'),
        DueBucket::Today => 'Due today',
        DueBucket::Next3BusinessDays => 'Due ' . (new DateTimeImmutable($it->dueDate))->format('D j M'),
        default => 'Due ' . fmt_date($it->dueDate),
    };
}
