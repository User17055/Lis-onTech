<?php
declare(strict_types=1);

$loginUser = isset($_POST['user']) ? trim((string)$_POST['user']) : '';
$loginError = $loginError ?? '';
$loginConfigured = isset($cfg) && is_array($cfg) ? authConfigured($cfg) : false;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Lis'on</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    *{box-sizing:border-box}
    body{
      margin:0;
      min-height:100vh;
      font-family:'Poppins',sans-serif;
      background:#f6f8fb;
      color:#172033;
      display:grid;
      place-items:center;
      padding:24px;
    }
    .login-shell{
      width:min(960px,100%);
      min-height:560px;
      display:grid;
      grid-template-columns:1fr 430px;
      background:#fff;
      border:1px solid #e6edf5;
      border-radius:8px;
      overflow:hidden;
      box-shadow:0 24px 70px rgba(23,32,51,.12);
    }
    .login-brand{
      background:#172033;
      color:#fff;
      padding:44px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      min-width:0;
    }
    .brand-top{display:flex;align-items:center;gap:14px;font-size:28px;font-weight:800}
    .brand-top img{height:56px;width:auto;background:#fff;border-radius:8px;padding:6px}
    .brand-top span span{color:#38b6ff}
    .brand-copy strong{display:block;font-size:34px;line-height:1.1;margin-bottom:14px}
    .brand-copy p{margin:0;color:#c9d5e3;font-weight:500;line-height:1.55;max-width:420px}
    .login-form{
      padding:44px;
      display:flex;
      flex-direction:column;
      justify-content:center;
    }
    .login-form h1{margin:0 0 8px;font-size:28px;line-height:1.15}
    .login-form .sub{margin:0 0 28px;color:#66758a;font-weight:600}
    .field{display:grid;gap:8px;margin-bottom:16px}
    .field label{font-size:13px;font-weight:800;color:#435169}
    .input-wrap{position:relative}
    .input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#93a4b7}
    input{
      width:100%;
      height:50px;
      border:1px solid #dce6f1;
      border-radius:8px;
      padding:0 14px 0 42px;
      outline:none;
      font:700 14px 'Poppins',sans-serif;
      color:#172033;
      background:#f8fafc;
    }
    input:focus{border-color:#38b6ff;background:#fff;box-shadow:0 0 0 4px rgba(56,182,255,.15)}
    .btn{
      width:100%;
      height:52px;
      border:0;
      border-radius:8px;
      background:#38b6ff;
      color:#fff;
      font:800 15px 'Poppins',sans-serif;
      cursor:pointer;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      margin-top:8px;
    }
    .btn:hover{background:#1677a8}
    .alert{
      border-radius:8px;
      padding:12px 14px;
      font-size:13px;
      font-weight:700;
      margin-bottom:18px;
      line-height:1.35;
    }
    .alert.error{background:#fee9e9;color:#9d2b2b}
    .alert.warn{background:#fff4da;color:#8a5b0a}
    .hint{margin-top:18px;color:#8795a8;font-size:12px;font-weight:600;line-height:1.45}
    @media(max-width:820px){
      .login-shell{grid-template-columns:1fr;min-height:0}
      .login-brand{padding:28px;gap:50px}
      .login-form{padding:30px}
    }
  </style>
</head>
<body>
  <main class="login-shell">
    <section class="login-brand">
      <div class="brand-top">
        <img src="/assets/lisonbb.svg" alt="Lis'on">
        <span>Lis'<span>on</span></span>
      </div>
      <div class="brand-copy">
        <strong>Painel protegido</strong>
        <p>Entre para acessar cobrancas, chat WhatsApp, logs e rotinas administrativas.</p>
      </div>
    </section>

    <section class="login-form">
      <h1>Entrar</h1>
      <p class="sub">Use o usuario administrativo configurado no servidor.</p>

      <?php if (!$loginConfigured): ?>
        <div class="alert warn">
          Configure ADMIN_USER e ADMIN_PASS no config.env antes de acessar o painel.
        </div>
      <?php elseif ($loginError !== ''): ?>
        <div class="alert error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" action="/painel/">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="field">
          <label for="user">Usuario</label>
          <div class="input-wrap">
            <i class="fa-solid fa-user"></i>
            <input id="user" name="user" autocomplete="username" value="<?= htmlspecialchars($loginUser, ENT_QUOTES, 'UTF-8') ?>" <?= !$loginConfigured ? 'disabled' : '' ?>>
          </div>
        </div>
        <div class="field">
          <label for="pass">Senha</label>
          <div class="input-wrap">
            <i class="fa-solid fa-lock"></i>
            <input id="pass" name="pass" type="password" autocomplete="current-password" <?= !$loginConfigured ? 'disabled' : '' ?>>
          </div>
        </div>
        <button class="btn" type="submit" name="login" value="1" <?= !$loginConfigured ? 'disabled' : '' ?>>
          <i class="fa-solid fa-right-to-bracket"></i> Entrar
        </button>
      </form>

      <div class="hint">
        Dica: ADMIN_PASS pode ser texto simples ou hash gerado por password_hash do PHP.
      </div>
    </section>
  </main>
</body>
</html>
