-- 0013_brand_logos: an optional logo per brand, shown on My day's brand
-- filter row. Only a link is stored (no uploads): an https:// URL that
-- Domain\Links::isHttpsUrl accepted when it was saved, and that the view checks
-- again before it renders an <img>. NULL means "no logo": the page shows a
-- coloured initials badge instead. The legacy app never reads this column, and
-- its brand INSERTs name their columns, so they keep working.
--
-- The index serves the COO's Overrides report (GET /admin/overrides), which
-- reads activity rows by verb and date range.

ALTER TABLE brands ADD COLUMN logo_url TEXT;

CREATE INDEX IF NOT EXISTS idx_activity_verb_created ON activity(verb, created_at);
