<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/simplesvet_sales.php';

if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }

function bsvh($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function bsvDate($value): string {
    if (!$value) return '-';
    $timestamp = strtotime((string)$value);
    return $timestamp === false ? '-' : date('d/m/Y H:i', $timestamp);
}
function bsvMoney($value): string {
    return $value === null || $value === '' ? '-' : 'R$ ' . number_format((float)$value, 2, ',', '.');
}
function bsvMethodIcon(string $method): string {
    $method = svSalesNormalize($method);
    $icons = [
        'fa-money-bill-wave' => ['DINHEIRO', 'CASH'],
        'fa-qrcode' => ['PIX'],
        'fa-barcode' => ['BOLETO', 'BANK SLIP', 'BANK_SLIP'],
        'fa-credit-card' => ['CART', 'CREDIT', 'DEBIT'],
    ];
    foreach ($icons as $icon => $needles) {
        foreach ($needles as $needle) {
            if (strpos($method, $needle) !== false) return $icon;
        }
    }
    return 'fa-wallet';
}

$vindiAdmin = 'https://app.vindi.com.br/admin';
$labels = svSalesStatusLabels();
$filterLabels = $labels + ['queue' => 'Na fila ou processando', 'cash_robot' => 'Dinheiro processado pelo robô'];
$stats = array_fill_keys(array_keys($labels), 0);
$amounts = array_fill_keys(array_keys($labels), 0.0);
$tableExists = false;
$rows = [];
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$total = 0;
$status = trim((string)($_GET['status'] ?? ''));
if (!array_key_exists($status, $filterLabels)) $status = '';
$query = trim((string)($_GET['q'] ?? ''));
$month = trim((string)($_GET['month'] ?? ''));
$flash = '';
$flashError = false;
$lastCompleted = null;
$stalePending = 0;
$cashOpen = 0;
$cashRobot = 0;

try {
    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='simplesvet_sale_jobs'");
    $tableExists = (int)$check->fetchColumn() > 0;
} catch (Throwable $e) {
    $tableExists = false;
}

if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bsv_action'])) {
    if (!authCsrfValid((string)($_POST['csrf'] ?? ''))) {
        $flash = 'Sessão expirada. Atualize a página e tente novamente.';
        $flashError = true;
    } else {
        try {
            $action = (string)$_POST['bsv_action'];
            if ($action === 'reclassify_cash') {
                $changed = svSalesReclassifyCashJobs($pdo);
                $flash = $changed > 0
                    ? $changed . ' pagamento(s) em dinheiro movido(s) para “Pago em dinheiro”. O robô não vai criar venda para eles.'
                    : 'Nenhum pagamento em dinheiro aguardando na fila.';
            } elseif ($action === 'retry_job') {
                $id = (int)($_POST['job_id'] ?? 0);
                $find = $pdo->prepare('SELECT source_payload FROM simplesvet_sale_jobs WHERE id=? LIMIT 1');
                $find->execute([$id]);
                $payload = $find->fetchColumn();
                if ($payload === false) throw new RuntimeException('Tarefa não encontrada.');
                if (svSalesManualPaymentMethod(svSalesJobBill($payload)) !== null) {
                    throw new RuntimeException('Pagamento em dinheiro: a venda já foi lançada no balcão, então o robô não cria outra.');
                }
                // Revisao manual so volta para a fila depois de confirmar que a venda nao existe no SimplesVet.
                $allowed = !empty($_POST['confirm_no_sale']) ? "('retry','manual_review')" : "('retry')";
                $retry = $pdo->prepare("
                    UPDATE simplesvet_sale_jobs
                       SET status='pending', attempts=0, next_attempt_at=?, last_error=NULL
                     WHERE id=? AND status IN {$allowed} AND simplesvet_sale_id IS NULL
                ");
                $retry->execute([date('Y-m-d H:i:s'), $id]);
                if ($retry->rowCount() === 0) {
                    throw new RuntimeException("A tarefa #{$id} não pode voltar para a fila: ela já tem venda no SimplesVet ou mudou de status.");
                }
                $flash = "Tarefa #{$id} voltou para a fila. O robô tenta de novo na próxima rodada.";
            } else {
                throw new RuntimeException('Ação inválida.');
            }
        } catch (Throwable $e) {
            $flash = $e->getMessage();
            $flashError = true;
        }
    }
}

if ($tableExists) {
    try {
        foreach ($pdo->query('SELECT status, COUNT(*) total, COALESCE(SUM(amount),0) amount FROM simplesvet_sale_jobs GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!array_key_exists($row['status'], $stats)) continue;
            $stats[$row['status']] = (int)$row['total'];
            $amounts[$row['status']] = (float)$row['amount'];
        }
        $lastCompleted = $pdo->query('SELECT MAX(completed_at) FROM simplesvet_sale_jobs')->fetchColumn() ?: null;
        $stale = $pdo->prepare("SELECT COUNT(*) FROM simplesvet_sale_jobs WHERE status='pending' AND next_attempt_at < ?");
        $stale->execute([date('Y-m-d H:i:s', time() - 30 * 60)]);
        $stalePending = (int)$stale->fetchColumn();
        $cashOpen = count(svSalesCashOpenJobIds($pdo));
        $cashRobot = count(svSalesCashRobotJobIds($pdo));

        [$whereSql, $params] = svSalesJobsWhere($pdo, $status, $query, $month);
        $count = $pdo->prepare("SELECT COUNT(*) FROM simplesvet_sale_jobs WHERE {$whereSql}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("
            SELECT id, bill_id, customer_id, customer_name, amount, paid_at, status, attempts,
                   next_attempt_at, simplesvet_sale_id, last_error, completed_at, created_at,
                   updated_at, source_payload
              FROM simplesvet_sale_jobs
             WHERE {$whereSql}
             ORDER BY COALESCE(paid_at, created_at) DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $flash = 'Não foi possível carregar as baixas: ' . $e->getMessage();
        $flashError = true;
    }
}

$totalPages = max(1, (int)ceil($total / $perPage));
$keepFilters = static function (array $override) use ($status, $query, $month): string {
    return '?' . http_build_query(array_filter(array_merge(
        ['pagina' => 'baixas_sv', 'status' => $status, 'q' => $query, 'month' => $month],
        $override
    ), static fn($value) => $value !== '' && $value !== null));
};
$pageUrl = static fn(int $target): string => $keepFilters(['p' => $target]);
$cardUrl = static fn(string $key): string => $keepFilters(['status' => $status === $key ? '' : $key]);
$exportUrl = '/painel/api/baixas_sv_export.php?' . http_build_query(array_filter(
    ['status' => $status, 'q' => $query, 'month' => $month],
    static fn($value) => $value !== ''
));
$cards = [
    ['completed', 'Concluídas', 'ok', $stats['completed'], ''],
    ['queue', 'Na fila', '', $stats['pending'] + $stats['processing'], ''],
    ['waiting_mapping', 'Aguardando conciliação', 'warn', $stats['waiting_mapping'], ''],
    ['awaiting_receipt', 'Venda criada / baixa pendente', 'warn', $stats['awaiting_receipt'], ''],
    ['retry', 'Nova tentativa', 'warn', $stats['retry'], ''],
    ['manual_review', 'Revisão manual', 'err', $stats['manual_review'], ''],
    ['ignored', 'Não usar', '', $stats['ignored'], ''],
    ['manual_payment', 'Pago em dinheiro', '', $stats['manual_payment'], $stats['manual_payment'] ? bsvMoney($amounts['manual_payment']) : ''],
];
$defaultDetail = [
    'completed' => 'Concluído sem ocorrências',
    'manual_payment' => 'Baixa manual em dinheiro na Vindi; venda não criada pelo robô',
    'pending' => 'Aguardando o robô',
    'processing' => 'Robô trabalhando nesta venda',
    'waiting_mapping' => 'Plano ainda não vinculado ao SimplesVet',
    'awaiting_receipt' => 'Venda criada; falta registrar o recebimento',
    'ignored' => 'Plano marcado como “Não usar”',
];
?>
<section class="bsv-page">
  <style>
    .bsv-badge.awaiting_receipt{background:#fef3c7;color:#8a5b00}
    .bsv-page{font-family:'Nunito',sans-serif;color:#172033;max-width:1180px;margin:0 auto;padding:4px 24px 40px}.bsv-head{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;margin-bottom:22px}.bsv-head h1{font-size:28px;margin:0 0 5px}.bsv-head p{margin:0;color:#68758b}.bsv-refresh{border:0;border-radius:12px;padding:11px 17px;background:#38b6ff;color:#fff;font-weight:800;cursor:pointer}.bsv-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:13px;margin-bottom:20px}.bsv-card{padding:18px;background:#fff;border:1px solid #e6edf5;border-radius:17px;box-shadow:0 5px 18px rgba(28,48,78,.05)}.bsv-card span{display:block;color:#7b8799;font-size:11px;font-weight:900;text-transform:uppercase}.bsv-card strong{display:block;margin-top:5px;font-size:27px}.bsv-card.ok strong{color:#138a5b}.bsv-card.warn strong{color:#b7791f}.bsv-card.err strong{color:#c24141}.bsv-panel{overflow:hidden;background:#fff;border:1px solid #e6edf5;border-radius:18px;box-shadow:0 5px 18px rgba(28,48,78,.05)}.bsv-tools{display:grid;grid-template-columns:minmax(220px,1fr) 190px 170px auto;gap:10px;padding:17px;border-bottom:1px solid #edf1f6}.bsv-tools input,.bsv-tools select,.bsv-tools button{box-sizing:border-box;height:42px;border:1px solid #dce4ee;border-radius:10px;background:#fff;padding:0 12px;font:inherit;color:#344054}.bsv-tools button{background:#38b6ff;border-color:#38b6ff;color:#fff;font-weight:900;cursor:pointer}.bsv-table-wrap{overflow-x:auto}.bsv-table{width:100%;border-collapse:collapse}.bsv-table th,.bsv-table td{padding:14px 17px;text-align:left;border-bottom:1px solid #edf1f6}.bsv-table th{background:#f8fafc;color:#718096;font-size:10px;text-transform:uppercase;letter-spacing:.05em}.bsv-table td{font-size:13px;vertical-align:top}.bsv-name{font-weight:900}.bsv-sub{margin-top:3px;color:#8a96a8;font-size:11px}.bsv-badge{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:10px;font-weight:900;white-space:nowrap}.bsv-badge.completed{background:#d1fae5;color:#06603f}.bsv-badge.pending,.bsv-badge.processing{background:#dbeafe;color:#1e40af}.bsv-badge.retry,.bsv-badge.waiting_mapping{background:#fef3c7;color:#8a5b00}.bsv-badge.manual_review{background:#fee2e2;color:#991b1b}.bsv-badge.ignored,.bsv-badge.manual_payment{background:#e5e7eb;color:#475467}.bsv-error{max-width:270px;color:#7a4650;overflow-wrap:anywhere}.bsv-empty{padding:46px 20px;text-align:center;color:#68758b}.bsv-pager{display:flex;justify-content:center;gap:7px;padding:16px}.bsv-pager a,.bsv-pager span{padding:8px 12px;border:1px solid #dce4ee;border-radius:9px;color:#475467;text-decoration:none;font-size:12px;font-weight:900}.bsv-pager .active{background:#38b6ff;border-color:#38b6ff;color:#fff}@media(max-width:800px){.bsv-page{padding-left:10px;padding-right:10px}.bsv-head{align-items:flex-start;flex-direction:column}.bsv-tools{grid-template-columns:1fr 1fr}.bsv-table{min-width:850px}}@media(max-width:520px){.bsv-tools{grid-template-columns:1fr}.bsv-cards{grid-template-columns:1fr 1fr}}
    .bsv-meta{margin-top:8px;color:#68758b;font-size:12px}.bsv-meta i{margin-right:4px;color:#38b6ff}.bsv-head-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}.bsv-btn-light{display:inline-flex;align-items:center;gap:7px;font-size:13px;border:1px solid #dce4ee;border-radius:12px;padding:10px 15px;background:#fff;color:#344054;font-weight:800;text-decoration:none}.bsv-btn-light:hover{border-color:#38b6ff;color:#1d8fd6}.bsv-auto{display:inline-flex;align-items:center;gap:6px;color:#68758b;font-size:12px;font-weight:800;cursor:pointer}
    a.bsv-card{color:inherit;text-decoration:none;transition:border-color .15s,box-shadow .15s,transform .15s}a.bsv-card:hover{border-color:#38b6ff;transform:translateY(-1px)}.bsv-card.active{border-color:#38b6ff;box-shadow:0 0 0 3px rgba(56,182,255,.18)}.bsv-card small{display:block;margin-top:3px;color:#8a96a8;font-size:11px;font-weight:800}
    .bsv-flash{margin-bottom:15px;padding:12px 15px;border-radius:11px;background:#e8f8ef;color:#087f3f;font-weight:800}.bsv-flash.error{background:#fee2e2;color:#991b1b}.bsv-alert{display:flex;align-items:center;gap:13px;margin-bottom:12px;padding:13px 16px;border:1px solid;border-radius:14px;font-size:13px}.bsv-alert>i{font-size:18px}.bsv-alert>div{flex:1}.bsv-alert.warn{background:#fffbeb;border-color:#fde68a;color:#8a5b00}.bsv-alert.err{background:#fef2f2;border-color:#fecaca;color:#991b1b}.bsv-alert button,.bsv-alert a{border:0;border-radius:10px;padding:9px 13px;background:#fff;color:inherit;font:inherit;font-weight:900;text-decoration:none;white-space:nowrap;cursor:pointer;box-shadow:0 1px 3px rgba(28,48,78,.1)}
    .bsv-link{color:#1d8fd6;font-weight:800;text-decoration:none}.bsv-link i{margin-left:3px;font-size:10px}.bsv-nowrap{white-space:nowrap}.bsv-error-text{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.bsv-method{white-space:nowrap;display:inline-flex;align-items:center;gap:5px;margin-top:5px;padding:3px 8px;border-radius:999px;background:#f1f5f9;color:#475467;font-size:11px;font-weight:800}.bsv-method.cash{background:#e8f8ef;color:#087f3f}.bsv-warn-chip{display:inline-block;margin-top:6px;padding:4px 8px;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:900}.bsv-error a{color:#1d8fd6;font-weight:800}
    .bsv-actions{text-align:right;white-space:nowrap}.bsv-actions form{display:inline}.bsv-icon-btn{width:34px;height:34px;border:1px solid #dce4ee;border-radius:10px;background:#fff;color:#475467;cursor:pointer}.bsv-icon-btn i{transition:transform .15s}.bsv-icon-btn[aria-expanded="true"] i{transform:rotate(180deg)}.bsv-retry{height:34px;margin-left:6px;border:0;border-radius:10px;padding:0 11px;background:#dbeafe;color:#1e40af;font:inherit;font-size:11px;font-weight:900;cursor:pointer}
    .bsv-detail td{background:#f8fafc}.bsv-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}.bsv-detail h4{margin:0 0 8px;color:#718096;font-size:10px;text-transform:uppercase;letter-spacing:.05em}.bsv-detail ul{margin:0;padding:0;list-style:none}.bsv-detail li{display:flex;justify-content:space-between;gap:12px;padding:5px 0;border-bottom:1px dashed #e2e8f0}.bsv-detail dl{display:grid;grid-template-columns:auto 1fr;gap:5px 12px;margin:0}.bsv-detail dt{color:#8a96a8}.bsv-detail dd{margin:0;font-weight:700;overflow-wrap:anywhere}.bsv-detail-error{grid-column:1/-1}.bsv-detail pre{margin:0;padding:10px;border:1px solid #edf1f6;border-radius:10px;background:#fff;color:#7a4650;font:12px/1.5 ui-monospace,Consolas,monospace;white-space:pre-wrap;overflow-wrap:anywhere}
    .bsv-pager{align-items:center;flex-wrap:wrap}.bsv-pager em{margin-right:8px;color:#8a96a8;font-size:12px;font-style:normal}@media(max-width:800px){.bsv-head-actions{justify-content:flex-start}.bsv-alert{flex-wrap:wrap}}
  </style>
  <div class="bsv-head">
    <div>
      <h1>Baixas SV</h1>
      <p>Vendas criadas e baixadas no SimplesVet após a confirmação de pagamento na Vindi.</p>
      <div class="bsv-meta"><i class="fa-solid fa-robot"></i> Última venda concluída pelo robô: <strong><?=bsvh(bsvDate($lastCompleted))?></strong></div>
    </div>
    <div class="bsv-head-actions">
      <label class="bsv-auto" title="Recarrega a cada 30 segundos enquanto nenhum detalhe estiver aberto"><input type="checkbox" id="bsvAuto"> Atualizar sozinho</label>
      <a class="bsv-btn-light" id="bsvExport" href="<?=bsvh($exportUrl)?>"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
      <button class="bsv-refresh" type="button" onclick="location.reload()"><i class="fa-solid fa-rotate"></i> Atualizar</button>
    </div>
  </div>
  <?php if ($flash !== ''): ?><div class="bsv-flash <?=$flashError ? 'error' : ''?>"><?=bsvh($flash)?></div><?php endif; ?>
  <?php if ($cashOpen > 0): ?>
    <form class="bsv-alert warn" method="post">
      <i class="fa-solid fa-money-bill-wave"></i>
      <div><strong><?=bsvh($cashOpen)?> pagamento(s) em dinheiro</strong> entraram antes da regra nova e ainda estão na fila do robô. Pagamento em dinheiro não vira venda automática.</div>
      <input type="hidden" name="csrf" value="<?=bsvh(authCsrfToken())?>">
      <button name="bsv_action" value="reclassify_cash">Mover para “Pago em dinheiro”</button>
    </form>
  <?php endif; ?>
  <?php if ($cashRobot > 0): ?>
    <div class="bsv-alert err">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <div><strong><?=bsvh($cashRobot)?> pagamento(s) em dinheiro</strong> foram processados pelo robô antes da regra nova. Confira no SimplesVet se a venda não ficou duplicada.</div>
      <a href="<?=bsvh($keepFilters(['status' => 'cash_robot']))?>">Ver lista</a>
    </div>
  <?php endif; ?>
  <?php if ($stalePending > 0): ?>
    <div class="bsv-alert warn">
      <i class="fa-solid fa-hourglass-half"></i>
      <div><strong><?=bsvh($stalePending)?> tarefa(s)</strong> esperando na fila há mais de 30 minutos. Confira se o robô está rodando na VPS.</div>
    </div>
  <?php endif; ?>
  <div class="bsv-cards">
    <?php foreach ($cards as [$key, $label, $tone, $value, $sub]): ?>
      <a class="bsv-card <?=bsvh($tone)?> <?=$status === $key ? 'active' : ''?>" href="<?=bsvh($cardUrl($key))?>"><span><?=bsvh($label)?></span><strong><?=bsvh($value)?></strong><?php if ($sub !== ''): ?><small><?=bsvh($sub)?></small><?php endif; ?></a>
    <?php endforeach; ?>
    <div class="bsv-card ok"><span>Valor concluído</span><strong style="font-size:21px"><?=bsvh(bsvMoney($amounts['completed']))?></strong></div>
  </div>
  <div class="bsv-panel">
    <form class="bsv-tools" method="get">
      <input type="hidden" name="pagina" value="baixas_sv">
      <input type="search" name="q" value="<?=bsvh($query)?>" placeholder="Cliente, fatura Vindi ou venda SV">
      <select name="status"><option value="">Todos os status</option><?php foreach ($filterLabels as $value => $label): ?><option value="<?=bsvh($value)?>" <?=$status === $value ? 'selected' : ''?>><?=bsvh($label)?></option><?php endforeach; ?></select>
      <input type="month" name="month" value="<?=bsvh($month)?>" aria-label="Mês">
      <button type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
    </form>
    <?php if (!$tableExists): ?>
      <div class="bsv-empty">A fila será criada automaticamente quando a Vindi informar o próximo pagamento.</div>
    <?php elseif (!$rows): ?>
      <div class="bsv-empty">Nenhuma baixa encontrada para os filtros selecionados.</div>
    <?php else: ?>
      <div class="bsv-table-wrap"><table class="bsv-table"><thead><tr><th>Cliente</th><th>Fatura Vindi</th><th>Valor</th><th>Pagamento</th><th>Status</th><th>Venda SV</th><th>Detalhe</th><th></th></tr></thead><tbody>
      <?php foreach ($rows as $row):
          $bill = svSalesJobBill($row['source_payload']);
          $method = svSalesPaymentMethodName($bill);
          $items = svSalesBillItemsSummary($bill);
          $isCash = svSalesManualPaymentMethod($bill) !== null;
          $hasSale = (string)$row['simplesvet_sale_id'] !== '';
          $cashWithSale = $isCash && ($hasSale || in_array($row['status'], ['completed', 'awaiting_receipt', 'manual_review', 'processing'], true));
          $canRetry = !$isCash && !$hasSale && in_array($row['status'], ['retry', 'manual_review'], true);
          $detailId = 'bsv-detail-' . (int)$row['id'];
          $itemNames = implode(', ', array_unique(array_column($items, 'name')));
      ?>
        <tr>
          <td><div class="bsv-name"><?=bsvh($row['customer_name'] ?: 'Cliente')?></div><div class="bsv-sub">Tarefa #<?=bsvh($row['id'])?><?=$itemNames !== '' ? ' · ' . bsvh($itemNames) : ''?></div></td>
          <td><a class="bsv-link" href="<?=bsvh($vindiAdmin . '/bills/' . (int)$row['bill_id'])?>" target="_blank" rel="noopener" title="Abrir fatura na Vindi">#<?=bsvh($row['bill_id'])?><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
          <td class="bsv-nowrap"><?=bsvh(bsvMoney($row['amount']))?></td>
          <td><?=bsvh(bsvDate($row['paid_at']))?><?php if ($method !== ''): ?><div><span class="bsv-method <?=$isCash ? 'cash' : ''?>"><i class="fa-solid <?=bsvh(bsvMethodIcon($method))?>"></i> <?=bsvh($method)?></span></div><?php endif; ?></td>
          <td><span class="bsv-badge <?=bsvh($row['status'])?>"><?=bsvh($labels[$row['status']] ?? $row['status'])?></span><div class="bsv-sub"><?=bsvh($row['attempts'])?> tentativa(s)<?=$row['status'] === 'retry' && $row['next_attempt_at'] ? ' · próxima ' . bsvh(bsvDate($row['next_attempt_at'])) : ''?></div></td>
          <td><?=bsvh($row['simplesvet_sale_id'] ?: '-')?><?php if ($row['completed_at']): ?><div class="bsv-sub"><?=bsvh(bsvDate($row['completed_at']))?></div><?php endif; ?></td>
          <td class="bsv-error">
            <div class="bsv-error-text" title="<?=bsvh($row['last_error'] ?? '')?>"><?=bsvh($row['last_error'] ?: ($defaultDetail[$row['status']] ?? '-'))?></div>
            <?php if ($row['status'] === 'waiting_mapping'): ?><div><a href="?pagina=conciliacao_planos">Conciliar plano</a></div><?php endif; ?>
            <?php if ($cashWithSale): ?><div><span class="bsv-warn-chip">Dinheiro: confira venda duplicada</span></div><?php endif; ?>
          </td>
          <td class="bsv-actions">
            <button type="button" class="bsv-icon-btn" data-bsv-toggle="<?=bsvh($detailId)?>" aria-expanded="false" aria-controls="<?=bsvh($detailId)?>" title="Ver detalhes"><i class="fa-solid fa-chevron-down"></i></button>
            <?php if ($canRetry):
                $confirmText = 'Antes de tentar de novo, confira no SimplesVet (referência VINDI #' . $row['bill_id'] . ') se a venda não foi criada. Confirma que a venda NÃO existe?';
            ?>
              <form method="post"<?php if ($row['status'] === 'manual_review'): ?> onsubmit="return confirm(<?=bsvh(json_encode($confirmText, JSON_UNESCAPED_UNICODE))?>)"<?php endif; ?>>
                <input type="hidden" name="csrf" value="<?=bsvh(authCsrfToken())?>">
                <input type="hidden" name="job_id" value="<?=bsvh($row['id'])?>">
                <?php if ($row['status'] === 'manual_review'): ?><input type="hidden" name="confirm_no_sale" value="1"><?php endif; ?>
                <button class="bsv-retry" name="bsv_action" value="retry_job" title="Colocar de volta na fila do robô"><i class="fa-solid fa-rotate-right"></i> Tentar agora</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <tr class="bsv-detail" id="<?=bsvh($detailId)?>" hidden>
          <td colspan="8"><div class="bsv-detail-grid">
            <div>
              <h4>Itens da fatura</h4>
              <?php if ($items): ?>
                <ul><?php foreach ($items as $item): ?><li><span><?=bsvh($item['name'])?><?=$item['quantity'] > 1 ? ' × ' . bsvh($item['quantity']) : ''?></span><strong><?=bsvh(bsvMoney($item['amount']))?></strong></li><?php endforeach; ?></ul>
              <?php else: ?>
                <div class="bsv-sub">O aviso da Vindi não trouxe os itens.</div>
              <?php endif; ?>
            </div>
            <div>
              <h4>Linha do tempo</h4>
              <dl>
                <dt>Pago em</dt><dd><?=bsvh(bsvDate($row['paid_at']))?></dd>
                <dt>Entrou na fila</dt><dd><?=bsvh(bsvDate($row['created_at']))?></dd>
                <?php if ($row['next_attempt_at']): ?><dt>Próxima tentativa</dt><dd><?=bsvh(bsvDate($row['next_attempt_at']))?></dd><?php endif; ?>
                <?php if ($row['completed_at']): ?><dt>Concluída</dt><dd><?=bsvh(bsvDate($row['completed_at']))?></dd><?php endif; ?>
                <dt>Atualizada</dt><dd><?=bsvh(bsvDate($row['updated_at']))?></dd>
              </dl>
            </div>
            <div>
              <h4>Vindi</h4>
              <dl>
                <dt>Forma</dt><dd><?=bsvh($method !== '' ? $method : '-')?></dd>
                <dt>Cliente</dt><dd><?php if ($row['customer_id']): ?><a class="bsv-link" href="<?=bsvh($vindiAdmin . '/customers/' . (int)$row['customer_id'])?>" target="_blank" rel="noopener">#<?=bsvh($row['customer_id'])?><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php else: ?>-<?php endif; ?></dd>
                <dt>Fatura</dt><dd><a class="bsv-link" href="<?=bsvh($vindiAdmin . '/bills/' . (int)$row['bill_id'])?>" target="_blank" rel="noopener">#<?=bsvh($row['bill_id'])?><i class="fa-solid fa-arrow-up-right-from-square"></i></a></dd>
                <dt>Venda SV</dt><dd><?=bsvh($row['simplesvet_sale_id'] ?: '-')?></dd>
              </dl>
            </div>
            <?php if ($row['last_error']): ?>
              <div class="bsv-detail-error"><h4>Ocorrência completa</h4><pre><?=bsvh($row['last_error'])?></pre></div>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
      <div class="bsv-pager">
        <em><?=bsvh(($page - 1) * $perPage + 1)?>–<?=bsvh(min($total, $page * $perPage))?> de <?=bsvh($total)?></em>
        <?php if ($totalPages > 1): ?>
          <?php if ($page > 1): ?><a href="<?=bsvh($pageUrl($page - 1))?>" aria-label="Página anterior">‹</a><?php endif; ?>
          <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?><a class="<?=$i === $page ? 'active' : ''?>" href="<?=bsvh($pageUrl($i))?>"><?=$i?></a><?php endfor; ?>
          <?php if ($page < $totalPages): ?><a href="<?=bsvh($pageUrl($page + 1))?>" aria-label="Próxima página">›</a><?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<script>
(function () {
  document.querySelectorAll('[data-bsv-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var row = document.getElementById(button.getAttribute('data-bsv-toggle'));
      if (!row) return;
      row.hidden = !row.hidden;
      button.setAttribute('aria-expanded', row.hidden ? 'false' : 'true');
    });
  });

  // Download nao troca de pagina; sem isso o carregador do painel fica preso na tela.
  var exportLink = document.getElementById('bsvExport');
  if (exportLink) {
    exportLink.addEventListener('click', function (event) {
      event.preventDefault();
      window.location.href = exportLink.href;
    });
  }

  var auto = document.getElementById('bsvAuto');
  var timer = null;
  function arm() {
    clearInterval(timer);
    if (!auto || !auto.checked) return;
    timer = setInterval(function () {
      if (!document.querySelector('.bsv-detail:not([hidden])')) location.reload();
    }, 30000);
  }
  if (auto) {
    try { auto.checked = localStorage.getItem('bsvAutoRefresh') === '1'; } catch (e) {}
    auto.addEventListener('change', function () {
      try { localStorage.setItem('bsvAutoRefresh', auto.checked ? '1' : '0'); } catch (e) {}
      arm();
    });
    arm();
  }
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  // Evita reenviar a acao ao atualizar a pagina.
  history.replaceState(null, '', location.href);
<?php endif; ?>
})();
</script>
