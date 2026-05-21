<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }
?>
<div class="chat-wrap">
  <style>
    .chat-wrap{
      --bg:#f6f8fb;
      --panel:#ffffff;
      --line:#e6edf5;
      --text:#172033;
      --muted:#66758a;
      --brand:#38b6ff;
      --brand-dark:#1677a8;
      --ok:#0f9f6e;
      --warn:#b7791f;
      --danger:#c24141;
      --soft-ok:#e4f8ee;
      --soft-warn:#fff4da;
      --soft-danger:#fee9e9;
      --bubble-in:#ffffff;
      --bubble-out:#e7f6ff;
      --bubble-out-border:#bfe8ff;
      --shadow:0 12px 28px rgba(23,32,51,.05);
      --shadow-strong:0 22px 54px rgba(23,32,51,.16);
      font-family:'Nunito',sans-serif;
      color:var(--text);
      width:100%;
      height:100%;
      min-height:0;
      overflow:hidden;
    }

    .chat-shell{
      height:100%;
      min-height:0;
      display:grid;
      grid-template-columns:minmax(360px,430px) minmax(0,1fr);
      background:var(--panel);
      border:0;
      border-radius:0;
      overflow:hidden;
      box-shadow:none;
    }

    .chat-list-pane{
      border-right:1px solid var(--line);
      display:flex;
      flex-direction:column;
      min-width:0;
      min-height:0;
      background:#fbfcfe;
    }

    .chat-main-pane{
      display:flex;
      flex-direction:column;
      min-width:0;
      min-height:0;
      background:#f8fafc;
    }

    .chat-pane-head{
      min-height:86px;
      padding:18px 20px;
      border-bottom:1px solid var(--line);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      background:#fff;
      box-sizing:border-box;
    }

    .chat-title{display:flex;align-items:center;gap:10px;min-width:0;}
    .chat-title i{color:var(--brand);font-size:26px;}
    .chat-title strong{display:block;font-size:21px;font-weight:900;line-height:1.1;}
    .chat-title span{display:block;color:var(--muted);font-size:13px;font-weight:800;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    .icon-btn{
      width:48px;
      height:48px;
      border:1px solid var(--line);
      border-radius:8px;
      background:#fff;
      color:var(--text);
      display:inline-flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      transition:.18s;
      flex:0 0 48px;
      font-size:16px;
    }
    .icon-btn:hover{border-color:#bfe8ff;color:var(--brand-dark);background:#f5fbff;}
    .icon-btn.primary{background:var(--brand);border-color:var(--brand);color:#fff;}
    .icon-btn.primary:hover{background:var(--brand-dark);border-color:var(--brand-dark);color:#fff;}
    .icon-btn:disabled{opacity:.45;cursor:not-allowed;}

    .chat-search{
      padding:14px 16px;
      border-bottom:1px solid var(--line);
      background:#fff;
      display:grid;
      gap:10px;
    }
    .input-row{display:flex;align-items:center;gap:8px;}
    .input-wrap{position:relative;min-width:0;flex:1;}
    .input-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#93a4b7;font-size:14px;}
    .chat-input,.chat-textarea{
      width:100%;
      border:1px solid var(--line);
      border-radius:8px;
      background:#f8fafc;
      color:var(--text);
      font-family:'Nunito',sans-serif;
      font-weight:800;
      outline:none;
      box-sizing:border-box;
      transition:.18s;
    }
    .chat-input{height:48px;padding:0 13px 0 38px;font-size:14px;}
    .chat-input.plain{padding-left:12px;}
    .chat-input:focus,.chat-textarea:focus{border-color:#9bdcff;background:#fff;box-shadow:0 0 0 3px rgba(56,182,255,.14);}

    .new-chat-box{
      display:none;
      grid-template-columns:1fr 48px;
      gap:8px;
    }
    .new-chat-box.show{display:grid;}

    .switch-line{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      color:var(--muted);
      font-size:12px;
      font-weight:900;
    }
    .switch-line label{display:inline-flex;align-items:center;gap:8px;cursor:pointer;}
    .switch-line input{accent-color:var(--brand);}

    .filter-tabs{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:6px;
      padding:4px;
      border:1px solid var(--line);
      border-radius:8px;
      background:#f2f6fb;
    }
    .filter-tab{
      height:36px;
      border:0;
      border-radius:6px;
      background:transparent;
      color:#5f6f83;
      font-family:'Nunito',sans-serif;
      font-size:12px;
      font-weight:900;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:7px;
      transition:.16s;
    }
    .filter-tab:hover{background:#fff;color:var(--brand-dark);}
    .filter-tab.active{background:#fff;color:#172033;box-shadow:0 1px 3px rgba(23,32,51,.06);}

    .thread-list{
      overflow:auto;
      flex:1;
      min-height:0;
      padding:10px;
    }
    .thread-item{
      width:100%;
      border:1px solid transparent;
      background:transparent;
      border-radius:8px;
      padding:13px;
      display:grid;
      grid-template-columns:50px minmax(0,1fr) auto;
      gap:12px;
      text-align:left;
      cursor:pointer;
      color:var(--text);
      font-family:'Nunito',sans-serif;
      transition:.16s;
      min-height:94px;
    }
    .thread-item:hover{background:#fff;border-color:#dce8f3;box-shadow:0 1px 3px rgba(23,32,51,.05);}
    .thread-item.active{background:#eef8ff;border-color:#bfe8ff;}
    .avatar{
      width:50px;
      height:50px;
      border-radius:8px;
      background:#38b6ff;
      color:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:900;
      flex:0 0 50px;
      font-size:15px;
    }
    .thread-name{font-size:15px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .thread-phone{font-size:12px;color:var(--muted);font-weight:800;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .thread-preview{font-size:13px;color:#435169;font-weight:800;margin-top:7px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .thread-window{display:inline-flex;align-items:center;gap:6px;margin-top:8px;border-radius:8px;padding:4px 7px;font-size:11px;font-weight:900;}
    .thread-window.open{background:var(--soft-ok);color:#08734d;}
    .thread-window.closed{background:var(--soft-warn);color:var(--warn);}
    .thread-meta{display:flex;flex-direction:column;align-items:flex-end;gap:8px;min-width:58px;}
    .thread-time{font-size:11px;color:#8a99ac;font-weight:900;white-space:nowrap;}
    .unread-pill{min-width:22px;height:22px;border-radius:999px;background:var(--brand);color:#fff;font-size:11px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;padding:0 7px;box-sizing:border-box;}

    .status-pill{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:6px 9px;
      border-radius:8px;
      font-size:11px;
      font-weight:900;
      white-space:nowrap;
      background:#eef2f7;
      color:#526174;
    }
    .status-pill.read{background:#e6f6ff;color:#12628f;}
    .status-pill.delivered,.status-pill.sent,.status-pill.accepted{background:var(--soft-ok);color:#08734d;}
    .status-pill.failed{background:var(--soft-danger);color:var(--danger);}
    .status-pill.received{background:#f0f4f8;color:#405064;}

    .chat-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;}
    .charge-btn{
      height:48px;
      border:1px solid #bfe8ff;
      border-radius:8px;
      background:#eef8ff;
      color:#12628f;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:9px;
      padding:0 14px;
      cursor:pointer;
      transition:.18s;
      font-family:'Nunito',sans-serif;
      font-size:13px;
      font-weight:900;
      white-space:nowrap;
    }
    .charge-btn:hover{background:#dff3ff;border-color:#8bd5ff;}
    .charge-btn:disabled{opacity:.45;cursor:not-allowed;background:#f3f7fb;border-color:var(--line);color:#7d8da1;}

    .window-panel{
      border-bottom:1px solid var(--line);
      background:#fff;
      padding:12px 20px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
    }
    .window-copy{display:flex;align-items:center;gap:10px;min-width:0;}
    .window-copy i{font-size:18px;}
    .window-copy strong{display:block;font-size:14px;font-weight:900;}
    .window-copy span{display:block;font-size:12px;font-weight:800;color:var(--muted);margin-top:1px;}
    .window-panel.open{background:#f4fff9;}
    .window-panel.open .window-copy i{color:var(--ok);}
    .window-panel.closed{background:#fffaf0;}
    .window-panel.closed .window-copy i{color:var(--warn);}
    .window-timer{font-size:16px;font-weight:900;white-space:nowrap;color:var(--text);}

    .messages{
      flex:1;
      min-height:0;
      overflow:auto;
      padding:28px;
      display:flex;
      flex-direction:column;
      gap:12px;
      background:#f5f8fc;
    }

    .empty-state{
      margin:auto;
      text-align:center;
      color:var(--muted);
      font-weight:800;
      max-width:360px;
      line-height:1.45;
    }
    .empty-state i{font-size:42px;color:#b5c3d4;margin-bottom:12px;display:block;}

    .msg-row{display:flex;}
    .msg-row.out{justify-content:flex-end;}
    .bubble{
      max-width:min(760px,80%);
      border:1px solid rgba(214,226,238,.95);
      border-radius:16px;
      padding:12px 14px 8px;
      background:var(--bubble-in);
      box-shadow:0 1px 2px rgba(23,32,51,.04);
      word-break:break-word;
      position:relative;
    }
    .msg-row.in .bubble{border-top-left-radius:6px;}
    .msg-row.out .bubble{background:var(--bubble-out);border-color:var(--bubble-out-border);border-top-right-radius:6px;}
    .msg-body{font-size:15px;font-weight:800;line-height:1.5;white-space:pre-wrap;}
    .msg-body a{color:#12628f;text-decoration:underline;text-underline-offset:2px;overflow-wrap:anywhere;}
    .media-box{display:grid;gap:8px;margin-bottom:9px;}
    .media-img,.media-video{max-width:360px;width:100%;border-radius:12px;border:1px solid rgba(214,226,238,.95);background:#eef5fb;display:block;}
    .media-audio{width:min(360px,100%);}
    .media-file{
      display:grid;
      grid-template-columns:44px minmax(0,1fr) 34px;
      align-items:center;
      gap:12px;
      width:min(360px,100%);
      border:1px solid rgba(214,226,238,.95);
      border-radius:12px;
      background:#fff;
      padding:10px;
      color:#172033;
      text-decoration:none;
      box-sizing:border-box;
    }
    .media-file:hover{border-color:#9bdcff;color:#12628f;background:#fff;}
    .media-file-icon{
      width:44px;
      height:44px;
      border-radius:10px;
      background:#3b82f6;
      color:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:19px;
      flex:0 0 44px;
    }
    .media-file-name{display:block;font-size:13px;font-weight:900;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .media-file-meta{display:block;font-size:11px;font-weight:900;color:#718096;margin-top:3px;text-transform:uppercase;}
    .media-file-open{
      width:34px;
      height:34px;
      border-radius:999px;
      display:flex;
      align-items:center;
      justify-content:center;
      background:#eef8ff;
      color:#12628f;
    }
    .media-caption{margin-top:2px;}
    .msg-foot{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:8px;color:#718096;font-size:11px;font-weight:900;}
    .msg-error{margin-top:8px;color:var(--danger);font-size:12px;font-weight:900;}

    .composer{
      flex:0 0 auto;
      border-top:1px solid var(--line);
      background:#fff;
      padding:14px 16px;
      display:grid;
      grid-template-columns:52px 1fr 52px;
      gap:12px;
      align-items:end;
    }
    .composer.blocked .chat-textarea{background:#f1f5f9;color:#8a99ac;}
    .chat-textarea{
      resize:none;
      min-height:54px;
      max-height:150px;
      padding:15px 16px;
      line-height:1.35;
      font-size:15px;
    }

    .toast{
      position:fixed;
      right:22px;
      bottom:22px;
      background:#172033;
      color:#fff;
      border-radius:8px;
      padding:12px 14px;
      font-weight:900;
      box-shadow:var(--shadow);
      display:none;
      z-index:1200;
      max-width:360px;
    }
    .toast.show{display:block;}
    .toast.error{background:#8f2525;}

    .confirm-backdrop{
      position:fixed;
      inset:0;
      z-index:1300;
      background:rgba(12,20,33,.46);
      display:none;
      align-items:center;
      justify-content:center;
      padding:18px;
    }
    .confirm-backdrop.show{display:flex;}
    .confirm-modal{
      width:min(440px,100%);
      border-radius:8px;
      background:#fff;
      box-shadow:var(--shadow-strong);
      overflow:hidden;
      border:1px solid rgba(255,255,255,.65);
    }
    .confirm-head{
      padding:18px 20px;
      display:flex;
      align-items:center;
      gap:12px;
      border-bottom:1px solid var(--line);
      background:#f7fbff;
    }
    .confirm-icon{
      width:46px;
      height:46px;
      border-radius:8px;
      display:flex;
      align-items:center;
      justify-content:center;
      background:#e6f6ff;
      color:var(--brand-dark);
      font-size:19px;
      flex:0 0 46px;
    }
    .confirm-title{font-size:17px;font-weight:900;line-height:1.15;}
    .confirm-subtitle{font-size:12px;font-weight:800;color:var(--muted);margin-top:4px;}
    .confirm-body{padding:18px 20px;color:#405064;font-size:14px;font-weight:800;line-height:1.45;}
    .confirm-note{
      margin-top:12px;
      border:1px solid var(--line);
      border-radius:8px;
      background:#f8fafc;
      padding:11px 12px;
      color:#596a80;
      font-size:12px;
      font-weight:900;
    }
    .confirm-actions{
      padding:14px 20px 18px;
      display:flex;
      justify-content:flex-end;
      gap:10px;
      background:#fff;
    }
    .confirm-btn{
      height:42px;
      border-radius:8px;
      border:1px solid var(--line);
      padding:0 14px;
      font-family:'Nunito',sans-serif;
      font-size:13px;
      font-weight:900;
      cursor:pointer;
      background:#fff;
      color:#334155;
    }
    .confirm-btn.primary{background:var(--brand);border-color:var(--brand);color:#fff;}
    .confirm-btn.primary:hover{background:var(--brand-dark);border-color:var(--brand-dark);}
    .confirm-btn:hover{background:#f8fafc;}

    @media(max-width:920px){
      .chat-wrap{height:auto;min-height:calc(100vh - var(--header-height, 70px));overflow:visible;}
      .chat-shell{height:auto;min-height:calc(100vh - var(--header-height, 70px));grid-template-columns:1fr;}
      .chat-list-pane{height:360px;border-right:none;border-bottom:1px solid var(--line);}
      .chat-main-pane{min-height:560px;}
      .messages{padding:16px;}
      .bubble{max-width:90%;}
    }
  </style>

  <div class="chat-shell">
    <aside class="chat-list-pane">
      <div class="chat-pane-head">
        <div class="chat-title">
          <i class="fa-brands fa-whatsapp"></i>
          <div>
            <strong>Chat</strong>
            <span id="threadCount">Carregando...</span>
          </div>
        </div>
        <button class="icon-btn" id="btnNewChat" type="button" title="Abrir conversa por numero">
          <i class="fa-solid fa-plus"></i>
        </button>
      </div>

      <div class="chat-search">
        <div class="input-row">
          <div class="input-wrap">
            <i class="fa-solid fa-search"></i>
            <input class="chat-input" id="searchThreads" placeholder="Buscar conversa">
          </div>
          <button class="icon-btn" id="btnReloadThreads" type="button" title="Atualizar">
            <i class="fa-solid fa-rotate"></i>
          </button>
        </div>
        <div class="new-chat-box" id="newChatBox">
          <input class="chat-input plain" id="newPhone" inputmode="numeric" placeholder="5511999998888">
          <button class="icon-btn primary" id="btnOpenPhone" type="button" title="Abrir">
            <i class="fa-solid fa-arrow-right"></i>
          </button>
        </div>
        <div class="filter-tabs" id="threadFilters" role="group" aria-label="Filtros de conversa">
          <button class="filter-tab active" type="button" data-filter="all"><i class="fa-solid fa-layer-group"></i> Todas</button>
          <button class="filter-tab" type="button" data-filter="received"><i class="fa-solid fa-inbox"></i> Recebidas</button>
          <button class="filter-tab" type="button" data-filter="unread"><i class="fa-solid fa-circle"></i> Nao lidas</button>
        </div>
        <div class="switch-line">
          <input type="checkbox" id="onlyUnread" hidden>
          <span id="filterHint">Todas as conversas</span>
          <span id="listStatus">online</span>
        </div>
      </div>

      <div class="thread-list" id="threadList"></div>
    </aside>

    <section class="chat-main-pane">
      <div class="chat-pane-head">
        <div class="chat-title">
          <i class="fa-solid fa-comments"></i>
          <div>
            <strong id="activeName">Selecione uma conversa</strong>
            <span id="activePhone">Nenhum telefone aberto</span>
          </div>
        </div>
        <div class="chat-actions">
          <button class="charge-btn" id="btnResendCharge" type="button" disabled>
            <i class="fa-solid fa-repeat"></i> Reenviar cobranca
          </button>
          <button class="icon-btn" id="btnSendTemplate" type="button" title="Enviar modelo inicial" disabled>
            <i class="fa-solid fa-file-lines"></i>
          </button>
          <span class="status-pill" id="activeStatus"><i class="fa-regular fa-circle"></i> aguardando</span>
        </div>
      </div>

      <div class="window-panel closed" id="windowPanel">
        <div class="window-copy">
          <i class="fa-solid fa-lock"></i>
          <div>
            <strong id="windowTitle">Selecione uma conversa</strong>
            <span id="windowSubtitle">O envio de texto livre depende da janela de 24h.</span>
          </div>
        </div>
        <div class="window-timer" id="windowTimer">--:--:--</div>
      </div>

      <div class="messages" id="messages">
        <div class="empty-state">
          <i class="fa-regular fa-message"></i>
          Abra uma conversa para ver o historico.
        </div>
      </div>

      <div class="composer blocked" id="composer">
        <button class="icon-btn" id="btnSendTemplateBottom" type="button" title="Enviar modelo inicial" disabled>
          <i class="fa-solid fa-file-lines"></i>
        </button>
        <textarea class="chat-textarea" id="messageText" placeholder="Responder..." disabled></textarea>
        <button class="icon-btn primary" id="btnSend" type="button" title="Enviar" disabled>
          <i class="fa-solid fa-paper-plane"></i>
        </button>
      </div>
    </section>
  </div>

  <div class="toast" id="toast"></div>
  <div class="confirm-backdrop" id="confirmBackdrop" aria-hidden="true">
    <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
      <div class="confirm-head">
        <div class="confirm-icon"><i class="fa-solid fa-paper-plane"></i></div>
        <div>
          <div class="confirm-title" id="confirmTitle">Confirmar envio</div>
          <div class="confirm-subtitle" id="confirmSubtitle">Revise antes de disparar</div>
        </div>
      </div>
      <div class="confirm-body">
        <div id="confirmMessage">Deseja enviar esta mensagem?</div>
        <div class="confirm-note" id="confirmNote"></div>
      </div>
      <div class="confirm-actions">
        <button class="confirm-btn" id="confirmCancel" type="button">Cancelar</button>
        <button class="confirm-btn primary" id="confirmOk" type="button">Enviar</button>
      </div>
    </div>
  </div>

  <script>
    const state = {
      threads: [],
      selectedPhone: new URLSearchParams(location.search).get('phone') || '',
      activeThread: null,
      loadingMessages: false,
      lastMessageHash: '',
      lastCharge: null,
      threadFilter: 'all',
      confirmResolve: null,
      backfillDone: false,
      threadTimer: null,
      messageTimer: null
    };

    const el = (id) => document.getElementById(id);

    function esc(value){
      return String(value ?? '').replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
      }[m]));
    }

    function attr(value){
      return esc(value).replace(/`/g, '&#096;');
    }

    function linkify(value){
      const text = String(value ?? '');
      const pattern = /(https?:\/\/[^\s<>"']+|www\.[^\s<>"']+)/gi;
      let out = '';
      let last = 0;
      let match;
      while ((match = pattern.exec(text)) !== null) {
        out += esc(text.slice(last, match.index));
        let raw = match[0];
        let trailing = '';
        while (/[.,;:!?)]$/.test(raw)) {
          trailing = raw.slice(-1) + trailing;
          raw = raw.slice(0, -1);
        }
        const href = raw.toLowerCase().startsWith('www.') ? 'https://' + raw : raw;
        out += `<a href="${attr(href)}" target="_blank" rel="noopener noreferrer">${esc(raw)}</a>${esc(trailing)}`;
        last = match.index + match[0].length;
      }
      return out + esc(text.slice(last));
    }

    function digits(value){
      let d = String(value ?? '').replace(/\D+/g, '');
      if (d && d.length <= 11) d = '55' + d;
      return d;
    }

    function fmtTime(value){
      const s = String(value ?? '').trim();
      if (!s) return '';
      const dt = new Date(s.replace(' ', 'T'));
      if (isNaN(dt.getTime())) return s;
      const now = new Date();
      const sameDay = dt.toDateString() === now.toDateString();
      return sameDay
        ? dt.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'})
        : dt.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'}) + ' ' + dt.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
    }

    function parseServerDate(value){
      const s = String(value ?? '').trim();
      if (!s) return null;
      const dt = new Date(s.replace(' ', 'T'));
      return isNaN(dt.getTime()) ? null : dt;
    }

    function durationText(ms){
      const total = Math.max(0, Math.floor(ms / 1000));
      const h = Math.floor(total / 3600);
      const m = Math.floor((total % 3600) / 60);
      const s = total % 60;
      return [h, m, s].map(v => String(v).padStart(2, '0')).join(':');
    }

    function threadWindow(thread){
      const expires = parseServerDate(thread?.window_expires_at);
      const left = expires ? expires.getTime() - Date.now() : 0;
      return {
        open: Boolean(thread?.can_send_text) && left > 0,
        expires,
        left
      };
    }

    function initials(name, phone){
      const base = String(name || phone || '?').trim();
      const words = base.split(/\s+/).filter(Boolean);
      if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase();
      return base.slice(0, 2).toUpperCase();
    }

    function statusLabel(status){
      const st = String(status || '').toLowerCase();
      const map = {
        accepted:['fa-regular fa-clock','aceita'],
        sent:['fa-solid fa-check','enviada'],
        delivered:['fa-solid fa-check-double','entregue'],
        read:['fa-solid fa-check-double','lida'],
        failed:['fa-solid fa-triangle-exclamation','falhou'],
        received:['fa-solid fa-inbox','recebida']
      };
      return map[st] || ['fa-regular fa-circle', st || 'aguardando'];
    }

    function statusPill(status){
      const [icon, label] = statusLabel(status);
      return `<span class="status-pill ${esc(status)}"><i class="${icon}"></i> ${esc(label)}</span>`;
    }

    function windowMini(row){
      const info = threadWindow(row);
      if (info.open) {
        return `<div class="thread-window open"><i class="fa-regular fa-clock"></i> ${esc(durationText(info.left))}</div>`;
      }
      return `<div class="thread-window closed"><i class="fa-solid fa-file-lines"></i> modelo</div>`;
    }

    function toast(message, type='ok'){
      const box = el('toast');
      box.textContent = message;
      box.classList.toggle('error', type === 'error');
      box.classList.add('show');
      setTimeout(() => box.classList.remove('show'), 3200);
    }

    function syncFilterUi(){
      const labels = {
        all:'Todas as conversas',
        received:'Conversas que receberam mensagem',
        unread:'Conversas nao lidas'
      };
      el('filterHint').textContent = labels[state.threadFilter] || labels.all;
      el('onlyUnread').checked = state.threadFilter === 'unread';
      document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.filter === state.threadFilter);
      });
    }

    function askConfirm({title, subtitle, message, note, okText='Enviar'}){
      return new Promise(resolve => {
        state.confirmResolve = resolve;
        el('confirmTitle').textContent = title || 'Confirmar envio';
        el('confirmSubtitle').textContent = subtitle || 'Revise antes de disparar';
        el('confirmMessage').textContent = message || 'Deseja enviar esta mensagem?';
        el('confirmNote').textContent = note || '';
        el('confirmNote').style.display = note ? 'block' : 'none';
        el('confirmOk').textContent = okText;
        el('confirmBackdrop').classList.add('show');
        el('confirmBackdrop').setAttribute('aria-hidden', 'false');
        el('confirmOk').focus();
      });
    }

    function closeConfirm(result){
      el('confirmBackdrop').classList.remove('show');
      el('confirmBackdrop').setAttribute('aria-hidden', 'true');
      if (state.confirmResolve) state.confirmResolve(Boolean(result));
      state.confirmResolve = null;
    }

    async function fetchJson(url, options = {}){
      const fetchOptions = {credentials:'same-origin', ...options};
      const resp = await fetch(url, fetchOptions);
      const text = await resp.text();
      let json = null;
      try { json = JSON.parse(text); } catch(e) {}

      if (resp.status === 401) {
        location.href = '/painel/';
        throw new Error('Login obrigatorio');
      }

      if (!resp.ok || !json || json.ok === false) {
        throw new Error(json?.error || text.slice(0, 220) || 'Falha na requisicao');
      }
      return json;
    }

    function renderThreads(){
      const list = el('threadList');
      if (!state.threads.length) {
        list.innerHTML = `
          <div class="empty-state" style="padding:40px 18px;">
            <i class="fa-regular fa-comments"></i>
            Nenhuma conversa encontrada.
          </div>
        `;
        return;
      }

      list.innerHTML = state.threads.map(row => {
        const phone = String(row.phone || '');
        const name = row.display_name || phone;
        const active = phone === state.selectedPhone ? 'active' : '';
        const unread = Number(row.unread_count || 0);
        return `
          <button class="thread-item ${active}" type="button" data-phone="${esc(phone)}">
            <div class="avatar">${esc(initials(name, phone))}</div>
            <div style="min-width:0;">
              <div class="thread-name">${esc(name)}</div>
              <div class="thread-phone">${esc(phone)}</div>
              <div class="thread-preview">${esc(row.last_message_preview || 'Sem mensagens')}</div>
              ${windowMini(row)}
            </div>
            <div class="thread-meta">
              <span class="thread-time">${esc(fmtTime(row.last_message_at))}</span>
              ${unread > 0 ? `<span class="unread-pill">${unread > 99 ? '99+' : unread}</span>` : statusPill(row.last_status)}
            </div>
          </button>
        `;
      }).join('');
    }

    async function loadThreads(manual=false){
      const url = new URL('/painel/api/chat_threads.php', location.origin);
      const q = el('searchThreads').value.trim();
      if (q) url.searchParams.set('q', q);
      if (el('onlyUnread').checked || state.threadFilter === 'unread') url.searchParams.set('unread', '1');
      if (state.threadFilter === 'received') url.searchParams.set('direction', 'in');
      if (!state.backfillDone) url.searchParams.set('backfill', '1');

      try {
        const data = await fetchJson(url.toString(), {cache:'no-store'});
        state.backfillDone = true;
        state.threads = Array.isArray(data.rows) ? data.rows : [];
        el('threadCount').textContent = `${data.total ?? state.threads.length} conversas`;
        el('listStatus').textContent = 'online';
        const selected = state.threads.find(t => t.phone === state.selectedPhone);
        if (selected) {
          state.activeThread = {...(state.activeThread || {}), ...selected};
          updateWindowPanel();
        }
        renderThreads();
        if (!state.selectedPhone && state.threads[0]?.phone) {
          openConversation(state.threads[0].phone, false);
        }
      } catch (e) {
        el('listStatus').textContent = 'erro';
        if (manual) toast(e.message || 'Erro ao carregar conversas', 'error');
      }
    }

    function messageHtml(msg){
      const dir = msg.direction === 'out' ? 'out' : 'in';
      const st = statusLabel(msg.status);
      const error = msg.error_text ? `<div class="msg-error">${esc(msg.error_text)}</div>` : '';
      const media = mediaHtml(msg);
      const body = messageBodyHtml(msg);
      return `
        <div class="msg-row ${dir}">
          <div class="bubble">
            ${media}
            ${body ? `<div class="msg-body ${media ? 'media-caption' : ''}">${body}</div>` : ''}
            ${error}
            <div class="msg-foot">
              <span>${esc(fmtTime(msg.created_at))}</span>
              ${dir === 'out' ? `<span class="status-pill ${esc(msg.status)}"><i class="${st[0]}"></i> ${esc(st[1])}</span>` : ''}
            </div>
          </div>
        </div>
      `;
    }

    function mediaUrl(msg){
      return `/painel/api/chat_media.php?id=${encodeURIComponent(msg.id)}`;
    }

    function messageBodyHtml(msg){
      const type = String(msg.message_type || '').toLowerCase();
      const body = String(msg.body || '');
      const placeholders = {
        image: ['[imagem]'],
        video: ['[video]'],
        audio: ['[audio]'],
        sticker: ['[figurinha]'],
        document: ['[documento]']
      };
      if (placeholders[type]?.includes(body.trim().toLowerCase())) return '';
      return linkify(body);
    }

    function mediaFileName(msg){
      const media = msg.media || {};
      const fallback = String(msg.body || '').replace(/^\[documento\]\s*/i, '').trim();
      return media.filename || fallback || 'Documento';
    }

    function mediaKindLabel(msg){
      const mime = String(msg.media?.mime_type || '').toLowerCase();
      if (mime.includes('pdf')) return 'PDF';
      if (mime.includes('word') || mime.includes('document')) return 'DOC';
      if (mime.includes('spreadsheet') || mime.includes('excel')) return 'XLS';
      if (mime.includes('image')) return 'Imagem';
      return 'Arquivo';
    }

    function mediaHtml(msg){
      const type = String(msg.message_type || '').toLowerCase();
      const url = mediaUrl(msg);
      if (type === 'image' || type === 'sticker') {
        return `<div class="media-box"><a href="${attr(url)}" target="_blank" rel="noopener"><img class="media-img" src="${attr(url)}" loading="lazy" alt="Midia recebida"></a></div>`;
      }
      if (type === 'video') {
        return `<div class="media-box"><video class="media-video" src="${attr(url)}" controls preload="metadata"></video></div>`;
      }
      if (type === 'audio') {
        return `<div class="media-box"><audio class="media-audio" src="${attr(url)}" controls preload="metadata"></audio></div>`;
      }
      if (type === 'document') {
        const name = mediaFileName(msg);
        const kind = mediaKindLabel(msg);
        return `
          <div class="media-box">
            <a class="media-file" href="${attr(url)}" target="_blank" rel="noopener" title="Abrir documento">
              <span class="media-file-icon"><i class="fa-regular fa-file-lines"></i></span>
              <span style="min-width:0;">
                <span class="media-file-name">${esc(name)}</span>
                <span class="media-file-meta">${esc(kind)} - tocar para abrir</span>
              </span>
              <span class="media-file-open"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
            </a>
          </div>
        `;
      }
      return '';
    }

    function setActiveHeader(thread){
      const phone = state.selectedPhone;
      state.activeThread = thread || (phone ? {phone, can_send_text:false, window_expires_at:null} : null);
      const name = thread?.display_name || phone || 'Conversa';
      el('activeName').textContent = name;
      el('activePhone').textContent = phone || 'Nenhum telefone aberto';
      el('activeStatus').outerHTML = statusPill(thread?.last_status || 'received').replace('<span class="status-pill', '<span id="activeStatus" class="status-pill');
      el('btnSendTemplate').disabled = !phone;
      el('btnSendTemplateBottom').disabled = !phone;
      el('btnResendCharge').disabled = !phone || !state.lastCharge?.source_ref;
      updateWindowPanel();
    }

    function updateWindowPanel(){
      const panel = el('windowPanel');
      const composer = el('composer');
      const text = el('messageText');
      const send = el('btnSend');
      const phone = state.selectedPhone;

      if (!phone) {
        panel.className = 'window-panel closed';
        el('windowTitle').textContent = 'Selecione uma conversa';
        el('windowSubtitle').textContent = 'O envio de texto livre depende da janela de 24h.';
        el('windowTimer').textContent = '--:--:--';
        composer.classList.add('blocked');
        text.disabled = true;
        send.disabled = true;
        el('btnSendTemplateBottom').disabled = true;
        text.placeholder = 'Responder...';
        return;
      }

      const info = threadWindow(state.activeThread);
      if (info.open) {
        panel.className = 'window-panel open';
        el('windowTitle').textContent = 'Texto livre liberado';
        el('windowSubtitle').textContent = 'Janela aberta pela ultima mensagem recebida do cliente.';
        el('windowTimer').textContent = durationText(info.left);
        composer.classList.remove('blocked');
        text.disabled = false;
        send.disabled = false;
        el('btnSendTemplateBottom').disabled = false;
        text.placeholder = 'Responder...';
        return;
      }

      panel.className = 'window-panel closed';
      el('windowTitle').textContent = 'Janela de texto fechada';
      el('windowSubtitle').textContent = 'Envie um modelo aprovado; texto livre volta quando o cliente responder.';
      el('windowTimer').textContent = 'modelo';
      composer.classList.add('blocked');
      text.disabled = true;
      send.disabled = true;
      el('btnSendTemplateBottom').disabled = false;
      text.placeholder = 'Use o botao de modelo para iniciar atendimento';
    }

    async function loadMessages(markRead=false){
      if (!state.selectedPhone || state.loadingMessages) return;
      state.loadingMessages = true;
      const url = new URL('/painel/api/chat_messages.php', location.origin);
      url.searchParams.set('phone', state.selectedPhone);
      if (markRead) url.searchParams.set('mark_read', '1');

      try {
        const data = await fetchJson(url.toString(), {cache:'no-store'});
        const messages = Array.isArray(data.messages) ? data.messages : [];
        state.lastCharge = data.last_charge || null;
        const hash = JSON.stringify(messages.map(m => [m.id, m.status, m.message_type, m.body, m.error_text]));
        const activeThread = data.thread || state.threads.find(t => t.phone === state.selectedPhone);
        setActiveHeader(activeThread);

        if (hash !== state.lastMessageHash) {
          state.lastMessageHash = hash;
          el('messages').innerHTML = messages.length
            ? messages.map(messageHtml).join('')
            : `<div class="empty-state"><i class="fa-regular fa-message"></i>Nenhuma mensagem nesta conversa.</div>`;
          el('messages').scrollTop = el('messages').scrollHeight;
        }

        if (markRead) loadThreads(false);
      } catch (e) {
        toast(e.message || 'Erro ao carregar mensagens', 'error');
      } finally {
        state.loadingMessages = false;
      }
    }

    function openConversation(phone, markRead=true){
      state.selectedPhone = digits(phone);
      state.lastMessageHash = '';
      state.lastCharge = null;
      const url = new URL(location.href);
      url.searchParams.set('pagina', 'chat');
      url.searchParams.set('phone', state.selectedPhone);
      history.replaceState(null, '', url.toString());
      renderThreads();
      setActiveHeader(state.threads.find(t => t.phone === state.selectedPhone));
      loadMessages(markRead);
    }

    async function sendMessage(){
      const phone = state.selectedPhone;
      const message = el('messageText').value.trim();
      if (!phone || !message) return;
      if (!threadWindow(state.activeThread).open) {
        toast('Janela fechada. Envie um modelo aprovado primeiro.', 'error');
        updateWindowPanel();
        return;
      }

      const ok = await askConfirm({
        title:'Enviar resposta',
        subtitle:state.activeThread?.display_name || phone,
        message,
        note:'Texto livre sera enviado agora pela janela ativa de 24h.',
        okText:'Enviar resposta'
      });
      if (!ok) return;

      const btn = el('btnSend');
      const text = el('messageText');
      btn.disabled = true;
      text.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

      try {
        await fetchJson('/painel/api/chat_send.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({phone, message})
        });
        text.value = '';
        await loadMessages(false);
        await loadThreads(false);
      } catch (e) {
        await loadMessages(false);
        await loadThreads(false);
        toast(e.message || 'Falha ao enviar', 'error');
      } finally {
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
        updateWindowPanel();
        text.focus();
      }
    }

    async function sendTemplate(){
      const phone = state.selectedPhone;
      if (!phone) return;
      const ok = await askConfirm({
        title:'Enviar mensagem de abertura',
        subtitle:state.activeThread?.display_name || phone,
        message:'Deseja enviar o modelo inicial aprovado para abrir atendimento?',
        note:'Esse envio usa o template configurado em META_TEMPLATE_CHAT_START_NAME.',
        okText:'Enviar abertura'
      });
      if (!ok) return;

      const btn = el('btnSendTemplate');
      const bottomBtn = el('btnSendTemplateBottom');
      btn.disabled = true;
      bottomBtn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
      bottomBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

      try {
        await fetchJson('/painel/api/chat_send_template.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({phone})
        });
        await loadMessages(false);
        await loadThreads(false);
        toast('Modelo enviado');
      } catch (e) {
        await loadMessages(false);
        await loadThreads(false);
        toast(e.message || 'Falha ao enviar modelo', 'error');
      } finally {
        btn.disabled = !state.selectedPhone;
        bottomBtn.disabled = !state.selectedPhone;
        btn.innerHTML = '<i class="fa-solid fa-file-lines"></i>';
        bottomBtn.innerHTML = '<i class="fa-solid fa-file-lines"></i>';
        updateWindowPanel();
      }
    }

    async function resendLastCharge(){
      const phone = state.selectedPhone;
      const lastCharge = state.lastCharge;
      if (!phone || !lastCharge?.source_ref) {
        toast('Nenhuma cobranca enviada encontrada nesta conversa.', 'error');
        return;
      }

      const ok = await askConfirm({
        title:'Reenviar cobranca',
        subtitle:state.activeThread?.display_name || phone,
        message:'Deseja reenviar a ultima mensagem de cobranca desta conversa?',
        note:lastCharge.body || 'A mesma cobranca sera reenviada pelo template de recobranca.',
        okText:'Reenviar cobranca'
      });
      if (!ok) return;

      const btn = el('btnResendCharge');
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Reenviando';

      try {
        await fetchJson('/painel/api/run_resend_whatsapp.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({
            run_id:lastCharge.source_ref,
            phone,
            mode:'same',
            reason:'Reenvio pelo chat'
          })
        });
        await loadMessages(false);
        await loadThreads(false);
        toast('Cobranca reenviada');
      } catch (e) {
        await loadMessages(false);
        toast(e.message || 'Falha ao reenviar cobranca', 'error');
      } finally {
        btn.innerHTML = '<i class="fa-solid fa-repeat"></i> Reenviar cobranca';
        setActiveHeader(state.activeThread);
      }
    }

    el('threadList').addEventListener('click', (event) => {
      const btn = event.target.closest('button[data-phone]');
      if (!btn) return;
      openConversation(btn.getAttribute('data-phone'));
    });

    el('btnNewChat').onclick = () => {
      el('newChatBox').classList.toggle('show');
      if (el('newChatBox').classList.contains('show')) el('newPhone').focus();
    };

    el('btnOpenPhone').onclick = () => {
      const phone = digits(el('newPhone').value);
      if (!/^55\d{10,11}$/.test(phone)) {
        toast('Telefone invalido', 'error');
        return;
      }
      openConversation(phone);
    };

    el('newPhone').addEventListener('keydown', (event) => {
      if (event.key === 'Enter') el('btnOpenPhone').click();
    });

    el('searchThreads').addEventListener('keydown', (event) => {
      if (event.key === 'Enter') loadThreads(true);
    });
    el('searchThreads').addEventListener('input', () => {
      clearTimeout(state.searchTimer);
      state.searchTimer = setTimeout(() => loadThreads(false), 350);
    });
    el('threadFilters').addEventListener('click', (event) => {
      const btn = event.target.closest('button[data-filter]');
      if (!btn) return;
      state.threadFilter = btn.getAttribute('data-filter') || 'all';
      syncFilterUi();
      loadThreads(true);
    });
    el('onlyUnread').onchange = () => {
      state.threadFilter = el('onlyUnread').checked ? 'unread' : 'all';
      syncFilterUi();
      loadThreads(true);
    };
    el('btnReloadThreads').onclick = () => loadThreads(true);
    el('btnSend').onclick = sendMessage;
    el('btnSendTemplate').onclick = sendTemplate;
    el('btnSendTemplateBottom').onclick = sendTemplate;
    el('btnResendCharge').onclick = resendLastCharge;
    el('confirmCancel').onclick = () => closeConfirm(false);
    el('confirmOk').onclick = () => closeConfirm(true);
    el('confirmBackdrop').addEventListener('click', (event) => {
      if (event.target === el('confirmBackdrop')) closeConfirm(false);
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && el('confirmBackdrop').classList.contains('show')) closeConfirm(false);
    });
    el('messageText').addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
      }
    });

    const initialPhone = state.selectedPhone;
    syncFilterUi();
    loadThreads(true).then(() => {
      if (initialPhone) openConversation(initialPhone);
    });

    state.threadTimer = setInterval(() => loadThreads(false), 6000);
    state.messageTimer = setInterval(() => loadMessages(false), 3500);
    setInterval(updateWindowPanel, 1000);
  </script>
</div>
