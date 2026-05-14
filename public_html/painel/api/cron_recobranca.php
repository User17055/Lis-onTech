<?php
date_default_timezone_set('America/Sao_Paulo');

$LOG_DIR = __DIR__ . '/../../storage/logs';
if (!is_dir($LOG_DIR)) {
  @mkdir($LOG_DIR, 0755, true);
}
$LOG_FILE = $LOG_DIR . '/recobranca.log';
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
  throw new RuntimeException("Raiz não encontrada para: " . implode(', ', $files));
}

if (isset($_GET['ping']) && $_GET['ping'] === '1') {
  header('Content-Type: text/plain; charset=utf-8');
  echo "OK cron_recobranca.php\n";
  exit;
}

$ROOT = findRootWithFiles(['config.php', 'db.php']);
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/auth.php';

$CRON_TOKENS = array_values(array_unique(array_filter([
  cfg($cfg, 'CRON_TOKEN', ''),
  cfg($cfg, 'RECOBRANCA_CRON_TOKEN', ''),
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

if (isset($_GET['debug_token']) && $_GET['debug_token'] === '1') {
  $tokenMatch = false;
  foreach ($CRON_TOKENS as $tokenCandidate) {
    if ($REQ_TOKEN !== '' && hash_equals($tokenCandidate, $REQ_TOKEN)) {
      $tokenMatch = true;
      break;
    }
  }

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'config_env_found' => !empty($GLOBALS['LISON_CONFIG_ENV_LABEL']),
    'config_env_loaded_from' => $GLOBALS['LISON_CONFIG_ENV_LABEL'] ?? null,
    'config_env_checked' => $GLOBALS['LISON_CONFIG_ENV_CHECKS'] ?? [],
    'cron_token_configured' => count($CRON_TOKENS) > 0,
    'cron_token_lengths' => array_map('strlen', $CRON_TOKENS),
    'request_token_length' => strlen($REQ_TOKEN),
    'token_match' => $tokenMatch,
  ], JSON_UNESCAPED_UNICODE);
  exit;
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

require_once $ROOT . '/db.php'; // precisa criar $pdo (PDO)
require_once $ROOT . '/includes/chat_db.php';

$META_PHONE_NUMBER_ID = cfg($cfg, 'META_PHONE_NUMBER_ID');
$META_ACCESS_TOKEN    = cfg($cfg, 'META_ACCESS_TOKEN');

$VINDI_API_KEY  = cfg($cfg, 'VINDI_API_KEY');
$VINDI_API_BASE = cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1');

$TEMPLATE_NAME = cfg($cfg, 'META_TEMPLATE_RECOBRANCA');
if ($TEMPLATE_NAME === '') {
  $TEMPLATE_NAME = cfg($cfg, 'META_TEMPLATE_REMINDER_NAME');
}
if ($TEMPLATE_NAME === '') {
  $TEMPLATE_NAME = cfg($cfg, 'META_TEMPLATE_NAME');
}
if ($TEMPLATE_NAME === '') {
  $TEMPLATE_NAME = 'fatura22';
}
$TEMPLATE_LANG = cfg($cfg, 'META_TEMPLATE_LANG', 'pt_BR');

$DRY_RUN = (isset($_GET['dry_run']) && $_GET['dry_run'] === '1');
$LIMIT   = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;
$ONLY_BILL_ID = isset($_GET['bill_id']) ? max(0, (int)$_GET['bill_id']) : 0;
$BACKFILL = (isset($_GET['backfill']) && $_GET['backfill'] === '1');
$BACKFILL_PAGE = isset($_GET['backfill_page']) ? max(1, (int)$_GET['backfill_page']) : 1;
$BACKFILL_PAGES = isset($_GET['backfill_pages']) ? max(1, min(20, (int)$_GET['backfill_pages'])) : 1;
$BACKFILL_LIMIT = isset($_GET['backfill_limit']) ? max(1, min(50, (int)$_GET['backfill_limit'])) : min(50, $LIMIT);
$BACKFILL_BEFORE_RAW = trim((string)($_GET['backfill_before'] ?? ''));
$BACKFILL_AFTER_RAW = trim((string)($_GET['backfill_after'] ?? ''));
$BACKFILL_FORCE_READY = (isset($_GET['backfill_force_ready']) && $_GET['backfill_force_ready'] === '1');

$INTERVAL_DAYS = (int) cfg($cfg, 'RECOBRANCA_INTERVAL_DAYS', 7);
$FIRST_DELAY_DAYS = max(7, (int) cfg($cfg, 'RECOBRANCA_FIRST_DELAY_DAYS', '7'));
$MAX_OVERDUE   = (int) cfg($cfg, 'RECOBRANCA_MAX_OVERDUE', 12);

if ($META_PHONE_NUMBER_ID === '' || $META_ACCESS_TOKEN === '' || $VINDI_API_KEY === '' || $TEMPLATE_NAME === '') {
  logLine("ERRO config incompleta META/VINDI/TEMPLATE.");
  header('Content-Type: text/plain; charset=utf-8');
  echo "ERRO config incompleta META/VINDI/TEMPLATE.\n";
  http_response_code(200);
  exit;
}

function waClean(string $s): string {
  $s = preg_replace("/[\r\n\t]+/", " ", $s);
  $s = preg_replace("/ {2,}/", " ", $s);
  return trim($s);
}

function curlGetJson(string $url, string $apiKey): ?array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Basic ' . base64_encode($apiKey . ':'),
  ]);
  $res  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false || $http < 200 || $http >= 300) return null;
  $json = json_decode($res, true);
  return is_array($json) ? $json : null;
}

