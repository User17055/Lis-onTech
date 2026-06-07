<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

function reportsOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function reportsTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
}

function reportsColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
}

function reportsMoney($value): float
{
    if ($value === null || $value === '') return 0.0;
    if (is_numeric($value)) return (float)$value;
    $s = preg_replace('/[^\d,.\-]/', '', (string)$value) ?? '';
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float)$s : 0.0;
}

function reportsDaysOverdue($value): int
{
    if (!$value) return 0;
    $ts = strtotime((string)$value);
    if (!$ts) return 0;
    return max(0, (int)floor((time() - $ts) / 86400));
}

function reportsCustomerKey(array $row): string
{
    $customerId = (int)($row['customer_id'] ?? 0);
    if ($customerId > 0) return 'id:' . $customerId;
    $phone = trim((string)($row['phone'] ?? ''));
    if ($phone !== '') return 'phone:' . preg_replace('/\D+/', '', $phone);
    return 'name:' . strtolower(trim((string)($row['customer_name'] ?? 'Cliente')));
}

try {
    if (!reportsTableExists($pdo, 'bill_reminders')) {
        reportsOut([
            'ok' => true,
            'summary' => [
                'debtors' => 0,
                'open_bills' => 0,
                'total_amount' => 0,
                'overdue_bills' => 0,
                'reminders_sent' => 0,
                'top_debtor' => null,
            ],
            'rows' => [],
            'warning' => 'Tabela bill_reminders ainda nao existe.',
        ]);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $limit = max(10, min(5000, (int)($_GET['limit'] ?? 1200)));

    $cols = [
        'bill_id',
        'customer_id',
        'customer_name',
        'phone',
        'bill_url',
        'items_text',
        'amount',
        'due_at',
        'status',
        'active',
        'blocked',
        'reminder_count',
        'overdue_sent_count',
        'reminder_attempts',
        'last_overdue_sent_at',
        'last_reminder_sent_at',
        'next_reminder_at',
        'updated_at',
    ];

    $select = [];
    foreach ($cols as $col) {
        $select[] = reportsColumnExists($pdo, 'bill_reminders', $col)
            ? "br.{$col}"
            : "NULL AS {$col}";
    }

    $where = [];
    $params = [];
    if (reportsColumnExists($pdo, 'bill_reminders', 'active')) {
        $where[] = 'COALESCE(br.active, 1) = 1';
    }
    if (reportsColumnExists($pdo, 'bill_reminders', 'status')) {
        $where[] = "LOWER(COALESCE(NULLIF(br.status, ''), 'unpaid')) IN ('unpaid','pending','overdue')";
    }
    if ($q !== '') {
        $search = [];
        foreach (['customer_name', 'phone', 'bill_id', 'customer_id'] as $col) {
            if (reportsColumnExists($pdo, 'bill_reminders', $col)) {
                $search[] = "CAST(br.{$col} AS CHAR) LIKE :q";
            }
        }
        if ($search) {
            $where[] = '(' . implode(' OR ', $search) . ')';
            $params[':q'] = '%' . $q . '%';
        }
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $orderBits = [];
    if (reportsColumnExists($pdo, 'bill_reminders', 'due_at')) $orderBits[] = 'br.due_at ASC';
    if (reportsColumnExists($pdo, 'bill_reminders', 'updated_at')) $orderBits[] = 'br.updated_at DESC';
    $orderSql = $orderBits ? ('ORDER BY ' . implode(', ', $orderBits)) : 'ORDER BY br.bill_id DESC';

    $stmt = $pdo->prepare("
        SELECT " . implode(",\n               ", $select) . "
        FROM bill_reminders br
        {$whereSql}
        {$orderSql}
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $logMap = [];
    if (
        $bills
        && reportsTableExists($pdo, 'reminder_logs')
        && reportsColumnExists($pdo, 'reminder_logs', 'bill_id')
        && reportsColumnExists($pdo, 'reminder_logs', 'ok')
        && reportsColumnExists($pdo, 'reminder_logs', 'created_at')
    ) {
        $ids = [];
        foreach ($bills as $bill) {
            $id = (int)($bill['bill_id'] ?? 0);
            if ($id > 0) $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $logStmt = $pdo->prepare("
                SELECT
                    bill_id,
                    COUNT(CASE WHEN ok = 1 THEN 1 END) AS sent_count,
                    COUNT(*) AS attempt_count,
                    MAX(created_at) AS last_log_at
                FROM reminder_logs
                WHERE bill_id IN ({$in})
                GROUP BY bill_id
            ");
            $logStmt->execute($ids);
            foreach ($logStmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
                $logMap[(int)$log['bill_id']] = $log;
            }
        }
    }

    $groups = [];
    foreach ($bills as $bill) {
        $billId = (int)($bill['bill_id'] ?? 0);
        $amount = reportsMoney($bill['amount'] ?? 0);
        $log = $logMap[$billId] ?? null;
        $sentCount = $log
            ? (int)($log['sent_count'] ?? 0)
            : max((int)($bill['overdue_sent_count'] ?? 0), (int)($bill['reminder_count'] ?? 0));
        $attemptCount = $log
            ? (int)($log['attempt_count'] ?? 0)
            : max((int)($bill['reminder_attempts'] ?? 0), $sentCount);
        $lastSentAt = $log['last_log_at'] ?? ($bill['last_overdue_sent_at'] ?? ($bill['last_reminder_sent_at'] ?? null));
        $key = reportsCustomerKey($bill);

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'customer_key' => $key,
                'customer_id' => (int)($bill['customer_id'] ?? 0),
                'customer_name' => trim((string)($bill['customer_name'] ?? '')) ?: 'Cliente',
                'phone' => trim((string)($bill['phone'] ?? '')),
                'total_amount' => 0.0,
                'open_bills' => 0,
                'overdue_bills' => 0,
                'reminders_sent' => 0,
                'reminder_attempts' => 0,
                'oldest_due_at' => null,
                'max_days_overdue' => 0,
                'last_reminder_at' => null,
                'bills' => [],
            ];
        }

        $days = reportsDaysOverdue($bill['due_at'] ?? null);
        $groups[$key]['total_amount'] += $amount;
        $groups[$key]['open_bills']++;
        if ($days > 0) $groups[$key]['overdue_bills']++;
        $groups[$key]['reminders_sent'] += $sentCount;
        $groups[$key]['reminder_attempts'] += $attemptCount;
        $groups[$key]['max_days_overdue'] = max((int)$groups[$key]['max_days_overdue'], $days);

        $dueAt = $bill['due_at'] ?? null;
        if ($dueAt && (!$groups[$key]['oldest_due_at'] || strtotime((string)$dueAt) < strtotime((string)$groups[$key]['oldest_due_at']))) {
            $groups[$key]['oldest_due_at'] = $dueAt;
        }
        if ($lastSentAt && (!$groups[$key]['last_reminder_at'] || strtotime((string)$lastSentAt) > strtotime((string)$groups[$key]['last_reminder_at']))) {
            $groups[$key]['last_reminder_at'] = $lastSentAt;
        }

        $groups[$key]['bills'][] = [
            'bill_id' => $billId,
            'amount' => $amount,
            'due_at' => $dueAt,
            'days_overdue' => $days,
            'items_text' => trim((string)($bill['items_text'] ?? '')) ?: 'Sem itens informados',
            'bill_url' => trim((string)($bill['bill_url'] ?? '')),
            'status' => (string)($bill['status'] ?? 'unpaid'),
            'blocked' => (int)($bill['blocked'] ?? 0),
            'reminders_sent' => $sentCount,
            'reminder_attempts' => $attemptCount,
            'last_reminder_at' => $lastSentAt,
            'next_reminder_at' => $bill['next_reminder_at'] ?? null,
        ];
    }

    $rows = array_values($groups);
    usort($rows, function (array $a, array $b): int {
        $amountCmp = $b['total_amount'] <=> $a['total_amount'];
        if ($amountCmp !== 0) return $amountCmp;
        $billCmp = $b['open_bills'] <=> $a['open_bills'];
        if ($billCmp !== 0) return $billCmp;
        return strcmp((string)$a['customer_name'], (string)$b['customer_name']);
    });

    foreach ($rows as &$row) {
        usort($row['bills'], function (array $a, array $b): int {
            $ad = strtotime((string)($a['due_at'] ?? '')) ?: PHP_INT_MAX;
            $bd = strtotime((string)($b['due_at'] ?? '')) ?: PHP_INT_MAX;
            return $ad <=> $bd;
        });
        $row['total_amount'] = round((float)$row['total_amount'], 2);
    }
    unset($row);

    $summary = [
        'debtors' => count($rows),
        'open_bills' => array_sum(array_map(function ($r) { return (int)$r['open_bills']; }, $rows)),
        'total_amount' => round(array_sum(array_map(function ($r) { return (float)$r['total_amount']; }, $rows)), 2),
        'overdue_bills' => array_sum(array_map(function ($r) { return (int)$r['overdue_bills']; }, $rows)),
        'reminders_sent' => array_sum(array_map(function ($r) { return (int)$r['reminders_sent']; }, $rows)),
        'top_debtor' => $rows[0] ?? null,
    ];

    reportsOut([
        'ok' => true,
        'summary' => $summary,
        'rows' => $rows,
        'generated_at' => date('Y-m-d H:i:s'),
        'limit' => $limit,
    ]);
} catch (Throwable $e) {
    reportsOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
