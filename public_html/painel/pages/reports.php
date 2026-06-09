<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }
?>
<div class="pa-wrap reports-wrap">
  <style>
    .reports-wrap{
      --bg-panel:#f7f9fc;
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
      padding:0 0 26px;
    }
    .rep-header{
      background:transparent;
      border:0;
      padding:0 2px 10px;
      min-height:48px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      position:relative;
      border-radius:0;
      box-shadow:none;
      margin:0 auto 6px;
      gap:16px;
      max-width:1320px;
    }
    .brand{font-size:21px;font-weight:1000;color:#0f172a;display:flex;align-items:center;gap:12px;}
    .brand i{width:42px;height:42px;border-radius:16px;background:#eef8ff;color:#12628f;font-size:17px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 0 0 5px #f7fbff;}
    .rep-title{display:flex;align-items:center;gap:12px;min-width:0;}
    .rep-title i{color:var(--primary);font-size:24px;}
    .rep-title h1{font-size:22px;line-height:1.1;margin:0;font-weight:900;letter-spacing:0;}
    .rep-title p{margin:4px 0 0;color:var(--text-muted);font-size:12px;font-weight:800;}
    .status-pill{
      font-size:12px;color:#526985;background:transparent;padding:0;border-radius:0;
      font-weight:900;border:0;display:flex;align-items:center;gap:8px;white-space:nowrap;box-shadow:none;
    }
    .status-pill.updated{background:transparent;color:var(--green-text);border-color:transparent;transform:none;}
    .status-pill.error{background:var(--red-bg);color:var(--red-text);border-color:var(--red-bg);}
    .status-dot{width:8px;height:8px;border-radius:50%;background:var(--primary);display:inline-block;animation:pulse-dot 1.5s infinite;}
    .rep-container{max-width:1320px;margin:0 auto;padding:0 22px;}
    .toolbar{
      background:#fff;border:2px solid var(--border-color);border-radius:28px;padding:16px 20px;display:flex;gap:12px;align-items:center;
      box-shadow:var(--shadow-soft);margin-bottom:24px;flex-wrap:wrap;transition:box-shadow .25s,border-color .25s;
    }
    .toolbar:hover{border-color:#dbeafe;box-shadow:var(--shadow-hover);}
    .search-box{flex:1;min-width:240px;position:relative;}
    .search-box i{position:absolute;left:19px;top:17px;color:var(--text-muted);}
    .form-control{
      width:100%;height:48px;box-sizing:border-box;border:2px solid transparent;border-radius:var(--radius-pill);background:var(--bg-panel);
      padding:0 20px 0 48px;color:var(--text-main);font-family:'Nunito',sans-serif;font-weight:900;font-size:15px;outline:none;transition:.2s;
    }
    .month-control{width:210px;padding-left:18px;flex:0 0 210px;cursor:pointer;}
    .month-control.is-hidden{display:none;}
    .form-control:focus{background:#fff;border-color:#9bdcff;box-shadow:0 0 0 4px rgba(56,182,255,.12);}
    .btn-primary{
      height:48px;border:0;border-radius:var(--radius-pill);background:var(--primary);color:#fff;padding:0 24px;
      display:inline-flex;align-items:center;justify-content:center;gap:10px;font-family:'Nunito',sans-serif;font-weight:900;cursor:pointer;
      box-shadow:0 4px 6px rgba(59,130,246,.2);transition:.2s;white-space:nowrap;
    }
    .btn-primary:hover{transform:translateY(-1px);background:var(--primary-hover);box-shadow:var(--shadow-hover);}
    .btn-secondary{
      height:48px;border:2px solid var(--border-color);border-radius:var(--radius-pill);background:#fff;color:var(--text-main);padding:0 20px;
      display:inline-flex;align-items:center;justify-content:center;gap:10px;font-family:'Nunito',sans-serif;font-weight:900;cursor:pointer;
      box-shadow:var(--shadow-soft);transition:.2s;white-space:nowrap;
    }
    .btn-secondary:hover{transform:translateY(-1px);border-color:#bfe8ff;color:#12628f;box-shadow:var(--shadow-hover);}
    .btn-primary:disabled,.btn-secondary:disabled{opacity:.55;cursor:not-allowed;transform:none;}
    .filter-select{
      width:auto;min-width:160px;height:48px;border:2px solid var(--border-color);border-radius:var(--radius-pill);background:#fff;color:var(--text-main);
      padding:0 14px;font-family:'Nunito',sans-serif;font-weight:900;box-shadow:var(--shadow-soft);outline:none;cursor:pointer;
    }
    .filter-select:focus{border-color:#9bdcff;box-shadow:none;}
    .summary-grid{
      display:grid;grid-template-columns:repeat(4,minmax(200px,1fr));gap:14px;margin-bottom:20px;background:transparent;border:0;
      border-radius:0;box-shadow:none;overflow:visible;
    }
    .metric{
      background:#fff;border:2px solid var(--border-color);border-radius:20px;padding:20px 64px 17px 21px;box-shadow:var(--shadow-soft);
      display:grid;gap:10px;align-content:center;min-height:132px;box-sizing:border-box;transition:.22s;position:relative;overflow:hidden;
    }
    .metric:last-child{border-right:2px solid var(--border-color);}
    .metric::before{display:none;}
    .metric:hover{background:#fff;transform:translateY(-3px);box-shadow:var(--shadow-hover);border-color:#dbeafe;}
    .metric-label{display:flex;align-items:center;gap:7px;color:#5a6f8c;font-size:12px;font-weight:1000;text-transform:uppercase;white-space:nowrap;letter-spacing:0;}
    .metric-label i{position:absolute;right:18px;top:18px;width:42px;height:42px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#eef8ff;color:#12628f;font-size:16px;flex:0 0 42px;}
    .metric.danger .metric-label i{background:#eef8ff;color:#12628f;}
    .metric.warn .metric-label i{background:var(--yellow-bg);color:var(--yellow-text);}
    .metric.ok .metric-label i{background:var(--green-bg);color:var(--green-text);}
    .metric strong{display:block;font-size:27px;font-weight:1000;line-height:1;color:#0f172a;letter-spacing:0;}
    .metric small{display:block;color:#526985;font-size:12px;font-weight:1000;line-height:1.2;margin-top:-3px;}
    .metric-track{height:5px;border-radius:999px;background:#f1f5f9;overflow:hidden;border:0;margin-top:1px;}
    .metric-fill{display:block;height:100%;width:0;border-radius:999px;background:var(--primary);transition:width .35s ease;}
    .metric.danger::before,.metric.danger .metric-fill{background:var(--primary);}
    .metric.warn::before,.metric.warn .metric-fill{background:#f59e0b;}
    .metric.ok::before,.metric.ok .metric-fill{background:#10b981;}
    .leader-strip{
      display:none;
    }
    .leader-main{display:grid;grid-template-columns:42px 1fr;gap:12px;align-items:center;min-width:0;}
    .avatar-initial{
      width:38px;height:38px;border-radius:14px;background:#eef8ff;color:#12628f;box-shadow:0 0 0 4px #f4f7fa;
      display:inline-flex;align-items:center;justify-content:center;font-size:15px;font-weight:1000;letter-spacing:0;text-transform:uppercase;flex:0 0 auto;
    }
    .avatar-initial.hot{background:#38b6ff;color:#fff;box-shadow:0 0 0 4px #e0f5ff;}
    .leader-main .avatar-initial{width:42px;height:42px;border-radius:15px;}
    .month-board{
      background:#fff;border:2px solid var(--border-color);border-radius:22px;box-shadow:var(--shadow-soft);
      padding:16px 18px;margin:0 0 20px;display:grid;gap:0;transition:box-shadow .25s,border-color .25s;
    }
    .month-board:hover{border-color:#dbeafe;box-shadow:var(--shadow-hover);}
    .month-board.expanded{gap:12px;}
    .month-board-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .month-board-title{display:flex;align-items:center;gap:10px;color:#0f172a;font-size:15px;font-weight:1000;}
    .month-board-title i{color:var(--primary);}
    .month-board-title small{color:var(--text-muted);font-size:12px;font-weight:900;margin-left:4px;}
    .month-years{display:none;gap:12px;max-height:360px;overflow:auto;padding-right:4px;}
    .month-board.expanded .month-years{display:grid;}
    .month-year{display:grid;gap:8px;}
    .month-year-label{color:var(--text-muted);font-size:12px;font-weight:900;}
    .month-grid{display:grid;grid-template-columns:repeat(4,minmax(118px,1fr));gap:8px;}
    .month-btn{
      min-height:48px;border:2px solid var(--border-color);border-radius:16px;background:#fff;color:var(--text-main);
      display:grid;grid-template-columns:1fr auto;align-items:center;gap:10px;text-align:left;padding:10px 13px;font-family:'Nunito',sans-serif;font-weight:900;cursor:pointer;
      box-shadow:var(--shadow-soft);transition:.18s;
    }
    .month-btn:hover{transform:translateY(-1px);border-color:#bfe8ff;color:#12628f;box-shadow:var(--shadow-hover);}
    .month-btn.active{background:#eef8ff;border-color:#9bdcff;color:#12628f;}
    .month-btn span{font-size:13px;text-transform:uppercase;}
    .month-btn small{font-size:12px;color:var(--text-muted);font-weight:900;white-space:nowrap;}
    .meta-line{
      margin:0 0 18px;color:var(--text-muted);font-size:13px;font-weight:900;display:flex;align-items:center;gap:9px;flex-wrap:wrap;
    }
    .meta-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border:1px solid #dfe8f2;border-radius:999px;background:#fff;box-shadow:var(--shadow-soft);white-space:nowrap;}
    .meta-chip i{color:var(--primary);font-size:12px;}
    .meta-chip.ok i{color:var(--green-text);}
    .meta-chip.warn i{color:var(--yellow-text);}
    .meta-chip.danger i{color:var(--red-text);}
    .leader-name{font-weight:1000;font-size:15px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#fff;}
    .leader-sub{display:block;color:#b7c4d6;font-size:12px;font-weight:800;margin-top:3px;}
    .pill{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);font-size:12px;font-weight:900;white-space:nowrap;}
    .row-actions{display:flex;align-items:center;justify-content:flex-end;gap:18px;}
    .row-time{font-size:12px;color:var(--text-muted);font-weight:900;white-space:nowrap;}
    .btn-icon{
      color:#94a3b8;width:42px;height:42px;display:flex;align-items:center;justify-content:center;border-radius:50%;
      transition:.2s;text-decoration:none;border:2px solid transparent;background:transparent;box-shadow:none;
    }
    .btn-icon:hover{background:#f1f5f9;color:var(--primary);border-color:#dbeafe;}
    .btn-mini{
      min-height:38px;border:2px solid var(--border-color);border-radius:999px;background:#fff;color:var(--text-main);padding:0 14px;
      display:inline-flex;align-items:center;justify-content:center;gap:8px;font-family:'Nunito',sans-serif;font-weight:900;font-size:13px;text-decoration:none;cursor:pointer;transition:.18s;
    }
    .btn-mini:hover{border-color:#bfe8ff;color:#12628f;transform:translateY(-1px);}
    .btn-mini.primary{background:#eef8ff;border-color:#bfe8ff;color:#12628f;}
    .btn-mini.danger{background:#fff;border-color:#fecaca;color:#991b1b;}
    .btn-mini.danger:hover{background:#fee2e2;border-color:#fecaca;color:#991b1b;}
    .report-list-panel{
      background:#fff;border:2px solid var(--border-color);border-radius:22px;padding:14px 16px 8px;box-shadow:var(--shadow-soft);transition:box-shadow .25s,border-color .25s;
    }
    .report-list-panel:hover{border-color:#dbeafe;box-shadow:var(--shadow-hover);}
    .report-list-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 8px;padding:3px 2px 7px;border-bottom:1px solid #edf2f7;}
    .report-list-title strong{font-size:15px;font-weight:1000;color:#0f172a;text-transform:uppercase;}
    .report-list-title span{font-size:13px;font-weight:900;color:#64748b;background:#fff;border:1px solid var(--border-color);border-radius:999px;padding:7px 12px;box-shadow:var(--shadow-soft);}
    table{width:100%;border-collapse:separate;border-spacing:0 12px;}
    thead th{color:#526985;font-size:12px;text-transform:uppercase;font-weight:1000;padding:8px 24px 2px;text-align:left;}
    tbody tr{background:white;box-shadow:0 3px 8px rgba(15,23,42,.04);border:2px solid var(--border-color);border-radius:20px;transition:.22s ease;cursor:pointer;}
    tbody tr:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover);border-color:#dbeafe;background:#fff;}
    tbody tr.rep-row.marked{background:#fff7f7;border-color:#fecaca;}
    tbody tr.rep-row.marked:hover{border-color:#fca5a5;box-shadow:0 8px 16px rgba(239,68,68,.1);}
    tbody td{padding:20px 24px;vertical-align:middle;border-top:2px solid var(--border-color);border-bottom:2px solid var(--border-color);background:#fff;}
    tbody tr.marked td{background:#fff7f7;}
    tbody td:first-child{border-top-left-radius:20px;border-bottom-left-radius:20px;border-left:2px solid var(--border-color);}
    tbody td:last-child{border-top-right-radius:20px;border-bottom-right-radius:20px;border-right:2px solid var(--border-color);}
    .customer-name{font-weight:1000;font-size:16px;color:#071832;display:block;text-transform:uppercase;letter-spacing:0;line-height:1.22;}
    .bill-id{font-size:13px;color:#1d9dff;font-weight:1000;margin-top:8px;display:inline-block;background:#eef8ff;padding:6px 11px;border-radius:999px;line-height:1;}
    .row-sub{display:inline-block;margin-left:8px;color:var(--text-muted);font-size:13px;font-weight:900;}
    .badge{min-height:34px;padding:0 14px;border-radius:var(--radius-pill);font-family:inherit;font-weight:900;font-size:12px;line-height:1;text-transform:uppercase;letter-spacing:0;display:inline-flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap;}
    .badge i{font-size:12px;line-height:1;}
    .b-success{background:#e8f8ef71;color:#087f3f;}
    .b-error{background:#fde8e871;color:#b42318;}
    .b-process{background:#e8f1ff71;color:#1d4ed8;}
    .b-paid{background:#d9f8f771;color:#0e7490;}
    .b-pending{background:#fff4d671;color:#b45309;}
    .b-marked{background:#fee2e2;color:#991b1b;}
    .debt-snapshot{
      min-width:206px;display:grid;gap:7px;padding:4px 0;border:0;border-left:0;
      border-radius:0;background:transparent;box-shadow:none;
    }
    .debt-snapshot.marked{background:#fff7f7;border-color:#fecaca;border-left-color:#991b1b;}
    .snapshot-value{display:flex;align-items:center;gap:9px;color:#0f172a;font-size:17px;font-weight:1000;line-height:1;}
    .snapshot-value i{width:auto;height:auto;border-radius:0;background:transparent;color:#0f172a;display:inline-flex;align-items:center;justify-content:center;font-size:12px;}
    .debt-snapshot.marked .snapshot-value{color:#991b1b;}
    .debt-snapshot.marked .snapshot-value i{background:#fee2e2;color:#991b1b;}
    .snapshot-meta{display:flex;align-items:center;gap:14px;color:#64748b;font-size:13px;font-weight:900;line-height:1;white-space:nowrap;}
    .snapshot-meta span{display:inline-flex;align-items:center;gap:5px;}
    .snapshot-meta i{font-size:10px;color:#38b6ff;}
    .detail-date{font-size:13px;color:#526985;font-weight:900;white-space:nowrap;}
    .mark-flag{position:relative;display:inline-flex;align-items:center;gap:6px;margin-left:8px;padding:6px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:1000;line-height:1;vertical-align:middle;}
    .mark-flag .mark-tooltip{position:absolute;left:50%;bottom:calc(100% + 10px);transform:translateX(-50%) translateY(4px);width:max-content;max-width:280px;padding:10px 12px;border-radius:12px;background:#0f172a;color:#fff;box-shadow:0 14px 28px rgba(15,23,42,.22);font-size:12px;font-weight:900;line-height:1.3;white-space:normal;opacity:0;visibility:hidden;pointer-events:none;transition:.18s;z-index:20;text-transform:none;}
    .mark-flag .mark-tooltip::after{content:"";position:absolute;left:50%;top:100%;transform:translateX(-50%);border:7px solid transparent;border-top-color:#0f172a;}
    .mark-flag:hover .mark-tooltip{opacity:1;visibility:visible;transform:translateX(-50%) translateY(0);}
    .report-context-menu{
      position:fixed;z-index:1000;min-width:190px;background:#fff;border:2px solid var(--border-color);border-radius:8px;padding:6px;
      box-shadow:0 18px 38px rgba(15,23,42,.16);display:none;
    }
    .report-context-menu.open{display:grid;gap:4px;}
    .report-context-menu button{
      border:0;background:#fff;color:var(--text-main);height:38px;border-radius:7px;padding:0 10px;display:flex;align-items:center;gap:10px;
      font-family:'Nunito',sans-serif;font-size:13px;font-weight:900;text-align:left;cursor:pointer;
    }
    .report-context-menu button:hover{background:#eef8ff;color:#12628f;}
    .report-context-menu button.danger{color:#991b1b;}
    .report-context-menu button.danger:hover{background:#fee2e2;color:#991b1b;}
    .mark-modal{position:fixed;inset:0;z-index:1100;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.36);}
    .mark-modal.open{display:flex;}
    .mark-card{width:min(460px,100%);background:#fff;border:2px solid var(--border-color);border-radius:12px;box-shadow:0 24px 48px rgba(15,23,42,.2);overflow:hidden;}
    .mark-card-head{display:flex;align-items:center;gap:12px;padding:16px 18px;border-bottom:2px solid var(--border-color);}
    .mark-card-head i{width:36px;height:36px;border-radius:10px;background:#fee2e2;color:#991b1b;display:inline-flex;align-items:center;justify-content:center;flex:0 0 36px;}
    .mark-card-title{font-size:15px;font-weight:1000;color:var(--text-main);display:block;}
    .mark-card-sub{font-size:12px;font-weight:900;color:var(--text-muted);display:block;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:330px;}
    .mark-card-body{padding:16px 18px;display:grid;gap:8px;}
    .mark-card-body label{font-size:12px;font-weight:1000;color:var(--text-muted);text-transform:uppercase;}
    .mark-card-body textarea{width:100%;min-height:110px;box-sizing:border-box;border:2px solid var(--border-color);border-radius:8px;resize:vertical;padding:12px;font-family:'Nunito',sans-serif;font-weight:800;color:var(--text-main);outline:none;}
    .mark-card-body textarea:focus{border-color:#9bdcff;box-shadow:none;}
    .mark-error{min-height:18px;color:#991b1b;font-size:12px;font-weight:900;}
    .mark-card-actions{display:flex;justify-content:flex-end;gap:10px;padding:0 18px 18px;}
    .detail-row td{padding:0 18px 22px;background:#fff;border:none;}
    .detail-panel{border:2px solid #e6eef7;border-radius:24px;background:#fbfdff;padding:18px;display:grid;gap:16px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.8);}
    .detail-top{
      display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid var(--border-color);
      border-radius:20px;padding:16px 18px;box-shadow:0 4px 12px rgba(15,23,42,.035);
    }
    .detail-title{display:flex;align-items:center;gap:10px;min-width:0;}
    .detail-title i{width:42px;height:42px;border-radius:14px;background:#eef8ff;color:#12628f;display:inline-flex;align-items:center;justify-content:center;flex:0 0 42px;}
    .detail-title strong{display:block;font-size:17px;font-weight:1000;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .detail-title span{display:block;color:var(--text-muted);font-size:13px;font-weight:900;margin-top:3px;}
    .detail-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end;}
    .bill-line{
      background:#fff;border:2px solid var(--border-color);border-radius:22px;padding:18px;display:grid;
      grid-template-columns:minmax(190px,.35fr) minmax(0,1fr);
      gap:18px;align-items:start;box-shadow:0 6px 14px rgba(15,23,42,.04);transition:.2s;
    }
    .bill-line:hover{border-color:#dbeafe;box-shadow:0 12px 20px -8px rgba(56,182,255,.24);}
    .bill-head{display:grid;gap:8px;align-content:start;}
    .bill-tag{display:inline-flex;align-items:center;gap:8px;width:max-content;max-width:100%;padding:8px 12px;border-radius:999px;background:#eef8ff;color:#12628f;font-size:13px;font-weight:1000;text-decoration:none;}
    .bill-amount{font-size:24px;font-weight:1000;line-height:1.05;color:var(--text-main);}
    .bill-due{display:flex;align-items:flex-start;gap:8px;color:var(--text-muted);font-size:13px;font-weight:900;line-height:1.3;}
    .bill-due i{color:var(--primary);margin-top:1px;}
    .bill-main{display:grid;gap:8px;align-content:start;}
    .bill-label{display:flex;align-items:center;gap:8px;color:var(--text-muted);font-size:12px;font-weight:1000;text-transform:uppercase;}
    .bill-label i{color:var(--primary);}
    .bill-items{white-space:pre-wrap;word-break:break-word;font-size:15px;font-weight:900;line-height:1.42;color:var(--text-main);background:#f8fbff;border:1px solid var(--border-color);border-radius:16px;padding:14px;}
    .bill-side{grid-column:1 / -1;display:flex;gap:8px;align-items:center;justify-content:flex-start;flex-wrap:wrap;text-align:left;padding-top:2px;}
    .bill-mini{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:var(--bg-panel);border:2px solid var(--border-color);font-size:13px;font-weight:1000;}
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
      .summary-grid{grid-template-columns:repeat(2,minmax(220px,1fr));}
      .leader-strip{grid-template-columns:1fr 1fr;}
      .month-grid{grid-template-columns:repeat(3,minmax(112px,1fr));}
      .bill-line{grid-template-columns:1fr;}
      .bill-side{text-align:left;overflow:visible;}
      .table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}
    }
    @media(max-width:680px){
      .rep-header{align-items:flex-start;flex-direction:column;height:auto;min-height:62px;padding:14px 16px;border-radius:18px;gap:10px;}
      .rep-container{padding:0 8px;}
      .toolbar{border-radius:22px;align-items:stretch;flex-direction:column;padding:12px;}
      .search-box{min-width:0;width:100%;}
      .month-control{width:100%;flex:auto;}
      .filter-select{width:100%;}
      .summary-grid,.leader-strip,.bill-line{grid-template-columns:1fr;}
      .month-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
      .month-board-head{align-items:stretch;flex-direction:column;}
      #btnAllMonths,#btnToggleMonths{width:100%;justify-content:center;}
      .month-board{padding:12px;border-radius:18px;}
      .metric{min-height:122px;}
      .btn-primary{width:100%;}
      .btn-secondary{width:100%;}
      .meta-chip{width:100%;box-sizing:border-box;}
      .mark-card-sub{max-width:230px;}
    }
    @media(max-width:620px){
      .summary-grid{grid-template-columns:1fr;}
      .metric{padding:18px 64px 16px 18px;min-height:118px;}
      .metric strong{font-size:25px;}
      .metric-label{font-size:12px;}
      .metric-label i{width:42px;height:42px;right:18px;top:18px;font-size:16px;}
      .table-scroll{overflow:visible;}
      table,thead,tbody,tr,td{display:block;width:100%;min-width:0;box-sizing:border-box;}
      table{border-spacing:0;}
      thead{display:none;}
      tbody tr.rep-row{padding:15px;margin-bottom:12px;border:2px solid var(--border-color);border-radius:18px;box-shadow:var(--shadow-soft);}
      tbody tr.rep-row:hover{transform:none;}
      tbody td,
      tbody td:first-child,
      tbody td:last-child{border:0;padding:7px 0;border-radius:0;text-align:left;}
      tbody td:last-child{text-align:left;}
      .row-actions{justify-content:space-between;}
      .row-sub{display:block;margin:6px 0 0;}
      .pill{white-space:normal;}
      .detail-row td{padding:0 0 14px;}
      .detail-panel{padding:10px;border-radius:14px;}
      .bill-side{display:flex;align-items:stretch;}
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
      <select id="markFilter" class="filter-select" title="Filtrar marcacoes">
        <option value="all">Todos</option>
        <option value="marked">Marcados</option>
        <option value="unmarked">Nao marcados</option>
      </select>
      <select id="prefixFilter" class="filter-select" title="Filtrar prefixo">
        <option value="all">Todos prefixos</option>
        <option value="p">(P)</option>
        <option value="f">(F)</option>
        <option value="none">Sem prefixo</option>
      </select>
      <button id="btnLoadReports" class="btn-primary" type="button"><i class="fa-solid fa-rotate"></i> Atualizar</button>
      <button id="btnSyncReports" class="btn-secondary" type="button"><i class="fa-solid fa-cloud-arrow-down"></i> Sincronizar Vindi</button>
    </div>

    <div class="month-board" id="monthBoard">
      <div class="month-board-head">
        <div class="month-board-title">
          <i class="fa-regular fa-calendar"></i>
          <span id="monthFilterTitle">Todos os meses</span>
          <small id="monthFilterCount"></small>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button type="button" class="btn-secondary" id="btnAllMonths"><i class="fa-solid fa-filter-circle-xmark"></i> Limpar filtros</button>
          <button type="button" class="btn-secondary" id="btnToggleMonths"><i class="fa-regular fa-calendar-days"></i> Filtrar mes</button>
        </div>
      </div>
      <div class="month-years" id="monthYears">
        <div class="empty">Carregando meses...</div>
      </div>
    </div>

    <div class="summary-grid">
      <div class="metric"><span class="metric-label"><i class="fa-solid fa-users"></i> Devedores</span><strong id="mDebtors">0</strong><small>clientes em atraso</small><span class="metric-track"><span class="metric-fill" id="mDebtorsFill"></span></span></div>
      <div class="metric danger"><span class="metric-label"><i class="fa-solid fa-coins"></i> Total</span><strong id="mAmount">R$ 0,00</strong><small>valor vencido em aberto</small><span class="metric-track"><span class="metric-fill" id="mAmountFill"></span></span></div>
      <div class="metric warn"><span class="metric-label"><i class="fa-solid fa-file-invoice"></i> Faturas</span><strong id="mBills">0</strong><small>parcelas/faturas vencidas</small><span class="metric-track"><span class="metric-fill" id="mBillsFill"></span></span></div>
      <div class="metric ok"><span class="metric-label"><i class="fa-brands fa-whatsapp"></i> Recobrancas</span><strong id="mReminders">0</strong><small>mensagens enviadas</small><span class="metric-track"><span class="metric-fill" id="mRemindersFill"></span></span></div>
    </div>

    <div class="leader-strip" id="leaderStrip">
      <div>
        <div class="leader-main">
          <span class="avatar-initial">?</span>
          <div>
            <span class="leader-name">Maior devedor</span>
            <span class="leader-sub">-</span>
          </div>
        </div>
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

    <div class="report-list-panel">
      <div class="report-list-title">
        <strong>Clientes em atraso</strong>
        <span id="reportListCount">0 registro(s)</span>
      </div>
      <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Cliente / Documento</th>
            <th>Dívida</th>
            <th style="text-align:right;">Detalhes</th>
          </tr>
        </thead>
        <tbody id="repBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="reportContextMenu" class="report-context-menu">
    <button type="button" data-context-action="mark"><i class="fa-solid fa-flag"></i> <span id="contextMarkLabel">Marcar</span></button>
    <button type="button" class="danger" data-context-action="unmark"><i class="fa-solid fa-flag"></i> Desmarcar</button>
    <button type="button" data-context-action="details"><i class="fa-solid fa-chevron-right"></i> Abrir detalhes</button>
  </div>

  <div id="markModal" class="mark-modal" aria-hidden="true">
    <div class="mark-card" role="dialog" aria-modal="true" aria-labelledby="markModalTitle">
      <div class="mark-card-head">
        <i class="fa-solid fa-flag"></i>
        <div style="min-width:0;">
          <span id="markModalTitle" class="mark-card-title">Marcar cliente</span>
          <span id="markModalClient" class="mark-card-sub">Cliente</span>
        </div>
      </div>
      <div class="mark-card-body">
        <label for="markReason">Motivo</label>
        <textarea id="markReason"></textarea>
        <div id="markError" class="mark-error"></div>
      </div>
      <div class="mark-card-actions">
        <button type="button" class="btn-secondary" id="markCancel" style="height:40px;padding:0 14px;">Cancelar</button>
        <button type="button" class="btn-primary" id="markSave" style="height:40px;padding:0 16px;"><i class="fa-solid fa-check"></i> Salvar</button>
      </div>
    </div>
  </div>

  <script>
    const repState = { rows: [], expanded: new Set(), contextRowKey: '' };
    const $rep = (id) => document.getElementById(id);
    let reportsLoading = false;
    let reportsRequestId = 0;
    const reportUrlParams = new URLSearchParams(location.search);
    let selectedMonth = reportUrlParams.get('month') || '';
    let markFilter = reportUrlParams.get('mark_filter') || 'all';
    let prefixFilter = reportUrlParams.get('prefix_filter') || 'all';
    let monthsExpanded = false;
    $rep('repQ').value = reportUrlParams.get('q') || '';
    $rep('markFilter').value = ['all','marked','unmarked'].includes(markFilter) ? markFilter : 'all';
    $rep('prefixFilter').value = ['all','p','f','none'].includes(prefixFilter) ? prefixFilter : 'all';
    markFilter = $rep('markFilter').value;
    prefixFilter = $rep('prefixFilter').value;
    const debtHead = document.querySelectorAll('.report-list-panel thead th')[2];
    if (debtHead) debtHead.textContent = 'Divida';

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

    function isoDate(date){
      return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function monthStart(value){
      const [year, month] = String(value || '').split('-').map(Number);
      return new Date(year || new Date().getFullYear(), (month || 1) - 1, 1);
    }

    function monthEnd(value){
      const start = monthStart(value);
      return new Date(start.getFullYear(), start.getMonth() + 1, 0);
    }

    function buildSyncMonths(){
      if (/^\d{4}-\d{2}$/.test(selectedMonth)) return [selectedMonth];
      const from = '2024-01';
      const now = new Date();
      const end = new Date(now.getFullYear(), now.getMonth(), 1);
      const months = [];
      let cursor = monthStart(from);
      while (cursor <= end) {
        months.push(`${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}`);
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
      }
      return months;
    }

    function initialOf(name){
      const clean = String(name || 'Cliente').trim();
      const first = clean.replace(/^[^A-Za-zÀ-ÿ0-9]+/, '').charAt(0);
      return (first || 'C').toUpperCase();
    }

    function setMetric(valueId, fillId, text, percent){
      const value = $rep(valueId);
      const fill = $rep(fillId);
      if (value) value.textContent = text;
      if (fill) fill.style.width = `${Math.max(0, Math.min(100, Number(percent || 0)))}%`;
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

    function customerLocalHref(row){
      const customerId = String(row?.customer_id || '').replace(/\D+/g, '');
      if (!customerId) return '';
      const url = new URL('/painel/index.php', location.origin);
      url.searchParams.set('pagina', 'customer');
      url.searchParams.set('customer_id', customerId);
      if (row?.customer_name) url.searchParams.set('name', String(row.customer_name));
      return url.toString();
    }

    function customerProfileHref(row){
      const customerId = String(row?.customer_id || '').replace(/\D+/g, '');
      if (!customerId) return '';
      return `https://app.vindi.com.br/admin/customers/${encodeURIComponent(customerId)}#tab-bills`;
    }

    function currentReportsHref(){
      const url = new URL('/painel/index.php', location.origin);
      url.searchParams.set('pagina', 'reports');
      const q = $rep('repQ')?.value?.trim() || '';
      if (q) url.searchParams.set('q', q);
      if (selectedMonth) url.searchParams.set('month', selectedMonth);
      if (markFilter !== 'all') url.searchParams.set('mark_filter', markFilter);
      if (prefixFilter !== 'all') url.searchParams.set('prefix_filter', prefixFilter);
      return url.toString();
    }

    function reportDetailsHref(row){
      const customerId = String(row?.customer_id || '').replace(/\D+/g, '');
      const url = new URL('/painel/index.php', location.origin);
      url.searchParams.set('pagina', 'report_details');
      if (customerId) url.searchParams.set('customer_id', customerId);
      if (row?.customer_name) url.searchParams.set('name', String(row.customer_name));
      if (selectedMonth) url.searchParams.set('month', selectedMonth);
      url.searchParams.set('back', currentReportsHref());
      return url.toString();
    }

    function findRowByKey(key){
      return repState.rows.find(row => String(row.customer_key || '') === String(key));
    }

    function syncFilterControls(){
      if ($rep('markFilter')) $rep('markFilter').value = markFilter;
      if ($rep('prefixFilter')) $rep('prefixFilter').value = prefixFilter;
      if ($rep('monthFilter')) $rep('monthFilter').value = selectedMonth;
    }

    function clearAllFilters(){
      selectedMonth = '';
      markFilter = 'all';
      prefixFilter = 'all';
      monthsExpanded = false;
      const q = $rep('repQ');
      if (q) q.value = '';
      syncFilterControls();
      const board = $rep('monthBoard');
      if (board) board.classList.remove('expanded');
      repState.expanded.clear();
      closeContextMenu();
    }

    function keepMarkedRowVisible(action){
      if (action === 'mark' && markFilter === 'unmarked') markFilter = 'all';
      if (action === 'unmark' && markFilter === 'marked') markFilter = 'all';
      syncFilterControls();
    }

    function closeContextMenu(){
      const menu = $rep('reportContextMenu');
      if (menu) menu.classList.remove('open');
      repState.contextRowKey = '';
    }

    function openContextMenu(event, row){
      const menu = $rep('reportContextMenu');
      if (!menu || !row) return;
      repState.contextRowKey = String(row.customer_key || '');
      const marked = !!row?.mark?.marked;
      const markLabel = $rep('contextMarkLabel');
      if (markLabel) markLabel.textContent = marked ? 'Editar motivo' : 'Marcar';
      const unmark = menu.querySelector('[data-context-action="unmark"]');
      if (unmark) unmark.style.display = marked ? 'flex' : 'none';
      menu.classList.add('open');
      const rect = menu.getBoundingClientRect();
      const left = Math.min(event.clientX, window.innerWidth - rect.width - 10);
      const top = Math.min(event.clientY, window.innerHeight - rect.height - 10);
      menu.style.left = `${Math.max(10, left)}px`;
      menu.style.top = `${Math.max(10, top)}px`;
    }

    function openMarkModal(row){
      return new Promise((resolve) => {
        const modal = $rep('markModal');
        const input = $rep('markReason');
        const error = $rep('markError');
        const client = $rep('markModalClient');
        const title = $rep('markModalTitle');
        const save = $rep('markSave');
        const cancel = $rep('markCancel');
        if (!modal || !input || !save || !cancel) {
          resolve(null);
          return;
        }
        const cleanup = (value) => {
          modal.classList.remove('open');
          modal.setAttribute('aria-hidden', 'true');
          save.onclick = null;
          cancel.onclick = null;
          modal.onclick = null;
          document.removeEventListener('keydown', onKey);
          resolve(value);
        };
        const onKey = (event) => {
          if (event.key === 'Escape') cleanup(null);
          if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') save.click();
        };
        if (title) title.textContent = row?.mark?.marked ? 'Editar marcacao' : 'Marcar cliente';
        if (client) client.textContent = row?.customer_name || 'Cliente';
        if (error) error.textContent = '';
        input.value = row?.mark?.reason || '';
        save.onclick = () => {
          const reason = input.value.trim();
          if (!reason) {
            if (error) error.textContent = 'Informe o motivo da marcacao.';
            input.focus();
            return;
          }
          cleanup(reason);
        };
        cancel.onclick = () => cleanup(null);
        modal.onclick = (event) => {
          if (event.target === modal) cleanup(null);
        };
        document.addEventListener('keydown', onKey);
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => input.focus(), 40);
      });
    }

    async function saveMark(row, action, reason=''){
      const key = String(row?.customer_key || '');
      if (!key) return;
      reason = String(reason || '').trim();
      if (action === 'mark') {
        if (!reason) {
          setStatus('Informe o motivo da marcacao', true);
          return;
        }
      }
      const resp = await fetch('/painel/api/report_mark.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          action,
          mark_key: key,
          customer_id: row.customer_id || 0,
          customer_name: row.customer_name || '',
          reason
        })
      });
      const text = await resp.text();
      let data = null;
      try { data = JSON.parse(text); } catch(e) {}
      if (!resp.ok || !data || data.ok === false) {
        throw new Error(data?.error || text.slice(0, 160) || 'Falha ao salvar marcacao');
      }
      keepMarkedRowVisible(action);
      setStatus(action === 'mark' ? 'Cliente marcado' : 'Marcacao removida');
      await loadReports(false, true);
    }

    async function beginMarkFlow(row){
      const reason = await openMarkModal(row);
      if (reason === null) return;
      await saveMark(row, 'mark', reason);
    }

    function renderLeader(row){
      const box = $rep('leaderStrip');
      if (!row) {
        box.innerHTML = `
          <div class="leader-main">
            <span class="avatar-initial">?</span>
            <div>
              <span class="leader-name">Maior devedor</span>
              <span class="leader-sub">-</span>
            </div>
          </div>
          <span class="pill"><i class="fa-solid fa-coins"></i> R$ 0,00</span>
          <span class="pill"><i class="fa-solid fa-file-invoice"></i> 0 parcelas</span>
          <span class="pill"><i class="fa-solid fa-paper-plane"></i> 0 recobrancas</span>
        `;
        return;
      }
      box.innerHTML = `
        <div class="leader-main">
          <span class="avatar-initial hot">${esc(initialOf(row.customer_name))}</span>
          <div>
            <span class="leader-name">${esc(row.customer_name || 'Cliente')}</span>
            <span class="leader-sub">Maior devedor | ${row.phone ? 'Tel ' + esc(row.phone) : 'Telefone nao salvo'}</span>
          </div>
        </div>
        <span class="pill"><i class="fa-solid fa-coins"></i> ${brMoney(row.total_amount)}</span>
        <span class="pill"><i class="fa-solid fa-file-invoice"></i> ${brNumber(row.open_bills)} parcela(s)</span>
        <span class="pill"><i class="fa-solid fa-paper-plane"></i> ${brNumber(row.reminders_sent)} recobranca(s)</span>
      `;
    }

    function renderBills(row){
      const bills = Array.isArray(row.bills) ? row.bills : [];
      if (!bills.length) return '<div class="empty">Sem parcelas/faturas no detalhe.</div>';
      const profileHref = customerProfileHref(row);
      const localHref = customerLocalHref(row);
      const firstBillUrl = String(bills.find(bill => bill && bill.bill_url)?.bill_url || '');
      const top = `
        <div class="detail-top">
          <div class="detail-title">
            <i class="fa-solid fa-user"></i>
            <div>
              <strong>${esc(row.customer_name || 'Cliente')}</strong>
              <span>${brNumber(row.open_bills)} fatura(s) | ${brMoney(row.total_amount)} | ${brNumber(row.reminders_sent)} recobranca(s)</span>
            </div>
          </div>
          <div class="detail-actions">
            ${profileHref ? `<a class="btn-mini primary" href="${esc(profileHref)}" target="_blank" rel="noopener" onclick="event.stopPropagation();"><i class="fa-solid fa-user"></i> Perfil</a>` : ''}
            ${localHref ? `<a class="btn-mini" href="${esc(localHref)}" onclick="event.stopPropagation(); window.LisOnPageLoader?.show();"><i class="fa-solid fa-file-invoice"></i> Faturas</a>` : ''}
            ${firstBillUrl ? `<a class="btn-mini" href="${esc(firstBillUrl)}" target="_blank" rel="noopener" onclick="event.stopPropagation();"><i class="fa-solid fa-arrow-up-right-from-square"></i> Vindi</a>` : ''}
          </div>
        </div>
      `;
      const lines = bills.map(bill => {
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
      return top + lines;
    }

    function renderRows(){
      const body = $rep('repBody');
      const rows = repState.rows;
      if (!rows.length) {
        body.innerHTML = `<tr style="cursor:default; pointer-events:none;"><td colspan="3" style="text-align:center; padding:40px; color:var(--text-muted); background:transparent; box-shadow:none; border:none; font-weight:600;">Nenhum cliente devendo encontrado.</td></tr>`;
        return;
      }
      body.innerHTML = rows.map((row, idx) => {
        const key = String(row.customer_key || idx);
        const detailsHref = reportDetailsHref(row);
        const marked = !!row?.mark?.marked;
        const reason = String(row?.mark?.reason || '').trim();
        const prefix = String(row.prefix || '').toUpperCase();
        const doc = row.customer_id ? `DOC: ${row.customer_id}` : (row.phone ? `TEL: ${row.phone}` : 'DOC: -');
        const statusHtml = `
          <span class="debt-snapshot ${marked ? 'marked' : ''}">
            <span class="snapshot-value"><i class="fa-solid fa-coins"></i> ${brMoney(row.total_amount)}</span>
            <span class="snapshot-meta">
              <span><i class="fa-solid fa-file-invoice"></i> ${brNumber(row.open_bills)} fatura(s)</span>
              <span><i class="fa-regular fa-calendar"></i> ${brNumber(row.max_days_overdue)}d</span>
            </span>
          </span>
        `;
        return `
          <tr class="rep-row ${marked ? 'marked' : ''}" data-key="${esc(key)}" data-href="${esc(detailsHref)}" title="Clique para abrir detalhes">
            <td>
              <span class="customer-name">${esc(row.customer_name || 'Cliente')}</span>
              <span class="bill-id">${esc(doc)}</span>
              ${prefix ? `<span class="row-sub">(${esc(prefix)})</span>` : ''}
              ${marked ? `<span class="mark-flag" title="${esc(reason || 'Marcado')}"><i class="fa-solid fa-flag"></i> Marcado${reason ? `<span class="mark-tooltip">${esc(reason)}</span>` : ''}</span>` : ''}
            </td>
            <td>
              ${statusHtml}
            </td>
            <td style="text-align:right;">
              <div style="display:flex; align-items:center; justify-content:flex-end; gap:15px;">
                <span class="detail-date">${esc(brDate(row.oldest_due_at))} | ${brNumber(row.reminders_sent)} rec.</span>
                <a href="${esc(detailsHref)}" class="btn-icon" onclick="event.stopPropagation(); window.LisOnPageLoader?.show();">
                  <i class="fa-solid fa-chevron-right"></i>
                </a>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function applyData(data){
      const s = data.summary || {};
      const meta = data.meta || {};
      const vindi = meta.vindi || {};
      renderMonthOptions(meta.available_months || [], meta.selected_month || selectedMonth);
      const debtors = Number(s.debtors || 0);
      const openBills = Number(s.open_bills || 0);
      const reminders = Number(s.reminders_sent || 0);
      const countMax = Math.max(debtors, openBills, reminders, 1);
      setMetric('mDebtors', 'mDebtorsFill', brNumber(debtors), (debtors / countMax) * 100);
      setMetric('mAmount', 'mAmountFill', brMoney(s.total_amount), Number(s.total_amount || 0) > 0 ? 100 : 0);
      setMetric('mBills', 'mBillsFill', brNumber(openBills), (openBills / countMax) * 100);
      setMetric('mReminders', 'mRemindersFill', brNumber(reminders), (reminders / countMax) * 100);
      const chips = [
        `<span class="meta-chip"><i class="fa-solid fa-database"></i> Local ${brNumber(meta.local_rows || 0)}</span>`,
        `<span class="meta-chip"><i class="fa-solid fa-file-invoice"></i> ${brNumber(meta.merged_bills || 0)} faturas</span>`
      ];
      if (meta.mark_filter && meta.mark_filter !== 'all') {
        chips.push(`<span class="meta-chip warn"><i class="fa-solid fa-flag"></i> ${meta.mark_filter === 'marked' ? 'Marcados' : 'Nao marcados'}</span>`);
      }
      if (meta.prefix_filter && meta.prefix_filter !== 'all') {
        const label = meta.prefix_filter === 'none' ? 'Sem prefixo' : `(${String(meta.prefix_filter).toUpperCase()})`;
        chips.push(`<span class="meta-chip warn"><i class="fa-solid fa-filter"></i> ${esc(label)}</span>`);
      }
      if (Number(meta.marked_customers || 0) > 0) {
        chips.push(`<span class="meta-chip danger"><i class="fa-solid fa-flag"></i> ${brNumber(meta.marked_customers)} marcados</span>`);
      }
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
      const listCount = $rep('reportListCount');
      if (listCount) listCount.textContent = `${brNumber(repState.rows.length)} registro(s)`;
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
      rows.sort((a, b) => String(a.value || '').localeCompare(String(b.value || '')));
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
      const selectedRow = rows.find(row => String(row.value || '') === selectedMonth);
      const title = $rep('monthFilterTitle');
      const count = $rep('monthFilterCount');
      if (title) title.textContent = selectedRow ? (selectedRow.label || selectedRow.value) : 'Todos os meses';
      if (count) {
        if (selectedRow) count.textContent = `${brNumber(selectedRow.total || 0)} fatura(s)`;
        else count.textContent = rows.length ? `${brNumber(rows.length)} mes(es)` : '';
      }
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
      board.innerHTML = Array.from(years.entries()).sort((a, b) => b[0].localeCompare(a[0])).map(([year, yearRows]) => `
        <div class="month-year">
          <div class="month-year-label">${esc(year)}</div>
          <div class="month-grid">
            ${yearRows.sort((a, b) => String(a.value || '').localeCompare(String(b.value || ''))).map(row => {
              const value = String(row.value || '');
              const shortLabel = String(row.label || value).replace(/\s+\d{4}$/, '').toUpperCase();
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
          ? '<i class="fa-solid fa-chevron-up"></i> Fechar filtro'
          : '<i class="fa-regular fa-calendar-days"></i> Filtrar mes';
        toggle.style.display = rows.length ? 'inline-flex' : 'none';
      }
    }

    function syncUrlState(){
      const url = new URL(location.href);
      url.searchParams.set('pagina', 'reports');
      const q = $rep('repQ')?.value?.trim() || '';
      if (q) url.searchParams.set('q', q);
      else url.searchParams.delete('q');
      if (selectedMonth) url.searchParams.set('month', selectedMonth);
      else url.searchParams.delete('month');
      if (markFilter !== 'all') url.searchParams.set('mark_filter', markFilter);
      else url.searchParams.delete('mark_filter');
      if (prefixFilter !== 'all') url.searchParams.set('prefix_filter', prefixFilter);
      else url.searchParams.delete('prefix_filter');
      history.replaceState(null, '', url.toString());
    }

    async function fetchReportsData(params, timeoutMs=35000){
      const url = new URL('/painel/api/reports.php', location.origin);
      Object.entries(params || {}).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value));
      });
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), timeoutMs);
      try {
        const resp = await fetch(url.toString(), {credentials:'same-origin', cache:'no-store', signal:controller.signal});
        const text = await resp.text();
        let data = null;
        try { data = JSON.parse(text); } catch(e) {}
        if (resp.status === 401) {
          location.href = '/painel/';
          return null;
        }
        if (!resp.ok || !data || data.ok === false) {
          const cleanText = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
          throw new Error(data?.error || cleanText.slice(0, 180) || 'Falha ao carregar relatorios');
        }
        return data;
      } catch (e) {
        if (e.name === 'AbortError') {
          throw new Error('A Vindi demorou demais nesse lote. Tente sincronizar um mes por vez.');
        }
        throw e;
      } finally {
        clearTimeout(timer);
      }
    }

    async function syncReportsChunked(){
      if (reportsLoading) return;
      const requestId = ++reportsRequestId;
      reportsLoading = true;
      const btnLoad = $rep('btnLoadReports');
      const btnSync = $rep('btnSyncReports');
      const originalSync = btnSync ? btnSync.innerHTML : '';
      if (btnLoad) btnLoad.disabled = true;
      if (btnSync) btnSync.disabled = true;
      const q = $rep('repQ').value.trim();
      const months = buildSyncMonths();
      let lastData = null;
      const totals = {bills_read: 0, saved_local: 0, pages_read: 0, status_checked: 0, settled_local: 0};
      $rep('repBody').innerHTML = `<tr><td colspan="3" style="text-align:center;padding:38px;"><div class="spinner"></div><div class="muted" style="margin-top:12px;">Sincronizando Vindi em lotes...</div></td></tr>`;

      try {
        for (let i = 0; i < months.length; i++) {
          const monthKey = months[i];
          const start = monthStart(monthKey);
          const end = monthEnd(monthKey);
          const today = new Date();
          const syncTo = end > today ? today : end;
          setStatus(`Vindi ${i + 1}/${months.length}: ${monthKey.split('-').reverse().join('/')}`);
          if (btnSync) btnSync.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${i + 1}/${months.length}`;
          lastData = await fetchReportsData({
            q,
            month: selectedMonth,
            local_limit: 20000,
            sync: 1,
            mark_filter: markFilter,
            prefix_filter: prefixFilter,
            sync_from: isoDate(start),
            sync_to: isoDate(syncTo),
            max_pages: 6,
            status_limit: 0
          }, 30000);
          if (requestId !== reportsRequestId) return;
          const vm = lastData?.meta?.vindi || {};
          totals.bills_read += Number(vm.bills_read || 0);
          totals.saved_local += Number(vm.saved_local || lastData?.meta?.saved_local || 0);
          totals.pages_read += Number(vm.pages_read || 0);
        }

        setStatus('Atualizando status pagos...');
        if (btnSync) btnSync.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Status';
        lastData = await fetchReportsData({
          q,
          month: selectedMonth,
          local_limit: 20000,
          sync: 1,
          mark_filter: markFilter,
          prefix_filter: prefixFilter,
          sync_from: isoDate(new Date()),
          sync_to: isoDate(new Date()),
          max_pages: 1,
          status_limit: 80
        }, 30000);
        if (requestId !== reportsRequestId) return;
        const finalVm = lastData?.meta?.vindi || {};
        totals.bills_read += Number(finalVm.bills_read || 0);
        totals.saved_local += Number(finalVm.saved_local || lastData?.meta?.saved_local || 0);
        totals.pages_read += Number(finalVm.pages_read || 0);
        totals.status_checked += Number(finalVm.status_checked || 0);
        totals.settled_local += Number(finalVm.settled_local || 0);

        if (!lastData) return;
        if (lastData.meta && lastData.meta.vindi) {
          lastData.meta.vindi.bills_read = totals.bills_read;
          lastData.meta.vindi.saved_local = totals.saved_local;
          lastData.meta.vindi.pages_read = totals.pages_read;
          lastData.meta.vindi.status_checked = totals.status_checked;
          lastData.meta.vindi.settled_local = totals.settled_local;
          lastData.meta.saved_local = totals.saved_local;
        }
        applyData(lastData);
        syncUrlState();
        setStatus('Sincronizado em ' + new Date().toLocaleString('pt-BR'));
      } catch (e) {
        if (requestId !== reportsRequestId) return;
        setStatus(e.message || 'Erro ao sincronizar', true);
        await loadReports(false, true);
      } finally {
        if (requestId === reportsRequestId) {
          reportsLoading = false;
          if (btnLoad) btnLoad.disabled = false;
          if (btnSync) {
            btnSync.disabled = false;
            btnSync.innerHTML = originalSync;
          }
        }
      }
    }

    async function loadReports(sync=false, force=false){
      if (sync) {
        syncReportsChunked();
        return;
      }
      if (reportsLoading && !force) return;
      const requestId = ++reportsRequestId;
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
      const shouldShowLoader = sync || repState.rows.length === 0;
      if (shouldShowLoader) {
        $rep('repBody').innerHTML = `
          <tr><td colspan="3" style="text-align:center;padding:38px;"><div class="spinner"></div><div class="muted" style="margin-top:12px;">${sync ? 'Puxando Vindi e salvando no banco local...' : 'Carregando relatorios locais...'}</div></td></tr>
        `;
      }
      try {
        const q = $rep('repQ').value.trim();
        const data = await fetchReportsData({q, month: selectedMonth, local_limit: 20000, mark_filter: markFilter, prefix_filter: prefixFilter});
        if (!data) return;
        if (requestId !== reportsRequestId) return;
        applyData(data);
        syncUrlState();
        setStatus((sync ? 'Sincronizado em ' : 'Atualizado em ') + new Date().toLocaleString('pt-BR'));
      } catch (e) {
        if (requestId !== reportsRequestId) return;
        setStatus(e.message || 'Erro ao carregar', true);
        if (repState.rows.length === 0) {
          $rep('repBody').innerHTML = `<tr><td colspan="3"><div class="empty">${esc(e.message || 'Erro ao carregar relatorios.')}</div></td></tr>`;
        }
      } finally {
        if (requestId === reportsRequestId) {
          reportsLoading = false;
          if (btnLoad) btnLoad.disabled = false;
          if (btnSync) {
            btnSync.disabled = false;
            btnSync.innerHTML = originalSync;
          }
        }
      }
    }

    $rep('repBody').addEventListener('click', (e) => {
      const markBtn = e.target.closest('[data-mark-action]');
      if (markBtn) {
        e.preventDefault();
        e.stopPropagation();
        const row = findRowByKey(markBtn.getAttribute('data-key'));
        if (row) {
          const action = markBtn.getAttribute('data-mark-action');
          const run = action === 'mark' ? beginMarkFlow(row) : saveMark(row, 'unmark');
          run.catch(err => setStatus(err.message, true));
        }
        return;
      }
      closeContextMenu();
      if (e.target.closest('a,button,input,select,textarea')) return;
      const row = e.target.closest('tr.rep-row[data-href]');
      if (!row) return;
      const href = row.getAttribute('data-href');
      if (!href) return;
      window.LisOnPageLoader?.show();
      window.location.href = href;
    });

    $rep('repBody').addEventListener('contextmenu', (event) => {
      const tr = event.target.closest('tr.rep-row[data-key]');
      if (!tr) return;
      const row = findRowByKey(tr.getAttribute('data-key'));
      if (!row) return;
      event.preventDefault();
      openContextMenu(event, row);
    });

    $rep('reportContextMenu').addEventListener('click', (event) => {
      const btn = event.target.closest('[data-context-action]');
      if (!btn) return;
      const action = btn.getAttribute('data-context-action');
      const row = findRowByKey(repState.contextRowKey);
      closeContextMenu();
      if (!row) return;
      if (action === 'details') {
        window.LisOnPageLoader?.show();
        window.location.href = reportDetailsHref(row);
        return;
      }
      const run = action === 'unmark' ? saveMark(row, 'unmark') : beginMarkFlow(row);
      run.catch(err => setStatus(err.message, true));
    });

    document.addEventListener('click', (event) => {
      if (!event.target.closest('#reportContextMenu')) closeContextMenu();
    });
    window.addEventListener('scroll', closeContextMenu, true);

    $rep('btnLoadReports').onclick = () => loadReports(false);
    $rep('btnSyncReports').onclick = () => loadReports(true);
    $rep('markFilter').addEventListener('change', () => {
      markFilter = $rep('markFilter').value;
      repState.expanded.clear();
      closeContextMenu();
      loadReports(false, true);
    });
    $rep('prefixFilter').addEventListener('change', () => {
      prefixFilter = $rep('prefixFilter').value;
      repState.expanded.clear();
      closeContextMenu();
      loadReports(false, true);
    });
    $rep('monthFilter').addEventListener('change', () => {
      selectedMonth = $rep('monthFilter').value;
      repState.expanded.clear();
      closeContextMenu();
      loadReports(false, true);
    });
    $rep('monthYears').addEventListener('click', (event) => {
      const btn = event.target.closest('button[data-month]');
      if (!btn) return;
      selectedMonth = btn.getAttribute('data-month') || '';
      monthsExpanded = false;
      repState.expanded.clear();
      closeContextMenu();
      loadReports(false, true);
    });
    $rep('btnAllMonths').addEventListener('click', () => {
      clearAllFilters();
      loadReports(false, true);
    });
    $rep('btnToggleMonths').addEventListener('click', () => {
      monthsExpanded = !monthsExpanded;
      $rep('monthBoard').classList.toggle('expanded', monthsExpanded);
      $rep('btnToggleMonths').innerHTML = monthsExpanded
        ? '<i class="fa-solid fa-chevron-up"></i> Fechar filtro'
        : '<i class="fa-regular fa-calendar-days"></i> Filtrar mes';
    });
    $rep('repQ').addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        repState.expanded.clear();
        closeContextMenu();
        loadReports(false, true);
      }
    });

    loadReports(false);
    setInterval(() => loadReports(false), 60000);
  </script>
</div>
