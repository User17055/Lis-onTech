<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

function json_out(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

function normalize_br_phone(string $raw): string {
  $d = preg_replace('/\D+/', '', $raw) ?? '';
  if ($d === '') return '';

  // 55 + DDD + numero => 12 ou 13 dígitos
  if (str_starts_with($d, '55') && (strlen($d) === 12 || strlen($d) === 13)) return $d;

  // DDD + numero (10/11) => adiciona 55
  if (strlen($d) === 10 || strlen($d) === 11) return '55' . $d;

  // fallback
  if (strlen($d) === 12 || strlen($d) === 13) return $d;

  return $d;
}

function pick_customer_id_from_bill(array $bill): ?int {
  if (isset($bill['customer_id']) && is_numeric($bill['customer_id'])) return (int)$bill['customer_id'];
  if (isset($bill['customer']['id']) && is_numeric($bill['customer']['id'])) return (int)$bill['customer']['id'];
  if (isset($bill['charge']['customer']['id']) && is_numeric($bill['charge']['customer']['id'])) return (int)$bill['charge']['customer']['id'];
  return null;
}

function pick_phone_from_customer(array $customer): string {
    // 1) campos diretos mais comuns
    foreach (['mobile_phone','cellphone','whatsapp','phone','telefone','celular','mobile','phone_number'] as $k) {
      if (!empty($customer[$k]) && is_string($customer[$k])) {
        $digits = preg_replace('/\D+/', '', $customer[$k]) ?? '';
        if (strlen($digits) >= 10) return normalize_br_phone($customer[$k]);
      }
    }
  
    // 2) Vindi frequentemente usa phones: [{ phone_type, number }]
    if (!empty($customer['phones']) && is_array($customer['phones'])) {
      foreach ($customer['phones'] as $p) {
        if (!is_array($p)) continue;
  
        foreach (['number','phone_number','value'] as $k) {
          if (!empty($p[$k]) && is_string($p[$k])) {
            $digits = preg_replace('/\D+/', '', $p[$k]) ?? '';
            if (strlen($digits) >= 10) return normalize_br_phone($p[$k]);
          }
        }
      }
    }
  
    // 3) fallback: varre tudo e pega a primeira string que pareça telefone
    $stack = [$customer];
    while ($stack) {
      $cur = array_pop($stack);
      foreach ($cur as $k => $v) {
        if (is_array($v)) { $stack[] = $v; continue; }
        if (!is_string($v)) continue;
  
        $digits = preg_replace('/\D+/', '', $v) ?? '';
        if (strlen($digits) < 10) continue;
  
        $kk = strtolower((string)$k);
        $keyLooksPhone = preg_match('/phone|telefone|cel|cell|whats|mobile|number/', $kk) === 1;
        $valueLooksPhone = str_contains($v, '+') || str_contains($v, '(');
  
        if ($keyLooksPhone || $valueLooksPhone) {
          return normalize_br_phone($v);
        }
      }
    }
  
    return '';
  }
  

function vindi_get(string $base, string $key, string $path): array {
  if ($key === '') throw new RuntimeException('VINDI_API_KEY não configurada');

  $base = rtrim($base, '/');
  $url  = $base . '/' . ltrim($path, '/');

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $key . ':', // user=API_KEY, pass vazio
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
  ]);

  $body = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($body === false) throw new RuntimeException('Erro cURL: ' . $err);

  $json = json_decode($body, true);
  if (!is_array($json)) throw new RuntimeException('Resposta inválida da Vindi (não JSON)');

  if ($code < 200 || $code >= 300) {
    $msg = $json['error'] ?? $json['errors'] ?? $json['message'] ?? ('HTTP ' . $code);
    if (is_array($msg)) $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    throw new RuntimeException('Vindi: ' . (string)$msg);
  }

  return $json;
}

try {
  // ✅ pega do MESMO loader que você já usa
  // (assumindo que $cfg e cfg() já existem porque config.php já carrega secure/config.env)
  $VINDI_API_KEY  = cfg($cfg, 'VINDI_API_KEY');
  $VINDI_API_BASE = cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1');

  $raw = file_get_contents('php://input') ?: '';
  $in  = json_decode($raw, true);
  if (!is_array($in)) $in = [];

  $run_id = isset($in['run_id']) ? trim((string)$in['run_id']) : '';
  if ($run_id === '') json_out(['ok' => false, 'error' => 'run_id obrigatório'], 400);

  // 1) pega bill_id no banco
  $stmt = $pdo->prepare("SELECT bill_id FROM automation_runs WHERE run_id = :id LIMIT 1");
  $stmt->execute([':id' => $run_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) json_out(['ok' => false, 'error' => 'Run não encontrada'], 404);

  $bill_id = trim((string)($row['bill_id'] ?? ''));
  if ($bill_id === '') json_out(['ok' => false, 'error' => 'bill_id vazio nessa run'], 422);

  // 2) busca bill na Vindi
  $billResp = vindi_get($VINDI_API_BASE, $VINDI_API_KEY, "/bills/" . urlencode($bill_id));
  $bill = $billResp['bill'] ?? $billResp;

  // tenta pegar telefone direto do customer que vem junto
  $phone = '';
  if (isset($bill['customer']) && is_array($bill['customer'])) {
    $phone = pick_phone_from_customer($bill['customer']);
  }

  // 3) se não veio, busca customer
  if ($phone === '') {
    $customerId = pick_customer_id_from_bill($bill);
    if (!$customerId) json_out(['ok' => false, 'error' => 'Não achei customer_id na bill'], 422);

    $custResp = vindi_get($VINDI_API_BASE, $VINDI_API_KEY, "/customers/" . $customerId);
    $customer = $custResp['customer'] ?? $custResp;

    if (is_array($customer)) $phone = pick_phone_from_customer($customer);
  }

  if ($phone === '') json_out(['ok' => false, 'error' => 'Telefone não encontrado na Vindi'], 404);

  json_out(['ok' => true, 'phone' => $phone]);

} catch (Throwable $e) {
  json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
