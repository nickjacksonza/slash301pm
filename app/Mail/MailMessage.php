<?php
declare(strict_types=1);

namespace App\Mail;

/** One plain-text email. Go: type Message struct. */
final class MailMessage
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
    ) {}

    /** Header-safe: no line breaks or control characters in the addresses or the subject, and valid addresses. */
    public function headersSafe(): bool
    {
        foreach ([$this->from, $this->to, $this->subject] as $v) {
            if (preg_match('/[\x00-\x1f\x7f]/', $v) === 1) {
                return false;
            }
        }
        return filter_var($this->from, FILTER_VALIDATE_EMAIL) !== false && filter_var($this->to, FILTER_VALIDATE_EMAIL) !== false;
    }
}
