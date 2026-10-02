<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';

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

$tableExists = false;
$rows = [];
$stats = ['waiting_mapping' => 0, 'pending' => 0, 'processing' => 0, 'awaiting_receipt' => 0, 'retry' => 0, 'completed' => 0, 'manual_review' => 0, 'ignored' => 0, 'manual_payment' => 0];
$totalAmount = 0.0;
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$total = 0;
$status = trim((string)($_GET['status'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));
$month = trim((string)($_GET['month'] ?? ''));

try {
    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='simplesvet_sale_jobs'");
    $tableExists = (int)$check->fetchColumn() > 0;
    if ($tableExists) {
        foreach ($pdo->query('SELECT status, COUNT(*) total FROM simplesvet_sale_jobs GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['status'], $stats)) $stats[$row['status']] = (int)$row['total'];
        }
        $totalAmount = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) FROM simplesvet_sale_jobs WHERE status='completed'")->fetchColumn() ?: 0);

        $where = ['1=1'];
        $params = [];
        if ($status !== '' && array_key_exists($status, $stats)) {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($query !== '') {
            $where[] = '(customer_name LIKE :q OR CAST(bill_id AS CHAR) LIKE :q OR simplesvet_sale_id LIKE :q)';
            $params[':q'] = '%' . $query . '%';
        }
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $where[] = "DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') = :month";
            $params[':month'] = $month;
        }
        $whereSql = implode(' AND ', $where);
        $count = $pdo->prepare("SELECT COUNT(*) FROM simplesvet_sale_jobs WHERE {$whereSql}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("
            SELECT id, bill_id, customer_name, amount, paid_at, status, attempts,
                   simplesvet_sale_id, last_error, completed_at, created_at
              FROM simplesvet_sale_jobs
             WHERE {$whereSql}
             ORDER BY COALESCE(paid_at, created_at) DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    $tableExists = false;
}

$totalPages = max(1, (int)ceil($total / $perPage));
$labels = [
    'pending' => 'Na fila', 'processing' => 'Processando', 'retry' => 'Nova tentativa',
    'completed' => 'Concluída', 'manual_review' => 'Revisão manual',
    'waiting_mapping' => 'Aguardando conciliação', 'awaiting_receipt' => 'Venda criada; baixa pendente', 'ignored' => 'Não usar',
    'manual_payment' => 'Pago em dinheiro',
];
$pageUrl = static function (int $target) use ($status, $query, $month): string {
    return '?' . http_build_query(array_filter([
        'pagina' => 'baixas_sv', 'p' => $target, 'status' => $status, 'q' => $query, 'month' => $month,
    ], static fn($value) => $value !== ''));
};
?>
<section class="bsv-page">
  <style>
    .bsv-badge.awaiting_receipt{background:#fef3c7;color:#8a5b00}
    .bsv-page{font-family:'Nunito',sans-serif;color:#172033;max-width:1180px;margin:0 auto;padding:4px 24px 40px}.bsv-head{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;margin-bottom:22px}.bsv-head h1{font-size:28px;margin:0 0 5px}.bsv-head p{margin:0;color:#68758b}.bsv-refresh{border:0;border-radius:12px;padding:11px 17px;background:#38b6ff;color:#fff;font-weight:800;cursor:pointer}.bsv-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:13px;margin-bottom:20px}.bsv-card{padding:18px;background:#fff;border:1px solid #e6edf5;border-radius:17px;box-shadow:0 5px 18px rgba(28,48,78,.05)}.bsv-card span{display:block;color:#7b8799;font-size:11px;font-weight:900;text-transform:uppercase}.bsv-card strong{display:block;margin-top:5px;font-size:27px}.bsv-card.ok strong{color:#138a5b}.bsv-card.warn strong{color:#b7791f}.bsv-card.err strong{color:#c24141}.bsv-panel{overflow:hidden;background:#fff;border:1px solid #e6edf5;border-radius:18px;box-shadow:0 5px 18px rgba(28,48,78,.05)}.bsv-tools{display:grid;grid-template-columns:minmax(220px,1fr) 190px 170px auto;gap:10px;padding:17px;border-bottom:1px solid #edf1f6}.bsv-tools input,.bsv-tools select,.bsv-tools button{box-sizing:border-box;height:42px;border:1px solid #dce4ee;border-radius:10px;background:#fff;padding:0 12px;font:inherit;color:#344054}.bsv-tools button{background:#38b6ff;border-color:#38b6ff;color:#fff;font-weight:900;cursor:pointer}.bsv-table-wrap{overflow-x:auto}.bsv-table{width:100%;border-collapse:collapse}.bsv-table th,.bsv-table td{padding:14px 17px;text-align:left;border-bottom:1px solid #edf1f6}.bsv-table th{background:#f8fafc;color:#718096;font-size:10px;text-transform:uppercase;letter-spacing:.05em}.bsv-table td{font-size:13px;vertical-align:top}.bsv-name{font-weight:900}.bsv-sub{margin-top:3px;color:#8a96a8;font-size:11px}.bsv-badge{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:10px;font-weight:900;white-space:nowrap}.bsv-badge.completed{background:#d1fae5;color:#06603f}.bsv-badge.pending,.bsv-badge.processing{background:#dbeafe;color:#1e40af}.bsv-badge.retry,.bsv-badge.waiting_mapping{background:#fef3c7;color:#8a5b00}.bsv-badge.manual_review{background:#fee2e2;color:#991b1b}.bsv-badge.ignored,.bsv-badge.manual_payment{background:#e5e7eb;color:#475467}.bsv-error{max-width:270px;color:#7a4650;overflow-wrap:anywhere}.bsv-empty{padding:46px 20px;text-align:center;color:#68758b}.bsv-pager{display:flex;justify-content:center;gap:7px;padding:16px}.bsv-pager a,.bsv-pager span{padding:8px 12px;border:1px solid #dce4ee;border-radius:9px;color:#475467;text-decoration:none;font-size:12px;font-weight:900}.bsv-pager .active{background:#38b6ff;border-color:#38b6ff;color:#fff}@media(max-width:800px){.bsv-page{padding-left:10px;padding-right:10px}.bsv-head{align-items:flex-start;flex-direction:column}.bsv-tools{grid-template-columns:1fr 1fr}.bsv-table{min-width:850px}}@media(max-width:520px){.bsv-tools{grid-template-columns:1fr}.bsv-cards{grid-template-columns:1fr 1fr}}
  </style>
  <div class="bsv-head">
    <div><h1>Baixas SV</h1><p>Vendas criadas e baixadas no SimplesVet após a confirmação de pagamento na Vindi.</p></div>
    <button class="bsv-refresh" type="button" onclick="location.reload()"><i class="fa-solid fa-rotate"></i> Atualizar</button>
  </div>
  <div class="bsv-cards">
    <div class="bsv-card ok"><span>Concluídas</span><strong><?=bsvh($stats['completed'])?></strong></div>
    <div class="bsv-card"><span>Na fila</span><strong><?=bsvh($stats['pending'] + $stats['processing'])?></strong></div>
    <div class="bsv-card warn"><span>Aguardando conciliação</span><strong><?=bsvh($stats['waiting_mapping'])?></strong></div>
    <div class="bsv-card warn"><span>Venda criada / baixa pendente</span><strong><?=bsvh($stats['awaiting_receipt'])?></strong></div>
    <div class="bsv-card warn"><span>Nova tentativa</span><strong><?=bsvh($stats['retry'])?></strong></div>
    <div class="bsv-card err"><span>Revisão manual</span><strong><?=bsvh($stats['manual_review'])?></strong></div>
    <div class="bsv-card"><span>Não usar</span><strong><?=bsvh($stats['ignored'])?></strong></div>
    <div class="bsv-card"><span>Pago em dinheiro</span><strong><?=bsvh($stats['manual_payment'])?></strong></div>
    <div class="bsv-card ok"><span>Valor concluído</span><strong style="font-size:21px"><?=bsvh(bsvMoney($totalAmount))?></strong></div>
  </div>
  <div class="bsv-panel">
    <form class="bsv-tools" method="get">
      <input type="hidden" name="pagina" value="baixas_sv">
      <input type="search" name="q" value="<?=bsvh($query)?>" placeholder="Cliente, fatura Vindi ou venda SV">
      <select name="status"><option value="">Todos os status</option><?php foreach ($labels as $value => $label): ?><option value="<?=bsvh($value)?>" <?=$status === $value ? 'selected' : ''?>><?=bsvh($label)?></option><?php endforeach; ?></select>
      <input type="month" name="month" value="<?=bsvh($month)?>" aria-label="Mês">
      <button type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
    </form>
    <?php if (!$tableExists): ?>
      <div class="bsv-empty">A fila será criada automaticamente quando a Vindi informar o próximo pagamento.</div>
    <?php elseif (!$rows): ?>
      <div class="bsv-empty">Nenhuma baixa encontrada para os filtros selecionados.</div>
    <?php else: ?>
      <div class="bsv-table-wrap"><table class="bsv-table"><thead><tr><th>Cliente</th><th>Fatura Vindi</th><th>Valor</th><th>Pagamento</th><th>Status</th><th>Venda SV</th><th>Detalhe</th></tr></thead><tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><div class="bsv-name"><?=bsvh($row['customer_name'] ?: 'Cliente')?></div><div class="bsv-sub">Tarefa #<?=bsvh($row['id'])?></div></td>
          <td>#<?=bsvh($row['bill_id'])?></td><td><?=bsvh(bsvMoney($row['amount']))?></td><td><?=bsvh(bsvDate($row['paid_at']))?></td>
          <td><span class="bsv-badge <?=bsvh($row['status'])?>"><?=bsvh($labels[$row['status']] ?? $row['status'])?></span><div class="bsv-sub"><?=bsvh($row['attempts'])?> tentativa(s)</div></td>
          <td><?=bsvh($row['simplesvet_sale_id'] ?: '-')?><?php if ($row['completed_at']): ?><div class="bsv-sub"><?=bsvh(bsvDate($row['completed_at']))?></div><?php endif; ?></td>
          <td class="bsv-error"><?=bsvh($row['last_error'] ?: ($row['status'] === 'manual_payment' ? 'Baixa manual em dinheiro na Vindi; venda não criada pelo robô' : 'Concluído sem ocorrências'))?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
      <?php if ($totalPages > 1): ?><div class="bsv-pager"><?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?><a class="<?=$i === $page ? 'active' : ''?>" href="<?=bsvh($pageUrl($i))?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
    <?php endif; ?>
  </div>
</section>
