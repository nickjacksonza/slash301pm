<?php
declare(strict_types=1);

namespace App\Mail;

/**
 * Outgoing email (portal invites). True when the message was handed to the
 * transport; false on any failure. Never throws: a failed send must not undo
 * the change that triggered it (the invite is saved first, and the page shows
 * the link). A real email service later is another implementation.
 * Go: type Mailer interface{ Send(Message) bool }.
 */
interface Mailer
{
    public function send(MailMessage $m): bool;
}
