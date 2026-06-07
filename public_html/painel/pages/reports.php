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
    .month-control{width:210px;padding-left:18px;flex:0 0 210px;cursor:pointer;}
    .month-control.is-hidden{display:none;}
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
      display:grid;gap:10px;align-content:center;min-height:96px;box-sizing:border-box;transition:.2s;
    }
    .metric:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover);border-color:#dbeafe;}
    .metric-label{display:flex;align-items:center;gap:8px;color:var(--text-muted);font-size:12px;font-weight:900;text-transform:uppercase;white-space:nowrap;}
    .metric-label i{width:28px;height:28px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:#eef8ff;color:#12628f;font-size:13px;flex:0 0 28px;}
    .metric.danger .metric-label i{background:var(--red-bg);color:var(--red-text);}
    .metric.warn .metric-label i{background:var(--yellow-bg);color:var(--yellow-text);}
    .metric.ok .metric-label i{background:var(--green-bg);color:var(--green-text);}
    .metric strong{display:block;font-size:24px;font-weight:900;line-height:1.05;}
    .leader-strip{
      background:#fff;border:2px solid var(--border-color);border-radius:var(--radius-card);box-shadow:var(--shadow-soft);padding:16px 18px;
      display:grid;grid-template-columns:1fr repeat(3,max-content);align-items:center;gap:16px;margin-bottom:18px;
    }
    .month-board{
      background:#fff;border:2px solid var(--border-color);border-radius:var(--radius-card);box-shadow:var(--shadow-soft);
      padding:14px;margin:0 0 18px;display:grid;gap:12px;
    }
    .month-board-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .month-board-title{display:flex;align-items:center;gap:9px;color:var(--text-main);font-size:14px;font-weight:900;}
    .month-board-title i{color:var(--primary);}
    .month-years{display:grid;gap:12px;max-height:236px;overflow:hidden;transition:max-height .2s ease;}
    .month-board.expanded .month-years{max-height:760px;overflow:auto;padding-right:4px;}
    .month-year{display:grid;gap:8px;}
    .month-year-label{color:var(--text-muted);font-size:12px;font-weight:900;}
    .month-grid{display:grid;grid-template-columns:repeat(6,minmax(92px,1fr));gap:8px;}
    .month-btn{
      min-height:48px;border:2px solid var(--border-color);border-radius:14px;background:#fff;color:var(--text-main);
      display:flex;align-items:center;justify-content:space-between;gap:8px;text-align:left;padding:8px 10px;font-family:'Nunito',sans-serif;font-weight:900;cursor:pointer;
      box-shadow:var(--shadow-soft);transition:.18s;
    }
    .month-btn:hover{transform:translateY(-1px);border-color:#bfe8ff;color:#12628f;}
    .month-btn.active{background:#eef8ff;border-color:#9bdcff;color:#12628f;}
    .month-btn span{font-size:12px;text-transform:uppercase;}
    .month-btn small{font-size:11px;color:var(--text-muted);font-weight:900;white-space:nowrap;}
    .meta-line{
      margin:-6px 0 18px;color:var(--text-muted);font-size:12px;font-weight:800;display:flex;align-items:center;gap:8px;flex-wrap:wrap;
    }
    .meta-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border:2px solid var(--border-color);border-radius:999px;background:#fff;box-shadow:var(--shadow-soft);white-space:nowrap;}
    .meta-chip i{color:var(--primary);font-size:12px;}
    .meta-chip.ok i{color:var(--green-text);}
    .meta-chip.warn i{color:var(--yellow-text);}
    .meta-chip.danger i{color:var(--red-text);}
    .leader-name{font-weight:900;font-size:16px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .leader-sub{display:block;color:var(--text-muted);font-size:12px;font-weight:800;margin-top:3px;}
    .pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:var(--bg-panel);border:2px solid var(--border-color);font-size:12px;font-weight:900;white-space:nowrap;}
    table{width:100%;border-collapse:separate;border-spacing:0 10px;}
    thead th{color:var(--text-muted);font-size:12px;text-transform:uppercase;font-weight:900;padding:0 16px;text-align:left;}
    thead th:last-child, tbody td:last-child{text-align:right;}
    tbody tr.rep-row{background:#fff;box-shadow:var(--shadow-soft);border-radius:16px;transition:.2s;cursor:pointer;}
    tbody tr.rep-row:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover);}
    tbody tr.rep-row.open{box-shadow:var(--shadow-hover);}
    tbody td{padding:14px 16px;border-top:2px solid var(--border-color);border-bottom:2px solid var(--border-color);font-size:13px;font-weight:800;vertical-align:middle;}
    tbody td:first-child{border-left:2px solid var(--border-color);border-top-left-radius:16px;border-bottom-left-radius:16px;}
    tbody td:last-child{border-right:2px solid var(--border-color);border-top-right-radius:16px;border-bottom-right-radius:16px;}
    .customer-cell{display:grid;grid-template-columns:38px 1fr;gap:12px;align-items:center;}
    .debt-bar{width:38px;height:38px;border-radius:12px;background:#eef8ff;color:#12628f;box-shadow:none;display:flex;align-items:center;justify-content:center;}
    .debt-bar::before{content:"\\f007";font-family:"Font Awesome 6 Free";font-weight:900;font-size:14px;}
    .debt-bar.hot{background:#ef4444;box-shadow:0 0 0 4px #fee2e2;}
    .debt-bar.hot::before{color:#fff;}
    .customer-name{font-size:15px;font-weight:900;display:block;}
    .muted{color:var(--text-muted);font-size:12px;font-weight:800;margin-top:3px;display:block;}
    .detail-row td{padding:0 16px 18px;background:#fff;border:none;}
    .detail-panel{border:2px solid #e6eef7;border-radius:16px;background:#fbfdff;padding:14px;display:grid;gap:10px;}
    .bill-line{
      background:#fff;border:1px solid var(--border-color);border-radius:16px;padding:14px;display:grid;
      grid-template-columns:minmax(190px,.8fr) minmax(0,1.4fr) minmax(210px,.8fr);
      gap:14px;align-items:stretch;
    }
    .bill-head{display:grid;gap:8px;align-content:start;}
    .bill-tag{display:inline-flex;align-items:center;gap:7px;width:max-content;max-width:100%;padding:6px 10px;border-radius:999px;background:#eef8ff;color:#12628f;font-size:12px;font-weight:900;text-decoration:none;}
    .bill-amount{font-size:20px;font-weight:900;line-height:1.05;color:var(--text-main);}
    .bill-due{display:flex;align-items:flex-start;gap:8px;color:var(--text-muted);font-size:12px;font-weight:900;line-height:1.25;}
    .bill-due i{color:var(--primary);margin-top:1px;}
    .bill-main{display:grid;gap:8px;align-content:start;}
    .bill-label{display:flex;align-items:center;gap:8px;color:var(--text-muted);font-size:11px;font-weight:900;text-transform:uppercase;}
    .bill-label i{color:var(--primary);}
    .bill-items{white-space:pre-wrap;word-break:break-word;font-size:14px;font-weight:900;line-height:1.38;color:var(--text-main);}
    .bill-side{display:grid;gap:8px;align-content:start;justify-items:end;text-align:right;}
    .bill-mini{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:999px;background:var(--bg-panel);border:2px solid var(--border-color);font-size:12px;font-weight:900;}
    .bill-mini i{color:var(--primary);}
    .bill-mini.ok i{color:var(--green-text);}
    .bill-mini.warn i{color:var(--yellow-text);}
    .bill-link{color:#12628f;text-decoration:none;font-weight:900;}
    .bill-link:hover{text-decoration:underline;}
    .empty{color:var(--text-muted);font-size:13px;font-weight:800;text-align:center;padding:28px 12px;background:var(--bg-panel);border-radius:16px;}
    .spinner{width:30px;height:30px;border:4px solid #e2e8f0;border-top-color:var(--primary);border-radius:50%;animation:spin .8s linear infinite;margin:0 auto;}
    @keyframes spin{to{transform:rotate(360deg)}}
    @keyframes pulse-dot{0%{opacity:.5;transform:scale(1)}50%{opacity:1;transform:scale(1.3)}100%{opacity:.5;transform:scale(1)}}
    @media(max-width:1060px){
      .summary-grid{grid-template-columns:repeat(2,minmax(150px,1fr));}
      .leader-strip{grid-template-columns:1fr 1fr;}
      .month-grid{grid-template-columns:repeat(4,minmax(88px,1fr));}
      .bill-line{grid-template-columns:1fr;}
      .bill-side{justify-items:start;text-align:left;grid-template-columns:repeat(3,max-content);align-items:center;overflow-x:auto;}
      table{min-width:980px;}
      .table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}
    }
    @media(max-width:680px){
      .rep-header{align-items:flex-start;flex-direction:column;height:auto;min-height:62px;padding:14px 16px;border-radius:12px;gap:10px;}
      .rep-container{padding:0 8px;}
      .toolbar{border-radius:14px;align-items:stretch;flex-direction:column;}
      .search-box{min-width:0;width:100%;}
      .month-control{width:100%;flex:auto;}
      .summary-grid,.leader-strip,.bill-line{grid-template-columns:1fr;}
      .month-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
      .month-board-head{align-items:stretch;flex-direction:column;}
      #btnAllMonths{width:100%;justify-content:center;}
      .month-board{padding:12px;border-radius:16px;}
      .metric{min-height:82px;}
      .btn-primary{width:100%;}
      .btn-secondary{width:100%;}
      .meta-chip{width:100%;box-sizing:border-box;}
    }
    @media(max-width:620px){
      .summary-grid{grid-template-columns:1fr 1fr;}
      .metric{padding:13px;min-height:78px;}
      .metric strong{font-size:20px;}
      .metric-label{font-size:11px;}
      .table-scroll{overflow:visible;}
      table,thead,tbody,tr,td{display:block;width:100%;min-width:0;box-sizing:border-box;}
      table{border-spacing:0;}
      thead{display:none;}
      tbody tr.rep-row{padding:14px;margin-bottom:12px;border:2px solid var(--border-color);border-radius:16px;}
      tbody tr.rep-row:hover{transform:none;}
      tbody td,
      tbody td:first-child,
      tbody td:last-child{border:0;padding:7px 0;border-radius:0;text-align:left;}
      tbody td:last-child{text-align:left;}
      .pill{white-space:normal;}
      .detail-row td{padding:0 0 14px;}
      .detail-panel{padding:10px;border-radius:14px;}
      .bill-side{display:grid;grid-template-columns:1fr;justify-items:stretch;}
      .bill-mini{justify-content:center;}
    }
    @media(max-width:390px){
      .summary-grid{grid-template-columns:1fr;}
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
      <select id="monthFilter" class="form-control month-control is-hidden" title="Filtrar por mes">
        <option value="">Todos os meses</option>
      </select>
      <button id="btnLoadReports" class="btn-primary" type="button"><i class="fa-solid fa-rotate"></i> Atualizar</button>
      <button id="btnSyncReports" class="btn-secondary" type="button"><i class="fa-solid fa-cloud-arrow-down"></i> Sincronizar Vindi</button>
    </div>

    <div class="month-board" id="monthBoard">
      <div class="month-board-head">
        <div class="month-board-title"><i class="fa-regular fa-calendar"></i> Meses com faturas</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button type="button" class="btn-secondary" id="btnAllMonths" style="height:36px;padding:0 14px;"><i class="fa-solid fa-layer-group"></i> Todos</button>
          <button type="button" class="btn-secondary" id="btnToggleMonths" style="height:36px;padding:0 14px;"><i class="fa-solid fa-chevron-down"></i> Ver mais</button>
        </div>
      </div>
      <div class="month-years" id="monthYears">
        <div class="empty">Carregando meses...</div>
      </div>
    </div>

    <div class="summary-grid">
      <div class="metric"><span class="metric-label"><i class="fa-solid fa-users"></i> Devedores</span><strong id="mDebtors">0</strong></div>
      <div class="metric danger"><span class="metric-label"><i class="fa-solid fa-coins"></i> Total</span><strong id="mAmount">R$ 0,00</strong></div>
      <div class="metric warn"><span class="metric-label"><i class="fa-solid fa-file-invoice"></i> Faturas</span><strong id="mBills">0</strong></div>
      <div class="metric"><span class="metric-label"><i class="fa-solid fa-triangle-exclamation"></i> Vencidas</span><strong id="mOverdue">0</strong></div>
      <div class="metric ok"><span class="metric-label"><i class="fa-brands fa-whatsapp"></i> Recobrancas</span><strong id="mReminders">0</strong></div>
    </div>

    <div class="leader-strip" id="leaderStrip">
      <div>
        <span class="leader-name"><i class="fa-solid fa-crown" style="color:#eab308;margin-right:8px;"></i>Maior devedor</span>
        <span class="leader-sub">-</span>
      </div>
      <span class="pill"><i class="fa-solid fa-coins"></i> R$ 0,00</span>
      <span class="pill"><i class="fa-solid fa-file-invoice"></i> 0 parcelas</span>
      <span class="pill"><i class="fa-solid fa-paper-plane"></i> 0 recobrancas</span>
    </div>

    <div class="meta-line" id="repMeta">
      <span class="meta-chip"><i class="fa-solid fa-database"></i> Local 0</span>
      <span class="meta-chip"><i class="fa-solid fa-file-invoice"></i> 0 faturas</span>
      <span class="meta-chip"><i class="fa-solid fa-bolt"></i> Cache local</span>
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
    let selectedMonth = new URLSearchParams(location.search).get('month') || '';
    let monthsExpanded = false;

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
            <span class="leader-name"><i class="fa-solid fa-crown" style="color:#eab308;margin-right:8px;"></i>Maior devedor</span>
            <span class="leader-sub">-</span>
          </div>
          <span class="pill"><i class="fa-solid fa-coins"></i> R$ 0,00</span>
          <span class="pill"><i class="fa-solid fa-file-invoice"></i> 0 parcelas</span>
          <span class="pill"><i class="fa-solid fa-paper-plane"></i> 0 recobrancas</span>
        `;
        return;
      }
      box.innerHTML = `
        <div>
          <span class="leader-name"><i class="fa-solid fa-crown" style="color:#eab308;margin-right:8px;"></i>${esc(row.customer_name || 'Cliente')}</span>
          <span class="leader-sub">${row.phone ? 'Tel ' + esc(row.phone) : '-'}</span>
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
          ? `<a class="bill-tag" href="${esc(billUrl)}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Bill ${esc(bill.bill_id)}</a>`
          : `<span class="bill-tag"><i class="fa-solid fa-file-invoice"></i> Bill ${esc(bill.bill_id)}</span>`;
        const overdue = Number(bill.days_overdue || 0);
        const dueText = `${brDate(bill.due_at)}${overdue ? ` | ${overdue} dia(s) vencida` : ''}`;
        const lastText = brDateTime(bill.last_reminder_at);
        return `
          <div class="bill-line">
            <div class="bill-head">
              ${billLabel}
              <div class="bill-amount">${brMoney(bill.amount)}</div>
              <div class="bill-due"><i class="fa-regular fa-calendar"></i><span>${esc(dueText)}</span></div>
            </div>
            <div class="bill-main">
              <div class="bill-label"><i class="fa-solid fa-list-check"></i> O que esta devendo</div>
              <div class="bill-items">${esc(bill.items_text || 'Sem itens informados')}</div>
            </div>
            <div class="bill-side">
              <span class="bill-mini ok"><i class="fa-brands fa-whatsapp"></i> ${brNumber(bill.reminders_sent)} envio(s)</span>
              <span class="bill-mini"><i class="fa-regular fa-clock"></i> ${esc(lastText === '-' ? 'Sem envio' : lastText)}</span>
              <span class="bill-mini warn"><i class="fa-solid fa-database"></i> ${esc(sourceLabel(bill.source))}</span>
            </div>
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
      renderMonthOptions(meta.available_months || [], meta.selected_month || selectedMonth);
      $rep('mDebtors').textContent = brNumber(s.debtors);
      $rep('mAmount').textContent = brMoney(s.total_amount);
      $rep('mBills').textContent = brNumber(s.open_bills);
      $rep('mOverdue').textContent = brNumber(s.overdue_bills);
      $rep('mReminders').textContent = brNumber(s.reminders_sent);
      const chips = [
        `<span class="meta-chip"><i class="fa-solid fa-database"></i> Local ${brNumber(meta.local_rows || 0)}</span>`,
        `<span class="meta-chip"><i class="fa-solid fa-file-invoice"></i> ${brNumber(meta.merged_bills || 0)} faturas</span>`
      ];
      if (meta.sync && vindi.enabled) {
        chips.push(`<span class="meta-chip ok"><i class="fa-solid fa-cloud-arrow-down"></i> Vindi ${brNumber(vindi.bills_read || 0)}</span>`);
        chips.push(`<span class="meta-chip"><i class="fa-solid fa-floppy-disk"></i> Salvas ${brNumber(vindi.saved_local || meta.saved_local || 0)}</span>`);
        chips.push(`<span class="meta-chip warn"><i class="fa-solid fa-rotate"></i> Check ${brNumber(vindi.status_checked || 0)}</span>`);
        if (Number(vindi.settled_local || 0) > 0) {
          chips.push(`<span class="meta-chip ok"><i class="fa-solid fa-circle-check"></i> Pagas ${brNumber(vindi.settled_local || 0)}</span>`);
        }
        if (vindi.error) {
          chips.push(`<span class="meta-chip danger"><i class="fa-solid fa-triangle-exclamation"></i> ${esc(vindi.error)}</span>`);
        }
      } else if (meta.sync) {
        chips.push(`<span class="meta-chip danger"><i class="fa-solid fa-cloud-slash"></i> Vindi off</span>`);
      } else {
        chips.push(`<span class="meta-chip ok"><i class="fa-solid fa-bolt"></i> Cache local</span>`);
      }
      $rep('repMeta').innerHTML = chips.join('');
      renderLeader(s.top_debtor);
      repState.rows = Array.isArray(data.rows) ? data.rows : [];
      renderRows();
    }

    function renderMonthOptions(months, current){
      const select = $rep('monthFilter');
      const board = $rep('monthYears');
      if (!select) return;
      const prev = current || selectedMonth || select.value || '';
      const now = new Date();
      const currentMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
      const rows = (Array.isArray(months) ? months : []).filter(row => String(row.value || '') <= currentMonth);
      let exists = prev === '';
      const options = ['<option value="">Todos os meses</option>'];
      rows.forEach(row => {
        const value = String(row.value || '');
        if (!value) return;
        if (value === prev) exists = true;
        const total = Number(row.total || 0);
        const label = `${row.label || value}${total ? ' (' + brNumber(total) + ')' : ''}`;
        options.push(`<option value="${esc(value)}">${esc(label)}</option>`);
      });
      select.innerHTML = options.join('');
      selectedMonth = exists ? prev : '';
      select.value = selectedMonth;

      if (!board) return;
      if (!rows.length) {
        board.innerHTML = '<div class="empty">Nenhum mes com fatura aberta.</div>';
        return;
      }
      const years = new Map();
      rows.forEach(row => {
        const value = String(row.value || '');
        const year = value.slice(0, 4) || 'Sem ano';
        if (!years.has(year)) years.set(year, []);
        years.get(year).push(row);
      });
      board.innerHTML = Array.from(years.entries()).map(([year, yearRows]) => `
        <div class="month-year">
          <div class="month-year-label">${esc(year)}</div>
          <div class="month-grid">
            ${yearRows.map(row => {
              const value = String(row.value || '');
              const shortLabel = String(row.label || value).replace(/\s+\d{4}$/, '').slice(0, 3);
              const total = Number(row.total || 0);
              const active = value === selectedMonth ? 'active' : '';
              return `
                <button type="button" class="month-btn ${active}" data-month="${esc(value)}">
                  <span>${esc(shortLabel)}</span>
                  <small>${brNumber(total)} fatura(s)</small>
                </button>
              `;
            }).join('')}
          </div>
        </div>
      `).join('');
      const monthBoard = $rep('monthBoard');
      const toggle = $rep('btnToggleMonths');
      if (monthBoard) monthBoard.classList.toggle('expanded', monthsExpanded);
      if (toggle) {
        toggle.innerHTML = monthsExpanded
          ? '<i class="fa-solid fa-chevron-up"></i> Ver menos'
          : '<i class="fa-solid fa-chevron-down"></i> Ver mais';
        toggle.style.display = rows.length > 12 ? 'inline-flex' : 'none';
      }
    }

    function syncUrlState(){
      const url = new URL(location.href);
      url.searchParams.set('pagina', 'reports');
      if (selectedMonth) url.searchParams.set('month', selectedMonth);
      else url.searchParams.delete('month');
      history.replaceState(null, '', url.toString());
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
        if (selectedMonth) url.searchParams.set('month', selectedMonth);
        url.searchParams.set('local_limit', '20000');
        if (sync) {
          url.searchParams.set('sync', '1');
          url.searchParams.set('max_pages', '200');
          url.searchParams.set('status_limit', '5000');
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
        syncUrlState();
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
    $rep('monthFilter').addEventListener('change', () => {
      selectedMonth = $rep('monthFilter').value;
      repState.expanded.clear();
      loadReports(false);
    });
    $rep('monthYears').addEventListener('click', (event) => {
      const btn = event.target.closest('button[data-month]');
      if (!btn) return;
      selectedMonth = btn.getAttribute('data-month') || '';
      repState.expanded.clear();
      loadReports(false);
    });
    $rep('btnAllMonths').addEventListener('click', () => {
      selectedMonth = '';
      repState.expanded.clear();
      loadReports(false);
    });
    $rep('btnToggleMonths').addEventListener('click', () => {
      monthsExpanded = !monthsExpanded;
      $rep('monthBoard').classList.toggle('expanded', monthsExpanded);
      $rep('btnToggleMonths').innerHTML = monthsExpanded
        ? '<i class="fa-solid fa-chevron-up"></i> Ver menos'
        : '<i class="fa-solid fa-chevron-down"></i> Ver mais';
    });
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
