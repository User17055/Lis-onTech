<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';

function chatMediaBackfillOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chatMediaBackfillOut(['ok' => false, 'error' => 'Metodo invalido'], 405);
    }

    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/../../includes/chat_db.php';
    require_once __DIR__ . '/../../includes/chat_media_cache.php';

    chatEnsureTables($pdo);

    $token = cfg($cfg, 'META_ACCESS_TOKEN');
    if ($token === '') {
        chatMediaBackfillOut(['ok' => false, 'error' => 'META_ACCESS_TOKEN nao configurado'], 500);
    }

    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = [];

    $limit = max(1, min(50, (int)($body['limit'] ?? 25)));

    $stmt = $pdo->prepare("
        SELECT id
        FROM chat_messages
        WHERE message_type IN ('image', 'video', 'audio', 'document', 'sticker')
          AND payload_json IS NOT NULL
          AND payload_json <> ''
          AND payload_json NOT LIKE '%\"local_path\"%'
        ORDER BY created_at DESC, id DESC
        LIMIT {$limit}
    ");
    $stmt->execute();
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $saved = 0;
    $already = 0;
    $failed = 0;
    $errors = [];

    foreach ($ids as $id) {
        $res = chatCacheMessageMedia($pdo, $id, $token);
        if (!empty($res['ok'])) {
            if (!empty($res['cached'])) $already++;
            else $saved++;
            continue;
        }

        $failed++;
        if (count($errors) < 8) {
            $errors[] = ['id' => $id, 'error' => (string)($res['error'] ?? 'Falha ao salvar midia')];
        }
    }

    chatMediaBackfillOut([
        'ok' => true,
        'checked' => count($ids),
        'saved' => $saved,
        'already' => $already,
        'failed' => $failed,
        'errors' => $errors,
    ]);
} catch (Throwable $e) {
    chatMediaBackfillOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
