<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!function_exists('authConfigured')) {
    function authConfigured(array $cfg): bool
    {
        return cfg($cfg, 'ADMIN_USER') !== '' && cfg($cfg, 'ADMIN_PASS') !== '';
    }
}

if (!function_exists('authUser')) {
    function authUser(array $cfg): string
    {
        return cfg($cfg, 'ADMIN_USER');
    }
}

if (!function_exists('authPasswordMatches')) {
    function authPasswordMatches(string $input, string $stored): bool
    {
        if ($stored === '') return false;

        $looksHash = preg_match('/^\$2y\$|\$argon2i\$|\$argon2id\$/', $stored) === 1;
        if ($looksHash) {
            return password_verify($input, $stored);
        }

        return hash_equals($stored, $input);
    }
}

if (!function_exists('authCheckCredentials')) {
    function authCheckCredentials(array $cfg, string $user, string $pass): bool
    {
        $expectedUser = cfg($cfg, 'ADMIN_USER');
        $expectedPass = cfg($cfg, 'ADMIN_PASS');
        return $expectedUser !== ''
            && hash_equals($expectedUser, $user)
            && authPasswordMatches($pass, $expectedPass);
    }
}

if (!function_exists('authLogin')) {
    function authLogin(string $user): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['auth_user'] = $user;
        $_SESSION['auth_at'] = time();
    }
}

if (!function_exists('authLogout')) {
    function authLogout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }
}

if (!function_exists('authIsLoggedIn')) {
    function authIsLoggedIn(): bool
    {
        return !empty($_SESSION['auth']);
    }
}

if (!function_exists('authCsrfToken')) {
    function authCsrfToken(): string
    {
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}

if (!function_exists('authCsrfValid')) {
    function authCsrfValid(string $token): bool
    {
        return isset($_SESSION['csrf']) && is_string($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}

if (!function_exists('authRequireApi')) {
    function authRequireApi(): void
    {
        if (authIsLoggedIn()) return;

        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Login obrigatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
