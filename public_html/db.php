<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$host = cfg($cfg, 'DB_HOST');
$name = cfg($cfg, 'DB_NAME');
$user = cfg($cfg, 'DB_USER');
$pass = cfg($cfg, 'DB_PASS');

$dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

