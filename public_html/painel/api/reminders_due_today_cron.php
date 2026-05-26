<?php
date_default_timezone_set('America/Sao_Paulo');

$LOG_DIR = __DIR__ . '/../../storage/logs';
if (!is_dir($LOG_DIR)) {
  @mkdir($LOG_DIR, 0755, true);
}
$LOG_FILE = $LOG_DIR . '/due_today_reminders.log';
ini_set('log_errors', '1');
ini_set('error_log', $LOG_FILE);
error_reporting(E_ALL);
ini_set('display_errors', '0');

function logLine(string $msg): void {
  global $LOG_FILE;
  @file_put_contents($LOG_FILE, "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
}

function respondCronError(string $message): void {
  logLine("ERRO fatal: " . $message);
  if (!headers_sent()) {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
  }
  echo "ERRO " . $message . "\n";
}

set_exception_handler(function (Throwable $e): void {
  respondCronError($e->getMessage());
  exit;
});

register_shutdown_function(function (): void {
  $err = error_get_last();
  if (!$err) return;
  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
  if (!in_array((int)$err['type'], $fatalTypes, true)) return;
  $file = isset($err['file']) ? basename((string)$err['file']) : 'arquivo desconhecido';
  $line = isset($err['line']) ? (string)$err['line'] : '?';
  respondCronError((string)$err['message'] . " em {$file}:{$line}");
});

function findRootWithFiles(array $files): string {
  $dir = __DIR__;
  for ($i = 0; $i < 10; $i++) {
    $ok = true;
    foreach ($files as $f) {
      if (!file_exists($dir . '/' . $f)) { $ok = false; break; }
    }
    if ($ok) return $dir;
    $dir = dirname($dir);
  }
  throw new RuntimeException("Raiz nao encontrada para: " . implode(', ', $files));
}

if (isset($_GET['ping']) && $_GET['ping'] === '1') {
  header('Content-Type: text/plain; charset=utf-8');
  echo "OK reminders_due_today_cron.php\n";
  exit;
}

$ROOT = findRootWithFiles(['config.php', 'db.php']);
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/auth.php';

$CRON_TOKENS = array_values(array_unique(array_filter([
  cfg($cfg, 'CRON_TOKEN', ''),
  cfg($cfg, 'RECOBRANCA_CRON_TOKEN', ''),
  cfg($cfg, 'DUE_TODAY_CRON_TOKEN', ''),
], static fn($v) => trim((string)$v) !== '')));
$REQ_TOKEN = (string)($_GET['token'] ?? '');
if ($REQ_TOKEN === '') {
  $REQ_TOKEN = (string)($_POST['token'] ?? '');
}
if ($REQ_TOKEN === '') {
  $REQ_TOKEN = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
}
if ($REQ_TOKEN === '') {
  $authHeader = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
  if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $REQ_TOKEN = trim((string)$m[1]);
  }
}

$TOKEN_OK = false;
foreach ($CRON_TOKENS as $tokenCandidate) {
  if ($REQ_TOKEN !== '' && hash_equals($tokenCandidate, $REQ_TOKEN)) {
    $TOKEN_OK = true;
    break;
  }
}

if (!$TOKEN_OK) {
  authRequireApi();
}

require_once $ROOT . '/db.php';
require_once $ROOT . '/includes/chat_db.php';

$META_PHONE_NUMBER_ID = cfg($cfg, 'META_PHONE_NUMBER_ID');
$META_ACCESS_TOKEN    = cfg($cfg, 'META_ACCESS_TOKEN');

$VINDI_API_KEY  = cfg($cfg, 'VINDI_API_KEY');
$VINDI_API_BASE = cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1');

$TEMPLATE_NAME = cfg($cfg, 'META_TEMPLATE_DUE_TODAY_NAME', 'vencimento_hoje');
$TEMPLATE_LANG = cfg($cfg, 'META_TEMPLATE_DUE_TODAY_LANG', cfg($cfg, 'META_TEMPLATE_LANG', 'pt_BR'));

$DRY_RUN = (isset($_GET['dry_run']) && $_GET['dry_run'] === '1');
$LIMIT = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
$ONLY_BILL_ID = isset($_GET['bill_id']) ? max(0, (int)$_GET['bill_id']) : 0;

