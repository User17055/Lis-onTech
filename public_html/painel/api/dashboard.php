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

function dashSqlMoneyExpr(string $expr): string
{
    $clean = "REPLACE(REPLACE(COALESCE({$expr}, '0'), 'R$', ''), ' ', '')";
    return "CAST(CASE WHEN INSTR({$clean}, ',') > 0 THEN REPLACE(REPLACE({$clean}, '.', ''), ',', '.') ELSE {$clean} END AS DECIMAL(14,2))";
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
                COUNT(CASE WHEN direction = 'out' AND ({$sourceExpr} IN ('automation','automation_backfill','same_resend','manual_resend','recobranca_cron','reminders_runner') OR {$sourceRefExpr} <> '') THEN 1 END) AS recobranca_total,
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

    $modelSentTotal = 0;
    $modelSentPredicate = '0=1';
    if ($hasRuns) {
        $sentBits = [];
        if (dashColumnExists($pdo, 'automation_runs', 'step_whatsapp')) {
            $sentBits[] = 'COALESCE(ar.step_whatsapp, 0) = 1';
        }
        if (dashColumnExists($pdo, 'automation_runs', 'status')) {
            $sentBits[] = "ar.status IN ('processed','success','ok')";
        }
        if (dashColumnExists($pdo, 'automation_runs', 'whatsapp_response')) {
            $sentBits[] = "(ar.whatsapp_response IS NOT NULL AND CAST(ar.whatsapp_response AS CHAR) <> '')";
        }
        $eventFilter = dashColumnExists($pdo, 'automation_runs', 'event_type') ? "ar.event_type = 'bill_created'" : '1=1';
        $modelSentPredicate = $eventFilter . ' AND (' . ($sentBits ? implode(' OR ', $sentBits) : '0=1') . ')';

        $modelSentTotal = (int)dashScalar($pdo, "
            SELECT COUNT(*)
            FROM automation_runs ar
            WHERE {$modelSentPredicate}
              AND ar.created_at >= :start AND ar.created_at < :end
        ", $params, 0);

        if (!$hasChat) {
            $seriesRows = dashRows($pdo, "
            SELECT DATE(ar.created_at) AS label, COUNT(*) AS sent
            FROM automation_runs ar
            WHERE {$modelSentPredicate}
              AND ar.created_at >= :start AND ar.created_at < :end
            GROUP BY DATE(ar.created_at)
            ORDER BY label ASC
            ", $params);
        }
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

    if ($hasReminders && $hasRuns) {
        $amountRawExpr = dashColumnExists($pdo, 'bill_reminders', 'amount') ? 'br.amount' : '0';
        $amountExpr = dashSqlMoneyExpr($amountRawExpr);
        $runJsonAmountExpr = dashColumnExists($pdo, 'automation_runs', 'vindi_input')
            ? "COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(ar.vindi_input, '$.event.data.bill.amount')),
                JSON_UNQUOTE(JSON_EXTRACT(ar.vindi_input, '$.event.data.bill.total')),
                JSON_UNQUOTE(JSON_EXTRACT(ar.vindi_input, '$.event.data.bill.value')),
                '0'
            )"
            : "'0'";
        $runAmountExpr = dashSqlMoneyExpr('ms.run_amount');
        $effectiveAmountExpr = "CASE WHEN {$amountExpr} > 0 THEN {$amountExpr} ELSE {$runAmountExpr} END";
        $updatedExpr = dashColumnExists($pdo, 'bill_reminders', 'updated_at') ? 'br.updated_at' : 'NOW()';
        $paidAtExpr = dashColumnExists($pdo, 'bill_reminders', 'paid_at') ? 'COALESCE(br.paid_at, ' . $updatedExpr . ')' : $updatedExpr;
        $customerExpr = dashColumnExists($pdo, 'bill_reminders', 'customer_name') ? 'br.customer_name' : 'COALESCE(ms.customer_name, \'Cliente\')';
        $phoneExpr = dashColumnExists($pdo, 'bill_reminders', 'phone') ? 'br.phone' : "''";
        $nextExpr = dashColumnExists($pdo, 'bill_reminders', 'next_reminder_at') ? 'br.next_reminder_at' : 'NULL';
        $blockedExpr = dashColumnExists($pdo, 'bill_reminders', 'blocked') ? 'br.blocked' : '0';
        $modelSentSub = "
            SELECT ar.bill_id, MIN(ar.created_at) AS sent_at, MAX(ar.customer_name) AS customer_name, MAX({$runJsonAmountExpr}) AS run_amount
            FROM automation_runs ar
            WHERE {$modelSentPredicate}
              AND ar.bill_id IS NOT NULL
              AND ar.created_at >= :start AND ar.created_at < :end
            GROUP BY ar.bill_id
        ";

        $recovered = dashRows($pdo, "
            SELECT
                COUNT(CASE WHEN br.status = 'paid' AND {$paidAtExpr} >= ms.sent_at THEN 1 END) AS paid_bills,
                COALESCE(SUM(CASE WHEN br.status = 'paid' AND {$paidAtExpr} >= ms.sent_at THEN {$effectiveAmountExpr} ELSE 0 END), 0) AS paid_amount,
                COUNT(CASE WHEN br.status = 'paid' AND {$paidAtExpr} >= ms.sent_at THEN 1 END) AS recovered_bills,
                COALESCE(SUM(CASE WHEN br.status = 'paid' AND {$paidAtExpr} >= ms.sent_at THEN {$effectiveAmountExpr} ELSE 0 END), 0) AS recovered_amount,
                0 AS open_bills,
                0 AS open_amount,
                COUNT(CASE WHEN COALESCE(NULLIF(br.status, ''), 'unpaid') IN ('unpaid','pending','overdue') AND {$blockedExpr} = 0 AND ({$nextExpr} IS NULL OR {$nextExpr} <= NOW()) THEN 1 END) AS ready_bills
            FROM ({$modelSentSub}) ms
            INNER JOIN bill_reminders br ON br.bill_id = ms.bill_id
        ", $params)[0] ?? $recovered;

        $recentRecoveries = dashRows($pdo, "
            SELECT br.bill_id, {$customerExpr} AS customer_name, {$phoneExpr} AS phone, {$effectiveAmountExpr} AS amount, ms.sent_at, {$paidAtExpr} AS paid_at
            FROM ({$modelSentSub}) ms
            INNER JOIN bill_reminders br ON br.bill_id = ms.bill_id
            WHERE br.status = 'paid'
              AND {$paidAtExpr} >= ms.sent_at
            ORDER BY {$paidAtExpr} DESC
            LIMIT 10
        ", $params);
        foreach ($recentRecoveries as &$row) {
            $row['amount'] = dashMoney($row['amount'] ?? 0);
        }
        unset($row);

        $topCustomers = dashRows($pdo, "
            SELECT {$customerExpr} AS customer_name, COUNT(*) AS recovered_bills, COALESCE(SUM({$effectiveAmountExpr}), 0) AS recovered_amount
            FROM ({$modelSentSub}) ms
            INNER JOIN bill_reminders br ON br.bill_id = ms.bill_id
            WHERE br.status = 'paid'
              AND {$paidAtExpr} >= ms.sent_at
            GROUP BY {$customerExpr}
            ORDER BY recovered_amount DESC
            LIMIT 8
        ", $params);
        foreach ($topCustomers as &$row) {
            $row['recovered_amount'] = dashMoney($row['recovered_amount'] ?? 0);
        }
        unset($row);
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

        if (!$hasChat && !$seriesRows) {
            $seriesRows = dashRows($pdo, "
                SELECT DATE(created_at) AS label, COUNT(*) AS sent
                FROM reminder_logs
                WHERE ok = 1
                  AND created_at >= :start AND created_at < :end
                GROUP BY DATE(created_at)
                ORDER BY label ASC
            ", $params);
        }
    }

    $chatSuccess = (int)($messageStats['sent_success'] ?? 0);
    $logsOk = !$hasChat ? (int)($messageStats['reminder_logs_ok'] ?? 0) : 0;
    $sentTotal = $hasChat ? $chatSuccess : ($modelSentTotal + $logsOk);
    $failedTotal = $hasChat
        ? (int)($messageStats['sent_failed'] ?? 0)
        : max(0, (int)($messageStats['reminder_logs_total'] ?? 0) - (int)($messageStats['reminder_logs_ok'] ?? 0));
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
            'successful_messages' => $sentTotal,
            'failed_messages' => $failedTotal,
            'inbound_messages' => (int)($messageStats['inbound_total'] ?? 0),
            'recobranca_messages' => (int)($messageStats['recobranca_total'] ?? 0),
            'model_invoice_messages' => $modelSentTotal,
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
            'cost' => 'Custo calculado por mensagens de saida aceitas no WhatsApp dentro do periodo x custo unitario.',
            'recovered' => 'Fatura recuperada usa a mesma bill_id do modelo de fatura enviado e status=paid depois do envio.',
        ],
    ]);
} catch (Throwable $e) {
    dashOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
