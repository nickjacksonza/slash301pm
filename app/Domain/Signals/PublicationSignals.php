<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\ChecklistItem;
use App\Domain\PublicationChecklist;

/**
 * The signals of one post card, under pub_<publication id>:
 * {rv, cl: {copy, copy_note, image, image_note, ...}, scheduled_at, live_url,
 * promoted, promoted_note, reason}. Untrusted input; every read is typed.
 */
final class PublicationSignals
{
    public function __construct(
        public readonly int $rowVersion,
        public readonly PublicationChecklist $checklist,
        public readonly string $scheduledAt,
        public readonly string $liveUrl,
        public readonly bool $promoted,
        public readonly string $promotedNote,
        public readonly string $reason,
        public readonly string $noteProblem,
    ) {}

    /** The signal root of a publication's card: pub_<id> (ids are 32 hex characters). */
    public static function root(string $publicationId): string
    {
        return 'pub_' . $publicationId;
    }

    public static function fromSignals(array $s, string $publicationId): self
    {
        $p = SignalInput::obj($s, self::root($publicationId));
        $cl = SignalInput::obj($p, 'cl');
        $c = PublicationChecklist::empty();
        $problem = '';
        foreach (ChecklistItem::cases() as $item) {
            $note = trim(SignalInput::str($cl, $item->value . '_note'));
            $why = PublicationChecklist::noteProblem($note);
            if ($why !== '' && $problem === '') {
                $problem = $item->label() . ': ' . $why;
            }
            $c = $c->with($item, SignalInput::bool($cl, $item->value), $why === '' ? $note : '');
        }
        $promotedNote = trim(SignalInput::str($p, 'promoted_note'));
        $why = PublicationChecklist::noteProblem($promotedNote);
        if ($why !== '' && $problem === '') {
            $problem = 'Promoted note: ' . $why;
        }
        return new self(
            SignalInput::int($p, 'rv', -1), $c, trim(SignalInput::str($p, 'scheduled_at')), trim(SignalInput::str($p, 'live_url')),
            SignalInput::bool($p, 'promoted'), $why === '' ? $promotedNote : '', trim(SignalInput::str($p, 'reason')), $problem,
        );
    }
}
