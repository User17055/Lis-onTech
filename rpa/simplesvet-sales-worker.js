import path from 'node:path';
import process from 'node:process';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';
import { chromium } from 'playwright';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.dirname(here);
dotenv.config({ path: process.env.LISON_ENV_FILE || path.join(root, 'secure', 'config.env') });

const env = (name, fallback = '') => String(process.env[name] ?? fallback).trim();
const required = (name) => {
  const value = env(name);
  if (!value) throw new Error(`${name} nao configurada`);
  return value;
};
const clean = (value) => String(value ?? '').replace(/[\r\n]+/g, ' ').trim();
const digits = (value) => String(value ?? '').replace(/\D/g, '');
const moneyNumber = (value) => {
  let text = String(value ?? '').replace(/[^\d,.-]/g, '');
  if (text.includes(',') && text.includes('.')) text = text.replace(/\./g, '').replace(',', '.');
  else if (text.includes(',')) text = text.replace(',', '.');
  return Number(text);
};
const normalizedName = (value) => clean(value).toLocaleUpperCase('pt-BR')
  .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ');

if (env('SIMPLESVET_SALES_ENABLED', '0') !== '1') {
  console.log('VENDAS_SV desativadas (SIMPLESVET_SALES_ENABLED=0)');
  process.exit(0);
}

const queueUrl = required('SIMPLESVET_SALES_QUEUE_URL');
const apiToken = required('API_BEARER_TOKEN');
const batchSize = Math.max(1, Math.min(20, Number(env('SIMPLESVET_SALES_BATCH_SIZE', '5')) || 5));
let salesAccount = null;

async function queueRequest(body) {
  const response = await fetch(queueUrl, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-API-Key': apiToken,
    },
    body: JSON.stringify(body),
    signal: AbortSignal.timeout(40000),
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || result.ok !== true) {
    throw new Error(`Fila Lis-onTech HTTP ${response.status}: ${clean(result.error || 'resposta invalida')}`);
  }
  return result;
}

async function selectConfigured(locator, value) {
  if (!value) return;
  const tag = await locator.evaluate((element) => element.tagName.toLowerCase());
  if (tag === 'select') {
    try {
      await locator.selectOption({ value });
    } catch {
      await locator.selectOption({ label: value });
    }
    return;
  }
  await locator.fill(value);
  await locator.press('Enter').catch(() => undefined);
}

async function login(page) {
  await page.goto(required('SIMPLESVET_LOGIN_URL'), { waitUntil: 'commit', timeout: 90000 });
  const userSelector = env('SIMPLESVET_USERNAME_SELECTOR', 'input[name="usuario"], input[name="username"], input[type="email"]');
  const passwordSelector = env('SIMPLESVET_PASSWORD_SELECTOR', 'input[name="senha"], input[name="password"], input[type="password"]');
  await page.locator(userSelector).first().waitFor({ state: 'visible', timeout: 60000 });
  const username = clean(salesAccount?.username);
  if (!username) throw new Error('SIMPLESVET_SALES_USER nao configurada');
  await page.locator(userSelector).first().fill(username);
  const password = String(salesAccount?.password ?? '');
  if (!password) throw new Error('SIMPLESVET_SALES_PASSWORD nao configurada');
  await page.locator(passwordSelector).first().fill(password);
  const submitSelector = env('SIMPLESVET_SUBMIT_SELECTOR');
  if (submitSelector) await page.locator(submitSelector).first().click();
  else await page.getByRole('button', { name: /entrar|acessar|login/i }).first().click();
  await page.waitForURL((url) => !url.pathname.startsWith('/login/'), {
    waitUntil: 'domcontentloaded', timeout: 60000,
  });
  await selectUnit(page);
}

async function selectUnit(page) {
  const unitName = clean(salesAccount?.unit_name);
  if (!unitName) return;
  const configured = env('SIMPLESVET_SALES_UNIT_SELECTOR');
  if (configured) {
    const field = page.locator(configured).first();
    await field.waitFor({ state: 'visible', timeout: 15000 });
    const tag = await field.evaluate((element) => element.tagName.toLowerCase());
    if (tag === 'select') await field.selectOption({ label: unitName });
    else if (!(await field.innerText()).includes(unitName)) throw new Error(`Unidade nao confere: ${unitName}`);
    else await field.click();
    await page.waitForLoadState('domcontentloaded').catch(() => undefined);
    return;
  }
  const option = page.locator('select').filter({ has: page.locator(`option`, { hasText: unitName }) }).first();
  if (await option.count()) {
    await option.selectOption({ label: unitName });
    await page.waitForLoadState('domcontentloaded').catch(() => undefined);
    return;
  }
  const text = page.getByText(unitName, { exact: true }).first();
  if (await text.count()) {
    await text.click();
    await page.waitForLoadState('domcontentloaded').catch(() => undefined);
    return;
  }
  // Contas com uma unica unidade entram diretamente no dashboard.
  if (!page.url().includes('/login/')) return;
  throw new Error(`Unidade nao encontrada: ${unitName}`);
}

