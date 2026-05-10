<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

$billId = (int)($_POST['bill_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

$INTERVAL_DAYS = (int) cfg($cfg, 'REMINDERS_INTERVAL_DAYS', '7');

if ($billId <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'bill_id inválido']);
  exit;
}

if ($action === 'block') {
  $pdo->prepare("
    UPDATE bill_reminders
    SET blocked=1, status='blocked', active=0
    WHERE bill_id=?
  ")->execute([$billId]);

  echo json_encode(['ok'=>true,'msg'=>'Bloqueado']);
  exit;
}

if ($action === 'unblock') {
  $pdo->prepare("
    UPDATE bill_reminders
    SET blocked=0, status='unpaid', active=1,
        next_reminder_at = DATE_ADD(NOW(), INTERVAL {$INTERVAL_DAYS} DAY)
    WHERE bill_id=?
  ")->execute([$billId]);

  echo json_encode(['ok'=>true,'msg'=>'Desbloqueado']);
  exit;
}

echo json_encode(['ok'=>false,'msg'=>'action inválida']);
