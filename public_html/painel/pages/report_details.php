<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';

if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function rdTableExists(PDO $pdo, string $table): bool {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $st->execute([$table]);
    return $cache[$table] = ((int)$st->fetchColumn() > 0);
}

function rdColumnExists(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $st->execute([$table, $column]);
    return $cache[$key] = ((int)$st->fetchColumn() > 0);
}

function rdMoney($value): float {
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

function rdMoneyBr($value): string {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function rdDateBr($value): string {
    if (!$value) return '-';
    $ts = strtotime((string)$value);
    return $ts ? date('d/m/Y', $ts) : (string)$value;
}

function rdDateTimeBr($value): string {
    if (!$value) return '-';
    $ts = strtotime((string)$value);
    return $ts ? date('d/m/Y, H:i', $ts) : (string)$value;
}

function rdDaysOverdue($value): int {
    if (!$value) return 0;
    $ts = strtotime(substr((string)$value, 0, 10) . ' 00:00:00');
    $today = strtotime(date('Y-m-d') . ' 00:00:00');
    if (!$ts || !$today || $ts >= $today) return 0;
    return (int)floor(($today - $ts) / 86400);
}

function rdMonthRange(string $month): ?array {
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) return null;
    $start = DateTime::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00');
    if (!$start) return null;
    $end = (clone $start)->modify('first day of next month');
    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

function rdMonthLabel(string $month): string {
    static $names = [
        '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
        '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
        '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro',
    ];
    if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) return 'Todos os meses';
    return ($names[$m[2]] ?? $m[2]) . ' de ' . $m[1];
}

function rdInitial(string $name): string {
    $name = trim($name);
    if ($name === '') return 'C';
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($name, 0, 1));
}

function rdSafeBackHref(): string {
    $fallback = '/painel/index.php?pagina=reports';
    $raw = trim((string)($_GET['back'] ?? ''));
    if ($raw === '') return $fallback;

    $parts = parse_url($raw);
    if (!is_array($parts)) return $fallback;
    $host = (string)($parts['host'] ?? '');
    $path = (string)($parts['path'] ?? '');
    $currentHost = (string)($_SERVER['HTTP_HOST'] ?? '');

    if (($host === '' || strcasecmp($host, $currentHost) === 0)
        && in_array($path, ['/painel/index.php', '/painel/'], true)
    ) {
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $candidate = $path . $query;
        if (!str_contains($candidate, 'pagina=report_details')) return $candidate;
    }
    return $fallback;
}

function rdCustomerKey(int $customerId, string $phone, string $name): string {
    if ($customerId > 0) return 'id:' . $customerId;
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits !== '') return 'phone:' . $digits;
    return 'name:' . strtolower(trim($name ?: 'Cliente'));
}

