<?php
declare(strict_types=1);

namespace App\Http;

/**
 * A short message in #toasts. SSE: appended to #toasts. html transport: the
 * whole #toasts region is replaced (one patch per response). Rendered by
 * app/View/partials/toast.php.
 */
final class Toast
{
    public function __construct(
        public readonly ToastKind $kind,
        public readonly string $message,
        // Phase 3: jobs grid/board (an optional link after the message, e.g. "Open the brief")
        public readonly string $linkUrl = '',
        public readonly string $linkLabel = '',
    ) {}

    /** The same toast with a link after the message (an app URL from url()). */
    public function withLink(string $url, string $label): self
    {
        return new self($this->kind, $this->message, $url, $label);
    }

    public static function ok(string $message): self
    {
        return new self(ToastKind::Ok, $message);
    }

    public static function warn(string $message): self
    {
        return new self(ToastKind::Warn, $message);
    }

    public static function error(string $message): self
    {
        return new self(ToastKind::Error, $message);
    }

    public static function info(string $message): self
    {
        return new self(ToastKind::Info, $message);
    }

    public function html(): string
    {
        return partial_toast($this->kind->value, $this->message, $this->linkUrl, $this->linkLabel);
    }

    /** The #toasts container holding just this toast (html transport). */
    public function regionHtml(): string
    {
        return partial_toasts_region($this->html());
    }
}
