<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['auth'])) { http_response_code(403); exit("Sem login"); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function daysOverdueFromDueAt(string $dueAt): int {
    $dueAt = trim($dueAt);
    if ($dueAt === '') return 0;

    $dueDate = substr($dueAt, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) return 0;

    $dueTs   = strtotime($dueDate . ' 00:00:00');
    $todayTs = strtotime(date('Y-m-d') . ' 00:00:00');

    if ($dueTs === false || $todayTs === false) return 0;

    $diff = $todayTs - $dueTs;
    if ($diff <= 0) return 0;

    return (int)floor($diff / 86400);
}

function shouldSendOverdue(PDO $pdo, int $billId, int $intervalDays): bool {
    $st = $pdo->prepare("SELECT active, last_overdue_sent_at FROM bill_reminders WHERE bill_id=? LIMIT 1");
    $st->execute([$billId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ((int)$row['active'] === 0) return false;
        if (!empty($row['last_overdue_sent_at'])) {
            $last = strtotime((string)$row['last_overdue_sent_at']);
            if ($last !== false && (time() - $last) < ($intervalDays * 86400)) return false;
        }
    }
    return true;
}

function getReminderInfo(PDO $pdo, int $billId): array {
    $st = $pdo->prepare("SELECT last_overdue_sent_at, overdue_sent_count, active FROM bill_reminders WHERE bill_id=? LIMIT 1");
    $st->execute([$billId]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'last' => $r['last_overdue_sent_at'] ?? null,
        'count' => isset($r['overdue_sent_count']) ? (int)$r['overdue_sent_count'] : 0,
        'active' => isset($r['active']) ? (int)$r['active'] : 1,
    ];
}

function billBadgeHtml(string $status): string {
  $status = strtolower(trim($status));
  $map = [
    'paid'      => ['Pago',      'b-success', 'circle-check'],
    'pending'   => ['Pendente',  'b-pending', 'clock'],
    'scheduled' => ['Agendado',  'b-process', 'spinner fa-spin'],
    'overdue'   => ['Vencida',   'b-error',   'circle-xmark'],
  ];
  $info = $map[$status] ?? [strtoupper($status), 'b-pending', 'circle'];
  return '<span class="badge '.$info[1].'"><i class="fa-solid fa-'.$info[2].'"></i> '.$info[0].'</span>';
}

$csrf = $_SESSION['csrf'] ?? '';

$customerId = (string)($_GET['customer_id'] ?? '');
$name = (string)($_GET['name'] ?? '');
$minDaysBill = max(0, (int)($_GET['min_days_bill'] ?? 0));

$REMINDER_INTERVAL_DAYS = (int)cfg($cfg, 'REMINDER_INTERVAL_DAYS', 7);
$openList = "'pending','overdue'";

$st = $pdo->prepare("
  SELECT bill_id, customer_name, status, due_at, amount, bill_url
  FROM vindi_bills
  WHERE status IN ($openList)
    AND due_at < NOW()
    AND customer_id = :cid
  ORDER BY due_at ASC
");
$st->execute([':cid' => $customerId]);
$bills = $st->fetchAll(PDO::FETCH_ASSOC);

// filtro por atraso >= X dias
if ($minDaysBill > 0) {
    $bills = array_values(array_filter($bills, function($b) use ($minDaysBill) {
        $d = isset($b['due_at']) ? daysOverdueFromDueAt((string)$b['due_at']) : 0;
        return $d >= $minDaysBill;
    }));
}

$total = 0.0;
foreach ($bills as $b) $total += (float)($b['amount'] ?? 0);

$backHref = "/painel/index.php?view=customers";
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cliente • Faturas</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

  <style>
    :root {
      --bg-body: #ffffff;
      --bg-panel: #f4f7fa;

      --primary: #3b82f6;
      --primary-hover: #2563eb;

      --text-main: #1e293b;
      --text-muted: #64748b;
      --border-color: #eef2f6;

      --green-bg: #d1fae5; --green-text: #065f46;
      --red-bg: #fee2e2;   --red-text: #991b1b;
      --blue-bg: #dbeafe;  --blue-text: #1e40af;
      --yellow-bg: #fef3c7; --yellow-text: #92400e;

      --radius-pill: 50px;
      --radius-card: 20px;

      --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
      --shadow-hover: 0 10px 15px -3px rgba(59, 130, 246, 0.15);
    }

    body{
      font-family:'Nunito',sans-serif;
      background:var(--bg-body);
      color:var(--text-main);
      margin:0;
      -webkit-font-smoothing:antialiased;
    }

    .app{display:flex; min-height:100vh;}
    .side{
      width:270px;
      background:#fff;
      border-right:2px solid var(--border-color);
      padding:18px 14px;
      position:sticky; top:0; height:100vh;
      box-sizing:border-box;
      z-index:200;
    }
    .side-title{
      font-size:18px;
      font-weight:800;
      display:flex;
      align-items:center;
      gap:10px;
      margin:4px 10px 14px;
    }
    .side-title i{color:var(--primary);}
    .side a.nav{
      display:flex;
      align-items:center;
      gap:10px;
      text-decoration:none;
      color:var(--text-main);
      font-weight:800;
      padding:10px 14px;
      border-radius:var(--radius-pill);
      border:2px solid transparent;
      margin-bottom:8px;
      transition:.2s;
    }
    .side a.nav:hover{
      background:var(--bg-panel);
      border-color:#dbeafe;
      transform:translateY(-1px);
    }
    .side a.nav.active{
      background:#eff6ff;
      border-color:#dbeafe;
      color:var(--primary);
    }
    .side .hr{
      border:0;border-top:2px solid var(--border-color);
      margin:14px 8px;
    }
    .logout-btn{
      width:100%;
      height:42px;
      border-radius:var(--radius-pill);
      border:2px solid var(--border-color);
      background:#fff;
      font-weight:800;
      cursor:pointer;
      transition:.2s;
    }
    .logout-btn:hover{border-color:#dbeafe; box-shadow:var(--shadow-soft); transform:translateY(-1px);}

    .main{flex:1; min-width:0;}

    /* ===== HEADER estilo Detalhes ===== */
    header{
      background:#fff;
      border-bottom:2px solid var(--border-color);
      padding:0 40px;
      height:70px;
      display:flex;
      align-items:center;
      gap:20px;
      position:sticky;
      top:0;
      z-index:100;
      justify-content:space-between;
    }

    .head-left{
      display:flex;
      align-items:center;
      gap:20px;
      min-width:0;
    }

    .btn-back{
      background:var(--bg-panel);
      color:var(--text-main);
      border:none;
      width:40px;
      height:40px;
      border-radius:50%;
      display:flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      text-decoration:none;
      transition:.2s;
      font-size:16px;
      flex:0 0 auto;
    }
    .btn-back:hover{
      background:#e2e8f0;
      transform:translateX(-3px);
    }

    header h1{
      font-size:20px;
      margin:0;
      font-weight:900;
      display:flex;
      align-items:center;
      gap:10px;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    .status-bar{
      font-size:13px;
      color:var(--text-main);
      background:var(--bg-panel);
      padding:8px 20px;
      border-radius:var(--radius-pill);
      font-weight:800;
      border:2px solid var(--border-color);
      display:flex;
      align-items:center;
      gap:8px;
      white-space:nowrap;
    }

    .container{
      max-width:1100px;
      margin:40px auto;
      padding:0 25px;
    }

    .toolbar{
      background:#fff;
      border:2px solid var(--border-color);
      border-radius:var(--radius-pill);
      padding:12px 20px;
      display:flex;
      gap:12px;
      align-items:center;
      box-shadow:var(--shadow-soft);
      margin-bottom:18px;
      flex-wrap:wrap;
      transition:box-shadow .3s;
    }
    .toolbar:hover{ box-shadow:var(--shadow-hover); }

    .form-control{
      background:var(--bg-panel);
      border:2px solid transparent;
      color:var(--text-main);
      border-radius:var(--radius-pill);
      padding:0 25px;
      height:45px;
      font-family:'Nunito',sans-serif;
      font-size:15px;
      font-weight:700;
      outline:none;
      flex:1;
      transition:all .2s;
    }
    .form-control:focus{
      background:#fff;
      border-color:var(--primary);
      box-shadow:0 0 0 4px rgba(59,130,246,.1);
    }

    .btn-primary{
      background:var(--primary);
      color:#fff;
      border:none;
      padding:0 28px;
      height:45px;
      border-radius:var(--radius-pill);
      cursor:pointer;
      font-weight:800;
      font-size:15px;
      display:inline-flex;
      align-items:center;
      gap:10px;
      box-shadow:0 4px 6px rgba(59,130,246,.2);
      transition:all .2s cubic-bezier(0.4,0,0.2,1);
      text-decoration:none;
      white-space:nowrap;
    }
    .btn-primary:hover{ transform:translateY(-2px); background:var(--primary-hover); }
    .btn-primary:active{ transform:translateY(1px) scale(.95); box-shadow:none; }

    .btn-mini{
      height:34px;
      padding:0 14px;
      border-radius:var(--radius-pill);
      border:0;
      cursor:pointer;
      font-weight:900;
      font-size:12px;
      display:inline-flex;
      align-items:center;
      gap:8px;
      transition:.2s;
      white-space:nowrap;
    }
    .btn-mini.green{background:#10b981;color:#fff;}
    .btn-mini.gray{background:#e2e8f0;color:#0f172a;}
    .btn-mini:hover{transform:translateY(-1px);}

    .muted{color:var(--text-muted); font-weight:700; font-size:13px;}

    table{width:100%; border-collapse:separate; border-spacing:0 12px;}
    thead th{
      color:var(--text-muted);
      font-size:13px;
      text-transform:uppercase;
      font-weight:900;
      padding:0 30px;
      text-align:left;
    }
    tbody tr{
      background:#fff;
      box-shadow:var(--shadow-soft);
      border:2px solid var(--border-color);
      border-radius:var(--radius-card);
      transition:all .2s ease;
      cursor:default;
    }
    tbody tr:hover{
      transform:translateY(-3px) scale(1.005);
      box-shadow:var(--shadow-hover);
      border-color:#dbeafe;
    }
    tbody td{
      padding:18px 30px;
      vertical-align:middle;
      border-top:2px solid var(--border-color);
      border-bottom:2px solid var(--border-color);
    }
    tbody td:first-child{ border-top-left-radius:var(--radius-card); border-bottom-left-radius:var(--radius-card); border-left:2px solid var(--border-color); }
    tbody td:last-child{ border-top-right-radius:var(--radius-card); border-bottom-right-radius:var(--radius-card); border-right:2px solid var(--border-color); }

    .customer-name{font-weight:900; font-size:16px; color:var(--text-main); display:block;}
    .bill-id{
      font-size:13px;
      color:var(--primary);
      font-weight:800;
      margin-top:4px;
      display:inline-flex;
      gap:8px;
      align-items:center;
      background:#eff6ff;
      padding:2px 10px;
      border-radius:10px;
      text-decoration:none;
    }

    .badge{
      padding:6px 14px;
      border-radius:var(--radius-pill);
      font-weight:900;
      font-size:12px;
      text-transform:uppercase;
      display:inline-flex;
      align-items:center;
      gap:6px;
      white-space:nowrap;
    }
    .b-success{ background:var(--green-bg); color:var(--green-text); }
    .b-error{ background:var(--red-bg); color:var(--red-text); }
    .b-process{ background:var(--blue-bg); color:var(--blue-text); }
    .b-pending{ background:var(--yellow-bg); color:var(--yellow-text); }

    .btn-icon{
      color:#94a3b8;
      width:36px; height:36px;
      display:flex; align-items:center; justify-content:center;
      border-radius:50%;
      transition:.2s;
      text-decoration:none;
      border:2px solid transparent;
    }
    .btn-icon:hover{ background:#f1f5f9; color:var(--primary); border-color:#dbeafe; }

    .dot{
      width:10px;height:10px;
      border-radius:50%;
      display:inline-block;
      background:var(--primary);
      box-shadow:0 0 0 3px var(--blue-bg);
    }
    .dot-error{ background:var(--red-text); box-shadow:0 0 0 3px var(--red-bg); }
    .dot-pending{ background:var(--yellow-text); box-shadow:0 0 0 3px var(--yellow-bg); }

    .row-right{
      display:flex;
      align-items:center;
      justify-content:flex-end;
      gap:14px;
    }
    .row-meta{
      display:flex;
      flex-direction:column;
      align-items:flex-end;
      gap:4px;
      text-align:right;
      min-width:260px;
    }
    .row-meta .top{ font-weight:900; color:var(--text-main); line-height:1.1; }
    .row-meta .sub{ font-weight:800; font-size:13px; color:var(--text-muted); line-height:1.2; }

    .badges-inline{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }

    /* Loader global */
    .page-loader{
      position:fixed;
      inset:0;
      background:rgba(255,255,255,.75);
      backdrop-filter: blur(3px);
      display:flex;
      align-items:center;
      justify-content:center;
      z-index:9999;
      opacity:0;
      pointer-events:none;
      transition:opacity .18s ease;
    }
    .page-loader.show{ opacity:1; pointer-events:auto; }
    .page-loader .box{
      background:#fff;
      border:2px solid var(--border-color);
      border-radius: var(--radius-card);
      padding:22px 26px;
      box-shadow: var(--shadow-hover);
      text-align:center;
      min-width:240px;
    }
    .spinner{
      width:30px;height:30px;
      border:4px solid #e2e8f0;
      border-top-color:var(--primary);
      border-radius:50%;
      animation:spin .8s linear infinite;
      margin:0 auto;
    }
    @keyframes spin { to{ transform:rotate(360deg);} }

    @media (max-width: 980px){
      .side{display:none;}
      header{padding:0 18px;}
      thead{display:none;}
      tbody td{padding:16px 18px;}
      .row-meta{min-width:0;}
      header h1{font-size:16px;}
      .status-bar{display:none;}
    }
  </style>
</head>

<body>
<div class="app">

  <aside class="side">
    <div class="side-title"><i class="fa-solid fa-layer-group"></i> Painel</div>

    <a class="nav" href="/painel/index.php?view=notificacoes"><i class="fa-solid fa-bell"></i> Notificações</a>
    <a class="nav active" href="/painel/index.php?view=customers"><i class="fa-solid fa-users"></i> Vencidas</a>
    <a class="nav" href="/painel/index.php?view=upcoming&days=7"><i class="fa-solid fa-calendar-days"></i> A vencer</a>
    <a class="nav" href="/painel/index.php?view=paid"><i class="fa-solid fa-circle-check"></i> Pagos</a>
    <a class="nav" href="/painel/index.php?view=errors"><i class="fa-solid fa-triangle-exclamation"></i> Erros</a>

    <hr class="hr">

    <form method="post" action="/painel/index.php">
      <button class="logout-btn" name="logout" value="1">
        <i class="fa-solid fa-right-from-bracket"></i> Sair
      </button>
    </form>
  </aside>

  <div class="main">

    <!-- HEADER (igual detalhes) -->
    <header>
      <div class="head-left">
        <a href="<?=h($backHref)?>" class="btn-back" title="Voltar">
          <i class="fa-solid fa-arrow-left"></i>
        </a>

        <h1>
          <i class="fa-regular fa-file-code" style="color:var(--primary)"></i>
          <?=h($name ?: 'Cliente')?> <span class="muted" style="font-weight:900;">(ID <?=h($customerId)?>)</span>
        </h1>
      </div>

      <div class="status-bar">
        Cliente • Faturas
      </div>
    </header>

    <div class="container">

      <!-- resumo -->
      <div class="toolbar" style="justify-content:space-between;">
        <div>
          <div class="muted">
            Total em atraso: <b>R$ <?= number_format($total, 2, ',', '.') ?></b> •
            Faturas: <b><?= count($bills) ?></b> •
            Intervalo: <b><?= (int)$REMINDER_INTERVAL_DAYS ?> dias</b>
          </div>
        </div>
      </div>

      <!-- filtro -->
      <form method="get" class="toolbar" style="margin-top:-6px;">
        <input type="hidden" name="customer_id" value="<?=h($customerId)?>">
        <input type="hidden" name="name" value="<?=h($name)?>">

        <div style="flex:1; min-width:220px;">
          <div class="muted" style="margin:0 0 6px 10px;">Mostrar só com atraso ≥ (dias)</div>
          <input class="form-control" name="min_days_bill" type="number" min="0" value="<?= (int)$minDaysBill ?>" style="max-width:260px;">
        </div>

        <button class="btn-primary" type="submit">
          Aplicar <i class="fa-solid fa-check"></i>
        </button>
      </form>

      <!-- tabela -->
      <table>
        <thead>
          <tr>
            <th style="width:20px"></th>
            <th>Fatura / Documento</th>
            <th>Status</th>
            <th style="text-align:right;">Detalhes</th>
          </tr>
        </thead>
        <tbody>

        <?php if (!$bills): ?>
          <tr style="cursor:default; pointer-events:none;">
            <td colspan="4" class="muted" style="padding:22px 30px;">Nenhuma fatura vencida para este cliente.</td>
          </tr>
        <?php endif; ?>

        <?php foreach ($bills as $b):
          $billId = (int)($b['bill_id'] ?? 0);
          $due = (string)($b['due_at'] ?? '');
          $days = $due ? daysOverdueFromDueAt($due) : 0;

          $rem = getReminderInfo($pdo, $billId);
          $can = shouldSendOverdue($pdo, $billId, $REMINDER_INTERVAL_DAYS);

          $status = strtolower((string)($b['status'] ?? 'pending'));
          $dotClass = ($status === 'overdue') ? 'dot-error' : 'dot-pending';

          $billUrl = (string)($b['bill_url'] ?? '');
          $amount = (float)($b['amount'] ?? 0);

          $rowOnClick = $billUrl ? "window.open('".h($billUrl)."','_blank')" : "";
        ?>
          <tr <?= $billUrl ? 'onclick="'.$rowOnClick.'"' : '' ?> style="cursor:<?= $billUrl ? 'pointer' : 'default' ?>;">
            <td style="text-align:center;">
              <span class="dot <?= $dotClass ?>"></span>
            </td>

            <td>
              <span class="customer-name">#<?= (int)$billId ?></span>

              <div class="badges-inline" style="margin-top:6px;">
                <span class="bill-id" style="cursor:default;">
                  <i class="fa-solid fa-calendar-day"></i> <?=h($due ?: '-')?>
                </span>
                <span class="bill-id" style="cursor:default;">
                  <i class="fa-solid fa-hourglass-half"></i> <?= (int)$days ?> dias
                </span>
                <?php if ($billUrl): ?>
                  <a class="bill-id" href="<?=h($billUrl)?>" target="_blank" onclick="event.stopPropagation()">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir na Vindi
                  </a>
                <?php endif; ?>
              </div>
            </td>

            <td>
              <div class="badges-inline">
                <?= billBadgeHtml((string)$b['status']) ?>

                <?php if ((int)$rem['active'] === 0): ?>
                  <span class="badge b-process"><i class="fa-solid fa-pause"></i> Pausado</span>
                <?php endif; ?>

                <?php if ($can): ?>
                  <span class="badge b-success"><i class="fa-solid fa-paper-plane"></i> Pode enviar</span>
                <?php else: ?>
                  <span class="badge b-pending"><i class="fa-solid fa-clock"></i> Aguardar</span>
                <?php endif; ?>
              </div>
            </td>

            <td style="text-align:right;">
              <div class="row-right" onclick="event.stopPropagation()">
                <div class="row-meta">
                  <div class="top">R$ <?= number_format($amount, 2, ',', '.') ?></div>
                  <div class="sub">
                    Último envio: <?=h($rem['last'] ?? '-')?> • <?= (int)$rem['count'] ?>x
                  </div>

                  <div class="badges-inline" style="justify-content:flex-end; margin-top:8px;">
                    <form method="post" action="send.php" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                      <input type="hidden" name="bill_id" value="<?= (int)$billId ?>">
                      <input type="hidden" name="back" value="<?=h($_SERVER['REQUEST_URI'])?>">
                      <button class="btn-mini green" type="submit"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
                    </form>

                    <form method="post" action="send.php" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                      <input type="hidden" name="bill_id" value="<?= (int)$billId ?>">
                      <input type="hidden" name="force" value="1">
                      <input type="hidden" name="back" value="<?=h($_SERVER['REQUEST_URI'])?>">
                      <button class="btn-mini gray" type="submit"><i class="fa-solid fa-bolt"></i> Forçar</button>
                    </form>
                  </div>
                </div>

                <?php if ($billUrl): ?>
                  <a href="<?=h($billUrl)?>" target="_blank" class="btn-icon" onclick="event.stopPropagation()">
                    <i class="fa-solid fa-chevron-right"></i>
                  </a>
                <?php else: ?>
                  <span class="btn-icon" style="opacity:.35; pointer-events:none;">
                    <i class="fa-solid fa-chevron-right"></i>
                  </span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        </tbody>
      </table>

    </div>
  </div>
</div>

<div id="pageLoader" class="page-loader" aria-hidden="true">
  <div class="box">
    <div class="spinner"></div>
    <div style="margin-top:12px;font-weight:900;">Carregando...</div>
    <div class="muted" style="margin-top:4px;">Só um instante</div>
  </div>
</div>

<script>
(function(){
  const loader = document.getElementById('pageLoader');
  if(!loader) return;

  const show = () => loader.classList.add('show');
  const hide = () => loader.classList.remove('show');

  window.addEventListener('pageshow', hide);

  document.addEventListener('submit', () => show(), true);

  document.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if(!a) return;

    const href = a.getAttribute('href') || '';
    if(!href || href.startsWith('#')) return;
    if(a.target === '_blank' || a.hasAttribute('download')) return;

    show();
  }, true);
})();
</script>

</body>
</html>
