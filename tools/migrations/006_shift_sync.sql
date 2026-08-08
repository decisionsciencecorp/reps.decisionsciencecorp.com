-- Slice C: Shift sync + Admin/Ops match (applied by includes/db.php migration 006_shift_sync).
-- Idempotent CREATE / ALTER live in PHP; this file is the human-readable checklist.

CREATE TABLE IF NOT EXISTS shops (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'prospect',
    assigned_sales_rep TEXT,
    contact_name TEXT NOT NULL DEFAULT '',
    contact_phone TEXT NOT NULL DEFAULT '',
    agreed_shop_split REAL NOT NULL DEFAULT 0.5,
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- operators expanded: phone, shop_id, status, matched_user_id, rollups, …
-- sessions: session_id PK from Shift hours-feed
-- operator_match_events: match/unmatch audit
