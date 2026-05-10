<?php
date_default_timezone_set('America/Sao_Paulo');

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
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException("POST obrigatório");

  $billId = (int)($_POST['bill_id'] ?? 0);
  $action = trim((string)($_POST['action'] ?? ''));

  if ($billId <= 0) throw new RuntimeException("bill_id inválido");
  if (!in_array($action, ['pause','resume','send_now'], true)) throw new RuntimeException("action inválida");

  $ROOT = findRootWithFiles(['config.php', 'db.php']);
  require_once $ROOT . '/config.php';
  require_once $ROOT . '/db.php';

  if ($action === 'pause') {
    $st = $pdo->prepare("
      UPDATE bill_reminders
      SET blocked=1, status='blocked', next_reminder_at=NULL
      WHERE bill_id=?
      LIMIT 1
    ");
    $st->execute([$billId]);
  }

  if ($action === 'resume') {
    $st = $pdo->prepare("
      UPDATE bill_reminders
      SET blocked=0, active=1, status='unpaid', next_reminder_at=NOW()
      WHERE bill_id=?
      LIMIT 1
    ");
    $st->execute([$billId]);
  }

  if ($action === 'send_now') {
    $st = $pdo->prepare("
      UPDATE bill_reminders
      SET next_reminder_at=NOW()
      WHERE bill_id=? AND active=1 AND blocked=0 AND status='unpaid'
      LIMIT 1
    ");
    $st->execute([$billId]);
  }

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}