async function getVindiCustomer(customerId) {
  if (!customerId) throw new Error('Fatura paga sem customer_id');
  const base = required('VINDI_API_BASE').replace(/\/$/, '');
  const key = required('VINDI_API_KEY');
  const response = await fetch(`${base}/customers/${customerId}`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Basic ${Buffer.from(`${key}:`).toString('base64')}`,
    },
    signal: AbortSignal.timeout(40000),
  });
  if (!response.ok) throw new Error(`Vindi customer HTTP ${response.status}`);
  const json = await response.json();
  return json.customer ?? json;
}

function customerCpf(customer) {
  const values = [customer?.registry_code, customer?.cpf, customer?.document,
    customer?.document_number, customer?.metadata?.cpf, customer?.metadata?.document];
  return values.map(digits).find((value) => value.length === 11) || '';
}

function billFromJob(job) {
  return job?.source?.event?.data?.bill ?? {};
}

function itemMappingKey(item) {
  const productId = Number(item?.product?.id ?? item?.product_id ?? 0);
  const code = clean(item?.product?.code);
  const name = clean(item?.product?.name || item?.description || code);
  const identity = productId > 0 ? `id:${productId}` : (code ? `code:${normalizedName(code)}` : `name:${normalizedName(name)}`);
  return createHash('sha256').update(identity).digest('hex');
}

function billItems(bill, mappings) {
  const items = Array.isArray(bill?.bill_items) ? bill.bill_items : [];
  return items.map((item) => {
    const mapping = mappings?.[itemMappingKey(item)] ?? null;
    if (!mapping || mapping.mapping_status === 'pending') {
      throw new Error(`Produto Vindi sem conciliacao: ${clean(item?.product?.name || item?.description || 'sem nome')}`);
    }
    if (mapping.mapping_status === 'ignored') return null;
    const quantity = Math.max(1, Number(item?.quantity ?? 1) || 1);
    const total = Number(item?.amount ?? item?.pricing_schema?.price ?? 0) || 0;
    const name = clean(mapping.simplesvet_product_name || item?.product?.name || item?.description);
    return {
      key: clean(mapping.simplesvet_product_code || mapping.simplesvet_product_name),
      name,
      quantity,
      unitPrice: Number(total / quantity) || 0,
    };
  }).filter(Boolean);
}

async function locateCustomer(page, cpf, customerName) {
  const input = page.locator(required('SIMPLESVET_SALE_CUSTOMER_SELECTOR')).first();
  await input.waitFor({ state: 'visible', timeout: 20000 });
  const search = page.locator(required('SIMPLESVET_SALE_CUSTOMER_SEARCH_SELECTOR')).first();
  const rowsSelector = required('SIMPLESVET_SALE_CUSTOMER_RESULTS_SELECTOR');

  const closeDrawer = async () => {
    const close = page.locator('#pesquisa button.btn-white.sv-pop:visible').last();
    if (await close.count()) await close.evaluate((element) => element.click());
    await page.waitForTimeout(500);
  };
  const find = async (term, type) => {
    await input.fill(term);
    const drawer = page.locator('#pesquisa');
    if (!(await drawer.isVisible().catch(() => false))) {
      await page.locator('.blockUI.blockOverlay').waitFor({ state: 'hidden', timeout: 15000 }).catch(() => undefined);
      await search.click();
    }
    await drawer.waitFor({ state: 'visible', timeout: 15000 });
    const drawerSelector = type === 'cpf'
      ? env('SIMPLESVET_SALE_CUSTOMER_CPF_SELECTOR', '#pesquisa input#cpf:visible')
      : env('SIMPLESVET_SALE_CUSTOMER_NAME_SELECTOR', '#pesquisa input#nome:visible');
    const drawerInput = page.locator(drawerSelector).last();
    await drawerInput.waitFor({ state: 'visible', timeout: 15000 });
    await drawerInput.fill(term);
    await drawerInput.press('Enter');
    await page.waitForFunction(
      (selector) => document.querySelectorAll(selector).length > 0
        || document.body.innerText.includes('Nenhum cliente foi encontrado'),
      rowsSelector,
      { timeout: 20000 },
    );
    return page.locator(`${rowsSelector}:visible`);
  };

  let rows = await find(cpf, 'cpf');
  let count = await rows.count();
  if (count === 0 && customerName) {
    await closeDrawer();
    rows = await find(customerName, 'name');
    count = await rows.count();
  }
  if (count !== 1) throw new Error(`Cliente retornou ${count} resultados no SimplesVet; revisao manual necessaria`);
  const customerLink = rows.first().locator('a.item_pesquisa').first();
  if (await customerLink.count()) await customerLink.click();
  else await rows.first().click();
}

async function addItem(page, item) {
  const productInput = page.locator(required('SIMPLESVET_SALE_PRODUCT_SELECTOR')).first();
  await productInput.waitFor({ state: 'visible', timeout: 15000 });
  const quantitySelector = env('SIMPLESVET_SALE_QUANTITY_SELECTOR');
  if (quantitySelector) await page.locator(quantitySelector).first().fill(String(item.quantity));
  const rowsSelector = required('SIMPLESVET_SALE_PRODUCT_RESULTS_SELECTOR');
  const rows = page.locator(rowsSelector);
  const before = await rows.count();
  await productInput.fill(item.key);
  await productInput.press('Enter');
  await page.waitForFunction(
    ({ selector, previous, key }) => Array.from(document.querySelectorAll(selector))
      .slice(previous).some((row) => row.textContent.includes(key)),
    { selector: rowsSelector, previous: before, key: item.key },
    { timeout: 20000 },
  );
  const priceSelector = env('SIMPLESVET_SALE_PRICE_SELECTOR');
  if (priceSelector && item.unitPrice > 0) {
    await page.locator(priceSelector).last().fill(item.unitPrice.toFixed(2).replace('.', ','));
  }
  const addSelector = env('SIMPLESVET_SALE_ADD_ITEM_SELECTOR');
  if (addSelector) await page.locator(addSelector).last().click();
}

async function createAndReceiveSale(page, job) {
  const bill = billFromJob(job);
  const customer = await getVindiCustomer(Number(job.customer_id || bill?.customer?.id || 0));
  const cpf = customerCpf(customer);
  if (!cpf) throw new Error('CPF ausente ou invalido na Vindi');
  const items = billItems(bill, job.product_mappings);
  if (!items.length) throw new Error('Fatura Vindi sem produtos habilitados na conciliacao');

  await page.goto(required('SIMPLESVET_SALES_URL'), { waitUntil: 'domcontentloaded', timeout: 60000 });
  await locateCustomer(page, cpf, clean(customer?.name || job.customer_name));
  for (const item of items) await addItem(page, item);

  const referenceSelector = required('SIMPLESVET_SALE_REFERENCE_SELECTOR');
  await page.locator(referenceSelector).first().fill(`VINDI #${job.bill_id}`);

  const expectedTotal = items.reduce((total, item) => total + (item.unitPrice * item.quantity), 0);
  const totalField = page.locator(required('SIMPLESVET_SALE_TOTAL_SELECTOR')).first();
  await totalField.waitFor({ state: 'visible', timeout: 15000 });
  const displayedTotal = moneyNumber(await totalField.textContent());
  if (!Number.isFinite(displayedTotal) || Math.abs(displayedTotal - expectedTotal) > 0.01) {
    throw new Error(`Total divergente: Vindi R$ ${expectedTotal.toFixed(2)}; SimplesVet R$ ${Number.isFinite(displayedTotal) ? displayedTotal.toFixed(2) : 'invalido'}`);
  }

  let saleCreated = false;
  try {
    await page.locator(required('SIMPLESVET_SALE_SAVE_RECEIVE_SELECTOR')).first().click();
    saleCreated = true;
    const payment = page.locator(required('SIMPLESVET_SALE_PAYMENT_SELECTOR')).first();
    await payment.waitFor({ state: 'visible', timeout: 20000 });
    const idSelector = required('SIMPLESVET_SALE_ID_SELECTOR');
    const idField = page.locator(idSelector).first();
    await idField.waitFor({ state: 'attached', timeout: 15000 });
    await page.waitForFunction((selector) => {
      const field = document.querySelector(selector);
      return Boolean(field && String('value' in field ? field.value : field.textContent).trim());
    }, idSelector, { timeout: 15000 });
    const saleId = clean(await idField.inputValue().catch(() => idField.textContent()));
    if (!saleId) throw new Error('SimplesVet criou a venda sem informar o codigo');
    await queueRequest({
      action: 'checkpoint', id: job.id, lease_token: job.lease_token,
      simplesvet_sale_id: saleId, result: { stage: 'sale_created', reference: `VINDI #${job.bill_id}` },
    });
    const cashierSelector = env('SIMPLESVET_SALE_CASHIER_SELECTOR');
    if (cashierSelector) {
      const cashier = page.locator(cashierSelector).first();
      const configuredCashier = env('SIMPLESVET_SALE_CASHIER_VALUE');
      if (configuredCashier) {
        await selectConfigured(cashier, configuredCashier);
      } else {
        const available = await cashier.locator('option:not([disabled])').evaluateAll((options) => options
          .map((option) => ({ value: option.value, text: option.textContent.trim() }))
          .filter((option) => option.value && !/selecione/i.test(option.text)));
        if (!available.length) throw new Error('Nenhum caixa aberto no SimplesVet');
        await cashier.selectOption(available[0].value);
      }
    }
    await selectConfigured(payment, env('SIMPLESVET_SALE_PAYMENT_VALUE', 'Vindi'));
    const amountSelector = env('SIMPLESVET_SALE_RECEIVED_AMOUNT_SELECTOR');
    if (amountSelector) {
      const amount = items.reduce((total, item) => total + (item.unitPrice * item.quantity), 0);
      if (amount > 0) await page.locator(amountSelector).first().fill(amount.toFixed(2).replace('.', ','));
    }
    await page.locator(required('SIMPLESVET_SALE_CONFIRM_SELECTOR')).first().click();
    const success = page.locator(required('SIMPLESVET_SALE_SUCCESS_SELECTOR')).first();
    await success.waitFor({ state: 'visible', timeout: 30000 });
    return { saleId, items, confirmation: clean(await success.textContent()) };
  } catch (error) {
    if (saleCreated) error.manualReview = true;
    throw error;
  }
}

let browser;
let page;
let exitCode = 0;
let processed = 0;
try {
  const remoteConfig = await queueRequest({ action: 'config' });
  if (!remoteConfig.configured || !remoteConfig.settings) {
    throw new Error('Conta exclusiva do SimplesVet nao configurada no painel Lis-onTech');
  }
  salesAccount = remoteConfig.settings;
  for (let index = 0; index < batchSize; index++) {
    // Reserva apenas uma tarefa. As demais continuam na fila ate a venda atual terminar.
    const claimed = await queueRequest({ action: 'claim', limit: 1 });
    const job = Array.isArray(claimed.jobs) ? claimed.jobs[0] : null;
    if (!job) break;
    processed++;

    try {
      if (!page) {
        browser = await chromium.launch({ headless: env('SIMPLESVET_HEADLESS', '1') !== '0' });
        const context = await browser.newContext({ locale: 'pt-BR' });
        page = await context.newPage();
        await login(page);
      }
      const result = await createAndReceiveSale(page, job);
      await queueRequest({
        action: 'complete', id: job.id, lease_token: job.lease_token,
        simplesvet_sale_id: result.saleId, result,
      });
      console.log(`VENDA_SV_OK bill_id=${job.bill_id} sale_id=${result.saleId || '-'}`);
    } catch (error) {
      exitCode = 1;
      const message = clean(error?.message || error).slice(0, 8000);
      await queueRequest({
        action: 'fail', id: job.id, lease_token: job.lease_token,
        error: message, manual_review: error?.manualReview === true,
      }).catch((queueError) => console.error(`VENDA_SV_ACK_ERRO ${clean(queueError?.message || queueError)}`));
      console.error(`VENDA_SV_ERRO bill_id=${job.bill_id} ${message}`);
      if (page?.url().includes('/login/')) await login(page).catch(() => undefined);
    }
  }
  if (processed === 0) console.log('VENDAS_SV fila vazia');
} finally {
  if (browser) await browser.close();
}
process.exitCode = exitCode;
