-- 0005_brief_assets: the deliverables list of a brief. Each line expands into qty
-- rows of the legacy assets table on send; assets.brief_asset_id links them.
-- Backfill: existing assets with a template_id are grouped per job and template
-- into one line each (qty = number of assets), then linked. Idempotent: lines
-- are only added where none exists for that brief and template, and only
-- unlinked assets are linked. Labels are the legacy ASSET_TEMPLATES names.

CREATE TABLE IF NOT EXISTS brief_assets (
    id TEXT PRIMARY KEY,
    brief_id TEXT NOT NULL REFERENCES briefs(id) ON DELETE CASCADE,
    job_id TEXT NOT NULL,
    template_id TEXT,
    label TEXT NOT NULL,
    qty INTEGER NOT NULL DEFAULT 1,
    channel TEXT,
    size_format TEXT,
    specs TEXT,
    copy_required INTEGER NOT NULL DEFAULT 0,
    due_date TEXT,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_brief_assets_brief ON brief_assets(brief_id, sort_order);

ALTER TABLE assets ADD COLUMN brief_asset_id TEXT;

CREATE INDEX IF NOT EXISTS idx_assets_brief_asset ON assets(brief_asset_id);

INSERT INTO brief_assets (id, brief_id, job_id, template_id, label, qty, copy_required, due_date, sort_order)
SELECT lower(hex(randomblob(16))), b.id, a.job_id, a.template_id,
       CASE a.template_id
           WHEN 'social-static' THEN 'Social Post (Static)'
           WHEN 'social-video' THEN 'Social Post (Video)'
           WHEN 'social-carousel' THEN 'Social Carousel'
           WHEN 'social-story' THEN 'Story/Reel'
           WHEN 'banner-display' THEN 'Display Banner'
           WHEN 'banner-animated' THEN 'Animated Banner'
           WHEN 'email-template' THEN 'Email Template'
           WHEN 'email-copy' THEN 'Email Copy'
           WHEN 'landing-page' THEN 'Landing Page'
           WHEN 'video-edit-short' THEN 'Video Edit (Short)'
           WHEN 'video-edit-long' THEN 'Video Edit (Long)'
           WHEN 'print-ad' THEN 'Print Ad'
           WHEN 'ooh-billboard' THEN 'OOH/Billboard'
           WHEN 'radio-spot' THEN 'Radio Spot'
           WHEN 'podcast-ad' THEN 'Podcast Ad'
           WHEN 'blog-post' THEN 'Blog Post'
           WHEN 'press-release' THEN 'Press Release'
           WHEN 'presentation' THEN 'Presentation'
           ELSE a.template_id END,
       COUNT(*),
       CASE WHEN a.template_id IN ('email-copy', 'blog-post', 'press-release') THEN 1 ELSE 0 END,
       MAX(a.due_date),
       MIN(a.sort_order)
FROM assets a
JOIN briefs b ON b.job_id = a.job_id
WHERE a.template_id IS NOT NULL AND a.template_id <> '' AND a.brief_asset_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM brief_assets x WHERE x.brief_id = b.id AND x.template_id = a.template_id)
GROUP BY b.id, a.job_id, a.template_id;

UPDATE assets SET brief_asset_id = (
    SELECT x.id FROM brief_assets x
    JOIN briefs b ON b.id = x.brief_id
    WHERE b.job_id = assets.job_id AND x.template_id = assets.template_id
    ORDER BY x.sort_order, x.id
    LIMIT 1
)
WHERE brief_asset_id IS NULL AND template_id IS NOT NULL AND template_id <> '';
