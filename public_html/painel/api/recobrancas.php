<?php
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/_auth.php';

function findRootWithFiles(array $files): string {
  $dir = __DIR__;
  for ($i = 0; $i < 10; $i++) {
    $ok = true;
    foreach ($files as $f) {
      if (!file_exists($dir . '/' . $f)) { $ok = false; break; }
    }
    if ($ok) return $dir;
    $dir = dirname($dir);
  }
  throw new RuntimeException("Raiz não encontrada");
}

header('Content-Type: application/json; charset=utf-8');

try {
  $ROOT = findRootWithFiles(['config.php', 'db.php']);
  require_once $ROOT . '/config.php';
  require_once $ROOT . '/db.php';

  $q      = trim((string)($_GET['q'] ?? ''));
  $status = trim((string)($_GET['status'] ?? ''));
  $onlyOverdue = (string)($_GET['only_overdue'] ?? '1') !== '0';

  $page  = max(1, (int)($_GET['page'] ?? 1));
  $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
  $off   = ($page - 1) * $limit;

  $where = [];
  $params = [];

  // padrão: só o que está devendo e ativo
  $where[] = "active = 1";

  if ($onlyOverdue) $where[] = "due_at IS NOT NULL AND due_at < NOW()";

  if ($status !== '') {
    $where[] = "status = :status";
    $params[':status'] = $status;
  }

  if ($q !== '') {
    $where[] = "(customer_name LIKE :q_customer_name OR phone LIKE :q_phone OR CAST(bill_id AS CHAR) LIKE :q_bill_id OR CAST(customer_id AS CHAR) LIKE :q_customer_id)";
    $qLike = "%" . $q . "%";
    $params[':q_customer_name'] = $qLike;
    $params[':q_phone'] = $qLike;
    $params[':q_bill_id'] = $qLike;
    $params[':q_customer_id'] = $qLike;
  }

  $whereSql = implode(" AND ", $where);

  $st = $pdo->prepare("SELECT COUNT(*) FROM bill_reminders WHERE {$whereSql}");
  $st->execute($params);
  $total = (int)$st->fetchColumn();

  $totalPages = (int)ceil($total / $limit);

  $sql = "
    SELECT
      bill_id, customer_id, customer_name, phone, bill_url, items_text,
      amount, due_at, active, blocked, status,
      reminder_count, overdue_sent_count, reminder_attempts,
      last_overdue_sent_at, last_reminder_sent_at, next_reminder_at,
      last_status, last_status_check_at, updated_at
    FROM bill_reminders
    WHERE {$whereSql}
    ORDER BY due_at DESC, updated_at DESC
    LIMIT {$limit} OFFSET {$off}
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  // calcula dias em atraso
  foreach ($rows as &$r) {
    $r['days_overdue'] = null;
    if (!empty($r['due_at'])) {
      $d = (int)floor((time() - strtotime((string)$r['due_at'])) / 86400);
      $r['days_overdue'] = $d >= 0 ? $d : 0;
    }
  }
  unset($r);

  echo json_encode([
    'ok' => true,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'total_pages' => max(1, $totalPages),
    'rows' => $rows
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(200);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
