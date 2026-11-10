<?php
declare(strict_types=1);

namespace App\Mail;

/** Records messages instead of sending them (tests, screenshots). $fail makes every send fail. */
final class MemoryMailer implements Mailer
{
    /** @var list<MailMessage> */
    public array $sent = [];

    public function __construct(public bool $fail = false) {}

    public function send(MailMessage $m): bool
    {
        if ($this->fail || !$m->headersSafe()) {
            return false;
        }
        $this->sent[] = $m;
        return true;
    }
}
