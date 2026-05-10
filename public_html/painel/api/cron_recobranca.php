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
require_once $ROOT . '/db.php'; // precisa criar $pdo (PDO)

$META_PHONE_NUMBER_ID = cfg($cfg, 'META_PHONE_NUMBER_ID');
$META_ACCESS_TOKEN    = cfg($cfg, 'META_ACCESS_TOKEN');

$VINDI_API_KEY  = cfg($cfg, 'VINDI_API_KEY');
$VINDI_API_BASE = cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1');

$TEMPLATE_NAME = cfg($cfg, 'META_TEMPLATE_RECOBRANCA', cfg($cfg, 'META_TEMPLATE_NAME', 'fatura22'));
$TEMPLATE_LANG = cfg($cfg, 'META_TEMPLATE_LANG', 'pt_BR');

$DRY_RUN = (isset($_GET['dry_run']) && $_GET['dry_run'] === '1');
$LIMIT   = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;
$ONLY_BILL_ID = isset($_GET['bill_id']) ? max(0, (int)$_GET['bill_id']) : 0;

$INTERVAL_DAYS = (int) cfg($cfg, 'RECOBRANCA_INTERVAL_DAYS', 7);
$FIRST_DELAY_DAYS = max(1, (int) cfg($cfg, 'RECOBRANCA_FIRST_DELAY_DAYS', '7'));
$MAX_OVERDUE   = (int) cfg($cfg, 'RECOBRANCA_MAX_OVERDUE', 12);

if ($META_PHONE_NUMBER_ID === '' || $META_ACCESS_TOKEN === '' || $VINDI_API_KEY === '') {
  logLine("ERRO config incompleta META/VINDI.");
  header('Content-Type: text/plain; charset=utf-8');
  echo "ERRO config incompleta META/VINDI.\n";
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

function billAmountOrNull(array $bill): ?float {
  foreach (['amount', 'total', 'price'] as $key) {
    if (isset($bill[$key]) && is_numeric($bill[$key])) return (float)$bill[$key];
  }
  return null;
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
$params = [':max_overdue' => $MAX_OVERDUE];
if ($ONLY_BILL_ID > 0) $params[':only_bill_id'] = $ONLY_BILL_ID;
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
  $ok = ($resp['http'] >= 200 && $resp['http'] < 300)
    && empty($resp['curl_error'])
    && empty($respArr['error']);

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
$suffix = $issues ? ' | ' . implode(' | ', array_slice($issues, 0, 5)) : '';
echo "OK sent={$sent} skipped={$skipped}{$suffix}\n";
