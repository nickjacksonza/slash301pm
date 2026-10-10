<?php
declare(strict_types=1);

use App\Domain\JobAction;
use App\Domain\JobReviewStatus;
use App\Domain\MediaKind;
use App\Domain\PartState;
use App\Domain\ReviewDecision;
use App\Domain\ReviewEvent;
use App\Domain\ReviewNotify;
use App\Domain\ReviewPart;
use App\Domain\ReviewPolicy;
use App\Domain\ReviewRules;
use App\Domain\Role;
use App\Domain\Stage;
use App\Domain\Transitions;
use App\Domain\Types\AssetReview;
use App\Domain\Types\Assignment;
use App\Domain\Types\JobReview;
use App\Domain\Types\SubmissionInput;
use App\Domain\Types\TransitionRequest;
use App\Domain\WaitingOn;

require_once dirname(__DIR__, 2) . '/support/brief_fx.php';

/** An asset in review; $o overrides by constructor name. */
function rt_asset(array $o = []): AssetReview
{
    $d = [
        'assetId' => 'a1', 'jobId' => 'j1', 'assetName' => 'MERC-004_Static_01', 'assetType' => 'image', 'templateId' => 'social-static', 'assetStatus' => 'Inbox',
        'assigneeId' => null, 'assigneeRole' => null, 'assigneeName' => '', 'lineLabel' => 'Social Post (Static)', 'channel' => 'Instagram', 'sortOrder' => 0,
        'submissionId' => null, 'round' => 0, 'copyText' => '', 'mediaUrl' => '', 'mediaKind' => MediaKind::Link, 'hashtags' => '', 'linkUrl' => '', 'note' => '',
        'copyState' => PartState::Missing, 'mediaState' => PartState::Missing, 'submittedBy' => null, 'submittedAt' => null, 'reviewRequestedAt' => null,
        'rejectedInRound' => false, 'rowVersion' => 0, 'copyMakerId' => null, 'copyMakerName' => '', 'mediaMakerId' => null, 'mediaMakerName' => '',
    ];
    return new AssetReview(...array_merge($d, $o));
}

/** A handed-in asset at round $round with both parts in the given states. */
function rt_handed(PartState $copy, PartState $media, int $round = 1, array $o = []): AssetReview
{
    return rt_asset($o + ['submissionId' => 's1', 'round' => $round, 'copyText' => 'Copy', 'mediaUrl' => 'https://cdn.example.com/a.png', 'mediaKind' => MediaKind::Image,
        'copyState' => $copy, 'mediaState' => $media, 'rowVersion' => 2, 'copyMakerId' => 'copy1', 'mediaMakerId' => 'des1']);
}

function rt_in(string $copy = 'Copy', string $media = 'https://cdn.example.com/a.png', string $tags = '', string $link = '', string $note = ''): SubmissionInput
{
    return new SubmissionInput($copy, $media, $tags, $link, $note, 0);
}

function rt_jr(array $o = []): JobReview
{
    $d = ['jobId' => 'j1', 'jobRound' => 1, 'allDoneAt' => null, 'ecdRequestedBy' => null, 'ecdRequestedAt' => null, 'cdApprovedBy' => null, 'cdApprovedByName' => '',
        'cdApprovedAt' => null, 'ecdApprovedBy' => null, 'ecdApprovedByName' => '', 'ecdApprovedAt' => null, 'clientReadyBy' => null, 'clientReadyAt' => null,
        'sentToClientBy' => null, 'sentToClientAt' => null, 'assigneeId' => null, 'assigneeName' => '', 'assigneeRole' => null, 'rowVersion' => 1];
    return new JobReview(...array_merge($d, $o));
}

const RT_BOTH = [ReviewPart::Copy, ReviewPart::Media];

