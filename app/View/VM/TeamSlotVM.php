<?php
declare(strict_types=1);

namespace App\View\VM;

use App\View\ui\SelectOption;

/** One role slot in the brief's Team section. comboId is the DOM id of its combobox (signal root comboId with '-' -> '_'). */
final class TeamSlotVM
{
    /** @param list<SelectOption> $options people of that role ('' = not assigned) */
    public function __construct(
        public readonly string $role,
        public readonly string $routeRole,
        public readonly string $label,
        public readonly bool $required,
        public readonly string $holderId,
        public readonly string $holderName,
        public readonly array $options,
        public readonly bool $canEdit,
        public readonly string $comboId,
        public readonly string $hint,
    ) {}
}
