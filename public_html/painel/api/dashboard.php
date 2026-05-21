<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

function dashOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dashTableExists(PDO $pdo, string $table): bool
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

function dashColumnExists(PDO $pdo, string $table, string $column): bool
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

function dashScalar(PDO $pdo, string $sql, array $params = [], $default = 0)
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function dashRows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function dashMoney($value): float
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

function dashDateRange(): array
{
    $month = trim((string)($_GET['month'] ?? ''));
    $start = trim((string)($_GET['start'] ?? ''));
    $end = trim((string)($_GET['end'] ?? ''));

    if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
        $startDt = DateTime::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00') ?: new DateTime('first day of this month 00:00:00');
        $endDt = (clone $startDt)->modify('first day of next month');
    } else {
        $startDt = $start !== '' ? new DateTime($start . ' 00:00:00') : new DateTime('first day of this month 00:00:00');
        $endDt = $end !== '' ? (new DateTime($end . ' 00:00:00'))->modify('+1 day') : new DateTime('tomorrow 00:00:00');
        $month = $startDt->format('Y-m');
    }

    return [
        'month' => $month,
        'start' => $startDt->format('Y-m-d H:i:s'),
        'end_exclusive' => $endDt->format('Y-m-d H:i:s'),
        'start_date' => $startDt->format('Y-m-d'),
        'end_date' => (clone $endDt)->modify('-1 day')->format('Y-m-d'),
    ];
}

