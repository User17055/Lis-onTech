import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';
import { chromium } from 'playwright';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.dirname(here);
const envFile = process.env.LISON_ENV_FILE || path.join(root, 'secure', 'config.env');
dotenv.config({ path: envFile });
const { db } = await import('./store.js');

const env = (name, fallback = '') => String(process.env[name] ?? fallback).trim();
const firstEnv = (...names) => {
  for (const name of names) {
    const value = env(name);
    if (value) return value;
  }
  return '';
};
const required = (name) => {
  const value = env(name);
  if (!value) throw new Error(`${name} nao configurada`);
  return value;
};

const marker = env('SIMPLESVET_MARKER', 'CONSULTAR GERENCIA');
const maxItems = Math.max(1, Number(env('SIMPLESVET_BATCH_SIZE', '20')) || 20);
const retryMinutes = Math.max(5, Number(env('SIMPLESVET_RETRY_MINUTES', '60')) || 60);
const maxAttempts = Math.max(1, Number(env('SIMPLESVET_MAX_ATTEMPTS', '5')) || 5);

function digits(value) {
  return String(value ?? '').replace(/\D/g, '');
}

function validCpf(value) {
  const cpf = digits(value);
  if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
  for (let size = 9; size <= 10; size++) {
    let sum = 0;
    for (let i = 0; i < size; i++) sum += Number(cpf[i]) * (size + 1 - i);
    const check = ((sum * 10) % 11) % 10;
    if (check !== Number(cpf[size])) return false;
  }
  return true;
}

function customerDocument(customer) {
  const candidates = [
    customer.registry_code,
    customer.cpf,
    customer.document,
    customer.document_number,
    customer?.metadata?.cpf,
    customer?.metadata?.document,
  ];
  return candidates.map(digits).find(validCpf) || '';
}

