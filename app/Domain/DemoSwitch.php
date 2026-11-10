<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Who may use the demo user switcher in the top bar (POST /account/switch/{userId}).
 *
 * The rule: demo mode must be on (data/.demo_mode exists) AND the session must
 * have been started by the demo sign-in picker (it carries demo_user_id and no
 * real user_id). A real password login, even a COO or ECD one, never gets the
 * switcher, so it cannot be used to impersonate people. Turning demo mode off
 * removes the menu and makes the route answer 403 at once.
 * Pure: the caller passes the facts in. Go: func CanSwitchDemoUser(demoMode, demoSession bool) Decision.
 */
final class DemoSwitch
{
    public static function can(bool $demoMode, bool $demoSession): Decision
    {
        if (!$demoMode) {
            return Decision::deny('Demo mode is not enabled.');
        }
        if (!$demoSession) {
            return Decision::deny('Switching users is only available after signing in from the demo picker.');
        }
        return Decision::allow();
    }
}
