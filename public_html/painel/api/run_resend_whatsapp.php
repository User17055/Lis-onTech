<?php
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

// ✅ Log simples
$LOG_DIR = __DIR__ . '/../../storage/logs';
if (!is_dir($LOG_DIR)) {
  @mkdir($LOG_DIR, 0755, true);
}
$LOG = $LOG_DIR . '/resend_whatsapp.log';
function rlog($msg) {
  global $LOG;
  @file_put_contents($LOG, "[".date('d/m/Y H:i:s')."] ".$msg.PHP_EOL, FILE_APPEND);
}

// ✅ não deixar warning quebrar JSON
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// compat PHP < 8 (se precisar)
if (!function_exists('str_contains')) {
  function str_contains(string $haystack, string $needle): bool {
    return $needle === '' ? true : (strpos($haystack, $needle) !== false);
  }
}

/**
 * Procura a base do projeto onde existem os arquivos necessários.
 * Retorna: [$baseEncontrada, $candidatosTestados]
 */
function findBaseForFiles(array $files): array {
  $tries = [];

  // 1) tenta via DOCUMENT_ROOT
  $doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
  if ($doc !== '') {
    $tries[] = $doc;                 // ex: /public_html
    $tries[] = $doc . '/painel';     // ex: /public_html/painel
    $tries[] = dirname($doc);        // ex: /home/... (casos raros)
    $tries[] = dirname($doc) . '/painel';
  }

  // 2) tenta subindo a partir do /painel/api
  $dir = __DIR__;
  for ($i = 0; $i < 6; $i++) {
    $tries[] = $dir;
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
  }

  // remove duplicados
  $tries = array_values(array_unique($tries));

  // testa cada base
  foreach ($tries as $base) {
    $ok = true;
    foreach ($files as $f) {
      if (!file_exists($base . '/' . $f)) { $ok = false; break; }
    }
    if ($ok) return [$base, $tries];
  }

  return ['', $tries];
}

