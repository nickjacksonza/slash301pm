<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\JobSortField;

/** One sort key of the grid: a whitelisted field and a direction. Token form: "due" or "-due". */
final class JobSort
{
    public function __construct(
        public readonly JobSortField $field,
        public readonly bool $desc = false,
    ) {}

    public static function fromToken(string $token): ?self
    {
        $t = trim($token);
        $desc = str_starts_with($t, '-');
        $f = JobSortField::tryFrom($desc ? substr($t, 1) : $t);
        return $f === null ? null : new self($f, $desc);
    }

    public function token(): string
    {
        return ($this->desc ? '-' : '') . $this->field->value;
    }
}
