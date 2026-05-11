<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';

function findRootWithFiles(array $files): string {
  $dir = __DIR__;
  for ($i=0; $i<10; $i++) {
    $ok = true;
    foreach ($files as $f) {
      if (!file_exists($dir . '/' . $f)) { $ok = false; break; }
    }
    if ($ok) return $dir;
    $dir = dirname($dir);
  }
  throw new RuntimeException("Raiz não encontrada para: " . implode(', ', $files));
}

function curlGetWithHeaders(string $url, string $apiKey): array {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Basic ' . base64_encode($apiKey . ':'),
  ]);

  $raw = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $hsz  = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($raw === false) return ['http'=>$http, 'headers'=>[], 'body'=>'', 'err'=>$err ?: 'curl_error'];

  $headerRaw = substr($raw, 0, $hsz);
  $bodyRaw   = substr($raw, $hsz);

  $headers = [];
  foreach (preg_split("/\r\n|\n|\r/", $headerRaw) as $line) {
    $p = strpos($line, ':');
    if ($p === false) continue;
    $k = strtolower(trim(substr($line, 0, $p)));
    $v = trim(substr($line, $p + 1));
    if ($k !== '') $headers[$k] = $v;
  }

  return ['http'=>$http, 'headers'=>$headers, 'body'=>$bodyRaw, 'err'=>$err ?: null];
}

function vindiListBills(string $base, string $apiKey, string $query, int $page, int $perPage): array {
  $base = rtrim($base, '/');
  $url = $base . "/bills?per_page=" . $perPage . "&page=" . $page . "&query=" . rawurlencode($query);

  $resp = curlGetWithHeaders($url, $apiKey);
  if ($resp['http'] < 200 || $resp['http'] >= 300) {
    return ['ok'=>false, 'error'=>"Vindi HTTP {$resp['http']}", 'raw'=>substr($resp['body'] ?? '', 0, 300)];
  }

  $json = json_decode($resp['body'] ?? '', true);
  if (!is_array($json)) return ['ok'=>false, 'error'=>"JSON inválido da Vindi", 'raw'=>substr($resp['body'] ?? '', 0, 300)];

  // Normalmente vem { bills: [...] }
  $bills = $json['bills'] ?? [];
  if (!is_array($bills)) $bills = [];

  // Paginação via headers Total / Per-Page (case-insensitive)
  $total = 0;
  foreach ($resp['headers'] as $k => $v) {
    if ($k === 'total') $total = (int)$v;
  }

  return ['ok'=>true, 'bills'=>$bills, 'total'=>$total];
}

function vindiListCustomersByName(string $base, string $apiKey, string $name, int $limit=10): array {
  $base = rtrim($base, '/');
  $name = trim($name);
  if ($name === '') return [];

  // query suporta name:xxx (contém) :contentReference[oaicite:3]{index=3}
  $q = preg_match('/\s/', $name) ? 'name:"' . str_replace('"','', $name) . '"' : 'name:' . str_replace('"','', $name);
  $url = $base . "/customers?per_page=" . $limit . "&page=1&query=" . rawurlencode($q);

  $resp = curlGetWithHeaders($url, $apiKey);
  if ($resp['http'] < 200 || $resp['http'] >= 300) return [];

  $json = json_decode($resp['body'] ?? '', true);
  if (!is_array($json)) return [];

  $customers = $json['customers'] ?? [];
  if (!is_array($customers)) return [];

  $ids = [];
  foreach ($customers as $c) {
    $id = (int)($c['id'] ?? 0);
    if ($id > 0) $ids[] = $id;
    if (count($ids) >= $limit) break;
  }
  return $ids;
}

function latestReminderLogs(PDO $pdo, array $billIds): array {
  $billIds = array_values(array_unique(array_filter(array_map('intval', $billIds))));
  if (empty($billIds)) return [];

  $in = implode(',', array_fill(0, count($billIds), '?'));
  try {
    $st = $pdo->prepare("
      SELECT rl.bill_id, rl.created_at, rl.message, rl.ok, rl.http_code
      FROM reminder_logs rl
      INNER JOIN (
        SELECT bill_id, MAX(created_at) AS created_at
        FROM reminder_logs
        WHERE bill_id IN ($in)
        GROUP BY bill_id
      ) last_log
        ON last_log.bill_id = rl.bill_id
       AND last_log.created_at = rl.created_at
    ");
    $st->execute($billIds);

    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $map[(int)$row['bill_id']] = $row;
    }
    return $map;
  } catch (Throwable $e) {
    return [];
  }
}