function vindiListBillsForBackfill(string $base, string $apiKey, string $query, int $page, int $perPage): array {
  $url = rtrim($base, '/') . "/bills?per_page={$perPage}&page={$page}&query=" . rawurlencode($query);
  $resp = curlGetJson($url, $apiKey);
  if (!$resp) {
    return ['ok' => false, 'error' => 'vindi_list_error'];
  }

  $bills = $resp['bills'] ?? [];
  if (!is_array($bills)) $bills = [];

  return ['ok' => true, 'bills' => $bills];
}

function vindiGetBill(int $billId, string $base, string $apiKey): ?array {
  $url = rtrim($base, '/') . "/bills/" . $billId;
  $resp = curlGetJson($url, $apiKey);
  if (!$resp) return null;
  return $resp['bill'] ?? $resp;
}

function extractPhoneFromBill(array $bill): ?string {
  $c = $bill['customer'] ?? [];
  $candidates = [
    $c['phone_number'] ?? null,
    $c['mobile'] ?? null,
    $c['phone'] ?? null,
  ];
  if (!empty($c['phones']) && is_array($c['phones'])) {
    $first = $c['phones'][0] ?? null;
    if (is_array($first)) {
      $candidates[] = $first['number'] ?? null;
      $candidates[] = $first['phone_number'] ?? null;
    } elseif (is_string($first)) {
      $candidates[] = $first;
    }
  }
  foreach ($candidates as $cand) {
    if (is_string($cand) && trim($cand) !== '') return $cand;
  }
  return null;
}

function getCustomerPhoneFromVindi(int $customerId, string $base, string $apiKey): ?string {
  $url = rtrim($base, '/') . "/customers/" . $customerId;
  $resp = curlGetJson($url, $apiKey);
  if (!$resp) return null;
  $customer = $resp['customer'] ?? $resp;
  if (!is_array($customer)) return null;

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

  foreach ($candidates as $cand) {
    if (is_string($cand) && trim($cand) !== '') return $cand;
  }
  return null;
}

function buildBillItemsText(array $bill): string {
  $itens = $bill['bill_items'] ?? [];
  if (!is_array($itens) || empty($itens)) return "Sem itens informados";
  $linhas = [];
  foreach ($itens as $item) {
    if (!is_array($item)) continue;
    $nomeItem = $item['product']['name'] ?? $item['description'] ?? 'Item';
    $qtd = $item['quantity'] ?? null;
    if (!empty($qtd) && (int)$qtd > 1) $linhas[] = "{$nomeItem}, " . (int)$qtd . " un";
    else $linhas[] = "{$nomeItem}";
  }
  return waClean(implode(" | ", $linhas));
}

