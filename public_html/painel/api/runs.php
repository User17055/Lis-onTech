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

$where = "WHERE 1=1";
$params = [];

if ($status !== '') {
  $where .= " AND status = :status ";
  $params[':status'] = $status;
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

/* 1) total */
$countSql = "SELECT COUNT(*) FROM automation_runs $where";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$totalPages = max(1, (int)ceil($total / $limit));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $limit;

/* 2) dados */
$dataSql = "
  SELECT
    run_id,
    created_at,
    updated_at,
    event_type,
    status,
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
  'total_pages' => $totalPages
], JSON_UNESCAPED_UNICODE);
