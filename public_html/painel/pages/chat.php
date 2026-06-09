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
      min-height:72px;
      padding:12px 18px;
      border-bottom:1px solid var(--line);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      background:#fff;
      box-sizing:border-box;
    }

    .chat-title{display:flex;align-items:center;gap:10px;min-width:0;}
    .chat-title i{color:var(--brand);font-size:22px;}
    .chat-title strong{display:block;font-size:18px;font-weight:900;line-height:1.08;}
    .chat-title span{display:block;color:var(--muted);font-size:12px;font-weight:800;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    .icon-btn{
      width:40px;
      height:40px;
      border:1px solid var(--line);
      border-radius:8px;
      background:#fff;
      color:var(--text);
      display:inline-flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      transition:.18s;
      flex:0 0 40px;
      font-size:15px;
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
    .filter-toggle{
      border:0;
      background:transparent;
      color:var(--brand-dark);
      font-family:'Nunito',sans-serif;
      font-size:12px;
      font-weight:900;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:6px 0;
      white-space:nowrap;
    }
    .filter-toggle:hover{text-decoration:underline;text-underline-offset:3px;}

    .filter-tabs{
      display:flex;
      flex-wrap:wrap;
      gap:6px;
      padding:4px;
      border:1px solid var(--line);
      border-radius:8px;
      background:#f2f6fb;
    }
    .filter-tab{
      min-height:36px;
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
      flex:1 1 92px;
      min-width:0;
      padding:7px 8px;
    }
    .filter-tab:hover{background:#fff;color:var(--brand-dark);}
    .filter-tab.active{background:#fff;color:#172033;box-shadow:0 1px 3px rgba(23,32,51,.06);}
    .chat-search.filters-hidden .filter-tabs{display:none;}

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
    .thread-item.reviewing{background:#fff7ed;border-color:#fed7aa;}
    .thread-item.reviewing.active{background:#ffedd5;border-color:#fb923c;}
    .thread-item.reviewing .avatar{background:#f97316;}
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
    .thread-review-chip{display:inline-flex;align-items:center;gap:6px;margin-top:8px;margin-left:6px;border-radius:8px;padding:4px 7px;font-size:11px;font-weight:900;background:#fed7aa;color:#9a3412;}
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
    .mobile-chat-back{display:none;}
    .charge-btn{
      height:40px;
      border:1px solid #bfe8ff;
      border-radius:8px;
      background:#eef8ff;
      color:#12628f;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding:0 12px;
      cursor:pointer;
      transition:.18s;
      font-family:'Nunito',sans-serif;
      font-size:12px;
      font-weight:900;
      white-space:nowrap;
    }
    .charge-btn:hover{background:#dff3ff;border-color:#8bd5ff;}
    .charge-btn:disabled{opacity:.45;cursor:not-allowed;background:#f3f7fb;border-color:var(--line);color:#7d8da1;}
    .profile-btn,
    .review-btn{
      height:40px;
      border:1px solid var(--line);
      border-radius:8px;
      background:#fff;
      color:var(--text);
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding:0 12px;
      cursor:pointer;
      transition:.18s;
      font-family:'Nunito',sans-serif;
      font-size:12px;
      font-weight:900;
      white-space:nowrap;
      text-decoration:none;
      box-sizing:border-box;
    }
    .profile-btn:hover{border-color:#cfe0ff;color:var(--brand-dark);background:#f7fbff;}
    .review-btn:hover{border-color:#fdba74;color:#c2410c;background:#fff7ed;}
    .review-btn.active{background:#f97316;border-color:#f97316;color:#fff;}
    .review-btn:disabled{opacity:.45;cursor:not-allowed;background:#f3f7fb;border-color:var(--line);color:#7d8da1;}

    .window-panel{
      border-bottom:1px solid var(--line);
      background:#fff;
      padding:6px 18px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
      cursor:pointer;
    }
    .window-panel:hover{filter:brightness(.99);}
    .window-copy{display:flex;align-items:center;gap:8px;min-width:0;}
    .window-copy i{font-size:15px;}
    .window-copy strong{display:block;font-size:13px;font-weight:900;}
    .window-copy span{display:none;}
    .window-panel.open{background:#f4fff9;}
    .window-panel.open .window-copy i{color:var(--ok);}
    .window-panel.closed{background:#fffaf0;}
    .window-panel.closed .window-copy i{color:var(--warn);}
    .window-timer{font-size:13px;font-weight:900;white-space:nowrap;color:var(--text);}

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
    .chat-main-pane.reviewing{background:#fff7ed;}
    .chat-main-pane.reviewing > .chat-pane-head{background:#fff7ed;border-bottom-color:#fed7aa;}
    .chat-main-pane.reviewing .chat-title i{color:#f97316;}
    .chat-main-pane.reviewing .messages{background:#fff7ed;}
    .chat-main-pane.reviewing .window-panel{border-bottom-color:#fed7aa;}
    .chat-main-pane.reviewing .window-panel.open,
    .chat-main-pane.reviewing .window-panel.closed{background:#ffedd5;}
    .chat-main-pane.reviewing .bubble{border-color:#fed7aa;}
    .chat-main-pane.reviewing .msg-row.out .bubble{background:#ffedd5;border-color:#fdba74;}
    .chat-main-pane.reviewing .composer{border-top-color:#fed7aa;background:#fffaf4;}

    .empty-state{
      margin:auto;
      text-align:center;
      color:var(--muted);
      font-weight:800;
      max-width:360px;
      line-height:1.45;
    }
    .empty-state i{font-size:42px;color:#b5c3d4;margin-bottom:12px;display:block;}

    .date-separator{
      display:flex;
      align-items:center;
      gap:12px;
      margin:8px 0 4px;
      color:#718198;
      font-size:12px;
      font-weight:900;
      line-height:1;
      text-align:center;
    }
    .date-separator::before,
    .date-separator::after{
      content:"";
      height:1px;
      flex:1;
      background:var(--line);
    }
    .date-separator span{
      white-space:nowrap;
      max-width:min(72vw, 360px);
      overflow:hidden;
      text-overflow:ellipsis;
    }

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
    .bubble.media-bubble{
      background:transparent;
      border:0;
      box-shadow:none;
      padding:0;
      max-width:min(460px,86%);
    }
    .msg-row.out .bubble.media-bubble{background:transparent;border-color:transparent;}
    .bubble.media-bubble .msg-body{
      display:inline-block;
      max-width:100%;
      border:1px solid rgba(214,226,238,.95);
      border-radius:12px;
      background:#fff;
      padding:9px 11px;
      box-sizing:border-box;
    }
    .msg-row.out .bubble.media-bubble .msg-body{
      background:var(--bubble-out);
      border-color:var(--bubble-out-border);
    }
    .bubble.media-bubble .msg-foot{
      justify-content:flex-start;
      width:max-content;
      max-width:100%;
      margin-top:6px;
      padding:4px 7px;
      border-radius:999px;
      background:rgba(255,255,255,.82);
      border:1px solid rgba(214,226,238,.85);
      backdrop-filter:blur(4px);
    }
    .msg-row.out .bubble.media-bubble .msg-foot{margin-left:auto;}
    .msg-body{font-size:15px;font-weight:800;line-height:1.5;white-space:pre-wrap;}
    .msg-body a{color:#12628f;text-decoration:underline;text-underline-offset:2px;overflow-wrap:anywhere;}
    .media-box{display:grid;gap:8px;margin-bottom:9px;}
    .bubble.media-bubble .media-box{margin-bottom:0;}
    .bubble.media-bubble .msg-body.media-caption{margin-top:8px;}
    .media-img{
      width:168px;
      height:168px;
      border-radius:12px;
      border:1px solid rgba(214,226,238,.95);
      background:#eef5fb;
      display:block;
      object-fit:cover;
      cursor:pointer;
    }
    .media-img:hover{filter:brightness(.96);}
    .media-image-btn{
      border:0;
      padding:0;
      margin:0;
      background:transparent;
      border-radius:12px;
      cursor:pointer;
      display:block;
      line-height:0;
    }
    .media-video{max-width:360px;width:100%;border-radius:12px;border:1px solid rgba(214,226,238,.95);background:#eef5fb;display:block;}
    .media-audio-card{
      display:grid;
      grid-template-columns:50px minmax(0,1fr) 42px;
      align-items:center;
      gap:12px;
      width:min(390px,100%);
      border:1px solid rgba(191,232,255,.95);
      border-radius:14px;
      background:#fff;
      padding:12px;
      box-sizing:border-box;
      box-shadow:0 4px 14px rgba(23,32,51,.06);
    }
    .media-audio-icon{
      width:50px;
      height:50px;
      border-radius:11px;
      background:#38b6ff;
      color:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:20px;
      box-shadow:inset 0 -10px 18px rgba(22,119,168,.16),0 4px 10px rgba(56,182,255,.18);
    }
    .media-audio-info{min-width:0;display:grid;gap:8px;}
    .media-audio-title{display:flex;align-items:center;justify-content:space-between;gap:10px;min-width:0;}
    .media-audio-title strong{font-size:13px;font-weight:900;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .media-audio-title span{font-size:11px;font-weight:900;color:#526e95;text-transform:uppercase;white-space:nowrap;}
    .media-audio-shell{
      display:flex;
      align-items:center;
      min-width:0;
      height:40px;
      border-radius:999px;
      background:#f1f7fc;
      border:1px solid #e0edf7;
      padding:0 8px;
      box-sizing:border-box;
    }
    .media-audio{width:100%;height:32px;display:block;filter:sepia(8%) saturate(120%) hue-rotate(165deg);}
    .media-audio::-webkit-media-controls-enclosure{
      border-radius:999px;
      background:#f1f7fc;
    }
    .media-audio::-webkit-media-controls-panel{
      background:#f1f7fc;
    }
    .media-download{
      width:42px;
      height:42px;
      border-radius:999px;
      display:flex;
      align-items:center;
      justify-content:center;
      background:#eef8ff;
      color:#12628f;
      text-decoration:none;
      border:1px solid #bfe8ff;
      font-size:16px;
      box-shadow:0 1px 3px rgba(18,98,143,.08);
    }
    .media-download:hover{background:#dff3ff;border-color:#8bd5ff;color:#0f5f89;}
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
      cursor:pointer;
      font-family:'Nunito',sans-serif;
      text-align:left;
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

    .image-viewer{
      position:fixed;
      inset:0;
      z-index:1400;
      background:rgba(15,23,42,.82);
      display:none;
      align-items:center;
      justify-content:center;
      padding:24px;
    }
    .image-viewer.show{display:flex;}
    .image-viewer img{
      max-width:min(1100px,96vw);
      max-height:86vh;
      border-radius:12px;
      background:#fff;
      box-shadow:0 18px 60px rgba(0,0,0,.28);
      object-fit:contain;
    }
    .image-viewer-frame{
      width:min(1100px,96vw);
      height:86vh;
      border:0;
      border-radius:12px;
      background:#fff;
      box-shadow:0 18px 60px rgba(0,0,0,.28);
      display:none;
    }
    .image-viewer-title{
      position:absolute;
      top:20px;
      left:24px;
      right:76px;
      color:#fff;
      font-size:14px;
      font-weight:900;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
      pointer-events:none;
    }
    .image-viewer-close{
      position:absolute;
      top:18px;
      right:18px;
      width:44px;
      height:44px;
      border-radius:999px;
      border:1px solid rgba(255,255,255,.3);
      background:rgba(255,255,255,.14);
      color:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      font-size:18px;
    }
    .image-viewer-close:hover{background:rgba(255,255,255,.24);}

    @media(max-width:920px){
      .chat-wrap{height:calc(100dvh - var(--header-height, 70px));min-height:0;overflow:hidden;}
      .chat-shell{height:100%;min-height:0;grid-template-columns:1fr;}
      .chat-list-pane{height:100%;min-height:0;border-right:none;border-bottom:0;}
      .chat-main-pane{display:none;height:100%;min-height:0;}
      .chat-shell.conversation-open .chat-list-pane{display:none;}
      .chat-shell.conversation-open .chat-main-pane{display:flex;}
      .mobile-chat-back{display:inline-flex;}
      .messages{padding:16px;}
      .bubble{max-width:90%;}
      .chat-search{padding:12px;}
      .chat-search.filters-hidden{gap:8px;}
    }
    @media(max-width:620px){
      .chat-pane-head{
        min-height:auto;
        padding:14px;
        align-items:flex-start;
      }
      .chat-title strong{font-size:17px;}
      .chat-title span{font-size:12px;max-width:58vw;}
      .chat-actions{
        gap:6px;
      }
      .icon-btn{
        width:42px;
        height:42px;
        flex-basis:42px;
      }
      .filter-tab{font-size:11px;gap:4px;}
      .thread-item{
        grid-template-columns:42px minmax(0,1fr) auto;
        padding:12px;
      }
      .avatar{
        width:42px;
        height:42px;
      }
      .charge-btn{
        width:100%;
        justify-content:center;
        min-height:42px;
        white-space:normal;
      }
      .chat-main-pane > .chat-pane-head{
        flex-direction:column;
      }
      .chat-main-pane > .chat-pane-head .chat-actions{
        width:100%;
        display:grid;
        grid-template-columns:42px minmax(0,1fr) 42px;
        align-items:center;
      }
      .chat-main-pane > .chat-pane-head .chat-actions .status-pill{
        grid-column:1 / -1;
        justify-self:start;
      }
      .window-panel{
        align-items:flex-start;
        flex-direction:column;
        padding:12px 14px;
      }
      .composer{
        grid-template-columns:42px minmax(0,1fr) 42px;
        padding:10px;
        gap:8px;
      }
      .chat-textarea{
        min-height:44px;
        max-height:120px;
        padding:11px 12px;
      }
      .media-video,
      .media-img{
        max-width:calc(100vw - 52px);
      }
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
        <div class="chat-actions">
          <button class="icon-btn" id="btnCacheMedia" type="button" title="Salvar midias recentes">
            <i class="fa-solid fa-box-archive"></i>
          </button>
          <button class="icon-btn" id="btnNewChat" type="button" title="Abrir conversa por numero">
            <i class="fa-solid fa-plus"></i>
          </button>
        </div>
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
          <button class="filter-tab" type="button" data-filter="available"><i class="fa-regular fa-comment-dots"></i> Disponivel</button>
          <button class="filter-tab" type="button" data-filter="review"><i class="fa-solid fa-clipboard-check"></i> Revisao</button>
          <button class="filter-tab" type="button" data-filter="received"><i class="fa-solid fa-inbox"></i> Recebidas</button>
          <button class="filter-tab" type="button" data-filter="unread"><i class="fa-solid fa-circle"></i> Nao lidas</button>
        </div>
        <div class="switch-line">
          <input type="checkbox" id="onlyUnread" hidden>
          <span id="filterHint">Todas as conversas</span>
          <button class="filter-toggle" id="btnToggleFilters" type="button">
            <i class="fa-solid fa-sliders"></i>
            <span id="filterToggleText">Ocultar filtros</span>
          </button>
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
          <button class="icon-btn mobile-chat-back" id="btnCloseMobileChat" type="button" title="Voltar para conversas">
            <i class="fa-solid fa-arrow-left"></i>
          </button>
          <a class="profile-btn" id="btnVindiProfile" href="#" target="_blank" rel="noopener" style="display:none;">
            <i class="fa-solid fa-user"></i> Perfil
          </a>
          <button class="review-btn" id="btnReviewChat" type="button" disabled>
            <i class="fa-solid fa-clipboard-check"></i> <span id="reviewText">Revisão</span>
          </button>
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
  <div class="image-viewer" id="imageViewer" aria-hidden="true">
    <div class="image-viewer-title" id="imageViewerTitle"></div>
    <button class="image-viewer-close" id="imageViewerClose" type="button" title="Fechar">
      <i class="fa-solid fa-xmark"></i>
    </button>
    <img id="imageViewerImg" alt="Imagem recebida">
    <iframe class="image-viewer-frame" id="imageViewerFrame" title="Documento recebido"></iframe>
  </div>
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
      messageController: null,
      messageRequestSeq: 0,
      threadController: null,
      lastMessageHash: '',
      lastCharge: null,
      threadFilter: 'all',
      confirmResolve: null,
      backfillDone: false,
      threadTimer: null,
      messageTimer: null,
      notificationsReady: false,
      notificationSnapshot: new Map(),
      notificationBaselineDone: false,
      originalTitle: document.title,
      titleTimer: null,
      audioContext: null,
      filtersHidden: localStorage.getItem('chatFiltersHidden') === 'true'
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

    function messageDateKey(value){
      const dt = parseServerDate(value);
      if (!dt) return String(value ?? '').slice(0, 10);
      const y = dt.getFullYear();
      const m = String(dt.getMonth() + 1).padStart(2, '0');
      const d = String(dt.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    }

    function dateSeparatorLabel(value){
      const dt = parseServerDate(value);
      if (!dt) return String(value ?? '');
      const today = new Date();
      const yesterday = new Date();
      yesterday.setDate(today.getDate() - 1);

      if (dt.toDateString() === today.toDateString()) return 'Hoje';
      if (dt.toDateString() === yesterday.toDateString()) return 'Ontem';

      return dt.toLocaleDateString('pt-BR', {
        day:'numeric',
        month:'long',
        year:'numeric'
      });
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

    function isMobileChat(){
      return window.matchMedia('(max-width: 920px)').matches;
    }

    function setConversationOpen(open){
      const shell = document.querySelector('.chat-shell');
      if (shell) shell.classList.toggle('conversation-open', Boolean(open));
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

    function isThreadInReview(thread){
      return Number(thread?.in_review || 0) === 1;
    }

    function syncProfileButton(thread){
      const btn = el('btnVindiProfile');
      if (!btn) return;

      const customerId = String(thread?.customer_id || '').replace(/\D+/g, '');
      if (!customerId) {
        btn.style.display = 'none';
        btn.removeAttribute('href');
        return;
      }

      btn.href = `https://app.vindi.com.br/admin/customers/${encodeURIComponent(customerId)}#tab-bills`;
      btn.style.display = 'inline-flex';
    }

    function syncReviewUi(thread){
      const reviewing = isThreadInReview(thread);
      const main = document.querySelector('.chat-main-pane');
      const btn = el('btnReviewChat');
      if (main) main.classList.toggle('reviewing', reviewing);
      if (!btn) return;

      btn.disabled = !state.selectedPhone;
      btn.classList.toggle('active', reviewing);
      btn.title = reviewing ? 'Tirar conversa da revisao' : 'Colocar conversa em revisao';
      btn.innerHTML = reviewing
        ? '<i class="fa-solid fa-clipboard-check"></i> <span id="reviewText">Em revisao</span>'
        : '<i class="fa-regular fa-clipboard"></i> <span id="reviewText">Revisao</span>';
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

    function updateNotifyButton(){
      const btn = el('btnNotify');
      if (!btn) return;
      const supported = 'Notification' in window;
      const granted = supported && Notification.permission === 'granted';
      state.notificationsReady = granted;
      btn.classList.toggle('primary', granted);
      btn.title = !supported
        ? 'Navegador sem notificacoes'
        : (granted ? 'Notificacoes ativas' : 'Ativar notificacoes');
      btn.innerHTML = granted
        ? '<i class="fa-solid fa-bell"></i>'
        : '<i class="fa-regular fa-bell"></i>';
    }

    async function enableNotifications(){
      if (!('Notification' in window)) {
        toast('Este navegador nao suporta notificacoes.', 'error');
        return;
      }
      try {
        if (Notification.permission === 'default') {
          await Notification.requestPermission();
        }
        unlockNotifySound();
        updateNotifyButton();
        toast(Notification.permission === 'granted' ? 'Notificacoes ativadas' : 'Permissao de notificacao bloqueada', Notification.permission === 'granted' ? 'ok' : 'error');
      } catch (e) {
        toast('Nao foi possivel ativar notificacoes', 'error');
      }
    }

    function unlockNotifySound(){
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        if (!state.audioContext) state.audioContext = new Ctx();
        if (state.audioContext.state === 'suspended') state.audioContext.resume();
      } catch (e) {}
    }

    function playNotifySound(){
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        const ctx = state.audioContext || (Ctx ? new Ctx() : null);
        if (!ctx) return;
        state.audioContext = ctx;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.12, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
        osc.connect(gain).connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.3);
      } catch (e) {}
    }

    function flashTitle(count){
      if (state.titleTimer) clearTimeout(state.titleTimer);
      document.title = count > 0 ? `(${count}) Nova mensagem - Chat` : state.originalTitle;
      state.titleTimer = setTimeout(() => {
        document.title = state.originalTitle;
        state.titleTimer = null;
      }, 6000);
    }

    function handleThreadNotifications(rows){
      if (window.LisOnGlobalNotify) return;
      const incomingRows = rows.filter(row => Number(row.unread_count || 0) > 0 && row.last_direction === 'in');
      const nextSnapshot = new Map();
      incomingRows.forEach(row => {
        nextSnapshot.set(row.phone, {
          unread:Number(row.unread_count || 0),
          at:String(row.last_message_at || ''),
          preview:String(row.last_message_preview || '')
        });
      });

      if (!state.notificationBaselineDone) {
        state.notificationSnapshot = nextSnapshot;
        state.notificationBaselineDone = true;
        return;
      }

      incomingRows.forEach(row => {
        const phone = String(row.phone || '');
        const prev = state.notificationSnapshot.get(phone);
        const unread = Number(row.unread_count || 0);
        const at = String(row.last_message_at || '');
        const isNew = !prev || unread > Number(prev.unread || 0) || (at && at !== prev.at);
        if (!isNew) return;

        const name = row.display_name || phone || 'Cliente';
        const preview = row.last_message_preview || 'Nova mensagem recebida';
        const totalUnread = rows.reduce((sum, item) => sum + Number(item.unread_count || 0), 0);
        toast(`${name}: ${preview}`);
        flashTitle(totalUnread);
        playNotifySound();

        if (state.notificationsReady && document.visibilityState !== 'visible') {
          const notification = new Notification('Nova mensagem no chat', {
            body: `${name}: ${preview}`,
            tag: `chat-${phone}`,
            renotify: true
          });
          notification.onclick = () => {
            window.focus();
            openConversation(phone);
            notification.close();
          };
        }
      });

      state.notificationSnapshot = nextSnapshot;
    }

    function syncFilterUi(){
      const labels = {
        all:'Todas as conversas',
        available:'Com janela aberta para responder',
        review:'Conversas em revisao',
        received:'Conversas que receberam mensagem',
        unread:'Conversas nao lidas'
      };
      el('filterHint').textContent = labels[state.threadFilter] || labels.all;
      el('onlyUnread').checked = state.threadFilter === 'unread';
      document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.filter === state.threadFilter);
      });
    }

    function syncFilterVisibility(){
      const search = document.querySelector('.chat-search');
      if (!search) return;
      search.classList.toggle('filters-hidden', state.filtersHidden);
      el('filterToggleText').textContent = state.filtersHidden ? 'Mostrar filtros' : 'Ocultar filtros';
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
        const reviewing = isThreadInReview(row);
        const unread = Number(row.unread_count || 0);
        return `
          <button class="thread-item ${active} ${reviewing ? 'reviewing' : ''}" type="button" data-phone="${esc(phone)}">
            <div class="avatar">${esc(initials(name, phone))}</div>
            <div style="min-width:0;">
              <div class="thread-name">${esc(name)}</div>
              <div class="thread-phone">${esc(phone)}</div>
              <div class="thread-preview">${esc(row.last_message_preview || 'Sem mensagens')}</div>
              ${windowMini(row)}
              ${reviewing ? '<div class="thread-review-chip"><i class="fa-solid fa-clipboard-check"></i> em revisao</div>' : ''}
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
      if (state.threadController) state.threadController.abort();
      state.threadController = new AbortController();
      const controller = state.threadController;
      const url = new URL('/painel/api/chat_threads.php', location.origin);
      const q = el('searchThreads').value.trim();
      if (q) url.searchParams.set('q', q);
      if (el('onlyUnread').checked || state.threadFilter === 'unread') url.searchParams.set('unread', '1');
      if (state.threadFilter === 'received') url.searchParams.set('direction', 'in');
      if (state.threadFilter === 'available') url.searchParams.set('window', 'open');
      if (state.threadFilter === 'review') url.searchParams.set('review', '1');
      if (!state.backfillDone) url.searchParams.set('backfill', '1');

      try {
        const data = await fetchJson(url.toString(), {cache:'no-store', signal:controller.signal});
        if (controller !== state.threadController) return;
        state.backfillDone = true;
        state.threads = Array.isArray(data.rows) ? data.rows : [];
        el('threadCount').textContent = `${data.total ?? state.threads.length} conversas`;
        el('listStatus').textContent = 'online';
        const selected = state.threads.find(t => t.phone === state.selectedPhone);
        if (selected) {
          state.activeThread = {...(state.activeThread || {}), ...selected};
          syncProfileButton(state.activeThread);
          syncReviewUi(state.activeThread);
          updateWindowPanel();
        }
        renderThreads();
        handleThreadNotifications(state.threads);
        if (!state.selectedPhone && state.threads[0]?.phone && !isMobileChat()) {
          openConversation(state.threads[0].phone, false);
        }
      } catch (e) {
        if (e.name === 'AbortError') return;
        el('listStatus').textContent = 'erro';
        if (manual) toast(e.message || 'Erro ao carregar conversas', 'error');
      } finally {
        if (controller === state.threadController) state.threadController = null;
      }
    }

    function messageHtml(msg){
      const dir = msg.direction === 'out' ? 'out' : 'in';
      const st = statusLabel(msg.status);
      const error = msg.error_text ? `<div class="msg-error">${esc(msg.error_text)}</div>` : '';
      const media = mediaHtml(msg);
      const body = messageBodyHtml(msg);
      const bubbleClass = media ? 'bubble media-bubble' : 'bubble';
      return `
        <div class="msg-row ${dir}">
          <div class="${bubbleClass}">
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

    function renderMessages(messages){
      const parts = [];
      let lastKey = '';

      messages.forEach(msg => {
        const key = messageDateKey(msg.created_at);
        if (key !== lastKey) {
          parts.push(`<div class="date-separator"><span>${esc(dateSeparatorLabel(msg.created_at))}</span></div>`);
          lastKey = key;
        }
        parts.push(messageHtml(msg));
      });

      return parts.join('');
    }

    function mediaUrl(msg, download=false){
      const suffix = download ? '&download=1' : '';
      return `/painel/api/chat_media.php?id=${encodeURIComponent(msg.id)}${suffix}`;
    }

    function messageBodyHtml(msg){
      const type = String(msg.message_type || '').toLowerCase();
      let body = String(msg.body || '');
      const placeholders = {
        image: ['[imagem]'],
        video: ['[video]'],
        audio: ['[audio]'],
        sticker: ['[figurinha]'],
        document: ['[documento]']
      };
      if (placeholders[type]?.includes(body.trim().toLowerCase())) return '';
      if (type === 'image') body = body.replace(/^\[imagem\]\s*/i, '');
      if (type === 'video') body = body.replace(/^\[video\]\s*/i, '');
      if (type === 'audio') body = body.replace(/^\[audio\]\s*/i, '');
      if (type === 'document') body = body.replace(/^\[documento\]\s*/i, '');
      if (type === 'sticker') body = body.replace(/^\[figurinha\]\s*/i, '');
      body = body.trim();
      if (body === '') return '';
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
      if (mime.includes('audio/ogg') || mime.includes('opus')) return 'Audio OGG';
      if (mime.includes('audio/mpeg') || mime.includes('mp3')) return 'Audio MP3';
      if (mime.includes('audio')) return 'Audio';
      return 'Arquivo';
    }

    function mediaAudioName(msg){
      const media = msg.media || {};
      return media.filename || `audio-${msg.id}.ogg`;
    }

    function mediaHtml(msg){
      const type = String(msg.message_type || '').toLowerCase();
      const url = mediaUrl(msg);
      if (type === 'image' || type === 'sticker') {
        return `<div class="media-box"><button class="media-image-btn" type="button" data-image-url="${attr(url)}" aria-label="Abrir imagem"><img class="media-img" src="${attr(url)}" loading="lazy" alt="Midia recebida"></button></div>`;
      }
      if (type === 'video') {
        return `<div class="media-box"><video class="media-video" src="${attr(url)}" controls preload="metadata"></video></div>`;
      }
      if (type === 'audio') {
        const name = mediaAudioName(msg);
        const kind = mediaKindLabel(msg);
        const downloadUrl = mediaUrl(msg, true);
        return `
          <div class="media-box">
            <div class="media-audio-card">
              <span class="media-audio-icon"><i class="fa-solid fa-microphone-lines"></i></span>
              <span class="media-audio-info">
                <span class="media-audio-title">
                  <strong>${esc(name)}</strong>
                  <span>${esc(kind)}</span>
                </span>
                <span class="media-audio-shell">
                  <audio class="media-audio" src="${attr(url)}" controls preload="metadata">
                    Seu navegador nao conseguiu tocar este audio.
                  </audio>
                </span>
              </span>
              <a class="media-download" href="${attr(downloadUrl)}" download="${attr(name)}" title="Baixar audio">
                <i class="fa-solid fa-download"></i>
              </a>
            </div>
          </div>
        `;
      }
      if (type === 'document') {
        const name = mediaFileName(msg);
        const kind = mediaKindLabel(msg);
        return `
          <div class="media-box">
            <button class="media-file" type="button" data-doc-url="${attr(url)}" data-doc-title="${attr(name)}" title="Abrir documento">
              <span class="media-file-icon"><i class="fa-regular fa-file-lines"></i></span>
              <span style="min-width:0;">
                <span class="media-file-name">${esc(name)}</span>
                <span class="media-file-meta">${esc(kind)} - tocar para visualizar</span>
              </span>
              <span class="media-file-open"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
            </button>
          </div>
        `;
      }
      return '';
    }

    function openImageViewer(url, title='Imagem recebida'){
      if (!url) return;
      el('imageViewerFrame').removeAttribute('src');
      el('imageViewerFrame').style.display = 'none';
      el('imageViewerImg').src = url;
      el('imageViewerImg').style.display = 'block';
      el('imageViewerTitle').textContent = title;
      el('imageViewer').classList.add('show');
      el('imageViewer').setAttribute('aria-hidden', 'false');
      el('imageViewerClose').focus();
    }

    function openDocumentViewer(url, title='Documento recebido'){
      if (!url) return;
      el('imageViewerImg').removeAttribute('src');
      el('imageViewerImg').style.display = 'none';
      el('imageViewerFrame').src = url;
      el('imageViewerFrame').style.display = 'block';
      el('imageViewerTitle').textContent = title;
      el('imageViewer').classList.add('show');
      el('imageViewer').setAttribute('aria-hidden', 'false');
      el('imageViewerClose').focus();
    }

    function closeImageViewer(){
      el('imageViewer').classList.remove('show');
      el('imageViewer').setAttribute('aria-hidden', 'true');
      el('imageViewerImg').removeAttribute('src');
      el('imageViewerFrame').removeAttribute('src');
      el('imageViewerTitle').textContent = '';
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
      syncProfileButton(state.activeThread);
      syncReviewUi(state.activeThread);
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
        panel.dataset.help = 'Abra uma conversa para ver se o texto livre esta disponivel.';
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
        panel.dataset.help = 'Janela aberta pela ultima mensagem recebida do cliente. Voce pode enviar texto livre agora.';
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
      panel.dataset.help = 'Envie um modelo aprovado; texto livre volta quando o cliente responder.';
      el('windowTimer').textContent = 'modelo';
      composer.classList.add('blocked');
      text.disabled = true;
      send.disabled = true;
      el('btnSendTemplateBottom').disabled = false;
      text.placeholder = 'Use o botao de modelo para iniciar atendimento';
    }

    async function loadMessages(markRead=false){
      if (!state.selectedPhone) return;
      if (state.loadingMessages && !markRead) return;
      if (state.messageController && markRead) state.messageController.abort();

      const requestPhone = state.selectedPhone;
      const requestSeq = ++state.messageRequestSeq;
      const controller = new AbortController();
      state.messageController = controller;
      state.loadingMessages = true;
      const url = new URL('/painel/api/chat_messages.php', location.origin);
      url.searchParams.set('phone', requestPhone);
      if (markRead) url.searchParams.set('mark_read', '1');

      try {
        const data = await fetchJson(url.toString(), {cache:'no-store', signal:controller.signal});
        if (controller !== state.messageController || requestSeq !== state.messageRequestSeq || requestPhone !== state.selectedPhone) return;
        const messages = Array.isArray(data.messages) ? data.messages : [];
        state.lastCharge = data.last_charge || null;
        const hash = JSON.stringify(messages.map(m => [m.id, m.status, m.message_type, m.body, m.error_text, m.created_at]));
        const activeThread = data.thread || state.threads.find(t => t.phone === requestPhone);
        setActiveHeader(activeThread);

        if (hash !== state.lastMessageHash) {
          state.lastMessageHash = hash;
          el('messages').innerHTML = messages.length
            ? renderMessages(messages)
            : `<div class="empty-state"><i class="fa-regular fa-message"></i>Nenhuma mensagem nesta conversa.</div>`;
          el('messages').scrollTop = el('messages').scrollHeight;
        }

        if (markRead) loadThreads(false);
      } catch (e) {
        if (e.name === 'AbortError') return;
        toast(e.message || 'Erro ao carregar mensagens', 'error');
      } finally {
        if (controller === state.messageController) {
          state.loadingMessages = false;
          state.messageController = null;
        }
      }
    }

    function openConversation(phone, markRead=true){
      state.selectedPhone = digits(phone);
      if (state.messageController) state.messageController.abort();
      state.loadingMessages = false;
      state.lastMessageHash = '';
      state.lastCharge = null;
      setConversationOpen(true);
      const url = new URL(location.href);
      url.searchParams.set('pagina', 'chat');
      url.searchParams.set('phone', state.selectedPhone);
      history.replaceState(null, '', url.toString());
      renderThreads();
      setActiveHeader(state.threads.find(t => t.phone === state.selectedPhone));
      el('messages').innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-spinner fa-spin"></i>
          Carregando conversa...
        </div>
      `;
      loadMessages(markRead);
    }

    function closeMobileChat(){
      setConversationOpen(false);
      if (!isMobileChat()) return;
      state.selectedPhone = '';
      state.activeThread = null;
      state.lastMessageHash = '';
      state.lastCharge = null;
      const url = new URL(location.href);
      url.searchParams.set('pagina', 'chat');
      url.searchParams.delete('phone');
      history.replaceState(null, '', url.toString());
      renderThreads();
      setActiveHeader(null);
      el('messages').innerHTML = `
        <div class="empty-state">
          <i class="fa-regular fa-message"></i>
          Abra uma conversa para ver o historico.
        </div>
      `;
    }

    async function toggleReviewMode(){
      const phone = state.selectedPhone;
      if (!phone) return;

      const next = !isThreadInReview(state.activeThread);
      const btn = el('btnReviewChat');
      btn.disabled = true;

      try {
        const data = await fetchJson('/painel/api/chat_review.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({phone, in_review:next})
        });

        state.activeThread = {...(state.activeThread || {}), ...(data.thread || {}), phone};
        state.threads = state.threads.map(row => row.phone === phone ? {...row, ...state.activeThread} : row);
        renderThreads();
        setActiveHeader(state.activeThread);
        toast(next ? 'Conversa marcada em revisao' : 'Conversa removida da revisao');
      } catch (e) {
        toast(e.message || 'Falha ao atualizar revisao', 'error');
        syncReviewUi(state.activeThread);
      }
    }

    async function cacheRecentMedia(){
      const btn = el('btnCacheMedia');
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

      try {
        const data = await fetchJson('/painel/api/chat_media_backfill.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({limit:30})
        });

        const saved = Number(data.saved || 0);
        const failed = Number(data.failed || 0);
        const checked = Number(data.checked || 0);
        toast(failed > 0
          ? `Midias salvas: ${saved}. Falharam: ${failed}.`
          : (checked > 0 ? `Midias salvas localmente: ${saved}.` : 'Nenhuma midia pendente encontrada.')
        );
      } catch (e) {
        toast(e.message || 'Falha ao salvar midias', 'error');
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
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

    el('messages').addEventListener('click', (event) => {
      const btn = event.target.closest('button[data-image-url], button[data-doc-url]');
      if (!btn) return;
      event.preventDefault();
      if (btn.hasAttribute('data-doc-url')) {
        openDocumentViewer(btn.getAttribute('data-doc-url'), btn.getAttribute('data-doc-title') || 'Documento recebido');
        return;
      }
      openImageViewer(btn.getAttribute('data-image-url'));
    });

    el('btnNewChat').onclick = () => {
      el('newChatBox').classList.toggle('show');
      if (el('newChatBox').classList.contains('show')) el('newPhone').focus();
    };
    if (el('btnNotify')) el('btnNotify').onclick = enableNotifications;

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
    el('btnToggleFilters').onclick = () => {
      state.filtersHidden = !state.filtersHidden;
      localStorage.setItem('chatFiltersHidden', state.filtersHidden ? 'true' : 'false');
      syncFilterVisibility();
    };
    el('onlyUnread').onchange = () => {
      state.threadFilter = el('onlyUnread').checked ? 'unread' : 'all';
      syncFilterUi();
      loadThreads(true);
    };
    el('btnReloadThreads').onclick = () => loadThreads(true);
    el('btnCacheMedia').onclick = cacheRecentMedia;
    el('btnCloseMobileChat').onclick = closeMobileChat;
    el('btnSend').onclick = sendMessage;
    el('btnSendTemplate').onclick = sendTemplate;
    el('btnSendTemplateBottom').onclick = sendTemplate;
    el('btnResendCharge').onclick = resendLastCharge;
    el('btnReviewChat').onclick = toggleReviewMode;
    el('windowPanel').onclick = () => {
      toast(el('windowPanel').dataset.help || 'Status da janela de conversa.');
    };
    el('confirmCancel').onclick = () => closeConfirm(false);
    el('confirmOk').onclick = () => closeConfirm(true);
    el('imageViewerClose').onclick = closeImageViewer;
    el('imageViewer').addEventListener('click', (event) => {
      if (event.target === el('imageViewer')) closeImageViewer();
    });
    el('confirmBackdrop').addEventListener('click', (event) => {
      if (event.target === el('confirmBackdrop')) closeConfirm(false);
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && el('imageViewer').classList.contains('show')) closeImageViewer();
      if (event.key === 'Escape' && el('confirmBackdrop').classList.contains('show')) closeConfirm(false);
    });
    el('messageText').addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
      }
    });

    const initialPhone = state.selectedPhone;
    updateNotifyButton();
    syncFilterUi();
    syncFilterVisibility();
    loadThreads(true).then(() => {
      if (initialPhone) openConversation(initialPhone);
    });

    state.threadTimer = setInterval(() => loadThreads(false), 6000);
    state.messageTimer = setInterval(() => loadMessages(false), 3500);
    setInterval(updateWindowPanel, 1000);
  </script>
</div>
