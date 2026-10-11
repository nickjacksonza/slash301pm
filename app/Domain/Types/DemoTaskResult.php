<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** What "Add demo role tasks" did. Go: type DemoTaskResult struct. */
final class DemoTaskResult
{
    /**
     * @param list<string> $jobNumbers jobs that got the three tasks
     * @param list<string> $skipped job numbers that already had them
     * @param list<string> $missingRoles roles with no active user (their tasks stay unassigned)
     */
    public function __construct(
        public readonly array $jobNumbers,
        public readonly array $skipped,
        public readonly array $missingRoles,
    ) {}
}
