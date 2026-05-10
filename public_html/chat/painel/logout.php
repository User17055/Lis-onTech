<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

session_start();
session_destroy();

header('Location: ' . BASE_URL . '/painel/login.php');
exit;