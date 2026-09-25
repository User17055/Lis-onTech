<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';

if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }

function svh($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function svFriendlyError(string $error): string
{
    if (stripos($error, 'CPF ausente ou invalido') !== false) return 'CPF ausente ou inválido na Vindi.';
    if (stripos($error, "locator('tr.linhaRegistro')") !== false) return 'Cliente não encontrado no SimplesVet pelo CPF informado.';
    if (stripos($error, 'login') !== false || stripos($error, 'session') !== false) return 'Não foi possível entrar no SimplesVet. O sistema tentará novamente.';
    if (stripos($error, 'Timeout') !== false) return 'O SimplesVet demorou para responder. O sistema tentará novamente.';
    return 'Não foi possível concluir esta alteração. O sistema tentará novamente.';
}

$tableExists = false;
$summary = ['total' => 0, 'synced' => 0, 'pending' => 0, 'retry' => 0, 'manual_review' => 0];
$actions = ['ADD' => 0, 'REMOVE' => 0, 'VERIFIED' => 0];
$errors = [];
$successes = [];
$dailyStats = [];
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
        $historyCheck = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='simplesvet_sync_history'");
        $historyExists = (int)$historyCheck->fetchColumn() > 0;
        if ($historyExists) {
            foreach ($pdo->query("SELECT action, COUNT(*) total FROM simplesvet_sync_history WHERE DATE(occurred_at)=CURDATE() GROUP BY action")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $action = (string)$row['action'];
                if (array_key_exists($action, $actions)) $actions[$action] = (int)$row['total'];
            }
            $successes = $pdo->query("
                SELECT customer_id, customer_name, action AS last_action, occurred_at AS synced_at
                  FROM simplesvet_sync_history
                 WHERE action IN ('ADD', 'REMOVE', 'VERIFIED')
                 ORDER BY occurred_at DESC, id DESC
                 LIMIT 2000
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $dailyStats = $pdo->query("
                SELECT DATE(occurred_at) stat_date,
                       COUNT(*) success_count,
                       SUM(action='ADD') add_count,
                       SUM(action='REMOVE') remove_count,
                       SUM(action='VERIFIED') verified_count
                  FROM simplesvet_sync_history
                 WHERE occurred_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                 GROUP BY DATE(occurred_at)
                 ORDER BY stat_date DESC
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            foreach ($pdo->query("SELECT last_action, COUNT(*) total FROM simplesvet_sync_status WHERE status='synced' AND DATE(synced_at)=CURDATE() GROUP BY last_action")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $action = (string)$row['last_action'];
                if (array_key_exists($action, $actions)) $actions[$action] = (int)$row['total'];
            }
            $successes = $pdo->query("
                SELECT customer_id, customer_name, last_action, synced_at
                  FROM simplesvet_sync_status
                 WHERE status='synced' AND last_action IN ('ADD', 'REMOVE', 'VERIFIED')
                 ORDER BY synced_at DESC, customer_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

$summary['synced'] = array_sum($actions);

$formatDate = static function ($value): string {
    if (!$value) return '-';
    $timestamp = strtotime((string)$value);
    return $timestamp === false ? '-' : date('d/m/Y H:i', $timestamp);
};
$formatDay = static function ($value): string {
    if (!$value) return '-';
    $timestamp = strtotime((string)$value);
    return $timestamp === false ? '-' : date('d/m/Y', $timestamp);
};

$panelTimezone = new DateTimeZone('America/Sao_Paulo');
$now = new DateTimeImmutable('now', $panelTimezone);
$nextRun = $now->setTime((int)$now->format('H'), 0);
if ($nextRun <= $now) $nextRun = $nextRun->modify('+1 hour');
while (((int)$nextRun->format('H')) % 2 !== 0) $nextRun = $nextRun->modify('+1 hour');
$nextRunLabel = $nextRun->format('d/m/Y') . ' às ' . $nextRun->format('H:i');
?>
<section class="sv-page">
  <style>
    .sv-page{font-family:'Nunito',sans-serif;color:#172033;max-width:1180px;margin:0 auto;padding:4px 24px 40px}
    .sv-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;margin-bottom:24px}.sv-head h1{font-size:28px;margin:0 0 6px}.sv-head p{margin:0;color:#68758b}
    .sv-refresh{border:0;border-radius:12px;padding:11px 17px;background:#38b6ff;color:#fff;font-weight:800;cursor:pointer}
    .sv-next{display:flex;align-items:center;gap:12px;margin:-8px 0 20px;padding:14px 17px;border:1px solid #cfe9ff;border-radius:15px;background:#eef8ff;color:#245477}.sv-next i{width:35px;height:35px;border-radius:11px;background:#38b6ff;color:#fff;display:grid;place-items:center}.sv-next strong{display:block;color:#123b5c}.sv-next span{font-size:12px;color:#5e7890}
    .sv-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:14px;margin-bottom:22px}.sv-card{background:#fff;border:1px solid #e7edf5;border-radius:18px;padding:19px;box-shadow:0 5px 18px rgba(28,48,78,.06);font:inherit;color:inherit;text-align:left}.sv-card.is-clickable{cursor:pointer;transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease}.sv-card.is-clickable:hover,.sv-card.is-clickable:focus-visible{transform:translateY(-2px);border-color:#8ed7ff;box-shadow:0 9px 24px rgba(28,48,78,.12);outline:none}
    .sv-card span{display:block;color:#718096;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.sv-card strong{display:block;font-size:29px;margin-top:5px}.sv-card.ok strong{color:#138a5b}.sv-card.err strong{color:#c24141}.sv-card.warn strong{color:#b7791f}
    .sv-panel{background:#fff;border:1px solid #e7edf5;border-radius:18px;box-shadow:0 5px 18px rgba(28,48,78,.06);overflow:hidden}.sv-panel+.sv-panel{margin-top:22px}.sv-panel-head{padding:18px 20px;border-bottom:1px solid #edf1f6;display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap}.sv-panel-head h2{font-size:18px;margin:0}.sv-updated{font-size:12px;color:#718096}
    .sv-table-wrap{overflow-x:auto}.sv-table{width:100%;border-collapse:collapse}.sv-table th,.sv-table td{padding:14px 18px;text-align:left;border-bottom:1px solid #edf1f6;vertical-align:top}.sv-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#718096;background:#f8fafc}.sv-table td{font-size:13px}
    .sv-name{font-weight:800;overflow-wrap:anywhere}.sv-id{font-size:11px;color:#8793a7;margin-top:3px}.sv-reason{min-width:0;white-space:normal;color:#5c6678}.sv-badge{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:11px;line-height:1;font-weight:900;white-space:nowrap}.sv-badge.retry{background:#fff3cd;color:#8a6300}.sv-badge.manual_review{background:#fee2e2;color:#991b1b}.sv-badge.ADD{background:#d1fae5;color:#06603f}.sv-badge.REMOVE{background:#dbeafe;color:#1e40af}.sv-badge.VERIFIED{background:#fef3c7;color:#8a5b00}
    .sv-tools{display:flex;gap:8px;flex-wrap:wrap}.sv-tools input,.sv-tools select{border:1px solid #dce4ee;border-radius:10px;background:#fff;padding:9px 11px;font:inherit;font-size:12px;color:#344054}.sv-success-list{max-height:620px;overflow:auto}.sv-success-list thead th{position:sticky;top:0;z-index:1}
    .sv-day-list{display:grid;gap:10px;padding:16px}.sv-day{display:grid;grid-template-columns:minmax(100px,1.4fr) repeat(4,minmax(80px,1fr));gap:10px;align-items:center;padding:13px 15px;border:1px solid #edf1f6;border-radius:13px}.sv-day strong{font-size:13px}.sv-day-metric span{display:block;color:#98a2b3;font-size:9px;font-weight:900;text-transform:uppercase}.sv-day-metric b{font-size:16px;color:#344054}
    .sv-errors-list{display:grid;gap:12px;padding:16px}.sv-error-card{display:grid;grid-template-columns:minmax(210px,1.4fr) auto auto minmax(135px,.7fr);gap:16px;align-items:center;padding:17px;border:1px solid #e7edf5;border-radius:15px;background:#fff}.sv-error-cell{min-width:0}.sv-error-label{display:block;margin-bottom:6px;color:#98a2b3;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}.sv-error-reason{grid-column:1/-1;padding-top:14px;border-top:1px solid #edf1f6}.sv-error-message{display:flex;gap:10px;align-items:flex-start;min-width:0}.sv-error-icon{width:30px;height:30px;border-radius:10px;background:#fff1f1;color:#c24141;display:grid;place-items:center;flex:0 0 auto}.sv-error-title{font-weight:800;color:#3c4658;line-height:1.35;overflow-wrap:anywhere}.sv-error-details{margin-top:7px;color:#7a8699;font-size:11px;max-width:100%}.sv-error-details summary{cursor:pointer;font-weight:800;color:#667085}.sv-error-details div{box-sizing:border-box;margin-top:7px;padding:9px 11px;border-radius:9px;background:#f7f9fc;max-width:100%;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word}.sv-attempt{font-weight:800;color:#5b6577;white-space:nowrap}.sv-attempt small{display:block;margin-top:3px;color:#98a2b3;font-weight:700}
    .sv-empty{padding:42px 22px;text-align:center;color:#68758b}.sv-empty i{display:block;font-size:38px;color:#21a56f;margin-bottom:10px}
    @media(max-width:900px){.sv-error-card{grid-template-columns:1fr 1fr}.sv-error-reason{grid-column:1/-1}}
    @media(max-width:800px){.sv-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.sv-head{align-items:flex-start;flex-direction:column}.sv-page{padding-left:14px;padding-right:14px}}
    @media(max-width:520px){.sv-error-card{grid-template-columns:1fr}.sv-error-reason{grid-column:1}.sv-next{align-items:flex-start}.sv-day{grid-template-columns:1fr 1fr}.sv-day>strong{grid-column:1/-1}}
  </style>
  <div class="sv-head"><div><h1>Sincronização SimplesVet</h1><p>Falhas da marcação automática Vindi → SimplesVet.</p></div><button class="sv-refresh" type="button" onclick="location.reload()"><i class="fa-solid fa-rotate"></i> Atualizar</button></div>
  <div class="sv-next">
    <i class="fa-solid fa-clock"></i>
    <div><strong>Próxima verificação: <?=svh($nextRunLabel)?></strong><span>Execução automática a cada 2 horas, no horário de São Paulo.</span></div>
  </div>
  <div class="sv-cards">
    <div class="sv-card"><span>Total</span><strong><?=svh($summary['total'])?></strong></div>
    <button class="sv-card ok is-clickable" type="button" data-sv-filter="" data-sv-today="<?=$now->format('Y-m-d')?>"><span>Sucesso hoje</span><strong><?=svh($summary['synced'])?></strong></button>
    <button class="sv-card ok is-clickable" type="button" data-sv-filter="ADD" data-sv-today="<?=$now->format('Y-m-d')?>"><span>Acrescentados hoje</span><strong><?=svh($actions['ADD'])?></strong></button>
    <button class="sv-card is-clickable" type="button" data-sv-filter="REMOVE" data-sv-today="<?=$now->format('Y-m-d')?>"><span>Retirados hoje</span><strong><?=svh($actions['REMOVE'])?></strong></button>
    <button class="sv-card warn is-clickable" type="button" data-sv-filter="VERIFIED" data-sv-today="<?=$now->format('Y-m-d')?>"><span>Verificados hoje</span><strong><?=svh($actions['VERIFIED'])?></strong></button>
    <div class="sv-card err"><span>Revisão manual</span><strong><?=svh($summary['manual_review'])?></strong></div>
  </div>
  <div class="sv-panel">
    <div class="sv-panel-head"><h2>Registros com erro</h2><span class="sv-updated">Última atualização: <?=svh($formatDate($lastReport))?></span></div>
    <?php if (!$tableExists): ?>
      <div class="sv-empty"><i class="fa-solid fa-cloud-arrow-up"></i>O primeiro relatório da VPS ainda não foi recebido.</div>
    <?php elseif (!$errors): ?>
      <div class="sv-empty"><i class="fa-solid fa-circle-check"></i>Nenhum erro ativo na sincronização.</div>
    <?php else: ?>
      <div class="sv-errors-list">
      <?php foreach ($errors as $row): ?>
        <article class="sv-error-card">
          <div class="sv-error-cell"><span class="sv-error-label">Cliente</span><div class="sv-name"><?=svh($row['customer_name'])?></div><div class="sv-id">Vindi #<?=svh($row['customer_id'])?></div></div>
          <div class="sv-error-cell"><span class="sv-error-label">Status</span><span class="sv-badge <?=svh($row['status'])?>"><?= $row['status'] === 'manual_review' ? 'Revisão manual' : 'Aguardando verificação' ?></span></div>
          <div class="sv-error-cell"><span class="sv-error-label">Tentativas</span><div class="sv-attempt"><?=svh($row['attempts'])?> de 5</div></div>
          <div class="sv-error-cell"><span class="sv-error-label">Última tentativa</span><?=svh($formatDate($row['last_attempt_at']))?></div>
          <div class="sv-error-reason">
            <span class="sv-error-label">Motivo</span>
            <div class="sv-error-message">
              <span class="sv-error-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
              <div class="sv-reason">
                <div class="sv-error-title"><?=svh(svFriendlyError((string)($row['last_error'] ?? '')))?></div>
                <details class="sv-error-details"><summary>Ver detalhe técnico</summary><div><?=svh($row['last_error'] ?: 'Erro não informado')?></div></details>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="sv-panel" id="svCompletedPanel">
    <div class="sv-panel-head">
      <h2>Alterações concluídas</h2>
      <div class="sv-tools">
        <input id="svSuccessSearch" type="search" placeholder="Buscar cliente ou ID" aria-label="Buscar cliente">
        <select id="svActionFilter" aria-label="Filtrar ação">
          <option value="">Todas as ações</option>
          <option value="ADD">Acrescentados</option>
          <option value="REMOVE">Retirados</option>
          <option value="VERIFIED">Verificados</option>
        </select>
      </div>
    </div>
    <?php if (!$tableExists || !$successes): ?>
      <div class="sv-empty">Nenhuma alteração concluída foi recebida.</div>
    <?php else: ?>
      <div class="sv-table-wrap sv-success-list"><table class="sv-table">
        <thead><tr><th>Cliente</th><th>Ação</th><th>Confirmado em</th></tr></thead>
        <tbody id="svSuccessBody">
        <?php foreach ($successes as $row): ?>
          <tr data-action="<?=svh($row['last_action'])?>" data-date="<?=svh(date('Y-m-d', strtotime((string)$row['synced_at'])))?>" data-search="<?=svh(mb_strtolower($row['customer_name'] . ' ' . $row['customer_id'], 'UTF-8'))?>">
            <td><div class="sv-name"><?=svh($row['customer_name'])?></div><div class="sv-id">Vindi #<?=svh($row['customer_id'])?></div></td>
            <td><span class="sv-badge <?=svh($row['last_action'])?>"><?php
              if ($row['last_action'] === 'REMOVE') echo 'Retirado';
              elseif ($row['last_action'] === 'VERIFIED') echo 'Verificado';
              else echo 'Acrescentado';
            ?></span></td>
            <td><?=svh($formatDate($row['synced_at']))?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <div class="sv-panel">
    <div class="sv-panel-head"><h2>Estatísticas por dia</h2><span class="sv-updated">Últimos 14 dias</span></div>
    <?php if (!$dailyStats): ?>
      <div class="sv-empty">As estatísticas diárias aparecerão após o próximo relatório.</div>
    <?php else: ?>
      <div class="sv-day-list">
      <?php foreach ($dailyStats as $day): ?>
        <div class="sv-day">
          <strong><?=svh($formatDay($day['stat_date']))?></strong>
          <div class="sv-day-metric"><span>Sucesso</span><b><?=svh($day['success_count'])?></b></div>
          <div class="sv-day-metric"><span>Acrescentados</span><b><?=svh($day['add_count'])?></b></div>
          <div class="sv-day-metric"><span>Retirados</span><b><?=svh($day['remove_count'])?></b></div>
          <div class="sv-day-metric"><span>Verificados</span><b><?=svh($day['verified_count'])?></b></div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<script>
(() => {
  const search = document.getElementById('svSuccessSearch');
  const action = document.getElementById('svActionFilter');
  const body = document.getElementById('svSuccessBody');
  if (!search || !action || !body) return;
  let selectedDay = '';
  const filter = () => {
    const term = search.value.trim().toLocaleLowerCase('pt-BR');
    const selected = action.value;
    body.querySelectorAll('tr').forEach((row) => {
      const matchesTerm = !term || (row.dataset.search || '').includes(term);
      const matchesAction = !selected || row.dataset.action === selected;
      const matchesDay = !selectedDay || row.dataset.date === selectedDay;
      row.hidden = !(matchesTerm && matchesAction && matchesDay);
    });
  };
  search.addEventListener('input', filter);
  action.addEventListener('change', () => { selectedDay = ''; filter(); });
  document.querySelectorAll('[data-sv-filter]').forEach((card) => {
    card.addEventListener('click', () => {
      action.value = card.dataset.svFilter || '';
      selectedDay = card.dataset.svToday || '';
      filter();
      document.getElementById('svCompletedPanel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
})();
</script>
