<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }
?>
<div class="pa-wrap">
  <style>
    .pa-wrap{
      --bg-panel:#f4f7fa;
      --primary:#38b6ff;
      --primary-hover:rgb(39,121,169);
      --text-main:#1e293b;
      --text-muted:#64748b;
      --border-color:#eef2f6;
      --green-bg:#d1fae5; --green-text:#065f46;
      --red-bg:#fee2e2; --red-text:#991b1b;
      --blue-bg:#dbeafe; --blue-text:#1e40af;
      --yellow-bg:#fef3c7; --yellow-text:#92400e;
      --radius-pill:50px;
      --radius-card:20px;
      --shadow-soft:0 4px 6px -1px rgba(0,0,0,.05),0 2px 4px -1px rgba(0,0,0,.03);
      --shadow-hover:0 10px 15px -3px rgba(59,130,246,.15);
      font-family:'Nunito',sans-serif;
      color:var(--text-main);
    }

    .pa-header{
      background:#fff;
      border-bottom:2px solid var(--border-color);
      padding:0 40px;
      height:70px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      position:sticky;
      top:0;
      z-index:50;
      border-radius:16px;
      box-shadow:var(--shadow-soft);
      margin-bottom:25px;
    }

    .brand{font-size:22px;font-weight:800;display:flex;align-items:center;gap:12px;}
    .brand i{color:var(--primary);font-size:24px;}

    .status-bar{
      font-size:13px;
      background:var(--bg-panel);
      padding:8px 20px;
      border-radius:var(--radius-pill);
      font-weight:700;
      border:2px solid var(--border-color);
      display:flex;
      align-items:center;
      gap:8px;
      transition:.3s;
    }

    .status-bar.updated{background:var(--green-bg);color:var(--green-text);border-color:var(--green-bg);transform:scale(1.05);}
    .status-bar.error{background:var(--red-bg);color:var(--red-text);border-color:var(--red-bg);}
    .live-dot{width:8px;height:8px;background:var(--primary);border-radius:50%;display:inline-block;animation:pulse 1.5s infinite;}
    @keyframes pulse{0%{opacity:.5;transform:scale(1)}50%{opacity:1;transform:scale(1.3)}100%{opacity:.5;transform:scale(1)}}

    .container{max-width:1200px;margin:0 auto;padding:0 25px;}

    .toolbar{
      background:#fff;
      border:2px solid var(--border-color);
      border-radius:var(--radius-pill);
      padding:12px 20px;
      display:flex;
      gap:12px;
      align-items:center;
      box-shadow:var(--shadow-soft);
      margin-bottom:26px;
      flex-wrap:wrap;
      transition:box-shadow .3s;
    }
    .toolbar:hover{box-shadow:var(--shadow-hover);}

    .form-control{
      background:var(--bg-panel);
      border:2px solid transparent;
      border-radius:var(--radius-pill);
      padding:0 20px;
      height:45px;
      font-family:'Nunito',sans-serif;
      font-size:15px;
      font-weight:700;
      outline:none;
      transition:.2s;
      color:var(--text-main);
      flex:1;
      min-width:180px;
    }
    .form-control:focus{background:#fff;border-color:var(--primary);box-shadow:0 0 0 4px rgba(59,130,246,.1);}

    .btn-primary,.btn-secondary{
      border:none;
      padding:0 18px;
      height:45px;
      border-radius:var(--radius-pill);
      cursor:pointer;
      font-weight:800;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      transition:.2s;
      font-family:'Nunito',sans-serif;
      white-space:nowrap;
    }
    .btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 6px rgba(59,130,246,.2);}
    .btn-primary:hover{transform:translateY(-2px);background:var(--primary-hover);}
    .btn-secondary{background:#fff;color:var(--text-main);border:2px solid var(--border-color);box-shadow:var(--shadow-soft);}
    .btn-secondary:hover{transform:translateY(-2px);border-color:#dbeafe;color:var(--primary);}
    .btn-primary:disabled,.btn-secondary:disabled,.btn-mini:disabled{opacity:.45;cursor:not-allowed;transform:none;}

    .toggle-wrapper{display:flex;align-items:center;gap:8px;padding:0 18px;border-right:2px solid var(--border-color);}
    .custom-check{accent-color:var(--primary);width:18px;height:18px;cursor:pointer;}
    .toggle-wrapper label{font-size:14px;font-weight:700;color:var(--text-muted);cursor:pointer;}

    .summary-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin-bottom:18px;}
    .metric{
      background:#fff;
      border:2px solid var(--border-color);
      border-radius:20px;
      padding:16px 18px;
      box-shadow:var(--shadow-soft);
    }
    .metric span{display:block;color:var(--text-muted);font-size:12px;font-weight:900;text-transform:uppercase;}
    .metric strong{display:block;font-size:24px;font-weight:900;margin-top:4px;}

    table{width:100%;border-collapse:separate;border-spacing:0 12px;}
    thead th{color:var(--text-muted);font-size:13px;text-transform:uppercase;font-weight:800;padding:0 20px;text-align:left;}
    tbody tr{background:#fff;box-shadow:var(--shadow-soft);border:2px solid var(--border-color);border-radius:var(--radius-card);transition:.2s;}
    tbody tr:hover{transform:translateY(-3px) scale(1.003);box-shadow:var(--shadow-hover);border-color:#dbeafe;}
    tbody td{padding:18px 20px;vertical-align:middle;border-top:2px solid var(--border-color);border-bottom:2px solid var(--border-color);}
    tbody td:first-child{border-left:2px solid var(--border-color);border-top-left-radius:var(--radius-card);border-bottom-left-radius:var(--radius-card);}
    tbody td:last-child{border-right:2px solid var(--border-color);border-top-right-radius:var(--radius-card);border-bottom-right-radius:var(--radius-card);}

    .customer-name{font-weight:900;font-size:16px;display:block;color:var(--text-main);}
    .muted{color:var(--text-muted);font-weight:700;font-size:12px;margin-top:4px;display:block;}
    .bill-id{font-size:13px;color:var(--primary);font-weight:800;margin-top:4px;display:inline-block;background:#eff6ff;padding:2px 8px;border-radius:10px;text-decoration:none;}

    .badge{padding:6px 14px;border-radius:var(--radius-pill);font-weight:800;font-size:12px;text-transform:uppercase;display:inline-flex;align-items:center;gap:6px;}
    .b-paid{background:var(--green-bg);color:var(--green-text);}
    .b-unpaid{background:var(--red-bg);color:var(--red-text);}
    .b-canceled{background:#e2e8f0;color:#334155;}
    .b-blocked{background:var(--yellow-bg);color:var(--yellow-text);}
    .b-ready{background:var(--blue-bg);color:var(--blue-text);}

    .pill{display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;background:var(--bg-panel);border:2px solid var(--border-color);font-weight:800;font-size:12px;color:var(--text-main);white-space:nowrap;}

    .customer-cell{display:grid;grid-template-columns:10px 1fr;gap:14px;align-items:center;}
    .status-dot{width:10px;height:46px;border-radius:99px;background:var(--primary);box-shadow:0 0 0 4px #eff6ff;}
    .status-dot.ready{background:#2563eb;}
    .status-dot.wait{background:#f59e0b;box-shadow:0 0 0 4px #fef3c7;}
    .status-dot.blocked{background:#d97706;box-shadow:0 0 0 4px #fef3c7;}
    .status-dot.paid{background:#059669;box-shadow:0 0 0 4px #d1fae5;}

    .status-stack{display:flex;flex-direction:column;align-items:flex-start;gap:8px;min-width:270px;}
    .last-message{display:flex;align-items:flex-start;gap:9px;background:#f8fafc;border:2px solid var(--border-color);border-radius:14px;padding:9px 11px;max-width:340px;color:var(--text-main);font-size:12px;font-weight:800;line-height:1.3;}
    .last-message i{color:var(--primary);margin-top:1px;}
    .last-message.fail{background:var(--red-bg);border-color:var(--red-bg);color:var(--red-text);}
    .last-message.fail i{color:var(--red-text);}
    .date-line{display:inline-flex;align-items:center;gap:7px;color:var(--text-muted);font-weight:800;font-size:12px;}
    .date-line i{color:#94a3b8;}
    .action-grid{display:grid;grid-template-columns:repeat(2,max-content);gap:10px;justify-content:end;}

    .btn-mini{
      border:2px solid var(--border-color);
      background:#fff;
      color:var(--text-main);
      border-radius:999px;
      padding:7px 12px;
      font-weight:900;
      cursor:pointer;
      transition:.2s;
      display:inline-flex;
      align-items:center;
      gap:8px;
      text-decoration:none;
      font-family:'Nunito',sans-serif;
    }
    .btn-mini:hover{border-color:#dbeafe;color:var(--primary);transform:translateY(-1px);}
    .btn-mini.danger:hover{border-color:var(--red-bg);color:var(--red-text);}
    .btn-mini.ok:hover{border-color:var(--green-bg);color:var(--green-text);}

    .spinner{width:30px;height:30px;border:4px solid #e2e8f0;border-top-color:var(--primary);border-radius:50%;animation:spin .8s linear infinite;margin:0 auto;}
    @keyframes spin{to{transform:rotate(360deg)}}

    .pager{display:flex;justify-content:center;align-items:center;gap:8px;flex-wrap:wrap;margin:18px 0 10px;}
    .page-btn{background:#fff;border:2px solid var(--border-color);color:var(--text-main);border-radius:999px;padding:8px 14px;font-weight:800;cursor:pointer;box-shadow:var(--shadow-soft);transition:.2s;font-family:'Nunito',sans-serif;}
    .page-btn:hover{transform:translateY(-1px);border-color:#dbeafe;color:var(--primary);}
    .page-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);}
    .page-btn:disabled{opacity:.45;cursor:not-allowed;transform:none;}
    .page-info{color:var(--text-muted);font-weight:700;padding:8px 12px;}

    @media(max-width:900px){
      .pa-header{padding:0 18px;}
      .summary-grid{grid-template-columns:repeat(2,minmax(140px,1fr));}
      table{min-width:980px;}
      .container{overflow-x:auto;padding:0 12px;}
    }
  </style>

  <div class="pa-header">
    <div class="brand"><i class="fa-solid fa-money-bill-wave"></i> Recobrancas</div>
    <div class="status-bar" id="statusBar">
      <span class="live-dot" id="liveIndicator"></span>
      <span id="statusText">Aguardando...</span>
    </div>
  </div>

  <div class="container">
    <div class="toolbar">
      <div style="flex:2;min-width:220px;position:relative;">
        <i class="fa-solid fa-search" style="color:var(--text-muted);position:absolute;left:18px;top:15px;font-size:15px;"></i>
        <input id="q" class="form-control" style="padding-left:45px;" placeholder="Buscar cliente, telefone ou bill id">
      </div>

      <select id="status" class="form-control" style="max-width:210px;cursor:pointer;">
        <option value="unpaid" selected>Devendo</option>
        <option value="all">Todos</option>
        <option value="paid">Pagos</option>
        <option value="canceled">Cancelados</option>
        <option value="blocked">Bloqueados</option>
      </select>

      <div class="toggle-wrapper">
        <input type="checkbox" id="onlyOverdue" checked class="custom-check">
        <label for="onlyOverdue">So vencidas</label>
      </div>

      <div class="toggle-wrapper" style="border-right:none;">
        <input type="checkbox" id="auto" checked class="custom-check">
        <label for="auto">Auto-refresh</label>
      </div>

      <button type="button" id="btnRunQueue" class="btn-secondary"><i class="fa-solid fa-paper-plane"></i> Processar fila</button>
      <button type="button" id="btnReload" class="btn-primary">Atualizar <i class="fa-solid fa-rotate"></i></button>
    </div>

    <div class="summary-grid">
      <div class="metric"><span>Registros</span><strong id="mTotal">0</strong></div>
      <div class="metric"><span>Prontos</span><strong id="mReady">0</strong></div>
      <div class="metric"><span>Bloqueados</span><strong id="mBlocked">0</strong></div>
      <div class="metric"><span>Envios</span><strong id="mSent">0</strong></div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Cliente / Bill</th>
          <th>Valor</th>
          <th>Vencimento</th>
          <th>Status / Ultima mensagem</th>
          <th>Envios</th>
          <th style="text-align:right;">Acoes</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>

    <div id="pager" class="pager"></div>
  </div>

  <script>
    function esc(s){
      return String(s ?? '').replace(/[&<>"']/g, m => ({
        "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
      }[m]));
    }

    function normalizeStatus(status, blocked){
      if (Number(blocked || 0) === 1) return 'blocked';
      const st = String(status || '').toLowerCase();
      if (st === 'pending' || st === 'overdue' || st === 'unpaid' || st === '') return 'unpaid';
      if (st === 'paid') return 'paid';
      if (st === 'canceled' || st === 'cancelled') return 'canceled';
      return st;
    }

    function fmtMoney(v){
      const n = Number(v ?? 0);
      if (!isFinite(n)) return esc(v);
      return n.toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
    }

    function fmtDateTimeBr(value){
      const s = String(value ?? '').trim();
      if (!s) return '-';
      const dt = new Date(s.replace(' ', 'T'));
      if (isNaN(dt.getTime())) return esc(s);
      return dt.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    }

    function badge(st, blocked, ready){
      const normalized = normalizeStatus(st, blocked);
      if (normalized === 'blocked') return `<span class="badge b-blocked"><i class="fa-solid fa-pause"></i> Bloqueado</span>`;
      if (normalized === 'paid') return `<span class="badge b-paid"><i class="fa-solid fa-circle-check"></i> Pago</span>`;
      if (normalized === 'canceled') return `<span class="badge b-canceled"><i class="fa-solid fa-ban"></i> Cancelado</span>`;
      if (ready) return `<span class="badge b-ready"><i class="fa-solid fa-bolt"></i> Pronto</span>`;
      return `<span class="badge b-unpaid"><i class="fa-solid fa-clock"></i> Aguardando</span>`;
    }

    function rowDotClass(normalized, ready){
      if (normalized === 'blocked') return 'blocked';
      if (normalized === 'paid') return 'paid';
      if (ready) return 'ready';
      return 'wait';
    }

    function setStatus(text, type='ok'){
      const bar = document.getElementById('statusBar');
      document.getElementById('statusText').textContent = text;
      bar.classList.remove('updated','error');
      bar.classList.add(type === 'error' ? 'error' : 'updated');
      setTimeout(() => bar.classList.remove('updated','error'), 1600);
    }

    function showLoading(){
      document.getElementById("tbody").innerHTML = `
        <tr style="pointer-events:none;">
          <td colspan="6" style="text-align:center;padding:40px;background:transparent;box-shadow:none;border:none;">
            <div class="spinner"></div>
            <div style="margin-top:12px;font-weight:800;color:var(--text-main);">Carregando dados...</div>
          </td>
        </tr>
      `;
    }

    function buildPager(totalPages, page){
      const pager = document.getElementById('pager');
      if (!totalPages || totalPages <= 1){ pager.innerHTML = ''; return; }
      const parts = [];
      const addBtn = (p,label,disabled=false,active=false) => {
        parts.push(`<button type="button" class="page-btn ${active?'active':''}" data-page="${p}" ${disabled?'disabled':''}>${label}</button>`);
      };
      addBtn(Math.max(1,page-1),'‹', page===1);
      const start=Math.max(1,page-2), end=Math.min(totalPages,page+2);
      if (start>1){ addBtn(1,'1',false,page===1); if (start>2) parts.push(`<span class="page-info">...</span>`); }
      for (let p=start; p<=end; p++) addBtn(p,String(p),false,p===page);
      if (end<totalPages){ if (end<totalPages-1) parts.push(`<span class="page-info">...</span>`); addBtn(totalPages,String(totalPages),false,page===totalPages); }
      addBtn(Math.min(totalPages,page+1),'›', page===totalPages);
      parts.push(`<span class="page-info">Pagina ${page} de ${totalPages}</span>`);
      pager.innerHTML = parts.join('');
    }

    async function postAction(action, billId){
      const fd = new FormData();
      fd.set('action', action);
      fd.set('bill_id', String(billId));
      const r = await fetch('/painel/api/recobranca_action.php', { method:'POST', body: fd });
      const text = await r.text();
      let j = null;
      try { j = JSON.parse(text); } catch(e) {}
      if (!r.ok) throw new Error(`HTTP ${r.status}: ${text.slice(0, 180)}`);
      if (!j || !j.ok) throw new Error(j?.error || text.slice(0, 180) || 'Falha na acao');
      return j;
    }

    async function runQueue(limit=20, billId=null){
      const btn = document.getElementById('btnRunQueue');
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Processando`;
      try{
        const url = new URL('/painel/api/cron_recobranca.php', location.origin);
        url.searchParams.set('limit', String(limit));
        if (billId) url.searchParams.set('bill_id', String(billId));
        const r = await fetch(url.toString(), { cache:'no-store' });
        const text = await r.text();
        if (!r.ok) throw new Error(`HTTP ${r.status}: ${text.slice(0, 180)}`);
        if (/^\s*ERRO/i.test(text)) throw new Error(text.trim());
        if (billId && /\bsent=0\b/.test(text)) throw new Error(text.trim() || 'Nenhuma mensagem foi enviada');
        setStatus((text || 'Fila processada').trim(), 'ok');
        previousHash = null;
        await load(true);
      } catch(e){
        console.error(e);
        previousHash = null;
        await load(true);
        setStatus(e.message || 'Erro ao processar fila', 'error');
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    }

    let currentPage = 1;
    const limit = 50;
    let previousHash = null;

    async function load(isManual=false){
      const q = document.getElementById('q').value.trim();
      const status = document.getElementById('status').value;
      const onlyOverdue = document.getElementById('onlyOverdue').checked ? '1' : '0';
      const url = new URL('/painel/api/recobrancas_live.php', location.origin);
      url.searchParams.set('page', currentPage);
      url.searchParams.set('limit', limit);
      url.searchParams.set('only_overdue', onlyOverdue);
      if (q) url.searchParams.set('q', q);
      if (status) url.searchParams.set('status', status);

      if (isManual || previousHash === null) showLoading();

      try{
        const r = await fetch(url.toString(), { cache:'no-store' });
        const j = await r.json();
        const hash = JSON.stringify({ rows:j.rows ?? [], page:j.page ?? currentPage, total:j.total ?? 0 });
        if (hash === previousHash && !isManual){
          document.getElementById('statusText').textContent = "Verificado em " + new Date().toLocaleString('pt-BR');
          return;
        }
        previousHash = hash;
        setStatus("Atualizado em " + new Date().toLocaleString('pt-BR'), 'ok');

        const tbody = document.getElementById('tbody');
        const rows = Array.isArray(j.rows) ? j.rows : [];

        document.getElementById('mTotal').textContent = String(j.total ?? rows.length ?? 0);
        document.getElementById('mReady').textContent = String(rows.filter(x => normalizeStatus(x.status, x.blocked)==='unpaid' && Number(x.days_overdue || 0) >= 7).length);
        document.getElementById('mBlocked').textContent = String(rows.filter(x => Number(x.blocked || 0) === 1).length);
        document.getElementById('mSent').textContent = String(rows.reduce((acc,x) => acc + Number(x.overdue_sent_count || 0), 0));

        if (!j.ok || rows.length === 0){
          tbody.innerHTML = `
            <tr style="pointer-events:none;">
              <td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);background:transparent;box-shadow:none;border:none;font-weight:700;">
                Nenhuma cobranca encontrada.
              </td>
            </tr>
          `;
          buildPager(0,1);
          currentPage = 1;
          return;
        }

        currentPage = j.page ?? currentPage;
        buildPager(j.total_pages ?? 1, currentPage);

        tbody.innerHTML = rows.map(row => {
          const billId = row.bill_id;
          const normalized = normalizeStatus(row.status, row.blocked);
          const days = Number(row.days_overdue || 0);
          const ready = normalized === 'unpaid' && days >= 7;
          const blocked = Number(row.blocked || 0) === 1;
          const billUrl = String(row.bill_url || '');
          const phone = row.phone ? `Tel ${esc(row.phone)}` : 'Tel nao salvo';
          const next = row.next_reminder_at ? fmtDateTimeBr(row.next_reminder_at) : '-';
          const lastAt = row.last_message_at ? fmtDateTimeBr(row.last_message_at) : 'Sem envio';
          const lastMessage = row.last_message || 'Nenhuma mensagem enviada ainda';
          const lastOk = row.last_message_ok;
          const lastStatus = row.last_status ? esc(row.last_status) : '-';

          const btnNow = `<button type="button" class="btn-mini" data-act="send_now" data-id="${billId}" ${blocked || !ready ? 'disabled' : ''} title="${ready ? 'Agenda e processa agora' : 'So libera apos 7 dias de atraso'}">
            <i class="fa-solid fa-bolt"></i> Enviar
          </button>`;
          const btnPause = `<button type="button" class="btn-mini danger" data-act="pause" data-id="${billId}" ${blocked ? 'disabled' : ''}>
            <i class="fa-solid fa-pause"></i> Parar
          </button>`;
          const btnResume = `<button type="button" class="btn-mini ok" data-act="resume" data-id="${billId}" ${blocked ? '' : 'disabled'}>
            <i class="fa-solid fa-play"></i> Reativar
          </button>`;
          const btnBill = billUrl
            ? `<a class="btn-mini" href="${esc(billUrl)}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Bill</a>`
            : `<span class="btn-mini" style="opacity:.45;pointer-events:none;"><i class="fa-solid fa-link-slash"></i> Bill</span>`;

          return `
            <tr>
              <td>
                <div class="customer-cell">
                  <span class="status-dot ${rowDotClass(normalized, ready)}"></span>
                  <div>
                    <span class="customer-name">${esc(row.customer_name || 'Cliente')}</span>
                    <span class="bill-id">Bill ${esc(billId)}</span>
                    <span class="muted">${phone} | ${days} dias em atraso</span>
                  </div>
                </div>
              </td>
              <td><span class="pill"><i class="fa-solid fa-coins"></i> ${fmtMoney(row.amount)}</span></td>
              <td>
                <span class="pill"><i class="fa-regular fa-calendar"></i> ${fmtDateTimeBr(row.due_at)}</span>
                <span class="muted">Prox: ${esc(next)}</span>
              </td>
              <td>
                <div class="status-stack">
                  ${badge(row.status, row.blocked, ready)}
                  <div class="last-message ${lastOk === 0 ? 'fail' : ''}">
                    <i class="fa-solid ${lastOk === 0 ? 'fa-triangle-exclamation' : 'fa-message'}"></i>
                    <span>${esc(lastMessage)}</span>
                  </div>
                  <span class="date-line"><i class="fa-regular fa-clock"></i> ${esc(lastAt)} | ${lastStatus}</span>
                </div>
              </td>
              <td>
                <span class="pill"><i class="fa-solid fa-paper-plane"></i> ${Number(row.overdue_sent_count || 0)}</span>
                <span class="pill" style="margin-left:6px;"><i class="fa-solid fa-triangle-exclamation"></i> ${Number(row.reminder_attempts || 0)}</span>
              </td>
              <td style="text-align:right;">
                <div class="action-grid">
                  ${btnNow}${btnPause}${btnResume}${btnBill}
                </div>
              </td>
            </tr>
          `;
        }).join('');
      } catch(e){
        console.error(e);
        setStatus('Erro ao carregar cobrancas', 'error');
        document.getElementById('tbody').innerHTML = `
          <tr>
            <td colspan="6" style="text-align:center;padding:30px;color:var(--red-text);font-weight:800;">
              Erro ao conectar com o servidor.
            </td>
          </tr>
        `;
      }
    }

    document.getElementById('tbody').addEventListener('click', async (e) => {
      const btn = e.target.closest('button[data-act]');
      if (!btn) return;
      e.preventDefault();
      const act = btn.getAttribute('data-act');
      const id = btn.getAttribute('data-id');
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;
      try{
        await postAction(act, id);
        if (act === 'send_now') await runQueue(1, id);
        else {
          previousHash = null;
          await load(true);
          setStatus('Acao aplicada', 'ok');
        }
      } catch(err){
        console.error(err);
        setStatus(err.message || 'Falha na acao', 'error');
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });

    document.getElementById('btnReload').onclick = () => load(true);
    document.getElementById('btnRunQueue').onclick = () => runQueue(20);
    document.getElementById('status').onchange = () => { currentPage = 1; previousHash=null; load(true); };
    document.getElementById('onlyOverdue').onchange = () => { currentPage = 1; previousHash=null; load(true); };
    document.getElementById('q').onkeydown = (e) => {
      if (e.key === 'Enter'){ currentPage=1; previousHash=null; load(true); }
    };

    document.getElementById('pager').onclick = (e) => {
      const b = e.target.closest('button[data-page]');
      if (!b) return;
      e.preventDefault();
      const p = parseInt(b.getAttribute('data-page'), 10);
      if (!p || p === currentPage) return;
      currentPage = p;
      previousHash = null;
      load(true);
    };

    let autoInterval = setInterval(() => load(false), 4000);
    document.getElementById('auto').onchange = function(){
      const ind = document.getElementById('liveIndicator');
      if (this.checked){
        autoInterval = setInterval(() => load(false), 4000);
        ind.style.display = 'inline-block';
        document.getElementById('statusText').textContent = 'Monitorando...';
      } else {
        clearInterval(autoInterval);
        ind.style.display = 'none';
        document.getElementById('statusText').textContent = 'Auto-refresh pausado';
      }
    };

    load(true);
  </script>
</div>
