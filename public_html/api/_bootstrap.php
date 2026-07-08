<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

if (!function_exists('apiCors')) {
    function apiCors(array $cfg): void
    {
        $origin = cfg($cfg, 'API_CORS_ORIGIN', '*');
        header('Access-Control-Allow-Origin: ' . ($origin !== '' ? $origin : '*'));
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

if (!function_exists('apiOut')) {
    function apiOut(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('apiRequirePost')) {
    function apiRequirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiOut(['ok' => false, 'error' => 'Metodo invalido'], 405);
        }
    }
}

if (!function_exists('apiBearerToken')) {
    function apiBearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $key => $value) {
                if (strtolower((string)$key) === 'authorization') {
                    $header = (string)$value;
                    break;
                }
            }
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim((string)$header), $m)) {
            return trim($m[1]);
        }

        return '';
    }
}

if (!function_exists('apiRequireBearer')) {
    function apiRequireBearer(array $cfg): void
    {
        $expected = cfg($cfg, 'API_BEARER_TOKEN');
        if ($expected === '') {
            apiOut(['ok' => false, 'error' => 'API_BEARER_TOKEN nao configurado'], 500);
        }

        $provided = apiBearerToken();
        if ($provided === '' || !hash_equals($expected, $provided)) {
            apiOut(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
    }
}

if (!function_exists('apiJsonBody')) {
    function apiJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            apiOut(['ok' => false, 'error' => 'JSON invalido'], 400);
        }
        return $body;
    }
}

if (!function_exists('pickupEnsureTables')) {
    function pickupEnsureTables(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pickup_orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id VARCHAR(120) NOT NULL,
                order_number VARCHAR(80) NULL,
                customer_name VARCHAR(180) NULL,
                phone VARCHAR(32) NULL,
                pickup_code VARCHAR(40) NOT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'ready_for_pickup',
                code_expires_at DATETIME NULL,
                picked_up_at DATETIME NULL,
                confirmed_by VARCHAR(120) NULL,
                source_payload MEDIUMTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_pickup_orders_order_id (order_id),
                UNIQUE KEY uq_pickup_orders_code (pickup_code),
                KEY idx_pickup_orders_status (status),
                KEY idx_pickup_orders_phone (phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('pickupGenerateCode')) {
    function pickupGenerateCode(): string
    {
        return 'LT-' . random_int(100000, 999999);
    }
}

if (!function_exists('pickupCleanCode')) {
    function pickupCleanCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9-]+/', '', $code) ?? '';
        return $code;
    }
}
