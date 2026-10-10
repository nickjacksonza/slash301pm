<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** One checklist item of a publication: ticked or not, plus a note. */
final class ChecklistEntry
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $note,
    ) {}
}
