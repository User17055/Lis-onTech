<?php
declare(strict_types=1);

require_once __DIR__ . '/chat_db.php';

if (!function_exists('chatMediaCacheStorageRoot')) {
    function chatMediaCacheStorageRoot(): string
    {
        return dirname(__DIR__) . '/storage';
    }
}

if (!function_exists('chatMediaCacheAbsolutePath')) {
    function chatMediaCacheAbsolutePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            return '';
        }

        return chatMediaCacheStorageRoot() . '/' . $relativePath;
    }
}

if (!function_exists('chatMediaCacheExtensionFromMime')) {
    function chatMediaCacheExtensionFromMime(string $mime, string $type): string
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
        if (str_contains($mime, 'word')) return 'docx';
        if (str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel')) return 'xlsx';
        return $type === 'audio' ? 'ogg' : $type;
    }
}

if (!function_exists('chatMediaCacheFilename')) {
    function chatMediaCacheFilename(string $filename, string $mime, string $type, int $messageId): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            $filename = $type . '-' . $messageId;
        }

        $base = basename(str_replace('\\', '/', $filename));
        $base = preg_replace('/[\r\n"]+/', '', $base) ?? '';
        if ($base === '' || $base === '.' || $base === '..') {
            $base = $type . '-' . $messageId;
        }

        if (!preg_match('/\.[A-Za-z0-9]{2,8}$/', $base)) {
            $base .= '.' . chatMediaCacheExtensionFromMime($mime, $type);
        }

        return $base;
    }
}

if (!function_exists('chatMediaCacheRelativePath')) {
    function chatMediaCacheRelativePath(int $messageId, string $mediaId, string $mime, string $type): string
    {
        $bucket = (string)floor($messageId / 1000);
        $hash = substr(sha1($mediaId !== '' ? $mediaId : (string)$messageId), 0, 16);
        $ext = chatMediaCacheExtensionFromMime($mime, $type);
        return "chat_media/{$bucket}/{$messageId}-{$hash}.{$ext}";
    }
}

if (!function_exists('chatMediaCacheDownload')) {
    function chatMediaCacheDownload(string $url, string $token): array
    {
        $ch = curl_init($url);
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
            return ['ok' => false, 'error' => 'Falha ao baixar midia' . ($err ? ': ' . $err : '')];
        }

        return [
            'ok' => true,
            'binary' => (string)$binary,
            'content_type' => $contentType !== '' ? explode(';', $contentType)[0] : '',
        ];
    }
}

if (!function_exists('chatMediaCacheMeta')) {
    function chatMediaCacheMeta(string $mediaId, string $token): array
    {
        $ch = curl_init("https://graph.facebook.com/v22.0/{$mediaId}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 25,
        ]);
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $http < 200 || $http >= 300) {
            return ['ok' => false, 'error' => 'Falha ao localizar midia na Meta' . ($err ? ': ' . $err : '')];
        }

        $meta = json_decode((string)$raw, true);
        if (!is_array($meta) || empty($meta['url'])) {
            return ['ok' => false, 'error' => 'URL da midia indisponivel'];
        }

        return ['ok' => true, 'meta' => $meta];
    }
}

if (!function_exists('chatMediaCacheExisting')) {
    function chatMediaCacheExisting(array $media): ?array
    {
        $relativePath = (string)($media['local_path'] ?? '');
        if ($relativePath === '') {
            return null;
        }

        $absolutePath = chatMediaCacheAbsolutePath($relativePath);
        if ($absolutePath === '' || !is_file($absolutePath)) {
            return null;
        }

        return [
            'ok' => true,
            'path' => $absolutePath,
            'relative_path' => $relativePath,
            'mime_type' => (string)($media['local_mime_type'] ?? $media['mime_type'] ?? 'application/octet-stream'),
            'filename' => (string)($media['local_filename'] ?? $media['filename'] ?? basename($absolutePath)),
            'size' => (int)filesize($absolutePath),
            'cached' => true,
        ];
    }
}

if (!function_exists('chatCacheMessageMedia')) {
    function chatCacheMessageMedia(PDO $pdo, int $messageId, string $token): array
    {
        if ($messageId <= 0) {
            return ['ok' => false, 'error' => 'Mensagem invalida'];
        }
        $stmt = $pdo->prepare("
            SELECT id, message_type, payload_json
            FROM chat_messages
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$messageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'Mensagem nao encontrada'];
        }

        $type = strtolower((string)($row['message_type'] ?? ''));
        if (!in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
            return ['ok' => false, 'error' => 'Mensagem sem midia'];
        }

        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            return ['ok' => false, 'error' => 'Payload de midia indisponivel'];
        }

        $media = $payload[$type] ?? null;
        if (!is_array($media) || empty($media['id'])) {
            return ['ok' => false, 'error' => 'ID da midia indisponivel'];
        }

        $existing = chatMediaCacheExisting($media);
        if ($existing) {
            return $existing;
        }

        if ($token === '') {
            return ['ok' => false, 'error' => 'META_ACCESS_TOKEN nao configurado'];
        }

        $mediaId = preg_replace('/[^0-9A-Za-z_\-]/', '', (string)$media['id']) ?? '';
        if ($mediaId === '') {
            return ['ok' => false, 'error' => 'ID da midia invalido'];
        }

        $metaResult = chatMediaCacheMeta($mediaId, $token);
        if (empty($metaResult['ok'])) {
            return $metaResult;
        }

        $meta = $metaResult['meta'];
        $mime = (string)($media['mime_type'] ?? $meta['mime_type'] ?? 'application/octet-stream');
        $download = chatMediaCacheDownload((string)$meta['url'], $token);
        if (empty($download['ok'])) {
            return $download;
        }

        if (!empty($download['content_type'])) {
            $mime = (string)$download['content_type'];
        }

        $filename = chatMediaCacheFilename((string)($media['filename'] ?? ''), $mime, $type, $messageId);
        $relativePath = chatMediaCacheRelativePath($messageId, $mediaId, $mime, $type);
        $absolutePath = chatMediaCacheAbsolutePath($relativePath);
        if ($absolutePath === '') {
            return ['ok' => false, 'error' => 'Caminho de cache invalido'];
        }

        $dir = dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Nao foi possivel criar pasta de cache'];
        }

        if (file_put_contents($absolutePath, (string)$download['binary'], LOCK_EX) === false) {
            return ['ok' => false, 'error' => 'Nao foi possivel salvar midia localmente'];
        }

        $payload[$type]['local_path'] = $relativePath;
        $payload[$type]['local_mime_type'] = $mime;
        $payload[$type]['local_filename'] = $filename;
        $payload[$type]['local_size'] = filesize($absolutePath);
        $payload[$type]['local_cached_at'] = date('Y-m-d H:i:s');

        $update = $pdo->prepare("UPDATE chat_messages SET payload_json = ? WHERE id = ?");
        $update->execute([chatJsonEncode($payload), $messageId]);

        return [
            'ok' => true,
            'path' => $absolutePath,
            'relative_path' => $relativePath,
            'mime_type' => $mime,
            'filename' => $filename,
            'size' => (int)filesize($absolutePath),
            'cached' => false,
        ];
    }
}
