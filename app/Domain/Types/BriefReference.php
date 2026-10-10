<?php
declare(strict_types=1);

namespace App\Domain\Types;

/** One reference link on a brief. url is '' or an http(s) URL. */
final class BriefReference
{
    public function __construct(
        public readonly string $label,
        public readonly string $url,
    ) {}

    /** @return array{label:string,url:string} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'url' => $this->url];
    }

    /** @return list<self> from decoded JSON (anything malformed is skipped) */
    public static function listFromArray(mixed $v): array
    {
        $out = [];
        if (!is_array($v)) {
            return $out;
        }
        foreach ($v as $item) {
            if (is_array($item) && isset($item['label'], $item['url']) && is_string($item['label']) && is_string($item['url'])) {
                $out[] = new self($item['label'], $item['url']);
            }
        }
        return $out;
    }

    /** One line in the editor's textarea: "label | url", or the url alone. */
    public function toLine(): string
    {
        if ($this->url === '') {
            return $this->label;
        }
        return $this->label === '' || $this->label === $this->url ? $this->url : $this->label . ' | ' . $this->url;
    }
}
