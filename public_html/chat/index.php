<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

session_start();

if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/painel/atendimentos.php');
    exit;
}

header('Location: ' . BASE_URL . '/painel/login.php');
exit;