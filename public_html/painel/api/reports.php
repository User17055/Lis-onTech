<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(300);

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
    if (($cache[$key] ?? null) === true) return true;
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

function reportsDateTimeOrNull($value): ?string
{
    if ($value === null || $value === '') return null;
    $ts = strtotime((string)$value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
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

function reportsCurlGetWithHeaders(string $url, string $apiKey): array
{
    if (!function_exists('curl_init')) {
        return ['http' => 0, 'headers' => [], 'body' => '', 'err' => 'curl indisponivel'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($apiKey . ':'),
    ]);

    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsz = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) return ['http' => $http, 'headers' => [], 'body' => '', 'err' => $err ?: 'curl_error'];

    $headers = [];
    foreach (preg_split("/\r\n|\n|\r/", substr($raw, 0, $hsz)) as $line) {
        $p = strpos($line, ':');
        if ($p === false) continue;
        $headers[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
    }

    return ['http' => $http, 'headers' => $headers, 'body' => substr($raw, $hsz), 'err' => $err ?: null];
}

function reportsVindiBillsPage(string $base, string $apiKey, string $query, int $page, int $perPage): array
{
    $url = rtrim($base, '/') . '/bills?per_page=' . $perPage . '&page=' . $page . '&query=' . rawurlencode($query);
    $resp = reportsCurlGetWithHeaders($url, $apiKey);
    if ($resp['http'] < 200 || $resp['http'] >= 300) {
        return ['ok' => false, 'error' => "Vindi HTTP {$resp['http']}", 'raw' => substr((string)($resp['body'] ?? ''), 0, 220)];
    }

    $json = json_decode((string)($resp['body'] ?? ''), true);
    if (!is_array($json)) return ['ok' => false, 'error' => 'JSON invalido da Vindi'];

    return [
        'ok' => true,
        'bills' => is_array($json['bills'] ?? null) ? $json['bills'] : [],
        'total' => (int)($resp['headers']['total'] ?? 0),
    ];
}

function reportsVindiGetBill(string $base, string $apiKey, int $billId): ?array
{
    if ($billId <= 0) return null;
    $url = rtrim($base, '/') . '/bills/' . rawurlencode((string)$billId);
    $resp = reportsCurlGetWithHeaders($url, $apiKey);
    if ($resp['http'] < 200 || $resp['http'] >= 300) return null;

    $json = json_decode((string)($resp['body'] ?? ''), true);
    if (!is_array($json)) return null;
    $bill = $json['bill'] ?? $json;
    return is_array($bill) ? $bill : null;
}

function reportsBillItemsText(array $bill): string
{
    $items = $bill['bill_items'] ?? ($bill['items'] ?? []);
    if (!is_array($items) || !$items) return '';

    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $product = $item['product'] ?? [];
        $name = (string)($item['description'] ?? ($product['name'] ?? ($item['name'] ?? 'Item')));
        $amount = $item['amount'] ?? ($item['pricing_schema']['price'] ?? null);
        $line = trim($name);
        if ($amount !== null && $amount !== '') {
            $line .= ' - R$ ' . number_format(reportsMoney($amount), 2, ',', '.');
        }
        if ($line !== '') $lines[] = $line;
    }

    return implode("\n", $lines);
}

function reportsBillAmount(array $bill): float
{
    foreach (['amount', 'total', 'value'] as $key) {
        if (isset($bill[$key]) && $bill[$key] !== '') return reportsMoney($bill[$key]);
    }
    return 0.0;
}

function reportsRowFromVindiBill(array $bill, array $local = []): array
{
    $customer = $bill['customer'] ?? [];
    if (!is_array($customer)) $customer = [];

    $billId = (int)($bill['id'] ?? 0);
    $customerId = (int)($customer['id'] ?? ($bill['customer_id'] ?? ($local['customer_id'] ?? 0)));
    $phone = (string)($local['phone'] ?? '');
    if ($phone === '') {
        $phone = (string)($customer['phone_number'] ?? ($customer['mobile'] ?? ($customer['phone'] ?? '')));
    }

    return [
        'bill_id' => $billId,
        'customer_id' => $customerId,
        'customer_name' => (string)($customer['name'] ?? ($local['customer_name'] ?? 'Cliente')),
        'phone' => $phone,
        'bill_url' => (string)($bill['url'] ?? ($local['bill_url'] ?? '')),
        'items_text' => reportsBillItemsText($bill) ?: (string)($local['items_text'] ?? ''),
        'amount' => reportsBillAmount($bill) ?: reportsMoney($local['amount'] ?? 0),
        'due_at' => reportsDateTimeOrNull($bill['due_at'] ?? ($local['due_at'] ?? null)),
        'status' => (string)($bill['status'] ?? ($local['status'] ?? 'pending')),
        'active' => 1,
        'blocked' => (int)($local['blocked'] ?? 0),
        'reminder_count' => (int)($local['reminder_count'] ?? 0),
        'overdue_sent_count' => (int)($local['overdue_sent_count'] ?? 0),
        'reminder_attempts' => (int)($local['reminder_attempts'] ?? 0),
        'last_overdue_sent_at' => $local['last_overdue_sent_at'] ?? null,
        'last_reminder_sent_at' => $local['last_reminder_sent_at'] ?? null,
        'next_reminder_at' => $local['next_reminder_at'] ?? null,
        'updated_at' => $local['updated_at'] ?? null,
        '_source' => !empty($local) ? 'vindi+local' : 'vindi',
    ];
}

function reportsNewPdo(array $cfg): PDO
{
    $host = cfg($cfg, 'DB_HOST');
    $name = cfg($cfg, 'DB_NAME');
    $user = cfg($cfg, 'DB_USER');
    $pass = cfg($cfg, 'DB_PASS');
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ]);
}