function applyLastMessage(array &$row, array $lastLogs): void {
  $billId = (int)($row['bill_id'] ?? 0);
  $log = $lastLogs[$billId] ?? null;

  if ($log) {
    $row['last_message_at'] = $log['created_at'] ?? null;
    $row['last_message'] = (string)($log['message'] ?? 'Mensagem registrada');
    $row['last_message_ok'] = isset($log['ok']) ? (int)$log['ok'] : null;
    $row['last_message_http'] = isset($log['http_code']) ? (int)$log['http_code'] : null;
    return;
  }

  $lastSent = $row['last_overdue_sent_at'] ?? ($row['last_reminder_sent_at'] ?? null);
  $lastCheck = $row['last_status_check_at'] ?? null;
  $lastStatus = (string)($row['last_status'] ?? '');

  $row['last_message_at'] = $lastSent ?: ($lastStatus !== '' ? $lastCheck : null);
  if ($lastSent) {
    $row['last_message'] = 'Recobranca enviada via WhatsApp';
    $row['last_message_ok'] = 1;
  } elseif ($lastStatus !== '') {
    $row['last_message'] = 'Ultima tentativa: ' . $lastStatus;
    $row['last_message_ok'] = 0;
  } else {
    $row['last_message'] = 'Nenhuma mensagem enviada ainda';
    $row['last_message_ok'] = null;
  }
  $row['last_message_http'] = null;
}

