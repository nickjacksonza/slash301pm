<?php
declare(strict_types=1);

namespace App\View\VM;

/** One asset on the assets page and the job sheet. */
final class AssetRowVM
{
    /** @param list<PostRowVM> $posts */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $status,
        public readonly string $assigneeName,
        public readonly string $dueText,
        public readonly array $posts,
    ) {}
}
