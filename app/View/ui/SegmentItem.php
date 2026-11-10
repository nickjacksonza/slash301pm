<?php
declare(strict_types=1);

namespace App\View\ui;

/**
 * One choice of partial_segmented(). href set renders a link (current marks it), otherwise a button.
 * activeExpr is a Datastar expression written by the developer (never user data) that says when the
 * item is the active one on the client; attrs are extra attributes (values escaped by the part).
 */
final readonly class SegmentItem
{
    /** @param array<string,string|int|bool> $attrs */
    public function __construct(
        public string $label,
        public ?int $count = null,
        public string $href = '',
        public bool $current = false,
        public string $activeExpr = '',
        public bool $disabled = false,
        public array $attrs = [],
    ) {}
}
