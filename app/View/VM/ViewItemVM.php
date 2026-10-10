<?php
declare(strict_types=1);

namespace App\View\VM;

/** One entry of the views menu. canManage: the viewer may rename, share, default or delete it. */
final class ViewItemVM
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $href,
        public readonly bool $builtin,
        public readonly bool $shared,
        public readonly bool $isDefault,
        public readonly bool $canManage,
        public readonly string $ownerName,
        public readonly string $manageUrl,
        public readonly bool $active,
    ) {}
}
