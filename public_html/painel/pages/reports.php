<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }
?>
<div class="pa-wrap reports-wrap">
  <style>
    .reports-wrap{
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
    .rep-header{
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
      gap:16px;
    }
    .brand{font-size:22px;font-weight:800;color:var(--text-main);display:flex;align-items:center;gap:12px;}
    .brand i{color:var(--primary);font-size:24px;}
    .rep-title{display:flex;align-items:center;gap:12px;min-width:0;}
    .rep-title i{color:var(--primary);font-size:24px;}
    .rep-title h1{font-size:22px;line-height:1.1;margin:0;font-weight:900;letter-spacing:0;}
    .rep-title p{margin:4px 0 0;color:var(--text-muted);font-size:12px;font-weight:800;}
    .status-pill{
      font-size:13px;color:var(--text-main);background:var(--bg-panel);padding:8px 18px;border-radius:var(--radius-pill);
      font-weight:800;border:2px solid var(--border-color);display:flex;align-items:center;gap:8px;white-space:nowrap;
    }
    .status-pill.updated{background:var(--green-bg);color:var(--green-text);border-color:var(--green-bg);transform:scale(1.05);}
    .status-pill.error{background:var(--red-bg);color:var(--red-text);border-color:var(--red-bg);}
    .status-dot{width:8px;height:8px;border-radius:50%;background:var(--primary);display:inline-block;animation:pulse-dot 1.5s infinite;}
    .rep-container{max-width:1200px;margin:0 auto;padding:0 25px;}
    .toolbar{
      background:#fff;border:2px solid var(--border-color);border-radius:var(--radius-pill);padding:12px 20px;display:flex;gap:12px;align-items:center;
      box-shadow:var(--shadow-soft);margin-bottom:22px;flex-wrap:wrap;transition:box-shadow .3s;
    }
    .toolbar:hover{box-shadow:var(--shadow-hover);}
    .search-box{flex:1;min-width:240px;position:relative;}
    .search-box i{position:absolute;left:18px;top:15px;color:var(--text-muted);}
    .form-control{
      width:100%;height:45px;box-sizing:border-box;border:2px solid transparent;border-radius:var(--radius-pill);background:var(--bg-panel);
      padding:0 18px 0 45px;color:var(--text-main);font-family:'Nunito',sans-serif;font-weight:800;font-size:15px;outline:none;
    }
    .form-control:focus{background:#fff;border-color:var(--primary);box-shadow:0 0 0 4px rgba(59,130,246,.1);}
    .btn-primary{
      height:45px;border:0;border-radius:var(--radius-pill);background:var(--primary);color:#fff;padding:0 18px;
      display:inline-flex;align-items:center;justify-content:center;gap:10px;font-family:'Nunito',sans-serif;font-weight:900;cursor:pointer;
      box-shadow:0 4px 6px rgba(59,130,246,.2);transition:.2s;white-space:nowrap;
    }
    .btn-primary:hover{transform:translateY(-2px);background:var(--primary-hover);}
    .btn-secondary{
      height:45px;border:2px solid var(--border-color);border-radius:var(--radius-pill);background:#fff;color:var(--text-main);padding:0 18px;
      display:inline-flex;align-items:center;justify-content:center;gap:10px;font-family:'Nunito',sans-serif;font-weight:900;cursor:pointer;
      box-shadow:var(--shadow-soft);transition:.2s;white-space:nowrap;
    }
    .btn-secondary:hover{transform:translateY(-2px);border-color:#dbeafe;color:var(--primary);}
    .btn-primary:disabled,.btn-secondary:disabled{opacity:.55;cursor:not-allowed;transform:none;}
    .summary-grid{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:14px;margin-bottom:18px;}
    .metric{
      background:#fff;border:2px solid var(--border-color);border-radius:var(--radius-card);padding:16px 18px;box-shadow:var(--shadow-soft);
      display:grid;grid-template-columns:1fr 42px;gap:12px;align-items:center;min-height:92px;box-sizing:border-box;
    }
    .metric span{display:block;color:var(--text-muted);font-size:12px;font-weight:900;text-transform:uppercase;}
    .metric strong{display:block;font-size:23px;font-weight:900;margin-top:4px;line-height:1.05;}
    .metric-icon{width:42px;height:42px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#eef8ff;color:#12628f;}
    .metric.danger .metric-icon{background:var(--red-bg);color:var(--red-text);}
    .metric.warn .metric-icon{background:var(--yellow-bg);color:var(--yellow-text);}
    .metric.ok .metric-icon{background:var(--green-bg);color:var(--green-text);}
    .leader-strip{
      background:#fff;border:2px solid var(--border-color);border-radius:var(--radius-card);box-shadow:var(--shadow-soft);padding:16px 18px;
      display:grid;grid-template-columns:1fr repeat(3,max-content);align-items:center;gap:16px;margin-bottom:18px;
    }
    .meta-line{
      margin:-6px 0 18px;color:var(--text-muted);font-size:12px;font-weight:800;display:flex;align-items:center;gap:8px;flex-wrap:wrap;
    }
    .meta-line i{color:var(--primary);}
    .leader-name{font-weight:900;font-size:16px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .leader-sub{display:block;color:var(--text-muted);font-size:12px;font-weight:800;margin-top:3px;}
    .pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:var(--bg-panel);border:2px solid var(--border-color);font-size:12px;font-weight:900;white-space:nowrap;}
    table{width:100%;border-collapse:separate;border-spacing:0 12px;}
    thead th{color:var(--text-muted);font-size:12px;text-transform:uppercase;font-weight:900;padding:0 16px;text-align:left;}
    thead th:last-child, tbody td:last-child{text-align:right;}
    tbody tr.rep-row{background:#fff;box-shadow:var(--shadow-soft);border-radius:var(--radius-card);transition:.2s;cursor:pointer;}
    tbody tr.rep-row:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover);}
    tbody tr.rep-row.open{box-shadow:var(--shadow-hover);}
    tbody td{padding:16px;border-top:2px solid var(--border-color);border-bottom:2px solid var(--border-color);font-size:13px;font-weight:800;vertical-align:middle;}
    tbody td:first-child{border-left:2px solid var(--border-color);border-top-left-radius:var(--radius-card);border-bottom-left-radius:var(--radius-card);}
    tbody td:last-child{border-right:2px solid var(--border-color);border-top-right-radius:var(--radius-card);border-bottom-right-radius:var(--radius-card);}
    .customer-cell{display:grid;grid-template-columns:10px 1fr;gap:14px;align-items:center;}
    .debt-bar{width:10px;height:48px;border-radius:99px;background:var(--primary);box-shadow:0 0 0 4px #eff6ff;}
    .debt-bar.hot{background:#ef4444;box-shadow:0 0 0 4px #fee2e2;}
    .customer-name{font-size:15px;font-weight:900;display:block;}
    .muted{color:var(--text-muted);font-size:12px;font-weight:800;margin-top:3px;display:block;}
    .detail-row td{padding:0 16px 18px;background:#fff;border:none;}
    .detail-panel{border:2px solid #e6eef7;border-radius:16px;background:#fbfdff;padding:14px;display:grid;gap:10px;}
    .bill-line{
      background:#fff;border:1px solid var(--border-color);border-radius:14px;padding:12px;display:grid;
      grid-template-columns:minmax(120px,.75fr) minmax(140px,.75fr) minmax(220px,1.35fr) minmax(140px,.7fr) minmax(92px,.45fr);
      gap:12px;align-items:start;
    }
    .bill-line span{display:block;color:var(--text-muted);font-size:11px;font-weight:900;text-transform:uppercase;margin-bottom:4px;}
    .bill-line strong{display:block;font-size:13px;font-weight:900;line-height:1.35;}
    .bill-items{white-space:pre-wrap;word-break:break-word;}
    .bill-link{color:#12628f;text-decoration:none;font-weight:900;}
    .bill-link:hover{text-decoration:underline;}
    .empty{color:var(--text-muted);font-size:13px;font-weight:800;text-align:center;padding:28px 12px;background:var(--bg-panel);border-radius:16px;}
    .spinner{width:30px;height:30px;border:4px solid #e2e8f0;border-top-color:var(--primary);border-radius:50%;animation:spin .8s linear infinite;margin:0 auto;}
    @keyframes spin{to{transform:rotate(360deg)}}
    @keyframes pulse-dot{0%{opacity:.5;transform:scale(1)}50%{opacity:1;transform:scale(1.3)}100%{opacity:.5;transform:scale(1)}}
    @media(max-width:1060px){
      .summary-grid{grid-template-columns:repeat(2,minmax(150px,1fr));}
      .leader-strip{grid-template-columns:1fr 1fr;}
      .bill-line{grid-template-columns:1fr 1fr;}
      table{min-width:980px;}
      .table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}
    }
    @media(max-width:680px){
      .rep-header{align-items:flex-start;flex-direction:column;height:auto;min-height:62px;padding:14px 16px;border-radius:12px;gap:10px;}
      .rep-container{padding:0 8px;}
      .toolbar{border-radius:14px;align-items:stretch;flex-direction:column;}
      .search-box{min-width:0;width:100%;}
      .summary-grid,.leader-strip,.bill-line{grid-template-columns:1fr;}
      .btn-primary{width:100%;}
    }
  </style>

  <div class="rep-header">
    <div class="brand">
      <i class="fa-solid fa-chart-pie"></i>
      <span>Relatorios</span>
    </div>
    <div class="status-pill"><span class="status-dot" id="repDot"></span><span id="repStatus">Aguardando...</span></div>
  </div>

  <div class="rep-container">
    <div class="toolbar">
      <div class="search-box">
        <i class="fa-solid fa-search"></i>
        <input id="repQ" class="form-control" placeholder="Buscar cliente, telefone ou bill id">
      </div>
      <button id="btnLoadReports" class="btn-primary" type="button"><i class="fa-solid fa-rotate"></i> Atualizar</button>
      <button id="btnSyncReports" class="btn-secondary" type="button"><i class="fa-solid fa-cloud-arrow-down"></i> Sincronizar Vindi</button>
    </div>

    <div class="summary-grid">
      <div class="metric"><div><span>Pessoas devendo</span><strong id="mDebtors">0</strong></div><div class="metric-icon"><i class="fa-solid fa-users"></i></div></div>
      <div class="metric danger"><div><span>Total em aberto</span><strong id="mAmount">R$ 0,00</strong></div><div class="metric-icon"><i class="fa-solid fa-coins"></i></div></div>
      <div class="metric warn"><div><span>Parcelas</span><strong id="mBills">0</strong></div><div class="metric-icon"><i class="fa-solid fa-file-invoice"></i></div></div>
      <div class="metric"><div><span>Vencidas</span><strong id="mOverdue">0</strong></div><div class="metric-icon"><i class="fa-solid fa-triangle-exclamation"></i></div></div>
      <div class="metric ok"><div><span>Recobrancas</span><strong id="mReminders">0</strong></div><div class="metric-icon"><i class="fa-brands fa-whatsapp"></i></div></div>
    </div>

    <div class="leader-strip" id="leaderStrip">
      <div>
        <span class="leader-name">Maior devedor</span>
        <span class="leader-sub">Aguardando dados</span>
      </div>
      <span class="pill"><i class="fa-solid fa-coins"></i> R$ 0,00</span>
      <span class="pill"><i class="fa-solid fa-file-invoice"></i> 0 parcelas</span>
      <span class="pill"><i class="fa-solid fa-paper-plane"></i> 0 recobrancas</span>
    </div>

    <div class="meta-line" id="repMeta">
      <i class="fa-solid fa-database"></i>
      <span>Aguardando leitura completa...</span>
    </div>

    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Total</th>
            <th>Parcelas</th>
            <th>Mais antiga</th>
            <th>Recobrancas</th>
            <th>Detalhe</th>
          </tr>
        </thead>
        <tbody id="repBody"></tbody>
      </table>
    </div>
  </div>

  <script>
    const repState = { rows: [], expanded: new Set() };
    const $rep = (id) => document.getElementById(id);
    let reportsLoading = false;

    function esc(value){
      return String(value ?? '').replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
      }[m]));
    }

    function brMoney(value){
      return Number(value || 0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
    }

    function brNumber(value){
      return Number(value || 0).toLocaleString('pt-BR');
    }

    function brDate(value){
      const raw = String(value || '').trim();
      if (!raw) return '-';
      const d = new Date(raw.replace(' ', 'T'));
      if (Number.isNaN(d.getTime())) return raw;
      return d.toLocaleDateString('pt-BR');
    }

    function brDateTime(value){
      const raw = String(value || '').trim();
      if (!raw) return '-';
      const d = new Date(raw.replace(' ', 'T'));
      if (Number.isNaN(d.getTime())) return raw;
      return d.toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'});
    }

    function setStatus(text, error=false){
      const bar = $rep('repStatus').parentElement;
      $rep('repStatus').textContent = text;
      $rep('repDot').style.background = error ? '#ef4444' : 'var(--primary)';
      if (bar) {
        bar.classList.remove('updated','error');
        bar.classList.add(error ? 'error' : 'updated');
        setTimeout(() => bar.classList.remove('updated','error'), 1600);
      }
    }

    function sourceLabel(source){
      const s = String(source || '').toLowerCase();
      if (s === 'vindi+local') return 'Vindi + local';
      if (s === 'vindi') return 'Vindi';
      return 'Local';
    }

    function renderLeader(row){
      const box = $rep('leaderStrip');
      if (!row) {
        box.innerHTML = `
          <div>
            <span class="leader-name">Maior devedor</span>
            <span class="leader-sub">Nenhum cliente devendo encontrado.</span>
          </div>
          <span class="pill"><i class="fa-solid fa-coins"></i> R$ 0,00</span>
          <span class="pill"><i class="fa-solid fa-file-invoice"></i> 0 parcelas</span>
          <span class="pill"><i class="fa-solid fa-paper-plane"></i> 0 recobrancas</span>
        `;
        return;
      }
      box.innerHTML = `
        <div>
          <span class="leader-name">${esc(row.customer_name || 'Cliente')}</span>
          <span class="leader-sub">Cliente que esta mais devendo no momento${row.phone ? ' | Tel ' + esc(row.phone) : ''}</span>
        </div>
        <span class="pill"><i class="fa-solid fa-coins"></i> ${brMoney(row.total_amount)}</span>
        <span class="pill"><i class="fa-solid fa-file-invoice"></i> ${brNumber(row.open_bills)} parcela(s)</span>
        <span class="pill"><i class="fa-solid fa-paper-plane"></i> ${brNumber(row.reminders_sent)} recobranca(s)</span>
      `;
    }

    function renderBills(row){
      const bills = Array.isArray(row.bills) ? row.bills : [];
      if (!bills.length) return '<div class="empty">Sem parcelas/faturas no detalhe.</div>';
      return bills.map(bill => {
        const billUrl = String(bill.bill_url || '');
        const billLabel = billUrl
          ? `<a class="bill-link" href="${esc(billUrl)}" target="_blank" rel="noopener">Bill ${esc(bill.bill_id)}</a>`
          : `Bill ${esc(bill.bill_id)}`;
        const overdue = Number(bill.days_overdue || 0);
        return `
          <div class="bill-line">
            <div><span>Parcela / fatura</span><strong>${billLabel}</strong></div>
            <div><span>Valor e vencimento</span><strong>${brMoney(bill.amount)}<br>${brDate(bill.due_at)}${overdue ? ` | ${overdue} dia(s) vencida` : ''}</strong></div>
            <div><span>O que esta devendo</span><strong class="bill-items">${esc(bill.items_text || 'Sem itens informados')}</strong></div>
            <div><span>Recobrancas</span><strong>${brNumber(bill.reminders_sent)} enviada(s)<br>Ultima: ${esc(brDateTime(bill.last_reminder_at))}</strong></div>
            <div><span>Origem</span><strong>${esc(sourceLabel(bill.source))}</strong></div>
          </div>
        `;
      }).join('');
    }

    function renderRows(){
      const body = $rep('repBody');
      const rows = repState.rows;
      if (!rows.length) {
        body.innerHTML = `<tr><td colspan="6"><div class="empty">Nenhum cliente devendo encontrado.</div></td></tr>`;
        return;
      }
      body.innerHTML = rows.map((row, idx) => {
        const key = String(row.customer_key || idx);
        const open = repState.expanded.has(key);
        const hot = idx === 0 ? 'hot' : '';
        return `
          <tr class="rep-row ${open ? 'open' : ''}" data-key="${esc(key)}">
            <td>
              <div class="customer-cell">
                <span class="debt-bar ${hot}"></span>
                <div>
                  <span class="customer-name">${esc(row.customer_name || 'Cliente')}</span>
                  <span class="muted">${row.phone ? 'Tel ' + esc(row.phone) : 'Telefone nao salvo'} | ${brNumber(row.max_days_overdue)} dia(s) max.</span>
                </div>
              </div>
            </td>
            <td><span class="pill"><i class="fa-solid fa-coins"></i> ${brMoney(row.total_amount)}</span></td>
            <td><span class="pill"><i class="fa-solid fa-file-invoice"></i> ${brNumber(row.open_bills)}</span></td>
            <td><span class="pill"><i class="fa-regular fa-calendar"></i> ${esc(brDate(row.oldest_due_at))}</span></td>
            <td><span class="pill"><i class="fa-solid fa-paper-plane"></i> ${brNumber(row.reminders_sent)}</span></td>
            <td><span class="pill"><i class="fa-solid ${open ? 'fa-chevron-up' : 'fa-chevron-down'}"></i> ${open ? 'Fechar' : 'Abrir'}</span></td>
          </tr>
          ${open ? `<tr class="detail-row"><td colspan="6"><div class="detail-panel">${renderBills(row)}</div></td></tr>` : ''}
        `;
      }).join('');
    }

    function applyData(data){
      const s = data.summary || {};
      const meta = data.meta || {};
      const vindi = meta.vindi || {};
      $rep('mDebtors').textContent = brNumber(s.debtors);
      $rep('mAmount').textContent = brMoney(s.total_amount);
      $rep('mBills').textContent = brNumber(s.open_bills);
      $rep('mOverdue').textContent = brNumber(s.overdue_bills);
      $rep('mReminders').textContent = brNumber(s.reminders_sent);
      const vindiText = meta.sync
        ? (vindi.enabled
          ? `Vindi: ${brNumber(vindi.bills_read)} fatura(s), ${brNumber(vindi.pages_read)} pagina(s), ${brNumber(vindi.saved_local || meta.saved_local || 0)} salva(s), parada: ${esc(vindi.stopped_by || '-')}${vindi.error ? ' | ' + esc(vindi.error) : ''}`
          : `Sincronizacao Vindi indisponivel${vindi.error ? ': ' + esc(vindi.error) : ''}`)
        : 'Leitura rapida pelo banco local';
      $rep('repMeta').innerHTML = `
        <i class="fa-solid fa-database"></i>
        <span>Local: ${brNumber(meta.local_rows || 0)} registro(s) | Consolidado: ${brNumber(meta.merged_bills || 0)} fatura(s) | ${vindiText}</span>
      `;
      renderLeader(s.top_debtor);
      repState.rows = Array.isArray(data.rows) ? data.rows : [];
      renderRows();
    }

    async function loadReports(sync=false){
      if (reportsLoading) return;
      reportsLoading = true;
      const btnLoad = $rep('btnLoadReports');
      const btnSync = $rep('btnSyncReports');
      const originalSync = btnSync ? btnSync.innerHTML : '';
      setStatus(sync ? 'Sincronizando Vindi...' : 'Carregando local...');
      if (btnLoad) btnLoad.disabled = true;
      if (btnSync) {
        btnSync.disabled = true;
        if (sync) btnSync.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sincronizando';
      }
      $rep('repBody').innerHTML = `
        <tr><td colspan="6" style="text-align:center;padding:38px;"><div class="spinner"></div><div class="muted" style="margin-top:12px;">${sync ? 'Puxando Vindi e salvando no banco local...' : 'Carregando relatorios locais...'}</div></td></tr>
      `;
      try {
        const url = new URL('/painel/api/reports.php', location.origin);
        const q = $rep('repQ').value.trim();
        if (q) url.searchParams.set('q', q);
        url.searchParams.set('local_limit', '20000');
        if (sync) {
          url.searchParams.set('sync', '1');
          url.searchParams.set('max_pages', '200');
        }
        const resp = await fetch(url.toString(), {credentials:'same-origin', cache:'no-store'});
        const text = await resp.text();
        let data = null;
        try { data = JSON.parse(text); } catch(e) {}
        if (resp.status === 401) {
          location.href = '/painel/';
          return;
        }
        if (!resp.ok || !data || data.ok === false) {
          throw new Error(data?.error || text.slice(0, 220) || 'Falha ao carregar relatorios');
        }
        applyData(data);
        setStatus((sync ? 'Sincronizado em ' : 'Atualizado em ') + new Date().toLocaleString('pt-BR'));
      } catch (e) {
        setStatus(e.message || 'Erro ao carregar', true);
        $rep('repBody').innerHTML = `<tr><td colspan="6"><div class="empty">${esc(e.message || 'Erro ao carregar relatorios.')}</div></td></tr>`;
      } finally {
        reportsLoading = false;
        if (btnLoad) btnLoad.disabled = false;
        if (btnSync) {
          btnSync.disabled = false;
          btnSync.innerHTML = originalSync;
        }
      }
    }

    $rep('repBody').addEventListener('click', (e) => {
      const row = e.target.closest('tr.rep-row[data-key]');
      if (!row) return;
      const key = row.getAttribute('data-key');
      if (repState.expanded.has(key)) repState.expanded.delete(key);
      else repState.expanded.add(key);
      renderRows();
    });

    $rep('btnLoadReports').onclick = () => loadReports(false);
    $rep('btnSyncReports').onclick = () => loadReports(true);
    $rep('repQ').addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        repState.expanded.clear();
        loadReports(false);
      }
    });

    loadReports(false);
    setInterval(() => loadReports(false), 60000);
  </script>
</div>
