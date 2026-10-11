<?php
declare(strict_types=1);

use App\Domain\Types\ReviewDayItem;
use App\View\ui\BadgeProps;
use App\View\ui\NativeSelectProps;
use App\View\ui\PartProps;
use App\View\VM\ReviewCardVM;
use App\View\VM\ReviewDayVM;
use App\View\VM\ReviewHistoryVM;
use App\View\VM\ReviewJobBarVM;
use App\View\VM\ReviewPageVM;

/**
 * GET /jobs/{id}/review: one scrollable column of post cards (partial_post_preview
 * on the left, the decision on the right, stacked on narrow screens), a
 * sticky header with Previous/Next (public/js/review.js: j/k and the arrow
 * keys) and the filter (_rf: mine | all | rejected, UI only), and the job
 * action bar. Signals: rv_<key>.{rv, part, decision, feedback} per card,
 * _fb_<key> (feedback box open), jr.{rv, to} for the job bar.
 */
function page_review(ReviewPageVM $vm): string
{
    ob_start(); ?>
<div id="review" class="mx-auto flex w-full max-w-4xl flex-col gap-4" data-signals="<?= attr(jobs_js(['_rf' => $vm->defaultFilter])) ?>">
  <div class="sticky top-0 z-30 -mx-4 flex flex-col gap-2 border-b border-border bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <p class="truncate text-xs text-muted-foreground"><span class="font-mono"><?= e($vm->jobNumber) ?></span><?= $vm->brandName !== '' ? ' · ' . e($vm->brandName) : '' ?><?= $vm->versionLabel !== '' ? ' · brief ' . e($vm->versionLabel) : '' ?></p>
        <h2 class="truncate text-lg font-semibold leading-tight md:text-xl"><?= e($vm->title) ?></h2>
      </div>
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <?= ui_badge(new BadgeProps(stage: $vm->stage->value), e($vm->stage->label())) ?>
        <a class="text-primary underline-offset-4 hover:underline" href="<?= attr($vm->briefUrl) ?>">Brief</a>
        <?php if ($vm->workUrl !== ''): ?><a class="text-primary underline-offset-4 hover:underline" href="<?= attr($vm->workUrl) ?>">Hand in work</a><?php endif; ?>
      </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <button type="button" data-review-prev class="inline-flex h-8 items-center rounded-md border border-border px-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground" aria-keyshortcuts="k ArrowUp ArrowLeft">Previous</button>
        <button type="button" data-review-next class="inline-flex h-8 items-center rounded-md border border-border px-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground" aria-keyshortcuts="j ArrowDown ArrowRight">Next</button>
        <span data-review-pos aria-live="polite" class="text-sm text-muted-foreground"></span>
      </div>
      <?= partial_review_summary($vm) ?>
    </div>
    <p class="hidden text-xs text-muted-foreground md:block">Keys: j or the down/right arrow for the next post, k or the up/left arrow for the previous one.</p>
  </div>
  <?= partial_review_jobbar($vm->bar) ?>
  <div id="review-posts" class="flex flex-col gap-6">
    <?php if ($vm->cards === []): ?>
      <p class="rounded-md border border-dashed border-border px-3 py-6 text-center text-sm text-muted-foreground">This job has no posts to review.</p>
    <?php endif; ?>
    <?php foreach ($vm->cards as $c): ?>
      <?= partial_review_card($c) ?>
    <?php endforeach; ?>
  </div>
</div>
<script type="module" data-cfasync="false" src="<?= attr(asset('js/review.js')) ?>"></script>
<?php
    return (string) ob_get_clean();
}

