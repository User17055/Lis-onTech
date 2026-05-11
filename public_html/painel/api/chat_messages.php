<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';

function chatMessagesOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/../../includes/chat_db.php';

    chatEnsureTables($pdo);

    $phone = chatNormalizePhone((string)($_GET['phone'] ?? ''));
    if ($phone === '') {
        chatMessagesOut(['ok' => false, 'error' => 'Telefone obrigatorio'], 400);
    }

    $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));

    $threadStmt = $pdo->prepare("SELECT * FROM chat_threads WHERE phone = ? LIMIT 1");
    $threadStmt->execute([$phone]);
    $thread = $threadStmt->fetch(PDO::FETCH_ASSOC);

    if (!$thread) {
        $threadId = chatGetOrCreateThread($pdo, $phone);
        $threadStmt->execute([$phone]);
        $thread = $threadStmt->fetch(PDO::FETCH_ASSOC);
        if (!$thread) $thread = ['id' => $threadId, 'phone' => $phone, 'display_name' => null, 'unread_count' => 0];
    }

    if ((string)($_GET['mark_read'] ?? '0') === '1') {
        chatMarkThreadRead($pdo, $phone);
        $thread['unread_count'] = 0;
    }

    $thread = chatThreadWithWindowInfo($thread);

    $stmt = $pdo->prepare("
        SELECT *
        FROM (
            SELECT
                id, phone, direction, message_type, body, meta_message_id,
                status, status_at, sent_at, delivered_at, read_at, failed_at,
                error_text, http_code, source, source_ref, created_at
            FROM chat_messages
            WHERE phone = ?
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit}
        ) recent
        ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([$phone]);

    chatMessagesOut([
        'ok' => true,
        'thread' => $thread,
        'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    chatMessagesOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
