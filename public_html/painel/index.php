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
  <script>
    try {
      if (localStorage.getItem('menuOpen') === 'true') {
        document.documentElement.classList.add('sidebar-open-pref');
      }
    } catch (e) {}
  </script>
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

  <style>
    .lt-content-loader {
      position: fixed;
      top: var(--header-height, 70px);
      right: 0;
      bottom: 0;
      left: 0;
      z-index: 850;
      display: grid;
      place-items: center;
      background: rgba(247, 249, 252, .86);
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity .2s ease, visibility .2s ease;
    }

    .main-content.shift > .lt-content-loader {
      left: var(--sidebar-width, 260px);
    }

    .lt-content-loader.show {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }

    .lt-content-loader-inner {
      color: #0f172a;
      font-family: 'Nunito', sans-serif;
      text-align: center;
    }

    .lt-content-spinner {
      width: 30px;
      height: 30px;
      border: 4px solid #e2e8f0;
      border-top-color: #38b6ff;
      border-radius: 50%;
      animation: ltSpin .8s linear infinite;
      margin: 0 auto;
    }

    .lt-content-loader-text {
      margin-top: 15px;
      font-size: 14px;
      font-weight: 800;
      letter-spacing: 0;
    }

    @keyframes ltSpin {
      to { transform: rotate(360deg); }
    }

    @media (max-width: 768px) {
      .main-content.shift > .lt-content-loader {
        left: 0;
      }
    }
  </style>
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
          overflow: hidden;
        }

        body.page-chat .main-content {
          height: calc(100dvh - var(--header-height, 70px));
          min-height: 0;
          padding: 0;
          overflow: hidden;
        }
      }
    </style>
  <?php endif; ?>

  <!-- ✅ Sidebar + Header (Lis'on) -->
  <?php require __DIR__ . "/partials/sidebar.php"; ?>

  <!-- ✅ Conteúdo das páginas -->
  <main class="main-content" id="content">
    <div class="lt-content-loader" id="pageLoader" role="status" aria-live="polite" aria-label="Carregando">
      <div class="lt-content-loader-inner">
        <div class="lt-content-spinner"></div>
        <div class="lt-content-loader-text">Carregando dados...</div>
      </div>
    </div>

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

  <script>
    (function () {
      const loader = document.getElementById('pageLoader');

      function hideLoader() {
        if (loader) loader.classList.remove('show');
      }

      function showLoader() {
        if (loader) loader.classList.add('show');
      }

      window.addEventListener('load', hideLoader);
      window.addEventListener('pageshow', hideLoader);

      document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link) return;
        if (link.closest('#sidebar') || link.closest('.logo-container')) return;
        const url = new URL(link.href, location.href);
        const sameWindow = !link.target || link.target === '_self';
        if (sameWindow && url.origin === location.origin && url.href !== location.href && !event.defaultPrevented) {
          showLoader();
        }
      });

      document.addEventListener('submit', (event) => {
        if (!event.defaultPrevented) showLoader();
      });

      window.LisOnPageLoader = { show: showLoader, hide: hideLoader };
    })();
  </script>

</body>

</html>
