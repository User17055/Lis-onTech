<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (!function_exists('legalEscape')) {
    function legalEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('legalSettings')) {
    function legalSettings(array $cfg): array
    {
        return [
            'service' => cfg($cfg, 'LEGAL_SERVICE_NAME', 'Lis-onTech'),
            'controller' => cfg($cfg, 'LEGAL_CONTROLLER_NAME', 'Canil Holy Frans'),
            'email' => cfg($cfg, 'LEGAL_CONTACT_EMAIL', ''),
            'domain' => 'https://andrejrs.com.br',
            'updated' => '31 de agosto de 2026',
        ];
    }
}

if (!function_exists('legalContactHtml')) {
    function legalContactHtml(array $legal): string
    {
        if ($legal['email'] !== '') {
            $email = legalEscape($legal['email']);
            return '<a href="mailto:' . $email . '">' . $email . '</a>';
        }

        return 'pelo WhatsApp empresarial que originou a comunicação';
    }
}

if (!function_exists('legalHeader')) {
    function legalHeader(array $legal, string $title, string $summary): void
    {
        $pageTitle = legalEscape($title . ' | ' . $legal['service']);
        $description = legalEscape($summary);
        $service = legalEscape($legal['service']);
        echo <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="{$description}">
  <meta name="robots" content="index,follow">
  <title>{$pageTitle}</title>
  <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/legal.css">
</head>
<body>
  <header class="legal-header">
    <nav class="legal-nav" aria-label="Navegação jurídica">
      <a class="legal-brand" href="/">
        <img src="/assets/lison.svg" alt="" width="42" height="42">
        <span>{$service}</span>
      </a>
      <div class="legal-nav-links">
        <a href="/termos-de-uso.php">Termos</a>
        <a href="/politica-de-privacidade.php">Privacidade</a>
        <a href="/exclusao-de-dados.php">Exclusão de dados</a>
      </div>
    </nav>
    <div class="legal-hero">
      <p class="legal-eyebrow">Transparência e proteção de dados</p>
      <h1>{$title}</h1>
      <p>{$description}</p>
    </div>
  </header>
  <main class="legal-shell">
    <article class="legal-card">
HTML;
    }
}

if (!function_exists('legalFooter')) {
    function legalFooter(array $legal): void
    {
        $year = date('Y');
        $service = legalEscape($legal['service']);
        echo <<<HTML
    </article>
  </main>
  <footer class="legal-footer">
    <div class="legal-footer-links">
      <a href="/termos-de-uso.php">Termos de Uso</a>
      <a href="/politica-de-privacidade.php">Política de Privacidade</a>
      <a href="/exclusao-de-dados.php">Exclusão de Dados</a>
    </div>
    <div>&copy; {$year} {$service}. Todos os direitos reservados.</div>
  </footer>
</body>
</html>
HTML;
    }
}
