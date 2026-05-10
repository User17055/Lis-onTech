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
      --red-bg:#fee2e2;   --red-text:#991b1b;
      --blue-bg:#dbeafe;  --blue-text:#1e40af;
      --yellow-bg:#fef3c7;--yellow-text:#92400e;
      --radius-pill:999px;
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
    .brand i{color:var(--primary);font-size:22px;}

    .status-bar{
      font-size:13px;
      background:var(--bg-panel);
      padding:8px 20px;
      border-radius:var(--radius-pill);
      font-weight:800;
      border:2px solid var(--border-color);
      display:flex;
      align-items:center;
      gap:8px;
      transition:.3s;
    }
    .status-bar.updated{
      background:var(--green-bg);
      color:var(--green-text);
      border-color:var(--green-bg);
      transform:scale(1.03);
    }
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
      margin-bottom:25px;
      flex-wrap:wrap;
    }
    .form-control{
      background:var(--bg-panel);
      border:2px solid transparent;
      border-radius:var(--radius-pill);
      padding:0 18px;
      height:45px;
      font-size:15px;
      font-weight:700;
      outline:none;
      transition:.2s;
      color:var(--text-main);
      flex:1;
      min-width:180px;
    }
    .form-control:focus{background:#fff;border-color:var(--primary);box-shadow:0 0 0 4px rgba(59,130,246,.1);}
    .btn-primary{
      background:var(--primary);
      color:#fff;
      border:none;
      padding:0 18px;
      height:45px;
      border-radius:var(--radius-pill);
      cursor:pointer;
      font-weight:800;
      display:flex;
      align-items:center;
      gap:10px;
      transition:.2s;
      box-shadow:0 4px 6px rgba(59,130,246,.2);
    }
    .btn-primary:hover{transform:translateY(-2px);background:var(--primary-hover);}

    .toggle-wrapper{
      display:flex;
      align-items:center;
      gap:8px;
      padding:0 14px;
      border-right:2px solid var(--border-color);
    }
    .custom-check{accent-color:var(--primary);width:18px;height:18px;cursor:pointer;}

    table{width:100%;border-collapse:separate;border-spacing:0 12px;}
    thead th{color:var(--text-muted);font-size:12px;text-transform:uppercase;font-weight:900;padding:0 20px;text-align:left;}
    tbody tr{
      background:#fff;
      box-shadow:var(--shadow-soft);
      border:2px solid var(--border-color);
      border-radius:var(--radius-card);
      transition:.2s;
    }
    tbody tr:hover{transform:translateY(-3px) scale(1.003);box-shadow:var(--shadow-hover);border-color:#dbeafe;}
    tbody td{padding:16px 20px;vertical-align:middle;border-top:2px solid var(--border-color);border-bottom:2px solid var(--border-color);}
    tbody td:first-child{border-left:2px solid var(--border-color);border-top-left-radius:var(--radius-card);border-bottom-left-radius:var(--radius-card);}
    tbody td:last-child{border-right:2px solid var(--border-color);border-top-right-radius:var(--radius-card);border-bottom-right-radius:var(--radius-card);}

    .customer-name{font-weight:900;font-size:15px;display:block;}
    .muted{color:var(--text-muted);font-weight:800;font-size:12px;margin-top:4px;display:block;}

    .badge{
      padding:6px 12px;
      border-radius:var(--radius-pill);
      font-weight:900;
      font-size:12px;
      text-transform:uppercase;
      display:inline-flex;
      align-items:center;
      gap:6px;
    }
    .b-paid{background:var(--green-bg);color:var(--green-text);}
    .b-unpaid{background:var(--red-bg);color:var(--red-text);}
    .b-canceled{background:#e2e8f0;color:#334155;}
    .b-blocked{background:var(--yellow-bg);color:var(--yellow-text);}

    .pill{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:6px 12px;
      border-radius:999px;
      background:var(--bg-panel);
      border:2px solid var(--border-color);
      font-weight:900;
      font-size:12px;
      color:var(--text-main);
      white-space:nowrap;
    }

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
    }
    .btn-mini:hover{border-color:#dbeafe;color:var(--primary);transform:translateY(-1px);}
    .btn-mini.danger:hover{border-color:var(--red-bg);color:var(--red-text);}
    .btn-mini.ok:hover{border-color:var(--green-bg);color:var(--green-text);}

    .spinner{
      width:28px;height:28px;border:4px solid #e2e8f0;border-top-color:var(--primary);
      border-radius:50%;animation:spin .8s linear infinite;margin:0 auto;
    }
    @keyframes spin{to{transform:rotate(360deg)}}

    .pager{display:flex;justify-content:center;align-items:center;gap:8px;flex-wrap:wrap;margin:18px 0 10px;}
    .page-btn{
      background:#fff;border:2px solid var(--border-color);color:var(--text-main);
      border-radius:999px;padding:8px 14px;font-weight:900;cursor:pointer;
      box-shadow:var(--shadow-soft);transition:.2s;
    }
    .page-btn:hover{transform:translateY(-1px);border-color:#dbeafe;color:var(--primary);}
    .page-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);}
    .page-btn:disabled{opacity:.45;cursor:not-allowed;transform:none;}
    .page-info{color:var(--text-muted);font-weight:800;padding:8px 12px;}
  </style>

  <div class="pa-header">
    <div class="brand">
      <i class="fa-solid fa-money-bill-wave"></i> Recobranças
    </div>
    <div class="status-bar" id="statusBar">
      <span class="live-dot" id="liveIndicator"></span>
      <span id="statusText">Aguardando...</span>
    </div>
  </div>

  <div class="container">
    <div class="toolbar">
      <div style="flex:2; min-width:220px; position:relative;">
        <i class="fa-solid fa-search" style="color:var(--text-muted); position:absolute; left:18px; top:14px;"></i>
        <input id="q" class="form-control" style="padding-left:44px;" placeholder="Buscar nome, telefone, bill id...">
      </div>

      <select id="status" class="form-control" style="max-width:220px; cursor:pointer;">
        <option value="">Status (todos)</option>
        <option value="unpaid" selected>unpaid (devendo)</option>
        <option value="paid">paid</option>
        <option value="canceled">canceled</option>
        <option value="blocked">blocked</option>
      </select>

      <div class="toggle-wrapper" style="margin-right:4px;">
        <input type="checkbox" id="onlyOverdue" checked class="custom-check">
        <label for="onlyOverdue" style="font-size:14px; font-weight:900; color:var(--text-muted); cursor:pointer;">
          Só vencidas
        </label>
      </div>

      <div class="toggle-wrapper" style="border-right:none;">
        <input type="checkbox" id="auto" checked class="custom-check">
        <label for="auto" style="font-size:14px; font-weight:900; color:var(--text-muted); cursor:pointer;">
          Auto
        </label>
      </div>

      <button id="btnReload" class="btn-primary">
        Atualizar <i class="fa-solid fa-rotate"></i>
      </button>
    </div>

    <table>
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Valor</th>
          <th>Vencimento</th>
          <th>Status</th>
          <th>Envios</th>
          <th style="text-align:right;">Ações</th>
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

    function badge(st, blocked){
      if (blocked == 1 || st === 'blocked') return `<span class="badge b-blocked"><i class="fa-solid fa-pause"></i> blocked</span>`;
      if (st === 'paid') return `<span class="badge b-paid"><i class="fa-solid fa-circle-check"></i> paid</span>`;
      if (st === 'canceled') return `<span class="badge b-canceled"><i class="fa-solid fa-ban"></i> canceled</span>`;
      return `<span class="badge b-unpaid"><i class="fa-solid fa-triangle-exclamation"></i> unpaid</span>`;
    }

    function showLoading(){
      document.getElementById("tbody").innerHTML = `
        <tr>
          <td colspan="6" style="text-align:center; padding:40px; background:transparent; box-shadow:none; border:none;">
            <div class="spinner"></div>
            <div style="margin-top:12px; font-weight:900; color:var(--text-main);">Carregando...</div>
          </td>
        </tr>
      `;
    }

    function buildPager(totalPages, page){
      const pager = document.getElementById('pager');
      if (!totalPages || totalPages <= 1){ pager.innerHTML = ''; return; }

      const parts = [];
      const addBtn = (p,label,disabled=false,active=false) => {
        parts.push(`<button class="page-btn ${active?'active':''}" data-page="${p}" ${disabled?'disabled':''}>${label}</button>`);
      };

      addBtn(Math.max(1,page-1),'‹', page===1);
      const w=2;
      const start=Math.max(1,page-w), end=Math.min(totalPages,page+w);

      if (start>1){ addBtn(1,'1',false,page===1); if (start>2) parts.push(`<span class="page-info">...</span>`); }
      for (let p=start; p<=end; p++) addBtn(p,String(p),false,p===page);
      if (end<totalPages){ if (end<totalPages-1) parts.push(`<span class="page-info">...</span>`); addBtn(totalPages,String(totalPages),false,page===totalPages); }

      addBtn(Math.min(totalPages,page+1),'›', page===totalPages);
      parts.push(`<span class="page-info">Página ${page} de ${totalPages}</span>`);
      pager.innerHTML = parts.join('');
    }

    async function postAction(action, billId){
      const fd = new FormData();
      fd.set('action', action);
      fd.set('bill_id', String(billId));

      const r = await fetch('/painel/api/recobranca_action.php', { method:'POST', body: fd });
      const j = await r.json().catch(() => null);
      return j && j.ok;
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

        document.getElementById('statusText').textContent = "Atualizado em " + new Date().toLocaleString('pt-BR');
        const sb = document.getElementById('statusBar');
        sb.classList.add('updated');
        setTimeout(() => sb.classList.remove('updated'), 1200);

        const tbody = document.getElementById('tbody');

        if (!j.ok || !Array.isArray(j.rows) || j.rows.length === 0){
          tbody.innerHTML = `
            <tr>
              <td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted); background:transparent; box-shadow:none; border:none; font-weight:900;">
                Nenhum registro encontrado.
              </td>
            </tr>
          `;
          buildPager(0,1);
          currentPage = 1;
          return;
        }

        currentPage = j.page ?? currentPage;
        buildPager(j.total_pages ?? 1, currentPage);

        tbody.innerHTML = j.rows.map(row => {
          const billId = row.bill_id;
          const nome = row.customer_name || 'Cliente';
          const phone = row.phone ? `Tel ${esc(row.phone)}` : 'Tel vazio';
          const due = fmtDateTimeBr(row.due_at);
          const amount = fmtMoney(row.amount);

          const blocked = Number(row.blocked || 0);
          const st = String(row.status || '');
          const envio = Number(row.overdue_sent_count || 0);
          const erros = Number(row.reminder_attempts || 0);
          const last = row.last_overdue_sent_at ? fmtDateTimeBr(row.last_overdue_sent_at) : '-';
          const next = row.next_reminder_at ? fmtDateTimeBr(row.next_reminder_at) : '-';
          const lastStatus = row.last_status ? esc(row.last_status) : '-';

          const detalhesLink = `/painel/index.php?pagina=recobranca_detalhes&bill_id=${encodeURIComponent(billId)}`;

          const btnPause = `<button class="btn-mini danger" data-act="pause" data-id="${billId}" ${blocked? 'disabled':''}>
            <i class="fa-solid fa-pause"></i> Parar
          </button>`;

          const btnResume = `<button class="btn-mini ok" data-act="resume" data-id="${billId}" ${blocked? '' : 'disabled'}>
            <i class="fa-solid fa-play"></i> Reativar
          </button>`;

          const btnNow = `<button class="btn-mini" data-act="send_now" data-id="${billId}" ${blocked || st!=='unpaid' ? 'disabled':''}>
            <i class="fa-solid fa-bolt"></i> Enviar agora
          </button>`;

          return `
            <tr>
              <td>
                <span class="customer-name">${esc(nome)}</span>
                <span class="muted">Bill ${esc(billId)} • ${phone} • ${row.days_overdue != null ? (row.days_overdue + " dias em atraso") : ""}</span>
              </td>
              <td><span class="pill"><i class="fa-solid fa-coins"></i> ${amount}</span></td>
              <td>
                <span class="pill"><i class="fa-regular fa-calendar"></i> ${due}</span>
                <span class="muted">Próx ${esc(next)}</span>
              </td>
              <td>
                ${badge(st, blocked)}
                <span class="muted">last ${last} • ${lastStatus}</span>
              </td>
              <td>
                <span class="pill"><i class="fa-solid fa-paper-plane"></i> ${envio}</span>
                <span class="pill" style="margin-left:6px;"><i class="fa-solid fa-bug"></i> ${erros}</span>
              </td>
              <td style="text-align:right;">
                <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                  ${btnNow}
                  ${btnPause}
                  ${btnResume}
                  <a class="btn-mini" href="${detalhesLink}">
                    <i class="fa-solid fa-chevron-right"></i> Detalhes
                  </a>
                </div>
              </td>
            </tr>
          `;
        }).join('');

      } catch(e){
        console.error(e);
        document.getElementById('tbody').innerHTML = `
          <tr>
            <td colspan="6" style="text-align:center; padding:30px; color:var(--red-text); font-weight:900;">
              Erro ao conectar no servidor.
            </td>
          </tr>
        `;
      }
    }

    // ações
    document.getElementById('tbody').addEventListener('click', async (e) => {
      const btn = e.target.closest('button[data-act]');
      if (!btn) return;

      const act = btn.getAttribute('data-act');
      const id  = btn.getAttribute('data-id');

      btn.disabled = true;
      const ok = await postAction(act, id);
      await load(true);
      if (!ok) alert('Não foi possível executar a ação.');
    });

    // filtros
    document.getElementById('btnReload').onclick = () => load(true);
    document.getElementById('status').onchange = () => { currentPage = 1; previousHash=null; load(true); };
    document.getElementById('onlyOverdue').onchange = () => { currentPage = 1; previousHash=null; load(true); };
    document.getElementById('q').onkeydown = (e) => {
      if (e.key === 'Enter'){ currentPage=1; previousHash=null; load(true); }
    };

    // pager
    document.getElementById('pager').onclick = (e) => {
      const b = e.target.closest('button[data-page]');
      if (!b) return;
      const p = parseInt(b.getAttribute('data-page'), 10);
      if (!p || p === currentPage) return;
      currentPage = p;
      previousHash=null;
      load(true);
    };

    // auto refresh
    let t = setInterval(() => load(false), 4000);
    document.getElementById('auto').onchange = function(){
      const ind = document.getElementById('liveIndicator');
      if (this.checked){
        t = setInterval(() => load(false), 4000);
        ind.style.display = "inline-block";
        document.getElementById('statusText').textContent = "Monitorando...";
      } else {
        clearInterval(t);
        ind.style.display = "none";
        document.getElementById('statusText').textContent = "Auto pausado";
      }
    };

    load(true);
  </script>
</div>