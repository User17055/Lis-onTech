<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../db.php';

apiCors($cfg);
apiRequirePost();
apiRequireBearer($cfg);

$body = apiJsonBody();
$items = $body['items'] ?? null;
if (!is_array($items) || count($items) > 5000) {
    apiOut(['ok' => false, 'error' => 'Lista de itens invalida'], 400);
}

$allowedStatuses = ['pending', 'processing', 'retry', 'synced', 'manual_review'];
$generatedAt = trim((string)($body['generated_at'] ?? ''));
$batch = hash('sha256', $generatedAt . '|' . microtime(true) . '|' . random_int(1, PHP_INT_MAX));

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS simplesvet_sync_status (
            customer_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(220) NOT NULL DEFAULT '',
            desired_marked TINYINT(1) NOT NULL DEFAULT 0,
            applied_marked TINYINT(1) NULL,
            status VARCHAR(32) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_action VARCHAR(16) NULL,
            last_error TEXT NULL,
            last_attempt_at DATETIME NULL,
            synced_at DATETIME NULL,
            source_updated_at DATETIME NULL,
            report_generated_at DATETIME NULL,
            sync_batch CHAR(64) NOT NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (customer_id),
            KEY idx_simplesvet_status (status),
            KEY idx_simplesvet_received (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS simplesvet_sync_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            report_generated_at DATETIME NULL,
            total_count INT UNSIGNED NOT NULL DEFAULT 0,
            success_count INT UNSIGNED NOT NULL DEFAULT 0,
            pending_count INT UNSIGNED NOT NULL DEFAULT 0,
            retry_count INT UNSIGNED NOT NULL DEFAULT 0,
            manual_review_count INT UNSIGNED NOT NULL DEFAULT 0,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_simplesvet_runs_received (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $upsert = $pdo->prepare("
        INSERT INTO simplesvet_sync_status (
            customer_id, customer_name, desired_marked, applied_marked, status,
            attempts, last_action, last_error, last_attempt_at, synced_at,
            source_updated_at, report_generated_at, sync_batch, received_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            customer_name=VALUES(customer_name), desired_marked=VALUES(desired_marked),
            applied_marked=VALUES(applied_marked), status=VALUES(status), attempts=VALUES(attempts),
            last_action=VALUES(last_action), last_error=VALUES(last_error),
            last_attempt_at=VALUES(last_attempt_at), synced_at=VALUES(synced_at),
            source_updated_at=VALUES(source_updated_at), report_generated_at=VALUES(report_generated_at),
            sync_batch=VALUES(sync_batch), received_at=NOW()
    ");
    $asDate = static function ($value, bool $sourceIsUtc = false): ?string {
        $value = trim((string)$value);
        if ($value === '') return null;
        try {
            $hasTimezone = (bool)preg_match('/(?:Z|[+\-]\d{2}:?\d{2})$/i', $value);
            $sourceTimezone = $sourceIsUtc && !$hasTimezone
                ? new DateTimeZone('UTC')
                : new DateTimeZone('America/Sao_Paulo');
            $date = new DateTimeImmutable($value, $sourceTimezone);
            return $date->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    };

    $pdo->beginTransaction();
    $saved = 0;
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $customerId = (int)($item['customer_id'] ?? 0);
        $status = trim((string)($item['status'] ?? ''));
        if ($customerId <= 0 || !in_array($status, $allowedStatuses, true)) continue;
        $applied = $item['applied_marked'] ?? null;
        $lastAction = trim((string)($item['last_action'] ?? ''));
        $lastError = trim((string)($item['last_error'] ?? ''));
        $upsert->execute([
            $customerId,
            mb_substr(trim((string)($item['customer_name'] ?? '')), 0, 220),
            !empty($item['desired_marked']) ? 1 : 0,
            $applied === null ? null : (!empty($applied) ? 1 : 0),
            $status,
            max(0, (int)($item['attempts'] ?? 0)),
            $lastAction === '' ? null : mb_substr($lastAction, 0, 16),
            $lastError === '' ? null : mb_substr($lastError, 0, 8000),
            $asDate($item['last_attempt_at'] ?? null, true),
            $asDate($item['synced_at'] ?? null, true),
            $asDate($item['updated_at'] ?? null, true),
            $asDate($generatedAt),
            $batch,
        ]);
        $saved++;
    }
    if ($saved > 0) {
        $delete = $pdo->prepare('DELETE FROM simplesvet_sync_status WHERE sync_batch <> ?');
        $delete->execute([$batch]);
    }

    $summary = is_array($body['summary'] ?? null) ? $body['summary'] : [];
    $run = $pdo->prepare("
        INSERT INTO simplesvet_sync_runs (
            report_generated_at, total_count, success_count, pending_count, retry_count, manual_review_count
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $run->execute([
        $asDate($generatedAt), max(0, (int)($summary['total'] ?? $saved)),
        max(0, (int)($summary['synced'] ?? 0)), max(0, (int)($summary['pending'] ?? 0)),
        max(0, (int)($summary['retry'] ?? 0)), max(0, (int)($summary['manual_review'] ?? 0)),
    ]);
    $pdo->commit();
    apiOut(['ok' => true, 'saved' => $saved]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiOut(['ok' => false, 'error' => 'Falha ao salvar relatorio do SimplesVet'], 500);
}
