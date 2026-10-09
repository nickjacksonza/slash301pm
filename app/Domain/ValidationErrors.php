<?php
declare(strict_types=1);

namespace App\Domain;

/** Field => message. Validation returns this; it never throws. Go: type ValidationErrors map[string]string. */
final class ValidationErrors
{
    /** @param array<string,string> $errors */
    public function __construct(
        public readonly array $errors = [],
    ) {}

    public function with(string $field, string $message): self
    {
        $errors = $this->errors;
        if (!isset($errors[$field])) {
            $errors[$field] = $message;
        }
        return new self($errors);
    }

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    public function first(): string
    {
        foreach ($this->errors as $message) {
            return $message;
        }
        return '';
    }
}
