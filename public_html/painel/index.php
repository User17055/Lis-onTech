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

  <style>
    .lt-page-loader {
      position: fixed;
      inset: 0;
      z-index: 20000;
      display: grid;
      place-items: center;
      background: #05070b;
      color: #fff;
      font-family: 'Nunito', sans-serif;
      opacity: 1;
      visibility: visible;
      transition: opacity .28s ease, visibility .28s ease;
    }

    .lt-page-loader.is-hidden {
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
    }

    .lt-loader-core {
      position: relative;
      width: 120px;
      height: 96px;
    }

    .lt-loader-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      position: absolute;
      left: 50%;
      bottom: 50%;
      will-change: transform;
    }

    <?php for ($i = 1; $i <= 50; $i++): ?>
    .lt-loader-dot:nth-child(<?= $i ?>) {
      background: hsl(200, 100%, <?= min(96, $i * 2) ?>%);
      box-shadow: 0 0 20px 20px hsla(200, 100%, <?= min(96, $i + 25) ?>%, .03);
    }
    <?php endfor; ?>

    .lt-loader-label {
      margin-top: 22px;
      font-size: 14px;
      font-weight: 800;
      letter-spacing: 0;
      text-align: center;
    }

    @media (prefers-reduced-motion: reduce) {
      .lt-loader-dot {
        animation: lt-loader-pulse 1s ease-in-out infinite;
      }

      @keyframes lt-loader-pulse {
        50% { opacity: .35; }
      }
    }
  </style>
</head>

<body class="page-<?= htmlspecialchars($pagina, ENT_QUOTES, 'UTF-8') ?>">

  <div class="lt-page-loader" id="pageLoader" role="status" aria-live="polite" aria-label="Carregando">
    <div>
      <div class="lt-loader-core" id="pageLoaderDots">
        <?php for ($i = 0; $i < 50; $i++): ?>
          <span class="lt-loader-dot"></span>
        <?php endfor; ?>
      </div>
      <div class="lt-loader-label">Carregando...</div>
    </div>
  </div>

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

  <script>
    (function () {
      const loader = document.getElementById('pageLoader');
      const dots = Array.from(document.querySelectorAll('#pageLoaderDots .lt-loader-dot'));
      let rotate = 600;
      const rotation = 720;
      const add = 4;
      let lastFrame = 0;

      function rotateCircle(cx, cy, x, y, angle) {
        const radians = (Math.PI / 180) * angle;
        const cos = Math.cos(radians);
        const sin = Math.sin(radians);
        return {
          x: cos * (x - cx) + sin * (y - cy) + cx,
          y: cos * (y - cy) - sin * (x - cx) + cy
        };
      }

      function frame(now) {
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && now - lastFrame > 16) {
          lastFrame = now;
          rotate = rotation <= rotate + add ? 0 : rotate + add;
          dots.forEach((dot, index) => {
            const offset = add * index;
            const current = rotate + offset >= rotation ? rotate + offset - rotation : rotate + offset;
            const point = Math.floor(current / 360) >= 1
              ? rotateCircle(0, 0, -20, 0, current % 360)
              : rotateCircle(0, 0, 20, 0, -(current % 360));
            const x = Math.floor(current / 360) >= 1 ? point.x : point.x - 40;
            dot.style.transform = `translate(${x}px, ${point.y}px)`;
          });
        }
        requestAnimationFrame(frame);
      }

      function hideLoader() {
        if (loader) loader.classList.add('is-hidden');
      }

      function showLoader() {
        if (loader) loader.classList.remove('is-hidden');
      }

      requestAnimationFrame(frame);
      window.addEventListener('load', () => setTimeout(hideLoader, 120));
      window.addEventListener('pageshow', hideLoader);

      document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link) return;
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
