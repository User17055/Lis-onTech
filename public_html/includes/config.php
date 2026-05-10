<?php
declare(strict_types=1);

/**
 * Locaweb, às vezes bloqueia ler fora do public_html (open_basedir),
 * então este config procura em vários caminhos.
 */
$paths = [
    __DIR__ . '/../secure/config.env',
    __DIR__ . '/../config.env',
    __DIR__ . '/../../secure/config.env',
];

$envPath = null;
foreach ($paths as $p) {
    if (is_readable($p)) {
        $envPath = $p;
        break;
    }
}

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
