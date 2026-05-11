<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';

function chatTemplateOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        chatTemplateOut(['ok' => false, 'error' => 'Metodo invalido'], 405);
    }

    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/../../includes/chat_db.php';

    chatEnsureTables($pdo);

    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        chatTemplateOut(['ok' => false, 'error' => 'JSON invalido'], 400);
    }

    $phone = chatNormalizePhone((string)($body['phone'] ?? ''));
    if (!preg_match('/^55\d{10,11}$/', $phone)) {
        chatTemplateOut(['ok' => false, 'error' => 'Telefone invalido. Ex: 5511999998888'], 400);
    }

    $phoneNumberId = cfg($cfg, 'META_PHONE_NUMBER_ID');
    $accessToken = cfg($cfg, 'META_ACCESS_TOKEN');
    $templateName = cfg($cfg, 'META_TEMPLATE_CHAT_START_NAME');
    $templateLang = cfg($cfg, 'META_TEMPLATE_CHAT_START_LANG', cfg($cfg, 'META_TEMPLATE_LANG', 'pt_BR'));

    if ($phoneNumberId === '' || $accessToken === '') {
        chatTemplateOut(['ok' => false, 'error' => 'Config META incompleta'], 500);
    }

    if ($templateName === '') {
        chatTemplateOut([
            'ok' => false,
            'error' => 'Configure META_TEMPLATE_CHAT_START_NAME com um modelo aprovado para iniciar atendimento.'
        ], 400);
    }

    $params = $body['params'] ?? [];
    if (!is_array($params)) $params = [];

    $bodyParams = [];
    foreach ($params as $param) {
        $text = chatCleanText((string)$param);
        if ($text === '') continue;
        $bodyParams[] = ['type' => 'text', 'text' => $text];
    }

    $template = [
        'name' => $templateName,
        'language' => ['code' => $templateLang],
    ];
    if (!empty($bodyParams)) {
        $template['components'] = [
            ['type' => 'body', 'parameters' => $bodyParams],
        ];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'template',
        'template' => $template,
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
        chatDescribeWhatsAppPayload($payload),
        $payload,
        $resp,
        $http,
        $err ?: null,
        'chat_template'
    );

    $ok = ($http >= 200 && $http < 300) && ($err === '') && empty($resp['error']);
    if (!$ok) {
        $error = chatMetaErrorText($resp, $err ?: null);
        if ($error === '') $error = 'Falha ao enviar modelo';
        chatTemplateOut([
            'ok' => false,
            'error' => $error,
            'message_id' => $messageId,
            'http' => $http,
        ], 502);
    }

    chatTemplateOut([
        'ok' => true,
        'message_id' => $messageId,
        'meta_message_id' => chatMetaMessageId($resp),
        'http' => $http,
    ]);
} catch (Throwable $e) {
    chatTemplateOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