try {
    $range = dashDateRange();
    $params = [
        ':start' => $range['start'],
        ':end' => $range['end_exclusive'],
    ];

    $configuredCost = cfg($cfg, 'WHATSAPP_MESSAGE_COST_BRL', cfg($cfg, 'META_MESSAGE_COST_BRL', '0'));
    $unitCost = isset($_GET['unit_cost']) && $_GET['unit_cost'] !== ''
        ? dashMoney($_GET['unit_cost'])
        : dashMoney($configuredCost);

    $hasChat = dashTableExists($pdo, 'chat_messages');
    $hasRuns = dashTableExists($pdo, 'automation_runs');
    $hasReminders = dashTableExists($pdo, 'bill_reminders');
    $hasReminderLogs = dashTableExists($pdo, 'reminder_logs');

    $messageStats = [
        'sent_total' => 0,
        'sent_success' => 0,
        'sent_failed' => 0,
        'inbound_total' => 0,
        'recobranca_total' => 0,
        'manual_total' => 0,
        'auto_reply_total' => 0,
    ];
    $messageStatusRows = [];
    $messageSourceRows = [];
    $seriesRows = [];

    if ($hasChat) {
        $sourceExpr = dashColumnExists($pdo, 'chat_messages', 'source') ? 'COALESCE(NULLIF(source, \'\'), \'sem_origem\')' : "'sem_origem'";
        $sourceRefExpr = dashColumnExists($pdo, 'chat_messages', 'source_ref') ? 'source_ref' : "''";

        $messageStats = dashRows($pdo, "
            SELECT
                COUNT(CASE WHEN direction = 'out' THEN 1 END) AS sent_total,
                COUNT(CASE WHEN direction = 'out' AND status NOT IN ('failed') THEN 1 END) AS sent_success,
                COUNT(CASE WHEN direction = 'out' AND status = 'failed' THEN 1 END) AS sent_failed,
                COUNT(CASE WHEN direction = 'in' THEN 1 END) AS inbound_total,
                COUNT(CASE WHEN direction = 'out' AND ({$sourceExpr} IN ('automation','automation_backfill','same_resend','manual_resend') OR {$sourceRefExpr} <> '') THEN 1 END) AS recobranca_total,
                COUNT(CASE WHEN direction = 'out' AND {$sourceExpr} = 'manual' THEN 1 END) AS manual_total,
                COUNT(CASE WHEN direction = 'out' AND {$sourceExpr} = 'auto_reply' THEN 1 END) AS auto_reply_total
            FROM chat_messages
            WHERE created_at >= :start AND created_at < :end
        ", $params)[0] ?? $messageStats;

        $messageStatusRows = dashRows($pdo, "
            SELECT status, COUNT(*) AS total
            FROM chat_messages
            WHERE direction = 'out'
              AND created_at >= :start AND created_at < :end
            GROUP BY status
            ORDER BY total DESC
        ", $params);

        $messageSourceRows = dashRows($pdo, "
            SELECT {$sourceExpr} AS source, COUNT(*) AS total
            FROM chat_messages
            WHERE direction = 'out'
              AND created_at >= :start AND created_at < :end
            GROUP BY {$sourceExpr}
            ORDER BY total DESC
            LIMIT 8
        ", $params);

        $seriesRows = dashRows($pdo, "
            SELECT DATE(created_at) AS label, COUNT(*) AS sent
            FROM chat_messages
            WHERE direction = 'out'
              AND created_at >= :start AND created_at < :end
            GROUP BY DATE(created_at)
            ORDER BY label ASC
        ", $params);
    }

    $runStats = [
        'runs_total' => 0,
        'runs_processed' => 0,
        'runs_error' => 0,
        'runs_not_sent' => 0,
    ];
    if ($hasRuns) {
        $runStats = dashRows($pdo, "
            SELECT
                COUNT(*) AS runs_total,
                COUNT(CASE WHEN status = 'processed' THEN 1 END) AS runs_processed,
                COUNT(CASE WHEN status = 'error' THEN 1 END) AS runs_error,
                COUNT(CASE WHEN status = 'not_sent' THEN 1 END) AS runs_not_sent
            FROM automation_runs
            WHERE created_at >= :start AND created_at < :end
        ", $params)[0] ?? $runStats;
    }

    $recovered = [
        'recovered_bills' => 0,
        'recovered_amount' => 0,
        'paid_bills' => 0,
        'paid_amount' => 0,
        'open_bills' => 0,
        'open_amount' => 0,
        'ready_bills' => 0,
    ];
    $recentRecoveries = [];
    $topCustomers = [];

    if ($hasReminders) {
        $amountExpr = dashColumnExists($pdo, 'bill_reminders', 'amount') ? 'amount' : '0';
        $updatedExpr = dashColumnExists($pdo, 'bill_reminders', 'updated_at') ? 'updated_at' : 'NOW()';
        $paidAtExpr = dashColumnExists($pdo, 'bill_reminders', 'paid_at') ? 'COALESCE(paid_at, ' . $updatedExpr . ')' : $updatedExpr;
        $sentExprs = [];
        foreach (['last_overdue_sent_at', 'last_reminder_sent_at', 'created_sent_at'] as $col) {
            if (dashColumnExists($pdo, 'bill_reminders', $col)) $sentExprs[] = $col;
        }
        $sentExpr = $sentExprs ? 'COALESCE(' . implode(',', $sentExprs) . ')' : 'NULL';
        $customerExpr = dashColumnExists($pdo, 'bill_reminders', 'customer_name') ? 'customer_name' : "'Cliente'";
        $phoneExpr = dashColumnExists($pdo, 'bill_reminders', 'phone') ? 'phone' : "''";
        $nextExpr = dashColumnExists($pdo, 'bill_reminders', 'next_reminder_at') ? 'next_reminder_at' : 'NULL';
        $blockedExpr = dashColumnExists($pdo, 'bill_reminders', 'blocked') ? 'blocked' : '0';

        $recovered = dashRows($pdo, "
            SELECT
                COUNT(CASE WHEN status = 'paid' AND {$paidAtExpr} >= :start AND {$paidAtExpr} < :end THEN 1 END) AS paid_bills,
                COALESCE(SUM(CASE WHEN status = 'paid' AND {$paidAtExpr} >= :start AND {$paidAtExpr} < :end THEN {$amountExpr} ELSE 0 END), 0) AS paid_amount,
                COUNT(CASE WHEN status = 'paid' AND {$sentExpr} IS NOT NULL AND {$paidAtExpr} >= :start AND {$paidAtExpr} < :end THEN 1 END) AS recovered_bills,
                COALESCE(SUM(CASE WHEN status = 'paid' AND {$sentExpr} IS NOT NULL AND {$paidAtExpr} >= :start AND {$paidAtExpr} < :end THEN {$amountExpr} ELSE 0 END), 0) AS recovered_amount,
                COUNT(CASE WHEN COALESCE(NULLIF(status, ''), 'unpaid') IN ('unpaid','pending','overdue') THEN 1 END) AS open_bills,
                COALESCE(SUM(CASE WHEN COALESCE(NULLIF(status, ''), 'unpaid') IN ('unpaid','pending','overdue') THEN {$amountExpr} ELSE 0 END), 0) AS open_amount,
                COUNT(CASE WHEN COALESCE(NULLIF(status, ''), 'unpaid') IN ('unpaid','pending','overdue') AND {$blockedExpr} = 0 AND ({$nextExpr} IS NULL OR {$nextExpr} <= NOW()) THEN 1 END) AS ready_bills
            FROM bill_reminders
        ", $params)[0] ?? $recovered;

        $recentRecoveries = dashRows($pdo, "
            SELECT bill_id, {$customerExpr} AS customer_name, {$phoneExpr} AS phone, {$amountExpr} AS amount, {$sentExpr} AS sent_at, {$paidAtExpr} AS paid_at
            FROM bill_reminders
            WHERE status = 'paid'
              AND {$sentExpr} IS NOT NULL
              AND {$paidAtExpr} >= :start AND {$paidAtExpr} < :end
            ORDER BY {$paidAtExpr} DESC
            LIMIT 10
        ", $params);

        $topCustomers = dashRows($pdo, "
            SELECT {$customerExpr} AS customer_name, COUNT(*) AS recovered_bills, COALESCE(SUM({$amountExpr}), 0) AS recovered_amount
            FROM bill_reminders
            WHERE status = 'paid'
              AND {$sentExpr} IS NOT NULL
              AND {$paidAtExpr} >= :start AND {$paidAtExpr} < :end
            GROUP BY {$customerExpr}
            ORDER BY recovered_amount DESC
            LIMIT 8
        ", $params);
    }

    if ($hasReminderLogs) {
        $logsSent = (int)dashScalar($pdo, "
            SELECT COUNT(*)
            FROM reminder_logs
            WHERE created_at >= :start AND created_at < :end
        ", $params, 0);
        $logsOk = (int)dashScalar($pdo, "
            SELECT COUNT(*)
            FROM reminder_logs
            WHERE ok = 1 AND created_at >= :start AND created_at < :end
        ", $params, 0);
        $messageStats['reminder_logs_total'] = $logsSent;
        $messageStats['reminder_logs_ok'] = $logsOk;
    }

    $sentTotal = (int)($messageStats['sent_total'] ?? 0);
    $cost = $sentTotal * $unitCost;
    $recoveredAmount = dashMoney($recovered['recovered_amount'] ?? 0);
    $paidAmount = dashMoney($recovered['paid_amount'] ?? 0);
    $openAmount = dashMoney($recovered['open_amount'] ?? 0);

    foreach ($seriesRows as &$row) {
        $row['sent'] = (int)($row['sent'] ?? 0);
        $row['cost'] = $row['sent'] * $unitCost;
    }
    unset($row);

    dashOut([
        'ok' => true,
        'filters' => [
            'month' => $range['month'],
            'start' => $range['start_date'],
            'end' => $range['end_date'],
            'unit_cost_brl' => $unitCost,
            'configured_unit_cost_brl' => dashMoney($configuredCost),
        ],
        'summary' => [
            'sent_messages' => $sentTotal,
            'successful_messages' => (int)($messageStats['sent_success'] ?? 0),
            'failed_messages' => (int)($messageStats['sent_failed'] ?? 0),
            'inbound_messages' => (int)($messageStats['inbound_total'] ?? 0),
            'recobranca_messages' => (int)($messageStats['recobranca_total'] ?? 0),
            'manual_messages' => (int)($messageStats['manual_total'] ?? 0),
            'auto_reply_messages' => (int)($messageStats['auto_reply_total'] ?? 0),
            'message_cost_brl' => $cost,
            'recovered_bills' => (int)($recovered['recovered_bills'] ?? 0),
            'recovered_amount_brl' => $recoveredAmount,
            'paid_bills' => (int)($recovered['paid_bills'] ?? 0),
            'paid_amount_brl' => $paidAmount,
            'net_recovered_brl' => $recoveredAmount - $cost,
            'roi' => $cost > 0 ? $recoveredAmount / $cost : null,
            'open_bills' => (int)($recovered['open_bills'] ?? 0),
            'open_amount_brl' => $openAmount,
            'ready_bills' => (int)($recovered['ready_bills'] ?? 0),
            'runs_total' => (int)($runStats['runs_total'] ?? 0),
            'runs_processed' => (int)($runStats['runs_processed'] ?? 0),
            'runs_error' => (int)($runStats['runs_error'] ?? 0),
            'runs_not_sent' => (int)($runStats['runs_not_sent'] ?? 0),
        ],
        'series' => $seriesRows,
        'message_status' => $messageStatusRows,
        'message_sources' => $messageSourceRows,
        'recent_recoveries' => $recentRecoveries,
        'top_customers' => $topCustomers,
        'tables' => [
            'chat_messages' => $hasChat,
            'automation_runs' => $hasRuns,
            'bill_reminders' => $hasReminders,
            'reminder_logs' => $hasReminderLogs,
        ],
        'notes' => [
            'cost' => 'Custo calculado por mensagem enviada: mensagens saidas x custo unitario informado/configurado.',
            'recovered' => 'Cobranca recuperada usa bill_reminders.status=paid com envio de recobranca registrado antes do pagamento; updated_at e usado como data de pagamento quando nao existe paid_at.',
        ],
    ]);
} catch (Throwable $e) {
    dashOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
