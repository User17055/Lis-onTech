<?php
// public_html/painel/pages/detalhes.php
declare(strict_types=1);

if (!isset($_GET['id']) || trim((string)$_GET['id']) === '') {
  die("<h3 style='font-family:Poppins, sans-serif;'>Erro: Run ID obrigatório! <a href='/painel/index.php?pagina=index'>Voltar</a></h3>");
}

$run_id = (string)$_GET['id'];

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>

<!-- Prism (Syntax Highlight) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-json.min.js"></script>

<style>
  /* ✅ tudo com prefixo rd- pra não brigar com o layout */
  .rd-wrap{
    max-width: 980px;
    margin: 10px auto 40px auto;
    padding: 0 10px;
    font-family: 'Nunito', sans-serif;
  }

  .rd-topbar{
    background:#fff;
    border:1px solid #eef2f6;
    border-radius:18px;
    padding:18px 18px;
    display:flex;
    align-items:center;
    gap:14px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,.05);
  }

  .rd-back{
    width:42px;
    height:42px;
    border-radius:50%;
    background:#f4f7fa;
    display:flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    color:#1e293b;
    font-size:16px;
    transition:.2s;
    flex:0 0 auto;
  }

  .rd-back:hover{
    background:#e2e8f0;
    transform:translateX(-3px);
  }

  .rd-title{
    font-size:18px;
    font-weight:900;
    color:#1e293b;
    display:flex;
    align-items:center;
    gap:10px;
    min-width:0;
    flex:1;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .rd-title i{ color:#3b82f6; }

  .rd-loader{
    text-align:center;
    padding:50px;
    color:#64748b;
    font-weight:800;
  }

  .rd-card{
    background:#fff;
    border:2px solid #eef2f6;
    border-radius:20px;
    padding:25px;
    margin-top:18px;
    box-shadow:0 4px 6px -1px rgba(0,0,0,.05);
  }

  .rd-card-title{
    font-size:16px;
    font-weight:900;
    margin-bottom:14px;
    color:#3b82f6;
    display:flex;
    align-items:center;
    gap:8px;
  }

  .rd-row-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
  }
  @media(max-width:780px){
    .rd-row-grid{grid-template-columns:1fr;}
  }

  .rd-label{
    color:#64748b;
    font-size:12px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.03em;
  }
  .rd-value{
    font-weight:900;
    font-size:16px;
    margin-top:4px;
    color:#1e293b;
    word-break:break-word;
  }

  .rd-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:6px 14px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
    text-transform:uppercase;
    border:1px solid transparent;
  }

  .rd-ok{background:#d1fae5;color:#065f46;border-color:#a7f3d0;}
  .rd-info{background:#dbeafe;color:#1e40af;border-color:#bfdbfe;}
  .rd-warn{background:#ffedd5;color:#9a3412;border-color:#fed7aa;}
  .rd-danger{background:#fee2e2;color:#991b1b;border-color:#fecaca;}

  .rd-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;}
  .rd-tab{
    border:2px solid #eef2f6;
    background:#fff;
    border-radius:999px;
    padding:8px 14px;
    cursor:pointer;
    font-weight:900;
    color:#64748b;
    transition:.15s;
  }
  .rd-tab.active{
    border-color:#cfe0ff;
    color:#3b82f6;
    background:#f8fbff;
  }
  .rd-tab:hover{transform:translateY(-1px);}

  .rd-searchbar{
    display:flex;
    gap:10px;
    align-items:center;
    margin-top:10px;
    border:2px solid #eef2f6;
    border-radius:14px;
    padding:10px 12px;
    background:#fff;
  }
  .rd-searchbar input{
    border:none;
    outline:none;
    width:100%;
    font-size:14px;
    font-family:'Nunito',sans-serif;
    font-weight:700;
  }

  .rd-field-list{
    margin-top:12px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
  }
  @media(max-width:780px){
    .rd-field-list{grid-template-columns:1fr;}
  }

  .rd-field{
    border:2px solid #eef2f6;
    border-radius:14px;
    padding:10px 12px;
    background:#fbfdff;
  }
  .rd-field .k{
    font-size:12px;
    color:#64748b;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.02em;
  }
  .rd-field .v{
    margin-top:6px;
    font-weight:800;
    color:#0f172a;
    word-break:break-word;
    white-space:pre-wrap;
  }

  .rd-log-container{
    background:#0b1220;
    color:#cbd5e1;
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size:12.5px;
    padding:14px;
    border-radius:14px;
    max-height:360px;
    overflow:auto;
    border:1px solid #1f2a44;
    margin-top:12px;
  }

  .rd-log-line{
    padding:10px 0;
    border-bottom:1px solid #1f2a44;
    display:grid;
    grid-template-columns:140px 90px 160px 1fr;
    gap:12px;
    align-items:start;
  }
  .rd-log-line:last-child{border-bottom:none;}
  .rd-log-time{color:#94a3b8;}
  .rd-log-level{font-weight:900;text-transform:uppercase;}
  .rd-log-step{color:#93c5fd;font-weight:800;}
  .rd-log-msg{color:#e2e8f0;}

  .rd-code-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-top:12px;
    margin-bottom:8px;
  }
  .rd-btn-copy{
    border:2px solid #eef2f6;
    background:#fff;
    border-radius:12px;
    padding:8px 12px;
    cursor:pointer;
    font-weight:900;
    color:#0f172a;
    transition:.15s;
    display:inline-flex;
    gap:8px;
    align-items:center;
  }
  .rd-btn-copy:hover{
    transform:translateY(-1px);
    border-color:#cfe0ff;
    color:#3b82f6;
  }

  pre[class*="language-"]{
    margin:0;
    border-radius:12px;
    border:1px solid #e2e8f0;
    background:#f8fafc;
    font-size:12px;
    overflow:auto;
    max-height:520px;
  }

  .rd-muted{color:#64748b;font-weight:800;}
  .rd-link{color:#3b82f6;font-weight:900;text-decoration:none;}
  .rd-link:hover{text-decoration:underline;}
</style>

<div class="rd-wrap">

  <!-- Topo -->
  <div class="rd-topbar">
    <a class="rd-back" href="/painel/index.php?pagina=index" title="Voltar">
      <i class="fa-solid fa-arrow-left"></i>
    </a>

    <div class="rd-title">
      <i class="fa-regular fa-file-code"></i>
      Detalhes da Execução # <span id="rdDisplayId"><?= h($run_id) ?></span>
    </div>
  </div>

  <!-- Loader -->
  <div id="rdLoading" class="rd-loader">
    <i class="fa-solid fa-spinner fa-spin" style="font-size:30px;color:#3b82f6;"></i><br><br>
    Carregando informações...
  </div>

  <!-- Conteúdo -->
  <div id="rdContent" style="display:none;">

    <div class="rd-card">
      <div class="rd-card-title"><i class="fa-solid fa-circle-info"></i> Resumo</div>

      <div class="rd-row-grid">
        <div>
          <div class="rd-label">Cliente</div>
          <div class="rd-value" id="rdClientName">--</div>
        </div>

        <div>
          <div class="rd-label">Status</div>
          <div class="rd-value" id="rdStatusBadge">--</div>
        </div>

        <div>
          <div class="rd-label">Event Type</div>
          <div class="rd-value" id="rdEventType">--</div>
        </div>

        <div>
          <div class="rd-label">Bill</div>
          <div class="rd-value" id="rdBillInfo">--</div>
        </div>
      </div>

      <div style="margin-top:14px;" class="rd-muted">
        Criado: <span id="rdCreatedAt">--</span> • Atualizado: <span id="rdUpdatedAt">--</span>
      </div>
    </div>

    <div class="rd-card" id="rdErrorCard" style="display:none;">
      <div class="rd-card-title"><i class="fa-solid fa-triangle-exclamation"></i> Erro</div>
      <div id="rdErrorBox"></div>
    </div>

    <div class="rd-card">
      <div class="rd-card-title"><i class="fa-solid fa-layer-group"></i> Dados</div>

      <div class="rd-tabs">
        <button class="rd-tab active" data-tab="input"><i class="fa-solid fa-right-to-bracket"></i> Entrada</button>
        <button class="rd-tab" data-tab="output"><i class="fa-solid fa-right-from-bracket"></i> Saída</button>
        <button class="rd-tab" data-tab="logs"><i class="fa-solid fa-terminal"></i> Logs</button>
        <button class="rd-tab" data-tab="json"><i class="fa-solid fa-code"></i> JSON</button>
      </div>

      <div class="rd-searchbar">
        <i class="fa-solid fa-magnifying-glass" style="color:#64748b"></i>
        <input id="rdSearchInput" placeholder="Buscar informação (ex.: phone, nome, status...)" />
      </div>

      <div id="rdTab_input" class="rd-tabpane">
        <div class="rd-muted" style="margin-top:12px;">Campos de entrada (o que chegou pro fluxo).</div>
        <div id="rdInputFields" class="rd-field-list"></div>
      </div>

      <div id="rdTab_output" class="rd-tabpane" style="display:none;">
        <div class="rd-muted" style="margin-top:12px;">Campos de saída (respostas/resultados do processamento).</div>
        <div id="rdOutputFields" class="rd-field-list"></div>
      </div>

      <div id="rdTab_logs" class="rd-tabpane" style="display:none;">
        <div class="rd-muted" style="margin-top:12px;">Linha do tempo completa por etapa.</div>
        <div class="rd-log-container" id="rdLogBox"></div>
      </div>

      <div id="rdTab_json" class="rd-tabpane" style="display:none;">
        <div class="rd-muted" style="margin-top:12px;">Run completa (dados técnicos).</div>

        <div class="rd-code-header">
          <div class="rd-muted">Visualização em código (JSON)</div>
          <div>
            <button class="rd-btn-copy" id="rdBtnCopyJson"><i class="fa-regular fa-copy"></i> Copiar</button>
          </div>
        </div>

        <pre class="language-json"><code id="rdJsonBox" class="language-json"></code></pre>
      </div>

    </div>
  </div>
</div>

<script>
  const runId = "<?= h($run_id) ?>";

  const stepNameMap = {
    validate: 'Validação de dados',
    facebook_send: 'Envio ao Facebook',
    facebook: 'Facebook',
    webhook: 'Webhook',
    vindi: 'Vindi',
    build_payload: 'Montagem do payload',
    whatsapp_send: 'Envio WhatsApp',
  };

  function pillForStatus(status) {
    const st = (status || '').toLowerCase();
    if (st === 'processed' || st === 'success' || st === 'ok')
      return `<span class="rd-pill rd-ok"><i class="fa-solid fa-check"></i> ${st}</span>`;
    if (st === 'processing' || st === 'running')
      return `<span class="rd-pill rd-info"><i class="fa-solid fa-spinner fa-spin"></i> ${st}</span>`;
    if (st === 'warning')
      return `<span class="rd-pill rd-warn"><i class="fa-solid fa-triangle-exclamation"></i> ${st}</span>`;
    if (st === 'error' || st === 'failed' || st === 'canceled')
      return `<span class="rd-pill rd-danger"><i class="fa-solid fa-xmark"></i> ${st}</span>`;
    return `<span class="rd-pill rd-info"><i class="fa-regular fa-circle"></i> ${st || 'unknown'}</span>`;
  }

  function safeJsonParse(v) {
    if (!v) return null;
    if (typeof v === 'object') return v;
    try { return JSON.parse(v); } catch { return null; }
  }

  function flatten(obj, prefix = '') {
    const out = [];
    const isObj = (x) => x && typeof x === 'object' && !Array.isArray(x);

    if (obj === null || obj === undefined) return out;

    if (!isObj(obj) && !Array.isArray(obj)) {
      out.push({ key: prefix || 'value', value: String(obj) });
      return out;
    }

    if (Array.isArray(obj)) {
      out.push({ key: prefix || 'array', value: JSON.stringify(obj, null, 2) });
      return out;
    }

    for (const [k, v] of Object.entries(obj)) {
      const nextKey = prefix ? `${prefix}.${k}` : k;
      if (v === null || v === undefined) {
        out.push({ key: nextKey, value: '' });
      } else if (Array.isArray(v)) {
        out.push({ key: nextKey, value: JSON.stringify(v, null, 2) });
      } else if (typeof v === 'object') {
        out.push(...flatten(v, nextKey));
      } else {
        out.push({ key: nextKey, value: String(v) });
      }
    }
    return out;
  }

  function escapeHtml(str) {
    const s = String(str ?? '');
    return s
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function renderFields(containerId, items, q) {
    const el = document.getElementById(containerId);
    const query = (q || '').trim().toLowerCase();

    const filtered = !query ? items : items.filter(it =>
      it.key.toLowerCase().includes(query) || (it.value || '').toLowerCase().includes(query)
    );

    if (!filtered.length) {
      el.innerHTML = `<div class="rd-muted" style="margin-top:12px;">Nada encontrado.</div>`;
      return;
    }

    el.innerHTML = filtered.map(it => `
      <div class="rd-field">
        <div class="k">${escapeHtml(it.key)}</div>
        <div class="v">${escapeHtml(it.value)}</div>
      </div>
    `).join('');
  }

  function renderLogs(logs, q) {
    const box = document.getElementById('rdLogBox');
    const query = (q || '').trim().toLowerCase();

    const filtered = !query ? logs : logs.filter(l => {
      const hay = [
        l.created_at, l.level, l.step, l.message,
        l.context ? JSON.stringify(l.context) : ''
      ].join(' ').toLowerCase();
      return hay.includes(query);
    });

    if (!filtered.length) {
      box.innerHTML = `<div class="rd-muted">Nenhum log encontrado.</div>`;
      return;
    }

    box.innerHTML = filtered.map(l => {
      const lvl = (l.level || '').toUpperCase();
      const step = l.step ? (stepNameMap[l.step] || l.step) : '--';
      return `
        <div class="rd-log-line">
          <div class="rd-log-time">${escapeHtml(l.created_at || '')}</div>
          <div class="rd-log-level">${escapeHtml(lvl)}</div>
          <div class="rd-log-step">${escapeHtml(step)}</div>
          <div class="rd-log-msg">${escapeHtml(l.message || '')}</div>
        </div>
      `;
    }).join('');
  }

  function pickInputOutput(run, logs) {
    const meta = safeJsonParse(run.meta_http);

    let input = null;
    let output = null;

    if (meta && typeof meta === 'object') {
      input  = meta.input  ?? meta.request ?? meta.payload ?? meta.in ?? null;
      output = meta.output ?? meta.response ?? meta.out ?? null;
    }

    if (!input) {
      const err = logs.find(l => (l.level || '').toLowerCase() === 'error' && l.context);
      input = err?.context?.received ?? err?.context?.request ?? null;
    }
    if (!output) {
      const err = logs.find(l => (l.level || '').toLowerCase() === 'error' && l.context);
      output = err?.context?.response ?? null;
    }

    return { input, output, meta };
  }

  function renderErrorCard(run, logs) {
    const errorLog = logs.find(l => (l.level || '').toLowerCase() === 'error') || null;
    const errorMsg = run.error_message || errorLog?.message || null;
    if (!errorMsg) return;

    const stepKey = errorLog?.step || null;
    const stepLabel = stepKey ? (stepNameMap[stepKey] || stepKey) : 'Etapa desconhecida';

    const ctx = errorLog?.context || {};
    let extra = '';

    if (ctx?.missing_fields?.length) {
      extra += `<div style="margin-top:10px;"><b>Campos faltando:</b> ${escapeHtml(ctx.missing_fields.join(', '))}</div>`;
    }
    if (ctx?.http_status) {
      extra += `<div style="margin-top:8px;"><b>HTTP:</b> ${escapeHtml(ctx.http_status)}</div>`;
    }
    if (ctx?.endpoint) {
      extra += `<div style="margin-top:8px;"><b>Endpoint:</b> ${escapeHtml(ctx.endpoint)}</div>`;
    }

    document.getElementById('rdErrorBox').innerHTML = `
      <div style="background:#fee2e2;border:1px solid #fecaca;padding:14px;border-radius:14px;">
        <div style="font-weight:1000;color:#991b1b;font-size:14px;">
          ❌ Erro em: ${escapeHtml(stepLabel)}
        </div>
        <div style="margin-top:6px;color:#7f1d1d;font-weight:800;">
          ${escapeHtml(errorMsg)}
        </div>
        ${extra}
      </div>
    `;

    document.getElementById('rdErrorCard').style.display = 'block';
  }

  function setupTabs() {
    const tabs = document.querySelectorAll('.rd-tab');
    tabs.forEach(btn => {
      btn.addEventListener('click', () => {
        tabs.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const target = btn.dataset.tab;
        document.querySelectorAll('.rd-tabpane').forEach(p => p.style.display = 'none');
        document.getElementById('rdTab_' + target).style.display = 'block';
      });
    });
  }

  function highlightJsonElement(el) {
    if (!el) return;
    if (window.Prism) Prism.highlightElement(el);
  }

  async function loadDetails() {
    try {
      // ✅ sempre absoluto (não quebra)
      const req = await fetch(`/painel/api/run_detail.php?run_id=${encodeURIComponent(runId)}`, { cache: "no-store" });
      const res = await req.json();

      if (!res.ok) throw new Error(res.error || "Erro desconhecido");

      const run = res.run || {};
      const logs = res.logs || [];

      document.getElementById('rdClientName').innerText = run.customer_name || '--';
      document.getElementById('rdStatusBadge').innerHTML = pillForStatus(run.status);
      document.getElementById('rdEventType').innerText = run.event_type || '--';
      document.getElementById('rdCreatedAt').innerText = run.created_at || '--';
      document.getElementById('rdUpdatedAt').innerText = run.updated_at || '--';

      if (run.bill_url) {
        document.getElementById('rdBillInfo').innerHTML =
          `<a class="rd-link" href="${escapeHtml(run.bill_url)}" target="_blank" rel="noopener">Abrir Bill #${escapeHtml(run.bill_id || '')}</a>`;
      } else {
        document.getElementById('rdBillInfo').innerText = run.bill_id ? `#${run.bill_id}` : '--';
      }

      renderErrorCard(run, logs);

      const { input, output, meta } = pickInputOutput(run, logs);
      const inputItems = input ? flatten(input) : [];
      const outputItems = output ? flatten(output) : [];

      const jsonEl = document.getElementById('rdJsonBox');
      const fullJson = { run, logs, meta_http_parsed: meta };
      jsonEl.textContent = JSON.stringify(fullJson, null, 2);
      highlightJsonElement(jsonEl);

      const btnCopyJson = document.getElementById('rdBtnCopyJson');
      btnCopyJson.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(JSON.stringify(fullJson, null, 2));
          btnCopyJson.innerHTML = `<i class="fa-solid fa-check"></i> Copiado`;
          setTimeout(() => btnCopyJson.innerHTML = `<i class="fa-regular fa-copy"></i> Copiar`, 1200);
        } catch {
          alert('Não foi possível copiar. (Em HTTP sem SSL o navegador pode bloquear)');
        }
      });

      renderFields('rdInputFields', inputItems, '');
      renderFields('rdOutputFields', outputItems, '');
      renderLogs(logs, '');

      const search = document.getElementById('rdSearchInput');
      search.addEventListener('input', () => {
        const q = search.value || '';
        renderFields('rdInputFields', inputItems, q);
        renderFields('rdOutputFields', outputItems, q);
        renderLogs(logs, q);
      });

      setupTabs();

      document.getElementById('rdLoading').style.display = 'none';
      document.getElementById('rdContent').style.display = 'block';

      if (!inputItems.length) {
        document.getElementById('rdInputFields').innerHTML =
          `<div class="rd-muted" style="margin-top:12px;">Sem dados de entrada (dica: salve JSON em meta_http como { "input": {...} }).</div>`;
      }
      if (!outputItems.length) {
        document.getElementById('rdOutputFields').innerHTML =
          `<div class="rd-muted" style="margin-top:12px;">Sem dados de saída (dica: salve JSON em meta_http como { "output": {...} }).</div>`;
      }

    } catch (error) {
      document.getElementById('rdLoading').innerHTML =
        `<span style="color:#64748b;font-weight:900;">Erro ao carregar: ${escapeHtml(error.message)}</span>`;
    }
  }

  loadDetails();
</script>
