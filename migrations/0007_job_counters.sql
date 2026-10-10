-- 0007_job_counters: one counter per brand prefix, so job numbers cannot collide.
-- next is the number the next job gets. Seeded from the highest existing number
-- per prefix (PREFIX-NNN), the same formula the patched legacy add_job uses for
-- a prefix it has not seen. Idempotent: existing counters are left alone.

CREATE TABLE IF NOT EXISTS job_counters (
    prefix TEXT PRIMARY KEY,
    next INTEGER NOT NULL
);

INSERT INTO job_counters (prefix, next)
SELECT b.prefix,
       COALESCE((SELECT MAX(CAST(SUBSTR(j.job_number, LENGTH(b.prefix) + 2) AS INTEGER))
                 FROM jobs j WHERE j.job_number LIKE b.prefix || '-%'), 0) + 1
FROM brands b
WHERE NOT EXISTS (SELECT 1 FROM job_counters c WHERE c.prefix = b.prefix);
