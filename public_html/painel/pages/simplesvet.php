<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';

if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }

function svh($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$tableExists = false;
$summary = ['total' => 0, 'synced' => 0, 'pending' => 0, 'retry' => 0, 'manual_review' => 0];
$errors = [];
$lastReport = null;
try {
    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='simplesvet_sync_status'");
    $tableExists = (int)$check->fetchColumn() > 0;
    if ($tableExists) {
        foreach ($pdo->query('SELECT status, COUNT(*) total FROM simplesvet_sync_status GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string)$row['status'];
            $total = (int)$row['total'];
            $summary['total'] += $total;
            if (array_key_exists($status, $summary)) $summary[$status] = $total;
        }
        $errors = $pdo->query("
            SELECT customer_id, customer_name, status, attempts, last_error, last_attempt_at
              FROM simplesvet_sync_status
             WHERE status IN ('retry', 'manual_review')
             ORDER BY CASE status WHEN 'manual_review' THEN 0 ELSE 1 END, last_attempt_at DESC, customer_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $lastReport = $pdo->query('SELECT MAX(received_at) FROM simplesvet_sync_status')->fetchColumn() ?: null;
    }
} catch (Throwable $e) { $tableExists = false; }

$formatDate = static function ($value): string {
    if (!$value) return '-';
    $timestamp = strtotime((string)$value);
    return $timestamp === false ? '-' : date('d/m/Y H:i', $timestamp);
};
?>
<section class="sv-page">
  <style>
    .sv-page{font-family:'Nunito',sans-serif;color:#172033;max-width:1180px;margin:0 auto;padding:4px 24px 40px}
    .sv-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;margin-bottom:24px}.sv-head h1{font-size:28px;margin:0 0 6px}.sv-head p{margin:0;color:#68758b}
    .sv-refresh{border:0;border-radius:12px;padding:11px 17px;background:#38b6ff;color:#fff;font-weight:800;cursor:pointer}
    .sv-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}.sv-card{background:#fff;border:1px solid #e7edf5;border-radius:18px;padding:19px;box-shadow:0 5px 18px rgba(28,48,78,.06)}
    .sv-card span{display:block;color:#718096;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.sv-card strong{display:block;font-size:29px;margin-top:5px}.sv-card.ok strong{color:#138a5b}.sv-card.err strong{color:#c24141}.sv-card.warn strong{color:#b7791f}
    .sv-panel{background:#fff;border:1px solid #e7edf5;border-radius:18px;box-shadow:0 5px 18px rgba(28,48,78,.06);overflow:hidden}.sv-panel-head{padding:18px 20px;border-bottom:1px solid #edf1f6;display:flex;justify-content:space-between;gap:14px;align-items:center}.sv-panel-head h2{font-size:18px;margin:0}.sv-updated{font-size:12px;color:#718096}
    .sv-table-wrap{overflow-x:auto}.sv-table{width:100%;border-collapse:collapse}.sv-table th,.sv-table td{padding:14px 18px;text-align:left;border-bottom:1px solid #edf1f6;vertical-align:top}.sv-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#718096;background:#f8fafc}.sv-table td{font-size:13px}
    .sv-name{font-weight:800}.sv-id{font-size:11px;color:#8793a7;margin-top:3px}.sv-reason{max-width:510px;white-space:normal;color:#5c6678}.sv-badge{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:900}.sv-badge.retry{background:#fff3cd;color:#8a6300}.sv-badge.manual_review{background:#fee2e2;color:#991b1b}
    .sv-empty{padding:42px 22px;text-align:center;color:#68758b}.sv-empty i{display:block;font-size:38px;color:#21a56f;margin-bottom:10px}
    @media(max-width:800px){.sv-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.sv-head{align-items:flex-start;flex-direction:column}.sv-page{padding-left:14px;padding-right:14px}}
  </style>
  <div class="sv-head"><div><h1>Sincronização SimplesVet</h1><p>Falhas da marcação automática Vindi → SimplesVet.</p></div><button class="sv-refresh" type="button" onclick="location.reload()"><i class="fa-solid fa-rotate"></i> Atualizar</button></div>
  <div class="sv-cards">
    <div class="sv-card"><span>Total</span><strong><?=svh($summary['total'])?></strong></div>
    <div class="sv-card ok"><span>Sucesso</span><strong><?=svh($summary['synced'])?></strong></div>
    <div class="sv-card warn"><span>Nova tentativa</span><strong><?=svh($summary['retry'])?></strong></div>
    <div class="sv-card err"><span>Revisão manual</span><strong><?=svh($summary['manual_review'])?></strong></div>
  </div>
  <div class="sv-panel">
    <div class="sv-panel-head"><h2>Registros com erro</h2><span class="sv-updated">Última atualização: <?=svh($formatDate($lastReport))?></span></div>
    <?php if (!$tableExists): ?>
      <div class="sv-empty"><i class="fa-solid fa-cloud-arrow-up"></i>O primeiro relatório da VPS ainda não foi recebido.</div>
    <?php elseif (!$errors): ?>
      <div class="sv-empty"><i class="fa-solid fa-circle-check"></i>Nenhum erro ativo na sincronização.</div>
    <?php else: ?>
      <div class="sv-table-wrap"><table class="sv-table"><thead><tr><th>Cliente</th><th>Status</th><th>Tentativas</th><th>Última tentativa</th><th>Motivo</th></tr></thead><tbody>
      <?php foreach ($errors as $row): ?><tr>
        <td><div class="sv-name"><?=svh($row['customer_name'])?></div><div class="sv-id">Vindi #<?=svh($row['customer_id'])?></div></td>
        <td><span class="sv-badge <?=svh($row['status'])?>"><?= $row['status'] === 'manual_review' ? 'Revisão manual' : 'Nova tentativa' ?></span></td>
        <td><?=svh($row['attempts'])?> / 5</td><td><?=svh($formatDate($row['last_attempt_at']))?></td><td class="sv-reason"><?=svh($row['last_error'] ?: 'Erro não informado')?></td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
</section>
