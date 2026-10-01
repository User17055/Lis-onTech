<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/simplesvet_sales.php';

if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }

svSalesEnsureTables($pdo);
$notice = '';
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sales_account'])) {
    if (!authCsrfValid((string)($_POST['csrf'] ?? ''))) {
        $notice = 'Sessao expirada. Atualize a pagina e tente novamente.';
        $error = true;
    } else {
        try {
            $username = mb_substr(trim((string)($_POST['sales_username'] ?? '')), 0, 220);
            $password = (string)($_POST['sales_password'] ?? '');
            $unitName = mb_substr(trim((string)($_POST['sales_unit_name'] ?? '')), 0, 180);
            svSalesSaveSettings($pdo, $cfg, $username, $password, $unitName);
            $notice = 'Acesso do robo de pagamentos salvo com seguranca. A senha nao sera exibida novamente.';
        } catch (Throwable $e) {
            $notice = $e->getMessage();
            $error = true;
        }
    }
}

$settings = svSalesGetSettings($pdo, $cfg);
$username = (string)$settings['username'];
$unitName = (string)$settings['unit_name'];
$maskedUser = $username === '' ? '' : preg_replace('/(^.).*(@.*$)/', '$1***$2', $username);
if ($maskedUser === $username && mb_strlen($username) > 3) {
    $maskedUser = mb_substr($username, 0, 2) . str_repeat('*', max(3, mb_strlen($username) - 2));
}

function cfgPageH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<section class="svcfg-page">
<style>
.svcfg-page{font-family:'Nunito',sans-serif;color:#172033;max-width:880px;margin:0 auto;padding:4px 24px 45px}.svcfg-head{margin-bottom:22px}.svcfg-head h1{font-size:28px;margin:0 0 6px}.svcfg-head p{margin:0;color:#68758b}.svcfg-card{background:#fff;border:1px solid #e4ebf3;border-radius:18px;padding:24px;box-shadow:0 6px 22px rgba(28,48,78,.06)}.svcfg-title{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:20px}.svcfg-title h2{font-size:19px;margin:0}.svcfg-badge{padding:6px 10px;border-radius:999px;background:#fef3c7;color:#8a5b00;font-size:11px;font-weight:900}.svcfg-badge.ok{background:#d1fae5;color:#06603f}.svcfg-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.svcfg-field{display:grid;gap:7px}.svcfg-field.full{grid-column:1/-1}.svcfg-field label{font-size:12px;font-weight:900;color:#475467}.svcfg-field input{height:45px;border:1px solid #d8e1ec;border-radius:10px;padding:0 13px;font:inherit;box-sizing:border-box}.svcfg-help{font-size:11px;color:#7b8799}.svcfg-actions{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-top:22px}.svcfg-actions button{border:0;border-radius:10px;padding:12px 17px;background:#138a5b;color:#fff;font:inherit;font-weight:900;cursor:pointer}.svcfg-alert{margin-bottom:16px;padding:12px 15px;border-radius:11px;background:#e8f8ef;color:#087f3f;font-weight:800}.svcfg-alert.error{background:#fee2e2;color:#991b1b}.svcfg-warning{margin-top:18px;padding:14px;border-radius:11px;background:#f8fafc;color:#596579;font-size:12px;line-height:1.55}.svcfg-warning strong{color:#172033}@media(max-width:700px){.svcfg-page{padding-left:10px;padding-right:10px}.svcfg-grid{grid-template-columns:1fr}.svcfg-field.full{grid-column:auto}.svcfg-actions{align-items:flex-start;flex-direction:column}}
</style>
<div class="svcfg-head"><h1>Configuracoes</h1><p>Credenciais exclusivas do robo que registra pagamentos no SimplesVet.</p></div>
<?php if ($notice !== ''): ?><div class="svcfg-alert <?=$error ? 'error' : ''?>"><?=cfgPageH($notice)?></div><?php endif; ?>
<div class="svcfg-card">
  <div class="svcfg-title"><h2>Conta do bot de pagamentos</h2><span class="svcfg-badge <?=$settings['configured'] ? 'ok' : ''?>"><?=$settings['configured'] ? 'Acesso preenchido' : 'Aguardando acesso'?></span></div>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?=cfgPageH(authCsrfToken())?>">
    <div class="svcfg-grid">
      <div class="svcfg-field full"><label for="sales_username">Login do SimplesVet</label><input id="sales_username" name="sales_username" type="text" value="<?=cfgPageH($username)?>" autocomplete="off" required placeholder="E-mail ou usuario da conta exclusiva"></div>
      <div class="svcfg-field"><label for="sales_password">Senha</label><input id="sales_password" name="sales_password" type="password" autocomplete="new-password" placeholder="<?=$settings['configured'] ? 'Deixe vazio para manter a senha atual' : 'Digite a senha'?>" <?=$settings['configured'] ? '' : 'required'?>><span class="svcfg-help">A senha e criptografada antes de ser salva.</span></div>
      <div class="svcfg-field"><label for="sales_unit_name">Unidade no SimplesVet</label><input id="sales_unit_name" name="sales_unit_name" type="text" value="<?=cfgPageH($unitName)?>" placeholder="Nome exato da unidade"><span class="svcfg-help">Use o mesmo nome mostrado depois do login.</span></div>
    </div>
    <div class="svcfg-actions"><span class="svcfg-help"><?=$settings['configured'] ? 'Conta atual: ' . cfgPageH($maskedUser) : 'Nenhuma conta cadastrada.'?></span><button name="save_sales_account" value="1"><i class="fa-solid fa-lock"></i> Salvar acesso</button></div>
  </form>
  <div class="svcfg-warning"><strong>Importante:</strong> esta deve ser somente a segunda conta, reservada para os pagamentos. Salvar o acesso nao ativa o bot automaticamente; primeiro faremos um teste controlado.</div>
</div>
</section>
