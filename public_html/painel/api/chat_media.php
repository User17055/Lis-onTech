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

function mediaExtensionFromMime(string $mime, string $type): string
{
    $mime = strtolower($mime);
    if (str_contains($mime, 'ogg') || str_contains($mime, 'opus')) return 'ogg';
    if (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3')) return 'mp3';
    if (str_contains($mime, 'mp4') || str_contains($mime, 'aac')) return $type === 'video' ? 'mp4' : 'm4a';
    if (str_contains($mime, 'webm')) return 'webm';
    if (str_contains($mime, 'jpeg')) return 'jpg';
    if (str_contains($mime, 'png')) return 'png';
    if (str_contains($mime, 'webp')) return 'webp';
    if (str_contains($mime, 'pdf')) return 'pdf';
    return $type === 'audio' ? 'ogg' : $type;
}

function mediaEnsureFilenameExtension(string $filename, string $mime, string $type): string
{
    $filename = trim($filename);
    if ($filename === '') {
        $filename = $type;
    }

    $base = basename(str_replace('\\', '/', $filename));
    if ($base === '' || $base === '.' || $base === '..') {
        $base = $type;
    }

    if (!preg_match('/\.[A-Za-z0-9]{2,5}$/', $base)) {
        $base .= '.' . mediaExtensionFromMime($mime, $type);
    }

    return $base;
}

try {
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/../../includes/chat_db.php';
    require_once __DIR__ . '/../../includes/chat_media_cache.php';

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
    $cache = chatCacheMessageMedia($pdo, $messageId, $token);
    if (empty($cache['ok'])) {
        mediaOutError((string)($cache['error'] ?? 'Falha ao carregar midia'), 502);
    }

    $path = (string)($cache['path'] ?? '');
    if ($path === '' || !is_file($path)) {
        mediaOutError('Midia local indisponivel', 404);
    }

    $mime = mediaHeaderValue((string)($cache['mime_type'] ?? $media['mime_type'] ?? ''), 'application/octet-stream');
    $filename = mediaHeaderValue((string)($cache['filename'] ?? $media['filename'] ?? $type . '-' . $messageId), $type . '-' . $messageId);
    $filename = mediaEnsureFilenameExtension($filename, $mime, $type);
    $disposition = (string)($_GET['download'] ?? '0') === '1' ? 'attachment' : 'inline';
    $size = (int)($cache['size'] ?? filesize($path));

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=86400');
    readfile($path);
} catch (Throwable $e) {
    mediaOutError($e->getMessage(), 500);
}
