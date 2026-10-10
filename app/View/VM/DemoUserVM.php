<?php
declare(strict_types=1);

namespace App\View\VM;

final class DemoUserVM
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $username,
    ) {}
}
