<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\Brief;
use App\Domain\Types\BriefLine;
use App\Domain\Types\ReadinessItem;
use App\Domain\Types\Team;

/**
 * What a brief needs before it can go to Traffic (send and every update):
 * title, campaign, due date on or after the brief date, at least one deliverable
 * with qty >= 1, Traffic assigned, creative direction. CD and creatives are
 * optional (Traffic chooses them).
 */
final class BriefRules
{
    /**
     * @param list<BriefLine> $lines
     * @return list<ReadinessItem>
     */
    public static function checklist(Brief $b, array $lines, Team $team): array
    {
        $items = [];
        $items[] = new ReadinessItem('title', 'Title', trim($b->title) !== '', 'Add a title.');
        $items[] = new ReadinessItem('campaign', 'Campaign', $b->campaignId !== null, 'Choose a campaign.');
        $dueOk = $b->dueDate !== null && ($b->briefDate === null || strcmp($b->dueDate, $b->briefDate) >= 0);
        $dueMsg = $b->dueDate === null ? 'Set a due date.' : 'The due date must be on or after the brief date.';
        $items[] = new ReadinessItem('due_date', 'Due date', $dueOk, $dueMsg);
        $hasLine = false;
        foreach ($lines as $l) {
            if ($l->qty >= 1) {
                $hasLine = true;
                break;
            }
        }
        $items[] = new ReadinessItem('deliverables', 'Deliverables', $hasLine, 'Add at least one deliverable.');
        $items[] = new ReadinessItem('creative_direction', 'Creative direction', trim($b->creativeDirection) !== '', 'Write the creative direction.');
        $items[] = new ReadinessItem('traffic', 'Traffic assigned', $team->userFor(Role::Traffic) !== null, 'Traffic is required. Assign a Traffic person.');
        return $items;
    }

    /** @param list<BriefLine> $lines */
    public static function readyToSend(Brief $b, array $lines, Team $team): ValidationErrors
    {
        $errors = new ValidationErrors();
        foreach (self::checklist($b, $lines, $team) as $item) {
            if (!$item->ok) {
                $errors = $errors->with($item->key, $item->message);
            }
        }
        return $errors;
    }
}
