<?php
date_default_timezone_set('America/Sao_Paulo');

$LOG_DIR = __DIR__ . '/../../storage/logs';
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0755, true);
}
$LOG_FILE = $LOG_DIR . '/weekly_reminders.log';

ini_set('log_errors', '1');
ini_set('error_log', $LOG_FILE);
error_reporting(E_ALL);
ini_set('display_errors', '0');

function logLine(string $msg): void {
    global $LOG_FILE;
    @file_put_contents($LOG_FILE, "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
}

function waClean(string $s): string {
    $s = preg_replace("/[\r\n\t]+/", " ", $s);
    $s = preg_replace("/ {2,}/", " ", $s);
    return trim($s);
}

function mask(string $v): string {
    $v = trim($v);
    if ($v === '') return 'VAZIO';
    if (strlen($v) <= 10) return $v;
    return substr($v, 0, 4) . '...' . substr($v, -4);
}

function curlGetJson(string $url, string $apiKey): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($apiKey . ':'),
    ]);

    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($res === false || $http < 200 || $http >= 300) {
        logLine("VINDI HTTP={$http} err=" . ($err ?: ''));
        return null;
    }

    $json = json_decode($res, true);
    return is_array($json) ? $json : null;
}

function getBillFromVindi(int $billId, string $base, string $apiKey): ?array {
    $url = rtrim($base, '/') . "/bills/" . $billId;
    $raw = curlGetJson($url, $apiKey);
    if (!$raw) return null;
    $bill = $raw['bill'] ?? $raw;
    return is_array($bill) ? $bill : null;
}

function getCustomerFromVindi(int $customerId, string $base, string $apiKey): ?array {
    $url = rtrim($base, '/') . "/customers/" . $customerId;
    $raw = curlGetJson($url, $apiKey);
    if (!$raw) return null;
    $customer = $raw['customer'] ?? $raw;
    return is_array($customer) ? $customer : null;
}

function extractPhoneFromCustomer(array $customer): ?string {
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
        if (is_string($cand) && trim($cand) !== '') return $cand;
    }
    return null;
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

    // se vier só DDD+numero (10/11 dígitos), prefixa 55
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

function canSendToCustomer(PDO $pdo, int $customerId): bool {
    $st = $pdo->prepare("SELECT last_sent_at FROM customer_weekly_reminders WHERE customer_id=? LIMIT 1");
    $st->execute([$customerId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['last_sent_at'])) return true;

    $last = strtotime($row['last_sent_at']);
    return ($last <= strtotime('-7 days'));
}