if ($META_PHONE_NUMBER_ID === '' || $META_ACCESS_TOKEN === '' || $VINDI_API_KEY === '' || $TEMPLATE_NAME === '') {
  logLine("ERRO config incompleta META/VINDI/TEMPLATE_DUE_TODAY.");
  header('Content-Type: text/plain; charset=utf-8');
  echo "ERRO config incompleta META/VINDI/TEMPLATE_DUE_TODAY.\n";
  http_response_code(200);
  exit;
}

function waClean(string $s): string {
  $s = preg_replace("/[\r\n\t]+/", " ", $s);
  $s = preg_replace("/ {2,}/", " ", $s);
  return trim($s);
}

function ensureBillReminderColumn(PDO $pdo, string $column, string $definition): void {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) return;

  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'bill_reminders'
      AND COLUMN_NAME = ?
  ");
  $st->execute([$column]);
  if ((int)$st->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE bill_reminders ADD COLUMN {$column} {$definition}");
  }
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
  curl_close($ch);

  if ($res === false || $http < 200 || $http >= 300) return null;
  $json = json_decode($res, true);
  return is_array($json) ? $json : null;
}

function vindiGetBill(int $billId, string $base, string $apiKey): ?array {
  $resp = curlGetJson(rtrim($base, '/') . '/bills/' . $billId, $apiKey);
  if (!$resp) return null;
  $bill = $resp['bill'] ?? $resp;
  return is_array($bill) ? $bill : null;
}

function extractPhoneFromBill(array $bill): string {
  $customer = $bill['customer'] ?? [];
  if (!is_array($customer)) $customer = [];

  $candidates = [
    $customer['phone_number'] ?? null,
    $customer['mobile'] ?? null,
    $customer['phone'] ?? null,
  ];

  if (!empty($customer['phones']) && is_array($customer['phones'])) {
    foreach ($customer['phones'] as $phone) {
      if (is_array($phone)) {
        $candidates[] = $phone['number'] ?? null;
        $candidates[] = $phone['phone_number'] ?? null;
      } elseif (is_string($phone)) {
        $candidates[] = $phone;
      }
    }
  }

  foreach ($candidates as $candidate) {
    if (is_string($candidate) && trim($candidate) !== '') return $candidate;
  }
  return '';
}

function getCustomerPhoneFromVindi(int $customerId, string $base, string $apiKey): string {
  if ($customerId <= 0) return '';

  $resp = curlGetJson(rtrim($base, '/') . '/customers/' . $customerId, $apiKey);
  if (!$resp) return '';

  $customer = $resp['customer'] ?? $resp;
  if (!is_array($customer)) return '';

  $candidates = [
    $customer['phone_number'] ?? null,
    $customer['mobile'] ?? null,
    $customer['phone'] ?? null,
  ];

  if (!empty($customer['phones']) && is_array($customer['phones'])) {
    foreach ($customer['phones'] as $phone) {
      if (is_array($phone)) {
        $candidates[] = $phone['number'] ?? null;
        $candidates[] = $phone['phone_number'] ?? null;
      } elseif (is_string($phone)) {
        $candidates[] = $phone;
      }
    }
  }

  foreach ($candidates as $candidate) {
    if (is_string($candidate) && trim($candidate) !== '') return $candidate;
  }
  return '';
}

function billAmountOrNull(array $bill): ?float {
  foreach (['amount', 'total', 'price', 'value'] as $key) {
    if (isset($bill[$key]) && is_numeric($bill[$key])) return (float)$bill[$key];
  }
  return null;
}

