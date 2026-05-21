<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

function mediaOutError(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function mediaHeaderValue(string $value, string $fallback): string
{
    $value = trim($value);
    $value = preg_replace('/[\r\n"]+/', '', $value) ?? '';
    return $value !== '' ? $value : $fallback;
}

try {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/../../includes/chat_db.php';

    chatEnsureTables($pdo);

    $messageId = max(0, (int)($_GET['id'] ?? 0));
    if ($messageId <= 0) {
        mediaOutError('Mensagem invalida', 400);
    }

    $stmt = $pdo->prepare("
        SELECT id, direction, message_type, payload_json
        FROM chat_messages
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$messageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        mediaOutError('Mensagem nao encontrada', 404);
    }

    $type = strtolower((string)($row['message_type'] ?? ''));
    if (!in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
        mediaOutError('Mensagem sem midia', 400);
    }

    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    if (!is_array($payload)) {
        mediaOutError('Payload de midia indisponivel', 404);
    }

    $media = $payload[$type] ?? null;
    if (!is_array($media) || empty($media['id'])) {
        mediaOutError('ID da midia indisponivel', 404);
    }

    $mediaId = preg_replace('/[^0-9A-Za-z_\-]/', '', (string)$media['id']) ?? '';
    if ($mediaId === '') {
        mediaOutError('ID da midia invalido', 400);
    }

    $token = cfg($cfg, 'META_ACCESS_TOKEN');
    if ($token === '') {
        mediaOutError('META_ACCESS_TOKEN nao configurado', 500);
    }

    $metaUrl = "https://graph.facebook.com/v22.0/{$mediaId}";
    $ch = curl_init($metaUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT => 25,
    ]);
    $metaRaw = curl_exec($ch);
    $metaHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $metaErr = curl_error($ch);
    curl_close($ch);

    if ($metaRaw === false || $metaHttp < 200 || $metaHttp >= 300) {
        mediaOutError('Falha ao localizar midia na Meta' . ($metaErr ? ': ' . $metaErr : ''), 502);
    }

    $meta = json_decode((string)$metaRaw, true);
    if (!is_array($meta) || empty($meta['url'])) {
        mediaOutError('URL da midia indisponivel', 502);
    }

    $mime = mediaHeaderValue((string)($media['mime_type'] ?? $meta['mime_type'] ?? ''), 'application/octet-stream');
    $filename = mediaHeaderValue((string)($media['filename'] ?? $type . '-' . $messageId), $type . '-' . $messageId);

    $ch = curl_init((string)$meta['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT => 45,
    ]);
    $binary = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($binary === false || $http < 200 || $http >= 300) {
        mediaOutError('Falha ao baixar midia' . ($err ? ': ' . $err : ''), 502);
    }

    if ($contentType !== '') {
        $mime = mediaHeaderValue(explode(';', $contentType)[0], $mime);
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen((string)$binary));
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=300');
    echo $binary;
} catch (Throwable $e) {
    mediaOutError($e->getMessage(), 500);
}