// 🔎 diagnóstico/base (GET)  => /painel/api/run_resend_whatsapp.php?ping=1
if (isset($_GET['ping']) && $_GET['ping'] === '1' && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $needFiles = ['config.php', 'db.php', 'runs_db.php'];
  [$BASE, $CANDS] = findBaseForFiles($needFiles);

  $checks = [];
  foreach ($CANDS as $b) {
    $checks[] = [
      'base' => $b,
      'exists' => [
        'config.php' => file_exists($b.'/config.php'),
        'db.php' => file_exists($b.'/db.php'),
        'runs_db.php' => file_exists($b.'/runs_db.php'),
      ]
    ];
  }

  rlog("PING/DIAG OK");
  echo json_encode([
    'ok' => true,
    'pong' => true,
    '__DIR__' => __DIR__,
    'DOCUMENT_ROOT' => ($_SERVER['DOCUMENT_ROOT'] ?? null),
    'chosen_base' => $BASE ?: null,
    'tries' => $checks,
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Método inválido']);
  exit;
}

try {
  $raw = file_get_contents('php://input') ?: '';
  $body = json_decode($raw, true);

  if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
  }

  $runId  = (string)($body['run_id'] ?? '');
  $phone  = preg_replace('/\D+/', '', (string)($body['phone'] ?? ''));
  $mode   = strtolower(trim((string)($body['mode'] ?? 'manual'))); // manual | same | vindi
  $reason = trim((string)($body['reason'] ?? ''));

  if ($runId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'run_id obrigatório']);
    exit;
  }

  // Brasil: 55 + DDD(2) + número(8/9) => 12/13 dígitos
  if (!preg_match('/^55\d{10,11}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Telefone inválido. Ex: 5511999998888']);
    exit;
  }

  // ✅ motivo obrigatório quando for "same" (cliente recebeu mas não viu)
  if ($mode === 'same' && mb_strlen($reason) < 3) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Motivo do reenvio é obrigatório']);
    exit;
  }

  // localizar base e includes
  $needFiles = ['config.php', 'db.php', 'runs_db.php'];
  [$BASE, $CANDS] = findBaseForFiles($needFiles);

  if ($BASE === '') {
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => 'ARQUIVO BASE NÃO ENCONTRADO',
      '__DIR__' => __DIR__,
      'DOCUMENT_ROOT' => ($_SERVER['DOCUMENT_ROOT'] ?? null),
      'candidates' => $CANDS,
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  require_once $BASE . '/config.php';
  require_once $BASE . '/db.php';
  require_once $BASE . '/runs_db.php';

  // configs Meta
  $META_PHONE_NUMBER_ID = cfg($cfg, 'META_PHONE_NUMBER_ID');
  $META_ACCESS_TOKEN    = cfg($cfg, 'META_ACCESS_TOKEN');
  $TEMPLATE_NAME        = cfg($cfg, 'META_TEMPLATE_NAME');
  if ($TEMPLATE_NAME === '') {
    $TEMPLATE_NAME = 'fatura22';
  }
  $TEMPLATE_LANG        = cfg($cfg, 'META_TEMPLATE_LANG', 'pt_BR');

  if ($META_PHONE_NUMBER_ID === '' || $META_ACCESS_TOKEN === '' || $TEMPLATE_NAME === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Config META/template incompleta']);
    exit;
  }

  // ✅ busca run (tenta por id numérico e por run_id)
  $run = null;

  // tenta id numérico
  if (ctype_digit($runId)) {
    $st = $pdo->prepare("SELECT * FROM automation_runs WHERE id = ? LIMIT 1");
    $st->execute([(int)$runId]);
    $run = $st->fetch(PDO::FETCH_ASSOC);
  }

  // tenta run_id string
  if (!$run) {
    $st = $pdo->prepare("SELECT * FROM automation_runs WHERE run_id = ? LIMIT 1");
    $st->execute([$runId]);
    $run = $st->fetch(PDO::FETCH_ASSOC);
  }

  if (!$run) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Run não encontrada']);
    exit;
  }

  // ✅ Regras de permissão:
  // - mode=manual/vindi => mantém regra antiga: só falha de telefone
  // - mode=same         => permite quando já foi enviado (status ok / step_whatsapp=1 / whatsapp_response preenchido)
  $status = strtolower((string)($run['status'] ?? ''));
  $errMsg = strtolower((string)($run['error_message'] ?? ''));

  $successLike = in_array($status, ['processed','success','ok'], true)
    || ((int)($run['step_whatsapp'] ?? 0) === 1)
    || (trim((string)($run['whatsapp_response'] ?? '')) !== '');

  $looksPhoneFail = (
    $status === 'not_sent' || $status === 'error' || $status === 'failed' || $status === 'canceled' ||
    str_contains($errMsg, 'sem telefone') ||
    str_contains($errMsg, 'telefone') ||
    str_contains($errMsg, 'numero') ||
    str_contains($errMsg, 'número') ||
    str_contains($errMsg, 'invalid') ||
    str_contains($errMsg, 'invál') ||
    str_contains($errMsg, 'inval')
  );

  if ($mode === 'same') {
    if (!$successLike) {
      http_response_code(403);
      echo json_encode(['ok' => false, 'error' => 'Reenvio (mesma mensagem) permitido apenas quando o WhatsApp já foi enviado (status ok).']);
      exit;
    }
  } else {
    if (!$looksPhoneFail) {
      http_response_code(403);
      echo json_encode(['ok' => false, 'error' => 'Reenvio permitido apenas para falhas de telefone']);
      exit;
    }
  }

  // monta variáveis do template
  $nome = (string)($run['customer_name'] ?? 'Cliente');
  $link = (string)($run['bill_url'] ?? '');
  $itens_texto = "Sem itens informados";

  // tenta ler vindi_input salvo (se existir)
  $vindiRaw = (string)($run['vindi_input'] ?? '');
  if ($vindiRaw !== '') {
    $vindi = json_decode($vindiRaw, true);
    if (is_array($vindi)) {
      $bill = $vindi['event']['data']['bill'] ?? null;
      if (is_array($bill)) {
        // itens
        $itens_texto = buildBillItemsText($bill);
        // fallback link/nome
        if ($link === '' && !empty($bill['url'])) $link = (string)$bill['url'];
        $cust = $bill['customer'] ?? [];
        if (($nome === '' || $nome === 'Cliente') && is_array($cust) && !empty($cust['name'])) {
          $nome = (string)$cust['name'];
        }
      }
    }
  }

  if ($link === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bill_url não encontrado para reenviar']);
    exit;
  }

  $variaveis = [
    ["type" => "text", "text" => waClean($nome)],
    ["type" => "text", "text" => waClean($link)],
    ["type" => "text", "text" => waClean($itens_texto)],
  ];

  $tag = ($mode === 'same') ? 'same_resend' : 'manual_resend';

  rlog("RESEND mode={$mode} run_id={$runId} phone={$phone} status={$status} reason=".preg_replace("/[\r\n]+/"," ",$reason));

  // log no banco
  try {
    runLog($pdo, $runId, 'info', "[{$tag}] Disparando para {$phone}" . ($mode === 'same' ? " | motivo: {$reason}" : ""));
  } catch(Throwable $e){}

  $resultado = enviarTemplateWhatsApp(
    $META_PHONE_NUMBER_ID,
    $META_ACCESS_TOKEN,
    $phone,
    $TEMPLATE_NAME,
    $TEMPLATE_LANG,
    $variaveis
  );

  $respArr = json_decode($resultado['response_raw'] ?? '', true);
  if (!is_array($respArr)) $respArr = ['raw' => ($resultado['response_raw'] ?? '')];

  $http = (int)($resultado['http'] ?? 0);
  $metaOk = ($http >= 200 && $http < 300) && empty($resultado['curl_error']) && empty($respArr['error']);

  rlog("META http={$http} curl_error=" . ($resultado['curl_error'] ?? 'null') .
       " resp_preview=" . substr((string)($resultado['response_raw'] ?? ''), 0, 220));

  if ($metaOk) {
    try { runLog($pdo, $runId, 'info', "[{$tag}] Meta aceitou."); } catch(Throwable $e){}

    // ✅ opcional: NÃO sobrescrever o envio original quando for mode=same
    if ($mode !== 'same') {
      try { runMarkProcessed($pdo, $runId, $phone, $resultado['request'], $respArr, $http); } catch(Throwable $e){}
    }

    echo json_encode(['ok' => true]);
    exit;
  }

  // erro Meta
  $metaMsg  = $respArr['error']['message'] ?? '';
  $metaCode = $respArr['error']['code'] ?? '';
  $metaSub  = $respArr['error']['error_subcode'] ?? '';
  $msg = "Falha WhatsApp ({$tag}), HTTP={$http}, code={$metaCode}, sub={$metaSub}, msg={$metaMsg}";

  try { runLog($pdo, $runId, 'error', "[{$tag}] ".$msg); } catch(Throwable $e){}

  // ✅ opcional: NÃO sobrescrever erro original quando for mode=same
  if ($mode !== 'same') {
    try {
      runMarkErrorFull($pdo, $runId, $msg, [
        'meta_http' => $http,
        'curl_error' => $resultado['curl_error'] ?? null,
        'whatsapp_request' => $resultado['request'] ?? [],
        'whatsapp_response' => $respArr
      ]);
    } catch(Throwable $e){}
  }

  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;

} catch (Throwable $e) {
  rlog("FATAL: ".$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Erro interno: '.$e->getMessage()]);
  exit;
}

// ===== helpers iguais do seu vindi.php =====
function waClean(string $s): string {
  $s = preg_replace("/[\r\n\t]+/", " ", $s);
  $s = preg_replace("/ {2,}/", " ", $s);
  return trim($s);
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

  if (strlen($destinatario) > 0 && strlen($destinatario) <= 11) {
    $destinatario = "55" . $destinatario;
  }

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