function mysqlDateTimeOrNull($value): ?string {
  if ($value === null || trim((string)$value) === '') return null;
  $ts = strtotime((string)$value);
  return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function inputDateTimeOrNull($value, bool $endOfDay = true): ?string {
  $raw = trim((string)($value ?? ''));
  if ($raw === '') return null;

  if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $raw, $m)) {
    $day = (int)$m[1];
    $month = (int)$m[2];
    $year = (int)$m[3];
    $hour = isset($m[4]) && $m[4] !== '' ? (int)$m[4] : ($endOfDay ? 23 : 0);
    $minute = isset($m[5]) && $m[5] !== '' ? (int)$m[5] : ($endOfDay ? 59 : 0);
    $second = isset($m[6]) && $m[6] !== '' ? (int)$m[6] : ($endOfDay ? 59 : 0);
    if (checkdate($month, $day, $year)) {
      return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
  }

  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
    $raw .= $endOfDay ? ' 23:59:59' : ' 00:00:00';
  }

  return mysqlDateTimeOrNull($raw);
}

function billAmountOrNull(array $bill): ?float {
  foreach (['amount', 'total', 'price'] as $key) {
    if (isset($bill[$key]) && is_numeric($bill[$key])) return (float)$bill[$key];
  }
  return null;
}

