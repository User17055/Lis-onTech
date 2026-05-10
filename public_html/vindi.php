<?php
date_default_timezone_set('America/Sao_Paulo');

$LOG_DIR = __DIR__ . '/storage/logs';
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0755, true);
}
$LOG_FILE = $LOG_DIR . '/vindi.log';

ini_set('log_errors', '1');
ini_set('error_log', $LOG_FILE);
error_reporting(E_ALL);
ini_set('display_errors', '0');

function bootlog($msg)
{
    global $LOG_FILE;
    @file_put_contents($LOG_FILE, "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
}


if (isset($_GET['ping']) && $_GET['ping'] === '1') {
    bootlog("PING OK: " . ($_SERVER['REQUEST_URI'] ?? ''));
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK - vindi.php executou\n";
    exit;
}


register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e)
        return;
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($e['type'], $fatal, true))
        return;
    bootlog("FATAL: {$e['message']} em {$e['file']}:{$e['line']}");
});

$need = ['config.php', 'db.php', 'runs_db.php'];
foreach ($need as $f) {
    $p = __DIR__ . '/' . $f;
    if (!file_exists($p)) {
        bootlog("ERRO: arquivo faltando: {$p}");
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo "ERRO: arquivo faltando: {$f}\n";
        exit;
    }
}

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/runs_db.php';
    ;
} catch (Throwable $ex) {
    bootlog("EXCEPTION NO REQUIRE: " . $ex->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo "ERRO ao carregar arquivos: " . $ex->getMessage() . "\n";
    exit;
}

if (isset($_GET['selftest'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $t = (string) $_GET['selftest'];

    if ($t === 'db') {
        try {
            $pdo->query("SELECT 1");
            echo "DB OK\n";
        } catch (Throwable $e) {
            bootlog("DB ERRO: " . $e->getMessage());
            echo "DB ERRO: " . $e->getMessage() . "\n";
        }
        exit;
    }

    if ($t === 'insert') {
        try {
            $rid = bin2hex(random_bytes(16));
            $st = $pdo->prepare("INSERT INTO automation_runs (run_id, event_type, status, step_vindi) VALUES (?, 'debug', 'processing', 1)");
            $st->execute([$rid]);
            echo "INSERT OK run_id={$rid}\n";
        } catch (Throwable $e) {
            bootlog("INSERT ERRO: " . $e->getMessage());
            echo "INSERT ERRO: " . $e->getMessage() . "\n";
        }
        exit;
    }

    echo "selftest inválido. Use db ou insert.\n";
    exit;
}

bootlog("BOOT OK, seguindo fluxo normal.");


date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/runs_db.php';


$META_PHONE_NUMBER_ID = cfg($cfg, 'META_PHONE_NUMBER_ID');
$META_ACCESS_TOKEN = cfg($cfg, 'META_ACCESS_TOKEN');

$VINDI_API_KEY = cfg($cfg, 'VINDI_API_KEY');
$VINDI_API_BASE = cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1');

$TEMPLATE_NAME = cfg($cfg, 'META_TEMPLATE_NAME');
if ($TEMPLATE_NAME === '') {
    $TEMPLATE_NAME = 'fatura22';
}
$TEMPLATE_LANG = cfg($cfg, 'META_TEMPLATE_LANG', 'pt_BR');
$FIRST_DELAY_DAYS = max(1, (int) cfg($cfg, 'RECOBRANCA_FIRST_DELAY_DAYS', '7'));

$LOG_FILE = $LOG_DIR . '/vindi.log';



$DEBUG = (cfg($cfg, 'DEBUG', '0') === '1') || (isset($_GET['debug']) && $_GET['debug'] === '1');
$DRY_RUN = (isset($_GET['dry_run']) && $_GET['dry_run'] === '1');

if ($DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

function mask(string $v): string
{
    $v = trim($v);
    if ($v === '')
        return 'VAZIO';
    if (strlen($v) <= 10)
        return $v;
    return substr($v, 0, 4) . '...' . substr($v, -4);
}

function dbg(string $logFile, bool $debug, ?PDO $pdo, ?string $runId, string $step, array $extra = []): void
{
    if (!$debug)
        return;

    $msg = "DBG {$step}";
    if (!empty($extra))
        $msg .= " | " . json_encode($extra, JSON_UNESCAPED_UNICODE);

    file_put_contents($logFile, "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);

    if ($pdo && $runId) {
        try {
            runLog($pdo, $runId, 'info', $msg);
        } catch (Throwable $e) {
        }
    }
}

function mysqlDateTimeOrNull($value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function billAmountOrNull(array $bill): ?string
{
    foreach (['amount', 'total', 'value'] as $key) {
        if (isset($bill[$key]) && $bill[$key] !== '') {
            return (string) $bill[$key];
        }
    }

    return null;
}

$runId = null;


register_shutdown_function(function () use ($LOG_FILE, $DEBUG, $pdo, &$runId) {
    $err = error_get_last();
    if (!$err)
        return;

    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($err['type'], $fatal, true))
        return;

    $msg = "FATAL: {$err['message']} em {$err['file']}:{$err['line']}";
    file_put_contents($LOG_FILE, "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);

    if ($pdo && $runId) {
        try {
            runMarkErrorFull($pdo, $runId, $msg, ['fatal' => $err]);
        } catch (Throwable $e) {
        }
    }
});


if ($DEBUG && isset($_GET['selftest'])) {
    header('Content-Type: text/plain; charset=utf-8');

    $t = (string) $_GET['selftest'];

    if ($t === 'db') {
        try {
            $pdo->query("SELECT 1");
            echo "DB OK\n";
        } catch (Throwable $e) {
            echo "DB ERRO: {$e->getMessage()}\n";
        }
        exit;
    }

    if ($t === 'insert') {
        try {
            $rid = bin2hex(random_bytes(16));
            $st = $pdo->prepare("
                INSERT INTO automation_runs (run_id, event_type, status, step_vindi)
                VALUES (?, 'debug', 'processing', 1)
            ");
            $st->execute([$rid]);
            echo "INSERT OK run_id={$rid}\n";
        } catch (Throwable $e) {
            echo "INSERT ERRO: {$e->getMessage()}\n";
        }
        exit;
    }

    echo "selftest inválido. Use db ou insert.\n";
    exit;
}

dbg($LOG_FILE, $DEBUG, $pdo ?? null, null, 'BOOT', [
    'META_PHONE_NUMBER_ID' => mask($META_PHONE_NUMBER_ID),
    'META_ACCESS_TOKEN' => mask($META_ACCESS_TOKEN),
    'VINDI_API_KEY' => mask($VINDI_API_KEY),
    'DB_HOST' => mask(cfg($cfg, 'DB_HOST')),
    'DB_NAME' => mask(cfg($cfg, 'DB_NAME')),
    'DB_USER' => mask(cfg($cfg, 'DB_USER')),
    'DRY_RUN' => $DRY_RUN ? 1 : 0
]);



if ($META_PHONE_NUMBER_ID === '' || $META_ACCESS_TOKEN === '' || $VINDI_API_KEY === '' || $TEMPLATE_NAME === '') {
    logLine($LOG_FILE, "ERRO: config.env incompleto (META/VINDI).");
    dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'ENV_FAIL', [
        'META_PHONE_NUMBER_ID' => mask($META_PHONE_NUMBER_ID),
        'META_ACCESS_TOKEN' => mask($META_ACCESS_TOKEN),
        'VINDI_API_KEY' => mask($VINDI_API_KEY),
        'TEMPLATE_NAME' => $TEMPLATE_NAME,
    ]);
    http_response_code(200);
    exit;
}



$input = file_get_contents('php://input');
$dados = json_decode($input, true);

logLine($LOG_FILE, "NOVO AVISO DA VINDI: " . ($input ?: '[vazio]'));
dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'PAYLOAD_READ', [
    'len' => strlen($input ?: ''),
    'json_ok' => is_array($dados)
]);

if (!is_array($dados) || !isset($dados['event']['type'])) {
    dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'PAYLOAD_INVALID');
    http_response_code(200);
    exit;
}

$evento = (string) $dados['event']['type'];

if ($evento === 'test') {
    dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'EVENT_TEST');
    http_response_code(200);
    exit;
}

// --- PARA LEMBRETES QUANDO PAGAR/CANCELAR ---
if ($evento === 'bill_paid' || $evento === 'bill_canceled') {

    $bill = $dados['event']['data']['bill'] ?? null;
    $billIdInt = (int) (is_array($bill) ? ($bill['id'] ?? 0) : 0);

    if ($billIdInt > 0) {
        // Upsert: se não existir, cria; se existir, desativa
        $st = $pdo->prepare("
            INSERT INTO bill_reminders (bill_id, active)
            VALUES (?, 0)
            ON DUPLICATE KEY UPDATE active=0
        ");
        $st->execute([$billIdInt]);

        logLine($LOG_FILE, "bill_reminders desativado por {$evento} | bill_id={$billIdInt}");
    } else {
        logLine($LOG_FILE, "AVISO: {$evento} sem bill_id no payload.");
    }

    http_response_code(200);
    exit;
}

try {
    if ($evento === 'bill_created') {

        $bill = $dados['event']['data']['bill'] ?? null;
        if (!is_array($bill)) {
            logLine($LOG_FILE, "ERRO: bill_created sem bill no payload.");
            dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'BILL_MISSING');
            http_response_code(200);
            exit;
        }

        $customer = $bill['customer'] ?? [];
        $nome = (string) ($customer['name'] ?? 'Cliente');
        $customerId = $customer['id'] ?? null;

        $billId = $bill['id'] ?? null;
        $link_fatura = (string) ($bill['url'] ?? '');

        $billIdInt = (int) ($billId ?? 0);

        // --- TRAVA: NÃO REENVIAR bill_created SE JÁ FOI ENVIADO ---
        if ($billIdInt > 0) {
            $st = $pdo->prepare("SELECT created_sent_at FROM bill_reminders WHERE bill_id=? LIMIT 1");
            $st->execute([$billIdInt]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['created_sent_at'])) {
                logLine($LOG_FILE, "bill_created duplicado ignorado: já enviado | bill_id={$billIdInt}");
                http_response_code(200);
                exit;
            }
        }

        if ($billIdInt > 0) {
            $seedItemsText = buildBillItemsText($bill);
            $seedDueAtSql = mysqlDateTimeOrNull($bill['due_at'] ?? null);
            $seedAmount = billAmountOrNull($bill);

            try {
                $st = $pdo->prepare("
                    INSERT INTO bill_reminders (
                        bill_id, customer_id, customer_name, phone, bill_url, items_text,
                        amount, due_at, active, blocked, status, next_reminder_at
                    )
                    VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1, 0, 'unpaid',
                        CASE
                            WHEN ? IS NULL THEN NULL
                            ELSE DATE_ADD(?, INTERVAL {$FIRST_DELAY_DAYS} DAY)
                        END
                    )
                    ON DUPLICATE KEY UPDATE
                        customer_id = VALUES(customer_id),
                        customer_name = VALUES(customer_name),
                        bill_url = VALUES(bill_url),
                        items_text = VALUES(items_text),
                        amount = COALESCE(VALUES(amount), amount),
                        due_at = COALESCE(VALUES(due_at), due_at),
                        active = IF(status IN ('paid', 'canceled', 'cancelled'), active, 1),
                        status = IF(status IN ('paid', 'canceled', 'cancelled'), status, 'unpaid'),
                        next_reminder_at = CASE
                            WHEN next_reminder_at IS NOT NULL THEN next_reminder_at
                            WHEN VALUES(due_at) IS NULL THEN next_reminder_at
                            ELSE DATE_ADD(VALUES(due_at), INTERVAL {$FIRST_DELAY_DAYS} DAY)
                        END
                ");
                $st->execute([
                    $billIdInt,
                    !empty($customerId) ? (int) $customerId : null,
                    $nome,
                    $link_fatura,
                    $seedItemsText,
                    $seedAmount,
                    $seedDueAtSql,
                    $seedDueAtSql,
                    $seedDueAtSql,
                ]);
            } catch (Throwable $e) {
                logLine($LOG_FILE, "ERRO ao criar controle inicial bill_reminders: " . $e->getMessage());
            }
        }


        dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'RUN_CREATE_START', [
            'evento' => $evento,
            'cliente' => $nome,
            'billId' => $billId
        ]);


        try {
            $runId = runCreate($pdo, [
                'event_type' => $evento,
                'customer_id' => $customerId,
                'customer_name' => $nome,
                'bill_id' => $billId,
                'bill_url' => $link_fatura,
                'vindi_input' => $dados
            ]);
        } catch (Throwable $e) {
            dbg($LOG_FILE, true, null, null, 'RUN_CREATE_FAIL', ['err' => $e->getMessage()]);
            logLine($LOG_FILE, "ERRO DB runCreate: " . $e->getMessage());
            http_response_code(200);
            exit;
        }

        dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'RUN_CREATED', ['runId' => $runId]);
        runLog($pdo, $runId, 'info', 'Recebido bill_created, iniciando.');

        // telefone
        $telefone_cliente = extractPhoneFromPayload($bill);
        dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'PHONE_EXTRACTED', ['telefone' => mask((string) $telefone_cliente)]);

        if ($telefone_cliente === null || trim($telefone_cliente) === '') {
            if (!empty($customerId)) {
                runLog($pdo, $runId, 'info', 'Telefone não veio no payload, buscando na Vindi.');
                $telefone_cliente = getCustomerPhoneFromVindi((int) $customerId, $VINDI_API_BASE, $VINDI_API_KEY, $LOG_FILE);
                dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'PHONE_FETCHED_VINDI', ['telefone' => mask((string) $telefone_cliente)]);
            }
        }

        if ($telefone_cliente === null || trim($telefone_cliente) === '') {
            $msg = "Cliente sem telefone, não enviado.";
            runLog($pdo, $runId, 'error', $msg);
            runMarkNotSent($pdo, $runId, $msg);
            dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'NO_PHONE');
            http_response_code(200);
            exit;
        }

        $itens_texto = buildBillItemsText($bill);
        runLog($pdo, $runId, 'info', "Montado envio WhatsApp. Itens: {$itens_texto}");
        dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'ITEMS_OK', ['itens' => $itens_texto]);

        $dueAtSql = mysqlDateTimeOrNull($bill['due_at'] ?? null);
        $amount = billAmountOrNull($bill);

        if ($billIdInt > 0) {
            try {
                $st = $pdo->prepare("
                    INSERT INTO bill_reminders (
                        bill_id, customer_id, customer_name, phone, bill_url, items_text,
                        amount, due_at, active, blocked, status, next_reminder_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 'unpaid',
                        CASE
                            WHEN ? IS NULL THEN NULL
                            ELSE DATE_ADD(?, INTERVAL {$FIRST_DELAY_DAYS} DAY)
                        END
                    )
                    ON DUPLICATE KEY UPDATE
                        customer_id = VALUES(customer_id),
                        customer_name = VALUES(customer_name),
                        phone = VALUES(phone),
                        bill_url = VALUES(bill_url),
                        items_text = VALUES(items_text),
                        amount = COALESCE(VALUES(amount), amount),
                        due_at = COALESCE(VALUES(due_at), due_at),
                        active = IF(status IN ('paid', 'canceled', 'cancelled'), active, 1),
                        status = IF(status IN ('paid', 'canceled', 'cancelled'), status, 'unpaid'),
                        next_reminder_at = CASE
                            WHEN next_reminder_at IS NOT NULL THEN next_reminder_at
                            WHEN VALUES(due_at) IS NULL THEN next_reminder_at
                            ELSE DATE_ADD(VALUES(due_at), INTERVAL {$FIRST_DELAY_DAYS} DAY)
                        END
                ");
                $st->execute([
                    $billIdInt,
                    !empty($customerId) ? (int) $customerId : null,
                    $nome,
                    $telefone_cliente,
                    $link_fatura,
                    $itens_texto,
                    $amount,
                    $dueAtSql,
                    $dueAtSql,
                    $dueAtSql,
                ]);
            } catch (Throwable $e) {
                logLine($LOG_FILE, "ERRO ao preparar bill_reminders: " . $e->getMessage());
            }
        }

        $variaveis = [
            ["type" => "text", "text" => waClean($nome)],
            ["type" => "text", "text" => waClean($link_fatura)],
            ["type" => "text", "text" => waClean($itens_texto)],
        ];


        if ($DRY_RUN) {
            runLog($pdo, $runId, 'info', 'DRY_RUN=1, pulando envio WhatsApp.');
            runMarkProcessed($pdo, $runId, $telefone_cliente, ['dry_run' => true], ['dry_run' => true], 0);
            dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'DRY_RUN_DONE');
            http_response_code(200);
            exit;
        }

        dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'WA_SEND_START', [
            'to' => mask((string) $telefone_cliente),
            'template' => $TEMPLATE_NAME
        ]);

        $resultado = enviarTemplateWhatsApp(
            $META_PHONE_NUMBER_ID,
            $META_ACCESS_TOKEN,
            $telefone_cliente,
            $TEMPLATE_NAME,
            $TEMPLATE_LANG,
            $variaveis
        );

        dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'WA_SEND_END', [
            'http' => $resultado['http'] ?? null,
            'curl_error' => $resultado['curl_error'] ?? null,
            'response_preview' => substr((string) ($resultado['response_raw'] ?? ''), 0, 200),
        ]);

        $respArr = json_decode($resultado['response_raw'] ?? '', true);
        if (!is_array($respArr))
            $respArr = ['raw' => ($resultado['response_raw'] ?? '')];

        $metaOk = ($resultado['http'] >= 200 && $resultado['http'] < 300)
            && empty($resultado['curl_error'])
            && empty($respArr['error']);

        if ($metaOk) {
            runLog($pdo, $runId, 'info', 'WhatsApp enviado, Meta aceitou.');
            runMarkProcessed($pdo, $runId, $telefone_cliente, $resultado['request'], $respArr, (int) $resultado['http']);
            // --- MARCA QUE bill_created FOI ENVIADO (created_sent_at) ---
            try {
                $st = $pdo->prepare("
                    UPDATE bill_reminders
                    SET customer_id = COALESCE(?, customer_id),
                        active = IF(status IN ('paid', 'canceled', 'cancelled'), active, 1),
                        created_sent_at = COALESCE(created_sent_at, NOW())
                    WHERE bill_id = ?
                ");
                $st->execute([
                    !empty($customerId) ? (int) $customerId : null,
                    (int) $billIdInt
                ]);
            } catch (Throwable $e) {
                logLine($LOG_FILE, "ERRO ao gravar bill_reminders (created_sent_at): " . $e->getMessage());
            }

            dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'STATUS_PROCESSED');
        } else {
            $metaMsg = $respArr['error']['message'] ?? '';
            $metaCode = $respArr['error']['code'] ?? '';
            $metaSub = $respArr['error']['error_subcode'] ?? '';
            $msg = "Falha WhatsApp, HTTP={$resultado['http']}, code={$metaCode}, sub={$metaSub}, msg={$metaMsg}";

            runLog($pdo, $runId, 'error', $msg);

            runMarkErrorFull($pdo, $runId, $msg, [
                'meta_http' => (int) ($resultado['http'] ?? 0),
                'curl_error' => $resultado['curl_error'] ?? null,
                'whatsapp_request' => $resultado['request'] ?? [],
                'whatsapp_response' => $respArr
            ]);

            dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'STATUS_ERROR', [
                'msg' => $msg
            ]);
        }

        logLine($LOG_FILE, "Run {$runId} finalizado. HTTP Meta: " . ($resultado['http'] ?? ''));
    }

} catch (Throwable $e) {
    logLine($LOG_FILE, "EXCEÇÃO: " . $e->getMessage());
    dbg($LOG_FILE, $DEBUG, $pdo, $runId, 'EXCEPTION', ['err' => $e->getMessage()]);

    if ($runId) {
        runLog($pdo, $runId, 'error', 'Exceção: ' . $e->getMessage());
        runMarkErrorFull($pdo, $runId, $e->getMessage(), [
            'exception_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

http_response_code(200);
exit;



function logLine(string $file, string $msg): void
{
    $line = "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND);
}

function waClean(string $s): string
{
    $s = preg_replace("/[\r\n\t]+/", " ", $s);
    $s = preg_replace("/ {2,}/", " ", $s);
    return trim($s);
}

function buildBillItemsText(array $bill): string
{
    $itens = $bill['bill_items'] ?? [];
    if (!is_array($itens) || empty($itens))
        return "Sem itens informados";

    $linhas = [];
    foreach ($itens as $item) {
        if (!is_array($item))
            continue;
        $nomeItem = $item['product']['name'] ?? $item['description'] ?? 'Item';
        $qtd = $item['quantity'] ?? null;

        if (!empty($qtd) && (int) $qtd > 1)
            $linhas[] = "{$nomeItem}, " . (int) $qtd . " un";
        else
            $linhas[] = "{$nomeItem}";
    }

    return waClean(implode(" | ", $linhas));
}

function extractPhoneFromPayload(array $bill): ?string
{
    $customer = $bill['customer'] ?? [];
    if (!is_array($customer))
        $customer = [];

    $candidates = [
        $customer['phone_number'] ?? null,
        $customer['mobile'] ?? null,
        $customer['phone'] ?? null,
    ];

    if (!empty($customer['phones']) && is_array($customer['phones'])) {
        $first = $customer['phones'][0] ?? null;
        if (is_array($first)) {
            $candidates[] = $first['number'] ?? null;
            $candidates[] = $first['phone_number'] ?? null;
        } elseif (is_string($first)) {
            $candidates[] = $first;
        }
    }

    if (!empty($bill['metadata']) && is_array($bill['metadata'])) {
        $candidates[] = $bill['metadata']['whatsapp'] ?? null;
        $candidates[] = $bill['metadata']['telefone'] ?? null;
        $candidates[] = $bill['metadata']['phone'] ?? null;
    }

    if (!empty($customer['metadata']) && is_array($customer['metadata'])) {
        $candidates[] = $customer['metadata']['whatsapp'] ?? null;
        $candidates[] = $customer['metadata']['telefone'] ?? null;
        $candidates[] = $customer['metadata']['phone'] ?? null;
    }

    foreach ($candidates as $cand) {
        if (is_string($cand) && trim($cand) !== '')
            return $cand;
    }
    return null;
}

function getCustomerPhoneFromVindi(int $customerId, string $base, string $apiKey, string $logFile): ?string
{
    if (empty($apiKey)) {
        logLine($logFile, "AVISO: VINDI_API_KEY não configurada.");
        return null;
    }

    $url = rtrim($base, '/') . "/customers/" . $customerId;
    $respRaw = curlGetJson($url, $apiKey);

    if ($respRaw === null) {
        logLine($logFile, "ERRO: Falha ao chamar Vindi GET /customers/{$customerId}");
        return null;
    }

    $customer = $respRaw['customer'] ?? $respRaw;
    if (!is_array($customer))
        return null;

    $candidates = [
        $customer['phone_number'] ?? null,
        $customer['mobile'] ?? null,
        $customer['phone'] ?? null,
    ];

    if (!empty($customer['phones']) && is_array($customer['phones'])) {
        $first = $customer['phones'][0] ?? null;
        if (is_array($first)) {
            $candidates[] = $first['number'] ?? null;
            $candidates[] = $first['phone_number'] ?? null;
        } elseif (is_string($first)) {
            $candidates[] = $first;
        }
    }

    if (!empty($customer['metadata']) && is_array($customer['metadata'])) {
        $candidates[] = $customer['metadata']['whatsapp'] ?? null;
        $candidates[] = $customer['metadata']['telefone'] ?? null;
        $candidates[] = $customer['metadata']['phone'] ?? null;
    }

    foreach ($candidates as $cand) {
        if (is_string($cand) && trim($cand) !== '')
            return $cand;
    }

    return null;
}

function curlGetJson(string $url, string $apiKey): ?array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($apiKey . ':'),
    ]);

    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $http < 200 || $http >= 300)
        return null;

    $json = json_decode($res, true);
    return is_array($json) ? $json : null;
}

function enviarTemplateWhatsApp(
    string $phoneNumberId,
    string $token,
    string $destinatario,
    string $templateNome,
    string $lang,
    array $paramsBody
): array {
    $destinatario = preg_replace('/[^0-9]/', '', $destinatario ?? '');

    if (strlen($destinatario) > 0 && strlen($destinatario) <= 11) {
        $destinatario = "55" . $destinatario;
    }

    foreach ($paramsBody as &$p) {
        if (is_array($p) && ($p['type'] ?? '') === 'text' && isset($p['text'])) {
            $p['text'] = waClean((string) $p['text']);
        }
    }
    unset($p);

    $url = "https://graph.facebook.com/v21.0/{$phoneNumberId}/messages";

    $payload = [
        "messaging_product" => "whatsapp",
        "recipient_type" => "individual",
        "to" => $destinatario,
        "type" => "template",
        "template" => [
            "name" => $templateNome,
            "language" => ["code" => $lang],
            "components" => [
                ["type" => "body", "parameters" => $paramsBody]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'http' => (int) $http,
        'curl_error' => $err ?: null,
        'request' => $payload,
        'response_raw' => $res ?: ''
    ];
}
