<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * major.minor.patch (ADR 0003). Drafts are 0.x (0.1.0); the first send is 1.0.0;
 * a bump resets the parts to its right. Go: type BriefVersion struct.
 */
final class BriefVersion
{
    public function __construct(
        public readonly int $major,
        public readonly int $minor,
        public readonly int $patch,
    ) {}

    public static function draft(): self
    {
        return new self(0, 1, 0);
    }

    public static function first(): self
    {
        return new self(1, 0, 0);
    }

    /** "1.2.3" or "v1.2.3"; null for anything else. */
    public static function parse(string $s): ?self
    {
        if (preg_match('/^v?(0|[1-9]\d{0,5})\.(0|[1-9]\d{0,5})\.(0|[1-9]\d{0,5})$/', trim($s), $m) !== 1) {
            return null;
        }
        return new self((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    public function format(): string
    {
        return $this->major . '.' . $this->minor . '.' . $this->patch;
    }

    /** "v1.2.0" */
    public function label(): string
    {
        return 'v' . $this->format();
    }

    public function isDraft(): bool
    {
        return $this->major === 0;
    }

    public function bump(BumpLevel $level): self
    {
        return match ($level) {
            BumpLevel::Major => new self($this->major + 1, 0, 0),
            BumpLevel::Minor => new self($this->major, $this->minor + 1, 0),
            BumpLevel::Patch => new self($this->major, $this->minor, $this->patch + 1),
        };
    }

    /** -1, 0 or 1. */
    public function compare(self $o): int
    {
        if ($this->major !== $o->major) {
            return $this->major <=> $o->major;
        }
        if ($this->minor !== $o->minor) {
            return $this->minor <=> $o->minor;
        }
        return $this->patch <=> $o->patch;
    }

    public function equals(self $o): bool
    {
        return $this->compare($o) === 0;
    }
}
