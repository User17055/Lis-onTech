<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$accessToken = cfg($cfg, 'META_ACCESS_TOKEN');
$phoneId = cfg($cfg, 'META_PHONE_NUMBER_ID');
$verifyToken = cfg($cfg, 'META_VERIFY_TOKEN');
$autoReplyText = cfg(
    $cfg,
    'AUTO_REPLY_TEXT',
    "Este canal e apenas para notificacoes.\n\nPara atendimento, use o WhatsApp oficial."
);

$LOG_DIR = __DIR__ . '/storage/logs';
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0755, true);
}
$LOG_FILE = $LOG_DIR . '/webhook_chat.log';

function webhookLog(string $message): void
{
    global $LOG_FILE;
    @file_put_contents($LOG_FILE, '[' . date('d/m/Y H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hubMode = $_GET['hub_mode'] ?? '';
    $hubChallenge = $_GET['hub_challenge'] ?? '';
    $hubVerifyToken = $_GET['hub_verify_token'] ?? '';

    if ($hubMode === 'subscribe' && $verifyToken !== '' && $hubVerifyToken === $verifyToken) {
        echo $hubChallenge;
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode((string) $input, true);

if (is_array($data)) {
    $chatPdo = null;
    try {
        require_once __DIR__ . '/db.php';
        require_once __DIR__ . '/includes/chat_db.php';
        require_once __DIR__ . '/includes/chat_media_cache.php';
        $chatPdo = $pdo;
        chatEnsureTables($chatPdo);
    } catch (Throwable $e) {
        webhookLog('DB/chat indisponivel: ' . $e->getMessage());
    }

    $entries = $data['entry'] ?? [];
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            $changes = is_array($entry) ? ($entry['changes'] ?? []) : [];
            if (!is_array($changes)) continue;

            foreach ($changes as $change) {
                $value = is_array($change) ? ($change['value'] ?? []) : [];
                if (!is_array($value)) continue;

                $statuses = $value['statuses'] ?? [];
                if ($chatPdo && is_array($statuses)) {
                    foreach ($statuses as $status) {
                        if (!is_array($status)) continue;
                        try {
                            chatApplyStatus($chatPdo, $status);
                        } catch (Throwable $e) {
                            webhookLog('status_fail: ' . $e->getMessage());
                        }
                    }
                }

                $messages = $value['messages'] ?? [];
                if (!is_array($messages)) continue;

                foreach ($messages as $message) {
                    if (!is_array($message)) continue;

                    $customerNumber = (string) ($message['from'] ?? '');
                    $shouldAutoReply = false;

                    if ($customerNumber !== '' && $chatPdo) {
                        try {
                            $normalizedPhone = chatNormalizePhone($customerNumber);
                            if ($normalizedPhone !== '') {
                                $stmt = $chatPdo->prepare("SELECT last_inbound_at FROM chat_threads WHERE phone = ? LIMIT 1");
                                $stmt->execute([$normalizedPhone]);
                                $lastInboundAt = $stmt->fetchColumn();
                                $windowBeforeMessage = chatTextWindowInfo(is_string($lastInboundAt) ? $lastInboundAt : null);
                                $shouldAutoReply = !$windowBeforeMessage['can_send_text'];
                            }
                        } catch (Throwable $e) {
                            webhookLog('auto_reply_window_check_fail: ' . $e->getMessage());
                        }
                    }

                    if ($chatPdo) {
                        try {
                            $savedMessageId = chatSaveIncomingMessage($chatPdo, $value, $message);
                            $messageType = strtolower((string)($message['type'] ?? ''));
                            if ($savedMessageId > 0 && $accessToken !== '' && in_array($messageType, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
                                $cache = chatCacheMessageMedia($chatPdo, $savedMessageId, $accessToken);
                                if (empty($cache['ok'])) {
                                    webhookLog('media_cache_fail: ' . (string)($cache['error'] ?? 'erro desconhecido'));
                                }
                            }
                        } catch (Throwable $e) {
                            webhookLog('incoming_fail: ' . $e->getMessage());
                        }
                    }

                    if (!$shouldAutoReply || $customerNumber === '' || $accessToken === '' || $phoneId === '' || trim($autoReplyText) === '') {
                        continue;
                    }

                    $reply = [
                        'messaging_product' => 'whatsapp',
                        'to' => $customerNumber,
                        'type' => 'text',
                        'text' => [
                            'body' => $autoReplyText,
                        ],
                    ];

                    $ch = curl_init("https://graph.facebook.com/v22.0/{$phoneId}/messages");
                    curl_setopt_array($ch, [
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $accessToken,
                            'Content-Type: application/json',
                        ],
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode($reply, JSON_UNESCAPED_UNICODE),
                        CURLOPT_RETURNTRANSFER => true,
                    ]);
                    $res = curl_exec($ch);
                    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $err = curl_error($ch);
                    curl_close($ch);

                    if ($chatPdo) {
                        $resp = json_decode((string) $res, true);
                        if (!is_array($resp)) {
                            $resp = ['raw' => (string) $res];
                        }

                        try {
                            chatSaveOutgoingMessage(
                                $chatPdo,
                                $customerNumber,
                                $autoReplyText,
                                $reply,
                                $resp,
                                $http,
                                $err ?: null,
                                'auto_reply'
                            );
                        } catch (Throwable $e) {
                            webhookLog('auto_reply_log_fail: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
}

http_response_code(200);
