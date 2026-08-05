-- Shop pipeline notes overlay (applied by includes/db.php).
CREATE TABLE IF NOT EXISTS shop_notes (
    shop_id INTEGER PRIMARY KEY,
    notes TEXT NOT NULL DEFAULT '',
    updated_by_user_id INTEGER,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
