<?php
declare(strict_types=1);

namespace App\Http;

/** In-memory Session for tests. */
final class MemorySession implements Session
{
    public int $regenerations = 0;
    public bool $destroyed = false;

    /** @param array<string,mixed> $data */
    public function __construct(public array $data = []) {}

    public function start(): void {}

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->destroyed = true;
    }

    public function close(): void {}
}
