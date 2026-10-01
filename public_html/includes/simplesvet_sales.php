<?php
declare(strict_types=1);

function svSalesEnsureTables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS simplesvet_product_mappings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mapping_key CHAR(64) NOT NULL,
            vindi_product_id BIGINT UNSIGNED NULL,
            vindi_product_code VARCHAR(120) NULL,
            vindi_product_name VARCHAR(255) NOT NULL,
            simplesvet_product_code VARCHAR(120) NULL,
            simplesvet_product_name VARCHAR(255) NULL,
            mapping_status VARCHAR(24) NOT NULL DEFAULT 'pending',
            seen_count INT UNSIGNED NOT NULL DEFAULT 1,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sv_mapping_key (mapping_key),
            KEY idx_sv_mapping_status (mapping_status),
            KEY idx_sv_mapping_product (vindi_product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS simplesvet_sale_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bill_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(220) NOT NULL DEFAULT '',
            amount DECIMAL(14,2) NULL,
            paid_at DATETIME NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'waiting_mapping',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            next_attempt_at DATETIME NULL,
            lease_token CHAR(36) NULL,
            leased_at DATETIME NULL,
            simplesvet_sale_id VARCHAR(80) NULL,
            last_error TEXT NULL,
            source_payload MEDIUMTEXT NULL,
            result_payload MEDIUMTEXT NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_simplesvet_sale_bill (bill_id),
            KEY idx_simplesvet_sale_queue (status, next_attempt_at),
            KEY idx_simplesvet_sale_paid (paid_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function svSalesNormalize($value): string
{
    $value = mb_strtoupper(trim((string)$value), 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return preg_replace('/\s+/', ' ', $ascii !== false ? $ascii : $value) ?? $value;
}

function svSalesItemIdentity(array $item): array
{
    $product = is_array($item['product'] ?? null) ? $item['product'] : [];
    $id = (int)($product['id'] ?? $item['product_id'] ?? 0);
    $code = trim((string)($product['code'] ?? ''));
    $name = trim((string)($product['name'] ?? $item['description'] ?? $code));
    $identity = $id > 0 ? 'id:' . $id : ($code !== '' ? 'code:' . svSalesNormalize($code) : 'name:' . svSalesNormalize($name));
    return [
        'mapping_key' => hash('sha256', $identity),
        'vindi_product_id' => $id > 0 ? $id : null,
        'vindi_product_code' => $code !== '' ? $code : null,
        'vindi_product_name' => $name !== '' ? $name : 'Produto sem nome',
    ];
}

function svSalesBillItems(array $bill): array
{
    return is_array($bill['bill_items'] ?? null) ? $bill['bill_items'] : [];
}

function svSalesObserveItems(PDO $pdo, array $items): void
{
    $stmt = $pdo->prepare("
        INSERT INTO simplesvet_product_mappings (
            mapping_key, vindi_product_id, vindi_product_code, vindi_product_name
        ) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            vindi_product_id=COALESCE(VALUES(vindi_product_id), vindi_product_id),
            vindi_product_code=COALESCE(VALUES(vindi_product_code), vindi_product_code),
            vindi_product_name=VALUES(vindi_product_name),
            seen_count=seen_count+1, last_seen_at=NOW()
    ");
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $identity = svSalesItemIdentity($item);
        $stmt->execute([$identity['mapping_key'], $identity['vindi_product_id'], $identity['vindi_product_code'], $identity['vindi_product_name']]);
    }
}

function svSalesMappingsForItems(PDO $pdo, array $items): array
{
    $keys = [];
    foreach ($items as $item) {
        if (is_array($item)) $keys[] = svSalesItemIdentity($item)['mapping_key'];
    }
    $keys = array_values(array_unique($keys));
    if (!$keys) return [];
    $marks = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT * FROM simplesvet_product_mappings WHERE mapping_key IN ({$marks})");
    $stmt->execute($keys);
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $result[$row['mapping_key']] = $row;
    return $result;
}

function svSalesResolveBill(PDO $pdo, array $bill): array
{
    $items = svSalesBillItems($bill);
    $mappings = svSalesMappingsForItems($pdo, $items);
    $mapped = 0;
    $amount = 0.0;
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $key = svSalesItemIdentity($item)['mapping_key'];
        $status = (string)($mappings[$key]['mapping_status'] ?? 'pending');
        if ($status === 'pending') return ['status' => 'waiting_mapping', 'amount' => null, 'mappings' => $mappings];
        if ($status === 'mapped') {
            $mapped++;
            $amount += max(0, (float)($item['amount'] ?? $item['pricing_schema']['price'] ?? 0));
        }
    }
    return [
        'status' => $mapped > 0 ? 'pending' : 'ignored',
        'amount' => $mapped > 0 ? $amount : null,
        'mappings' => $mappings,
    ];
}

function svSalesEnqueueBill(PDO $pdo, array $bill, array $payload, ?string $paidAt): string
{
    $billId = (int)($bill['id'] ?? 0);
    if ($billId <= 0) return 'invalid';

    svSalesEnsureTables($pdo);
    svSalesObserveItems($pdo, svSalesBillItems($bill));
    $resolved = svSalesResolveBill($pdo, $bill);
    $customer = is_array($bill['customer'] ?? null) ? $bill['customer'] : [];
    $sourcePayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($sourcePayload === false) $sourcePayload = null;

    $stmt = $pdo->prepare("
        INSERT INTO simplesvet_sale_jobs (
            bill_id, customer_id, customer_name, amount, paid_at, status,
            next_attempt_at, source_payload
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            customer_id=COALESCE(VALUES(customer_id), customer_id),
            customer_name=IF(VALUES(customer_name) <> '', VALUES(customer_name), customer_name),
            amount=VALUES(amount),
            paid_at=COALESCE(VALUES(paid_at), paid_at),
            source_payload=COALESCE(VALUES(source_payload), source_payload),
            status=CASE WHEN status IN ('completed','processing','awaiting_receipt','manual_review') THEN status ELSE VALUES(status) END,
            next_attempt_at=CASE WHEN status IN ('completed','processing','awaiting_receipt','manual_review') THEN next_attempt_at ELSE VALUES(next_attempt_at) END
    ");
    $stmt->execute([
        $billId,
        !empty($customer['id']) ? (int)$customer['id'] : null,
        mb_substr(trim((string)($customer['name'] ?? '')), 0, 220),
        $resolved['amount'],
        $paidAt,
        $resolved['status'],
        $resolved['status'] === 'pending' ? date('Y-m-d H:i:s') : null,
        $sourcePayload,
    ]);
    return $resolved['status'];
}

function svSalesRefreshJobs(PDO $pdo): int
{
    $rows = $pdo->query("
        SELECT id, source_payload FROM simplesvet_sale_jobs
         WHERE status IN ('waiting_mapping','pending','retry','ignored')
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $update = $pdo->prepare("
        UPDATE simplesvet_sale_jobs
           SET status=?, amount=?, next_attempt_at=?, attempts=CASE WHEN ?='pending' THEN 0 ELSE attempts END,
               last_error=CASE WHEN ?='pending' THEN NULL ELSE last_error END
         WHERE id=?
    ");
    $changed = 0;
    foreach ($rows as $row) {
        $payload = json_decode((string)$row['source_payload'], true);
        $bill = is_array($payload) ? ($payload['event']['data']['bill'] ?? []) : [];
        if (!is_array($bill)) continue;
        $resolved = svSalesResolveBill($pdo, $bill);
        $next = $resolved['status'] === 'pending' ? date('Y-m-d H:i:s') : null;
        $update->execute([$resolved['status'], $resolved['amount'], $next, $resolved['status'], $resolved['status'], $row['id']]);
        $changed += $update->rowCount();
    }
    return $changed;
}
