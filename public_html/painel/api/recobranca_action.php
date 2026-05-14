<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

$LOG_DIR = __DIR__ . '/../../storage/logs';
if (!is_dir($LOG_DIR)) {
  @mkdir($LOG_DIR, 0755, true);
}
$LOG_FILE = $LOG_DIR . '/recobranca_action.log';
ini_set('log_errors', '1');
ini_set('error_log', $LOG_FILE);

function actionLogLine(string $msg): void {
  global $LOG_FILE;
  @file_put_contents($LOG_FILE, "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
}

function respondActionError(string $message): void {
  actionLogLine("ERRO fatal: " . $message);
  if (!headers_sent()) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
}

set_exception_handler(function (Throwable $e): void {
  respondActionError($e->getMessage());
  exit;
});

register_shutdown_function(function (): void {
  $err = error_get_last();
  if (!$err) return;
  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
  if (!in_array((int)$err['type'], $fatalTypes, true)) return;
  $file = isset($err['file']) ? basename((string)$err['file']) : 'arquivo desconhecido';
  $line = isset($err['line']) ? (string)$err['line'] : '?';
  respondActionError((string)$err['message'] . " em {$file}:{$line}");
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
  throw new RuntimeException('Raiz nao encontrada');
}

function out(array $payload): void {
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function curlGetJson(string $url, string $apiKey): ?array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Basic ' . base64_encode($apiKey . ':'),
  ]);
  curl_setopt($ch, CURLOPT_TIMEOUT, 25);
  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false || $http < 200 || $http >= 300) return null;
  $json = json_decode($res, true);
  return is_array($json) ? $json : null;
}

function vindiGetBill(int $billId, string $base, string $apiKey): ?array {
  if ($apiKey === '') return null;
  $resp = curlGetJson(rtrim($base, '/') . '/bills/' . $billId, $apiKey);
  if (!$resp) return null;
  $bill = $resp['bill'] ?? $resp;
  return is_array($bill) ? $bill : null;
}

function vindiGetCustomerPhone(int $customerId, string $base, string $apiKey): string {
  if ($customerId <= 0 || $apiKey === '') return '';
  $resp = curlGetJson(rtrim($base, '/') . '/customers/' . $customerId, $apiKey);
  if (!$resp) return '';
  $c = $resp['customer'] ?? $resp;
  if (!is_array($c)) return '';

  $phones = [];
  foreach (['phone_number', 'mobile', 'phone'] as $key) {
    if (!empty($c[$key])) $phones[] = (string)$c[$key];
  }
  if (!empty($c['phones']) && is_array($c['phones'])) {
    foreach ($c['phones'] as $phone) {
      if (is_array($phone)) {
        foreach (['number', 'phone_number'] as $key) {
          if (!empty($phone[$key])) $phones[] = (string)$phone[$key];
        }
      } elseif (is_string($phone)) {
        $phones[] = $phone;
      }
    }
  }

  foreach ($phones as $phone) {
    $phone = trim($phone);
    if ($phone !== '') return $phone;
  }
  return '';
}

