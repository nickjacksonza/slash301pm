<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Which "My day" a user gets (docs/roles.md "My day needs"). Owner: AM, PM,
 * Producer, COO, ECD (jobs they own). Traffic: briefs waiting for a team plus
 * their jobs' dates. Assigned: CD, makers and QA (jobs they are assigned to).
 * Chosen by Policy::myDayMode(). Go: type MyDayMode string.
 */
enum MyDayMode: string
{
    case Owner = 'owner';
    case Traffic = 'traffic';
    case Assigned = 'assigned';
}