function formatMoneyBr($value): string {
  if ($value === null || $value === '') return 'valor nao informado';
  return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatDueDateBr($value): string {
  $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
  return $ts ? date('d/m/Y', $ts) : 'data nao informada';
}

function mysqlDateTimeOrNull($value): ?string {
  if ($value === null || trim((string)$value) === '') return null;
  $ts = strtotime((string)$value);
  return $ts ? date('Y-m-d H:i:s', $ts) : null;
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
  if (strlen($destinatario) > 0 && strlen($destinatario) <= 11) $destinatario = '55' . $destinatario;

  foreach ($paramsBody as &$p) {
    if (is_array($p) && ($p['type'] ?? '') === 'text' && isset($p['text'])) {
      $p['text'] = waClean((string)$p['text']);
    }
  }
  unset($p);

  $url = "https://graph.facebook.com/v21.0/{$phoneNumberId}/messages";
  $payload = [
    'messaging_product' => 'whatsapp',
    'recipient_type' => 'individual',
    'to' => $destinatario,
    'type' => 'template',
    'template' => [
      'name' => $templateNome,
      'language' => ['code' => $lang],
      'components' => [
        ['type' => 'body', 'parameters' => $paramsBody],
      ],
    ],
  ];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token,
  ]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  return [
    'http' => (int)$http,
    'curl_error' => $err ?: null,
    'request' => $payload,
    'response_raw' => $res ?: '',
  ];
}

