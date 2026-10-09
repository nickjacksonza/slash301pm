<?php
declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

/**
 * Patch HTML into the page. No selector: each top-level element is matched by
 * its id (only Outer and Replace allow that). Remove needs a selector and no HTML.
 */
final class PatchElements
{
    public function __construct(
        public readonly string $html,
        public readonly ?string $selector = null,
        public readonly PatchMode $mode = PatchMode::Outer,
    ) {
        if ($selector === null && $mode !== PatchMode::Outer && $mode !== PatchMode::Replace) {
            throw new InvalidArgumentException('Datastar mode ' . $mode->value . ' needs a selector');
        }
    }

    /** Morph by id (the default and most common patch). */
    public static function html(string $html): self
    {
        return new self($html);
    }

    public static function into(string $selector, string $html, PatchMode $mode = PatchMode::Inner): self
    {
        return new self($html, $selector, $mode);
    }

    public static function remove(string $selector): self
    {
        return new self('', $selector, PatchMode::Remove);
    }

    /** LF only: SSE data lines must not carry a stray CR. */
    public function normalizedHtml(): string
    {
        return str_replace(["\r\n", "\r"], "\n", $this->html);
    }
}