/** #review-summary: the filter with counts, and the empty-filter note. Re-rendered after every write. */
function partial_review_summary(ReviewPageVM $vm): string
{
    $mine = $vm->countMine();
    $rejected = $vm->countRejected();
    $all = count($vm->cards);
    $btn = static function (string $value, string $label, int $n): string {
        $is = '$_rf === ' . "'" . $value . "'";
        return '<button type="button" class="inline-flex h-8 items-center gap-1 rounded-md px-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground"'
            . ' data-on:click="' . attr('$_rf = ' . "'" . $value . "'") . '"'
            . ' data-class="' . attr('{\'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground\': ' . $is . '}') . '"'
            . ' data-attr:aria-pressed="' . attr($is . " ? 'true' : 'false'") . '">' . e($label) . ' <span class="text-xs opacity-80">' . $n . '</span></button>';
    };
    $empty = "(\$_rf === 'mine' && " . ($mine === 0 ? 'true' : 'false') . ") || (\$_rf === 'rejected' && " . ($rejected === 0 ? 'true' : 'false') . ')';
    ob_start(); ?>
<div id="review-summary" class="flex flex-wrap items-center gap-2">
  <div role="group" aria-label="Show" class="flex flex-wrap items-center gap-1 rounded-lg border border-border p-0.5">
    <?= $btn('mine', 'Waiting for me', $mine) ?>
    <?= $btn('all', 'All', $all) ?>
    <?= $btn('rejected', 'Rejected', $rejected) ?>
  </div>
  <p style="display: none" data-show="<?= attr($empty) ?>" class="text-sm text-muted-foreground">Nothing here. <button type="button" class="underline" data-on:click="$_rf = 'all'">Show all posts</button></p>
</div>
<?php
    return (string) ob_get_clean();
}

