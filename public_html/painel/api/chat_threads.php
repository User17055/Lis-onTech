<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';

function chatApiOut(array $payload, int $status = 200): void
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
    if ((string)($_GET['backfill'] ?? '0') === '1') {
        chatBackfillAutomationRuns($pdo, 300);
    }

    $limit = max(1, min(100, (int)($_GET['limit'] ?? 60)));
    $q = trim((string)($_GET['q'] ?? ''));
    $onlyUnread = (string)($_GET['unread'] ?? '0') === '1';
    $direction = strtolower(trim((string)($_GET['direction'] ?? '')));

    $where = 'WHERE 1=1';
    $params = [];

    if ($q !== '') {
        $where .= " AND (t.phone LIKE :q OR t.display_name LIKE :q OR t.last_message_preview LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    if ($onlyUnread) {
        $where .= ' AND t.unread_count > 0';
    }

    if (in_array($direction, ['in', 'out'], true)) {
        $where .= ' AND EXISTS (
            SELECT 1
            FROM chat_messages cm_filter
            WHERE cm_filter.thread_id = t.id
              AND cm_filter.direction = :direction
        )';
        $params[':direction'] = $direction;
    }

    $count = $pdo->prepare("SELECT COUNT(*) FROM chat_threads t {$where}");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $sql = "
        SELECT
            t.id,
            t.phone,
            t.display_name,
            t.last_message_preview,
            t.last_message_at,
            t.last_inbound_at,
            t.last_outbound_at,
            t.unread_count,
            t.in_review,
            t.review_updated_at,
            m.direction AS last_direction,
            m.status AS last_status,
            m.error_text AS last_error
        FROM chat_threads t
        LEFT JOIN chat_messages m
          ON m.id = (
              SELECT cm.id
              FROM chat_messages cm
              WHERE cm.thread_id = t.id
              ORDER BY cm.created_at DESC, cm.id DESC
              LIMIT 1
          )
        {$where}
        ORDER BY COALESCE(t.last_message_at, t.updated_at, t.created_at) DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row = chatThreadWithWindowInfo($row);
        $row['customer_id'] = chatFindThreadCustomerId($pdo, (int)($row['id'] ?? 0), (string)($row['phone'] ?? ''));
    }
    unset($row);

    chatApiOut([
        'ok' => true,
        'rows' => $rows,
        'total' => $total,
        'limit' => $limit,
    ]);
} catch (Throwable $e) {
    chatApiOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
