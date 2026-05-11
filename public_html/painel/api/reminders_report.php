<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

$start = $_GET['start'] ?? null; // YYYY-MM-DD
$end   = $_GET['end'] ?? null;   // YYYY-MM-DD

if (!$start || !$end) {
  echo json_encode(['ok'=>false,'msg'=>'Informe start e end (YYYY-MM-DD)']);
  exit;
}

$st = $pdo->prepare("
  SELECT
    bill_id,
    customer_name,
    phone,
    amount,
    due_at,
    reminder_count,
    last_reminder_at,
    next_reminder_at
  FROM bill_reminders
  WHERE status='unpaid'
    AND blocked=0
    AND due_at IS NOT NULL
    AND DATE(due_at) BETWEEN ? AND ?
  ORDER BY due_at ASC
");
$st->execute([$start, $end]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($rows as $r) $total += (float)($r['amount'] ?? 0);

echo json_encode([
  'ok'=>true,
  'count'=>count($rows),
  'total'=>$total,
  'data'=>$rows
], JSON_UNESCAPED_UNICODE);
