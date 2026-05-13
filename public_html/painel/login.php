<?php
declare(strict_types=1);

$loginUser = isset($_POST['user']) ? trim((string)$_POST['user']) : '';
$loginError = $loginError ?? '';
$loginConfigured = isset($cfg) && is_array($cfg) ? authConfigured($cfg) : false;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Lis'onTech</title>
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
      overflow: hidden;
    }

    .login-panel {
      align-items: center;
      background: #EAF2FF;
      display: flex;
      justify-content: center;
      padding: 48px 32px;
    }

    .login-content {
      margin-top: -2px;
      width: min(100%, 314px);
    }

    .headline {
      margin-bottom: 34px;
    }

    .headline h1 {
      color: var(--ink);
      font-size: 34px;
      font-weight: 500;
      letter-spacing: 1.02px;
      line-height: 1.2;
      margin: 0 0 3px;
      text-transform: uppercase;
    }

    .headline p {
      color: var(--body);
      font-size: 14px;
      font-weight: 400;
      letter-spacing: 0.42px;
      line-height: 1.55;
      margin: 0;
    }

    .login-form {
      display: grid;
      gap: 18px;
    }

    .field {
      display: grid;
      gap: 8px;
    }

    .field span,
    .remember,
    .form-options a {
      color: var(--label);
      font-size: 14px;
      font-weight: 500;
      letter-spacing: 0.42px;
      line-height: 1.35;
    }

    .field input {
      background: rgba(196, 196, 196, 0);
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: var(--shadow);
      color: var(--body);
      height: 41px;
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

    .form-options {
      align-items: center;
      display: flex;
      justify-content: space-between;
      margin-top: -2px;
    }

    .remember {
      align-items: center;
      display: inline-flex;
      gap: 8px;
    }

    .remember input {
      accent-color: var(--accent);
      height: 14px;
      margin: 0;
      width: 14px;
    }

    .form-options a,
    .signup a {
      color: var(--label);
      text-decoration: none;
      transition: color 160ms ease;
    }

    .form-options a:hover,
    .signup a:hover {
      color: var(--accent);
    }

    .button {
      align-items: center;
      border-radius: 12px;
      cursor: pointer;
      display: inline-flex;
      font-size: 14px;
      font-weight: 600;
      height: 41px;
      justify-content: center;
      letter-spacing: 0.42px;
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
      margin-top: -3px;
    }

    .button-primary:hover:not(:disabled) {
      background: #1e53e3;
      box-shadow: 0 7px 18px rgba(45, 106, 255, 0.27);
    }

    .button-google {
      background: rgba(196, 196, 196, 0);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      color: #181818;
      gap: 10px;
      margin-top: -6px;
    }

    .button-google:hover {
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.16);
    }

    .signup {
      color: var(--label);
      font-size: 10px;
      font-weight: 500;
      letter-spacing: 0.3px;
      line-height: 1.5;
      margin: 15px 0 0;
      text-align: center;
    }

    .signup a {
      color: var(--accent);
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
        grid-template-columns: 1fr;
      }

      .art-panel {
        min-height: 280px;
        order: -1;
      }

      .login-panel {
        padding: 42px 24px 48px;
      }

      .headline h1 {
        font-size: 30px;
      }
    }

    @media (max-width: 380px) {
      .login-panel {
        padding-inline: 18px;
      }

      .headline h1 {
        font-size: 27px;
      }

      .form-options {
        align-items: flex-start;
        flex-direction: column;
        gap: 10px;
      }
    }
  </style>
</head>
<body>
  <main class="login-page" aria-label="Pagina de login">
    <section class="login-panel" aria-labelledby="login-title">
      <div class="login-content">
        <header class="headline">
          <h1 id="login-title">Bem-vindo de volta</h1>
          <p>Bem-vindo! Por favor, informe seus dados.</p>
        </header>

        <?php if (!$loginConfigured): ?>
          <div class="alert warn">
            Configure ADMIN_USER e ADMIN_PASS no config.env antes de acessar o painel.
          </div>
        <?php elseif ($loginError !== ''): ?>
          <div class="alert error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form class="login-form" method="post" action="/painel/">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

          <label class="field">
            <span>Email</span>
            <input
              type="text"
              name="user"
              placeholder="Digite seu email"
              autocomplete="username"
              value="<?= htmlspecialchars($loginUser, ENT_QUOTES, 'UTF-8') ?>"
              <?= !$loginConfigured ? 'disabled' : '' ?>
            >
          </label>

          <label class="field">
            <span>Senha</span>
            <input
              type="password"
              name="pass"
              placeholder="Digite sua senha"
              autocomplete="current-password"
              <?= !$loginConfigured ? 'disabled' : '' ?>
            >
          </label>

          <div class="form-options">
            <label class="remember">
              <input type="checkbox" name="remember" <?= !$loginConfigured ? 'disabled' : '' ?>>
              <span>Lembrar de mim</span>
            </label>
            <a href="#">Esqueceu a senha?</a>
          </div>

          <button class="button button-primary" type="submit" name="login" value="1" <?= !$loginConfigured ? 'disabled' : '' ?>>Entrar</button>
          <button class="button button-google" type="button">
            <svg aria-hidden="true" viewBox="0 0 24 24" width="29" height="29">
              <path
                fill="#4285F4"
                d="M22.6 12.2c0-.8-.1-1.5-.2-2.2H12v4.2h5.9c-.3 1.3-1 2.4-2 3.1v2.6h3.3c2-1.8 3.4-4.4 3.4-7.7Z"
              />
              <path
                fill="#34A853"
                d="M12 23c2.8 0 5.2-.9 6.9-2.6l-3.3-2.6c-.9.6-2.1 1-3.6 1-2.7 0-5-1.8-5.8-4.3H2.8v2.7C4.5 20.6 8 23 12 23Z"
              />
              <path
                fill="#FBBC05"
                d="M6.2 14.5c-.2-.6-.3-1.3-.3-2s.1-1.4.3-2V7.8H2.8A11 11 0 0 0 2 12.5c0 1.7.4 3.2 1.1 4.6l3.1-2.6Z"
              />
              <path
                fill="#EA4335"
                d="M12 6.3c1.5 0 2.9.5 4 1.6l3-3A10.3 10.3 0 0 0 12 2C8 2 4.5 4.4 2.8 7.8l3.4 2.7C7 8 9.3 6.3 12 6.3Z"
              />
            </svg>
            <span>Entrar com Google</span>
          </button>
        </form>

        <p class="signup">Ainda nao tem conta? <a href="#">Cadastre-se gratis!</a></p>
      </div>
    </section>

    <section class="art-panel" aria-label="Arte Lis'onTech"></section>
  </main>
</body>
</html>