function upsertReminderFromBill(PDO $pdo, array $bill, string $base, string $apiKey, bool $forceReady = false, int $maxOverdue = 12): bool {
  $billId = (int)($bill['id'] ?? 0);
  if ($billId <= 0) return false;

  $customer = $bill['customer'] ?? [];
  if (!is_array($customer)) $customer = [];

  $customerId = (int)($customer['id'] ?? ($bill['customer_id'] ?? 0));
  $vindiStatus = strtolower((string)($bill['status'] ?? ''));
  $localStatus = 'unpaid';
  $active = 1;

  if ($vindiStatus === 'paid') {
    $localStatus = 'paid';
    $active = 0;
  } elseif (in_array($vindiStatus, ['canceled', 'cancelled'], true)) {
    $localStatus = 'canceled';
    $active = 0;
  }

  $phone = extractPhoneFromBill($bill);
  if ((!$phone || trim($phone) === '') && $customerId > 0) {
    $phone = getCustomerPhoneFromVindi($customerId, $base, $apiKey);
  }

  $nextReminderAt = $forceReady && $active === 1 ? date('Y-m-d H:i:s') : null;
  $itemsText = (!empty($bill['bill_items']) && is_array($bill['bill_items']))
    ? buildBillItemsText($bill)
    : '';

  $st = $pdo->prepare("
    INSERT INTO bill_reminders (
      bill_id, customer_id, customer_name, phone, bill_url, items_text,
      amount, due_at, active, blocked, status, next_reminder_at,
      last_status, last_status_check_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
      customer_id = VALUES(customer_id),
      customer_name = VALUES(customer_name),
      phone = VALUES(phone),
      bill_url = VALUES(bill_url),
      items_text = VALUES(items_text),
      amount = VALUES(amount),
      due_at = VALUES(due_at),
      active = CASE
        WHEN VALUES(status) IN ('paid', 'canceled') THEN 0
        WHEN VALUES(next_reminder_at) IS NOT NULL THEN 1
        WHEN blocked = 1 THEN active
        ELSE 1
      END,
      status = CASE
        WHEN VALUES(status) IN ('paid', 'canceled') THEN VALUES(status)
        WHEN VALUES(next_reminder_at) IS NOT NULL THEN 'unpaid'
        WHEN blocked = 1 THEN status
        ELSE 'unpaid'
      END,
      blocked = CASE
        WHEN VALUES(next_reminder_at) IS NOT NULL AND VALUES(status) NOT IN ('paid', 'canceled') THEN 0
        ELSE blocked
      END,
      overdue_sent_count = CASE
        WHEN VALUES(next_reminder_at) IS NOT NULL
          AND VALUES(status) NOT IN ('paid', 'canceled')
          AND COALESCE(overdue_sent_count, 0) >= ?
        THEN ?
        ELSE overdue_sent_count
      END,
      next_reminder_at = CASE
        WHEN VALUES(status) IN ('paid', 'canceled') THEN NULL
        WHEN VALUES(next_reminder_at) IS NOT NULL THEN VALUES(next_reminder_at)
        WHEN blocked = 1 THEN next_reminder_at
        ELSE next_reminder_at
      END,
      last_status = VALUES(last_status),
      last_status_check_at = NOW()
  ");
  $st->execute([
    $billId,
    $customerId > 0 ? $customerId : null,
    (string)($customer['name'] ?? 'Cliente'),
    (string)($phone ?? ''),
    (string)($bill['url'] ?? ''),
    $itemsText,
    billAmountOrNull($bill),
    mysqlDateTimeOrNull($bill['due_at'] ?? null),
    $active,
    $localStatus,
    $nextReminderAt,
    $vindiStatus ?: $localStatus,
    max(1, $maxOverdue),
    max(0, $maxOverdue - 1),
  ]);

  return true;
}

function ensureManualReminderRow(PDO $pdo, int $billId, string $base, string $apiKey): ?string {
  $st = $pdo->prepare("SELECT bill_id FROM bill_reminders WHERE bill_id = ? LIMIT 1");
  $st->execute([$billId]);
  if ($st->fetch(PDO::FETCH_ASSOC)) {
    $pdo->prepare("
      UPDATE bill_reminders
      SET active = 1,
          blocked = 0,
          status = 'unpaid',
          next_reminder_at = NOW()
      WHERE bill_id = ?
    ")->execute([$billId]);
    return null;
  }

  $bill = vindiGetBill($billId, $base, $apiKey);
  if (!$bill) return "nao consegui buscar esta bill na Vindi";

  $customer = $bill['customer'] ?? [];
  if (!is_array($customer)) $customer = [];
  $customerId = (int)($customer['id'] ?? ($bill['customer_id'] ?? 0));
  $phone = extractPhoneFromBill($bill);
  if ((!$phone || trim($phone) === '') && $customerId > 0) {
    $phone = getCustomerPhoneFromVindi($customerId, $base, $apiKey);
  }

  $st = $pdo->prepare("
    INSERT INTO bill_reminders (
      bill_id, customer_id, customer_name, phone, bill_url, items_text,
      amount, due_at, active, blocked, status, next_reminder_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 'unpaid', NOW())
    ON DUPLICATE KEY UPDATE
      customer_id = VALUES(customer_id),
      customer_name = VALUES(customer_name),
      phone = VALUES(phone),
      bill_url = VALUES(bill_url),
      items_text = VALUES(items_text),
      amount = VALUES(amount),
      due_at = VALUES(due_at),
      active = 1,
      blocked = 0,
      status = 'unpaid',
      next_reminder_at = NOW()
  ");
  $st->execute([
    $billId,
    $customerId > 0 ? $customerId : null,
    (string)($customer['name'] ?? 'Cliente'),
    (string)($phone ?? ''),
    (string)($bill['url'] ?? ''),
    buildBillItemsText($bill),
    billAmountOrNull($bill),
    mysqlDateTimeOrNull($bill['due_at'] ?? null),
  ]);

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
  if (strlen($destinatario) > 0 && strlen($destinatario) <= 11) $destinatario = "55" . $destinatario;

  foreach ($paramsBody as &$p) {
    if (is_array($p) && ($p['type'] ?? '') === 'text' && isset($p['text'])) {
      $p['text'] = waClean((string)$p['text']);
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

  $res  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  return [
    'http' => (int)$http,
    'curl_error' => $err ?: null,
    'request' => $payload,
    'response_raw' => $res ?: ''
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
    $message = substr($message, 0, 255);
    $st = $pdo->prepare("
      INSERT INTO reminder_logs (bill_id, ok, http_code, message, response_raw)
      VALUES (?, ?, ?, ?, ?)
    ");
    $st->execute([$billId, $ok ? 1 : 0, $httpCode, $message, $raw]);
  } catch (Throwable $e) {
    logLine("bill_id={$billId} reminder_logs_fail=" . $e->getMessage());
  }
}

/**
 * Seleciona bills vencidas e liberadas pra enviar
 */
$backfillSeeded = 0;
$backfillSkipped = 0;
$backfillIssues = [];
$backfillBeforeUsed = null;
$backfillQueryUsed = '';
$backfillPageCounts = [];

if ($BACKFILL && $ONLY_BILL_ID <= 0) {
  $backfillBefore = inputDateTimeOrNull($BACKFILL_BEFORE_RAW);
  if ($backfillBefore === null) {
    $backfillBefore = date('Y-m-d H:i:s', strtotime("-{$FIRST_DELAY_DAYS} days"));
  }
  $backfillBeforeUsed = $backfillBefore;
  $backfillBeforeTs = strtotime($backfillBefore) ?: time();

  $backfillQuery = 'status=pending AND due_at<="' . $backfillBefore . '"';
  $backfillQueryUsed = $backfillQuery;
  for ($pageOffset = 0; $pageOffset < $BACKFILL_PAGES; $pageOffset++) {
    $pageToFetch = $BACKFILL_PAGE + $pageOffset;
    $backfillResp = vindiListBillsForBackfill(
      $VINDI_API_BASE,
      $VINDI_API_KEY,
      $backfillQuery,
      $pageToFetch,
      $BACKFILL_LIMIT
    );

    if (!($backfillResp['ok'] ?? false)) {
      $msg = (string)($backfillResp['error'] ?? 'erro_backfill_vindi');
      $backfillIssues[] = $msg;
      logLine("BACKFILL fail query={$backfillQuery} page={$pageToFetch} error={$msg}");
      break;
    }

    $bills = $backfillResp['bills'] ?? [];
    $backfillPageCounts[] = "p{$pageToFetch}=" . count($bills);
    if (empty($bills)) {
      logLine("BACKFILL empty page={$pageToFetch} query={$backfillQuery}");
      break;
    }

    foreach ($bills as $bill) {
      if (!is_array($bill)) {
        $backfillSkipped++;
        continue;
      }

      try {
        $ok = upsertReminderFromBill($pdo, $bill, $VINDI_API_BASE, $VINDI_API_KEY, $BACKFILL_FORCE_READY, $MAX_OVERDUE);
        if ($ok) $backfillSeeded++;
        else $backfillSkipped++;
      } catch (Throwable $e) {
        $billId = (int)($bill['id'] ?? 0);
        $backfillSkipped++;
        $backfillIssues[] = "bill_id={$billId} " . $e->getMessage();
        logLine("BACKFILL bill_id={$billId} fail=" . $e->getMessage());
      }
    }

    logLine(
      "BACKFILL page={$pageToFetch} limit={$BACKFILL_LIMIT} before={$backfillBefore} " .
      "seeded={$backfillSeeded} skipped={$backfillSkipped} force_ready=" . ($BACKFILL_FORCE_READY ? '1' : '0')
    );
  }

  if ($backfillSeeded === 0 && empty($backfillIssues)) {
    $fallbackQuery = 'status=pending';
    $backfillQueryUsed .= ' | fallback=' . $fallbackQuery;

    for ($pageOffset = 0; $pageOffset < $BACKFILL_PAGES; $pageOffset++) {
      $pageToFetch = $BACKFILL_PAGE + $pageOffset;
      $backfillResp = vindiListBillsForBackfill(
        $VINDI_API_BASE,
        $VINDI_API_KEY,
        $fallbackQuery,
        $pageToFetch,
        $BACKFILL_LIMIT
      );

      if (!($backfillResp['ok'] ?? false)) {
        $msg = (string)($backfillResp['error'] ?? 'erro_backfill_fallback_vindi');
        $backfillIssues[] = $msg;
        logLine("BACKFILL fallback fail page={$pageToFetch} error={$msg}");
        break;
      }

      $bills = $backfillResp['bills'] ?? [];
      $backfillPageCounts[] = "fb{$pageToFetch}=" . count($bills);
      if (empty($bills)) {
        logLine("BACKFILL fallback empty page={$pageToFetch}");
        break;
      }

      foreach ($bills as $bill) {
        if (!is_array($bill)) {
          $backfillSkipped++;
          continue;
        }

        $dueAt = mysqlDateTimeOrNull($bill['due_at'] ?? null);
        $dueTs = $dueAt ? strtotime($dueAt) : null;
        if (!$dueTs || $dueTs > $backfillBeforeTs) {
          continue;
        }

        try {
          $ok = upsertReminderFromBill($pdo, $bill, $VINDI_API_BASE, $VINDI_API_KEY, $BACKFILL_FORCE_READY, $MAX_OVERDUE);
          if ($ok) $backfillSeeded++;
          else $backfillSkipped++;
        } catch (Throwable $e) {
          $billId = (int)($bill['id'] ?? 0);
          $backfillSkipped++;
          $backfillIssues[] = "bill_id={$billId} " . $e->getMessage();
          logLine("BACKFILL fallback bill_id={$billId} fail=" . $e->getMessage());
        }
      }

      logLine(
        "BACKFILL fallback page={$pageToFetch} limit={$BACKFILL_LIMIT} before={$backfillBefore} " .
        "seeded={$backfillSeeded} skipped={$backfillSkipped} force_ready=" . ($BACKFILL_FORCE_READY ? '1' : '0')
      );
    }
  }
}

if ($ONLY_BILL_ID > 0) {
  $manualSeedError = ensureManualReminderRow($pdo, $ONLY_BILL_ID, $VINDI_API_BASE, $VINDI_API_KEY);
  if ($manualSeedError !== null) {
    logLine("bill_id={$ONLY_BILL_ID} manual_seed_fail={$manualSeedError}");
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERRO bill_id={$ONLY_BILL_ID} {$manualSeedError}.\n";
    exit;
  }
}

$whereSql = "
  active = 1
  AND blocked = 0
  AND (status IS NULL OR status = '' OR status = 'unpaid')
  AND (due_at IS NULL OR due_at <= DATE_SUB(NOW(), INTERVAL {$FIRST_DELAY_DAYS} DAY))
  AND (next_reminder_at IS NULL OR next_reminder_at <= NOW())
  AND (overdue_sent_count IS NULL OR overdue_sent_count < :max_overdue)
";

if ($ONLY_BILL_ID > 0) {
  $whereSql = "
    bill_id = :only_bill_id
  ";
}

$sql = "
SELECT
  bill_id, customer_id, customer_name, phone, bill_url, items_text, amount, due_at,
  active, blocked, status,
  reminder_count, overdue_sent_count, reminder_attempts,
  last_status, last_status_check_at,
  last_overdue_sent_at, next_reminder_at
FROM bill_reminders
WHERE {$whereSql}
ORDER BY COALESCE(next_reminder_at, due_at) ASC
LIMIT {$LIMIT}
";
$st = $pdo->prepare($sql);
$params = [];
if ($ONLY_BILL_ID > 0) {
  $params[':only_bill_id'] = $ONLY_BILL_ID;
} else {
  $params[':max_overdue'] = $MAX_OVERDUE;
}
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

logLine("CRON start candidatos=" . count($rows) . " dry_run=" . ($DRY_RUN ? '1' : '0'));

$sent = 0;
$skipped = 0;
$issues = [];

if ($ONLY_BILL_ID > 0 && count($rows) === 0) {
  logLine("bill_id={$ONLY_BILL_ID} nao_elegivel");
  header('Content-Type: text/plain; charset=utf-8');
  echo "ERRO bill_id={$ONLY_BILL_ID} nao encontrei esta bill no controle local depois de preparar o envio.\n";
  exit;
}

foreach ($rows as $r) {
  $billId = (int)$r['bill_id'];

  // Claim simples: evita 2 cron pegarem a mesma fatura
  $claim = $pdo->prepare("
    UPDATE bill_reminders
    SET next_reminder_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
    WHERE bill_id = ?
      " . ($ONLY_BILL_ID > 0 ? "" : "
      AND active = 1
      AND blocked = 0
      AND (status IS NULL OR status = '' OR status = 'unpaid')
      AND (due_at IS NULL OR due_at <= DATE_SUB(NOW(), INTERVAL {$FIRST_DELAY_DAYS} DAY))
      AND (next_reminder_at IS NULL OR next_reminder_at <= NOW())
      ") . "
  ");
  $claim->execute([$billId]);
  if ($claim->rowCount() !== 1) { $skipped++; continue; }

  // Checa Vindi
  $bill = vindiGetBill($billId, $VINDI_API_BASE, $VINDI_API_KEY);
  if (!$bill) {
    $pdo->prepare("
      UPDATE bill_reminders
      SET reminder_attempts = COALESCE(reminder_attempts,0) + 1,
          next_reminder_at = DATE_ADD(NOW(), INTERVAL 1 DAY),
          last_status = 'vindi_error',
          last_status_check_at = NOW()
      WHERE bill_id = ?
    ")->execute([$billId]);
    logLine("bill_id={$billId} erro_vindi");
    $issues[] = "bill_id={$billId} vindi_error";
    $skipped++;
    continue;
  }

  $vindiStatus = strtolower((string)($bill['status'] ?? ''));
  $vindiDueStr = (string)($bill['due_at'] ?? '');
  $dueTs = $vindiDueStr ? strtotime($vindiDueStr) : null;

  // Atualiza status local se já pagou/cancelou
  if (in_array($vindiStatus, ['paid'], true)) {
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

  if (in_array($vindiStatus, ['canceled','cancelled'], true)) {
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

  // Garante que está vencida
  $minDueTs = strtotime("-{$FIRST_DELAY_DAYS} days");

  if (!$dueTs || $dueTs > $minDueTs) {
    $eligibleAt = $dueTs ? date('d/m/Y H:i', $dueTs + ($FIRST_DELAY_DAYS * 86400)) : 'sem vencimento';
    $nextAt = $dueTs
      ? date('Y-m-d H:i:s', max($dueTs + ($FIRST_DELAY_DAYS * 86400), time() + 86400))
      : date('Y-m-d H:i:s', time() + 86400);
    $pdo->prepare("
      UPDATE bill_reminders
      SET due_at = COALESCE(?, due_at),
          last_status = ?,
          last_status_check_at = NOW(),
          next_reminder_at = ?
      WHERE bill_id = ?
    ")->execute([
      $dueTs ? date('Y-m-d H:i:s', $dueTs) : null,
      $vindiStatus ?: 'unpaid',
      $nextAt,
      $billId
    ]);
    logLine("bill_id={$billId} ainda_nao_completou_{$FIRST_DELAY_DAYS}_dias");
    $issues[] = "bill_id={$billId} so_pode_recobrar_em={$eligibleAt}";
    $skipped++;
    continue;
  }

  // Completa dados faltantes usando Vindi
  $nome = $r['customer_name'] ?: (string)(($bill['customer']['name'] ?? 'Cliente'));
  $url  = $r['bill_url'] ?: (string)($bill['url'] ?? '');
  $itens= $r['items_text'] ?: buildBillItemsText($bill);

  $phone = $r['phone'] ?: extractPhoneFromBill($bill);
  $customerId = (int)($r['customer_id'] ?? 0);
  if ((!$phone || trim($phone)==='') && $customerId > 0) {
    $phone = getCustomerPhoneFromVindi($customerId, $VINDI_API_BASE, $VINDI_API_KEY);
  }

  // Se ainda não tem telefone, pausa por 1 dia
  if (!$phone || trim($phone)==='') {
    $pdo->prepare("
      UPDATE bill_reminders
      SET reminder_attempts = COALESCE(reminder_attempts,0) + 1,
          next_reminder_at = DATE_ADD(NOW(), INTERVAL 1 DAY),
          last_status = 'no_phone',
          last_status_check_at = NOW()
      WHERE bill_id = ?
    ")->execute([$billId]);
    logLine("bill_id={$billId} sem_telefone");
    $issues[] = "bill_id={$billId} sem_telefone";
    $skipped++;
    continue;
  }

  // Atualiza cache local
  $pdo->prepare("
    UPDATE bill_reminders
    SET customer_name = ?,
        phone = ?,
        bill_url = ?,
        items_text = ?,
        due_at = ?,
        last_status = ?,
        status = 'unpaid',
        last_status_check_at = NOW()
    WHERE bill_id = ?
  ")->execute([
    $nome,
    $phone,
    $url,
    $itens,
    date('Y-m-d H:i:s', $dueTs),
    $vindiStatus ?: 'unpaid',
    $billId
  ]);

  $params = [
    ["type" => "text", "text" => waClean($nome)],
    ["type" => "text", "text" => waClean($url)],
    ["type" => "text", "text" => waClean($itens)],
  ];

  if ($DRY_RUN) {
    $pdo->prepare("
      UPDATE bill_reminders
      SET next_reminder_at = DATE_ADD(NOW(), INTERVAL {$INTERVAL_DAYS} DAY),
          last_status = 'dry_run',
          last_status_check_at = NOW()
      WHERE bill_id = ?
    ")->execute([$billId]);
    logLine("bill_id={$billId} dry_run");
    $issues[] = "bill_id={$billId} dry_run";
    $skipped++;
    continue;
  }

  // Envia WhatsApp
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
      'recobranca_cron',
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
      'Recobranca enviada via WhatsApp',
      (string)($resp['response_raw'] ?? '')
    );

    $pdo->prepare("
      UPDATE bill_reminders
      SET last_overdue_sent_at = NOW(),
          overdue_sent_count = COALESCE(overdue_sent_count,0) + 1,
          last_reminder_at = NOW(),
          last_reminder_sent_at = NOW(),
          reminder_count = COALESCE(reminder_count,0) + 1,
          reminder_attempts = 0,
          next_reminder_at = DATE_ADD(NOW(), INTERVAL {$INTERVAL_DAYS} DAY),
          last_status = 'meta_ok',
          last_status_check_at = NOW()
      WHERE bill_id = ?
    ")->execute([$billId]);

    logLine("bill_id={$billId} enviado_ok phone={$phone}");
    $sent++;
  } else {
    $code = (string)($respArr['error']['code'] ?? '');
    $sub  = (string)($respArr['error']['error_subcode'] ?? '');
    $ls   = "meta_fail";
    if ($code !== '') $ls = "m{$code}";
    if ($sub  !== '') $ls = substr($ls . "_s{$sub}", 0, 30);

    $failMessage = "Falha ao enviar recobranca";
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
      SET reminder_attempts = COALESCE(reminder_attempts,0) + 1,
          last_reminder_at = NOW(),
          next_reminder_at = DATE_ADD(NOW(), INTERVAL 1 DAY),
          last_status = ?,
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
$allIssues = array_merge($backfillIssues, $issues);
$suffix = $allIssues ? ' | ' . implode(' | ', array_slice($allIssues, 0, 5)) : '';
$backfillText = $BACKFILL
  ? " backfill_seeded={$backfillSeeded} backfill_skipped={$backfillSkipped} backfill_before={$backfillBeforeUsed} backfill_pages=" . implode(',', $backfillPageCounts) . " backfill_query={$backfillQueryUsed}"
  : '';
echo "OK sent={$sent} skipped={$skipped}{$backfillText}{$suffix}\n";
