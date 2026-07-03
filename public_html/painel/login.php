<?php
declare(strict_types=1);

$loginUser = isset($_POST['user']) ? trim((string)$_POST['user']) : '';
$loginError = $loginError ?? '';
$loginConfigured = isset($cfg) && is_array($cfg) ? authConfigured($cfg) : false;
$loginToastMessage = '';

if ($loginError !== '') {
    $loginToastMessage = str_contains(strtolower($loginError), 'invalid')
        ? 'E-mail ou senha incorretos.'
        : $loginError;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Lis'onTech</title>
  <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
  <link rel="shortcut icon" type="image/svg+xml" href="/assets/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink: #051A48;
      --body: #324275;
      --label: #17294D;
      --accent: #2D6AFF;
      --border: rgba(45, 106, 255, 0.25);
      --surface: #F1F6FF;
      --shadow: 0 4px 18px rgba(45, 106, 255, 0.16);
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      margin: 0;
      min-height: 100%;
    }

    body {
      background: linear-gradient(180deg, #E8EEFF 0%, #F6F9FF 100%);
      color: var(--ink);
      font-family: "Poppins", Arial, sans-serif;
      min-height: 100vh;
      min-height: 100dvh;
    }

    button,
    input {
      font: inherit;
    }

    .login-page {
      background: var(--surface);
      display: grid;
      grid-template-columns: 1fr 1fr;
      min-height: 100vh;
      min-height: 100dvh;
      overflow: hidden;
    }

    .login-panel {
      align-items: center;
      background: #F7FAFF;
      display: flex;
      justify-content: center;
      padding: 48px 32px;
    }

    .login-content {
      margin-top: -2px;
      width: min(100%, 380px);
    }

    .brand-line {
      align-items: center;
      display: inline-flex;
      gap: 10px;
      margin-bottom: 28px;
    }

    .brand-line img {
      display: block;
      height: 34px;
      width: auto;
    }

    .brand-line span {
      color: var(--label);
      font-size: 13px;
      font-weight: 600;
      letter-spacing: 0.2px;
    }

    .headline {
      margin-bottom: 28px;
    }

    .headline h1 {
      color: var(--ink);
      font-size: 32px;
      font-weight: 500;
      letter-spacing: 0;
      line-height: 1.2;
      margin: 0 0 8px;
    }

    .headline p {
      color: var(--body);
      font-size: 14px;
      font-weight: 400;
      letter-spacing: 0;
      line-height: 1.55;
      margin: 0;
    }

    .login-form {
      display: grid;
      gap: 16px;
    }

    .field {
      display: grid;
      gap: 8px;
    }

    .field > span:first-child {
      color: var(--label);
      font-size: 14px;
      font-weight: 500;
      letter-spacing: 0;
      line-height: 1.35;
    }

    .field input {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: var(--shadow);
      color: var(--body);
      height: 48px;
      outline: none;
      padding: 0 15px;
      transition: border-color 160ms ease, box-shadow 160ms ease;
      width: 100%;
    }

    .field input::placeholder {
      color: rgba(99, 99, 100, 0.72);
      font-weight: 300;
    }

    .field input:disabled,
    .button:disabled {
      cursor: not-allowed;
      opacity: 0.58;
    }

    .field input:focus {
      border-color: rgba(45, 106, 255, 0.85);
      box-shadow: 0 0 0 3px rgba(45, 106, 255, 0.16), var(--shadow);
    }

    .password-box {
      display: block;
      position: relative;
    }

    .password-box input {
      display: block;
      padding-right: 92px;
    }

    .password-toggle {
      background: transparent;
      border: 0;
      color: var(--accent);
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      height: 34px;
      padding: 0 10px;
      position: absolute;
      right: 7px;
      top: 7px;
    }

    .button {
      align-items: center;
      border-radius: 12px;
      cursor: pointer;
      display: inline-flex;
      font-size: 14px;
      font-weight: 600;
      height: 48px;
      justify-content: center;
      letter-spacing: 0;
      line-height: 1;
      transition: transform 160ms ease, box-shadow 160ms ease, background 160ms ease;
      width: 100%;
    }

    .button:active {
      transform: translateY(1px);
    }

    .button-primary {
      background: var(--accent);
      border: 1px solid var(--accent);
      box-shadow: 0 4px 12px rgba(45, 106, 255, 0.22);
      color: #ffffff;
      margin-top: 2px;
    }

    .button-primary:hover:not(:disabled) {
      background: #1e53e3;
      box-shadow: 0 7px 18px rgba(45, 106, 255, 0.27);
    }

    .login-note {
      color: rgba(50, 66, 117, 0.82);
      font-size: 12px;
      font-weight: 500;
      line-height: 1.45;
      margin: 2px 0 0;
      text-align: center;
    }

    .alert {
      border-radius: 12px;
      box-shadow: var(--shadow);
      font-size: 12px;
      font-weight: 500;
      letter-spacing: 0.18px;
      line-height: 1.45;
      margin: -14px 0 18px;
      padding: 12px 14px;
    }

    .alert.error {
      background: #FEECEC;
      border: 1px solid rgba(188, 52, 52, 0.18);
      color: #922626;
    }

    .alert.warn {
      background: #FFF6D8;
      border: 1px solid rgba(196, 142, 24, 0.18);
      color: #7B5410;
    }

    .login-toast {
      animation: toast-enter 380ms ease-out both, toast-leave 420ms ease-in 4.8s forwards;
      background: rgba(255, 255, 255, 0.96);
      border: 1px solid rgba(224, 44, 44, 0.22);
      border-radius: 12px;
      box-shadow: 0 18px 45px rgba(119, 25, 25, 0.18);
      color: #8C1D1D;
      display: grid;
      gap: 3px;
      max-width: min(88vw, 390px);
      max-height: 92px;
      min-height: 74px;
      overflow: hidden;
      padding: 16px 18px 16px 24px;
      position: relative;
      width: 100%;
      z-index: 2;
    }

    .login-toast strong {
      color: #771818;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.18px;
      line-height: 1.25;
    }

    .login-toast span {
      color: #A23B3B;
      font-size: 12px;
      font-weight: 500;
      letter-spacing: 0.18px;
      line-height: 1.35;
    }

    .toast-timer {
      background: rgba(224, 44, 44, 0.13);
      bottom: 0;
      left: 0;
      position: absolute;
      top: 0;
      width: 6px;
    }

    .toast-timer::before {
      animation: toast-timer 4.8s linear forwards;
      background: linear-gradient(180deg, #FF4D4D 0%, #C81E1E 100%);
      bottom: 0;
      content: "";
      left: 0;
      position: absolute;
      right: 0;
      top: 0;
      transform-origin: bottom;
    }

    @keyframes toast-enter {
      from {
        opacity: 0;
        transform: translateY(-12px) scale(0.98);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    @keyframes toast-leave {
      to {
        border-width: 0;
        margin: 0;
        max-height: 0;
        min-height: 0;
        opacity: 0;
        padding-bottom: 0;
        padding-top: 0;
        pointer-events: none;
        transform: translateY(-10px) scale(0.98);
      }
    }

    @keyframes toast-timer {
      to {
        transform: scaleY(0);
      }
    }

    .art-panel {
      background-color: #2D6AFF;
      background-image: url("/assets/LISONTECH.png");
      background-position: center center;
      background-repeat: no-repeat;
      background-size: cover;
      min-height: 100vh;
    }

    @media (min-width: 900px) {
      .login-page {
        height: 100vh;
        width: 100vw;
      }

      .login-panel,
      .art-panel {
        min-height: auto;
      }
    }

    @media (max-width: 760px) {
      .login-page {
        display: flex;
        flex-direction: column;
        overflow: auto;
      }

      .art-panel {
        background-size: min(76vw, 320px) auto;
        flex: 0 0 132px;
        min-height: 132px;
        order: -1;
      }

      .login-panel {
        align-items: flex-start;
        flex: 1 1 auto;
        padding: 26px 22px max(26px, env(safe-area-inset-bottom));
      }

      .login-content {
        margin: 0 auto;
        width: min(100%, 430px);
      }

      .brand-line {
        margin-bottom: 22px;
      }

      .headline {
        margin-bottom: 22px;
      }

      .headline h1 {
        font-size: 28px;
      }
    }

    @media (max-width: 380px) {
      .login-panel {
        padding-inline: 16px;
      }

      .art-panel {
        flex-basis: 108px;
        min-height: 108px;
      }

      .brand-line img {
        height: 30px;
      }

      .headline h1 {
        font-size: 25px;
      }
    }
  </style>
</head>
<body>
  <main class="login-page" aria-label="Pagina de login">
    <section class="login-panel" aria-labelledby="login-title">
      <div class="login-content">
        <div class="brand-line" aria-label="Lis'onTech">
          <img src="/assets/lison.svg" alt="">
          <span>Lis'onTech</span>
        </div>

        <header class="headline">
          <h1 id="login-title">Bem-vindo de volta</h1>
          <p>Acesse o painel para acompanhar cobrancas, conversas e automacoes.</p>
        </header>

        <?php if (!$loginConfigured): ?>
          <div class="alert warn">
            Configure ADMIN_USER e ADMIN_PASS no config.env antes de acessar o painel.
          </div>
        <?php endif; ?>

        <form class="login-form" method="post" action="/painel/">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

          <?php if ($loginToastMessage !== ''): ?>
            <div class="login-toast" role="alert" aria-live="assertive">
              <div class="toast-timer" aria-hidden="true"></div>
              <strong>Login nao realizado</strong>
              <span><?= htmlspecialchars($loginToastMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endif; ?>

          <label class="field">
            <span>Usuario</span>
            <input
              type="text"
              name="user"
              placeholder="Digite seu usuario"
              autocomplete="username"
              required
              value="<?= htmlspecialchars($loginUser, ENT_QUOTES, 'UTF-8') ?>"
              <?= !$loginConfigured ? 'disabled' : '' ?>
            >
          </label>

          <label class="field">
            <span>Senha</span>
            <span class="password-box">
              <input
                id="loginPass"
                type="password"
                name="pass"
                placeholder="Digite sua senha"
                autocomplete="current-password"
                required
                <?= !$loginConfigured ? 'disabled' : '' ?>
              >
              <button class="password-toggle" id="togglePass" type="button" <?= !$loginConfigured ? 'disabled' : '' ?>>Mostrar</button>
            </span>
          </label>

          <button class="button button-primary" type="submit" name="login" value="1" <?= !$loginConfigured ? 'disabled' : '' ?>>Entrar</button>
          <p class="login-note">Acesso restrito aos usuarios autorizados.</p>
        </form>
      </div>
    </section>

    <section class="art-panel" aria-label="Arte Lis'onTech"></section>
  </main>
  <script>
    const passInput = document.getElementById('loginPass');
    const togglePass = document.getElementById('togglePass');

    if (passInput && togglePass) {
      togglePass.addEventListener('click', () => {
        const isHidden = passInput.type === 'password';
        passInput.type = isHidden ? 'text' : 'password';
        togglePass.textContent = isHidden ? 'Ocultar' : 'Mostrar';
        passInput.focus();
      });
    }
  </script>
</body>
</html>
