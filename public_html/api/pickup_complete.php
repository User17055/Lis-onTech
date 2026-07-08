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

    apiOut([
        'ok' => true,
        'status' => 'picked_up',
        'message' => 'Pedido marcado como retirado',
        'order_id' => $order['order_id'],
        'order_number' => $order['order_number'],
        'customer_name' => $order['customer_name'],
        'picked_up_at' => $pickedUpAt,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    apiOut(['ok' => false, 'error' => $e->getMessage()], 500);
}
