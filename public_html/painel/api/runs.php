<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

$limit  = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
$page   = isset($_GET['page'])  ? max(1, (int)$_GET['page']) : 1;

$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$q      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$knownSignature = isset($_GET['signature']) ? trim((string)$_GET['signature']) : '';

$where = "WHERE 1=1";
$params = [];
$paidNoSendExpr = "(
  status = 'paid'
  OR (
    status = 'not_sent'
    AND (
      LOWER(COALESCE(error_message, '')) LIKE '%ja paga%'
      OR LOWER(COALESCE(error_message, '')) LIKE '%já paga%'
      OR LOWER(COALESCE(error_message, '')) LIKE '%ja esta paga%'
      OR LOWER(COALESCE(error_message, '')) LIKE '%já está paga%'
    )
  )
)";

if ($status !== '') {
  if ($status === 'paid') {
    $where .= " AND $paidNoSendExpr ";
  } elseif ($status === 'not_sent') {
    $where .= " AND status = :status AND NOT ($paidNoSendExpr) ";
    $params[':status'] = $status;
  } else {
    $where .= " AND status = :status ";
    $params[':status'] = $status;
  }
}

if ($q !== '') {
  $where .= " AND (
    run_id LIKE :q_run_id OR
    customer_name LIKE :q_customer_name OR
    CAST(bill_id AS CHAR) LIKE :q_bill_id OR
    event_type LIKE :q_event_type
  ) ";
  $qLike = '%' . $q . '%';
  $params[':q_run_id'] = $qLike;
  $params[':q_customer_name'] = $qLike;
  $params[':q_bill_id'] = $qLike;
  $params[':q_event_type'] = $qLike;
}

/* 1) resumo leve para total, paginacao e polling sem baixar a lista inteira */
$summarySql = "
  SELECT
    COUNT(*) AS total,
    MAX(updated_at) AS max_updated_at,
    MAX(created_at) AS max_created_at
  FROM automation_runs
  $where
";
$stmt = $pdo->prepare($summarySql);
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$total = (int)($summary['total'] ?? 0);

$totalPages = max(1, (int)ceil($total / $limit));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $limit;
$signature = hash('sha256', implode('|', [
  $page,
  $limit,
  $status,
  $q,
  $total,
  (string)($summary['max_updated_at'] ?? ''),
  (string)($summary['max_created_at'] ?? ''),
]));

if ($knownSignature !== '' && hash_equals($signature, $knownSignature)) {
  echo json_encode([
    'ok' => true,
    'not_modified' => true,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'total_pages' => $totalPages,
    'signature' => $signature,
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* 2) dados */
$dataSql = "
  SELECT
    run_id,
    created_at,
    updated_at,
    event_type,
    CASE WHEN $paidNoSendExpr THEN 'paid' ELSE status END AS status,
    customer_name,
    bill_id,
    bill_url,
    meta_http,
    error_message
  FROM automation_runs
  $where
  ORDER BY created_at DESC
  LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'ok' => true,
  'rows' => $rows,
  'page' => $page,
  'limit' => $limit,
  'total' => $total,
  'total_pages' => $totalPages,
  'signature' => $signature
], JSON_UNESCAPED_UNICODE);
