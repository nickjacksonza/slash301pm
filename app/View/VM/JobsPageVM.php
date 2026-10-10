<?php
declare(strict_types=1);

namespace App\View\VM;

/** GET /jobs and GET /jobs/board. Exactly one of body (grid) and board is set. state is the q.* signal object. */
final class JobsPageVM
{
    /** @param array<string,mixed> $state */
    public function __construct(
        public readonly string $screen,
        public readonly array $state,
        public readonly JobFiltersVM $filters,
        public readonly ViewsMenuVM $views,
        public readonly ?JobsBodyVM $body,
        public readonly ?BoardColumnsVM $board,
        public readonly string $gridUrl,
        public readonly string $boardUrl,
    ) {}
}
