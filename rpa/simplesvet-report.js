import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';
import { db } from './store.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.dirname(here);
dotenv.config({ path: process.env.LISON_ENV_FILE || path.join(root, 'secure', 'config.env') });
const reportDir = process.env.SIMPLESVET_REPORT_DIR || path.join(root, 'logs', 'reports');
fs.mkdirSync(reportDir, { recursive: true, mode: 0o700 });

const rows = db.prepare(`
  SELECT customer_id, customer_name, desired_marked, applied_marked, status,
         attempts, last_action, last_error, last_attempt_at, synced_at, updated_at
    FROM customer_sync
   ORDER BY status, customer_name, customer_id
`).all();

const clean = (value) => String(value ?? '').replace(/[\r\n]+/g, ' ').trim();
const counts = rows.reduce((result, row) => {
  result[row.status] = (result[row.status] || 0) + 1;
  return result;
}, {});
const now = new Date();
const stamp = now.toISOString().replace(/[:.]/g, '-');
const lines = [
  `RELATORIO SIMPLESVET ${now.toISOString()}`,
  `TOTAL=${rows.length} SUCESSO=${counts.synced || 0} PENDENTE=${counts.pending || 0} RETENTATIVA=${counts.retry || 0} REVISAO_MANUAL=${counts.manual_review || 0}`,
  '',
  '=== SUCESSOS ===',
];

for (const row of rows.filter((item) => item.status === 'synced')) {
  lines.push(`SUCESSO | id=${row.customer_id} | nome=${clean(row.customer_name)} | acao=${clean(row.last_action)} | sincronizado=${clean(row.synced_at)}`);
}

lines.push('', '=== ERROS ===');
const failed = rows.filter((item) => item.status === 'retry' || item.status === 'manual_review');
if (!failed.length) lines.push('NENHUM');
for (const row of failed) {
  lines.push(`ERRO | id=${row.customer_id} | nome=${clean(row.customer_name)} | status=${row.status} | tentativas=${row.attempts} | motivo=${clean(row.last_error)}`);
}

lines.push('', '=== PENDENTES ===');
const pending = rows.filter((item) => item.status === 'pending');
if (!pending.length) lines.push('NENHUM');
for (const row of pending) {
  lines.push(`PENDENTE | id=${row.customer_id} | nome=${clean(row.customer_name)} | acao_desejada=${Number(row.desired_marked) === 1 ? 'ADICIONAR' : 'REMOVER'}`);
}

const report = `${lines.join('\n')}\n`;
const stampedFile = path.join(reportDir, `simplesvet-report-${stamp}.txt`);
const latestFile = path.join(reportDir, 'simplesvet-report-latest.txt');
fs.writeFileSync(stampedFile, report, { mode: 0o600 });
fs.writeFileSync(latestFile, report, { mode: 0o600 });

console.log(`REPORT total=${rows.length} sucesso=${counts.synced || 0} pendente=${counts.pending || 0} retry=${counts.retry || 0} manual_review=${counts.manual_review || 0}`);
for (const row of failed) {
  console.log(`REPORT_ERRO customer_id=${row.customer_id} status=${row.status} tentativas=${row.attempts} motivo=${clean(row.last_error)}`);
}
console.log(`REPORT_FILE ${latestFile}`);

const panelUrl = String(process.env.SIMPLESVET_PANEL_REPORT_URL
  || 'https://andrejrs.com.br/api/simplesvet_sync.php').trim();
const panelToken = String(process.env.API_BEARER_TOKEN || '').trim();
if (panelUrl && panelToken) {
  try {
    const response = await fetch(panelUrl, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-API-Key': panelToken,
      },
      body: JSON.stringify({
        generated_at: now.toISOString(),
        summary: {
          total: rows.length,
          synced: counts.synced || 0,
          pending: counts.pending || 0,
          retry: counts.retry || 0,
          manual_review: counts.manual_review || 0,
        },
        items: rows,
      }),
      signal: AbortSignal.timeout(30000),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok !== true) {
      throw new Error(`HTTP ${response.status}: ${clean(result.error || 'resposta invalida')}`);
    }
    console.log(`REPORT_PANEL_SYNC saved=${Number(result.saved || 0)}`);
  } catch (error) {
    console.error(`REPORT_PANEL_ERROR ${clean(error?.message || error)}`);
    process.exitCode = 1;
  }
} else {
  console.log('REPORT_PANEL_SKIPPED URL ou API_BEARER_TOKEN nao configurado');
}
db.close();
