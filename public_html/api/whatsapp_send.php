<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

apiCors($cfg);
apiRequirePost();
apiRequireBearer($cfg);

try {
    require_once __DIR__ . '/../db.php';
    require_once __DIR__ . '/../includes/chat_db.php';

    chatEnsureTables($pdo);
    pickupEnsureTables($pdo);

    $body = apiJsonBody();

    $phone = chatNormalizePhone((string)($body['phone'] ?? ''));
    if (!preg_match('/^55\d{10,11}$/', $phone)) {
        apiOut(['ok' => false, 'error' => 'Telefone invalido. Use DDI+DDD+numero, ex: 5511999998888'], 400);
    }

    $orderId = chatCleanText((string)($body['order_id'] ?? $body['shopify_order_id'] ?? ''));
    $orderNumber = chatCleanText((string)($body['order_number'] ?? ''));
    $customerName = chatCleanText((string)($body['customer_name'] ?? $body['name'] ?? ''));
    $event = chatCleanText((string)($body['event'] ?? ''));
    $pickupCode = pickupCleanCode((string)($body['pickup_code'] ?? $body['code'] ?? ''));

    if ($pickupCode === '' && $event === 'order_ready_for_pickup') {
        do {
            $pickupCode = pickupGenerateCode();
            $st = $pdo->prepare("SELECT COUNT(*) FROM pickup_orders WHERE pickup_code = ?");
            $st->execute([$pickupCode]);
        } while ((int)$st->fetchColumn() > 0);
    }

    if ($orderId !== '' && $pickupCode !== '') {
        $expiresDaysRaw = cfg($cfg, 'PICKUP_CODE_EXPIRES_DAYS', '0');
        $expiresDays = is_numeric($expiresDaysRaw) ? (int)$expiresDaysRaw : 0;
        $expiresAt = $expiresDays > 0
            ? date('Y-m-d H:i:s', strtotime('+' . $expiresDays . ' days'))
            : null;
        $stmt = $pdo->prepare("
            INSERT INTO pickup_orders (
                order_id, order_number, customer_name, phone, pickup_code,
                status, code_expires_at, source_payload
            ) VALUES (?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, 'ready_for_pickup', ?, ?)
            ON DUPLICATE KEY UPDATE
                order_number = COALESCE(NULLIF(VALUES(order_number), ''), order_number),
                customer_name = COALESCE(NULLIF(VALUES(customer_name), ''), customer_name),
                phone = VALUES(phone),
                pickup_code = VALUES(pickup_code),
                status = CASE WHEN status = 'picked_up' THEN status ELSE 'ready_for_pickup' END,
                code_expires_at = VALUES(code_expires_at),
                source_payload = VALUES(source_payload)
        ");
        $stmt->execute([
            $orderId,
            $orderNumber,
            $customerName,
            $phone,
            $pickupCode,
            $expiresAt,
            chatJsonEncode($body),
        ]);
    }

    $phoneNumberId = cfg($cfg, 'META_PHONE_NUMBER_ID');
    $accessToken = cfg($cfg, 'META_ACCESS_TOKEN');
    if ($phoneNumberId === '' || $accessToken === '') {
        apiOut(['ok' => false, 'error' => 'Config META incompleta'], 500);
    }

    $templateName = chatCleanText((string)($body['template_name'] ?? ''));
    $templateLang = chatCleanText((string)($body['template_lang'] ?? cfg($cfg, 'META_TEMPLATE_LANG', 'pt_BR')));
    $message = chatCleanText((string)($body['message'] ?? ''));

    if ($templateName === '' && $event === 'order_ready_for_pickup') {
        $templateName = cfg($cfg, 'META_TEMPLATE_PICKUP_READY_NAME');
        $templateLang = cfg($cfg, 'META_TEMPLATE_PICKUP_READY_LANG', $templateLang);
    }

    if ($templateName === '' && $message === '') {
        apiOut([
            'ok' => false,
            'error' => 'Informe template_name/params ou configure META_TEMPLATE_PICKUP_READY_NAME. Templates ainda pendentes.',
            'pickup_code' => $pickupCode,
        ], 400);
    }

    if ($templateName !== '') {
        $params = $body['params'] ?? null;
        if (!is_array($params)) {
            $params = [
                $customerName,
                $orderNumber,
                $pickupCode,
                chatCleanText((string)($body['store_name'] ?? cfg($cfg, 'PICKUP_STORE_NAME', 'Lis-onTech'))),
            ];
        }

        $bodyParams = [];
        foreach ($params as $param) {
            $text = chatCleanText((string)$param);
            if ($text !== '') {
                $bodyParams[] = ['type' => 'text', 'text' => $text];
            }
        }

        $template = [
            'name' => $templateName,
            'language' => ['code' => $templateLang !== '' ? $templateLang : 'pt_BR'],
        ];
        if ($bodyParams) {
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
    } else {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $message],
        ];
    }

    $ch = curl_init("https://graph.facebook.com/v22.0/{$phoneNumberId}/messages");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
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
        'lovable_pickup',
        $orderId
    );

    $ok = ($http >= 200 && $http < 300) && ($err === '') && empty($resp['error']);
    if (!$ok) {
        $error = chatMetaErrorText($resp, $err ?: null);
        apiOut([
            'ok' => false,
            'error' => $error !== '' ? $error : 'Falha ao enviar WhatsApp',
            'pickup_code' => $pickupCode,
            'message_id' => $messageId,
            'http' => $http,
        ], 502);
    }

    apiOut([
        'ok' => true,
        'pickup_code' => $pickupCode,
        'message_id' => $messageId,
        'meta_message_id' => chatMetaMessageId($resp),
        'http' => $http,
    ]);
} catch (Throwable $e) {
    apiOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
