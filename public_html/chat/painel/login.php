<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

session_start();

if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/painel/atendimentos.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    $sql = "SELECT * FROM usuarios WHERE email = :email AND ativo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_nivel'] = $usuario['nivel'];

        header('Location: ' . BASE_URL . '/painel/atendimentos.php');
        exit;
    }

    $erro = 'E-mail ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Painel WhatsApp</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/app.css">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <h1 class="login-title">Painel WhatsApp</h1>
            <p class="login-subtitle">Entre para acessar os atendimentos.</p>

            <form method="post">
                <label>E-mail</label>
                <input type="email" name="email" required>

                <label style="margin-top:12px; display:block;">Senha</label>
                <input type="password" name="senha" required>

                <button class="btn" type="submit" style="margin-top:16px;">Entrar</button>
            </form>

            <?php if ($erro): ?>
                <div class="flash flash-error" style="margin-top:16px;">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <p class="helper" style="margin-top:18px;">
                Login inicial, admin@admin.com, senha 123456
            </p>
        </div>
    </div>
</body>
</html>