<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Tell the browser to navigate (Datastar requests only; never answer an action
 * with a 3xx). Sent as a patch of the UI signal _redirect, which the layout's
 * body watches with data-effect (layout_redirect_effect()). A script would be
 * blocked by the CSP (no inline scripts, docs/adr/0007-security-headers.md).
 */
final class Redirect
{
    public const SIGNAL = '_redirect';

    public function __construct(
        public readonly string $url,
    ) {}

    /** {"_redirect":"/slash301pm/..."}, JSON-encoded with the HEX flags. */
    public function signalsJson(): string
    {
        return (string) json_encode([self::SIGNAL => $this->url], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
