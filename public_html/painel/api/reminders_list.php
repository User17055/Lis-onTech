<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

$status  = $_GET['status'] ?? 'unpaid';   // unpaid | paid | canceled | blocked
$blocked = $_GET['blocked'] ?? null;      // 0 | 1 | null
$active  = $_GET['active'] ?? null;       // 0 | 1 | null

$start = $_GET['start'] ?? null;          // YYYY-MM-DD
$end   = $_GET['end'] ?? null;            // YYYY-MM-DD

$where = [];
$params = [];

if ($status !== '') {
  $where[] = "status = ?";
  $params[] = $status;
}

if ($blocked !== null && $blocked !== '') {
  $where[] = "blocked = ?";
  $params[] = (int)$blocked;
}

if ($active !== null && $active !== '') {
  $where[] = "active = ?";
  $params[] = (int)$active;
}

if ($start && $end) {
  $where[] = "DATE(due_at) BETWEEN ? AND ?";
  $params[] = $start;
  $params[] = $end;
}

$sql = "
  SELECT
    bill_id, customer_id, customer_name, phone,
    amount, due_at,
    bill_url, items_text,
    active, blocked, status,
    reminder_count, last_reminder_at, next_reminder_at,
    created_sent_at
  FROM bill_reminders
";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY due_at DESC, bill_id DESC LIMIT 500";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
