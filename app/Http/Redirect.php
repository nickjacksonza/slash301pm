<?php
declare(strict_types=1);

namespace App\Http;

/** Tell the browser to navigate (Datastar requests only; never answer an action with a 3xx). */
final class Redirect
{
    public function __construct(
        public readonly string $url,
    ) {}

    /** The script Datastar runs; the URL is JSON-encoded with the HEX flags. */
    public function script(): string
    {
        return 'window.location.assign('
            . json_encode($this->url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            . ')';
    }
}
