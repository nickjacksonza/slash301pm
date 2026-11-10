<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\DemoSwitch;
use App\Domain\LoginRules;
use App\Domain\Signals\PasswordChangeSignals;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Http\Toast;

/** GET/POST /account/password (Datastar form, plain post fallback) and POST /account/switch/{userId} (demo user switcher). */
final class AccountHandlers
{
    public static function passwordForm(Request $r, Deps $d): Response
    {
        return Shell::page($r, $d, 'Change password', 'account', page_account_password($r->csrfToken(), ''));
    }

    public static function changePassword(Request $r, Deps $d): Response
    {
        $user = $r->user();
        if ($user === null) {
            return Response::navigate($r->isDatastar(), url('/login'));
        }
        $in = $r->isDatastar()
            ? PasswordChangeSignals::fromSignals($r->signals())
            : new PasswordChangeSignals($r->form('current'), $r->form('new'), $r->form('confirm'));

        $error = '';
        $hash = $d->users->passwordHash($user->id) ?? AuthHandlers::DUMMY_HASH;
        if (!password_verify($in->current, $hash)) {
            $error = 'Your current password is not right.';
        } else {
            $errors = LoginRules::validateNewPassword($in->new, $in->confirm);
            if (!$errors->isEmpty()) {
                $error = $errors->first();
            } elseif ($in->new === $in->current) {
                $error = 'The new password must be different from the current one.';
            }
        }
        if ($error !== '') {
            return $r->isDatastar()
                ? Response::events(Toast::error($error))
                : Shell::page($r, $d, 'Change password', 'account', page_account_password($r->csrfToken(), '', $error));
        }
        $d->users->setPassword($user->id, password_hash($in->new, PASSWORD_DEFAULT), $user->id, 'password_changed', $d->clock->now());
        $notice = 'Password changed. Use the new one next time you sign in (both apps).';
        // One patch: the panel comes back with empty signals and the notice.
        return $r->isDatastar()
            ? Response::events(PatchElements::html(partial_account_password_panel($r->csrfToken(), $notice, '')))
            : Shell::page($r, $d, 'Change password', 'account', page_account_password($r->csrfToken(), $notice));
    }

    /** True for a session started by the demo picker: demo_user_id set, no real user_id. */
    public static function isDemoSession(Session $s): bool
    {
        $demoId = $s->get('demo_user_id');
        $uid = $s->get('user_id');
        return is_string($demoId) && $demoId !== '' && !(is_string($uid) && $uid !== '');
    }

    /**
     * Demo user switcher. Allowed only while demo mode is on and the session came
     * from the demo picker (App\Domain\DemoSwitch). CSRF is checked by the middleware.
     * Changes no job data, so it writes no activity row (like /demo-login).
     */
    public static function switchUser(Request $r, Deps $d): Response
    {
        $decision = DemoSwitch::can($d->config->demoMode(), self::isDemoSession($r->session()));
        if (!$decision->allowed) {
            return Response::forbidden($decision->reason);
        }
        $target = $d->users->findById($r->pathValue('userId'));
        if ($target === null || !$target->isActive) {
            return Shell::deny($r, 'That user does not exist.');
        }
        AuthHandlers::startDemoSession($r->session(), $target, $d->clock->now()->getTimestamp());
        return Response::navigate($r->isDatastar(), url('/today'));
    }
}
