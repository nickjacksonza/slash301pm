<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\Role;
use App\Domain\Types\User;

/** All SQL for the users table. Columns match api/db.php; legacy writes the same rows. */
final class UserStore
{
    private const COLS = 'id, username, name, email, role, color, brand_id, is_active';

    public function __construct(private readonly Db $db) {}

    public function findById(string $id): ?User
    {
        $row = $this->db->one('SELECT ' . self::COLS . ' FROM users WHERE id = :id', ['id' => $id]);
        return $row === null ? null : User::fromRow($row);
    }

    /** Active users only, like legacy handleLogin(). */
    public function findByUsername(string $username): ?User
    {
        $row = $this->db->one('SELECT ' . self::COLS . ' FROM users WHERE username = :u AND is_active = 1', ['u' => $username]);
        return $row === null ? null : User::fromRow($row);
    }

    public function passwordHash(string $id): ?string
    {
        $v = $this->db->scalar('SELECT password_hash FROM users WHERE id = :id', ['id' => $id]);
        return $v === null ? null : (string) $v;
    }

    /** @return list<User> ordered by role then name, like legacy check_session */
    public function listActive(): array
    {
        $out = [];
        foreach ($this->db->query('SELECT ' . self::COLS . ' FROM users WHERE is_active = 1 ORDER BY role, name') as $row) {
            $out[] = User::fromRow($row);
        }
        return $out;
    }

    /** @return list<User> including deactivated users, active first */
    public function listAll(): array
    {
        $out = [];
        foreach ($this->db->query('SELECT ' . self::COLS . ' FROM users ORDER BY is_active DESC, role, name') as $row) {
            $out[] = User::fromRow($row);
        }
        return $out;
    }

    public function usernameTaken(string $username): bool
    {
        return $this->db->scalar('SELECT 1 FROM users WHERE username = :u', ['u' => $username]) !== null;
    }

    /** Generates the id in PHP (no RETURNING on SQLite 3.34). Returns it. */
    public function create(string $username, string $passwordHash, string $name, ?string $email, Role $role, string $color, ?string $brandId): string
    {
        $id = bin2hex(random_bytes(16));
        $this->db->txImmediate(function (Db $db) use ($id, $username, $passwordHash, $name, $email, $role, $color, $brandId): void {
            $db->exec(
                'INSERT INTO users (id, username, password_hash, name, email, role, color, brand_id) VALUES (:id, :username, :hash, :name, :email, :role, :color, :brand)',
                ['id' => $id, 'username' => $username, 'hash' => $passwordHash, 'name' => $name, 'email' => $email, 'role' => $role->value, 'color' => $color, 'brand' => $brandId],
            );
        });
        return $id;
    }

    public function setPassword(string $id, string $passwordHash): void
    {
        $this->db->txImmediate(function (Db $db) use ($id, $passwordHash): void {
            $db->exec('UPDATE users SET password_hash = :h WHERE id = :id', ['h' => $passwordHash, 'id' => $id]);
        });
    }

    public function setActive(string $id, bool $active): void
    {
        $this->db->txImmediate(function (Db $db) use ($id, $active): void {
            $db->exec('UPDATE users SET is_active = :a WHERE id = :id', ['a' => $active ? 1 : 0, 'id' => $id]);
        });
    }
}
