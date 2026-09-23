import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.dirname(here);
dotenv.config({ path: process.env.LISON_ENV_FILE || path.join(root, 'secure', 'config.env') });

const { db } = await import('./store.js');
const env = (name, fallback = '') => String(process.env[name] ?? fallback).trim();
const apiKey = env('VINDI_API_KEY');
const apiBase = env('VINDI_API_BASE', 'https://app.vindi.com.br/api/v1').replace(/\/$/, '');
const overdueDays = Math.max(1, Number(env('SIMPLESVET_OVERDUE_DAYS', '30')) || 30);
const perPage = Math.min(50, Math.max(1, Number(env('SIMPLESVET_VINDI_PER_PAGE', '50')) || 50));
const maxPages = Math.min(500, Math.max(1, Number(env('SIMPLESVET_VINDI_MAX_PAGES', '100')) || 100));

if (!apiKey) throw new Error('VINDI_API_KEY nao configurada');

const cutoffDate = new Date();
cutoffDate.setHours(0, 0, 0, 0);
cutoffDate.setDate(cutoffDate.getDate() - overdueDays);
const cutoff = cutoffDate.toISOString().slice(0, 10);
const query = `status=pending AND due_at<=\"${cutoff}\"`;
const seen = new Map();
let pagesRead = 0;

for (let page = 1; page <= maxPages; page++) {
  const url = new URL(`${apiBase}/bills`);
  url.searchParams.set('per_page', String(perPage));
  url.searchParams.set('page', String(page));
  url.searchParams.set('query', query);
  const response = await fetch(url, {
    headers: {
      Accept: 'application/json',
      Authorization: `Basic ${Buffer.from(`${apiKey}:`).toString('base64')}`,
    },
    signal: AbortSignal.timeout(40000),
  });
  if (!response.ok) throw new Error(`Falha Vindi HTTP ${response.status}`);
  const json = await response.json();
  if (!Array.isArray(json.bills)) throw new Error('Lista de faturas invalida na Vindi');

  pagesRead++;
  for (const bill of json.bills) {
    const customer = bill?.customer ?? {};
    const customerId = Number(customer.id ?? bill?.customer_id ?? 0);
    if (!Number.isSafeInteger(customerId) || customerId <= 0) continue;
    seen.set(customerId, String(customer.name || 'Cliente').trim() || 'Cliente');
  }
  if (json.bills.length < perPage) break;
  if (page === maxPages) {
    throw new Error('Limite de paginas da Vindi atingido; remocoes canceladas por seguranca');
  }
}

const runId = new Date().toISOString();
const upsert = db.prepare(`
  INSERT INTO customer_sync (
    customer_id, customer_name, desired_marked, status,
    attempts, next_attempt_at, last_overdue_seen_at, updated_at
  ) VALUES (?, ?, 1, 'pending', 0, CURRENT_TIMESTAMP, ?, CURRENT_TIMESTAMP)
  ON CONFLICT(customer_id) DO UPDATE SET
    customer_name=excluded.customer_name,
    desired_marked=1,
    status=CASE
      WHEN customer_sync.applied_marked=1 THEN 'synced'
      WHEN customer_sync.status='manual_review' THEN 'manual_review'
      ELSE 'pending'
    END,
    next_attempt_at=CASE
      WHEN customer_sync.applied_marked=1 OR customer_sync.status='manual_review' THEN NULL
      ELSE CURRENT_TIMESTAMP
    END,
    last_overdue_seen_at=excluded.last_overdue_seen_at,
    updated_at=CURRENT_TIMESTAMP
`);
const enqueueRemovals = db.prepare(`
  UPDATE customer_sync
     SET desired_marked=0, status='pending', attempts=0,
         next_attempt_at=CURRENT_TIMESTAMP, last_error=NULL, updated_at=CURRENT_TIMESTAMP
   WHERE desired_marked=1 AND applied_marked=1
     AND (last_overdue_seen_at IS NULL OR last_overdue_seen_at < ?)
`);

db.exec('BEGIN IMMEDIATE');
try {
  for (const [customerId, name] of seen) upsert.run(customerId, name, runId);
  const removalResult = enqueueRemovals.run(runId);
  db.exec('COMMIT');
  console.log(`OK cutoff=${cutoff} paginas=${pagesRead} inadimplentes=${seen.size} remocoes_enfileiradas=${removalResult.changes}`);
} catch (error) {
  db.exec('ROLLBACK');
  throw error;
} finally {
  db.close();
}

