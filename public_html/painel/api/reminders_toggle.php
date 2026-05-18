<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

$billId = (int)($_POST['bill_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

$INTERVAL_DAYS = (int) cfg($cfg, 'REMINDERS_INTERVAL_DAYS', '7');

if ($billId <= 0) {
  echo json_encode(['ok'=>false,'msg'=>'bill_id invalido']);
  exit;
}

$st = $pdo->prepare("SELECT status, last_status FROM bill_reminders WHERE bill_id=? LIMIT 1");
$st->execute([$billId]);
$row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
$status = strtolower(trim((string)($row['status'] ?? '')));
$lastStatus = strtolower(trim((string)($row['last_status'] ?? '')));

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
  if (in_array($status, ['paid', 'canceled', 'cancelled'], true) || in_array($lastStatus, ['paid', 'canceled', 'cancelled'], true)) {
    echo json_encode(['ok'=>false,'msg'=>'Fatura paga ou cancelada nao pode ser desbloqueada para cobranca']);
    exit;
  }

  $pdo->prepare("
    UPDATE bill_reminders
    SET blocked=0, status='unpaid', active=1,
        next_reminder_at = DATE_ADD(NOW(), INTERVAL {$INTERVAL_DAYS} DAY)
    WHERE bill_id=?
  ")->execute([$billId]);

  echo json_encode(['ok'=>true,'msg'=>'Desbloqueado']);
  exit;
}

echo json_encode(['ok'=>false,'msg'=>'action invalida']);
