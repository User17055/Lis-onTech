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

function chatMessageMediaInfo(array $message): ?array
{
    $type = strtolower((string)($message['message_type'] ?? ''));
    if (!in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
        return null;
    }

    $payload = json_decode((string)($message['payload_json'] ?? ''), true);
    if (!is_array($payload)) {
        return ['type' => $type];
    }

    $media = $payload[$type] ?? null;
    if (!is_array($media)) {
        return ['type' => $type];
    }

    $filename = (string)($media['filename'] ?? '');
    if ($filename === '' && $type === 'document') {
        $body = trim((string)($message['body'] ?? ''));
        $filename = preg_replace('/^\[documento\]\s*/i', '', $body) ?? '';
    }

    return [
        'type' => $type,
        'filename' => $filename,
        'mime_type' => (string)($media['mime_type'] ?? ''),
        'caption' => (string)($media['caption'] ?? ''),
    ];
}

function chatMessageContactInfo(array $message): array
{
    $type = strtolower((string)($message['message_type'] ?? ''));
    if ($type !== 'contacts') {
        return [];
    }

    $payload = json_decode((string)($message['payload_json'] ?? ''), true);
    $contacts = is_array($payload) && is_array($payload['contacts'] ?? null) ? $payload['contacts'] : [];
    $items = [];

    foreach ($contacts as $contact) {
        if (!is_array($contact)) continue;

        $nameData = is_array($contact['name'] ?? null) ? $contact['name'] : [];
        $name = trim((string)($nameData['formatted_name'] ?? ''));
        if ($name === '') {
            $parts = [
                trim((string)($nameData['first_name'] ?? '')),
                trim((string)($nameData['middle_name'] ?? '')),
                trim((string)($nameData['last_name'] ?? '')),
            ];
            $name = trim(implode(' ', array_filter($parts, static fn($part) => $part !== '')));
        }
        if ($name === '') {
            $name = trim((string)($contact['profile']['name'] ?? ''));
        }

        $phones = [];
        $phoneRows = is_array($contact['phones'] ?? null) ? $contact['phones'] : [];
        foreach ($phoneRows as $phoneRow) {
            if (!is_array($phoneRow)) continue;
            $phone = trim((string)($phoneRow['phone'] ?? ''));
            $waId = trim((string)($phoneRow['wa_id'] ?? ''));
            $label = trim((string)($phoneRow['type'] ?? ''));
            if ($phone === '' && $waId !== '') $phone = $waId;
            if ($phone === '') continue;
            $phones[] = [
                'phone' => $phone,
                'wa_id' => $waId,
                'label' => $label,
            ];
        }

        $items[] = [
            'name' => $name !== '' ? $name : 'Contato',
            'phones' => $phones,
        ];
    }

    return $items;
}

function chatMessageReactionInfo(array $message): ?array
{
    $type = strtolower((string)($message['message_type'] ?? ''));
    if ($type !== 'reaction') {
        return null;
    }

    $payload = json_decode((string)($message['payload_json'] ?? ''), true);
    $reaction = is_array($payload) && is_array($payload['reaction'] ?? null) ? $payload['reaction'] : [];
    return [
        'emoji' => trim((string)($reaction['emoji'] ?? '')),
        'message_id' => (string)($reaction['message_id'] ?? ''),
    ];
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
    $thread['customer_id'] = chatFindThreadCustomerId($pdo, (int)($thread['id'] ?? 0), $phone);

    $stmt = $pdo->prepare("
        SELECT *
        FROM (
            SELECT
                id, phone, direction, message_type, body, meta_message_id,
                status, status_at, sent_at, delivered_at, read_at, failed_at,
                error_text, http_code, source, source_ref, payload_json, created_at
            FROM chat_messages
            WHERE phone = ?
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit}
        ) recent
        ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([$phone]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($messages as &$message) {
        $message['media'] = chatMessageMediaInfo($message);
        $message['contacts'] = chatMessageContactInfo($message);
        $message['reaction'] = chatMessageReactionInfo($message);
        unset($message['payload_json']);
    }
    unset($message);

    $chargeStmt = $pdo->prepare("
        SELECT source_ref, body, created_at
        FROM chat_messages
        WHERE phone = ?
          AND direction = 'out'
          AND source IN ('automation', 'automation_backfill', 'same_resend', 'manual_resend')
          AND source_ref IS NOT NULL
          AND source_ref <> ''
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $chargeStmt->execute([$phone]);
    $lastCharge = $chargeStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    chatMessagesOut([
        'ok' => true,
        'thread' => $thread,
        'messages' => $messages,
        'last_charge' => $lastCharge,
    ]);
} catch (Throwable $e) {
    chatMessagesOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