function reportsEnsurePdo(PDO &$pdo, array $cfg): void
{
    try {
        $pdo->query('SELECT 1');
    } catch (Throwable $e) {
        $pdo = reportsNewPdo($cfg);
    }
}

function reportsEnsureBillRemindersStorage(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bill_reminders (
            bill_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(180) NULL,
            phone VARCHAR(32) NULL,
            bill_url VARCHAR(500) NULL,
            items_text TEXT NULL,
            amount DECIMAL(14,2) NULL,
            due_at DATETIME NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            blocked TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
            next_reminder_at DATETIME NULL,
            reminder_count INT UNSIGNED NOT NULL DEFAULT 0,
            overdue_sent_count INT UNSIGNED NOT NULL DEFAULT 0,
            reminder_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_overdue_sent_at DATETIME NULL,
            last_reminder_sent_at DATETIME NULL,
            last_status VARCHAR(30) NULL,
            last_status_check_at DATETIME NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (bill_id),
            KEY idx_bill_reminders_customer (customer_id),
            KEY idx_bill_reminders_status_due (status, due_at),
            KEY idx_bill_reminders_active_due (active, due_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = [
        'customer_id' => 'BIGINT UNSIGNED NULL',
        'customer_name' => 'VARCHAR(180) NULL',
        'phone' => 'VARCHAR(32) NULL',
        'bill_url' => 'VARCHAR(500) NULL',
        'items_text' => 'TEXT NULL',
        'amount' => 'DECIMAL(14,2) NULL',
        'due_at' => 'DATETIME NULL',
        'active' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'blocked' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'unpaid'",
        'next_reminder_at' => 'DATETIME NULL',
        'reminder_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'overdue_sent_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'reminder_attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'last_overdue_sent_at' => 'DATETIME NULL',
        'last_reminder_sent_at' => 'DATETIME NULL',
        'last_status' => 'VARCHAR(30) NULL',
        'last_status_check_at' => 'DATETIME NULL',
        'paid_at' => 'DATETIME NULL',
        'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];

    foreach ($columns as $column => $definition) {
        if (!reportsColumnExists($pdo, 'bill_reminders', $column)) {
            $pdo->exec("ALTER TABLE bill_reminders ADD COLUMN {$column} {$definition}");
        }
    }
}

function reportsUpsertStatement(PDO $pdo): PDOStatement
{
    return $pdo->prepare("
        INSERT INTO bill_reminders (
            bill_id, customer_id, customer_name, phone, bill_url, items_text,
            amount, due_at, active, blocked, status, next_reminder_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, COALESCE(?, 0), ?, ?)
        ON DUPLICATE KEY UPDATE
            customer_id = COALESCE(VALUES(customer_id), customer_id),
            customer_name = COALESCE(NULLIF(VALUES(customer_name), ''), customer_name),
            phone = COALESCE(NULLIF(VALUES(phone), ''), phone),
            bill_url = COALESCE(NULLIF(VALUES(bill_url), ''), bill_url),
            items_text = COALESCE(NULLIF(VALUES(items_text), ''), items_text),
            amount = COALESCE(VALUES(amount), amount),
            due_at = COALESCE(VALUES(due_at), due_at),
            active = 1,
            blocked = COALESCE(blocked, VALUES(blocked)),
            status = COALESCE(NULLIF(VALUES(status), ''), status),
            next_reminder_at = COALESCE(next_reminder_at, VALUES(next_reminder_at)),
            updated_at = NOW()
    ");
}

function reportsUpsertLocalBills(PDO &$pdo, array $rows, array $cfg): int
{
    if (!$rows) return 0;
    reportsEnsurePdo($pdo, $cfg);
    reportsEnsureBillRemindersStorage($pdo);
    $stmt = reportsUpsertStatement($pdo);

    $saved = 0;
    foreach ($rows as $row) {
        $billId = (int)($row['bill_id'] ?? 0);
        if ($billId <= 0) continue;
        $params = [
            $billId,
            ((int)($row['customer_id'] ?? 0)) ?: null,
            (string)($row['customer_name'] ?? 'Cliente'),
            (string)($row['phone'] ?? ''),
            (string)($row['bill_url'] ?? ''),
            (string)($row['items_text'] ?? ''),
            reportsMoney($row['amount'] ?? 0),
            reportsDateTimeOrNull($row['due_at'] ?? null),
            (int)($row['blocked'] ?? 0),
            (string)($row['status'] ?? 'pending'),
            $row['next_reminder_at'] ?? null,
        ];

        try {
            $stmt->execute($params);
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), '2006') === false && stripos($e->getMessage(), 'server has gone away') === false) {
                throw $e;
            }
            $pdo = reportsNewPdo($cfg);
            reportsEnsureBillRemindersStorage($pdo);
            $stmt = reportsUpsertStatement($pdo);
            $stmt->execute($params);
        }
        $saved++;
    }

    return $saved;
}

function reportsStatusIsSettled(string $status): bool
{
    $s = strtolower(trim($status));
    return in_array($s, ['paid', 'canceled', 'cancelled'], true);
}

function reportsVindiBillPaidAt(array $bill): ?string
{
    foreach (['paid_at', 'updated_at', 'created_at'] as $key) {
        $value = reportsDateTimeOrNull($bill[$key] ?? null);
        if ($value) return $value;
    }
    return null;
}

function reportsFetchStatusCheckRows(PDO $pdo, int $limit): array
{
    if (!reportsTableExists($pdo, 'bill_reminders')) return [];
    reportsEnsureBillRemindersStorage($pdo);

    $stmt = $pdo->prepare("
        SELECT bill_id
        FROM bill_reminders
        WHERE COALESCE(active, 1) = 1
          AND LOWER(COALESCE(NULLIF(status, ''), 'unpaid')) IN ('unpaid','pending','overdue')
        ORDER BY
          CASE WHEN last_status_check_at IS NULL THEN 0 ELSE 1 END ASC,
          last_status_check_at ASC,
          due_at ASC
        LIMIT {$limit}
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function reportsRefreshLocalStatuses(PDO &$pdo, array $cfg, string $base, string $apiKey, int $limit): array
{
    if ($apiKey === '' || $limit <= 0) {
        return ['checked' => 0, 'settled' => 0, 'settled_ids' => []];
    }

    reportsEnsurePdo($pdo, $cfg);
    $rows = reportsFetchStatusCheckRows($pdo, $limit);
    if (!$rows) return ['checked' => 0, 'settled' => 0, 'settled_ids' => []];

    $markOpen = $pdo->prepare("
        UPDATE bill_reminders
        SET last_status = ?, last_status_check_at = NOW()
        WHERE bill_id = ?
    ");
    $markSettled = $pdo->prepare("
        UPDATE bill_reminders
        SET active = 0,
            blocked = 0,
            status = ?,
            last_status = ?,
            last_status_check_at = NOW(),
            paid_at = COALESCE(?, paid_at),
            next_reminder_at = NULL,
            updated_at = NOW()
        WHERE bill_id = ?
    ");

    $checked = 0;
    $settled = 0;
    $settledIds = [];
    foreach ($rows as $row) {
        $billId = (int)($row['bill_id'] ?? 0);
        if ($billId <= 0) continue;

        $bill = reportsVindiGetBill($base, $apiKey, $billId);
        if (!$bill) continue;

        $checked++;
        $status = strtolower(trim((string)($bill['status'] ?? '')));
        if (reportsStatusIsSettled($status)) {
            $localStatus = $status === 'cancelled' ? 'canceled' : $status;
            $markSettled->execute([$localStatus, $status, reportsVindiBillPaidAt($bill), $billId]);
            $settled++;
            $settledIds[] = $billId;
        } else {
            $markOpen->execute([$status ?: 'pending', $billId]);
        }
    }

    return ['checked' => $checked, 'settled' => $settled, 'settled_ids' => $settledIds];
}

function reportsMonthLabel(string $month): string
{
    static $names = [
        '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Marco', '04' => 'Abril',
        '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
        '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro',
    ];
    if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) return $month;
    return ($names[$m[2]] ?? $m[2]) . ' ' . $m[1];
}

function reportsAvailableMonths(PDO $pdo): array
{
    if (!reportsTableExists($pdo, 'bill_reminders') || !reportsColumnExists($pdo, 'bill_reminders', 'due_at')) {
        return [];
    }

    $where = ["due_at IS NOT NULL"];
    if (reportsColumnExists($pdo, 'bill_reminders', 'active')) {
        $where[] = 'COALESCE(active, 1) = 1';
    }
    if (reportsColumnExists($pdo, 'bill_reminders', 'status')) {
        $where[] = "LOWER(COALESCE(NULLIF(status, ''), 'unpaid')) IN ('unpaid','pending','overdue')";
    }

    $stmt = $pdo->query("
        SELECT DATE_FORMAT(due_at, '%Y-%m') AS month_key, COUNT(*) AS total
        FROM bill_reminders
        WHERE " . implode(' AND ', $where) . "
        GROUP BY DATE_FORMAT(due_at, '%Y-%m')
        ORDER BY month_key DESC
    ");

    $months = [];
    $currentMonth = date('Y-m');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string)($row['month_key'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $key)) continue;
        if ($key > $currentMonth) continue;
        $months[] = [
            'value' => $key,
            'label' => reportsMonthLabel($key),
            'total' => (int)($row['total'] ?? 0),
        ];
    }
    return $months;
}

function reportsMonthRange(string $month): ?array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) return null;
    $start = DateTime::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00');
    if (!$start) return null;
    $end = (clone $start)->modify('first day of next month');
    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

function reportsVisibleEndExclusive(): string
{
    return (new DateTime('first day of next month 00:00:00'))->format('Y-m-d H:i:s');
}

function reportsFilterVisibleMonths(array $rows): array
{
    $endTs = strtotime(reportsVisibleEndExclusive());
    return array_values(array_filter($rows, function (array $row) use ($endTs): bool {
        $due = $row['due_at'] ?? null;
        if (!$due) return true;
        $ts = strtotime((string)$due);
        return !$ts || $ts < $endTs;
    }));
}

function reportsFilterRowsByMonth(array $rows, string $month): array
{
    $range = reportsMonthRange($month);
    if (!$range) return $rows;
    [$start, $end] = $range;
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    return array_values(array_filter($rows, function (array $row) use ($startTs, $endTs): bool {
        $ts = strtotime((string)($row['due_at'] ?? ''));
        return $ts && $ts >= $startTs && $ts < $endTs;
    }));
}

function reportsFetchLocalBills(PDO $pdo, string $q, int $limit, string $month = ''): array
{
    if (!reportsTableExists($pdo, 'bill_reminders')) return [];

    $cols = [
        'bill_id', 'customer_id', 'customer_name', 'phone', 'bill_url', 'items_text',
        'amount', 'due_at', 'status', 'active', 'blocked', 'reminder_count',
        'overdue_sent_count', 'reminder_attempts', 'last_overdue_sent_at',
        'last_reminder_sent_at', 'next_reminder_at', 'last_status',
        'last_status_check_at', 'paid_at', 'updated_at',
    ];

    $select = [];
    foreach ($cols as $col) {
        $select[] = reportsColumnExists($pdo, 'bill_reminders', $col) ? "br.{$col}" : "NULL AS {$col}";
    }

    $where = [];
    $params = [];
    if (reportsColumnExists($pdo, 'bill_reminders', 'active')) {
        $where[] = 'COALESCE(br.active, 1) = 1';
    }
    if (reportsColumnExists($pdo, 'bill_reminders', 'status')) {
        $where[] = "LOWER(COALESCE(NULLIF(br.status, ''), 'unpaid')) IN ('unpaid','pending','overdue')";
    }
    if (reportsColumnExists($pdo, 'bill_reminders', 'due_at')) {
        $where[] = '(br.due_at IS NULL OR br.due_at < :visible_end)';
        $params[':visible_end'] = reportsVisibleEndExclusive();
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
    $monthRange = reportsMonthRange($month);
    if ($monthRange && reportsColumnExists($pdo, 'bill_reminders', 'due_at')) {
        $where[] = 'br.due_at >= :month_start AND br.due_at < :month_end';
        $params[':month_start'] = $monthRange[0];
        $params[':month_end'] = $monthRange[1];
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $order = reportsColumnExists($pdo, 'bill_reminders', 'due_at') ? 'br.due_at ASC' : 'br.bill_id DESC';
    $stmt = $pdo->prepare("
        SELECT " . implode(",\n               ", $select) . "
        FROM bill_reminders br
        {$whereSql}
        ORDER BY {$order}
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) $row['_source'] = 'local';
    unset($row);
    return $rows;
}

function reportsFetchLogMap(PDO $pdo, array $ids): array
{
    if (!$ids || !reportsTableExists($pdo, 'reminder_logs')) return [];
    if (
        !reportsColumnExists($pdo, 'reminder_logs', 'bill_id')
        || !reportsColumnExists($pdo, 'reminder_logs', 'ok')
        || !reportsColumnExists($pdo, 'reminder_logs', 'created_at')
    ) {
        return [];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $map = [];
    foreach (array_chunk($ids, 700) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("
            SELECT
                bill_id,
                COUNT(CASE WHEN ok = 1 THEN 1 END) AS sent_count,
                COUNT(*) AS attempt_count,
                MAX(created_at) AS last_log_at
            FROM reminder_logs
            WHERE bill_id IN ({$in})
            GROUP BY bill_id
        ");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
            $map[(int)$log['bill_id']] = $log;
        }
    }
    return $map;
}

function reportsFilterRowsByText(array $rows, string $q): array
{
    if ($q === '') return $rows;
    $needle = strtolower($q);
    return array_values(array_filter($rows, function (array $row) use ($needle): bool {
        $haystack = strtolower(implode(' ', [
            $row['bill_id'] ?? '',
            $row['customer_id'] ?? '',
            $row['customer_name'] ?? '',
            $row['phone'] ?? '',
        ]));
        return strpos($haystack, $needle) !== false;
    }));
}

function reportsMonthWindows(string $from, string $to): array
{
    $start = DateTime::createFromFormat('Y-m-d H:i:s', $from . ' 00:00:00')
        ?: DateTime::createFromFormat('Y-m-d', $from)
        ?: new DateTime('2024-01-01 00:00:00');
    $end = DateTime::createFromFormat('Y-m-d H:i:s', $to . ' 23:59:59')
        ?: DateTime::createFromFormat('Y-m-d', $to)
        ?: new DateTime('today 23:59:59');

    $start->modify('first day of this month 00:00:00');
    $end->setTime(23, 59, 59);
    if ($end < $start) $end = clone $start;

    $windows = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $windowStart = clone $cursor;
        $windowEnd = (clone $cursor)->modify('last day of this month')->setTime(23, 59, 59);
        if ($windowEnd > $end) $windowEnd = clone $end;
        $windows[] = [
            'label' => $windowStart->format('Y-m'),
            'start' => $windowStart->format('Y-m-d H:i:s'),
            'end' => $windowEnd->format('Y-m-d H:i:s'),
        ];
        $cursor->modify('first day of next month 00:00:00');
    }

    return $windows;
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $sync = (string)($_GET['sync'] ?? '0') === '1';
    $month = trim((string)($_GET['month'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = '';
    if ($month !== '' && $month > date('Y-m')) $month = '';
    $localLimit = max(1000, min(20000, (int)($_GET['local_limit'] ?? 20000)));
    $maxPages = max(1, min(200, (int)($_GET['max_pages'] ?? 80)));
    $defaultRecentFrom = cfg($cfg, 'REPORTS_VINDI_SYNC_FROM', '2024-01-01');
    $syncFrom = trim((string)($_GET['sync_from'] ?? $defaultRecentFrom));
    if ($syncFrom === '') $syncFrom = $defaultRecentFrom;
    $syncTo = trim((string)($_GET['sync_to'] ?? date('Y-m-d')));
    $statusCheckLimit = max(0, min(5000, (int)($_GET['status_limit'] ?? 1000)));
    $perPage = 50;
    $savedLocal = 0;
    $statusRefresh = ['checked' => 0, 'settled' => 0, 'settled_ids' => []];

    $localRows = reportsFetchLocalBills($pdo, $q, $localLimit, $month);
    $localByBill = [];
    foreach ($localRows as $row) {
        $billId = (int)($row['bill_id'] ?? 0);
        if ($billId > 0) $localByBill[$billId] = $row;
    }

    $rowsByBill = $localByBill;
    $vindiMeta = [
        'enabled' => false,
        'ok' => false,
        'pages_read' => 0,
        'bills_read' => 0,
        'reported_total' => 0,
        'stopped_by' => $sync ? 'not_configured' : 'local_only',
        'error' => null,
        'saved_local' => 0,
        'windows_read' => 0,
        'window_pages_read' => 0,
        'sync_from' => $syncFrom,
        'sync_to' => $syncTo,
        'last_window' => null,
        'status_checked' => 0,
        'settled_local' => 0,
    ];

    $apiKey = cfg($cfg, 'VINDI_API_KEY');
    if ($sync && $apiKey !== '') {
        $vindiMeta['enabled'] = true;
        $base = cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1');
        $windows = reportsMonthWindows($syncFrom, $syncTo);

        foreach ($windows as $window) {
            $vindiMeta['windows_read']++;
            $vindiMeta['last_window'] = $window['label'];
            $query = 'status=pending AND due_at>="' . $window['start'] . '" AND due_at<="' . $window['end'] . '"';

            for ($page = 1; $page <= $maxPages; $page++) {
                $resp = reportsVindiBillsPage($base, $apiKey, $query, $page, $perPage);
                if (!($resp['ok'] ?? false)) {
                    $vindiMeta['error'] = (string)($resp['error'] ?? 'Falha na Vindi');
                    $vindiMeta['stopped_by'] = 'error';
                    break 2;
                }

                $bills = is_array($resp['bills'] ?? null) ? $resp['bills'] : [];
                $vindiMeta['ok'] = true;
                $vindiMeta['pages_read']++;
                $vindiMeta['window_pages_read'] = $page;
                $vindiMeta['bills_read'] += count($bills);
                $vindiMeta['reported_total'] += (int)($resp['total'] ?? 0);

                if (!$bills) {
                    break;
                }

                $pageRowsForSave = [];
                foreach ($bills as $bill) {
                    if (!is_array($bill)) continue;
                    $billId = (int)($bill['id'] ?? 0);
                    if ($billId <= 0) continue;
                    $merged = reportsRowFromVindiBill($bill, $localByBill[$billId] ?? []);
                    $rowsByBill[$billId] = $merged;
                    $localByBill[$billId] = $merged;
                    $pageRowsForSave[$billId] = $merged;
                }

                if ($pageRowsForSave) {
                    $savedNow = reportsUpsertLocalBills($pdo, array_values($pageRowsForSave), $cfg);
                    $savedLocal += $savedNow;
                    $vindiMeta['saved_local'] = $savedLocal;
                }

                $reportedTotal = (int)($resp['total'] ?? 0);
                if ($reportedTotal > 0 && ($page * $perPage) >= $reportedTotal) {
                    break;
                }
                if ($page === $maxPages) {
                    $vindiMeta['stopped_by'] = 'max_pages_in_window';
                }
            }
        }

        if ($vindiMeta['stopped_by'] === 'not_configured') {
            $vindiMeta['stopped_by'] = 'date_windows_done';
        }

        $statusRefresh = reportsRefreshLocalStatuses($pdo, $cfg, $base, $apiKey, $statusCheckLimit);
        $vindiMeta['status_checked'] = (int)($statusRefresh['checked'] ?? 0);
        $vindiMeta['settled_local'] = (int)($statusRefresh['settled'] ?? 0);
        foreach (($statusRefresh['settled_ids'] ?? []) as $settledId) {
            unset($rowsByBill[(int)$settledId]);
        }

    } elseif ($sync && $apiKey === '') {
        $vindiMeta['enabled'] = false;
        $vindiMeta['stopped_by'] = 'not_configured';
        $vindiMeta['error'] = 'VINDI_API_KEY nao configurada';
    }

    $bills = reportsFilterRowsByMonth(reportsFilterVisibleMonths(reportsFilterRowsByText(array_values($rowsByBill), $q)), $month);
    reportsEnsurePdo($pdo, $cfg);
    $availableMonths = reportsAvailableMonths($pdo);
    $logMap = reportsFetchLogMap($pdo, array_column($bills, 'bill_id'));

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
                'sources' => [],
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
        $groups[$key]['sources'][(string)($bill['_source'] ?? 'local')] = true;

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
            'source' => (string)($bill['_source'] ?? 'local'),
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
        $row['sources'] = array_keys($row['sources']);
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
        'meta' => [
            'local_rows' => count($localRows),
            'merged_bills' => count($bills),
            'vindi' => $vindiMeta,
            'max_pages' => $maxPages,
            'per_page' => $perPage,
            'sync' => $sync,
            'saved_local' => $savedLocal,
            'status_refresh' => $statusRefresh,
            'available_months' => $availableMonths,
            'selected_month' => $month,
        ],
    ]);
} catch (Throwable $e) {
    reportsOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
