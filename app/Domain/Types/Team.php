<?php
declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\Role;

/** Every assignment slot on a job (job_assignments is UNIQUE per job and role). */
final class Team
{
    /** @param list<Assignment> $assignments */
    public function __construct(
        public readonly array $assignments,
    ) {}

    public function holder(Role $role): ?Assignment
    {
        foreach ($this->assignments as $a) {
            if ($a->role === $role) {
                return $a;
            }
        }
        return null;
    }

    public function userFor(Role $role): ?string
    {
        $a = $this->holder($role);
        return $a === null ? null : $a->userId;
    }

    public function has(string $userId): bool
    {
        foreach ($this->assignments as $a) {
            if ($a->userId === $userId) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> distinct user ids, Client slots excluded */
    public function agencyUserIds(): array
    {
        $out = [];
        foreach ($this->assignments as $a) {
            if ($a->role !== Role::Client && !in_array($a->userId, $out, true)) {
                $out[] = $a->userId;
            }
        }
        return $out;
    }

    /** Snapshot form: role => {user_id, name}, in briefRoles() order. @return array<string,array{user_id:string,name:string}> */
    public function toArray(): array
    {
        $out = [];
        foreach (self::briefRoles() as $role) {
            $a = $this->holder($role);
            if ($a !== null) {
                $out[$role->value] = ['user_id' => $a->userId, 'name' => $a->userName];
            }
        }
        return $out;
    }

    /**
     * Every slot the team section offers (POST /jobs/{id}/assignments/{role}), in
     * order: the account team, Traffic, the CD, makers and QA. Wider than
     * briefRoles(), which stays the sent snapshot's team. @return list<Role>
     */
    public static function slotRoles(): array
    {
        return [Role::AM, Role::PM, Role::Producer, Role::Traffic, Role::CD, Role::Copywriter, Role::Designer, Role::QA, Role::Developer, Role::SEO, Role::Social];
    }

    /** The slots the sent snapshot records, in order. Traffic is required to send. @return list<Role> */
    public static function briefRoles(): array
    {
        return [Role::AM, Role::Traffic, Role::CD, Role::Copywriter, Role::Designer];
    }
}