/** #review-jobbar: the job's review state and the job actions this person may take. */
function partial_review_jobbar(ReviewJobBarVM $b): string
{
    $base = url('/jobs/' . rawurlencode($b->jobId) . '/review');
    $only = 'filterSignals: {include: /^jr\\./}, retryMaxCount: 0';
    $primary = 'inline-flex h-9 items-center justify-center rounded-md bg-green-700 px-4 text-sm font-medium text-white shadow-xs hover:bg-green-800 disabled:pointer-events-none disabled:opacity-50';
    $outline = 'inline-flex h-9 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium shadow-xs hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50';
    $button = static fn (string $class, string $path, string $label, string $confirm = ''): string => '<button type="button" class="' . attr($class) . '" data-indicator="_jrbusy" data-attr:disabled="$_jrbusy"'
        . ' data-on:click="' . attr(($confirm !== '' ? 'confirm(' . "'" . $confirm . "'" . ') && ' : '') . act_raw('post', $base . $path, $only)) . '">' . e($label) . '</button>';
    $any = $b->canApproveJob || $b->canRequestEcd || $b->canEcdApprove || $b->canReassign || $b->canMarkReady || $b->canSend;
    ob_start(); ?>
<section id="review-jobbar" class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm" aria-labelledby="review-jobbar-title"
  data-signals="<?= attr(jobs_js(['jr' => ['rv' => $b->rowVersion, 'to' => '']])) ?>">
  <div class="flex flex-wrap items-baseline justify-between gap-2">
    <h3 id="review-jobbar-title" class="text-sm font-semibold">Job review: <?= e($b->statusLabel) ?> <span class="font-normal text-muted-foreground">· round <?= (int) $b->jobRound ?></span></h3>
  </div>
  <?php if ($b->facts !== []): ?>
    <ul class="flex flex-col gap-0.5 text-sm text-muted-foreground"><?php foreach ($b->facts as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if ($b->hint !== ''): ?><p class="text-sm"><?= e($b->hint) ?></p><?php endif; ?>
  <?php if ($any): ?>
  <div class="flex flex-wrap items-center gap-2">
    <?php if ($b->canApproveJob): ?><?= $button($primary, '/approve', 'Approve job', 'Approve the whole job? Every post is approved. The ECD is told.') ?><?php endif; ?>
    <?php if ($b->canRequestEcd): ?><?= $button($outline, '/request-ecd', 'Request ECD review') ?><?php endif; ?>
    <?php if ($b->canEcdApprove): ?><?= $button($primary, '/ecd-approve', 'Approve job (ECD)', 'Give the ECD approval for this job? The AM and the CD are told.') ?><?php endif; ?>
    <?php if ($b->canMarkReady): ?><?= $button($outline, '/ready', 'Mark ready for client') ?><?php endif; ?>
    <?php if ($b->canSend): ?><?= $button($primary, '/send', 'Send to client', 'Record that the job was sent to the client?') ?><?php endif; ?>
    <?php if ($b->canReassign): ?>
      <div class="flex flex-wrap items-center gap-2">
        <label for="review-reassign" class="sr-only">Reassign the review to</label>
        <div class="w-56"><?= ui_native_select(new NativeSelectProps(id: 'review-reassign', options: $b->reassignTo, placeholder: 'Reassign review to...', attrs: ['data-bind' => 'jr.to'])) ?></div>
        <button type="button" class="<?= attr($outline) ?>" data-indicator="_jrbusy" data-attr:disabled="$_jrbusy || !$jr.to"
          data-on:click="<?= attr(act_raw('post', $base . '/reassign', $only)) ?>">Reassign</button>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>
<?php
    return (string) ob_get_clean();
}

/** #post-<key>: one post card. */
function partial_review_card(ReviewCardVM $c): string
{
    $root = 'rv_' . $c->key;
    $fb = '_fb_' . $c->key;
    $busy = '_rvbusy_' . $c->key;
    $s = '$' . $root;
    $only = 'filterSignals: {include: /^' . $root . '\\./}, retryMaxCount: 0';
    $post = act_raw('post', $c->decideUrl, $only);
    $show = "\$_rf === 'all' || (\$_rf === 'mine' && " . ($c->waitingForMe ? 'true' : 'false') . ") || (\$_rf === 'rejected' && " . ($c->rejected ? 'true' : 'false') . ')';
    $signals = [$root => ['rv' => $c->rowVersion, 'part' => $c->defaultPart, 'decision' => '', 'feedback' => ''], $fb => false];
    $big = 'inline-flex h-10 w-full items-center justify-center rounded-md px-4 text-sm font-semibold text-white shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:opacity-50';
    $green = 'bg-green-700 hover:bg-green-800 focus-visible:outline-green-700';
    $orange = 'bg-orange-700 hover:bg-orange-800 focus-visible:outline-orange-700';
    $red = 'bg-red-700 hover:bg-red-800 focus-visible:outline-red-700';
    $partLabel = ['both' => 'Both', 'copy' => 'Copy only', 'media' => 'Media only'];
    $heading = 'h-post-' . $c->key;
    ob_start(); ?>
<article id="<?= attr('post-' . $c->key) ?>" data-review-card data-mine="<?= $c->waitingForMe ? '1' : '0' ?>" data-rejected="<?= $c->rejected ? '1' : '0' ?>"
  data-show="<?= attr($show) ?>" tabindex="-1" aria-labelledby="<?= attr($heading) ?>"
  class="scroll-mt-44 rounded-xl border border-border bg-card p-4 text-card-foreground shadow-sm outline-none data-[current]:ring-2 data-[current]:ring-ring md:p-5"
  data-signals="<?= attr(jobs_js($signals)) ?>">
  <header class="flex flex-wrap items-start justify-between gap-2">
    <div class="min-w-0">
      <p class="text-xs text-muted-foreground">Post <?= (int) $c->index ?> of <?= (int) $c->total ?> · Round <?= (int) $c->round ?></p>
      <h3 id="<?= attr($heading) ?>" class="break-words text-base font-semibold"><?= e($c->assetName) ?></h3>
      <?php if ($c->lineLabel !== ''): ?><p class="text-xs text-muted-foreground"><?= e($c->lineLabel) ?></p><?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-1.5">
      <?= partial_part_chip('Copy', $c->copyState) ?>
      <?php if ($c->needsMedia): ?><?= partial_part_chip('Media', $c->mediaState) ?><?php endif; ?>
    </div>
  </header>
  <div class="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_15rem]">
    <div class="min-w-0"><?= partial_post_preview($c->preview) ?></div>
    <div class="flex flex-col gap-2">
      <?php if ($c->canDecide): ?>
        <?php if (count($c->partChoices) > 1 || $c->needsMedia): ?>
        <div role="group" aria-label="Which part" class="grid grid-cols-3 gap-1 rounded-lg border border-border p-0.5">
          <?php foreach (['both', 'copy', 'media'] as $choice): if ($choice !== 'copy' && !$c->needsMedia) { continue; } $on = in_array($choice, $c->partChoices, true); $is = $s . '.part === ' . "'" . $choice . "'"; ?>
            <button type="button" class="inline-flex h-8 items-center justify-center rounded-md px-1 text-xs font-medium hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-40"
              <?= $on ? '' : 'disabled' ?> data-on:click="<?= attr($s . '.part = ' . "'" . $choice . "'") ?>"
              data-class="<?= attr('{\'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground\': ' . $is . '}') ?>"
              data-attr:aria-pressed="<?= attr($is . " ? 'true' : 'false'") ?>"><?= e($partLabel[$choice]) ?></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <button type="button" class="<?= attr($big . ' ' . $green) ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>"
          data-on:click="<?= attr($s . ".decision = 'approved'; " . $post) ?>">Approve</button>
        <button type="button" class="<?= attr($big . ' ' . $orange) ?>" aria-controls="<?= attr('fb-' . $c->key) ?>" data-attr:aria-expanded="<?= attr('$' . $fb . " ? 'true' : 'false'") ?>"
          data-on:click="<?= attr('$' . $fb . ' = !$' . $fb) ?>">Reject with feedback</button>
        <div id="<?= attr('fb-' . $c->key) ?>" class="flex flex-col gap-2 rounded-md border border-orange-300 p-2 dark:border-orange-800" style="display: none" data-show="<?= attr('$' . $fb) ?>">
          <label for="<?= attr('fbt-' . $c->key) ?>" class="text-xs font-medium">Feedback for the maker (required)</label>
          <textarea id="<?= attr('fbt-' . $c->key) ?>" rows="4" required maxlength="4000" class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            data-bind="<?= attr($root . '.feedback') ?>"></textarea>
          <button type="button" class="<?= attr($big . ' ' . $orange) ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy . ' || !' . $s . '.feedback.trim()') ?>"
            data-on:click="<?= attr($s . ".decision = 'rejected_with_feedback'; " . $post) ?>">Send feedback</button>
        </div>
        <button type="button" class="<?= attr($big . ' ' . $red) ?>" data-indicator="<?= attr($busy) ?>" data-attr:disabled="<?= attr('$' . $busy) ?>"
          data-on:click="<?= attr("confirm('Reject this without feedback? It goes back to the maker.') && (" . $s . ".decision = 'rejected') && " . $post) ?>">Reject</button>
      <?php else: ?>
        <p class="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground"><?= e($c->cannotDecideReason !== '' ? $c->cannotDecideReason : 'You can read this post.') ?></p>
      <?php endif; ?>
    </div>
  </div>
  <footer class="mt-4 flex flex-col gap-2 border-t border-border pt-3 text-sm">
    <p class="text-muted-foreground">Copy by <?= e($c->copyMaker !== '' ? $c->copyMaker : 'nobody yet') ?><?php if ($c->needsMedia): ?> · Media by <?= e($c->mediaMaker !== '' ? $c->mediaMaker : 'nobody yet') ?><?php endif; ?> · <?= e($c->handedInLine) ?></p>
    <?php if ($c->note !== ''): ?><p><span class="font-medium">Maker's note:</span> <span class="whitespace-pre-line"><?= e($c->note) ?></span></p><?php endif; ?>
    <?= partial_review_history($c->history) ?>
  </footer>
</article>
<?php
    return (string) ob_get_clean();
}

/** Earlier decisions, newest first ("Previous feedback"). @param list<ReviewHistoryVM> $history */
function partial_review_history(array $history): string
{
    if ($history === []) {
        return '';
    }
    $h = '<details class="rounded-md border border-border px-3 py-2"' . ($history[0]->decision->isRejection() ? ' open' : '') . '><summary class="cursor-pointer text-sm font-medium">Previous feedback (' . count($history) . ')</summary><ul class="mt-2 flex flex-col gap-2">';
    foreach ($history as $r) {
        $tone = match ($r->decision) {
            \App\Domain\ReviewDecision::Approved => 'text-green-800 dark:text-green-300',
            \App\Domain\ReviewDecision::RejectedWithFeedback => 'text-orange-800 dark:text-orange-300',
            \App\Domain\ReviewDecision::Rejected => 'text-red-800 dark:text-red-300',
        };
        $h .= '<li class="text-sm"><span class="' . attr('font-medium ' . $tone) . '">' . e($r->part->label() . ': ' . $r->decision->label()) . '</span>'
            . ' <span class="text-xs text-muted-foreground">round ' . (int) $r->round . ' · ' . e($r->reviewerName !== '' ? $r->reviewerName : 'someone') . ' · ' . e($r->when)
            . ($r->briefVersion !== '' ? ' · brief ' . e($r->briefVersion) : '') . '</span>'
            . ($r->feedback !== '' ? '<span class="mt-0.5 block whitespace-pre-line break-words">' . e($r->feedback) . '</span>' : '') . '</li>';
    }
    return $h . '</ul></details>';
}

/** #today-section-reviews: the review lists of My day that apply to this person. */
function partial_today_reviews(ReviewDayVM $vm): string
{
    $list = static function (string $title, ?array $items, string $empty): string {
        if ($items === null) {
            return '';
        }
        /** @var list<ReviewDayItem> $items */
        $h = '<div class="flex flex-col gap-2"><h4 class="text-sm font-semibold">' . e($title) . ' <span class="font-normal text-muted-foreground">(' . count($items) . ')</span></h4>';
        if ($items === []) {
            return $h . '<p class="rounded-md border border-dashed border-border px-3 py-3 text-sm text-muted-foreground">' . e($empty) . '</p></div>';
        }
        $h .= '<ul class="divide-y divide-border rounded-lg border border-border">';
        foreach (array_slice($items, 0, 10) as $it) {
            $href = url('/jobs/' . rawurlencode($it->jobId) . ($it->target === 'work' ? '/work' : '/review'));
            $h .= '<li><a href="' . attr($href) . '" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2.5 hover:bg-muted/50">'
                . '<span class="w-20 shrink-0 font-mono text-xs text-muted-foreground">' . e($it->jobNumber) . '</span>'
                . '<span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">' . e($it->title) . '</span><span class="block truncate text-xs text-muted-foreground">' . e($it->detail) . '</span></span></a></li>';
        }
        $h .= '</ul>';
        if (count($items) > 10) {
            $h .= '<p class="text-sm text-muted-foreground">' . (count($items) - 10) . ' more.</p>';
        }
        return $h . '</div>';
    };
    $d = $vm->day;
    $body = '<h3 id="h-today-section-reviews" class="text-base font-semibold">Reviews</h3>'
        . $list('Waiting for my review', $d->toReview, 'No post is waiting for your review.')
        . $list('Jobs waiting for ECD approval', $d->ecdWaiting, 'No job is waiting for the ECD.')
        . $list('Changes requested', $d->changes, 'Nothing was sent back to you.')
        . $list('Past the round limit', $d->roundWarnings, 'No post of yours has passed the review round limit.')
        . $list('Ready for the client', $d->readyForClient, 'No job is waiting to go to the client.');
    return ui_card(new PartProps(class: 'px-6', attrs: ['id' => 'today-section-reviews', 'aria-labelledby' => 'h-today-section-reviews',
        'data-on-interval__duration.60s' => act_raw('get', url('/today/reviews'))]), '<div class="flex flex-col gap-4">' . $body . '</div>');
}
