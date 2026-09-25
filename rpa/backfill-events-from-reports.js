import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { db } from './store.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.dirname(here);
const reportDir = process.env.SIMPLESVET_REPORT_DIR || path.join(root, 'logs', 'reports');
const files = fs.existsSync(reportDir)
  ? fs.readdirSync(reportDir).filter((name) => /^simplesvet-report-.*\.txt$/.test(name) && !name.includes('latest')).sort()
  : [];
const earliest = new Map();
const pattern = /^SUCESSO \| id=(\d+) \| nome=(.*?) \| acao=(ADD|REMOVE) \| sincronizado=(.+)$/;

for (const file of files) {
  const lines = fs.readFileSync(path.join(reportDir, file), 'utf8').split(/\r?\n/);
  for (const line of lines) {
    const match = line.match(pattern);
    if (!match) continue;
    const [, customerId, customerName, action, rawDate] = match;
    const key = `${customerId}:${action}`;
    const occurredAt = `${rawDate.trim().replace(' ', 'T')}Z`;
    const existing = earliest.get(key);
    if (!existing || occurredAt < existing.occurredAt) {
      earliest.set(key, { customerId: Number(customerId), customerName: customerName.trim(), action, occurredAt });
    }
  }
}

const insert = db.prepare(`
  INSERT OR IGNORE INTO sync_events (
    event_key, customer_id, customer_name, action, occurred_at
  ) VALUES (?, ?, ?, ?, ?)
`);
let inserted = 0;
db.exec('BEGIN IMMEDIATE');
try {
  for (const [key, event] of earliest) {
    const result = insert.run(
      `report-backfill:${key}`,
      event.customerId,
      event.customerName,
      event.action,
      event.occurredAt,
    );
    inserted += Number(result.changes || 0);
  }
  db.exec('COMMIT');
} catch (error) {
  db.exec('ROLLBACK');
  throw error;
} finally {
  db.close();
}

console.log(`BACKFILL reports=${files.length} events_found=${earliest.size} inserted=${inserted}`);

