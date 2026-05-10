<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

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
  throw new RuntimeException('Raiz nao encontrada');
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new RuntimeException('POST obrigatorio');
  }

  $billId = (int)($_POST['bill_id'] ?? 0);
  $action = trim((string)($_POST['action'] ?? ''));

  if ($billId <= 0) throw new RuntimeException('bill_id invalido');
  if (!in_array($action, ['pause', 'resume', 'send_now'], true)) {
    throw new RuntimeException('action invalida');
  }

  $ROOT = findRootWithFiles(['config.php', 'db.php']);
  require_once $ROOT . '/config.php';
  require_once $ROOT . '/db.php';

  if ($action === 'pause') {
    $st = $pdo->prepare("
      INSERT INTO bill_reminders (bill_id, active, blocked, status, next_reminder_at)
      VALUES (?, 1, 1, 'blocked', NULL)
      ON DUPLICATE KEY UPDATE
        active=1,
        blocked=1,
        status='blocked',
        next_reminder_at=NULL
    ");
  } elseif ($action === 'resume') {
    $st = $pdo->prepare("
      INSERT INTO bill_reminders (bill_id, active, blocked, status, next_reminder_at)
      VALUES (?, 1, 0, 'unpaid', NOW())
      ON DUPLICATE KEY UPDATE
        active=1,
        blocked=0,
        status='unpaid',
        next_reminder_at=NOW()
    ");
  } else {
    $st = $pdo->prepare("
      INSERT INTO bill_reminders (bill_id, active, blocked, status, next_reminder_at)
      VALUES (?, 1, 0, 'unpaid', NOW())
      ON DUPLICATE KEY UPDATE
        active=1,
        blocked=0,
        status='unpaid',
        next_reminder_at=NOW()
    ");
  }

  $st->execute([$billId]);

  echo json_encode([
    'ok' => true,
    'action' => $action,
    'bill_id' => $billId,
    'changed' => $st->rowCount(),
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