function markCustomerSent(PDO $pdo, int $customerId): void {
    $st = $pdo->prepare("
        INSERT INTO customer_weekly_reminders (customer_id, last_sent_at, sent_count)
        VALUES (?, NOW(), 1)
        ON DUPLICATE KEY UPDATE
            last_sent_at=NOW(),
            sent_count=sent_count+1
    ");
    $st->execute([$customerId]);
}

function markBillSent(PDO $pdo, int $billId): void {
    $st = $pdo->prepare("
        UPDATE bill_reminders
        SET weekly_last_sent_at=NOW(),
            weekly_sent_count=weekly_sent_count+1
        WHERE bill_id=?
        LIMIT 1
    ");
    $st->execute([$billId]);
}

function deactivateBillReminder(PDO $pdo, int $billId): void {
    $st = $pdo->prepare("UPDATE bill_reminders SET active=0 WHERE bill_id=? LIMIT 1");
    $st->execute([$billId]);
}

/* =======================
   BOOT + includes
======================= */

$need = [__DIR__ . '/../../config.php', __DIR__ . '/../../db.php', __DIR__ . '/../../runs_db.php'];
foreach ($need as $p) {
    if (!file_exists($p)) {
        logLine("ERRO: arquivo faltando: {$p}");
        http_response_code(500);
        exit("ERRO: faltando include\n");
    }
}

require_once $need[0];
require_once $need[1];
require_once $need[2];

/* =======================
   Proteção / token
======================= */
$CRON_TOKEN = cfg($cfg, 'CRON_TOKEN', '');
if (php_sapi_name() !== 'cli') {
    $token = (string)($_GET['token'] ?? '');
    if ($CRON_TOKEN === '' || !hash_equals($CRON_TOKEN, $token)) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

/* =======================
   Flags
======================= */
$DRY_RUN = (php_sapi_name() !== 'cli')
    ? (isset($_GET['dry_run']) && $_GET['dry_run'] === '1')
    : (in_array('--dry-run', $argv ?? [], true));

$LIMIT = (int)(php_sapi_name() !== 'cli' ? ($_GET['limit'] ?? 200) : 200);
if ($LIMIT <= 0) $LIMIT = 200;

/* =======================
   Lock anti execução dupla
======================= */
$lockFp = @fopen(__DIR__ . '/weekly_reminder.lock', 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    logLine("Já existe uma execução em andamento, abortando.");
    exit("Already running\n");
}

/* =======================
   ENV
======================= */
$META_PHONE_NUMBER_ID = cfg($cfg, 'META_PHONE_NUMBER_ID');
$META_ACCESS_TOKEN    = cfg($cfg, 'META_ACCESS_TOKEN');

$VINDI_API_KEY  = cfg($cfg, 'VINDI_API_KEY');
$VINDI_API_BASE = cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1');

$TEMPLATE_WEEKLY_NAME = cfg($cfg, 'META_TEMPLATE_WEEKLY_NAME', '');
$TEMPLATE_WEEKLY_LANG = cfg($cfg, 'META_TEMPLATE_WEEKLY_LANG', 'pt_BR');
$FIRST_DELAY_DAYS = max(1, (int) cfg($cfg, 'RECOBRANCA_FIRST_DELAY_DAYS', '7'));

if ($META_PHONE_NUMBER_ID === '' || $META_ACCESS_TOKEN === '' || $VINDI_API_KEY === '' || $TEMPLATE_WEEKLY_NAME === '') {
    logLine("ERRO: config incompleto. META/VINDI/TEMPLATE_WEEKLY.");
    exit("Config incompleto\n");
}

logLine("CRON weekly start | DRY_RUN=" . ($DRY_RUN ? '1' : '0'));

/* =======================
   Seleciona faturas:
   - active=1
   - created_sent_at <= 7 dias (ficou 1 semana sem pagar)
   - não enviou semanalmente nos últimos 7 dias
======================= */
$st = $pdo->prepare("
    SELECT bill_id, customer_id, due_at, created_sent_at, weekly_last_sent_at
    FROM bill_reminders
    WHERE active=1
      AND bill_id IS NOT NULL
      AND customer_id IS NOT NULL
      AND due_at IS NOT NULL
      AND due_at <= DATE_SUB(NOW(), INTERVAL {$FIRST_DELAY_DAYS} DAY)
      AND (weekly_last_sent_at IS NULL OR weekly_last_sent_at <= DATE_SUB(NOW(), INTERVAL 7 DAY))
    ORDER BY due_at ASC
    LIMIT {$LIMIT}
");
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

logLine("Candidatos encontrados: " . count($rows));

foreach ($rows as $r) {
    $billId = (int)$r['bill_id'];
    $customerId = (int)$r['customer_id'];

    // trava por cliente: 1x por semana por cliente
    if (!canSendToCustomer($pdo, $customerId)) {
        logLine("SKIP cliente {$customerId}: já cobrado nos últimos 7 dias.");
        continue;
    }

    $createdSentAt = $r['created_sent_at'] ?? null;
    $dueAt = $r['due_at'] ?? null;
    $daysOpen = $dueAt ? (int)floor((time() - strtotime((string)$dueAt)) / 86400) : $FIRST_DELAY_DAYS;

    // busca bill na Vindi para pegar status/url atual
    $bill = getBillFromVindi($billId, $VINDI_API_BASE, $VINDI_API_KEY);
    if (!$bill) {
        logLine("ERRO bill {$billId}: não consegui buscar na Vindi.");
        continue;
    }

    $status = strtolower((string)($bill['status'] ?? ''));
    if (in_array($status, ['paid', 'canceled', 'cancelled'], true)) {
        deactivateBillReminder($pdo, $billId);
        logLine("DESATIVADO bill {$billId} status={$status}");
        continue;
    }

    $billUrl = (string)($bill['url'] ?? '');

    // busca cliente na Vindi para pegar nome/telefone
    $customer = getCustomerFromVindi($customerId, $VINDI_API_BASE, $VINDI_API_KEY);
    if (!$customer) {
        logLine("ERRO customer {$customerId}: não consegui buscar na Vindi.");
        continue;
    }

    $nome = (string)($customer['name'] ?? 'Cliente');
    $telefone = extractPhoneFromCustomer($customer);

    if (!$telefone) {
        logLine("SKIP customer {$customerId}: sem telefone.");
        continue;
    }

    // cria run (aparece no seu painel)
    $runId = null;
    try {
        $runId = runCreate($pdo, [
            'event_type' => 'weekly_reminder',
            'customer_id' => $customerId,
            'customer_name' => $nome,
            'bill_id' => $billId,
            'bill_url' => $billUrl,
            'vindi_input' => [
                'created_sent_at' => $createdSentAt,
                'due_at' => $dueAt,
                'days_open' => $daysOpen,
                'bill_status' => $status
            ]
        ]);
        runLog($pdo, $runId, 'info', "Weekly reminder iniciado.");
    } catch (Throwable $e) {
        logLine("ERRO runCreate: " . $e->getMessage());
        continue;
    }

    // template vars: {1}=nome, {2}=link, {3}=dias
    $params = [
        ["type" => "text", "text" => waClean($nome)],
        ["type" => "text", "text" => waClean($billUrl)],
        ["type" => "text", "text" => (string)$daysOpen],
    ];

    if ($DRY_RUN) {
        runLog($pdo, $runId, 'info', "DRY_RUN, pulando envio WhatsApp.");
        runMarkProcessed($pdo, $runId, $telefone, ['dry_run' => true], ['dry_run' => true], 0);
        // mesmo em dry_run, eu não marco o weekly_last_sent_at, pra não “queimar” a cobrança
        continue;
    }

    $res = enviarTemplateWhatsApp(
        $META_PHONE_NUMBER_ID,
        $META_ACCESS_TOKEN,
        $telefone,
        $TEMPLATE_WEEKLY_NAME,
        $TEMPLATE_WEEKLY_LANG,
        $params
    );

    $respArr = json_decode($res['response_raw'] ?? '', true);
    if (!is_array($respArr)) $respArr = ['raw' => ($res['response_raw'] ?? '')];

    $metaOk = ($res['http'] >= 200 && $res['http'] < 300)
        && empty($res['curl_error'])
        && empty($respArr['error']);

    if ($metaOk) {
        runLog($pdo, $runId, 'info', "WhatsApp semanal enviado.");
        runMarkProcessed($pdo, $runId, $telefone, $res['request'], $respArr, (int)$res['http']);

        // marca envio por cliente e por fatura
        markCustomerSent($pdo, $customerId);
        markBillSent($pdo, $billId);

        logLine("OK bill={$billId} customer={$customerId} tel=" . mask($telefone));
    } else {
        $metaMsg = $respArr['error']['message'] ?? '';
        $metaCode = $respArr['error']['code'] ?? '';
        $metaSub  = $respArr['error']['error_subcode'] ?? '';
        $msg = "Falha WhatsApp semanal, HTTP={$res['http']}, code={$metaCode}, sub={$metaSub}, msg={$metaMsg}";

        runLog($pdo, $runId, 'error', $msg);
        runMarkErrorFull($pdo, $runId, $msg, [
            'meta_http' => (int)($res['http'] ?? 0),
            'curl_error' => $res['curl_error'] ?? null,
            'whatsapp_request' => $res['request'] ?? [],
            'whatsapp_response' => $respArr
        ]);

        logLine("ERRO bill={$billId} customer={$customerId} {$msg}");
    }
}

logLine("CRON weekly end");
echo "OK\n";