function rdFetchMark(PDO $pdo, string $markKey): array {
    if ($markKey === '' || !rdTableExists($pdo, 'report_customer_marks')) {
        return ['marked' => false, 'reason' => '', 'updated_at' => null];
    }
    $st = $pdo->prepare("
        SELECT reason, updated_at
        FROM report_customer_marks
        WHERE mark_key = ? AND active = 1
        LIMIT 1
    ");
    $st->execute([$markKey]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['marked' => false, 'reason' => '', 'updated_at' => null];
    return [
        'marked' => true,
        'reason' => trim((string)($row['reason'] ?? '')),
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

$backHref = rdSafeBackHref();
$customerId = (int)($_GET['customer_id'] ?? 0);
$name = trim((string)($_GET['name'] ?? ''));
$month = trim((string)($_GET['month'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}$/', $month) || $month > date('Y-m')) $month = '';

$bills = [];
$recentLogs = [];
$error = '';

if (!rdTableExists($pdo, 'bill_reminders')) {
    $error = 'Banco local ainda nao tem faturas sincronizadas.';
} elseif ($customerId <= 0 && $name === '') {
    $error = 'Cliente nao informado.';
} else {
    $cols = [
        'bill_id', 'customer_id', 'customer_name', 'phone', 'bill_url', 'items_text',
        'amount', 'due_at', 'status', 'active', 'blocked', 'reminder_count',
        'overdue_sent_count', 'reminder_attempts', 'last_overdue_sent_at',
        'last_reminder_sent_at', 'next_reminder_at', 'last_status',
        'last_status_check_at', 'paid_at', 'updated_at',
    ];
    $select = [];
    foreach ($cols as $col) {
        $select[] = rdColumnExists($pdo, 'bill_reminders', $col) ? "br.{$col}" : "NULL AS {$col}";
    }

    $where = [];
    $params = [];
    $hasIdentityFilter = false;
    if ($customerId > 0 && rdColumnExists($pdo, 'bill_reminders', 'customer_id')) {
        $where[] = 'br.customer_id = :customer_id';
        $params[':customer_id'] = $customerId;
        $hasIdentityFilter = true;
    } elseif ($name !== '' && rdColumnExists($pdo, 'bill_reminders', 'customer_name')) {
        $where[] = 'br.customer_name = :customer_name';
        $params[':customer_name'] = $name;
        $hasIdentityFilter = true;
    }
    if (rdColumnExists($pdo, 'bill_reminders', 'active')) {
        $where[] = 'COALESCE(br.active, 1) = 1';
    }
    if (rdColumnExists($pdo, 'bill_reminders', 'status')) {
        $where[] = "LOWER(COALESCE(NULLIF(br.status, ''), 'unpaid')) IN ('unpaid','pending','overdue')";
    }
    if (rdColumnExists($pdo, 'bill_reminders', 'due_at')) {
        $where[] = "br.due_at IS NOT NULL AND br.due_at < :visible_end";
        $params[':visible_end'] = date('Y-m-d 00:00:00');
    }
    $range = rdMonthRange($month);
    if ($range && rdColumnExists($pdo, 'bill_reminders', 'due_at')) {
        $where[] = 'br.due_at >= :month_start AND br.due_at < :month_end';
        $params[':month_start'] = $range[0];
        $params[':month_end'] = $range[1];
    }

    if (!$hasIdentityFilter) {
        $where[] = '1=0';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : 'WHERE 1=0';
    $order = rdColumnExists($pdo, 'bill_reminders', 'due_at') ? 'br.due_at ASC' : 'br.bill_id DESC';
    $st = $pdo->prepare("
        SELECT " . implode(",\n               ", $select) . "
        FROM bill_reminders br
        {$whereSql}
        ORDER BY {$order}
        LIMIT 500
    ");
    $st->execute($params);
    $bills = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($bills && rdTableExists($pdo, 'reminder_logs')
        && rdColumnExists($pdo, 'reminder_logs', 'bill_id')
        && rdColumnExists($pdo, 'reminder_logs', 'created_at')
    ) {
        $ids = array_values(array_unique(array_filter(array_map(static fn($b) => (int)($b['bill_id'] ?? 0), $bills))));
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $okExpr = rdColumnExists($pdo, 'reminder_logs', 'ok') ? 'ok' : 'NULL AS ok';
            $codeExpr = rdColumnExists($pdo, 'reminder_logs', 'http_code') ? 'http_code' : 'NULL AS http_code';
            $messageExpr = rdColumnExists($pdo, 'reminder_logs', 'message') ? 'message' : "'' AS message";
            $stLogs = $pdo->prepare("
                SELECT bill_id, {$okExpr}, {$codeExpr}, {$messageExpr}, created_at
                FROM reminder_logs
                WHERE bill_id IN ({$in})
                ORDER BY created_at DESC
                LIMIT 12
            ");
            $stLogs->execute($ids);
            $recentLogs = $stLogs->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
}

$customerName = $name ?: 'Cliente';
$phone = '';
$billIds = [];
$total = 0.0;
$sent = 0;
$attempts = 0;
$oldestDue = null;
$maxDays = 0;
$lastReminder = null;
$firstBillUrl = '';

foreach ($bills as &$bill) {
    $amount = rdMoney($bill['amount'] ?? 0);
    $bill['amount_num'] = $amount;
    $bill['days_overdue'] = rdDaysOverdue($bill['due_at'] ?? null);
    $bill['sent_num'] = max((int)($bill['overdue_sent_count'] ?? 0), (int)($bill['reminder_count'] ?? 0));
    $bill['attempts_num'] = max((int)($bill['reminder_attempts'] ?? 0), (int)$bill['sent_num']);
    $last = $bill['last_overdue_sent_at'] ?: ($bill['last_reminder_sent_at'] ?? null);
    $bill['last_reminder_calc'] = $last;

    if (!$customerName || $customerName === 'Cliente') $customerName = trim((string)($bill['customer_name'] ?? '')) ?: 'Cliente';
    if ($phone === '') $phone = trim((string)($bill['phone'] ?? ''));
    if ($customerId <= 0) $customerId = (int)($bill['customer_id'] ?? 0);
    if (!$firstBillUrl) $firstBillUrl = trim((string)($bill['bill_url'] ?? ''));

    $billIds[] = (int)($bill['bill_id'] ?? 0);
    $total += $amount;
    $sent += (int)$bill['sent_num'];
    $attempts += (int)$bill['attempts_num'];
    $maxDays = max($maxDays, (int)$bill['days_overdue']);

    $dueAt = $bill['due_at'] ?? null;
    if ($dueAt && (!$oldestDue || strtotime((string)$dueAt) < strtotime((string)$oldestDue))) $oldestDue = $dueAt;
    if ($last && (!$lastReminder || strtotime((string)$last) > strtotime((string)$lastReminder))) $lastReminder = $last;
}
unset($bill);

$profileHref = $customerId > 0 ? 'https://app.vindi.com.br/admin/customers/' . rawurlencode((string)$customerId) . '#tab-bills' : '';
$localHref = $customerId > 0 ? '/painel/index.php?pagina=customer&customer_id=' . rawurlencode((string)$customerId) . '&name=' . rawurlencode($customerName) : '';
$monthLabel = $month !== '' ? rdMonthLabel($month) : 'Todos os meses';
$countBills = count($bills);
$markKey = rdCustomerKey($customerId, $phone, $customerName);
$mark = rdFetchMark($pdo, $markKey);
$totalFill = $total > 0 ? 100 : 0;
$billFill = $countBills > 0 ? 100 : 0;
$daysFill = min(100, $maxDays > 0 ? max(12, ($maxDays / 180) * 100) : 0);
$sentFill = $sent > 0 ? min(100, max(12, ($sent / max($attempts, $sent, 1)) * 100)) : 0;
?>

<div class="det-wrap report-detail-wrap">
  <style>
    .report-detail-wrap{font-family:'Nunito',sans-serif;width:min(1180px,calc(100% - 36px));max-width:1180px;margin:24px auto 28px;padding:0;color:#0f172a;}
    .det-top{background:#fff;border:2px solid #eef2f6;border-radius:18px;padding:20px;display:flex;align-items:center;gap:18px;box-shadow:0 8px 20px rgba(15,23,42,.06);}
    .det-top.marked{background:#fff;border-color:#fecaca;}
    .det-back{width:46px;height:46px;border-radius:50%;background:#f4f7fa;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#0f172a;transition:.2s;flex:0 0 auto;border:1px solid rgba(15,23,42,.06);}
    .det-back:hover{background:#e2e8f0;transform:translateX(-3px);}
    .det-head{min-width:0;display:flex;flex-direction:column;gap:9px;flex:1;}
    .det-title{font-weight:1000;font-size:20px;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:flex;align-items:center;gap:12px;}
    .det-title-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .avatar-initial{width:46px;height:46px;border-radius:16px;background:#eef8ff;color:#12628f;box-shadow:0 0 0 5px #f7fbff;display:inline-flex;align-items:center;justify-content:center;font-size:18px;font-weight:1000;letter-spacing:0;text-transform:uppercase;flex:0 0 auto;}
    .det-sub{display:flex;gap:10px;flex-wrap:wrap;align-items:center;font-weight:900;color:#64748b;font-size:13px;}
    .det-chip{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:999px;border:1px solid #eef2f6;background:#fff;color:#334155;font-weight:1000;}
    .det-chip i{color:#38b6ff;}
    .det-chip.marked{border-color:#fecaca;background:#fee2e2;color:#991b1b;}
    .det-chip.marked i{color:#991b1b;}
    .det-status{display:inline-flex;align-items:center;gap:8px;width:max-content;border-radius:999px;border:1px solid #bfebff;background:#eef8ff;color:#12628f;padding:8px 14px;font-size:13px;font-weight:1000;text-transform:uppercase;white-space:nowrap;}
    .det-status i{color:#12628f;}
    .det-top-actions{margin-left:auto;display:flex;align-items:center;gap:8px;flex:0 0 auto;flex-wrap:wrap;justify-content:flex-end;}
    .det-btn{height:44px;border:2px solid #eef2f6;border-radius:999px;background:#fff;color:#0f172a;padding:0 16px;display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-weight:1000;transition:.2s;font-family:'Nunito',sans-serif;cursor:pointer;box-sizing:border-box;white-space:nowrap;}
    .det-btn:hover{border-color:#bfebff;color:#12628f;transform:translateY(-1px);}
    .det-btn.primary{background:#38b6ff;color:#fff;border-color:#38b6ff;box-shadow:0 4px 12px rgba(56,182,255,.28);}
    .det-btn.danger{border-color:#fecaca;background:#fee2e2;color:#991b1b;}
    .det-btn.danger:hover{border-color:#fecaca;background:#fee2e2;color:#991b1b;box-shadow:0 6px 14px rgba(153,27,27,.12);}
    .det-btn:disabled{opacity:.55;cursor:not-allowed;transform:none;}
    .mark-modal{position:fixed;inset:0;z-index:1100;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.36);}
    .mark-modal.open{display:flex;}
    .mark-card{width:min(460px,100%);background:#fff;border:2px solid #eef2f6;border-radius:12px;box-shadow:0 24px 48px rgba(15,23,42,.2);overflow:hidden;}
    .mark-card-head{display:flex;align-items:center;gap:12px;padding:16px 18px;border-bottom:2px solid #eef2f6;}
    .mark-card-head i{width:36px;height:36px;border-radius:10px;background:#fee2e2;color:#991b1b;display:inline-flex;align-items:center;justify-content:center;flex:0 0 36px;}
    .mark-card-title{font-size:15px;font-weight:1000;color:#0f172a;display:block;}
    .mark-card-sub{font-size:12px;font-weight:900;color:#64748b;display:block;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:330px;}
    .mark-card-body{padding:16px 18px;display:grid;gap:8px;}
    .mark-card-body label{font-size:12px;font-weight:1000;color:#64748b;text-transform:uppercase;}
    .mark-card-body textarea{width:100%;min-height:110px;box-sizing:border-box;border:2px solid #eef2f6;border-radius:8px;resize:vertical;padding:12px;font-family:'Nunito',sans-serif;font-weight:800;color:#0f172a;outline:none;}
    .mark-card-body textarea:focus{border-color:#38b6ff;box-shadow:0 0 0 4px rgba(59,130,246,.1);}
    .mark-error{min-height:18px;color:#991b1b;font-size:12px;font-weight:900;}
    .mark-card-actions{display:flex;justify-content:flex-end;gap:10px;padding:0 18px 18px;}
    .mark-note{margin-top:14px;background:#fff7f7;border:2px solid #fecaca;border-radius:20px;padding:14px 16px;box-shadow:0 8px 18px rgba(153,27,27,.06);display:flex;align-items:flex-start;gap:12px;color:#991b1b;font-weight:900;}
    .mark-note i{width:34px;height:34px;border-radius:12px;background:#fee2e2;display:inline-flex;align-items:center;justify-content:center;flex:0 0 34px;}
    .mark-note strong{display:block;font-size:13px;text-transform:uppercase;margin-bottom:3px;}
    .mark-note span{display:block;color:#7f1d1d;font-size:13px;line-height:1.35;}
    .det-card{margin-top:18px;background:#fff;border:2px solid #eef2f6;border-radius:18px;padding:20px;box-shadow:0 8px 18px rgba(15,23,42,.05);}
    .det-card-title{font-weight:1000;color:#38b6ff;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:15px;}
    .det-card-title i{width:30px;height:30px;border-radius:11px;background:#eef8ff;color:#12628f;display:inline-flex;align-items:center;justify-content:center;}
    .det-actions-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px;}
    .det-actions-row .det-btn:first-child{background:#19aeea;color:#fff;border-color:#19aeea;box-shadow:0 6px 14px rgba(25,174,234,.22);}
    .detail-info-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:14px;margin-top:10px;}
    .detail-info-box{border:1px solid #e6eef7;border-radius:16px;background:#fff;padding:16px;min-height:88px;box-sizing:border-box;}
    .detail-info-box span{display:block;color:#64748b;font-size:12px;font-weight:1000;text-transform:uppercase;margin-bottom:9px;}
    .detail-info-box strong{display:block;color:#0f172a;font-size:15px;font-weight:1000;line-height:1.3;}
    .detail-info-box a{color:#1d9dff;text-decoration:none;font-weight:1000;}
    .detail-info-box a:hover{text-decoration:underline;}
    .detail-time-line{margin-top:14px;color:#64748b;font-size:13px;font-weight:1000;}
    .summary-grid{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:14px;margin-top:16px;}
    .summary-box{border:2px solid #eef2f6;border-radius:20px;padding:18px 58px 16px 18px;background:#fff;box-shadow:0 4px 6px -1px rgba(0,0,0,.04);position:relative;overflow:hidden;min-height:118px;box-sizing:border-box;}
    .summary-box::before{display:none;}
    .summary-box span{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:1000;text-transform:uppercase;color:#64748b;}
    .summary-box span i{position:absolute;right:16px;top:16px;width:40px;height:40px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#eef8ff;color:#12628f;}
    .summary-box strong{display:block;margin-top:14px;font-size:25px;font-weight:1000;color:#0f172a;line-height:1.05;}
    .summary-track{height:5px;border-radius:999px;background:#f4f7fa;border:0;overflow:hidden;margin-top:12px;}
    .summary-fill{display:block;height:100%;width:0;border-radius:999px;background:#38b6ff;}
    .summary-box.danger::before,.summary-box.danger .summary-fill{background:#38b6ff;}
    .summary-box.warn::before,.summary-box.warn .summary-fill{background:#f59e0b;}
    .summary-box.ok::before,.summary-box.ok .summary-fill{background:#10b981;}
    .debt-snapshot{
      min-width:188px;display:grid;gap:7px;padding:10px 12px;border:1px solid #e6eef7;border-left:4px solid #38b6ff;
      border-radius:16px;background:#fff;box-shadow:0 6px 14px rgba(15,23,42,.045);
    }
    .snapshot-value{display:flex;align-items:center;gap:8px;color:#0f172a;font-size:16px;font-weight:1000;line-height:1;}
    .snapshot-value i{width:24px;height:24px;border-radius:8px;background:#eef8ff;color:#12628f;display:inline-flex;align-items:center;justify-content:center;font-size:11px;}
    .snapshot-meta{display:flex;align-items:center;gap:10px;color:#64748b;font-size:11px;font-weight:900;line-height:1;white-space:nowrap;}
    .snapshot-meta span{display:inline-flex;align-items:center;gap:5px;}
    .snapshot-meta i{font-size:10px;color:#38b6ff;}
    .detail-date{font-size:13px;color:#64748b;font-weight:800;white-space:nowrap;}
    .btn-icon{color:#94a3b8;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:.2s;text-decoration:none;border:2px solid transparent;background:transparent;}
    .btn-icon:hover{background:#f1f5f9;color:#38b6ff;border-color:#dbeafe;}
    .bill-list{display:grid;gap:14px;}
    .bill-card{
      border:2px solid #e6eef7;border-radius:22px;background:#fff;display:grid;grid-template-columns:1fr;
      overflow:hidden;box-shadow:0 6px 16px rgba(15,23,42,.05);transition:.2s;
    }
    .bill-card:hover{border-color:#dbeafe;box-shadow:0 12px 20px -8px rgba(56,182,255,.26);}
    .bill-main-card{padding:18px 20px 12px;display:grid;gap:14px;}
    .bill-topline{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .bill-id{display:inline-flex;align-items:center;gap:8px;width:max-content;max-width:100%;padding:7px 11px;border-radius:999px;background:#eef8ff;color:#12628f;font-weight:1000;text-decoration:none;font-size:12px;}
    .bill-amount{font-size:21px;font-weight:1000;margin-top:10px;color:#0f172a;}
    .bill-muted{display:flex;align-items:flex-start;gap:8px;color:#64748b;font-weight:900;font-size:12px;margin-top:8px;line-height:1.3;}
    .bill-muted i{color:#38b6ff;margin-top:1px;}
    .bill-label{display:flex;align-items:center;gap:8px;font-size:11px;font-weight:1000;text-transform:uppercase;color:#64748b;margin-bottom:8px;}
    .bill-label i{color:#38b6ff;}
    .bill-items{white-space:pre-wrap;word-break:break-word;font-size:14px;font-weight:900;line-height:1.42;color:#0f172a;background:#f8fbff;border:1px solid #eef2f6;border-radius:14px;padding:12px;}
    .bill-side{display:flex;gap:10px;align-items:center;justify-content:flex-start;flex-wrap:wrap;text-align:left;background:#fff;padding:0 20px 18px;border:0;}
    .bill-side .debt-snapshot{margin-right:auto;}
    .mini-chip{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border-radius:999px;background:#f4f7fa;border:1px solid #e6eef7;font-size:12px;font-weight:1000;color:#334155;}
    .mini-chip i{color:#38b6ff;}
    .timeline{display:grid;gap:10px;}
    .log-row{display:grid;grid-template-columns:36px 1fr auto;gap:12px;align-items:center;border:1px solid #eef2f6;border-radius:16px;padding:10px 12px;background:#fff;}
    .log-icon{width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#d1fae5;color:#065f46;}
    .log-icon.fail{background:#fee2e2;color:#991b1b;}
    .log-main strong{display:block;font-weight:1000;color:#0f172a;font-size:13px;}
    .log-main span{display:block;color:#64748b;font-size:12px;font-weight:800;margin-top:2px;}
    .empty{color:#64748b;font-size:13px;font-weight:800;text-align:center;padding:28px 12px;background:#f4f7fa;border-radius:16px;}
    @media(max-width:900px){.summary-grid,.detail-info-grid{grid-template-columns:repeat(2,minmax(150px,1fr));}.bill-card{grid-template-columns:1fr;}.bill-side{justify-content:flex-start;text-align:left;}.det-top{align-items:flex-start;}.det-top-actions{margin-left:0;width:100%;justify-content:flex-start;}.det-title{flex-wrap:wrap;white-space:normal;}}
    @media(max-width:560px){.report-detail-wrap{width:calc(100% - 16px);margin-top:10px;}.det-top{flex-wrap:wrap;border-radius:16px;}.det-title{white-space:normal;font-size:16px;}.summary-grid,.detail-info-grid{grid-template-columns:1fr;}.det-actions-row .det-btn,.det-btn{width:100%;justify-content:center;}.log-row{grid-template-columns:36px 1fr;}.log-row .mini-chip{grid-column:1 / -1;justify-content:center;}.mark-card-sub{max-width:230px;}.debt-snapshot{width:100%;min-width:0;}.snapshot-value{font-size:14px;}.snapshot-meta{flex-wrap:wrap;}.bill-side .debt-snapshot{margin-right:0;}}
  </style>

  <div class="det-top <?=!empty($mark['marked']) ? 'marked' : ''?>">
    <a class="det-back" href="<?=h($backHref)?>" title="Voltar">
      <i class="fa-solid fa-arrow-left"></i>
    </a>
    <div class="det-head">
      <div class="det-title">
        <span class="avatar-initial"><?=h(rdInitial($customerName))?></span>
        <span class="det-title-text">Relatorio de <?=h($customerName)?></span>
        <span class="det-status"><i class="fa-solid fa-check"></i> Em aberto</span>
      </div>
      <div class="det-sub">
        <span class="det-chip"><i class="fa-regular fa-calendar"></i> <?=h($monthLabel)?></span>
        <?php if ($customerId > 0): ?><span class="det-chip"><i class="fa-solid fa-id-card"></i> ID <?=h($customerId)?></span><?php endif; ?>
        <span class="det-chip"><i class="fa-solid fa-phone"></i> <?=h($phone ?: 'Telefone nao salvo')?></span>
        <?php if (!empty($mark['marked'])): ?><span class="det-chip marked"><i class="fa-solid fa-flag"></i> Marcado</span><?php endif; ?>
      </div>
    </div>
    <div class="det-top-actions">
      <button type="button" class="det-btn <?=!empty($mark['marked']) ? 'danger' : ''?>" id="btnReportMark" data-action="<?=!empty($mark['marked']) ? 'unmark' : 'mark'?>"><i class="fa-solid fa-flag"></i> <?=!empty($mark['marked']) ? 'Desmarcar' : 'Marcar'?></button>
      <?php if ($profileHref): ?>
        <a class="det-btn primary" href="<?=h($profileHref)?>" target="_blank" rel="noopener"><i class="fa-solid fa-user"></i> Perfil</a>
      <?php endif; ?>
      <?php if ($localHref): ?>
        <a class="det-btn" href="<?=h($localHref)?>" onclick="window.LisOnPageLoader?.show();"><i class="fa-solid fa-file-invoice"></i> Faturas</a>
      <?php endif; ?>
      <?php if ($firstBillUrl): ?>
        <a class="det-btn" href="<?=h($firstBillUrl)?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Vindi</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($mark['marked'])): ?>
    <div class="mark-note">
      <i class="fa-solid fa-flag"></i>
      <div>
        <strong>Motivo da marcacao</strong>
        <span><?=h($mark['reason'] ?: 'Marcado')?></span>
      </div>
    </div>
  <?php endif; ?>

  <div id="markModal" class="mark-modal" aria-hidden="true">
    <div class="mark-card" role="dialog" aria-modal="true" aria-labelledby="markModalTitle">
      <div class="mark-card-head">
        <i class="fa-solid fa-flag"></i>
        <div style="min-width:0;">
          <span id="markModalTitle" class="mark-card-title"><?=!empty($mark['marked']) ? 'Editar marcacao' : 'Marcar cliente'?></span>
          <span class="mark-card-sub"><?=h($customerName)?></span>
        </div>
      </div>
      <div class="mark-card-body">
        <label for="markReason">Motivo</label>
        <textarea id="markReason" placeholder="Descreva o motivo da marcacao"></textarea>
        <div id="markError" class="mark-error"></div>
      </div>
      <div class="mark-card-actions">
        <button type="button" class="det-btn" id="markCancel" style="height:40px;">Cancelar</button>
        <button type="button" class="det-btn primary" id="markSave" style="height:40px;"><i class="fa-solid fa-check"></i> Salvar</button>
      </div>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="det-card"><div class="empty"><?=h($error)?></div></div>
  <?php else: ?>
    <div class="det-card">
      <div class="det-card-title"><i class="fa-solid fa-chart-simple"></i> Resumo</div>
      <div class="det-actions-row">
        <?php if ($profileHref): ?>
          <a class="det-btn" href="<?=h($profileHref)?>" target="_blank" rel="noopener"><i class="fa-solid fa-user"></i> Perfil Vindi</a>
        <?php endif; ?>
        <?php if ($localHref): ?>
          <a class="det-btn" href="<?=h($localHref)?>" onclick="window.LisOnPageLoader?.show();"><i class="fa-solid fa-file-invoice"></i> Ir para faturas</a>
        <?php endif; ?>
        <?php if ($firstBillUrl): ?>
          <a class="det-btn" href="<?=h($firstBillUrl)?>" target="_blank" rel="noopener"><i class="fa-solid fa-rotate"></i> Abrir primeira bill</a>
        <?php endif; ?>
      </div>
      <div class="detail-info-grid">
        <div class="detail-info-box">
          <span>Cliente</span>
          <strong><?=h($customerName)?></strong>
        </div>
        <div class="detail-info-box">
          <span>Status</span>
          <strong><span class="det-status" style="margin:0;"><i class="fa-solid fa-check"></i> Em aberto</span></strong>
        </div>
        <div class="detail-info-box">
          <span>Filtro</span>
          <strong><?=h($monthLabel)?></strong>
        </div>
        <div class="detail-info-box">
          <span>Primeira fatura vencida</span>
          <strong><?=h(rdDateBr($oldestDue))?></strong>
        </div>
      </div>
      <div class="detail-time-line">Ultima recobranca: <?=h($lastReminder ? rdDateTimeBr($lastReminder) : 'Sem envio')?></div>
      <div class="summary-grid">
        <div class="summary-box danger"><span><i class="fa-solid fa-coins"></i> Total</span><strong><?=h(rdMoneyBr($total))?></strong><div class="summary-track"><span class="summary-fill" style="width:<?=h($totalFill)?>%"></span></div></div>
        <div class="summary-box warn"><span><i class="fa-solid fa-file-invoice"></i> Faturas</span><strong><?=h($countBills)?></strong><div class="summary-track"><span class="summary-fill" style="width:<?=h($billFill)?>%"></span></div></div>
        <div class="summary-box"><span><i class="fa-solid fa-triangle-exclamation"></i> Maior atraso</span><strong><?=h($maxDays)?> dia(s)</strong><div class="summary-track"><span class="summary-fill" style="width:<?=h($daysFill)?>%"></span></div></div>
        <div class="summary-box ok"><span><i class="fa-brands fa-whatsapp"></i> Recobrancas</span><strong><?=h($sent)?></strong><div class="summary-track"><span class="summary-fill" style="width:<?=h($sentFill)?>%"></span></div></div>
      </div>
    </div>

    <div class="det-card">
      <div class="det-card-title"><i class="fa-solid fa-file-invoice-dollar"></i> Faturas em aberto</div>
      <?php if (!$bills): ?>
        <div class="empty">Nenhuma fatura aberta para este cliente nesse filtro.</div>
      <?php else: ?>
        <div class="bill-list">
          <?php foreach ($bills as $bill):
            $billId = (int)($bill['bill_id'] ?? 0);
            $billUrl = trim((string)($bill['bill_url'] ?? ''));
            $last = $bill['last_reminder_calc'] ?? null;
          ?>
            <div class="bill-card">
              <div class="bill-main-card">
                <div class="bill-topline">
                  <?php if ($billUrl): ?>
                    <a class="bill-id" href="<?=h($billUrl)?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Bill <?=h($billId)?></a>
                  <?php else: ?>
                    <span class="bill-id"><i class="fa-solid fa-file-invoice"></i> Bill <?=h($billId)?></span>
                  <?php endif; ?>
                  <div class="bill-muted" style="margin-top:0;"><i class="fa-regular fa-calendar"></i><span>Venceu em <?=h(rdDateBr($bill['due_at'] ?? null))?></span></div>
                </div>
                <div>
                  <div class="bill-label"><i class="fa-solid fa-list-check"></i> O que esta devendo</div>
                  <div class="bill-items"><?=h(trim((string)($bill['items_text'] ?? '')) ?: 'Sem itens informados')?></div>
                </div>
              </div>
              <div class="bill-side">
                <span class="debt-snapshot">
                  <span class="snapshot-value"><i class="fa-solid fa-coins"></i> <?=h(rdMoneyBr($bill['amount_num']))?></span>
                  <span class="snapshot-meta">
                    <span><i class="fa-regular fa-calendar"></i> <?=h((int)$bill['days_overdue'])?> dia(s)</span>
                    <span><i class="fa-brands fa-whatsapp"></i> <?=h($bill['sent_num'])?> rec.</span>
                  </span>
                </span>
                <span class="mini-chip"><i class="fa-solid fa-rotate"></i> <?=h($bill['attempts_num'])?> tentativa(s)</span>
                <span class="mini-chip"><i class="fa-regular fa-clock"></i> <?=h($last ? rdDateTimeBr($last) : 'Sem envio')?></span>
                <?php if ($billUrl): ?>
                  <a class="mini-chip" href="<?=h($billUrl)?>" target="_blank" rel="noopener" style="text-decoration:none;"><i class="fa-solid fa-arrow-up-right-from-square"></i> Vindi</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="det-card">
      <div class="det-card-title"><i class="fa-solid fa-paper-plane"></i> Ultimas recobrancas</div>
      <?php if (!$recentLogs): ?>
        <div class="empty">Nenhuma recobranca registrada para essas faturas.</div>
      <?php else: ?>
        <div class="timeline">
          <?php foreach ($recentLogs as $log): ?>
            <div class="log-row">
              <span class="log-icon <?=((int)($log['ok'] ?? 0) === 1) ? '' : 'fail'?>">
                <i class="fa-solid <?=((int)($log['ok'] ?? 0) === 1) ? 'fa-check' : 'fa-xmark'?>"></i>
              </span>
              <div class="log-main">
                <strong>Bill <?=h($log['bill_id'] ?? '-')?></strong>
                <span><?=h(trim((string)($log['message'] ?? '')) ?: 'Registro de envio')?></span>
              </div>
              <span class="mini-chip"><i class="fa-regular fa-clock"></i> <?=h(rdDateTimeBr($log['created_at'] ?? null))?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <script>
    (() => {
      const btn = document.getElementById('btnReportMark');
      if (!btn) return;
      const markKey = <?=json_encode($markKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
      const customerId = <?=json_encode($customerId)?>;
      const customerName = <?=json_encode($customerName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
      const currentReason = <?=json_encode((string)($mark['reason'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
      const modal = document.getElementById('markModal');
      const reasonInput = document.getElementById('markReason');
      const errorBox = document.getElementById('markError');
      const saveBtn = document.getElementById('markSave');
      const cancelBtn = document.getElementById('markCancel');
      const openModal = () => new Promise((resolve) => {
        if (!modal || !reasonInput || !saveBtn || !cancelBtn) {
          resolve(null);
          return;
        }
        const cleanup = (value) => {
          modal.classList.remove('open');
          modal.setAttribute('aria-hidden', 'true');
          saveBtn.onclick = null;
          cancelBtn.onclick = null;
          modal.onclick = null;
          document.removeEventListener('keydown', onKey);
          resolve(value);
        };
        const onKey = (event) => {
          if (event.key === 'Escape') cleanup(null);
          if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') saveBtn.click();
        };
        if (errorBox) errorBox.textContent = '';
        reasonInput.value = currentReason;
        saveBtn.onclick = () => {
          const reason = reasonInput.value.trim();
          if (!reason) {
            if (errorBox) errorBox.textContent = 'Informe o motivo da marcacao.';
            reasonInput.focus();
            return;
          }
          cleanup(reason);
        };
        cancelBtn.onclick = () => cleanup(null);
        modal.onclick = (event) => {
          if (event.target === modal) cleanup(null);
        };
        document.addEventListener('keydown', onKey);
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => reasonInput.focus(), 40);
      });
      btn.addEventListener('click', async () => {
        const action = btn.getAttribute('data-action') || 'mark';
        let reason = '';
        if (action === 'mark') {
          const input = await openModal();
          if (input === null) return;
          reason = input;
        }
        btn.disabled = true;
        try {
          const resp = await fetch('/painel/api/report_mark.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
              action,
              mark_key: markKey,
              customer_id: customerId,
              customer_name: customerName,
              reason
            })
          });
          const text = await resp.text();
          let data = null;
          try { data = JSON.parse(text); } catch(e) {}
          if (!resp.ok || !data || data.ok === false) {
            throw new Error(data?.error || text.slice(0, 160) || 'Falha ao salvar marcacao');
          }
          window.location.reload();
        } catch (e) {
          alert(e.message || 'Falha ao salvar marcacao');
        } finally {
          btn.disabled = false;
        }
      });
    })();
  </script>
</div>
