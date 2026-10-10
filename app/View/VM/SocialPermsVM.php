<?php
declare(strict_types=1);

namespace App\View\VM;

/** What the viewer may do on one job's posts (SocialPolicy, decided by the handler). */
final class SocialPermsVM
{
    public function __construct(
        public readonly bool $checklist,
        public readonly bool $ready,
        public readonly bool $schedule,
        public readonly bool $live,
        public readonly bool $liveLink,
        public readonly bool $promote,
        public readonly bool $archive,
        public readonly bool $reopen,
    ) {}

    public function any(): bool
    {
        return $this->checklist || $this->ready || $this->schedule || $this->live || $this->liveLink || $this->promote || $this->archive || $this->reopen;
    }
}
