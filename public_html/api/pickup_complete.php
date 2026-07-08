<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

apiCors($cfg);
apiRequirePost();
apiRequireBearer($cfg);

try {
    require_once __DIR__ . '/../db.php';
    require_once __DIR__ . '/../includes/chat_db.php';

    pickupEnsureTables($pdo);

    $body = apiJsonBody();
    $code = pickupCleanCode((string)($body['code'] ?? $body['pickup_code'] ?? ''));
    $confirmedBy = chatCleanText((string)($body['confirmed_by'] ?? 'lovable'));

    if ($code === '') {
        apiOut(['ok' => false, 'error' => 'Codigo obrigatorio'], 400);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, order_id, order_number, customer_name, phone, pickup_code,
               status, code_expires_at, picked_up_at
        FROM pickup_orders
        WHERE pickup_code = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$code]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        apiOut(['ok' => false, 'error' => 'Codigo nao encontrado'], 404);
    }

    if ((string)$order['status'] === 'picked_up') {
        $pdo->rollBack();
        apiOut([
            'ok' => false,
            'error' => 'Pedido ja retirado',
            'status' => 'picked_up',
            'picked_up_at' => $order['picked_up_at'],
        ], 409);
    }

    $expiresAt = (string)($order['code_expires_at'] ?? '');
    if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
        $pdo->rollBack();
        apiOut([
            'ok' => false,
            'error' => 'Codigo expirado',
            'status' => $order['status'],
            'code_expires_at' => $expiresAt,
        ], 410);
    }

    if ((string)$order['status'] !== 'ready_for_pickup') {
        $pdo->rollBack();
        apiOut([
            'ok' => false,
            'error' => 'Pedido ainda nao esta pronto para retirada',
            'status' => $order['status'],
        ], 409);
    }

    $pickedUpAt = date('Y-m-d H:i:s');
    $upd = $pdo->prepare("
        UPDATE pickup_orders
        SET status = 'picked_up',
            picked_up_at = ?,
            confirmed_by = NULLIF(?, '')
        WHERE id = ?
    ");
    $upd->execute([$pickedUpAt, $confirmedBy, (int)$order['id']]);

    $pdo->commit();

    $notify = !array_key_exists('notify', $body) || !empty($body['notify']);
    $notifyResult = null;
    if ($notify && (string)($order['phone'] ?? '') !== '') {
        $templateName = cfg($cfg, 'META_TEMPLATE_PICKUP_DONE_NAME');
        $phoneNumberId = cfg($cfg, 'META_PHONE_NUMBER_ID');
        $accessToken = cfg($cfg, 'META_ACCESS_TOKEN');

        if ($templateName !== '' && $phoneNumberId !== '' && $accessToken !== '') {
            $templateLang = cfg($cfg, 'META_TEMPLATE_PICKUP_DONE_LANG', cfg($cfg, 'META_TEMPLATE_LANG', 'pt_BR'));
            $customerName = chatCleanText((string)($order['customer_name'] ?? ''));
            $params = [
                $customerName,
            ];
            $bodyParams = [];
            foreach ($params as $param) {
                $text = chatCleanText((string)$param);
                if ($text !== '') {
                    $bodyParams[] = ['type' => 'text', 'text' => $text];
                }
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => chatNormalizePhone((string)$order['phone']),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $templateLang],
                    'components' => [
                        ['type' => 'body', 'parameters' => $bodyParams],
                    ],
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
                (string)$order['phone'],
                chatDescribeWhatsAppPayload($payload),
                $payload,
                $resp,
                $http,
                $err ?: null,
                'lovable_pickup_done',
                (string)$order['order_id']
            );

            $notifyResult = [
                'ok' => ($http >= 200 && $http < 300) && ($err === '') && empty($resp['error']),
                'message_id' => $messageId,
                'http' => $http,
            ];
        }
    }

    apiOut([
        'ok' => true,
        'status' => 'picked_up',
        'message' => 'Pedido marcado como retirado',
        'order_id' => $order['order_id'],
        'order_number' => $order['order_number'],
        'customer_name' => $order['customer_name'],
        'picked_up_at' => $pickedUpAt,
        'notification' => $notifyResult,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
