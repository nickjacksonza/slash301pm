<?php
declare(strict_types=1);

namespace App\Domain;

use App\Domain\Types\SavedView;
use App\Domain\Types\User;

/**
 * Built-in views and the rules for saved ones. Built-ins live in code (ids
 * "builtin:<key>"), so they cannot be edited or deleted. The default view of
 * a screen is the user's own view marked default, else "My jobs" for the
 * account roles (AM, PM, Producer) and "All open" for everyone else.
 */
final class SavedViews
{
    public const MAX_NAME = 60;
    public const MAX_PER_USER = 50;

    /** @return list<SavedView> */
    public static function builtins(string $screen): array
    {
        $mk = static fn (string $key, string $name, array $state): SavedView => new SavedView(
            'builtin:' . $key, '', '', $screen, $name, JobQuery::fromState($state, $screen)->toJson(), true, false, 0, true,
        );
        $stages = $screen === JobQuery::SCREEN_BOARD ? 'board' : 'open';
        return [
            $mk('mine', 'My jobs', ['stages' => $stages, 'owner' => 'mine', 'sort' => 'due,job_number']),
            $mk('unowned', 'Unowned', ['stages' => $stages, 'owner' => 'unowned', 'sort' => 'job_number']),
            $mk('due_week', 'Due this week', ['stages' => $stages, 'due' => 'this_week', 'sort' => 'due,job_number']),
            $mk('all_open', 'All open', ['stages' => $stages, 'sort' => 'due,job_number']),
        ];
    }

    public static function builtin(string $id, string $screen): ?SavedView
    {
        foreach (self::builtins($screen) as $v) {
            if ($v->id === $id) {
                return $v;
            }
        }
        return null;
    }

    /** The built-in that applies when the user has no default of their own. */
    public static function fallbackId(User $u): string
    {
        return in_array($u->role, [Role::AM, Role::PM, Role::Producer], true) ? 'builtin:mine' : 'builtin:all_open';
    }

    /** A cleaned view name, or the reason it cannot be used. @return array{0:string,1:string} [name, error] */
    public static function cleanName(string $raw): array
    {
        $n = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $raw) ?? '') ?? '');
        if ($n === '') {
            return ['', 'Give the view a name.'];
        }
        if (mb_strlen($n) > self::MAX_NAME) {
            return ['', 'Keep the name under 60 characters.'];
        }
        return [$n, ''];
    }

    public static function validScreen(string $s): bool
    {
        return $s === JobQuery::SCREEN_JOBS || $s === JobQuery::SCREEN_BOARD;
    }
}
