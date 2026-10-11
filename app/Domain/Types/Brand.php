<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Links;

final class Brand
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $prefix,
        /** https:// logo link (migration 0013) or '' for none. */
        public readonly string $logoUrl = '',
    ) {}

    public static function fromRow(array $r): self
    {
        return new self((string) $r['id'], (string) $r['name'], (string) $r['prefix'], (string) ($r['logo_url'] ?? ''));
    }

    /** The logo to render: only a link that is still a valid https URL (a row written by hand never becomes an <img src>). */
    public function safeLogoUrl(): string
    {
        return Links::isHttpsUrl($this->logoUrl) ? $this->logoUrl : '';
    }
}
