<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dados do MySQL da Locaweb
|--------------------------------------------------------------------------
*/
$host = 'SEU_HOST_MYSQL';
$db   = 'SEU_BANCO';
$user = 'SEU_USUARIO';
$pass = 'SUA_SENHA';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    die('Erro ao conectar no banco: ' . $e->getMessage());
}