<?php
declare(strict_types=1);

namespace App\Store;

use App\Domain\ClientPersona;
use App\Domain\InviteRules;
use App\Domain\Types\ActivityEntry;
use App\Domain\Types\ClientContact;
use App\Domain\Types\Invite;
use DateTimeImmutable;

/**
 * Client contacts and their invite links (migration 0015). Every query is
 * scoped to a brand or to one token hash. Tokens never reach this class, only
 * their sha256 (InviteRules::hash). Writes log activity in the same
 * transaction (client_invited, client_invite_reissued, client_invite_accepted);
 * activity data never holds the token or its hash.
 */
final class InviteStore
{
    private const INVITE_SELECT = "SELECT i.id, i.user_id, i.token_hash, i.expires_at, i.used_at,
            u.name AS user_name, u.username, u.brand_id, COALESCE(b.name, '') AS brand_name, u.client_signoff
        FROM invites i JOIN users u ON u.id = i.user_id LEFT JOIN brands b ON b.id = u.brand_id";

    public function __construct(private readonly Db $db, private readonly ActivityStore $activity) {}

    /** Is this email already a username or a user's email (any role, any brand)? */
    public function emailTaken(string $email): bool
    {
        return $this->db->scalar('SELECT 1 FROM users WHERE lower(username) = :e OR lower(email) = :e LIMIT 1', ['e' => strtolower($email)]) !== null;
    }

    /** The Client contacts of one brand: pending invites first, then active, then deactivated, by name. @return list<ClientContact> */
    public function contacts(string $brandId): array
    {
        $out = [];
        foreach ($this->db->query(
            "SELECT u.id, u.name, u.username, u.email, u.client_signoff, u.is_active,
                    (SELECT COUNT(*) FROM invites i WHERE i.user_id = u.id) AS invites,
                    (SELECT COUNT(*) FROM invites i WHERE i.user_id = u.id AND i.used_at IS NOT NULL) AS used,
                    (SELECT MAX(i.expires_at) FROM invites i WHERE i.user_id = u.id AND i.used_at IS NULL) AS open_expires
             FROM users u WHERE u.role = 'Client' AND u.brand_id = :b
             ORDER BY u.is_active, u.name, u.id",
            ['b' => $brandId],
        ) as $r) {
            $out[] = ClientContact::fromRow($r);
        }
        $pending = [];
        $rest = [];
        foreach ($out as $c) {
            if ($c->pending) {
                $pending[] = $c;
            } else {
                $rest[] = $c;
            }
        }
        return array_merge($pending, $rest);
    }

    /** One contact of this brand, or null (also for a contact of another brand). */
    public function contact(string $brandId, string $userId): ?ClientContact
    {
        foreach ($this->contacts($brandId) as $c) {
            if ($c->userId === $userId) {
                return $c;
            }
        }
        return null;
    }

    /**
     * A new inactive Client user of the brand (username = email) and an
     * invite. Returns the user id.
     */
    public function createClient(string $brandId, string $name, string $email, ClientPersona $persona, string $unusableHash, string $tokenHash,
        DateTimeImmutable $expiresAt, string $actorId, DateTimeImmutable $now): string
    {
        $userId = Ids::new();
        $this->db->txImmediate(function (Db $tx) use ($userId, $brandId, $name, $email, $persona, $unusableHash, $tokenHash, $expiresAt, $actorId, $now): void {
            $tx->exec(
                "INSERT INTO users (id, username, password_hash, name, email, role, color, brand_id, is_active, client_signoff)
                 VALUES (:id, :u, :h, :n, :e, 'Client', '#3b82f6', :b, 0, :s)",
                ['id' => $userId, 'u' => $email, 'h' => $unusableHash, 'n' => $name, 'e' => $email, 'b' => $brandId, 's' => $persona->signoff()],
            );
            $this->insertInvite($tx, $userId, $tokenHash, $expiresAt, $actorId, $now);
            $this->activity->append($tx, new ActivityEntry(null, $actorId, InviteRules::VERB_INVITED, 'user', $userId,
                ['brand_id' => $brandId, 'persona' => $persona->value, 'expires_at' => Ids::utc($expiresAt), 'recipients' => []]), $now);
        });
        return $userId;
    }

