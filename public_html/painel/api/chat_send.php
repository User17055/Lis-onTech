<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

function chatSendOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chatSendOut(['ok' => false, 'error' => 'Metodo invalido'], 405);
    }

    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/../../includes/chat_db.php';

    chatEnsureTables($pdo);

    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        chatSendOut(['ok' => false, 'error' => 'JSON invalido'], 400);
    }

    $phone = chatNormalizePhone((string)($body['phone'] ?? ''));
    $message = chatCleanText((string)($body['message'] ?? ''));

    if (!preg_match('/^55\d{10,11}$/', $phone)) {
        chatSendOut(['ok' => false, 'error' => 'Telefone invalido. Use DDI+DDD+numero, ex: 5511999998888'], 400);
    }

    if ($message === '') {
        chatSendOut(['ok' => false, 'error' => 'Mensagem obrigatoria'], 400);
    }

    if ((function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message)) > 4096) {
        chatSendOut(['ok' => false, 'error' => 'Mensagem muito longa'], 400);
    }

    $threadStmt = $pdo->prepare("SELECT last_inbound_at FROM chat_threads WHERE phone = ? LIMIT 1");
    $threadStmt->execute([$phone]);
    $thread = $threadStmt->fetch(PDO::FETCH_ASSOC);
    $window = chatTextWindowInfo($thread['last_inbound_at'] ?? null);
    if (!$window['can_send_text']) {
        chatSendOut([
            'ok' => false,
            'error' => 'Janela de 24h fechada. Envie um modelo aprovado e aguarde o cliente responder.',
            'can_send_text' => false,
            'window_expires_at' => $window['window_expires_at'],
            'window_seconds_left' => 0,
        ], 403);
    }

    $phoneNumberId = cfg($cfg, 'META_PHONE_NUMBER_ID');
    $accessToken = cfg($cfg, 'META_ACCESS_TOKEN');

    if ($phoneNumberId === '' || $accessToken === '') {
        chatSendOut(['ok' => false, 'error' => 'Config META incompleta'], 500);
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'text',
        'text' => [
            'preview_url' => true,
            'body' => $message,
        ],
    ];

    $ch = curl_init("https://graph.facebook.com/v22.0/{$phoneNumberId}/messages");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $res = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $resp = json_decode((string)$res, true);
    if (!is_array($resp)) {
        $resp = ['raw' => (string)$res];
    }

    $messageId = chatSaveOutgoingMessage(
        $pdo,
        $phone,
        $message,
        $payload,
        $resp,
        $http,
        $err ?: null,
        'chat_manual'
    );

    $ok = ($http >= 200 && $http < 300) && ($err === '') && empty($resp['error']);
    if (!$ok) {
        $error = chatMetaErrorText($resp, $err ?: null);
        if ($error === '') $error = 'Falha ao enviar mensagem';
        chatSendOut([
            'ok' => false,
            'error' => $error,
            'message_id' => $messageId,
            'http' => $http,
        ], 502);
    }

    chatSendOut([
        'ok' => true,
        'message_id' => $messageId,
        'meta_message_id' => chatMetaMessageId($resp),
        'http' => $http,
    ]);
} catch (Throwable $e) {
    chatSendOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
