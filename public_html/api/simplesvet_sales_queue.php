<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/simplesvet_sales.php';

apiCors($cfg);
apiRequirePost();
apiRequireBearer($cfg);

function svSalesUuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-' . dechex((hexdec($hex[16]) & 0x3) | 0x8) . substr($hex, 17, 3)
        . '-' . substr($hex, 20, 12);
}

svSalesEnsureTables($pdo);
$body = apiJsonBody();
$action = strtolower(trim((string)($body['action'] ?? '')));
$maxAttempts = max(1, min(20, (int)cfg($cfg, 'SIMPLESVET_SALES_MAX_ATTEMPTS', '5')));

try {
    if ($action === 'claim') {
        $limit = max(1, min(20, (int)($body['limit'] ?? 5)));
        $leaseToken = svSalesUuid();
        $pdo->beginTransaction();
        // Uma queda pode ocorrer depois de a venda ter sido criada no SimplesVet.
        // Por seguranca, tarefa abandonada nunca volta automaticamente para criacao.
        $pdo->exec("
            UPDATE simplesvet_sale_jobs
               SET status='manual_review',
                   last_error='Execucao interrompida; confirme no SimplesVet usando a referencia VINDI antes de tentar novamente',
                   lease_token=NULL, leased_at=NULL, next_attempt_at=NULL
             WHERE status='processing' AND leased_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        ");
        $stmt = $pdo->prepare("
            SELECT id
              FROM simplesvet_sale_jobs
             WHERE status IN ('pending','retry')
               AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
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
                $bill = is_array($decoded) ? ($decoded['event']['data']['bill'] ?? []) : [];
                $job['product_mappings'] = is_array($bill)
                    ? svSalesMappingsForItems($pdo, svSalesBillItems($bill))
                    : [];
                unset($job['source_payload']);
            }
            unset($job);
        }
        apiOut(['ok' => true, 'jobs' => $jobs]);
    }

    if (!in_array($action, ['checkpoint', 'complete', 'fail'], true)) {
        apiOut(['ok' => false, 'error' => 'Acao invalida'], 400);
    }

    $id = (int)($body['id'] ?? 0);
    $leaseToken = trim((string)($body['lease_token'] ?? ''));
    if ($id <= 0 || $leaseToken === '') {
        apiOut(['ok' => false, 'error' => 'Id ou lease_token invalido'], 400);
    }

    $check = $pdo->prepare("SELECT attempts, status FROM simplesvet_sale_jobs WHERE id=? AND status IN ('processing','awaiting_receipt') AND lease_token=? LIMIT 1");
    $check->execute([$id, $leaseToken]);
    $activeJob = $check->fetch(PDO::FETCH_ASSOC);
    if (!$activeJob) apiOut(['ok' => false, 'error' => 'Tarefa nao encontrada ou lease expirado'], 409);
    $attempts = (int)$activeJob['attempts'];

    if ($action === 'checkpoint') {
        if ($activeJob['status'] !== 'processing') apiOut(['ok' => false, 'error' => 'Checkpoint ja registrado'], 409);
        $saleId = mb_substr(trim((string)($body['simplesvet_sale_id'] ?? '')), 0, 80);
        if ($saleId === '') apiOut(['ok' => false, 'error' => 'Codigo da venda SimplesVet obrigatorio'], 400);
        $result = json_encode($body['result'] ?? ['stage' => 'sale_created'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare("
            UPDATE simplesvet_sale_jobs
               SET status='awaiting_receipt', simplesvet_sale_id=?, result_payload=?
             WHERE id=? AND status='processing' AND lease_token=?
        ");
        $stmt->execute([$saleId, $result ?: null, $id, $leaseToken]);
        apiOut(['ok' => true, 'status' => 'awaiting_receipt']);
    }

    if ($action === 'complete') {
        $result = json_encode($body['result'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $saleId = mb_substr(trim((string)($body['simplesvet_sale_id'] ?? '')), 0, 80);
        $stmt = $pdo->prepare("
            UPDATE simplesvet_sale_jobs
               SET status='completed', simplesvet_sale_id=?, result_payload=?,
                   completed_at=NOW(), next_attempt_at=NULL, lease_token=NULL, leased_at=NULL
             WHERE id=? AND status IN ('processing','awaiting_receipt') AND lease_token=?
        ");
        $stmt->execute([$saleId !== '' ? $saleId : null, $result ?: null, $id, $leaseToken]);
        apiOut(['ok' => true]);
    }

    $error = mb_substr(trim((string)($body['error'] ?? 'Falha nao informada')), 0, 8000);
    $manualReview = !empty($body['manual_review']) || (int)$attempts >= $maxAttempts;
    $stmt = $pdo->prepare("
        UPDATE simplesvet_sale_jobs
           SET status=?, last_error=?, next_attempt_at=?, lease_token=NULL, leased_at=NULL
         WHERE id=? AND status IN ('processing','awaiting_receipt') AND lease_token=?
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
