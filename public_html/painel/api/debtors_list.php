<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../db.php';
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Falha ao carregar includes: ' . $e->getMessage()]);
    exit;
}

function out(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$limit  = (int)($_GET['limit'] ?? 50);
$limit  = max(1, min(200, $limit));

$q      = trim((string)($_GET['q'] ?? ''));
$filter = trim((string)($_GET['filter'] ?? '')); // ready | waiting | all

$params = [];
$where  = " WHERE br.active = 1 ";

if ($q !== '') {
    if (ctype_digit($q)) {
        $where .= " AND (br.customer_id = :qid OR br.bill_id = :qid OR ar.customer_name LIKE :q) ";
        $params[':qid'] = (int)$q;
        $params[':q']   = '%' . $q . '%';
    } else {
        $where .= " AND (ar.customer_name LIKE :q) ";
        $params[':q'] = '%' . $q . '%';
    }
}

/**
 * Tentamos a query completa (com weekly_last_sent_at).
 * Se sua tabela ainda não tiver essas colunas, cai no fallback automaticamente.
 */
$sqlMain = "
SELECT
  br.customer_id,
  COALESCE(MAX(ar.customer_name), 'Cliente') AS customer_name,
  COUNT(*) AS open_bills,
  MIN(br.created_sent_at) AS first_sent_at,
  MAX(brOALESCE(br.weekly_last_sent_at, NULL)) AS last_weekly_sent_at,
  TIMESTAMPDIFF(DAY, MIN(br.created_sent_at), NOW()) AS days_open,
  CASE
    WHEN MIN(br.created_sent_at) <= DATE_SUB(NOW(), INTERVAL 7 DAY)
     AND (MAX(br.weekly_last_sent_at) IS NULL OR MAX(br.weekly_last_sent_at) <= DATE_SUB(NOW(), INTERVAL 7 DAY))
    THEN 1 ELSE 0
  END AS ready_weekly,
  GROUP_CONCAT(br.bill_id ORDER BY br.created_sent_at ASC SEPARATOR ', ') AS bill_ids
FROM bill_reminders br
LEFT JOIN (
    SELECT bill_id, MAX(created_at) AS last_created
    FROM automation_runs
    WHERE bill_id IS NOT NULL
    GROUP BY bill_id
) arx ON arx.bill_id = br.bill_id
LEFT JOIN automation_runs ar
  ON ar.bill_id = arx.bill_id AND ar.created_at = arx.last_created
{$where}
GROUP BY br.customer_id
";

$having = "";
if ($filter === 'ready') {
    $having = " HAVING ready_weekly = 1 ";
} elseif ($filter === 'waiting') {
    $having = " HAVING ready_weekly = 0 ";
}

$sqlMain .= $having . " ORDER BY ready_weekly DESC, days_open DESC LIMIT {$limit} ";

$sqlFallback = "
SELECT
  br.customer_id,
  COALESCE(MAX(ar.customer_name), 'Cliente') AS customer_name,
  COUNT(*) AS open_bills,
  MIN(br.created_sent_at) AS first_sent_at,
  NULL AS last_weekly_sent_at,
  TIMESTAMPDIFF(DAY, MIN(br.created_sent_at), NOW()) AS days_open,
  CASE
    WHEN MIN(br.created_sent_at) <= DATE_SUB(NOW(), INTERVAL 7 DAY)
    THEN 1 ELSE 0
  END AS ready_weekly,
  GROUP_CONCAT(br.bill_id ORDER BY br.created_sent_at ASC SEPARATOR ', ') AS bill_ids
FROM bill_reminders br
LEFT JOIN (
    SELECT bill_id, MAX(created_at) AS last_created
    FROM automation_runs
    WHERE bill_id IS NOT NULL
    GROUP BY bill_id
) arx ON arx.bill_id = br.bill_id
LEFT JOIN automation_runs ar
  ON ar.bill_id = arx.bill_id AND ar.created_at = arx.last_created
{$where}
GROUP BY br.customer_id
";

$sqlFallback .= $having . " ORDER BY ready_weekly DESC, days_open DESC LIMIT {$limit} ";

try {
    $st = $pdo->prepare($sqlMain);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    out(['ok' => true, 'rows' => $rows]);
} catch (Throwable $e) {
    // fallback se coluna weekly_last_sent_at não existir ainda
    try {
        $st = $pdo->prepare($sqlFallback);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        out(['ok' => true, 'rows' => $rows, 'warning' => 'Fallback ativo (sem weekly_last_sent_at).']);
    } catch (Throwable $e2) {
        out(['ok' => false, 'error' => $e2->getMessage()]);
    }
}
