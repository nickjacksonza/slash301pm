<?php
declare(strict_types=1);

namespace App\Domain\Signals;

use App\Domain\LoginRules;
use App\Domain\Role;
use App\Domain\ValidationErrors;

/** Signals under new_user.* from the admin users page. Untrusted input. */
final class NewUserSignals
{
    public function __construct(
        public readonly string $name,
        public readonly string $username,
        public readonly string $email,
        public readonly string $role,
        public readonly string $password,
    ) {}

    public static function fromSignals(array $s): self
    {
        $n = isset($s['new_user']) && is_array($s['new_user']) ? $s['new_user'] : [];
        return new self(
            self::str($n, 'name'),
            self::str($n, 'username'),
            self::str($n, 'email'),
            self::str($n, 'role'),
            isset($n['password']) && is_string($n['password']) ? $n['password'] : '',
        );
    }

    public function validate(): ValidationErrors
    {
        $errors = new ValidationErrors();
        $nameLen = mb_strlen($this->name);
        if ($nameLen < 2 || $nameLen > 100) {
            $errors = $errors->with('name', 'Name must be 2 to 100 characters.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,99}$/', $this->username) !== 1) {
            $errors = $errors->with('username', 'Username must be 3 to 100 lowercase letters, digits, dots, dashes or underscores.');
        }
        if ($this->email !== '' && (strlen($this->email) > 200 || filter_var($this->email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors = $errors->with('email', 'Email address is not valid.');
        }
        if (Role::tryFrom($this->role) === null) {
            $errors = $errors->with('role', 'Pick a role.');
        }
        if (!LoginRules::passwordLengthOk($this->password)) {
            $errors = $errors->with('password', 'Password must be 12 to 128 characters.');
        }
        return $errors;
    }

    private static function str(array $a, string $k): string
    {
        return isset($a[$k]) && is_string($a[$k]) ? trim($a[$k]) : '';
    }
}