function ensureReminderLogsTable(PDO $pdo): void {
  static $done = false;
  if ($done) return;

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS reminder_logs (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      bill_id BIGINT UNSIGNED NOT NULL,
      ok TINYINT(1) NOT NULL DEFAULT 0,
      http_code INT NULL,
      message VARCHAR(255) NULL,
      response_raw MEDIUMTEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_reminder_logs_bill_created (bill_id, created_at),
      KEY idx_reminder_logs_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  ");

  $done = true;
}

function logReminderAttempt(PDO $pdo, int $billId, bool $ok, int $httpCode, string $message, string $raw): void {
  try {
    ensureReminderLogsTable($pdo);
    $st = $pdo->prepare("
      INSERT INTO reminder_logs (bill_id, ok, http_code, message, response_raw)
      VALUES (?, ?, ?, ?, ?)
    ");
    $st->execute([$billId, $ok ? 1 : 0, $httpCode, substr($message, 0, 255), $raw]);
  } catch (Throwable $e) {
    logLine("bill_id={$billId} reminder_logs_fail=" . $e->getMessage());
  }
}

ensureBillReminderColumn($pdo, 'due_today_sent_at', 'DATETIME NULL');
ensureBillReminderColumn($pdo, 'due_today_sent_count', 'INT UNSIGNED NOT NULL DEFAULT 0');

$lock = $pdo->query("SELECT GET_LOCK('due_today_reminders_lock', 1) AS l")->fetch(PDO::FETCH_ASSOC);
if (empty($lock['l'])) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "LOCK\n";
  exit;
}

try {
  $whereSql = "
    active = 1
    AND blocked = 0
    AND bill_id IS NOT NULL
    AND due_at IS NOT NULL
    AND DATE(due_at) = CURDATE()
    AND due_today_sent_at IS NULL
    AND COALESCE(NULLIF(status, ''), 'unpaid') = 'unpaid'
    AND (last_status IS NULL OR last_status = '' OR last_status NOT IN ('paid', 'canceled', 'cancelled'))
    AND (last_status IS NULL OR last_status <> 'due_today_skip_created')
    AND (
      created_sent_at IS NULL
      OR DATE(created_sent_at) < CURDATE()
    )
  ";

  if ($ONLY_BILL_ID > 0) {
    $whereSql = "
      bill_id = :only_bill_id
      AND due_at IS NOT NULL
      AND DATE(due_at) = CURDATE()
      AND due_today_sent_at IS NULL
    ";
  }

  $sql = "
    SELECT bill_id, customer_id, customer_name, phone, bill_url, amount, due_at, created_sent_at
    FROM bill_reminders
    WHERE {$whereSql}
    ORDER BY due_at ASC
    LIMIT {$LIMIT}
  ";
  $st = $pdo->prepare($sql);
  if ($ONLY_BILL_ID > 0) {
    $st->execute([':only_bill_id' => $ONLY_BILL_ID]);
  } else {
    $st->execute();
  }
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  logLine("CRON start candidatos=" . count($rows) . " dry_run=" . ($DRY_RUN ? '1' : '0'));

  $sent = 0;
  $skipped = 0;
  $issues = [];

  foreach ($rows as $r) {
    $billId = (int)$r['bill_id'];
    $bill = vindiGetBill($billId, $VINDI_API_BASE, $VINDI_API_KEY);
    if (!$bill) {
      $pdo->prepare("
        UPDATE bill_reminders
        SET last_status = 'vindi_error',
            last_status_check_at = NOW()
        WHERE bill_id = ?
      ")->execute([$billId]);
      logLine("bill_id={$billId} erro_vindi");
      $issues[] = "bill_id={$billId} vindi_error";
      $skipped++;
      continue;
    }

    $vindiStatus = strtolower((string)($bill['status'] ?? ''));
    if ($vindiStatus === 'paid') {
      $pdo->prepare("
        UPDATE bill_reminders
        SET status='paid', active=0, last_status=?, last_status_check_at=NOW()
        WHERE bill_id=?
      ")->execute([$vindiStatus, $billId]);
      logLine("bill_id={$billId} pago_desativado");
      $issues[] = "bill_id={$billId} pago";
      $skipped++;
      continue;
    }

    if (in_array($vindiStatus, ['canceled', 'cancelled'], true)) {
      $pdo->prepare("
        UPDATE bill_reminders
        SET status='canceled', active=0, last_status=?, last_status_check_at=NOW()
        WHERE bill_id=?
      ")->execute([$vindiStatus, $billId]);
      logLine("bill_id={$billId} cancelado_desativado");
      $issues[] = "bill_id={$billId} cancelado";
      $skipped++;
      continue;
    }

    $vindiDueAt = mysqlDateTimeOrNull($bill['due_at'] ?? null);
    if ($vindiDueAt === null || date('Y-m-d', strtotime($vindiDueAt)) !== date('Y-m-d')) {
      $pdo->prepare("
        UPDATE bill_reminders
        SET due_at = COALESCE(?, due_at),
            last_status = ?,
            last_status_check_at = NOW()
        WHERE bill_id = ?
      ")->execute([$vindiDueAt, $vindiStatus ?: 'unpaid', $billId]);
      logLine("bill_id={$billId} vencimento_nao_e_hoje");
      $issues[] = "bill_id={$billId} vencimento_nao_e_hoje";
      $skipped++;
      continue;
    }

    if (!empty($r['created_sent_at']) && date('Y-m-d', strtotime((string)$r['created_sent_at'])) === date('Y-m-d')) {
      $pdo->prepare("
        UPDATE bill_reminders
        SET last_status = 'due_today_skip_created',
            last_status_check_at = NOW()
        WHERE bill_id = ?
      ")->execute([$billId]);
      logLine("bill_id={$billId} skip_emissao_enviada_hoje");
      $issues[] = "bill_id={$billId} emissao_hoje";
      $skipped++;
      continue;
    }

    $vindiCreatedAt = mysqlDateTimeOrNull($bill['created_at'] ?? null);
    if ($vindiCreatedAt !== null && date('Y-m-d', strtotime($vindiCreatedAt)) === date('Y-m-d')) {
      $pdo->prepare("
        UPDATE bill_reminders
        SET last_status = 'due_today_skip_created',
            last_status_check_at = NOW()
        WHERE bill_id = ?
      ")->execute([$billId]);
      logLine("bill_id={$billId} skip_emitida_hoje");
      $issues[] = "bill_id={$billId} emitida_hoje";
      $skipped++;
      continue;
    }

    $customer = $bill['customer'] ?? [];
    if (!is_array($customer)) $customer = [];

    $nome = (string)($r['customer_name'] ?: ($customer['name'] ?? 'Cliente'));
    $phone = (string)($r['phone'] ?: extractPhoneFromBill($bill));
    if (trim($phone) === '' && (int)($r['customer_id'] ?? 0) > 0) {
      $phone = getCustomerPhoneFromVindi((int)$r['customer_id'], $VINDI_API_BASE, $VINDI_API_KEY);
    }
    $url = (string)($r['bill_url'] ?: ($bill['url'] ?? ''));
    $amount = billAmountOrNull($bill);
    if ($amount === null && isset($r['amount']) && $r['amount'] !== '') {
      $amount = (float)$r['amount'];
    }

    if (trim($phone) === '') {
      $pdo->prepare("
        UPDATE bill_reminders
        SET last_status = 'no_phone',
            last_status_check_at = NOW()
        WHERE bill_id = ?
      ")->execute([$billId]);
      logLine("bill_id={$billId} sem_telefone");
      $issues[] = "bill_id={$billId} sem_telefone";
      $skipped++;
      continue;
    }

    $pdo->prepare("
      UPDATE bill_reminders
      SET customer_name = ?,
          phone = ?,
          bill_url = ?,
          amount = COALESCE(?, amount),
          due_at = ?,
          status = 'unpaid',
          last_status = ?,
          last_status_check_at = NOW()
      WHERE bill_id = ?
    ")->execute([
      $nome,
      $phone,
      $url,
      $amount,
      $vindiDueAt,
      $vindiStatus ?: 'unpaid',
      $billId,
    ]);

    $params = [
      ['type' => 'text', 'text' => waClean($nome)],
      ['type' => 'text', 'text' => waClean(formatMoneyBr($amount))],
      ['type' => 'text', 'text' => waClean(formatDueDateBr($vindiDueAt))],
      ['type' => 'text', 'text' => waClean($url)],
    ];

    if ($DRY_RUN) {
      logLine("bill_id={$billId} dry_run");
      $issues[] = "bill_id={$billId} dry_run";
      $skipped++;
      continue;
    }

    $resp = enviarTemplateWhatsApp(
      $META_PHONE_NUMBER_ID,
      $META_ACCESS_TOKEN,
      $phone,
      $TEMPLATE_NAME,
      $TEMPLATE_LANG,
      $params
    );

    $respArr = json_decode($resp['response_raw'] ?? '', true);
    if (!is_array($respArr)) $respArr = ['raw' => (string)($resp['response_raw'] ?? '')];
    $ok = ($resp['http'] >= 200 && $resp['http'] < 300)
      && empty($resp['curl_error'])
      && empty($respArr['error']);

    try {
      chatSaveOutgoingMessage(
        $pdo,
        $phone,
        chatDescribeWhatsAppPayload($resp['request'] ?? []),
        $resp['request'] ?? [],
        $respArr,
        (int)($resp['http'] ?? 0),
        $resp['curl_error'] ?? null,
        'due_today_cron',
        (string)$billId
      );
    } catch (Throwable $e) {
      logLine("bill_id={$billId} chat_log_fail=" . $e->getMessage());
    }

    if ($ok) {
      logReminderAttempt(
        $pdo,
        $billId,
        true,
        (int)$resp['http'],
        'Lembrete vencimento hoje enviado via WhatsApp',
        (string)($resp['response_raw'] ?? '')
      );

      $pdo->prepare("
        UPDATE bill_reminders
        SET due_today_sent_at = NOW(),
            due_today_sent_count = COALESCE(due_today_sent_count, 0) + 1,
            last_reminder_at = NOW(),
            last_status = 'due_today_sent',
            last_status_check_at = NOW()
        WHERE bill_id = ?
      ")->execute([$billId]);

      logLine("bill_id={$billId} enviado_ok phone={$phone}");
      $sent++;
    } else {
      $code = (string)($respArr['error']['code'] ?? '');
      $sub = (string)($respArr['error']['error_subcode'] ?? '');
      $ls = "due_today_fail";
      if ($code !== '') $ls = "m{$code}";
      if ($sub !== '') $ls = substr($ls . "_s{$sub}", 0, 30);

      $failMessage = "Falha ao enviar vencimento_hoje";
      if (!empty($respArr['error']['message'])) {
        $failMessage .= ': ' . (string)$respArr['error']['message'];
      }
      logReminderAttempt(
        $pdo,
        $billId,
        false,
        (int)$resp['http'],
        $failMessage,
        (string)($resp['response_raw'] ?? '')
      );

      $pdo->prepare("
        UPDATE bill_reminders
        SET last_status = ?,
            last_status_check_at = NOW()
        WHERE bill_id = ?
      ")->execute([$ls, $billId]);

      logLine("bill_id={$billId} falha http={$resp['http']} curl=" . ($resp['curl_error'] ?? ''));
      $issues[] = "bill_id={$billId} {$ls}";
      $skipped++;
    }
  }

  logLine("CRON end sent={$sent} skipped={$skipped}");
  header('Content-Type: text/plain; charset=utf-8');
  $suffix = $issues ? ' | ' . implode(' | ', array_slice($issues, 0, 5)) : '';
  echo "OK sent={$sent} skipped={$skipped}{$suffix}\n";
} finally {
  $pdo->query("SELECT RELEASE_LOCK('due_today_reminders_lock')");
}
