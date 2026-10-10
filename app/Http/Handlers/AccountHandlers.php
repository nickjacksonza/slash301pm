<?php
declare(strict_types=1);

namespace App\Http\Handlers;

use App\Domain\LoginRules;
use App\Domain\Signals\PasswordChangeSignals;
use App\Http\Deps;
use App\Http\PatchElements;
use App\Http\Request;
use App\Http\Response;
use App\Http\Toast;

/** GET/POST /account/password. Datastar form, with a plain form post as fallback. */
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
}
