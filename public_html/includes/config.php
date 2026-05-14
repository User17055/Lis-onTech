<?php
declare(strict_types=1);

/**
 * Locaweb, às vezes bloqueia ler fora do public_html (open_basedir),
 * então este config procura em vários caminhos.
 */
$pathCandidates = [
    'public_html/secure/config.env' => __DIR__ . '/../secure/config.env',
    'public_html/config.env' => __DIR__ . '/../config.env',
    'secure/config.env' => __DIR__ . '/../../secure/config.env',
];

$envPath = null;
$envPathLabel = null;
foreach ($pathCandidates as $label => $p) {
    if (is_readable($p)) {
        $envPath = $p;
        $envPathLabel = $label;
        break;
    }
}

$GLOBALS['LISON_CONFIG_ENV_LABEL'] = $envPathLabel;
$GLOBALS['LISON_CONFIG_ENV_CHECKS'] = array_map(
    static fn($p) => is_readable($p),
    $pathCandidates
);

$cfg = [];
if ($envPath) {
    $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if (is_array($parsed)) $cfg = $parsed;
}

if (!function_exists('cfg')) {
    function cfg(array $cfg, string $key, string $default = ''): string {
        $v = $cfg[$key] ?? getenv($key) ?? $default;
        return is_string($v) ? trim($v) : (string)$v;
    }
}
