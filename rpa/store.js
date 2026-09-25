import fs from 'node:fs';
import path from 'node:path';
import { DatabaseSync } from 'node:sqlite';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const dataDir = process.env.SIMPLESVET_DATA_DIR || path.join(here, 'data');
fs.mkdirSync(dataDir, { recursive: true, mode: 0o700 });

export const db = new DatabaseSync(path.join(dataDir, 'simplesvet.sqlite'));
db.exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
db.exec(`
  CREATE TABLE IF NOT EXISTS customer_sync (
    customer_id INTEGER PRIMARY KEY,
    customer_name TEXT NOT NULL DEFAULT '',
    desired_marked INTEGER NOT NULL DEFAULT 0,
    applied_marked INTEGER NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    next_attempt_at TEXT NULL,
    last_action TEXT NULL,
    last_error TEXT NULL,
    last_overdue_seen_at TEXT NULL,
    last_attempt_at TEXT NULL,
    synced_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
  );
  CREATE INDEX IF NOT EXISTS idx_customer_sync_queue
    ON customer_sync(status, next_attempt_at);

  CREATE TABLE IF NOT EXISTS sync_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_key TEXT NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
    customer_name TEXT NOT NULL DEFAULT '',
    action TEXT NOT NULL CHECK(action IN ('ADD', 'REMOVE', 'VERIFIED')),
    occurred_at TEXT NOT NULL,
    reported_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
  );
  CREATE INDEX IF NOT EXISTS idx_sync_events_occurred
    ON sync_events(occurred_at DESC);
  CREATE INDEX IF NOT EXISTS idx_sync_events_reported
    ON sync_events(reported_at, occurred_at);

  INSERT OR IGNORE INTO sync_events (
    event_key, customer_id, customer_name, action, occurred_at
  )
  SELECT
    'legacy:' || customer_id || ':' || last_action || ':' || synced_at,
    customer_id, customer_name, last_action, synced_at
  FROM customer_sync
  WHERE last_action IN ('ADD', 'REMOVE') AND synced_at IS NOT NULL;
`);