try {
  $ROOT = findRootWithFiles(['config.php', 'db.php']);
  require_once $ROOT . '/config.php';
  require_once $ROOT . '/db.php'; // precisa ter $pdo

  $VINDI_API_KEY  = cfg($cfg, 'VINDI_API_KEY');
  $VINDI_API_BASE = cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1');

  if (!$VINDI_API_KEY) throw new RuntimeException("VINDI_API_KEY não configurada");

  $page  = max(1, (int)($_GET['page'] ?? 1));
  $limit = max(1, min(50, (int)($_GET['limit'] ?? 50))); // Vindi limita per_page em 50 :contentReference[oaicite:4]{index=4}

  $onlyOverdue = (string)($_GET['only_overdue'] ?? '1') !== '0';
  $statusUI = trim((string)($_GET['status'] ?? '')); // unpaid/paid/canceled/blocked/""
  $q = trim((string)($_GET['q'] ?? ''));

  // Mapeia UI -> Vindi (devendo normalmente é "pending")
  $statusVindi = '';
  if ($statusUI === '' || $statusUI === 'unpaid') $statusVindi = 'pending';
  if ($statusUI === 'paid') $statusVindi = 'paid';
  if ($statusUI === 'canceled') $statusVindi = 'canceled';
  if ($statusUI === 'all') $statusVindi = '';

  // Se quiser listar "blocked" (é local), a gente lista do banco e opcionalmente puxa detalhes por ID depois.
  if ($statusUI === 'blocked') {
    $st = $pdo->prepare("
      SELECT bill_id, customer_name, phone, bill_url, amount, due_at, status, blocked,
             overdue_sent_count, reminder_attempts, next_reminder_at, last_overdue_sent_at,
             last_reminder_sent_at, last_status, last_status_check_at
      FROM bill_reminders
      WHERE blocked=1
      ORDER BY updated_at DESC
      LIMIT {$limit} OFFSET " . (($page-1)*$limit)
    );
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $lastLogs = latestReminderLogs($pdo, array_column($rows, 'bill_id'));
    foreach ($rows as &$row) {
      $row['days_overdue'] = null;
      if (!empty($row['due_at'])) {
        $days = (int)floor((time() - strtotime((string)$row['due_at'])) / 86400);
        $row['days_overdue'] = max(0, $days);
      }
      applyLastMessage($row, $lastLogs);
    }
    unset($row);

    echo json_encode([
      'ok'=>true,
      'page'=>$page,
      'limit'=>$limit,
      'total'=>count($rows),
      'total_pages'=>1,
      'rows'=>$rows,
      'mode'=>'blocked_local'
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $parts = [];

  if ($statusVindi !== '') $parts[] = "status={$statusVindi}";

  if ($onlyOverdue) {
    $now = date('Y-m-d H:i:s');
    $parts[] = 'due_at<="' . $now . '"';
  }

  // Busca:
  // - se for número: pode ser id da fatura ou customer_id
  // - se for texto: busca customers por nome e filtra por customer_id (limit 10)
  if ($q !== '') {
    if (preg_match('/^\d+$/', $q)) {
      $n = (int)$q;
      $parts[] = "(id={$n} OR customer_id={$n})";
    } else {
      $ids = vindiListCustomersByName($VINDI_API_BASE, $VINDI_API_KEY, $q, 10);
      if (!empty($ids)) {
        $or = [];
        foreach ($ids as $id) $or[] = "customer_id={$id}";
        $parts[] = "(" . implode(" OR ", $or) . ")";
      } else {
        // nenhum customer encontrado -> retorna vazio sem estourar chamadas
        echo json_encode(['ok'=>true,'page'=>$page,'limit'=>$limit,'total'=>0,'total_pages'=>1,'rows'=>[]], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
  }

  $query = implode(" AND ", $parts);
  if ($query === '' && $statusUI !== 'all') $query = 'status=pending'; // padrão: devendo

  $v = vindiListBills($VINDI_API_BASE, $VINDI_API_KEY, $query, $page, $limit);
  if (!$v['ok']) {
    echo json_encode(['ok'=>false,'error'=>$v['error'] ?? 'erro','debug'=>$v['raw'] ?? null], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $bills = $v['bills'];
  $total = (int)($v['total'] ?? 0);
  $totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;

  // Junta com seu DB (controle interno)
  $ids = [];
  foreach ($bills as $b) {
    $id = (int)($b['id'] ?? 0);
    if ($id > 0) $ids[] = $id;
  }

  $localMap = [];
  $lastLogMap = [];
  if (!empty($ids)) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
      SELECT bill_id, phone, bill_url, blocked, overdue_sent_count, reminder_attempts, next_reminder_at, last_overdue_sent_at, last_reminder_sent_at, last_status, last_status_check_at
      FROM bill_reminders
      WHERE bill_id IN ($in)
    ");
    $st->execute($ids);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $localMap[(int)$r['bill_id']] = $r;
    }
    $lastLogMap = latestReminderLogs($pdo, $ids);
  }

  $rows = [];
  foreach ($bills as $b) {
    $billId = (int)($b['id'] ?? 0);
    $cust = $b['customer'] ?? [];
    $custId = (int)($cust['id'] ?? ($b['customer_id'] ?? 0));
    $custName = (string)($cust['name'] ?? 'Cliente');
    $amount = $b['amount'] ?? null;   // Vindi pode devolver string
    $dueAt  = $b['due_at'] ?? null;
    $status = (string)($b['status'] ?? '');

    $loc = $localMap[$billId] ?? [];
    $url = (string)($b['url'] ?? ($loc['bill_url'] ?? ''));
    $phone = (string)($loc['phone'] ?? '');
    if ($phone === '' && is_array($cust)) {
      $phone = (string)($cust['phone_number'] ?? $cust['mobile'] ?? $cust['phone'] ?? '');
    }
    $daysOverdue = null;
    if (!empty($dueAt)) {
      $daysOverdue = max(0, (int)floor((time() - strtotime((string)$dueAt)) / 86400));
    }

    $row = [
      'bill_id' => $billId,
      'customer_id' => $custId,
      'customer_name' => $custName,
      'amount' => $amount,
      'due_at' => $dueAt,
      'status' => $status, // AO VIVO da Vindi
      'bill_url' => $url,
      'phone' => $phone,
      'days_overdue' => $daysOverdue,

      // controle interno (do seu banco)
      'blocked' => (int)($loc['blocked'] ?? 0),
      'overdue_sent_count' => (int)($loc['overdue_sent_count'] ?? 0),
      'reminder_attempts' => (int)($loc['reminder_attempts'] ?? 0),
      'next_reminder_at' => $loc['next_reminder_at'] ?? null,
      'last_overdue_sent_at' => $loc['last_overdue_sent_at'] ?? null,
      'last_reminder_sent_at' => $loc['last_reminder_sent_at'] ?? null,
      'last_status' => $loc['last_status'] ?? null,
      'last_status_check_at' => $loc['last_status_check_at'] ?? null,
    ];

    applyLastMessage($row, $lastLogMap);
    $rows[] = $row;
  }

  echo json_encode([
    'ok'=>true,
    'page'=>$page,
    'limit'=>$limit,
    'total'=>$total,
    'total_pages'=>max(1,$totalPages),
    'rows'=>$rows,
    'mode'=>'vindi_live'
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
