<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../db.php';

apiCors($cfg);
apiRequirePost();
apiRequireBearer($cfg);

function svSalesEnsureTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS simplesvet_sale_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bill_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(220) NOT NULL DEFAULT '',
            amount DECIMAL(14,2) NULL,
            paid_at DATETIME NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
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

function svSalesUuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-' . dechex((hexdec($hex[16]) & 0x3) | 0x8) . substr($hex, 17, 3)
        . '-' . substr($hex, 20, 12);
}

svSalesEnsureTable($pdo);
$body = apiJsonBody();
$action = strtolower(trim((string)($body['action'] ?? '')));
$maxAttempts = max(1, min(20, (int)cfg($cfg, 'SIMPLESVET_SALES_MAX_ATTEMPTS', '5')));

try {
    if ($action === 'claim') {
        $limit = max(1, min(20, (int)($body['limit'] ?? 5)));
        $leaseToken = svSalesUuid();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT id
              FROM simplesvet_sale_jobs
             WHERE (
                    status IN ('pending','retry')
                    AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                   ) OR (
                    status='processing' AND leased_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)
                   )
             ORDER BY paid_at ASC, id ASC
             LIMIT {$limit}
             FOR UPDATE
        ");
        $stmt->execute();
        $ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $update = $pdo->prepare("
                UPDATE simplesvet_sale_jobs
                   SET status='processing', attempts=attempts+1,
                       lease_token=?, leased_at=NOW(), last_error=NULL
                 WHERE id IN ({$marks})
            ");
            $update->execute(array_merge([$leaseToken], $ids));
        }
        $pdo->commit();

        $jobs = [];
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $read = $pdo->prepare("
                SELECT id, bill_id, customer_id, customer_name, amount, paid_at,
                       attempts, lease_token, source_payload
                  FROM simplesvet_sale_jobs
                 WHERE id IN ({$marks}) AND lease_token=?
                 ORDER BY paid_at ASC, id ASC
            ");
            $read->execute(array_merge($ids, [$leaseToken]));
            $jobs = $read->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($jobs as &$job) {
                $decoded = json_decode((string)($job['source_payload'] ?? ''), true);
                $job['source'] = is_array($decoded) ? $decoded : null;
                unset($job['source_payload']);
            }
            unset($job);
        }
        apiOut(['ok' => true, 'jobs' => $jobs]);
    }

    if (!in_array($action, ['complete', 'fail'], true)) {
        apiOut(['ok' => false, 'error' => 'Acao invalida'], 400);
    }

    $id = (int)($body['id'] ?? 0);
    $leaseToken = trim((string)($body['lease_token'] ?? ''));
    if ($id <= 0 || $leaseToken === '') {
        apiOut(['ok' => false, 'error' => 'Id ou lease_token invalido'], 400);
    }

    $check = $pdo->prepare('SELECT attempts FROM simplesvet_sale_jobs WHERE id=? AND status=\'processing\' AND lease_token=? LIMIT 1');
    $check->execute([$id, $leaseToken]);
    $attempts = $check->fetchColumn();
    if ($attempts === false) apiOut(['ok' => false, 'error' => 'Tarefa nao encontrada ou lease expirado'], 409);

    if ($action === 'complete') {
        $result = json_encode($body['result'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $saleId = mb_substr(trim((string)($body['simplesvet_sale_id'] ?? '')), 0, 80);
        $stmt = $pdo->prepare("
            UPDATE simplesvet_sale_jobs
               SET status='completed', simplesvet_sale_id=?, result_payload=?,
                   completed_at=NOW(), next_attempt_at=NULL, lease_token=NULL, leased_at=NULL
             WHERE id=? AND status='processing' AND lease_token=?
        ");
        $stmt->execute([$saleId !== '' ? $saleId : null, $result ?: null, $id, $leaseToken]);
        apiOut(['ok' => true]);
    }

    $error = mb_substr(trim((string)($body['error'] ?? 'Falha nao informada')), 0, 8000);
    $manualReview = !empty($body['manual_review']) || (int)$attempts >= $maxAttempts;
    $stmt = $pdo->prepare("
        UPDATE simplesvet_sale_jobs
           SET status=?, last_error=?, next_attempt_at=?, lease_token=NULL, leased_at=NULL
         WHERE id=? AND status='processing' AND lease_token=?
    ");
    $stmt->execute([
        $manualReview ? 'manual_review' : 'retry',
        $error,
        $manualReview ? null : date('Y-m-d H:i:s', time() + 15 * 60),
        $id,
        $leaseToken,
    ]);
    apiOut(['ok' => true, 'status' => $manualReview ? 'manual_review' : 'retry']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    apiOut(['ok' => false, 'error' => 'Falha ao processar fila de vendas do SimplesVet'], 500);
}