function extractPhoneFromBill(array $bill): string {
  $c = $bill['customer'] ?? [];
  if (!is_array($c)) return '';

  $phones = [];
  foreach (['phone_number', 'mobile', 'phone'] as $key) {
    if (!empty($c[$key])) $phones[] = (string)$c[$key];
  }
  if (!empty($c['phones']) && is_array($c['phones'])) {
    foreach ($c['phones'] as $phone) {
      if (is_array($phone)) {
        foreach (['number', 'phone_number'] as $key) {
          if (!empty($phone[$key])) $phones[] = (string)$phone[$key];
        }
      } elseif (is_string($phone)) {
        $phones[] = $phone;
      }
    }
  }

  foreach ($phones as $phone) {
    $phone = trim($phone);
    if ($phone !== '') return $phone;
  }
  return '';
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

function buildBillItemsText(array $bill): string {
  $items = $bill['bill_items'] ?? $bill['items'] ?? [];
  if (!is_array($items) || empty($items)) return 'Fatura em aberto';

  $parts = [];
  foreach ($items as $item) {
    if (!is_array($item)) continue;
    $product = $item['product'] ?? [];
    $name = (string)($item['description'] ?? ($product['name'] ?? 'Item'));
    $qty = $item['quantity'] ?? null;
    $amount = $item['amount'] ?? $item['pricing_schema']['price'] ?? null;
    $piece = trim($name);
    if ($qty !== null && $qty !== '') $piece .= ' x' . $qty;
    if ($amount !== null && $amount !== '') $piece .= ' - R$ ' . number_format((float)$amount, 2, ',', '.');
    if ($piece !== '') $parts[] = $piece;
  }

  return $parts ? implode('; ', $parts) : 'Fatura em aberto';
}

function seedFromBill(array $bill, string $base, string $apiKey): array {
  $customer = $bill['customer'] ?? [];
  if (!is_array($customer)) $customer = [];

  $customerId = (int)($customer['id'] ?? ($bill['customer_id'] ?? 0));
  $phone = extractPhoneFromBill($bill);
  if ($phone === '' && $customerId > 0) {
    $phone = vindiGetCustomerPhone($customerId, $base, $apiKey);
  }

  return [
    'customer_id' => $customerId > 0 ? $customerId : null,
    'customer_name' => (string)($customer['name'] ?? 'Cliente'),
    'phone' => $phone,
    'bill_url' => (string)($bill['url'] ?? ''),
    'items_text' => buildBillItemsText($bill),
    'amount' => billAmountOrNull($bill),
    'due_at' => mysqlDateTimeOrNull($bill['due_at'] ?? null),
  ];
}

function blankSeed(): array {
  return [
    'customer_id' => null,
    'customer_name' => '',
    'phone' => '',
    'bill_url' => '',
    'items_text' => '',
    'amount' => null,
    'due_at' => null,
  ];
}

function preferFilled($newValue, $oldValue) {
  if ($newValue === null) return $oldValue;
  if (is_string($newValue) && trim($newValue) === '') return $oldValue;
  return $newValue;
}

function nextReminderFromDue(?string $dueAt, int $firstDelayDays): ?string {
  if ($dueAt === null || trim($dueAt) === '') return null;
  $ts = strtotime($dueAt);
  if (!$ts) return null;
  if ($ts >= time()) return date('Y-m-d H:i:s', $ts);
  return date('Y-m-d H:i:s', $ts + ($firstDelayDays * 86400));
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new RuntimeException('POST obrigatorio');
  }

  $billId = (int)($_POST['bill_id'] ?? 0);
  $action = trim((string)($_POST['action'] ?? ''));

  if ($billId <= 0) throw new RuntimeException('bill_id invalido');
  if (!in_array($action, ['pause', 'resume', 'send_now'], true)) {
    throw new RuntimeException('action invalida');
  }

  $ROOT = findRootWithFiles(['config.php', 'db.php']);
  require_once $ROOT . '/config.php';
  require_once $ROOT . '/db.php';

  $VINDI_API_KEY = cfg($cfg, 'VINDI_API_KEY');
  $VINDI_API_BASE = cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1');
  $FIRST_DELAY_DAYS = max(1, (int)cfg($cfg, 'RECOBRANCA_FIRST_DELAY_DAYS', '7'));

  $st = $pdo->prepare("
    SELECT bill_id, customer_id, customer_name, phone, bill_url, items_text, amount, due_at, next_reminder_at
    FROM bill_reminders
    WHERE bill_id = ?
    LIMIT 1
  ");
  $st->execute([$billId]);
  $existing = $st->fetch(PDO::FETCH_ASSOC) ?: null;

  $needsSeed = !$existing;
  if ($existing) {
    foreach (['customer_name', 'phone', 'bill_url', 'items_text', 'amount', 'due_at'] as $key) {
      if (!isset($existing[$key]) || trim((string)$existing[$key]) === '') {
        $needsSeed = true;
        break;
      }
    }
  }

  $seed = blankSeed();
  $seededFromVindi = false;
  if ($needsSeed) {
    $bill = vindiGetBill($billId, $VINDI_API_BASE, $VINDI_API_KEY);
    if ($bill) {
      $seed = seedFromBill($bill, $VINDI_API_BASE, $VINDI_API_KEY);
      $seededFromVindi = true;
    } elseif (!$existing) {
      throw new RuntimeException('Nao encontrei esta bill no banco e nao consegui buscar na Vindi.');
    }
  }

  $blocked = $action === 'pause' ? 1 : 0;
  $status = $action === 'pause' ? 'blocked' : 'unpaid';

  if ($existing) {
    $merged = [
      'customer_id' => preferFilled($seed['customer_id'], $existing['customer_id'] ?? null),
      'customer_name' => preferFilled($seed['customer_name'], $existing['customer_name'] ?? ''),
      'phone' => preferFilled($seed['phone'], $existing['phone'] ?? ''),
      'bill_url' => preferFilled($seed['bill_url'], $existing['bill_url'] ?? ''),
      'items_text' => preferFilled($seed['items_text'], $existing['items_text'] ?? ''),
      'amount' => preferFilled($seed['amount'], $existing['amount'] ?? null),
      'due_at' => preferFilled($seed['due_at'], $existing['due_at'] ?? null),
    ];
    if ($action === 'pause') {
      $nextReminderAt = null;
    } elseif ($action === 'send_now') {
      $nextReminderAt = $existing['next_reminder_at'] ?? nextReminderFromDue($merged['due_at'], $FIRST_DELAY_DAYS);
    } else {
      $nextReminderAt = nextReminderFromDue($merged['due_at'], $FIRST_DELAY_DAYS);
    }

    $st = $pdo->prepare("
      UPDATE bill_reminders
      SET customer_id = ?,
          customer_name = ?,
          phone = ?,
          bill_url = ?,
          items_text = ?,
          amount = ?,
          due_at = ?,
          active = 1,
          blocked = ?,
          status = ?,
          next_reminder_at = ?
      WHERE bill_id = ?
    ");
    $st->execute([
      $merged['customer_id'],
      $merged['customer_name'],
      $merged['phone'],
      $merged['bill_url'],
      $merged['items_text'],
      $merged['amount'],
      $merged['due_at'],
      $blocked,
      $status,
      $nextReminderAt,
      $billId,
    ]);
  } else {
    $nextReminderAt = $action === 'pause'
      ? null
      : ($action === 'send_now' ? null : nextReminderFromDue($seed['due_at'], $FIRST_DELAY_DAYS));

    $st = $pdo->prepare("
      INSERT INTO bill_reminders (
        bill_id, customer_id, customer_name, phone, bill_url, items_text,
        amount, due_at, active, blocked, status, next_reminder_at
      )
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
    ");
    $st->execute([
      $billId,
      $seed['customer_id'],
      $seed['customer_name'],
      $seed['phone'],
      $seed['bill_url'],
      $seed['items_text'],
      $seed['amount'],
      $seed['due_at'],
      $blocked,
      $status,
      $nextReminderAt,
    ]);
  }

  out([
    'ok' => true,
    'action' => $action,
    'bill_id' => $billId,
    'seeded_from_vindi' => $seededFromVindi,
  ]);
} catch (Throwable $e) {
  out(['ok' => false, 'error' => $e->getMessage()]);
}
