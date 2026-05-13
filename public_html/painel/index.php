<?php
// public_html/painel/index.php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
  if (authCsrfValid((string)($_POST['csrf'] ?? ''))) {
    authLogout();
  }
  header('Location: /painel/', true, 302);
  exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
  if (!authCsrfValid((string)($_POST['csrf'] ?? ''))) {
    $loginError = 'Sessao expirada. Tente novamente.';
  } elseif (!authConfigured($cfg)) {
    $loginError = 'Login nao configurado no servidor.';
  } else {
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');
    if (authCheckCredentials($cfg, $user, $pass)) {
      authLogin($user);
      header('Location: /painel/', true, 302);
      exit;
    }
    $loginError = 'Usuario ou senha invalidos.';
  }
}

if (!authIsLoggedIn()) {
  require __DIR__ . '/login.php';
  exit;
}

/**
 * ✅ ROTEADOR DO PAINEL (Lis'on)
 * - partials/ = componentes (sidebar, header etc)
 * - pages/    = páginas do sistema (index, detalhes, dashboard...)
 */

// rota principal
$pagina = $_GET['pagina'] ?? 'index';

// lista segura (só o que você permite abrir)
$paginasPermitidas = [
  'index',
  'dashboard',
  'recobrancas',
  'chat',
  'recobranca_detalhes',
  'reports',
  'finance',
  'config',
  'detalhes',
  'cobrancas',
  'customer',
];

if (!in_array($pagina, $paginasPermitidas, true)) {
  $pagina = 'index';
}

// ✅ primeiro tenta em /pages
$pathPages = __DIR__ . "/pages/{$pagina}.php";

// ✅ se não existir em /pages, tenta na raiz do /painel
$pathRoot = __DIR__ . "/{$pagina}.php";

// define o caminho final do include
if (file_exists($pathPages)) {
  $caminhoArquivo = $pathPages;
} elseif (file_exists($pathRoot)) {
  $caminhoArquivo = $pathRoot;
} else {
  $caminhoArquivo = null;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Lis'on System</title>
  <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
  <link rel="shortcut icon" type="image/svg+xml" href="/assets/favicon.svg">

  <!-- ✅ Sidebar Lis'on (Poppins + Boxicons) -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <!-- ✅ Painel Automator (Nunito + FontAwesome) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body class="page-<?= htmlspecialchars($pagina, ENT_QUOTES, 'UTF-8') ?>">

  <?php if ($pagina === 'chat'): ?>
    <style>
      body.page-chat {
        overflow: hidden;
      }

      body.page-chat .main-content {
        height: calc(100vh - var(--header-height, 70px));
        padding: 0;
        overflow: hidden;
        box-sizing: border-box;
      }

      @media (max-width: 920px) {
        body.page-chat {
          overflow: auto;
        }

        body.page-chat .main-content {
          height: auto;
          min-height: calc(100vh - var(--header-height, 70px));
          padding: 0;
          overflow: visible;
        }
      }
    </style>
  <?php endif; ?>

  <!-- ✅ Sidebar + Header (Lis'on) -->
  <?php require __DIR__ . "/partials/sidebar.php"; ?>

  <!-- ✅ Conteúdo das páginas -->
  <main class="main-content" id="content">
    <?php
    if ($caminhoArquivo) {
      include $caminhoArquivo;
    } else {
      echo "<div style='text-align:center; padding: 50px; color: #5a6a85; font-family:Poppins, sans-serif;'>";
      echo "<i class='bx bx-error-circle' style='font-size: 4rem; color: #ff6b6b;'></i>";
      echo "<h2>Página em construção ou não encontrada</h2>";
      echo "<p>Arquivo <code>{$pagina}.php</code> não existe em <b>/painel/pages</b> nem na raiz.</p>";
      echo "</div>";
    }
    ?>
  </main>

</body>

</html>