return [
    'review: required parts and media kinds' => function (): void {
        t_eq([ReviewPart::Copy], ReviewRules::requiredParts('copy'));
        foreach (['image', 'video', 'document', 'audio', ''] as $type) {
            t_eq(RT_BOTH, ReviewRules::requiredParts($type), $type);
        }
        $cases = [
            ['https://cdn.example.com/x/post.PNG?v=2', MediaKind::Image, true],
            ['http://cdn.example.com/post.jpg', MediaKind::Image, false],
            ['https://cdn.example.com/clip.mp4', MediaKind::Video, false],
            ['https://www.youtube.com/watch?v=1', MediaKind::Video, false],
            ['https://drive.example.com/folder/abc', MediaKind::Link, false],
            ['\\\\server\\Clients\\Meridian', MediaKind::Link, false],
            ['javascript:alert(1)//.png', MediaKind::Link, false],
            ['', MediaKind::Link, false],
        ];
        foreach ($cases as [$url, $kind, $inline]) {
            t_eq($kind, MediaKind::fromUrl($url), $url);
            t_eq($inline, MediaKind::showsInline($url, $kind), "inline $url");
        }
    },

    'review: makers from slots, assignees and who handed in' => function (): void {
        $team = bf_team(['Copywriter' => 'copy1', 'Designer' => 'des1', 'CD' => 'cd1']);
        // [asset overrides, part, derived maker, maker]
        $cases = [
            [[], ReviewPart::Copy, 'copy1', 'copy1'],
            [[], ReviewPart::Media, 'des1', 'des1'],
            [['assigneeId' => 'des2', 'assigneeRole' => Role::Designer], ReviewPart::Media, 'des2', 'des2'],
            [['assigneeId' => 'des2', 'assigneeRole' => Role::Designer], ReviewPart::Copy, 'copy1', 'copy1'],
            [['assigneeId' => 'copy2', 'assigneeRole' => Role::Copywriter, 'assetType' => 'copy'], ReviewPart::Copy, 'copy2', 'copy2'],
            [['assigneeId' => 'copy2', 'assigneeRole' => Role::Copywriter, 'assetType' => 'copy'], ReviewPart::Media, null, null],
            [['assigneeId' => 'dev1', 'assigneeRole' => Role::Developer], ReviewPart::Copy, 'dev1', 'dev1'],
            [['assigneeId' => 'dev1', 'assigneeRole' => Role::Developer], ReviewPart::Media, 'dev1', 'dev1'],
            [['copyMakerId' => 'cd1'], ReviewPart::Copy, 'copy1', 'cd1'],
        ];
        foreach ($cases as $i => [$o, $part, $derived, $maker]) {
            $a = rt_asset($o);
            t_eq($derived, ReviewRules::derivedMaker($a, $part, $team), "derived $i");
            t_eq($maker, ReviewRules::maker($a, $part, $team), "maker $i");
        }
        $empty = bf_team([]);
        t_eq(null, ReviewRules::derivedMaker(rt_asset(), ReviewPart::Copy, $empty), 'no slot, no assignee');
        t_true(ReviewRules::isMakerOf('copy1', rt_asset(), ReviewPart::Copy, $team));
        t_true(!ReviewRules::isMakerOf('copy1', rt_asset(), ReviewPart::Media, $team), 'copywriter never makes media');
        t_true(!ReviewRules::isMakerOf('des1', rt_asset(), ReviewPart::Copy, $team));
        t_true(ReviewRules::isMakerOf('x9', rt_asset(['mediaMakerId' => 'x9']), ReviewPart::Media, $team), 'who handed it in');
    },

    'review: hand-in validation refuses bad links and control characters' => function (): void {
        $cases = [
            [rt_in(), RT_BOTH, ''],
            [rt_in('Copy', '\\\\fileserver\\Clients\\a.png'), RT_BOTH, ''],
            [rt_in('Copy', '/Volumes/Clients/a.png'), RT_BOTH, ''],
            [rt_in('Copy', 'javascript:alert(1)'), RT_BOTH, 'media_url'],
            [rt_in('Copy', 'data:image/png;base64,AAAA'), RT_BOTH, 'media_url'],
            [rt_in('Copy', 'https://x.com/a.png', '', 'http://example.com'), RT_BOTH, 'link_url'],
            [rt_in('Copy', 'https://x.com/a.png', '', 'javascript:alert(1)'), RT_BOTH, 'link_url'],
            [rt_in('Copy', 'https://x.com/a.png', '', 'https://example.com/p?utm=1'), RT_BOTH, ''],
            [rt_in("Line one\nLine two"), RT_BOTH, ''],
            [rt_in("Bad \x07 bell"), RT_BOTH, 'copy_text'],
            [rt_in(str_repeat('x', ReviewRules::MAX_COPY + 1)), RT_BOTH, 'copy_text'],
            [rt_in('Copy', 'https://x.com/a.png', str_repeat('#a', 600)), RT_BOTH, 'hashtags'],
            // a Designer's form never validates (or writes) the copy fields
            [rt_in("Bad \x07", 'https://x.com/a.png', '', 'javascript:x'), [ReviewPart::Media], ''],
            [rt_in('', 'javascript:x'), [ReviewPart::Copy], ''],
        ];
        foreach ($cases as $i => [$in, $parts, $field]) {
            $e = ReviewRules::validateInput($in, $parts);
            t_eq($field === '' ? [] : [$field], array_keys($e->errors), "case $i: " . $e->first());
        }
    },

    'review: rounds open only when a part rejected in this round is handed in again' => function (): void {
        $S = PartState::Submitted;
        $A = PartState::Approved;
        $R = PartState::ChangesRequested;
        $M = PartState::Missing;
        // [current, input, editable, request review, expect round, new round, copy state, media state, error field]
        $cases = [
            'first hand-in' => [rt_asset(), rt_in(), RT_BOTH, false, 1, true, $S, $S, ''],
            'copy only first' => [rt_asset(), rt_in('Copy', ''), [ReviewPart::Copy], false, 1, true, $S, $M, ''],
            'edit before review' => [rt_handed($S, $S), rt_in('Copy 2'), RT_BOTH, false, 1, false, $S, $S, ''],
            'copy rejected, copy again' => [rt_handed($R, $A, 1, ['rejectedInRound' => true]), rt_in('Copy 2'), [ReviewPart::Copy], false, 2, true, $S, $A, ''],
            'both rejected, second maker in the new round' => [rt_handed($S, $R, 2, ['rejectedInRound' => false]), rt_in('Copy', 'https://x.com/b.png'), [ReviewPart::Media], false, 2, false, $S, $S, ''],
            'approved part frozen' => [rt_handed($A, $S), rt_in('Changed'), RT_BOTH, false, 1, false, $A, $S, 'copy_text'],
            'approved media frozen' => [rt_handed($S, $A), rt_in('Copy', 'https://x.com/new.png'), [ReviewPart::Media], false, 1, false, $S, $A, 'media_url'],
            'nothing changed' => [rt_handed($S, $S), rt_in(), RT_BOTH, false, 1, false, $S, $S, 'submission'],
            'note only' => [rt_handed($S, $S), rt_in('Copy', 'https://cdn.example.com/a.png', '', '', 'FYI'), RT_BOTH, false, 1, false, $S, $S, ''],
            'request without media' => [rt_asset(), rt_in('Copy', ''), [ReviewPart::Copy], true, 1, true, $S, $M, 'submission'],
            'request with a rejected part left' => [rt_handed($R, $S, 1, ['rejectedInRound' => true]), rt_in(), RT_BOTH, true, 1, false, $R, $S, 'submission'],
            'request nothing new' => [rt_handed($A, $A), rt_in(), RT_BOTH, true, 1, false, $A, $A, 'submission'],
            'request after resubmit' => [rt_handed($R, $A, 3, ['rejectedInRound' => true]), rt_in('Copy v4'), [ReviewPart::Copy], true, 4, true, $S, $A, ''],
            'copy deliverable needs no media' => [rt_asset(['assetType' => 'copy']), rt_in('Words', ''), RT_BOTH, true, 1, true, $S, $M, ''],
            'emptied copy is missing' => [rt_handed($S, $S), rt_in(''), RT_BOTH, false, 1, false, $M, $S, ''],
            'designer cannot write copy' => [rt_handed($S, $S), rt_in('Hacked'), [ReviewPart::Media], false, 1, false, $S, $S, 'submission'],
        ];
        foreach ($cases as $name => [$cur, $in, $editable, $req, $round, $new, $copy, $media, $field]) {
            $p = ReviewRules::planSubmission($cur, $in, $editable, $req);
            t_eq($field === '' ? [] : [$field], array_keys($p->errors->errors), "$name: " . $p->errors->first());
            if ($field !== '') {
                continue;
            }
            t_eq($round, $p->round, "$name round");
            t_eq($new, $p->newRound, "$name new round");
            t_eq($copy, $p->copyState, "$name copy");
            t_eq($media, $p->mediaState, "$name media");
        }
        // the designer's form keeps the stored copy whatever is sent
        $p = ReviewRules::planSubmission(rt_handed(PartState::Submitted, PartState::Submitted), rt_in('Hacked', 'https://x.com/z.png'), [ReviewPart::Media], false);
        t_eq('Copy', $p->copyText);
        t_eq('https://x.com/z.png', $p->mediaUrl);
        t_eq(MediaKind::Image, $p->mediaKind);
        t_eq([ReviewPart::Media], $p->changed);
    },

    'review: decisions need content, feedback when asked, and something to change' => function (): void {
        $S = PartState::Submitted;
        $A = PartState::Approved;
        $cases = [
            [rt_handed($S, $S), 'both', ReviewDecision::Approved, '', ''],
            [rt_handed($S, $S), 'copy', ReviewDecision::RejectedWithFeedback, '', 'feedback'],
            [rt_handed($S, $S), 'copy', ReviewDecision::RejectedWithFeedback, 'Shorter please', ''],
            [rt_handed($S, $S), 'media', ReviewDecision::Rejected, '', ''],
            [rt_handed($A, $A), 'both', ReviewDecision::Approved, '', 'part'],
            [rt_handed($A, $S), 'both', ReviewDecision::Approved, '', ''],
            [rt_asset(), 'both', ReviewDecision::Approved, '', 'part'],
            [rt_handed($S, PartState::Missing), 'media', ReviewDecision::Rejected, '', 'part'],
            [rt_handed($S, $S), 'copy', ReviewDecision::RejectedWithFeedback, str_repeat('x', ReviewRules::MAX_FEEDBACK + 1), 'feedback'],
            [rt_handed($S, $S), 'copy', ReviewDecision::RejectedWithFeedback, "bad \x00", 'feedback'],
            [rt_asset(['assetType' => 'copy', 'round' => 1, 'copyState' => $S]), 'media', ReviewDecision::Approved, '', 'part'],
        ];
        foreach ($cases as $i => [$a, $choice, $d, $fb, $field]) {
            $e = ReviewRules::reviewProblems($a, ReviewRules::partsFor($choice, $a), $d, $fb);
            t_eq($field === '' ? [] : [$field], array_keys($e->errors), "case $i: " . $e->first());
        }
        t_eq(RT_BOTH, ReviewRules::partsFor('both', rt_asset()));
        t_eq([], ReviewRules::partsFor('evil', rt_asset()));
    },

    'review: job aggregate' => function (): void {
        $S = PartState::Submitted;
        $A = PartState::Approved;
        $R = PartState::Rejected;
        $cases = [
            [[], JobReviewStatus::Empty],
            [[rt_asset()], JobReviewStatus::InProgress],
            [[rt_handed($S, $S), rt_asset(['assetId' => 'a2'])], JobReviewStatus::InProgress],
            [[rt_handed($S, $S), rt_handed($A, $A, 1, ['assetId' => 'a2'])], JobReviewStatus::WaitingReview],
            [[rt_handed($A, $A), rt_handed($A, $R, 1, ['assetId' => 'a2'])], JobReviewStatus::NeedsChanges],
            [[rt_handed($A, $A), rt_handed($A, $A, 1, ['assetId' => 'a2'])], JobReviewStatus::AllApproved],
            [[rt_handed($A, $A), rt_asset(['assetId' => 'a2', 'assetStatus' => 'Cancelled'])], JobReviewStatus::AllApproved],
            [[rt_asset(['assetType' => 'copy', 'round' => 1, 'copyState' => $A])], JobReviewStatus::AllApproved],
        ];
        foreach ($cases as $i => [$assets, $want]) {
            t_eq($want, ReviewRules::aggregate($assets), "case $i");
        }
    },

    'review: stage moves follow the stage machine' => function (): void {
        $cases = [
            [Stage::Briefed, ReviewEvent::Submitted, [JobAction::Start], Stage::InProgress],
            [Stage::InProgress, ReviewEvent::Submitted, [], Stage::InProgress],
            [Stage::Briefed, ReviewEvent::ReviewRequested, [JobAction::Start, JobAction::Submit], Stage::InReview],
            [Stage::InProgress, ReviewEvent::ReviewRequested, [JobAction::Submit], Stage::InReview],
            [Stage::InReview, ReviewEvent::ReviewRequested, [], Stage::InReview],
            [Stage::InReview, ReviewEvent::Rejected, [JobAction::SendBack], Stage::InProgress],
            [Stage::ApprovedInternal, ReviewEvent::Rejected, [JobAction::SendBack], Stage::InProgress],
            [Stage::InProgress, ReviewEvent::Rejected, [], Stage::InProgress],
            [Stage::InReview, ReviewEvent::EcdApproved, [JobAction::ApproveInternal], Stage::ApprovedInternal],
            [Stage::InProgress, ReviewEvent::EcdApproved, [JobAction::Submit, JobAction::ApproveInternal], Stage::ApprovedInternal],
            // client approval is never set here (client portal next); paused and closed jobs never move
            [Stage::ApprovedInternal, ReviewEvent::EcdApproved, [], Stage::ApprovedInternal],
            [Stage::Waiting, ReviewEvent::ReviewRequested, [], Stage::Waiting],
            [Stage::OnHold, ReviewEvent::Rejected, [], Stage::OnHold],
            [Stage::ApprovedClient, ReviewEvent::Rejected, [], Stage::ApprovedClient],
            [Stage::Done, ReviewEvent::Submitted, [], Stage::Done],
        ];
        foreach ($cases as $i => [$from, $event, $moves, $end]) {
            t_eq($moves, ReviewRules::stageMoves($from, $event), "case $i");
            $at = $from;
            foreach ($moves as $a) {
                $o = Transitions::plan($at, null, new TransitionRequest($a, null, $a === JobAction::SendBack ? 'Rejected: the copy' : ''), false);
                t_true($o->ok() && $o->to !== null, "case $i: $at->value " . $a->value . ' ' . $o->errors->first());
                $at = $o->to;
            }
            t_eq($end, $at, "case $i end");
        }
        // send back still needs a reason
        t_true(!Transitions::plan(Stage::ApprovedInternal, null, new TransitionRequest(JobAction::SendBack, WaitingOn::Client, ''), false)->ok());
    },

    'review: round limit warns from the round after the limit, once per new round' => function (): void {
        $cases = [[1, true, 3, false], [3, true, 3, false], [4, true, 3, true], [4, false, 3, false], [5, true, 3, true], [2, true, 1, true], [9, true, 0, false]];
        foreach ($cases as [$round, $new, $limit, $want]) {
            t_eq($want, ReviewRules::passesRoundLimit($round, $new, $limit), "round $round new " . ($new ? 'y' : 'n') . " limit $limit");
        }
        t_true(!ReviewRules::rejectionClears(rt_jr()));
        t_true(ReviewRules::rejectionClears(rt_jr(['ecdApprovedAt' => '2026-10-09 10:00:00'])));
        t_true(ReviewRules::rejectionClears(rt_jr(['cdApprovedAt' => '2026-10-09 10:00:00'])));
    },

    'review: recipients per event' => function (): void {
        $team = bf_team(['AM' => 'am1', 'Traffic' => 'tr1', 'CD' => 'cd1', 'Copywriter' => 'copy1', 'Designer' => 'des1', 'Client' => 'cli1']);
        $ecds = ['ecd1'];
        $jr = rt_jr();
        $reassigned = rt_jr(['assigneeId' => 'cd2', 'assigneeRole' => Role::CD]);
        $toClient = rt_jr(['assigneeId' => 'cli1', 'assigneeRole' => Role::Client]);
        // [event, team, job review, extra, actor, expected]
        $cases = [
            [ReviewNotify::REVIEW_REQUESTED, $team, $jr, [], 'copy1', ['cd1']],
            [ReviewNotify::REVIEW_REQUESTED, $team, $reassigned, [], 'copy1', ['cd2']],
            [ReviewNotify::REVIEW_REQUESTED, $team, $toClient, [], 'copy1', ['cd1']],
            [ReviewNotify::REVIEW_REQUESTED, bf_team(['Copywriter' => 'copy1']), $jr, [], 'copy1', ['ecd1']],
            [ReviewNotify::REVIEW_REQUESTED, $team, $jr, [], 'cd1', []],
            [ReviewNotify::JOB_REVIEW_REQUESTED, $team, $jr, [], 'cd1', ['ecd1']],
            [ReviewNotify::JOB_CD_APPROVED, $team, $jr, [], 'cd1', ['ecd1']],
            [ReviewNotify::JOB_ECD_APPROVED, $team, $jr, [], 'ecd1', ['am1', 'cd1']],
            [ReviewNotify::PART_REJECTED, $team, $jr, ['copy1'], 'cd1', ['copy1']],
            [ReviewNotify::PART_REJECTED, $team, $jr, ['copy1', 'des1', 'copy1'], 'cd1', ['copy1', 'des1']],
            [ReviewNotify::PART_REJECTED, $team, $jr, ['cd1'], 'cd1', []],
            [ReviewNotify::ROUND_LIMIT_WARNING, $team, $jr, [], 'copy1', ['am1']],
            [ReviewNotify::ROUND_LIMIT_WARNING, bf_team([]), $jr, [], 'copy1', ['creator1']],
            [ReviewNotify::JOB_READY_FOR_CLIENT, $team, $jr, [], 'cd1', ['am1', 'tr1']],
            [ReviewNotify::JOB_SENT_TO_CLIENT, $team, $jr, [], 'am1', ['tr1']],
            [ReviewNotify::REVIEW_REASSIGNED, $team, $jr, ['cd2'], 'cd1', ['cd2']],
            [ReviewNotify::REVIEW_REASSIGNED, $team, $jr, ['cli1'], 'cd1', []],
            [ReviewNotify::PART_APPROVED, $team, $jr, [], 'cd1', []],
            [ReviewNotify::ASSET_SUBMITTED, $team, $jr, [], 'copy1', []],
        ];
        foreach ($cases as $i => [$event, $t, $j, $extra, $actor, $want]) {
            t_eq($want, ReviewNotify::recipients($event, $t, $j, $ecds, 'creator1', $extra, $actor), "case $i $event");
        }
        foreach (ReviewNotify::verbs() as $v) {
            t_true(ReviewNotify::phrase($v, ['asset_name' => 'X', 'parts' => ['copy'], 'round' => 2]) !== null, $v);
        }
        t_eq('asked for changes to the copy of X', ReviewNotify::phrase(ReviewNotify::PART_REJECTED, ['asset_name' => 'X', 'parts' => ['copy'], 'decision' => 'rejected_with_feedback']));
    },

    'review policy: who reviews, approves, reassigns, gets it ready and sends it' => function (): void {
        $cdSlot = [new Assignment(Role::CD, 'cd1', 'CD', Role::CD), new Assignment(Role::AM, 'am1', 'AM', Role::AM), new Assignment(Role::Copywriter, 'copy1', 'C', Role::Copywriter)];
        $job = static fn (string $stage = 'in_review'): App\Domain\Types\JobAccess => bf_access($stage, ['assignments' => $cdSlot, 'creatorId' => 'am1']);
        $cd = bf_user(Role::CD, 'cd1');
        $cd2 = bf_user(Role::CD, 'cd2');
        $ecd = bf_user(Role::ECD, 'ecd1');
        $all = JobReviewStatus::AllApproved;
        $both = rt_jr(['cdApprovedAt' => 'x', 'ecdApprovedAt' => 'y']);
        $ready = rt_jr(['cdApprovedAt' => 'x', 'ecdApprovedAt' => 'y', 'clientReadyAt' => 'z']);
        // [decision fn, expected]
        $cases = [
            'CD slot reviews' => [ReviewPolicy::canReview($cd, $job(), rt_jr()), true],
            'other CD cannot' => [ReviewPolicy::canReview($cd2, $job(), rt_jr()), false],
            'reassigned CD can' => [ReviewPolicy::canReview($cd2, $job(), rt_jr(['assigneeId' => 'cd2', 'assigneeRole' => Role::CD])), true],
            'ECD reviews any job' => [ReviewPolicy::canReview($ecd, $job(), rt_jr()), true],
            'Designer never reviews' => [ReviewPolicy::canReview(bf_user(Role::Designer, 'des1'), $job(), rt_jr()), false],
            'AM never reviews' => [ReviewPolicy::canReview(bf_user(Role::AM, 'am1'), $job(), rt_jr()), false],
            'review at any open stage' => [ReviewPolicy::canReview($cd, $job('approved_client'), rt_jr()), true],
            'not on a closed job' => [ReviewPolicy::canReview($cd, $job('done'), rt_jr()), false],
            'not before the send' => [ReviewPolicy::canReview($cd, bf_access('draft', ['assignments' => $cdSlot]), rt_jr()), false],
            'CD not own part' => [ReviewPolicy::canReview($cd, $job(), rt_jr(), rt_handed(PartState::Submitted, PartState::Submitted, 1, ['copyMakerId' => 'cd1']), ReviewPart::Copy), false],
            'CD own part, nobody else' => [ReviewPolicy::canReview($cd, $job(), rt_jr(), rt_handed(PartState::Submitted, PartState::Submitted, 1, ['copyMakerId' => 'cd1']), ReviewPart::Copy, false), true],
            'CD other part' => [ReviewPolicy::canReview($cd, $job(), rt_jr(), rt_handed(PartState::Submitted, PartState::Submitted, 1, ['copyMakerId' => 'cd1']), ReviewPart::Media), true],
            'approve job when all approved' => [ReviewPolicy::canApproveJob($cd, $job(), rt_jr(), $all), true],
            'approve job too early' => [ReviewPolicy::canApproveJob($cd, $job(), rt_jr(), JobReviewStatus::WaitingReview), false],
            'approve job twice' => [ReviewPolicy::canApproveJob($cd, $job(), rt_jr(['cdApprovedAt' => 'x']), $all), false],
            'CD asks ECD early' => [ReviewPolicy::canRequestEcdReview($cd, $job('in_progress'), rt_jr()), true],
            'Copywriter cannot ask ECD' => [ReviewPolicy::canRequestEcdReview(bf_user(Role::Copywriter, 'copy1'), $job(), rt_jr()), false],
            'ECD approves after CD' => [ReviewPolicy::canApproveAsEcd($ecd, $job(), rt_jr(['cdApprovedAt' => 'x']), $all), true],
            'ECD not before CD' => [ReviewPolicy::canApproveAsEcd($ecd, $job(), rt_jr(), $all), false],
            'CD is not the ECD' => [ReviewPolicy::canApproveAsEcd($cd, $job(), rt_jr(['cdApprovedAt' => 'x']), $all), false],
            'COO covers the ECD' => [ReviewPolicy::canApproveAsEcd(bf_user(Role::COO), $job(), rt_jr(['cdApprovedAt' => 'x']), $all), true],
            'CD reassigns' => [ReviewPolicy::canReassign($cd, $job(), rt_jr()), true],
            'AM cannot reassign' => [ReviewPolicy::canReassign(bf_user(Role::AM, 'am1'), $job(), rt_jr()), false],
            'AM ready' => [ReviewPolicy::canMarkReady(bf_user(Role::AM, 'am1'), $job('approved_internal'), $both, $all), true],
            'other AM not' => [ReviewPolicy::canMarkReady(bf_user(Role::AM, 'am2'), $job('approved_internal'), $both, $all), false],
            'Traffic ready' => [ReviewPolicy::canMarkReady(bf_user(Role::Traffic, 'tr9'), $job('approved_internal'), $both, $all), true],
            'PM not (owner list)' => [ReviewPolicy::canMarkReady(bf_user(Role::PM, 'pm1'), bf_access('approved_internal', ['assignments' => [new Assignment(Role::PM, 'pm1', 'P', Role::PM)]]), $both, $all), false],
            'ready needs ECD' => [ReviewPolicy::canMarkReady($cd, $job('approved_internal'), rt_jr(['cdApprovedAt' => 'x']), $all), false],
            'ready needs the stage' => [ReviewPolicy::canMarkReady($cd, $job('in_review'), $both, $all), false],
            'send after ready' => [ReviewPolicy::canSendToClient(bf_user(Role::Producer, 'pr1'), bf_access('approved_internal', ['assignments' => [new Assignment(Role::Producer, 'pr1', 'P', Role::Producer)]]), $ready, $all), true],
            'send before ready' => [ReviewPolicy::canSendToClient($cd, $job('approved_internal'), $both, $all), false],
            'send twice' => [ReviewPolicy::canSendToClient($cd, $job('approved_internal'), rt_jr(['cdApprovedAt' => 'x', 'ecdApprovedAt' => 'y', 'clientReadyAt' => 'z', 'sentToClientAt' => 'w']), $all), false],
            'Designer cannot send' => [ReviewPolicy::canSendToClient(bf_user(Role::Designer, 'des1'), $job('approved_internal'), $ready, $all), false],
            'Copywriter sees the review of their job' => [ReviewPolicy::canView(bf_user(Role::Copywriter, 'copy1'), $job()), true],
            'Copywriter not another job' => [ReviewPolicy::canView(bf_user(Role::Copywriter, 'copy9'), $job()), false],
            'Client never sees it' => [ReviewPolicy::canView(bf_user(Role::Client, 'cli1', 'brand1'), $job()), false],
        ];
        foreach ($cases as $name => [$d, $want]) {
            t_eq($want, $d->allowed, "$name: " . $d->reason);
        }
        $cli = static fn (string $brand): App\Domain\Types\User => bf_user(Role::Client, 'cli1', $brand);
        // [target, job review, problem substring ('' = allowed)]
        $targets = [
            [$cd2, rt_jr(), ''],
            [$ecd, rt_jr(), ''],
            [$cli('brand1'), rt_jr(), 'after the ECD'],
            [$cli('brand1'), rt_jr(['ecdApprovedAt' => 'y']), ''],
            [$cli('brand9'), rt_jr(['ecdApprovedAt' => 'y']), 'brand'],
            [bf_user(Role::Designer, 'des1'), rt_jr(), 'another CD'],
            [bf_user(Role::AM, 'am1'), rt_jr(['ecdApprovedAt' => 'y']), 'another CD'],
            [$cd, rt_jr(), 'someone else'],
            [$cd2, rt_jr(['assigneeId' => 'cd2']), 'already'],
        ];
        foreach ($targets as $i => [$t, $j, $want]) {
            $got = ReviewPolicy::reassignTargetProblem($t, $job(), $j, 'cd1');
            if ($want === '') {
                t_eq('', $got, "target $i");
            } else {
                t_contains($want, $got, "target $i");
            }
        }
        // makers: the Copywriter hands in copy only, the CD slot everything, QA nothing
        $team = bf_team(['CD' => 'cd1', 'Copywriter' => 'copy1', 'Designer' => 'des1']);
        t_eq([ReviewPart::Copy], ReviewPolicy::submitParts(bf_user(Role::Copywriter, 'copy1'), $job(), rt_asset(), $team));
        t_eq([ReviewPart::Media], ReviewPolicy::submitParts(bf_user(Role::Designer, 'des1'), $job(), rt_asset(), $team));
        t_eq(RT_BOTH, ReviewPolicy::submitParts($cd, $job(), rt_asset(), $team));
        t_eq([], ReviewPolicy::submitParts(bf_user(Role::QA, 'qa1'), $job(), rt_asset(['assigneeId' => 'qa1', 'assigneeRole' => Role::QA]), $team));
        t_eq([], ReviewPolicy::submitParts(bf_user(Role::Copywriter, 'copy1'), $job('cancelled'), rt_asset(), $team));
        t_true(!ReviewPolicy::canSubmit(bf_user(Role::Copywriter, 'copy2'), $job(), rt_asset(), $team)->allowed, 'not their post');
    },
];
