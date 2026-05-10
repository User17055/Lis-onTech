<?php
declare(strict_types=1);

function isDebug(array $cfg): bool {
    return cfg($cfg, 'DEBUG', '0') === '1';
}

function dbgLog(string $file, string $msg): void {
    $line = "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND);
}

/**
 * Loga no arquivo SEM expor token inteiro (mas dá pra identificar qual é).
 */
function safeVal(string $v): string {
    $v = trim($v);
    if ($v === '') return 'VAZIO';
    if (strlen($v) <= 10) return $v;
    return substr($v, 0, 4) . "..." . substr($v, -4);
}

/**
 * Marca checkpoints (pontos do fluxo).
 * Se tiver $pdo e $runId, também grava em automation_run_logs.
 */
function checkpoint(array $cfg, string $logFile, ?PDO $pdo, ?string $runId, string $step, array $extra = []): void {
    $msg = "CHK {$step}";
    if (!empty($extra)) $msg .= " | " . json_encode($extra, JSON_UNESCAPED_UNICODE);

    dbgLog($logFile, $msg);

    if ($pdo && $runId) {
        try {
            runLog($pdo, $runId, 'info', $msg);
        } catch (Throwable $e) {
            dbgLog($logFile, "CHK_LOG_FAIL {$step} | " . $e->getMessage());
        }
    }
}

/**
 * Captura erros fatais que não caem no try/catch.
 */
function registerFatalHandler(array $cfg, string $logFile, ?PDO $pdo, ?string &$runId): void {
    register_shutdown_function(function() use ($cfg, $logFile, $pdo, &$runId) {
        $err = error_get_last();
        if (!$err) return;

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($err['type'], $fatalTypes, true)) return;

        $msg = "FATAL: {$err['message']} em {$err['file']}:{$err['line']}";
        dbgLog($logFile, $msg);

        if ($pdo && $runId) {
            try {
                runMarkErrorFull($pdo, $runId, $msg, [
                    'fatal' => $err,
                ]);
            } catch (Throwable $e) {
                dbgLog($logFile, "FATAL_DB_FAIL: " . $e->getMessage());
            }
        }
    });
}
