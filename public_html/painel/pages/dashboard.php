<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }

$defaultCost = cfg($cfg, 'WHATSAPP_MESSAGE_COST_BRL', cfg($cfg, 'META_MESSAGE_COST_BRL', '0'));
?>
<div class="dash-wrap">
  <style>
    .dash-wrap{
      --bg-body:#ffffff;
      --bg-panel:#f4f7fa;
      --primary:#38b6ff;
      --primary-hover:rgb(39, 121, 169);
      --text-main:#1e293b;
      --text-muted:#64748b;
      --border-color:#eef2f6;
      --green-bg:#d1fae5;
      --green-text:#065f46;
      --red-bg:#fee2e2;
      --red-text:#991b1b;
      --blue-bg:#dbeafe;
      --blue-text:#1e40af;
      --yellow-bg:#fef3c7;
      --yellow-text:#92400e;
      --radius-pill:50px;
      --radius-card:20px;
      --shadow-soft:0 4px 6px -1px rgba(0,0,0,.05),0 2px 4px -1px rgba(0,0,0,.03);
      --shadow-hover:0 10px 15px -3px rgba(59,130,246,.15);
      font-family:'Nunito',sans-serif;
      color:var(--text-main);
    }
    .dash-head{
      background:#ffffff;
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
    .dash-title{display:flex;align-items:center;gap:12px;min-width:0;}
    .dash-title i{
      color:var(--primary);
      font-size:24px;
      width:auto;
      height:auto;
      background:transparent;
    }
    .dash-title h1{font-size:22px;line-height:1.1;margin:0;font-weight:800;letter-spacing:0;}
    .dash-title p{margin:4px 0 0;color:var(--text-muted);font-size:12px;font-weight:700;}
    .status-pill{
      font-size:13px;
      color:var(--text-main);
      background:var(--bg-panel);
      padding:8px 20px;
      border-radius:var(--radius-pill);
      font-weight:700;
      border:2px solid var(--border-color);
      display:flex;
      align-items:center;
      gap:8px;
      transition:all .5s ease;
      white-space:nowrap;
    }
    .status-dot{width:8px;height:8px;border-radius:50%;background:var(--primary);display:inline-block;animation:pulse-dot 1.5s infinite;}
    .dash-container{max-width:1100px;margin:0 auto;padding:0 25px;}

    .filters{
      background:#ffffff;
      border:2px solid var(--border-color);
      border-radius:var(--radius-pill);
      padding:12px 20px;
      display:flex;
      gap:12px;
      align-items:center;
      box-shadow:var(--shadow-soft);
      margin-bottom:28px;
      flex-wrap:wrap;
      transition:box-shadow .3s;
    }
    .filters:hover{box-shadow:var(--shadow-hover);}
    .field{display:grid;gap:6px;min-width:150px;flex:1;}
    .field label{font-size:11px;text-transform:uppercase;color:var(--text-muted);font-weight:900;padding-left:12px;}
    .field input{
      height:45px;
      border:2px solid transparent;
      border-radius:var(--radius-pill);
      background:var(--bg-panel);
      color:var(--text-main);
      padding:0 18px;
      font-family:'Nunito',sans-serif;
      font-size:15px;
      font-weight:700;
      outline:none;
      box-sizing:border-box;
      min-width:0;
    }
    .field input:focus{background:#fff;border-color:var(--primary);box-shadow:0 0 0 4px rgba(59,130,246,.1);}
    .icon-btn{
      height:45px;
      border:0;
      border-radius:var(--radius-pill);
      background:var(--primary);
      color:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      padding:0 22px;
      cursor:pointer;
      font-size:15px;
      font-weight:700;
      font-family:'Nunito',sans-serif;
      box-shadow:0 4px 6px rgba(59,130,246,.2);
      transition:all .2s cubic-bezier(.4,0,.2,1);
    }
    .icon-btn:hover{transform:translateY(-2px);background:var(--primary-hover);}
    .icon-btn:active{transform:translateY(1px) scale(.95);box-shadow:none;}

    .metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px;}
    .metric{
      background:#ffffff;
      border:2px solid var(--border-color);
      border-radius:var(--radius-card);
      box-shadow:var(--shadow-soft);
      padding:18px 20px;
      min-height:132px;
      box-sizing:border-box;
      display:grid;
      align-content:space-between;
      gap:12px;
      transition:all .2s ease;
    }
    .metric:hover{transform:translateY(-3px) scale(1.005);box-shadow:var(--shadow-hover);border-color:#dbeafe;}
    .metric .top{display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .metric .label{font-size:12px;color:var(--text-muted);font-weight:900;text-transform:uppercase;}
    .metric .icon{
      width:38px;height:38px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:#eff6ff;color:var(--primary);
    }
    .metric.ok .icon{background:var(--green-bg);color:var(--green-text);}
    .metric.warn .icon{background:var(--yellow-bg);color:var(--yellow-text);}
    .metric.danger .icon{background:var(--red-bg);color:var(--red-text);}
    .metric strong{display:block;font-size:26px;line-height:1;font-weight:900;letter-spacing:0;}
    .metric span{font-size:12px;color:var(--text-muted);font-weight:800;}

    .dash-layout{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(360px,.8fr);gap:14px;}
    .panel{
      background:#ffffff;
      border:2px solid var(--border-color);
      border-radius:var(--radius-card);
      box-shadow:var(--shadow-soft);
      padding:20px;
      min-width:0;
      box-sizing:border-box;
      transition:box-shadow .2s ease,border-color .2s ease;
    }
    .panel:hover{box-shadow:var(--shadow-hover);border-color:#dbeafe;}
    .panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;}
    .panel-title{font-size:16px;font-weight:900;margin:0;}
    .panel-sub{font-size:12px;color:var(--text-muted);font-weight:800;}
    .bar-chart{display:grid;gap:9px;}
    .bar-row{display:grid;grid-template-columns:92px minmax(0,1fr) 70px;gap:10px;align-items:center;font-size:12px;font-weight:900;color:var(--text-muted);}
    .bar-track{height:13px;background:var(--bg-panel);border-radius:999px;overflow:hidden;border:1px solid var(--border-color);}
    .bar-fill{height:100%;min-width:2px;background:var(--primary);border-radius:999px;}
    .split-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;}
    .mini-list{display:grid;gap:10px;}
    .mini-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #eef2f6;}
    .mini-row:last-child{border-bottom:0;}
    .mini-main{min-width:0;}
    .mini-main strong{display:block;font-size:13px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .mini-main span{display:block;font-size:11px;color:var(--text-muted);font-weight:800;margin-top:2px;}
    .mini-value{text-align:right;font-size:13px;font-weight:900;white-space:nowrap;}
    .empty{color:var(--text-muted);font-size:13px;font-weight:800;text-align:center;padding:28px 12px;background:var(--bg-panel);border-radius:16px;}
    .note{
      margin-top:14px;border:2px solid var(--border-color);background:#ffffff;border-radius:var(--radius-card);padding:14px 16px;
      color:var(--text-muted);font-size:12px;line-height:1.45;font-weight:800;box-shadow:var(--shadow-soft);
    }
    .table{width:100%;border-collapse:separate;border-spacing:0 9px;}
    .table th{text-align:left;color:var(--text-muted);font-size:12px;text-transform:uppercase;padding:0 12px;font-weight:900;}
    .table td{background:#ffffff;border-top:2px solid var(--border-color);border-bottom:2px solid var(--border-color);padding:12px;font-size:13px;font-weight:800;vertical-align:middle;}
    .table td:first-child{border-left:2px solid var(--border-color);border-top-left-radius:16px;border-bottom-left-radius:16px;}
    .table td:last-child{border-right:2px solid var(--border-color);border-top-right-radius:16px;border-bottom-right-radius:16px;}
    .table td:last-child,.table th:last-child{text-align:right;}
    @keyframes pulse-dot{0%{opacity:.5;transform:scale(1);}50%{opacity:1;transform:scale(1.3);}100%{opacity:.5;transform:scale(1);}}

    @media(max-width:1120px){
      .metric-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
      .dash-layout{grid-template-columns:1fr;}
    }
    @media(max-width:680px){
      .dash-head{align-items:flex-start;flex-direction:column;height:auto;padding:18px 20px;}
      .dash-container{padding:0 8px;}
      .filters{border-radius:24px;align-items:stretch;}
      .field{min-width:100%;flex-basis:100%;}
      .icon-btn{width:100%;}
      .metric-grid,.split-grid{grid-template-columns:1fr;}
      .bar-row{grid-template-columns:72px minmax(0,1fr) 54px;}
    }
  </style>

  <div class="dash-head">
    <div class="dash-title">
      <i class="fa-solid fa-chart-line"></i>
      <div>
        <h1>Dashboard</h1>
        <p>Custos de WhatsApp, recobrancas recuperadas e volume de mensagens.</p>
      </div>
    </div>
    <div class="status-pill"><span class="status-dot" id="dashDot"></span><span id="dashStatus">Aguardando...</span></div>
  </div>

  <div class="dash-container">
  <div class="filters">
    <div class="field">
      <label for="month">Mes</label>
      <input id="month" type="month">
    </div>
    <div class="field">
      <label for="start">Inicio</label>
      <input id="start" type="date">
    </div>
    <div class="field">
      <label for="end">Fim</label>
      <input id="end" type="date">
    </div>
    <div class="field">
      <label for="unitCost">Custo por mensagem (R$)</label>
      <input id="unitCost" inputmode="decimal" value="<?= htmlspecialchars($defaultCost, ENT_QUOTES, 'UTF-8') ?>" placeholder="0,00">
    </div>
    <button class="icon-btn" id="btnLoadDash" type="button" title="Atualizar dashboard">
      Atualizar <i class="fa-solid fa-rotate"></i>
    </button>
  </div>

  <div class="metric-grid">
    <div class="metric">
      <div class="top"><div class="label">Custo estimado</div><div class="icon"><i class="fa-solid fa-coins"></i></div></div>
      <div><strong id="mCost">R$ 0,00</strong><span id="mCostSub">0 mensagens enviadas</span></div>
    </div>
    <div class="metric ok">
      <div class="top"><div class="label">Cobranca recuperada</div><div class="icon"><i class="fa-solid fa-hand-holding-dollar"></i></div></div>
      <div><strong id="mRecovered">R$ 0,00</strong><span id="mRecoveredSub">0 faturas recuperadas</span></div>
    </div>
    <div class="metric warn">
      <div class="top"><div class="label">Resultado liquido</div><div class="icon"><i class="fa-solid fa-scale-balanced"></i></div></div>
      <div><strong id="mNet">R$ 0,00</strong><span id="mRoi">ROI aguardando custo</span></div>
    </div>
    <div class="metric danger">
      <div class="top"><div class="label">Faturas recuperadas</div><div class="icon"><i class="fa-solid fa-receipt"></i></div></div>
      <div><strong id="mRecoveredCount">0</strong><span>pagas apos modelo</span></div>
    </div>
  </div>

  <div class="metric-grid">
    <div class="metric">
      <div class="top"><div class="label">Mensagens enviadas</div><div class="icon"><i class="fa-brands fa-whatsapp"></i></div></div>
      <div><strong id="mSent">0</strong><span id="mSentSub">0 com sucesso, 0 falhas</span></div>
    </div>
    <div class="metric">
      <div class="top"><div class="label">Modelo fatura</div><div class="icon"><i class="fa-solid fa-file-invoice"></i></div></div>
      <div><strong id="mCharges">0</strong><span id="mChargesSub">modelos enviados</span></div>
    </div>
    <div class="metric">
      <div class="top"><div class="label">Pagamentos</div><div class="icon"><i class="fa-solid fa-circle-check"></i></div></div>
      <div><strong id="mPaid">R$ 0,00</strong><span id="mPaidSub">0 faturas pagas no periodo</span></div>
    </div>
    <div class="metric">
      <div class="top"><div class="label">Fila pronta</div><div class="icon"><i class="fa-solid fa-paper-plane"></i></div></div>
      <div><strong id="mReady">0</strong><span>faturas liberadas para envio</span></div>
    </div>
  </div>

  <div class="dash-layout">
    <div>
      <div class="panel">
        <div class="panel-head">
          <div>
            <h2 class="panel-title">Mensagens por dia</h2>
            <div class="panel-sub">Volume enviado no periodo filtrado</div>
          </div>
        </div>
        <div class="bar-chart" id="seriesChart"></div>
      </div>

      <div class="split-grid">
        <div class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Por origem</h2>
              <div class="panel-sub">Automacao, manual e respostas</div>
            </div>
          </div>
          <div class="mini-list" id="sourceList"></div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Por status</h2>
              <div class="panel-sub">Entrega registrada no chat</div>
            </div>
          </div>
          <div class="mini-list" id="statusList"></div>
        </div>
      </div>
    </div>

    <div>
      <div class="panel">
        <div class="panel-head">
          <div>
            <h2 class="panel-title">Ultimas recuperadas</h2>
            <div class="panel-sub">Pagas apos envio do modelo fatura</div>
          </div>
        </div>
        <table class="table">
          <thead><tr><th>Cliente</th><th>Valor</th></tr></thead>
          <tbody id="recoveries"></tbody>
        </table>
      </div>

      <div class="panel" style="margin-top:14px;">
        <div class="panel-head">
          <div>
            <h2 class="panel-title">Top recuperacao</h2>
            <div class="panel-sub">Clientes por valor recuperado</div>
          </div>
        </div>
        <div class="mini-list" id="topCustomers"></div>
      </div>

      <div class="note" id="dashNote">
        Defina <strong>WHATSAPP_MESSAGE_COST_BRL</strong> no config.env para o custo fixo do dashboard, ou informe o valor no filtro acima.
      </div>
    </div>
  </div>
  </div>

  <script>
    const dashEl = (id) => document.getElementById(id);

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

    function todayMonth(){
      const d = new Date();
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    }

    function setStatus(text, error=false){
      dashEl('dashStatus').textContent = text;
      dashEl('dashDot').style.background = error ? 'var(--danger)' : 'var(--brand)';
    }

    function setMetric(id, value, subId, sub){
      dashEl(id).textContent = value;
      if (subId) dashEl(subId).textContent = sub || '';
    }

    function renderBars(rows){
      const box = dashEl('seriesChart');
      if (!rows.length) {
        box.innerHTML = '<div class="empty">Sem mensagens enviadas neste periodo.</div>';
        return;
      }
      const max = Math.max(...rows.map(r => Number(r.sent || 0)), 1);
      box.innerHTML = rows.map(r => {
        const sent = Number(r.sent || 0);
        const pct = Math.max(3, Math.round((sent / max) * 100));
        return `
          <div class="bar-row">
            <span>${esc(brDate(r.label))}</span>
            <span class="bar-track"><span class="bar-fill" style="width:${pct}%"></span></span>
            <span>${brNumber(sent)}</span>
          </div>
        `;
      }).join('');
    }

    function renderMiniList(id, rows, nameKey, valueKey, formatter){
      const box = dashEl(id);
      if (!rows.length) {
        box.innerHTML = '<div class="empty">Sem dados no periodo.</div>';
        return;
      }
      box.innerHTML = rows.map(row => `
        <div class="mini-row">
          <div class="mini-main">
            <strong>${esc(row[nameKey] || '-')}</strong>
            <span>${esc(row.recovered_bills ? `${row.recovered_bills} fatura(s)` : '')}</span>
          </div>
          <div class="mini-value">${formatter(row[valueKey])}</div>
        </div>
      `).join('');
    }

    function renderRecoveries(rows){
      const body = dashEl('recoveries');
      if (!rows.length) {
        body.innerHTML = '<tr><td colspan="2"><div class="empty">Nenhuma cobranca recuperada no periodo.</div></td></tr>';
        return;
      }
      body.innerHTML = rows.map(row => `
        <tr>
          <td>
            <div class="mini-main">
              <strong>${esc(row.customer_name || 'Cliente')}</strong>
              <span>Bill ${esc(row.bill_id)} - pago em ${esc(brDate(row.paid_at))}</span>
            </div>
          </td>
          <td>${brMoney(row.amount)}</td>
        </tr>
      `).join('');
    }

    function buildUrl(){
      const url = new URL('/painel/api/dashboard.php', location.origin);
      const month = dashEl('month').value;
      const start = dashEl('start').value;
      const end = dashEl('end').value;
      const cost = dashEl('unitCost').value.trim().replace(',', '.');
      if (month && !start && !end) url.searchParams.set('month', month);
      if (start) url.searchParams.set('start', start);
      if (end) url.searchParams.set('end', end);
      if (cost !== '') url.searchParams.set('unit_cost', cost);
      return url;
    }

    function syncPageUrl(data){
      const url = new URL(location.href);
      url.searchParams.set('pagina', 'dashboard');
      url.searchParams.set('month', dashEl('month').value || data?.filters?.month || todayMonth());
      if (dashEl('start').value) url.searchParams.set('start', dashEl('start').value); else url.searchParams.delete('start');
      if (dashEl('end').value) url.searchParams.set('end', dashEl('end').value); else url.searchParams.delete('end');
      if (dashEl('unitCost').value.trim() !== '') url.searchParams.set('unit_cost', dashEl('unitCost').value.trim());
      history.replaceState(null, '', url.toString());
    }

    async function loadDashboard(){
      setStatus('Carregando...');
      try {
        const resp = await fetch(buildUrl().toString(), {cache:'no-store', credentials:'same-origin'});
        const text = await resp.text();
        let data = null;
        try { data = JSON.parse(text); } catch(e) {}
        if (resp.status === 401) {
          location.href = '/painel/';
          return;
        }
        if (!resp.ok || !data || data.ok === false) {
          throw new Error(data?.error || text.slice(0, 220) || 'Falha ao carregar dashboard');
        }

        const s = data.summary || {};
        setMetric('mCost', brMoney(s.message_cost_brl), 'mCostSub', `${brNumber(s.sent_messages)} mensagens cobraveis`);
        setMetric('mRecovered', brMoney(s.recovered_amount_brl), 'mRecoveredSub', `${brNumber(s.recovered_bills)} faturas recuperadas`);
        setMetric('mNet', brMoney(s.net_recovered_brl), 'mRoi', s.roi === null ? 'ROI aguardando custo' : `${Number(s.roi).toFixed(1).replace('.', ',')}x sobre o custo`);
        setMetric('mRecoveredCount', brNumber(s.recovered_bills));
        setMetric('mSent', brNumber(s.sent_messages), 'mSentSub', `${brNumber(s.successful_messages)} com sucesso, ${brNumber(s.failed_messages)} falhas`);
        setMetric('mCharges', brNumber(s.model_invoice_messages ?? s.recobranca_messages), 'mChargesSub', 'modelos de fatura enviados');
        setMetric('mPaid', brMoney(s.paid_amount_brl), 'mPaidSub', `${brNumber(s.paid_bills)} faturas pagas no periodo`);
        setMetric('mReady', brNumber(s.ready_bills));

        renderBars(data.series || []);
        renderMiniList('sourceList', data.message_sources || [], 'source', 'total', brNumber);
        renderMiniList('statusList', data.message_status || [], 'status', 'total', brNumber);
        renderRecoveries(data.recent_recoveries || []);
        renderMiniList('topCustomers', data.top_customers || [], 'customer_name', 'recovered_amount', brMoney);

        const f = data.filters || {};
        if (!dashEl('month').value) dashEl('month').value = f.month || todayMonth();
        if (!dashEl('unitCost').value && f.unit_cost_brl !== undefined) dashEl('unitCost').value = String(f.unit_cost_brl).replace('.', ',');
        dashEl('dashNote').innerHTML = `${esc(data.notes?.cost || '')}<br>${esc(data.notes?.recovered || '')}`;
        syncPageUrl(data);
        setStatus('Atualizado em ' + new Date().toLocaleString('pt-BR'));
      } catch (e) {
        setStatus(e.message || 'Erro ao carregar', true);
      }
    }

    const params = new URLSearchParams(location.search);
    dashEl('month').value = params.get('month') || todayMonth();
    dashEl('start').value = params.get('start') || '';
    dashEl('end').value = params.get('end') || '';
    if (params.get('unit_cost')) dashEl('unitCost').value = params.get('unit_cost');

    dashEl('month').addEventListener('change', () => {
      if (dashEl('month').value) {
        dashEl('start').value = '';
        dashEl('end').value = '';
      }
      loadDashboard();
    });
    dashEl('start').addEventListener('change', () => { if (dashEl('start').value) dashEl('month').value = ''; });
    dashEl('end').addEventListener('change', () => { if (dashEl('end').value) dashEl('month').value = ''; });
    dashEl('btnLoadDash').onclick = loadDashboard;
    dashEl('unitCost').addEventListener('keydown', (event) => {
      if (event.key === 'Enter') loadDashboard();
    });

    loadDashboard();
  </script>
</div>
