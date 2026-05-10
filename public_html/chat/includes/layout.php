<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function esc(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function dt(?string $valor): string
{
    if (!$valor) {
        return '-';
    }

    $ts = strtotime($valor);
    if (!$ts) {
        return esc($valor);
    }

    return date('d/m/Y H:i', $ts);
}

function shortText(?string $texto, int $limite = 80): string
{
    $texto = trim((string) $texto);

    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($texto, 'UTF-8') <= $limite) {
            return $texto;
        }

        return mb_substr($texto, 0, $limite, 'UTF-8') . '...';
    }

    if (strlen($texto) <= $limite) {
        return $texto;
    }

    return substr($texto, 0, $limite) . '...';
}

function setFlash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
    ];
}

function renderFlash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $tipo = esc($flash['tipo'] ?? 'info');
    $mensagem = esc($flash['mensagem'] ?? '');

    return '<div class="flash flash-' . $tipo . '">' . $mensagem . '</div>';
}

function appHeader(string $titulo): void
{
    $base = BASE_URL;
    $usuario = esc($_SESSION['usuario_nome'] ?? '');

    echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo} | {APP_NAME}</title>
    <link rel="stylesheet" href="{$base}/assets/app.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">Painel WhatsApp</div>

        <nav class="sidebar-nav">
            <a href="{$base}/painel/atendimentos.php">Atendimentos</a>
            <a href="{$base}/painel/templates.php">Templates</a>
            <a href="{$base}/painel/logout.php">Sair</a>
        </nav>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div>
                <div class="page-title">{$titulo}</div>
                <div class="page-subtitle">Logado como {$usuario}</div>
            </div>
        </header>

        <main class="content">
HTML;

    echo renderFlash();
}

function appFooter(): void
{
    echo <<<HTML
        </main>
    </div>
</div>
</body>
</html>
HTML;
}