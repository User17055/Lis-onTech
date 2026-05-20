<?php
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/chat_db.php';

$LOG_DIR = __DIR__ . '/storage/logs';
if (!is_dir($LOG_DIR)) {
  @mkdir($LOG_DIR, 0755, true);
}
$LOG_FILE = $LOG_DIR . '/reminders.log';

function logCron($msg) {
  global $LOG_FILE;
  @file_put_contents($LOG_FILE, "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
}

function waClean(string $s): string {
  $s = preg_replace("/[\r\n\t]+/", " ", $s);
  $s = preg_replace("/ {2,}/", " ", $s);
  return trim($s);
}

/* 🔒 SEGURANÇA */
$CRON_TOKEN = cfg($cfg, 'CRON_TOKEN', '');
$token = $_GET['token'] ?? '';
if ($CRON_TOKEN === '' || $token !== $CRON_TOKEN) {
  http_response_code(403);
  echo "Forbidden\n";
  exit;
}

/* CONFIG META */
$META_PHONE_NUMBER_ID = cfg($cfg, 'META_PHONE_NUMBER_ID');
$META_ACCESS_TOKEN    = cfg($cfg, 'META_ACCESS_TOKEN');

$TEMPLATE_NAME = cfg($cfg, 'META_TEMPLATE_RECOBRANCA');
if ($TEMPLATE_NAME === '') {
  $TEMPLATE_NAME = cfg($cfg, 'META_TEMPLATE_REMINDER_NAME');
}
if ($TEMPLATE_NAME === '') {
  $TEMPLATE_NAME = 'recobranca';
}
$TEMPLATE_LANG = cfg($cfg, 'META_TEMPLATE_LANG', 'pt_BR');

$MAX_PER_RUN   = (int) cfg($cfg, 'REMINDERS_MAX_PER_RUN', '10');
$INTERVAL_DAYS = (int) cfg($cfg, 'REMINDERS_INTERVAL_DAYS', '7');
$FIRST_DELAY_DAYS = max(7, (int) cfg($cfg, 'RECOBRANCA_FIRST_DELAY_DAYS', '7'));

if ($META_PHONE_NUMBER_ID === '' || $META_ACCESS_TOKEN === '' || $TEMPLATE_NAME === '') {
  echo "META ENV/TEMPLATE FAIL\n";
  exit;
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
    if (($p['type'] ?? '') === 'text') {
      $p['text'] = waClean((string)($p['text'] ?? ''));
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
    'http'=>(int)$http,
    'curl_error'=>$err ?: null,
    'request'=>$payload,
    'response_raw'=>$res ?: ''
  ];
}

/* 🔒 LOCK */
$lock = $pdo->query("SELECT GET_LOCK('reminders_http_lock', 1) AS l")->fetch(PDO::FETCH_ASSOC);
if (empty($lock['l'])) {
  echo "LOCK\n";
  exit;
}

try {
  $st = $pdo->prepare("
    SELECT bill_id, customer_name, phone, bill_url, items_text
    FROM bill_reminders
    WHERE active=1
      AND blocked=0
      AND COALESCE(NULLIF(status, ''), 'unpaid') = 'unpaid'
      AND (last_status IS NULL OR last_status = '' OR last_status NOT IN ('paid', 'canceled', 'cancelled'))
      AND due_at IS NOT NULL
      AND due_at <= DATE_SUB(NOW(), INTERVAL {$FIRST_DELAY_DAYS} DAY)
      AND (next_reminder_at IS NULL OR next_reminder_at <= NOW())
    ORDER BY next_reminder_at ASC
    LIMIT {$MAX_PER_RUN}
  ");
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  if (!$rows) {
    echo "OK sent=0\n";
    exit;
  }

  $sent = 0;

  foreach ($rows as $r) {
    $billId = (int)$r['bill_id'];
    $nome   = (string)($r['customer_name'] ?? 'Cliente');
    $phone  = (string)($r['phone'] ?? '');
    $link   = (string)($r['bill_url'] ?? '');
    $itens  = (string)($r['items_text'] ?? 'Sem itens informados');

    if (trim($phone) === '') {
      $pdo->prepare("UPDATE bill_reminders SET active=0 WHERE bill_id=?")->execute([$billId]);
      continue;
    }

    $vars = [
      ["type"=>"text","text"=>waClean($nome)],
      ["type"=>"text","text"=>waClean($link)],
      ["type"=>"text","text"=>waClean($itens)],
    ];

    logCron("Enviando lembrete bill_id={$billId} para {$phone}");

    $res = enviarTemplateWhatsApp(
      $META_PHONE_NUMBER_ID,
      $META_ACCESS_TOKEN,
      $phone,
      $TEMPLATE_NAME,
      $TEMPLATE_LANG,
      $vars
    );

    $ok = ($res['http'] >= 200 && $res['http'] < 300 && empty($res['curl_error']));
    $respArr = json_decode($res['response_raw'] ?? '', true);
    if (!is_array($respArr)) $respArr = ['raw' => (string)($res['response_raw'] ?? '')];

    try {
      chatSaveOutgoingMessage(
        $pdo,
        $phone,
        chatDescribeWhatsAppPayload($res['request'] ?? []),
        $res['request'] ?? [],
        $respArr,
        (int)($res['http'] ?? 0),
        $res['curl_error'] ?? null,
        'reminders_runner',
        (string)$billId
      );
    } catch (Throwable $e) {
      logCron("bill_id={$billId} chat_log_fail=" . $e->getMessage());
    }

    // log
    $pdo->prepare("
      INSERT INTO reminder_logs (bill_id, ok, http_code, message, response_raw)
      VALUES (?, ?, ?, ?, ?)
    ")->execute([
      $billId,
      $ok ? 1 : 0,
      (int)($res['http'] ?? 0),
      $ok ? 'sent' : ('fail: ' . ($res['curl_error'] ?? 'meta_error')),
      (string)($res['response_raw'] ?? '')
    ]);

    if ($ok) {
      $pdo->prepare("
        UPDATE bill_reminders
        SET reminder_count = reminder_count + 1,
            last_reminder_at = NOW(),
            last_overdue_sent_at = NOW(),
            overdue_sent_count = COALESCE(overdue_sent_count,0) + 1,
            next_reminder_at = DATE_ADD(NOW(), INTERVAL {$INTERVAL_DAYS} DAY)
        WHERE bill_id=?
      ")->execute([$billId]);

      $sent++;
    } else {
      // falhou: tenta amanhã
      $pdo->prepare("
        UPDATE bill_reminders
        SET next_reminder_at = DATE_ADD(NOW(), INTERVAL 1 DAY)
        WHERE bill_id=?
      ")->execute([$billId]);
    }
  }

  echo "OK sent={$sent}\n";

} finally {
  $pdo->query("SELECT RELEASE_LOCK('reminders_http_lock')");
}
