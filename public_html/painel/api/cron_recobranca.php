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

$INTERVAL_DAYS = (int) cfg($cfg, 'RECOBRANCA_INTERVAL_DAYS', 7);
$MAX_OVERDUE   = (int) cfg($cfg, 'RECOBRANCA_MAX_OVERDUE', 12);

if ($META_PHONE_NUMBER_ID === '' || $META_ACCESS_TOKEN === '' || $VINDI_API_KEY === '') {
  logLine("ERRO config incompleta META/VINDI.");
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

/**
 * Seleciona bills vencidas e liberadas pra enviar
 */
$sql = "
SELECT
  bill_id, customer_id, customer_name, phone, bill_url, items_text, amount, due_at,
  active, blocked, status,
  reminder_count, overdue_sent_count, reminder_attempts,
  last_status, last_status_check_at,
  last_overdue_sent_at, next_reminder_at
FROM bill_reminders
WHERE active = 1
  AND blocked = 0
  AND status = 'unpaid'
  AND due_at IS NOT NULL
  AND due_at < NOW()
  AND (next_reminder_at IS NULL OR next_reminder_at <= NOW())
  AND (overdue_sent_count IS NULL OR overdue_sent_count < :max_overdue)
ORDER BY COALESCE(next_reminder_at, due_at) ASC
LIMIT {$LIMIT}
";
$st = $pdo->prepare($sql);
$st->execute([':max_overdue' => $MAX_OVERDUE]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

logLine("CRON start candidatos=" . count($rows) . " dry_run=" . ($DRY_RUN ? '1' : '0'));

$sent = 0;
$skipped = 0;

foreach ($rows as $r) {
  $billId = (int)$r['bill_id'];

  // Claim simples: evita 2 cron pegarem a mesma fatura
  $claim = $pdo->prepare("
    UPDATE bill_reminders
    SET next_reminder_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
    WHERE bill_id = ?
      AND active = 1
      AND blocked = 0
      AND status = 'unpaid'
      AND (next_reminder_at IS NULL OR next_reminder_at <= NOW())
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
    $skipped++;
    continue;
  }

  // Garante que está vencida
  if (!$dueTs || $dueTs > time()) {
    $pdo->prepare("
      UPDATE bill_reminders
      SET due_at = COALESCE(?, due_at),
          last_status = ?,
          last_status_check_at = NOW(),
          next_reminder_at = DATE_ADD(NOW(), INTERVAL 1 DAY)
      WHERE bill_id = ?
    ")->execute([
      $dueTs ? date('Y-m-d H:i:s', $dueTs) : null,
      $vindiStatus ?: 'unpaid',
      $billId
    ]);
    logLine("bill_id={$billId} nao_vencida_na_vindi");
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
    $skipped++;
    continue;
  }

  // Atualiza cache local
  $pdo->prepare("
    UPDATE bill_reminders
    SET customer_name = COALESCE(customer_name, ?),
        phone = COALESCE(phone, ?),
        bill_url = COALESCE(bill_url, ?),
        items_text = COALESCE(items_text, ?),
        due_at = COALESCE(due_at, ?),
        last_status = ?,
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
    $skipped++;
  }
}

logLine("CRON end sent={$sent} skipped={$skipped}");
header('Content-Type: text/plain; charset=utf-8');
echo "OK sent={$sent} skipped={$skipped}\n";