async function getVindiCustomer(customerId) {
  const base = required('VINDI_API_BASE').replace(/\/$/, '');
  const key = required('VINDI_API_KEY');
  const response = await fetch(`${base}/customers/${customerId}`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Basic ${Buffer.from(`${key}:`).toString('base64')}`,
    },
  });
  if (!response.ok) throw new Error(`Vindi customer HTTP ${response.status}`);
  const json = await response.json();
  return json.customer ?? json;
}

async function clickText(page, text) {
  const item = page.getByText(text, { exact: true }).first();
  await item.waitFor({ state: 'visible', timeout: 15000 });
  await item.click();
}

async function login(page) {
  await page.goto(required('SIMPLESVET_LOGIN_URL'), { waitUntil: 'domcontentloaded' });
  const userSelector = env('SIMPLESVET_USERNAME_SELECTOR', 'input[name="usuario"], input[name="username"], input[type="email"]');
  const passwordSelector = env('SIMPLESVET_PASSWORD_SELECTOR', 'input[name="senha"], input[name="password"], input[type="password"]');
  const username = firstEnv('SIMPLESVET_USER', 'SIMPLESVET_USERNAME');
  if (!username) throw new Error('SIMPLESVET_USER nao configurada');
  await page.locator(userSelector).first().fill(username);
  await page.locator(passwordSelector).first().fill(required('SIMPLESVET_PASSWORD'));
  const submitSelector = env('SIMPLESVET_SUBMIT_SELECTOR');
  if (submitSelector) await page.locator(submitSelector).first().click();
  else await page.getByRole('button', { name: /entrar|acessar|login/i }).first().click();
  await page.waitForLoadState('domcontentloaded');
  await selectUnitIfNeeded(page);
}

async function selectUnitIfNeeded(page) {
  const unitName = env('SIMPLESVET_UNIT_NAME');
  if (!unitName) return;

  const configuredSelector = env('SIMPLESVET_UNIT_SELECTOR');
  if (configuredSelector) {
    const field = page.locator(configuredSelector).first();
    await field.waitFor({ state: 'visible', timeout: 15000 });
    const tagName = await field.evaluate((el) => el.tagName.toLowerCase());
    if (tagName === 'select') await field.selectOption({ label: unitName });
    else {
      await field.click();
      await page.getByText(unitName, { exact: true }).last().click();
    }
    await page.waitForLoadState('domcontentloaded').catch(() => undefined);
    return;
  }

  // Algumas contas exibem um <select> de unidade logo apos o login.
  const selects = page.locator('select');
  for (let index = 0; index < await selects.count(); index++) {
    const select = selects.nth(index);
    const hasUnit = await select.evaluate((el, expected) => Array.from(el.options)
      .some((option) => option.text.trim() === expected), unitName);
    if (hasUnit) {
      await select.selectOption({ label: unitName });
      await page.waitForLoadState('domcontentloaded').catch(() => undefined);
      return;
    }
  }

  const unitText = page.getByText(unitName, { exact: true });
  if (await unitText.count() > 0 && await unitText.first().isVisible()) {
    await unitText.first().click();
    await page.waitForLoadState('domcontentloaded').catch(() => undefined);
  }
}

async function openResponsibleSearch(page) {
  const directUrl = env('SIMPLESVET_RESPONSIBLE_URL');
  if (directUrl) {
    await page.goto(directUrl, { waitUntil: 'domcontentloaded' });
    return;
  }
  await clickText(page, 'Atendimento Clínico');
  await clickText(page, 'Responsável');
  await clickText(page, 'Pesquisa');
}

async function locateResponsible(page, cpf) {
  await openResponsibleSearch(page);
  await page.locator(env('SIMPLESVET_FILTER_SELECTOR', '#p__btn_expandir')).first().click();
  const cpfSelector = required('SIMPLESVET_CPF_SELECTOR');
  await page.locator(cpfSelector).first().fill(cpf);
  const searchSelector = env('SIMPLESVET_SEARCH_SELECTOR');
  if (searchSelector) await page.locator(searchSelector).first().click();
  else await page.getByRole('button', { name: /pesquisar|buscar/i }).first().click();
  await page.waitForLoadState('domcontentloaded');

  const rows = page.locator(env('SIMPLESVET_RESULT_ROWS_SELECTOR', 'table tbody tr'));
  await rows.first().waitFor({ state: 'visible', timeout: 15000 });
  const count = await rows.count();
  if (count !== 1) throw new Error(`Pesquisa por CPF retornou ${count} registros; revisao manual necessaria`);
  await rows.first().click();
}

async function updateMarker(page, shouldMark) {
  await clickText(page, 'Informações');
  await clickText(page, 'Extras');

  const selector = required('SIMPLESVET_MARKINGS_SELECTOR');
  const field = page.locator(selector).first();
  await field.waitFor({ state: 'visible', timeout: 15000 });
  const tagName = await field.evaluate((el) => el.tagName.toLowerCase());

  const hasMarker = async (locator, type) => {
    if (type === 'select') {
      return locator.evaluate((el, expected) => Array.from(el.selectedOptions)
        .some((option) => option.text.trim().toLocaleUpperCase('pt-BR') === expected.toLocaleUpperCase('pt-BR')
          || option.value.toLocaleUpperCase('pt-BR') === expected.toLocaleUpperCase('pt-BR')), marker);
    }
    const value = await locator.inputValue();
    return value.split(/[,;\n]+/).map((part) => part.trim().toLocaleUpperCase('pt-BR'))
      .includes(marker.toLocaleUpperCase('pt-BR'));
  };

  if (await hasMarker(field, tagName) === shouldMark) return;

  if (tagName === 'select') {
    await field.evaluate((el, data) => {
      const select = /** @type {HTMLSelectElement} */ (el);
      let option = Array.from(select.options).find((o) => o.text.trim() === data.marker || o.value === data.marker);
      if (data.shouldMark && !option) {
        option = new Option(data.marker, data.marker, true, true);
        select.add(option);
      }
      if (option) option.selected = data.shouldMark;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }, { marker, shouldMark });
  } else {
    const current = await field.inputValue();
    const parts = current.split(/[,;\n]+/).map((v) => v.trim()).filter(Boolean);
    const without = parts.filter((v) => v.toLocaleUpperCase('pt-BR') !== marker.toLocaleUpperCase('pt-BR'));
    if (shouldMark) without.push(marker);
    await field.fill(without.join(', '));
    await field.dispatchEvent('change');
  }

  const saveSelector = env('SIMPLESVET_SAVE_SELECTOR');
  if (saveSelector) await page.locator(saveSelector).first().click();
  else await page.getByRole('button', { name: /salvar|gravar/i }).first().click();
  await Promise.race([
    page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => undefined),
    page.waitForTimeout(1000),
  ]);

  const savedField = page.locator(selector).first();
  await savedField.waitFor({ state: 'visible', timeout: 15000 });
  if (await hasMarker(savedField, tagName) !== shouldMark) {
    throw new Error('O SimplesVet nao confirmou a alteracao da marcacao');
  }
}

let browser;
let exitCode = 0;
try {
  const rows = db.prepare(
    `SELECT customer_id, customer_name, desired_marked, attempts
       FROM customer_sync
      WHERE status IN ('pending', 'retry')
        AND (next_attempt_at IS NULL OR next_attempt_at <= CURRENT_TIMESTAMP)
      ORDER BY updated_at ASC
      LIMIT ?`,
  ).all(maxItems);

  if (!rows.length) {
    console.log('OK fila vazia');
  } else {
    browser = await chromium.launch({ headless: env('SIMPLESVET_HEADLESS', '1') !== '0' });
    const context = await browser.newContext({ locale: 'pt-BR' });
    const page = await context.newPage();
    await login(page);

    for (const row of rows) {
      const customerId = Number(row.customer_id);
      const shouldMark = Number(row.desired_marked) === 1;
      db.prepare(`UPDATE customer_sync
                     SET status='processing', last_attempt_at=CURRENT_TIMESTAMP,
                         attempts=attempts+1, updated_at=CURRENT_TIMESTAMP
                   WHERE customer_id=? AND status IN ('pending','retry')`).run(customerId);

      try {
        const customer = await getVindiCustomer(customerId);
        const cpf = customerDocument(customer);
        if (!cpf) throw new Error('CPF ausente ou invalido na Vindi');
        await locateResponsible(page, cpf);
        await updateMarker(page, shouldMark);
        db.prepare(`UPDATE customer_sync
                       SET applied_marked=?, status='synced', attempts=0,
                           next_attempt_at=NULL, last_action=?, last_error=NULL,
                           synced_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP
                     WHERE customer_id=?`).run(
          shouldMark ? 1 : 0,
          shouldMark ? 'ADD' : 'REMOVE',
          customerId,
        );
        console.log(`OK customer_id=${customerId} action=${shouldMark ? 'ADD' : 'REMOVE'}`);
      } catch (error) {
        exitCode = 1;
        const attempts = Number(row.attempts || 0) + 1;
        const status = attempts >= maxAttempts ? 'manual_review' : 'retry';
        const message = String(error?.message || error).slice(0, 2000);
        const nextAttempt = status === 'retry'
          ? new Date(Date.now() + retryMinutes * 60000).toISOString()
          : null;
        db.prepare(`UPDATE customer_sync
                       SET status=?, next_attempt_at=?, last_error=?, updated_at=CURRENT_TIMESTAMP
                     WHERE customer_id=?`).run(status, nextAttempt, message, customerId);
        console.error(`ERRO customer_id=${customerId} ${message}`);
      }
    }
  }
} finally {
  if (browser) await browser.close();
  db.close();
}
process.exitCode = exitCode;
