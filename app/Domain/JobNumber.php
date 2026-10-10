<?php
declare(strict_types=1);

namespace App\Domain;

/** Job numbers are PREFIX-NNN (brand prefix, counter from job_counters, at least 3 digits). */
final class JobNumber
{
    public static function format(string $prefix, int $n): string
    {
        return $prefix . '-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /** The number part of a job number with this prefix, or null. */
    public static function numberOf(string $prefix, string $jobNumber): ?int
    {
        if (!str_starts_with($jobNumber, $prefix . '-')) {
            return null;
        }
        $rest = substr($jobNumber, strlen($prefix) + 1);
        return preg_match('/^\d+$/', $rest) === 1 ? (int) $rest : null;
    }

    /** First counter value for a prefix: highest existing number + 1. @param list<string> $existing */
    public static function seed(string $prefix, array $existing): int
    {
        $max = 0;
        foreach ($existing as $jn) {
            $n = self::numberOf($prefix, $jn);
            if ($n !== null && $n > $max) {
                $max = $n;
            }
        }
        return $max + 1;
    }
}
