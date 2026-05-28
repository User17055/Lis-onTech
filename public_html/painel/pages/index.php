<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }
?>
<div class="pa-wrap">

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap');

    /* ✅ Variáveis isoladas (não usa :root global) */
    .pa-wrap{
      --bg-body: #ffffff;
      --bg-panel: #f4f7fa;
      --primary: #38b6ff;
      --primary-hover:rgb(39, 121, 169);
      --text-main: #1e293b;
      --text-muted: #64748b;
      --border-color: #eef2f6;
      --green-bg: #d1fae5;
      --green-text: #065f46;
      --red-bg: #fee2e2;
      --red-text: #991b1b;
      --blue-bg: #dbeafe;
      --blue-text: #1e40af;
      --yellow-bg: #fef3c7;
      --yellow-text: #92400e;
      --radius-pill: 50px;
      --radius-card: 20px;
      --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
      --shadow-hover: 0 10px 15px -3px rgba(59, 130, 246, 0.15);

      /* ✅ Fonte só dentro do painel, não muda seu site inteiro */
      font-family: 'Nunito', sans-serif;
      color: var(--text-main);
    }

    /* ✅ NADA de body{} aqui! */

    .pa-header {
      background: #ffffff;
      border-bottom: 2px solid var(--border-color);
      padding: 0 40px;
      height: 70px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 50;
      border-radius: 16px;
      box-shadow: var(--shadow-soft);
      margin-bottom: 25px;
    }

    .brand {
      font-size: 22px;
      font-weight: 800;
      color: var(--text-main);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand i {
      color: var(--primary);
      font-size: 24px;
    }

    .status-bar {
      font-size: 13px;
      color: var(--text-main);
      background: var(--bg-panel);
      padding: 8px 20px;
      border-radius: var(--radius-pill);
      font-weight: 700;
      border: 2px solid var(--border-color);
      transition: all 0.5s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .status-bar.updated {
      background: var(--green-bg);
      color: var(--green-text);
      border-color: var(--green-bg);
      transform: scale(1.05);
    }

    .container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 25px;
    }

    .toolbar {
      background: #ffffff;
      border: 2px solid var(--border-color);
      border-radius: var(--radius-pill);
      padding: 12px 20px;
      display: flex;
      gap: 12px;
      align-items: center;
      box-shadow: var(--shadow-soft);
      margin-bottom: 35px;
      flex-wrap: wrap;
      transition: box-shadow 0.3s;
    }

    .toolbar:hover {
      box-shadow: var(--shadow-hover);
    }

    .form-control {
      background: var(--bg-panel);
      border: 2px solid transparent;
      color: var(--text-main);
      border-radius: var(--radius-pill);
      padding: 0 25px;
      height: 45px;
      font-family: inherit;
      font-size: 15px;
      font-weight: 600;
      outline: none;
      flex: 1;
      transition: all 0.2s;
    }

    .form-control:focus {
      background: #fff;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .btn-primary {
      background: var(--primary);
      color: white;
      border: none;
      padding: 0 28px;
      height: 45px;
      border-radius: var(--radius-pill);
      cursor: pointer;
      font-weight: 700;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
      transition: all 0.2s cubic-bezier(0.4, 0.0, 0.2, 1);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      background: var(--primary-hover);
    }

    .btn-primary:active {
      transform: translateY(1px) scale(0.95);
      box-shadow: none;
    }

    .toggle-wrapper {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 0 20px;
      border-right: 2px solid var(--border-color);
      margin-right: 5px;
    }

    .custom-check {
      accent-color: var(--primary);
      width: 18px;
      height: 18px;
      cursor: pointer;
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0 12px;
    }

    thead th {
      color: var(--text-muted);
      font-size: 13px;
      text-transform: uppercase;
      font-weight: 800;
      padding: 0 30px;
      text-align: left;
    }

    tbody tr {
      background: white;
      box-shadow: var(--shadow-soft);
      border: 2px solid var(--border-color);
      border-radius: var(--radius-card);
      transition: all 0.2s ease;
      cursor: pointer;
    }

    tbody tr:hover {
      transform: translateY(-3px) scale(1.005);
      box-shadow: var(--shadow-hover);
      border-color: #dbeafe;
    }

    tbody td {
      padding: 18px 30px;
      vertical-align: middle;
      border-top: 2px solid var(--border-color);
      border-bottom: 2px solid var(--border-color);
    }

    tbody td:first-child {
      border-top-left-radius: var(--radius-card);
      border-bottom-left-radius: var(--radius-card);
      border-left: 2px solid var(--border-color);
    }

    tbody td:last-child {
      border-top-right-radius: var(--radius-card);
      border-bottom-right-radius: var(--radius-card);
      border-right: 2px solid var(--border-color);
    }

    .customer-name {
      font-weight: 800;
      font-size: 16px;
      color: var(--text-main);
      display: block;
    }

    .bill-id {
      font-size: 13px;
      color: var(--primary);
      font-weight: 700;
      margin-top: 4px;
      display: inline-block;
      background: #eff6ff;
      padding: 2px 8px;
      border-radius: 10px;
    }

    .badge {
      min-height: 30px;
      padding: 0 12px;
      border-radius: var(--radius-pill);
      font-family: inherit;
      font-weight: 800;
      font-size: 12px;
      line-height: 1;
      text-transform: uppercase;
      letter-spacing: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      white-space: nowrap;
    }

    .badge i {
      font-size: 12px;
      line-height: 1;
    }

    .b-success { background: #e8f8ef; color: #059669; }
    .b-error   { background: #fde8e8; color: #dc2626; }
    .b-process { background: #e8f1ff; color: #2563eb; }
    .b-paid    { background: #d9f8f7; color: #0891b2; }
    .b-pending { background: #fff4d6; color: #d97706; }

    .btn-icon {
      color: #94a3b8;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: 0.2s;
      text-decoration: none;
      border: 2px solid transparent;
    }

    .btn-icon:hover {
      background: #f1f5f9;
      color: var(--primary);
      border-color: #dbeafe;
    }

    .spinner {
      width: 30px;
      height: 30px;
      border: 4px solid #e2e8f0;
      border-top-color: var(--primary);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin: 0 auto;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

@keyframes pulse-dot {
  0% { opacity: 0.5; transform: scale(1); }
  50% { opacity: 1; transform: scale(1.3); }
  100% { opacity: 0.5; transform: scale(1); }
}

@keyframes toastIn {
  0% { opacity: 0; transform: translateY(18px) scale(.96); }
  65% { opacity: 1; transform: translateY(-3px) scale(1.01); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes toastBar {
  from { transform: scaleX(1); }
  to { transform: scaleX(0); }
}

    .live-dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      background: var(--primary);
      border-radius: 50%;
      animation: pulse-dot 1.5s infinite;
    }

    .pager{
  display:flex;
  justify-content:center;
  align-items:center;
  gap:8px;
  flex-wrap:wrap;
  margin: 18px 0 10px;
}

.page-btn{
  background:#ffffff;
  border:2px solid var(--border-color);
  color: var(--text-main);
  border-radius: 999px;
  padding: 8px 14px;
  font-weight: 800;
  cursor:pointer;
  box-shadow: var(--shadow-soft);
  transition: .2s;
  font-family: 'Nunito', sans-serif;
}

.page-btn:hover{
  transform: translateY(-1px);
  border-color:#dbeafe;
  color: var(--primary);
}

.page-btn.active{
  background: var(--primary);
  color:#fff;
  border-color: var(--primary);
}

.page-btn:disabled{
  opacity:.45;
  cursor:not-allowed;
  transform:none;
}

.page-info{
  color: var(--text-muted);
  font-weight:700;
  padding: 8px 12px;
}

.pa-toast {
  position: fixed;
  right: 22px;
  bottom: 42px;
  min-width: min(360px, calc(100vw - 44px));
  max-width: 420px;
  display: none;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 18px;
  background: #ffffff;
  border: 2px solid #bbf7d0;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .18);
  z-index: 10000;
  overflow: hidden;
  font-family: 'Fredoka', 'Nunito', sans-serif;
}

.pa-toast.show {
  display: flex;
  animation: toastIn .42s cubic-bezier(.2, .9, .22, 1.2);
}

.pa-toast-icon {
  width: 38px;
  height: 38px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  color: #047857;
  background: #d1fae5;
}

.pa-toast-title {
  font-size: 16px;
  color: #0f172a;
  font-weight: 700;
  line-height: 1.15;
  letter-spacing: 0;
}

.pa-toast-sub {
  margin-top: 3px;
  color: #64748b;
  font-weight: 500;
  font-size: 13px;
  line-height: 1.25;
  letter-spacing: 0;
}

.pa-toast::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 4px;
  background: #22c55e;
  transform-origin: left center;
  animation: toastBar 6s linear forwards;
}

@media (max-width: 760px) {
  .pa-wrap .pa-header {
    height: auto;
    min-height: 62px;
    padding: 14px 16px;
    border-radius: 12px;
    align-items: flex-start;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
  }

  .pa-wrap .brand {
    font-size: 19px;
  }

  .pa-wrap .status-bar {
    width: 100%;
    justify-content: center;
    box-sizing: border-box;
    padding: 8px 12px;
  }

  .pa-wrap .container {
    width: 100%;
    max-width: 100%;
    padding: 0;
    overflow-x: hidden;
    box-sizing: border-box;
  }

  .pa-wrap .toolbar {
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 18px;
    align-items: stretch;
    flex-direction: column;
  }

  .pa-wrap .toolbar > div,
  .pa-wrap .toolbar select,
  .pa-wrap .toolbar button {
    width: 100%;
    max-width: 100% !important;
    box-sizing: border-box;
  }

  .pa-wrap .toggle-wrapper {
    justify-content: space-between;
    min-height: 42px;
    padding: 0;
    margin: 0;
    border-right: 0;
  }

  .pa-wrap .btn-primary {
    justify-content: center;
  }

  .pa-wrap table {
    min-width: 720px;
  }

  .pa-wrap .container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .pa-wrap thead th {
    padding: 0 16px;
  }

  .pa-wrap tbody td {
    padding: 14px 16px;
  }

  .pa-wrap tbody tr:hover {
    transform: none;
  }

  .pa-toast {
    right: 12px;
    bottom: 12px;
    min-width: auto;
    width: calc(100vw - 24px);
    border-radius: 12px;
  }
}

@media (max-width: 620px) {
  .pa-wrap .container {
    overflow-x: visible;
  }

  .pa-wrap table,
  .pa-wrap thead,
  .pa-wrap tbody,
  .pa-wrap tr,
  .pa-wrap td {
    display: block;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
  }

  .pa-wrap table {
    border-spacing: 0;
  }

  .pa-wrap thead {
    display: none;
  }

  .pa-wrap tbody tr {
    margin-bottom: 12px;
    padding: 14px;
    border: 2px solid var(--border-color);
    border-radius: 14px;
  }

  .pa-wrap tbody td,
  .pa-wrap tbody td:first-child,
  .pa-wrap tbody td:last-child {
    border: 0;
    padding: 7px 0;
    border-radius: 0;
  }

  .pa-wrap tbody td:first-child {
    display: none;
  }

  .pa-wrap tbody td:nth-child(3)::before,
  .pa-wrap tbody td:nth-child(4)::before {
    display: block;
    margin-bottom: 6px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
  }

  .pa-wrap tbody td:nth-child(3)::before {
    content: "Status";
  }

  .pa-wrap tbody td:nth-child(4)::before {
    content: "Detalhes";
  }

  .pa-wrap tbody td:last-child {
    text-align: left !important;
  }

  .pa-wrap tbody td:last-child > div {
    justify-content: space-between !important;
    gap: 10px !important;
  }
}

  </style>

  <div class="pa-header">
    <div class="brand">
      <i class="fa-homes"></i> Inicio
    </div>
    <div class="status-bar" id="statusBar">
      <span class="live-dot" id="liveIndicator"></span>
      <span id="statusText">Aguardando...</span>
    </div>
  </div>

  <div class="container">
    <div class="toolbar">
      <div style="flex: 2; min-width: 200px; position: relative;">
        <i class="fa-solid fa-search"
           style="color: var(--text-muted); position: absolute; left: 20px; top: 15px; font-size: 15px;"></i>
        <input id="q" class="form-control" style="padding-left: 45px;" placeholder="Buscar cliente ou ID...">
      </div>

      <select id="status" class="form-control" style="cursor:pointer; flex: 1; max-width: 200px;">
        <option value="">Todos os Status</option>
        <option value="processed">Enviados</option>
        <option value="processing">Processando</option>
        <option value="error">Erros</option>
        <option value="paid">J&aacute; pago</option>
        <option value="not_sent">Não Enviado</option>
      </select>

      <div class="toggle-wrapper">
        <input type="checkbox" id="auto" checked class="custom-check">
        <label for="auto" style="font-size:14px; font-weight:700; color:var(--text-muted); cursor:pointer;">
          Auto-refresh
        </label>
      </div>

      <button id="btnReload" class="btn-primary">
        Atualizar <i class="fa-solid fa-rotate"></i>
      </button>
    </div>

    <table>
      <thead>
      <tr>
        <th style="width:20px"></th>
        <th>Cliente / Documento</th>
        <th>Status</th>
        <th style="text-align: right;">Detalhes</th>
      </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>

    <div id="pager" class="pager"></div>

  </div>

  <div class="pa-toast" id="pageToast" role="status" aria-live="polite"></div>

  <script>
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[m]));
  }

  function badge(status) {
    const normalized = String(status || '').trim().toLowerCase();
    const map = {
      processed:  { label: 'Enviado',   cls: 'b-success', icon: 'paper-plane' },
      processing: { label: 'Gerando',   cls: 'b-process', icon: 'spinner fa-spin' },
      paid:       { label: 'J&aacute; pago', cls: 'b-paid',    icon: 'circle-check' },
      error:      { label: 'Falhou',    cls: 'b-error',   icon: 'circle-xmark' },
      not_sent:   { label: 'Pendente',  cls: 'b-pending', icon: 'clock' }
    };
    const info = map[normalized] || { label: normalized || 'Pendente', cls: 'b-pending', icon: 'clock' };
    return `<span class="badge ${info.cls}"><i class="fa-solid fa-${info.icon}"></i> ${info.label}</span>`;
  }

  function showLoading() {
    document.getElementById("tbody").innerHTML = `
      <tr style="cursor: default; pointer-events: none;">
        <td colspan="4" style="text-align:center; padding: 40px; background: transparent; box-shadow:none; border:none;">
          <div class="spinner"></div>
          <div style="color:var(--text-main); font-weight:700; font-size:14px; margin-top:15px;">
            Carregando dados...
          </div>
        </td>
      </tr>
    `;
  }

  function formatDateTimeBr(value) {
    const s = String(value ?? '').trim();
    if (!s) return '-';

    const dt = new Date(s.replace(' ', 'T'));
    if (isNaN(dt.getTime())) return esc(s);

    return dt.toLocaleString('pt-BR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  function buildPager(totalPages, page) {
    const pager = document.getElementById('pager');
    if (!pager) return;

    if (!totalPages || totalPages <= 1) {
      pager.innerHTML = '';
      return;
    }

    const parts = [];
    const addBtn = (p, label, disabled = false, active = false) => {
      parts.push(
        `<button class="page-btn ${active ? 'active' : ''}" data-page="${p}" ${disabled ? 'disabled' : ''}>${label}</button>`
      );
    };

    addBtn(Math.max(1, page - 1), '‹', page === 1);

    const windowSize = 2;
    const start = Math.max(1, page - windowSize);
    const end = Math.min(totalPages, page + windowSize);

    if (start > 1) {
      addBtn(1, '1', false, page === 1);
      if (start > 2) parts.push(`<span class="page-info">...</span>`);
    }

    for (let p = start; p <= end; p++) {
      addBtn(p, String(p), false, p === page);
    }

    if (end < totalPages) {
      if (end < totalPages - 1) parts.push(`<span class="page-info">...</span>`);
      addBtn(totalPages, String(totalPages), false, page === totalPages);
    }

    addBtn(Math.min(totalPages, page + 1), '›', page === totalPages);

    parts.push(`<span class="page-info">Página ${page} de ${totalPages}</span>`);

    pager.innerHTML = parts.join('');
  }

  const initialParams = new URLSearchParams(location.search);
  const initialPage = parseInt(initialParams.get('page') || '1', 10);

  let previousDataHash = null;
  let previousSignature = null;
  let activeRunsController = null;
  let currentPage = Number.isFinite(initialPage) && initialPage > 0 ? initialPage : 1;
  const limit = 50;
  const autoRefreshMs = 6000;

  document.getElementById("q").value = initialParams.get("q") || "";
  document.getElementById("status").value = initialParams.get("status") || "";

  function showPageToast(title, sub) {
    const toast = document.getElementById("pageToast");
    if (!toast) return;
    toast.innerHTML = `
      <span class="pa-toast-icon"><i class="fa-solid fa-check"></i></span>
      <span>
        <span class="pa-toast-title">${esc(title)}</span>
        <span class="pa-toast-sub">${esc(sub || '')}</span>
      </span>
    `;
    toast.classList.remove("show");
    void toast.offsetWidth;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 6000);
  }

  if (initialParams.get("deleted_message") === "1") {
    showPageToast("Mensagem excluida com sucesso", "Ela saiu da lista e nao fica mais pendente.");
  }

  function currentListUrl() {
    const url = new URL('/painel/index.php', location.origin);
    const q = document.getElementById("q").value.trim();
    const status = document.getElementById("status").value;

    url.searchParams.set('pagina', 'index');
    if (q) url.searchParams.set('q', q);
    if (status) url.searchParams.set('status', status);
    if (currentPage > 1) url.searchParams.set('page', String(currentPage));
    return url.toString();
  }

  function syncListUrl() {
    history.replaceState(null, '', currentListUrl());
  }

  async function loadRuns(isManual = false) {
    if (activeRunsController) {
      activeRunsController.abort();
    }
    activeRunsController = new AbortController();

    const q = document.getElementById("q").value.trim();
    const status = document.getElementById("status").value;
    syncListUrl();

    const url = new URL("api/runs.php", location.href);
    url.searchParams.set("limit", limit);
    url.searchParams.set("page", currentPage);
    if (q) url.searchParams.set("q", q);
    if (status) url.searchParams.set("status", status);
    if (!isManual && previousSignature) url.searchParams.set("signature", previousSignature);

    if (isManual || previousDataHash === null) showLoading();

    const statusText = document.getElementById("statusText");
    const statusBar = document.getElementById("statusBar");

    try {
      const r = await fetch(url.toString(), {
        cache: "no-store",
        credentials: "same-origin",
        signal: activeRunsController.signal
      });
      const text = await r.text();
      let j = null;
      try { j = JSON.parse(text); } catch (e) {}

      if (r.status === 401) {
        location.href = "/painel/";
        return;
      }

      if (!r.ok) {
        throw new Error(j?.error || text.slice(0, 220) || `HTTP ${r.status}`);
      }

      if (!j) {
        throw new Error(text.slice(0, 220) || "Resposta invalida do servidor");
      }

      if (j.ok === false) {
        throw new Error(j.error || "Falha na requisicao");
      }

      if (j.signature) {
        previousSignature = j.signature;
      }

      if (j.not_modified) {
        statusText.textContent = "Verificado em " + new Date().toLocaleString('pt-BR');
        return;
      }

      const currentDataHash = JSON.stringify({
        rows: j.rows ?? [],
        page: j.page ?? currentPage,
        total: j.total ?? 0
      });

      if (currentDataHash === previousDataHash && !isManual) {
        statusText.textContent = "Verificado em " + new Date().toLocaleString('pt-BR');
        return;
      }
      previousDataHash = currentDataHash;

      statusText.textContent = "Dados atualizados em " + new Date().toLocaleString('pt-BR');
      statusBar.classList.add("updated");
      setTimeout(() => statusBar.classList.remove("updated"), 1500);

      const tbody = document.getElementById("tbody");

      if (!j.ok || !j.rows || j.rows.length === 0) {
        tbody.innerHTML = `
          <tr style="cursor:default; pointer-events:none;">
            <td colspan="4" style="text-align:center; padding:40px; color:var(--text-muted); background:transparent; box-shadow:none; border:none; font-weight:600;">
              Nenhum registro encontrado.
            </td>
          </tr>
        `;
        buildPager(0, 1);
        currentPage = 1;
        return;
      }

      currentPage = j.page ?? currentPage;
      buildPager(j.total_pages ?? 1, currentPage);

      tbody.innerHTML = j.rows.map(row => {
        const quando = formatDateTimeBr(row.created_at);
        const backUrl = encodeURIComponent(currentListUrl());
        const linkDestino = `/painel/index.php?pagina=detalhes&id=${encodeURIComponent(row.run_id)}&back=${backUrl}`;
        const dotColor = (row.status === 'error') ? 'var(--red-text)' : 'var(--primary)';
        const dotBg = (row.status === 'error') ? 'var(--red-bg)' : 'var(--blue-bg)';

        return `
          <tr onclick="window.LisOnPageLoader?.show(); window.location.href='${linkDestino}'" title="Clique para ver detalhes">
            <td style="text-align:center;">
              <div style="width:10px; height:10px; background:${dotColor}; border-radius:50%;
                          box-shadow: 0 0 0 3px ${dotBg};"></div>
            </td>
            <td>
              <span class="customer-name">${esc(row.customer_name)}</span>
              <span class="bill-id">DOC: ${esc(row.bill_id)}</span>
            </td>
            <td>${badge(row.status)}</td>
            <td style="text-align:right;">
              <div style="display:flex; align-items:center; justify-content:flex-end; gap:15px;">
                <span style="font-size:13px; color:var(--text-muted); font-weight:600;">${quando}</span>
                <a href="${linkDestino}" class="btn-icon" onclick="event.stopPropagation(); window.LisOnPageLoader?.show();">
                  <i class="fa-solid fa-chevron-right"></i>
                </a>
              </div>
            </td>
          </tr>
        `;
      }).join("");

    } catch (e) {
      if (e.name === 'AbortError') return;
      console.error(e);
      document.getElementById("tbody").innerHTML = `
        <tr>
          <td colspan="4" style="text-align:center; padding:30px; color:var(--red-text); font-weight:700;">
            ${esc(e.message || 'Erro ao conectar com o servidor.')}
          </td>
        </tr>
      `;
    }
  }

  document.getElementById("btnReload").onclick = () => loadRuns(true);

  document.getElementById("status").onchange = () => {
    currentPage = 1;
    previousDataHash = null;
    previousSignature = null;
    loadRuns(true);
  };

  document.getElementById("q").onkeydown = (e) => {
    if (e.key === 'Enter') {
      currentPage = 1;
      previousDataHash = null;
      previousSignature = null;
      loadRuns(true);
    }
  };

  const pagerEl = document.getElementById('pager');
  if (pagerEl) {
    pagerEl.onclick = (e) => {
      const btn = e.target.closest('button[data-page]');
      if (!btn) return;

      const p = parseInt(btn.getAttribute('data-page'), 10);
      if (!p || p === currentPage) return;

      currentPage = p;
      previousDataHash = null;
      previousSignature = null;
      loadRuns(true);
    };
  }

  let autoInterval;
  document.getElementById("auto").onchange = function () {
    const indicator = document.getElementById("liveIndicator");

    if (this.checked) {
      autoInterval = setInterval(() => loadRuns(false), autoRefreshMs);
      indicator.style.display = "inline-block";
      document.getElementById("statusText").textContent = "Monitorando...";
    } else {
      clearInterval(autoInterval);
      indicator.style.display = "none";
      document.getElementById("statusText").textContent = "Auto-refresh pausado";
    }
  };

  autoInterval = setInterval(() => loadRuns(false), autoRefreshMs);
  loadRuns(true);
</script>


</div>
