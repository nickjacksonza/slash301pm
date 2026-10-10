-- 0008_users_client_signoff: Client sub-role without a users.role rebuild
-- (docs/roles.md Q3). 1 = may sign off (Brand Director), 0 = Marketing Manager.
-- Default 1 keeps today's behaviour for every existing client; NULL reads as 1.
-- Used from the client portal phase on; nothing reads it yet.

ALTER TABLE users ADD COLUMN client_signoff INTEGER DEFAULT 1;
