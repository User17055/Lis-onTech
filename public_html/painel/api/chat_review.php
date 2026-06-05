<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';

function chatReviewOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chatReviewOut(['ok' => false, 'error' => 'Metodo invalido'], 405);
    }

    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/../../includes/chat_db.php';

    chatEnsureTables($pdo);

    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        chatReviewOut(['ok' => false, 'error' => 'JSON invalido'], 400);
    }

    $phone = chatNormalizePhone((string)($body['phone'] ?? ''));
    if (!preg_match('/^55\d{10,11}$/', $phone)) {
        chatReviewOut(['ok' => false, 'error' => 'Telefone invalido'], 400);
    }

    $inReview = !empty($body['in_review']) ? 1 : 0;
    $threadId = chatGetOrCreateThread($pdo, $phone);

    $stmt = $pdo->prepare("
        UPDATE chat_threads
        SET in_review = ?,
            review_updated_at = CASE WHEN ? = 1 THEN NOW() ELSE NULL END
        WHERE id = ?
    ");
    $stmt->execute([$inReview, $inReview, $threadId]);

    $threadStmt = $pdo->prepare("SELECT * FROM chat_threads WHERE id = ? LIMIT 1");
    $threadStmt->execute([$threadId]);
    $thread = $threadStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $threadId, 'phone' => $phone];
    $thread = chatThreadWithWindowInfo($thread);
    $thread['customer_id'] = chatFindThreadCustomerId($pdo, (int)($thread['id'] ?? 0), $phone);

    chatReviewOut([
        'ok' => true,
        'thread' => $thread,
    ]);
} catch (Throwable $e) {
    chatReviewOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
