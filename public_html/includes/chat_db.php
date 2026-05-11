<?php
declare(strict_types=1);

if (!function_exists('chatEnsureTables')) {
    function chatEnsureTables(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_threads (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                phone VARCHAR(32) NOT NULL,
                display_name VARCHAR(180) NULL,
                last_message_preview VARCHAR(255) NULL,
                last_message_at DATETIME NULL,
                last_inbound_at DATETIME NULL,
                last_outbound_at DATETIME NULL,
                unread_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_chat_threads_phone (phone),
                KEY idx_chat_threads_last_message (last_message_at),
                KEY idx_chat_threads_unread (unread_count)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS chat_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                thread_id BIGINT UNSIGNED NOT NULL,
                phone VARCHAR(32) NOT NULL,
                direction VARCHAR(12) NOT NULL,
                message_type VARCHAR(40) NOT NULL DEFAULT 'text',
                body TEXT NULL,
                meta_message_id VARCHAR(191) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'accepted',
                status_at DATETIME NULL,
                sent_at DATETIME NULL,
                delivered_at DATETIME NULL,
                read_at DATETIME NULL,
                failed_at DATETIME NULL,
                error_text TEXT NULL,
                http_code INT NULL,
                source VARCHAR(60) NULL,
                source_ref VARCHAR(191) NULL,
                request_json MEDIUMTEXT NULL,
                response_json MEDIUMTEXT NULL,
                payload_json MEDIUMTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_chat_messages_meta_id (meta_message_id),
                KEY idx_chat_messages_thread_created (thread_id, created_at),
                KEY idx_chat_messages_phone_created (phone, created_at),
                KEY idx_chat_messages_status (direction, status),
                KEY idx_chat_messages_source_ref (source_ref)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        chatDropForeignKeyIfExists($pdo, 'chat_messages', 'fk_msg_conv');
        chatMakeColumnNullableIfExists($pdo, 'chat_messages', 'conversation_id', 'BIGINT UNSIGNED NULL');

        chatEnsureColumn($pdo, 'chat_threads', 'display_name', "VARCHAR(180) NULL");
        chatEnsureColumn($pdo, 'chat_threads', 'last_message_preview', "VARCHAR(255) NULL");
        chatEnsureColumn($pdo, 'chat_threads', 'last_message_at', "DATETIME NULL");
        chatEnsureColumn($pdo, 'chat_threads', 'last_inbound_at', "DATETIME NULL");
        chatEnsureColumn($pdo, 'chat_threads', 'last_outbound_at', "DATETIME NULL");
        chatEnsureColumn($pdo, 'chat_threads', 'unread_count', "INT UNSIGNED NOT NULL DEFAULT 0");
        chatEnsureColumn($pdo, 'chat_threads', 'created_at', "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        chatEnsureColumn($pdo, 'chat_threads', 'updated_at', "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

        chatEnsureColumn($pdo, 'chat_messages', 'thread_id', "BIGINT UNSIGNED NOT NULL DEFAULT 0");
        chatEnsureColumn($pdo, 'chat_messages', 'phone', "VARCHAR(32) NOT NULL DEFAULT ''");
        chatEnsureColumn($pdo, 'chat_messages', 'direction', "VARCHAR(12) NOT NULL DEFAULT 'out'");
        chatEnsureColumn($pdo, 'chat_messages', 'message_type', "VARCHAR(40) NOT NULL DEFAULT 'text'");
        chatEnsureColumn($pdo, 'chat_messages', 'body', "TEXT NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'meta_message_id', "VARCHAR(191) NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'status', "VARCHAR(30) NOT NULL DEFAULT 'accepted'");
        chatEnsureColumn($pdo, 'chat_messages', 'status_at', "DATETIME NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'sent_at', "DATETIME NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'delivered_at', "DATETIME NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'read_at', "DATETIME NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'failed_at', "DATETIME NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'error_text', "TEXT NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'http_code', "INT NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'source', "VARCHAR(60) NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'source_ref', "VARCHAR(191) NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'request_json', "MEDIUMTEXT NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'response_json', "MEDIUMTEXT NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'payload_json', "MEDIUMTEXT NULL");
        chatEnsureColumn($pdo, 'chat_messages', 'created_at', "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        chatEnsureColumn($pdo, 'chat_messages', 'updated_at', "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

        $done = true;
    }
}

if (!function_exists('chatDropForeignKeyIfExists')) {
    function chatDropForeignKeyIfExists(PDO $pdo, string $table, string $constraint): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $constraint)) {
            throw new InvalidArgumentException('Nome de constraint invalido');
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        $stmt->execute([$table, $constraint]);

        if ((int)$stmt->fetchColumn() > 0) {
            $pdo->exec("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }
}

if (!function_exists('chatMakeColumnNullableIfExists')) {
    function chatMakeColumnNullableIfExists(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new InvalidArgumentException('Nome de coluna invalido');
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);

        if ((int)$stmt->fetchColumn() > 0) {
            $pdo->exec("ALTER TABLE {$table} MODIFY COLUMN {$column} {$definition}");
        }
    }
}

if (!function_exists('chatEnsureColumn')) {
    function chatEnsureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new InvalidArgumentException('Nome de coluna invalido');
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);

        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}

if (!function_exists('chatNormalizePhone')) {
    function chatNormalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits !== '' && strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }
        return $digits;
    }
}

if (!function_exists('chatCleanText')) {
    function chatCleanText(string $text): string
    {
        $text = preg_replace("/[\r\n\t]+/", " ", $text) ?? $text;
        $text = preg_replace('/ {2,}/', ' ', $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('chatTruncate')) {
    function chatTruncate(string $text, int $limit = 255): string
    {
        $text = chatCleanText($text);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $limit
                ? mb_substr($text, 0, $limit - 1, 'UTF-8') . '...'
                : $text;
        }
        return strlen($text) > $limit ? substr($text, 0, $limit - 1) . '...' : $text;
    }
}

if (!function_exists('chatJsonEncode')) {
    function chatJsonEncode($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '{}';
    }
}

if (!function_exists('chatDateFromMetaTimestamp')) {
    function chatDateFromMetaTimestamp($timestamp = null): string
    {
        $ts = is_numeric($timestamp) ? (int)$timestamp : time();
        if ($ts <= 0) $ts = time();
        return date('Y-m-d H:i:s', $ts);
    }
}

if (!function_exists('chatGetOrCreateThread')) {
    function chatGetOrCreateThread(PDO $pdo, string $phone, string $displayName = ''): int
    {
        chatEnsureTables($pdo);

        $displayName = chatTruncate($displayName, 180);
        $stmt = $pdo->prepare("
            INSERT INTO chat_threads (phone, display_name)
            VALUES (?, NULLIF(?, ''))
            ON DUPLICATE KEY UPDATE
                display_name = CASE
                    WHEN VALUES(display_name) IS NOT NULL AND VALUES(display_name) <> '' THEN VALUES(display_name)
                    ELSE display_name
                END
        ");
        $stmt->execute([$phone, $displayName]);

        $stmt = $pdo->prepare("SELECT id FROM chat_threads WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('chatTouchThread')) {
    function chatTouchThread(PDO $pdo, int $threadId, string $direction, string $preview, string $at, bool $unread): void
    {
        $preview = chatTruncate($preview);

        if ($direction === 'in') {
            $stmt = $pdo->prepare("
                UPDATE chat_threads
                SET
                    last_message_preview = CASE
                        WHEN last_message_at IS NULL OR ? >= last_message_at THEN ? ELSE last_message_preview
                    END,
                    last_message_at = CASE
                        WHEN last_message_at IS NULL OR ? >= last_message_at THEN ? ELSE last_message_at
                    END,
                    last_inbound_at = CASE
                        WHEN last_inbound_at IS NULL OR ? >= last_inbound_at THEN ? ELSE last_inbound_at
                    END,
                    unread_count = unread_count + ?
                WHERE id = ?
            ");
            $stmt->execute([$at, $preview, $at, $at, $at, $at, $unread ? 1 : 0, $threadId]);
            return;
        }

        $stmt = $pdo->prepare("
            UPDATE chat_threads
            SET
                last_message_preview = CASE
                    WHEN last_message_at IS NULL OR ? >= last_message_at THEN ? ELSE last_message_preview
                END,
                last_message_at = CASE
                    WHEN last_message_at IS NULL OR ? >= last_message_at THEN ? ELSE last_message_at
                END,
                last_outbound_at = CASE
                    WHEN last_outbound_at IS NULL OR ? >= last_outbound_at THEN ? ELSE last_outbound_at
                END
            WHERE id = ?
        ");
        $stmt->execute([$at, $preview, $at, $at, $at, $at, $threadId]);
    }
}

if (!function_exists('chatIncomingBody')) {
    function chatIncomingBody(array $message): array
    {
        $type = (string)($message['type'] ?? 'unknown');
        $body = '';

        if ($type === 'text') {
            $body = (string)($message['text']['body'] ?? '');
        } elseif ($type === 'button') {
            $body = (string)($message['button']['text'] ?? $message['button']['payload'] ?? '');
        } elseif ($type === 'interactive') {
            $interactive = $message['interactive'] ?? [];
            $button = is_array($interactive) ? ($interactive['button_reply'] ?? null) : null;
            $list = is_array($interactive) ? ($interactive['list_reply'] ?? null) : null;
            if (is_array($button)) {
                $body = (string)($button['title'] ?? $button['id'] ?? '');
            } elseif (is_array($list)) {
                $body = (string)($list['title'] ?? $list['id'] ?? '');
            }
        } elseif ($type === 'image') {
            $caption = (string)($message['image']['caption'] ?? '');
            $body = $caption !== '' ? '[imagem] ' . $caption : '[imagem]';
        } elseif ($type === 'video') {
            $caption = (string)($message['video']['caption'] ?? '');
            $body = $caption !== '' ? '[video] ' . $caption : '[video]';
        } elseif ($type === 'document') {
            $doc = $message['document'] ?? [];
            $name = is_array($doc) ? (string)($doc['filename'] ?? '') : '';
            $caption = is_array($doc) ? (string)($doc['caption'] ?? '') : '';
            $body = trim('[documento] ' . trim($name . ' ' . $caption));
        } elseif ($type === 'audio') {
            $body = '[audio]';
        } elseif ($type === 'sticker') {
            $body = '[figurinha]';
        } elseif ($type === 'location') {
            $body = '[localizacao]';
        } elseif ($type === 'contacts') {
            $body = '[contato]';
        }

        if ($body === '') {
            $body = '[mensagem ' . $type . ']';
        }

        return [$type, chatCleanText($body)];
    }
}

if (!function_exists('chatContactName')) {
    function chatContactName(array $value, string $phone): string
    {
        $contacts = $value['contacts'] ?? [];
        if (!is_array($contacts)) return '';

        foreach ($contacts as $contact) {
            if (!is_array($contact)) continue;
            $waId = chatNormalizePhone((string)($contact['wa_id'] ?? ''));
            if ($waId !== $phone) continue;
            return (string)($contact['profile']['name'] ?? '');
        }

        return '';
    }
}

if (!function_exists('chatSaveIncomingMessage')) {
    function chatSaveIncomingMessage(PDO $pdo, array $value, array $message): int
    {
        chatEnsureTables($pdo);

        $phone = chatNormalizePhone((string)($message['from'] ?? ''));
        if ($phone === '') return 0;

        $metaId = (string)($message['id'] ?? '');
        if ($metaId !== '') {
            $stmt = $pdo->prepare("SELECT id FROM chat_messages WHERE meta_message_id = ? LIMIT 1");
            $stmt->execute([$metaId]);
            $existing = (int)$stmt->fetchColumn();
            if ($existing > 0) return $existing;
        }

        [$type, $body] = chatIncomingBody($message);
        $displayName = chatContactName($value, $phone);
        $createdAt = chatDateFromMetaTimestamp($message['timestamp'] ?? null);
        $threadId = chatGetOrCreateThread($pdo, $phone, $displayName);

        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (
                thread_id, phone, direction, message_type, body, meta_message_id,
                status, status_at, source, payload_json, created_at
            ) VALUES (?, ?, 'in', ?, ?, NULLIF(?, ''), 'received', ?, 'webhook', ?, ?)
        ");
        $stmt->execute([
            $threadId,
            $phone,
            $type,
            $body,
            $metaId,
            $createdAt,
            chatJsonEncode($message),
            $createdAt,
        ]);

        $messageId = (int)$pdo->lastInsertId();
        chatTouchThread($pdo, $threadId, 'in', $body, $createdAt, true);
        return $messageId;
    }
}

if (!function_exists('chatMetaMessageId')) {
    function chatMetaMessageId(array $response): string
    {
        $id = $response['messages'][0]['id'] ?? '';
        return is_string($id) ? $id : '';
    }
}

if (!function_exists('chatDescribeWhatsAppPayload')) {
    function chatDescribeWhatsAppPayload(array $payload): string
    {
        $type = (string)($payload['type'] ?? '');
        if ($type === 'text') {
            return chatCleanText((string)($payload['text']['body'] ?? ''));
        }

        if ($type === 'template') {
            $name = (string)($payload['template']['name'] ?? 'template');
            $parts = [];
            $components = $payload['template']['components'] ?? [];
            if (is_array($components)) {
                foreach ($components as $component) {
                    if (!is_array($component)) continue;
                    $params = $component['parameters'] ?? [];
                    if (!is_array($params)) continue;
                    foreach ($params as $param) {
                        if (is_array($param) && isset($param['text'])) {
                            $parts[] = chatCleanText((string)$param['text']);
                        }
                    }
                }
            }
            return $parts ? "Template {$name}: " . implode(' | ', $parts) : "Template {$name}";
        }

        if ($type === 'image') {
            $caption = (string)($payload['image']['caption'] ?? '');
            return $caption !== '' ? '[imagem] ' . $caption : '[imagem]';
        }

        if ($type === 'document') {
            $caption = (string)($payload['document']['caption'] ?? '');
            return $caption !== '' ? '[documento] ' . $caption : '[documento]';
        }

        return $type !== '' ? "[{$type}]" : '[mensagem]';
    }
}

if (!function_exists('chatMetaErrorText')) {
    function chatMetaErrorText(array $response, ?string $curlError = null): string
    {
        $bits = [];
        if ($curlError) $bits[] = $curlError;

        $err = $response['error'] ?? null;
        if (is_array($err)) {
            foreach (['message', 'code', 'error_subcode'] as $key) {
                if (isset($err[$key]) && (string)$err[$key] !== '') {
                    $bits[] = $key . ': ' . (string)$err[$key];
                }
            }
        }

        return implode(' | ', $bits);
    }
}

if (!function_exists('chatSaveOutgoingMessage')) {
    function chatSaveOutgoingMessage(
        PDO $pdo,
        string $phone,
        string $body,
        array $request,
        array $response,
        int $httpCode,
        ?string $curlError = null,
        string $source = 'manual',
        string $sourceRef = '',
        ?string $createdAt = null
    ): int {
        chatEnsureTables($pdo);

        $phone = chatNormalizePhone($phone);
        if ($phone === '') return 0;

        $metaId = chatMetaMessageId($response);
        $status = ($httpCode >= 200 && $httpCode < 300 && $curlError === null && empty($response['error']))
            ? 'accepted'
            : 'failed';
        $now = $createdAt ?: date('Y-m-d H:i:s');
        $errorText = $status === 'failed' ? chatMetaErrorText($response, $curlError) : null;

        if ($metaId !== '') {
            $stmt = $pdo->prepare("SELECT id, status FROM chat_messages WHERE meta_message_id = ? LIMIT 1");
            $stmt->execute([$metaId]);
            $existingRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $existing = $existingRow ? (int)$existingRow['id'] : 0;
            if ($existing > 0) {
                $currentStatus = (string)($existingRow['status'] ?? '');
                $finalStatus = ($status === 'failed' || chatStatusRank($status) > chatStatusRank($currentStatus))
                    ? $status
                    : $currentStatus;
                $stmt = $pdo->prepare("
                    UPDATE chat_messages
                    SET status_at = CASE WHEN status <> ? THEN ? ELSE status_at END,
                        status = ?, http_code = ?,
                        error_text = CASE WHEN ? IS NOT NULL AND ? <> '' THEN ? ELSE error_text END,
                        request_json = ?, response_json = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $finalStatus,
                    $now,
                    $finalStatus,
                    $httpCode,
                    $errorText,
                    $errorText,
                    $errorText,
                    chatJsonEncode($request),
                    chatJsonEncode($response),
                    $existing,
                ]);
                return $existing;
            }
        }

        $threadId = chatGetOrCreateThread($pdo, $phone);
        $body = $body !== '' ? $body : chatDescribeWhatsAppPayload($request);

        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (
                thread_id, phone, direction, message_type, body, meta_message_id,
                status, status_at, http_code, error_text, source, source_ref,
                request_json, response_json, created_at
            ) VALUES (?, ?, 'out', ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?)
        ");
        $stmt->execute([
            $threadId,
            $phone,
            (string)($request['type'] ?? 'text'),
            $body,
            $metaId,
            $status,
            $now,
            $httpCode,
            $errorText,
            $source,
            $sourceRef,
            chatJsonEncode($request),
            chatJsonEncode($response),
            $now,
        ]);

        $messageId = (int)$pdo->lastInsertId();
        chatTouchThread($pdo, $threadId, 'out', $body, $now, false);
        return $messageId;
    }
}

if (!function_exists('chatBackfillAutomationRuns')) {
    function chatBackfillAutomationRuns(PDO $pdo, int $limit = 300): void
    {
        chatEnsureTables($pdo);

        $limit = max(1, min(1000, $limit));

        try {
            $rows = $pdo->query("
                SELECT run_id, phone, whatsapp_request, whatsapp_response, meta_http, created_at
                FROM automation_runs
                WHERE phone IS NOT NULL
                  AND phone <> ''
                  AND whatsapp_request IS NOT NULL
                  AND whatsapp_response IS NOT NULL
                ORDER BY created_at DESC
                LIMIT {$limit}
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return;
        }

        foreach ($rows as $row) {
            $request = json_decode((string)($row['whatsapp_request'] ?? ''), true);
            $response = json_decode((string)($row['whatsapp_response'] ?? ''), true);
            if (!is_array($request) || !is_array($response) || !empty($request['dry_run'])) {
                continue;
            }

            try {
                $runId = (string)($row['run_id'] ?? '');
                if (chatMetaMessageId($response) === '' && $runId !== '') {
                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM chat_messages
                        WHERE source_ref = ?
                          AND source IN ('automation', 'automation_backfill')
                        LIMIT 1
                    ");
                    $stmt->execute([$runId]);
                    if ((int)$stmt->fetchColumn() > 0) {
                        continue;
                    }
                }

                chatSaveOutgoingMessage(
                    $pdo,
                    (string)($row['phone'] ?? ''),
                    chatDescribeWhatsAppPayload($request),
                    $request,
                    $response,
                    (int)($row['meta_http'] ?? 0),
                    null,
                    'automation_backfill',
                    $runId,
                    (string)($row['created_at'] ?? '') ?: null
                );
            } catch (Throwable $e) {
                continue;
            }
        }
    }
}

if (!function_exists('chatStatusRank')) {
    function chatStatusRank(string $status): int
    {
        $map = [
            'queued' => 0,
            'accepted' => 1,
            'sent' => 2,
            'delivered' => 3,
            'read' => 4,
            'failed' => 5,
            'received' => 1,
        ];
        return $map[$status] ?? 0;
    }
}

if (!function_exists('chatStatusErrorText')) {
    function chatStatusErrorText(array $status): string
    {
        $errors = $status['errors'] ?? [];
        if (!is_array($errors) || empty($errors)) return '';

        $messages = [];
        foreach ($errors as $error) {
            if (!is_array($error)) continue;
            $msg = (string)($error['message'] ?? $error['title'] ?? '');
            $code = (string)($error['code'] ?? '');
            if ($msg !== '' && $code !== '') $messages[] = "{$msg} ({$code})";
            elseif ($msg !== '') $messages[] = $msg;
            elseif ($code !== '') $messages[] = "Erro {$code}";
        }

        return implode(' | ', $messages);
    }
}

if (!function_exists('chatApplyStatus')) {
    function chatApplyStatus(PDO $pdo, array $statusData): int
    {
        chatEnsureTables($pdo);

        $metaId = (string)($statusData['id'] ?? '');
        if ($metaId === '') return 0;

        $status = strtolower((string)($statusData['status'] ?? ''));
        if (!in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            return 0;
        }

        $at = chatDateFromMetaTimestamp($statusData['timestamp'] ?? null);
        $errorText = $status === 'failed' ? chatStatusErrorText($statusData) : '';

        $stmt = $pdo->prepare("SELECT id, thread_id, phone, status FROM chat_messages WHERE meta_message_id = ? LIMIT 1");
        $stmt->execute([$metaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $phone = chatNormalizePhone((string)($statusData['recipient_id'] ?? ''));
            if ($phone === '') return 0;
            $threadId = chatGetOrCreateThread($pdo, $phone);
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (
                    thread_id, phone, direction, message_type, body, meta_message_id,
                    status, status_at, error_text, source, payload_json, created_at
                ) VALUES (?, ?, 'out', 'status', 'Mensagem enviada', ?, ?, ?, NULLIF(?, ''), 'status_webhook', ?, ?)
            ");
            $stmt->execute([
                $threadId,
                $phone,
                $metaId,
                $status,
                $at,
                $errorText,
                chatJsonEncode($statusData),
                $at,
            ]);
            chatTouchThread($pdo, $threadId, 'out', 'Mensagem enviada', $at, false);
            return (int)$pdo->lastInsertId();
        }

        $current = (string)($row['status'] ?? '');
        if ($status !== 'failed' && chatStatusRank($status) < chatStatusRank($current)) {
            return (int)$row['id'];
        }

        $sentAt = $status === 'sent' ? $at : null;
        $deliveredAt = $status === 'delivered' ? $at : null;
        $readAt = $status === 'read' ? $at : null;
        $failedAt = $status === 'failed' ? $at : null;

        $stmt = $pdo->prepare("
            UPDATE chat_messages
            SET status = ?,
                status_at = ?,
                sent_at = COALESCE(sent_at, ?),
                delivered_at = COALESCE(delivered_at, ?),
                read_at = COALESCE(read_at, ?),
                failed_at = COALESCE(failed_at, ?),
                error_text = CASE WHEN ? <> '' THEN ? ELSE error_text END,
                payload_json = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $status,
            $at,
            $sentAt,
            $deliveredAt,
            $readAt,
            $failedAt,
            $errorText,
            $errorText,
            chatJsonEncode($statusData),
            (int)$row['id'],
        ]);

        return (int)$row['id'];
    }
}

if (!function_exists('chatMarkThreadRead')) {
    function chatMarkThreadRead(PDO $pdo, string $phone): void
    {
        chatEnsureTables($pdo);
        $phone = chatNormalizePhone($phone);
        if ($phone === '') return;

        $stmt = $pdo->prepare("UPDATE chat_threads SET unread_count = 0 WHERE phone = ?");
        $stmt->execute([$phone]);
    }
}