    /**
     * A new link for a pending contact of this brand (never accepted); older
     * unused links expire now. False when the user is not a pending Client of
     * the brand.
     */
    public function reissue(string $brandId, string $userId, string $tokenHash, DateTimeImmutable $expiresAt, string $actorId, DateTimeImmutable $now): bool
    {
        return $this->db->txImmediate(function (Db $tx) use ($brandId, $userId, $tokenHash, $expiresAt, $actorId, $now): bool {
            $ok = $tx->scalar(
                "SELECT 1 FROM users u WHERE u.id = :u AND u.role = 'Client' AND u.brand_id = :b AND u.is_active = 0
                   AND EXISTS (SELECT 1 FROM invites i WHERE i.user_id = u.id)
                   AND NOT EXISTS (SELECT 1 FROM invites i WHERE i.user_id = u.id AND i.used_at IS NOT NULL)",
                ['u' => $userId, 'b' => $brandId],
            );
            if ($ok === null) {
                return false;
            }
            // Older unused links stop working now (they expire; used_at stays for an accepted link only).
            $tx->exec('UPDATE invites SET expires_at = :at WHERE user_id = :u AND used_at IS NULL AND expires_at > :at', ['at' => Ids::utc($now), 'u' => $userId]);
            $this->insertInvite($tx, $userId, $tokenHash, $expiresAt, $actorId, $now);
            $this->activity->append($tx, new ActivityEntry(null, $actorId, InviteRules::VERB_REISSUED, 'user', $userId,
                ['brand_id' => $brandId, 'expires_at' => Ids::utc($expiresAt), 'recipients' => []]), $now);
            return true;
        });
    }

    /** The invite with this token hash (a Client user's only), or null. The caller checks InviteRules::usable(). */
    public function findByTokenHash(string $tokenHash): ?Invite
    {
        $r = $this->db->one(self::INVITE_SELECT . " WHERE i.token_hash = :h AND u.role = 'Client'", ['h' => $tokenHash]);
        return $r === null ? null : Invite::fromRow($r);
    }

    /**
     * Use the invite: in one transaction mark it used (only while unused and
     * unexpired), store the new password hash and activate the user. The
     * actor is the invited user. False when the link was used or expired in
     * the meantime (nothing changes).
     */
    public function accept(Invite $i, string $passwordHash, DateTimeImmutable $now): bool
    {
        return $this->db->txImmediate(function (Db $tx) use ($i, $passwordHash, $now): bool {
            $at = Ids::utc($now);
            $n = $tx->exec('UPDATE invites SET used_at = :at WHERE id = :id AND token_hash = :h AND used_at IS NULL AND expires_at > :at',
                ['at' => $at, 'id' => $i->id, 'h' => $i->tokenHash]);
            if ($n !== 1) {
                return false;
            }
            $m = $tx->exec("UPDATE users SET password_hash = :p, is_active = 1 WHERE id = :u AND role = 'Client'", ['p' => $passwordHash, 'u' => $i->userId]);
            if ($m !== 1) {
                throw new \RuntimeException('invite user vanished');
            }
            $this->activity->append($tx, new ActivityEntry(null, $i->userId, InviteRules::VERB_ACCEPTED, 'user', $i->userId,
                ['brand_id' => $i->brandId ?? '', 'recipients' => []]), $now);
            return true;
        });
    }

    private function insertInvite(Db $tx, string $userId, string $tokenHash, DateTimeImmutable $expiresAt, string $actorId, DateTimeImmutable $now): void
    {
        $tx->exec(
            'INSERT INTO invites (id, user_id, token_hash, expires_at, used_at, created_by, created_at) VALUES (:id, :u, :h, :x, NULL, :by, :at)',
            ['id' => Ids::new(), 'u' => $userId, 'h' => $tokenHash, 'x' => Ids::utc($expiresAt), 'by' => $actorId, 'at' => Ids::utc($now)],
        );
    }
}
