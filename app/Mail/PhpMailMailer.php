<?php
declare(strict_types=1);

namespace App\Mail;

use Throwable;

/**
 * PHP mail() on the shared host, plain text UTF-8. The From address is the
 * owner's portal sender (Config::MAIL_FROM). Deliverability from a Gmail
 * address through the host's sendmail is not guaranteed (SPF/DMARC), which is
 * why the inviter always also sees the link (docs/portal.md, known limits).
 * Go: a Mailer over net/smtp, later a real service.
 */
final class PhpMailMailer implements Mailer
{
    public function send(MailMessage $m): bool
    {
        if (!$m->headersSafe()) {
            return false;
        }
        $headers = [
            'From' => $m->from,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];
        try {
            $subject = mb_encode_mimeheader($m->subject, 'UTF-8', 'Q');
            $body = str_replace(["\r\n", "\r"], "\n", $m->body);
            return @mail($m->to, $subject, $body, $headers);
        } catch (Throwable) {
            // The app's error handler turns warnings into exceptions; a mail failure is only "not sent".
            return false;
        }
    }
}
