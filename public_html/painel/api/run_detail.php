<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

$runId = trim((string)($_GET['run_id'] ?? ''));
if ($runId === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'run_id obrigatório'], JSON_UNESCAPED_UNICODE);
  exit;
}

$stmt = $pdo->prepare("
  SELECT ar.*, br.phone AS reminder_phone
  FROM automation_runs ar
  LEFT JOIN bill_reminders br ON br.bill_id = ar.bill_id
  WHERE ar.run_id = ?
");
$stmt->execute([$runId]);
$run = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$run) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Run não encontrada'], JSON_UNESCAPED_UNICODE);
  exit;
}

$rawStatus = strtolower((string)($run['status'] ?? ''));
$errorMessage = strtolower((string)($run['error_message'] ?? ''));
if ($rawStatus === 'not_sent' && (
  str_contains($errorMessage, 'ja paga') ||
  str_contains($errorMessage, 'já paga') ||
  str_contains($errorMessage, 'ja esta paga') ||
  str_contains($errorMessage, 'já está paga')
)) {
  $run['status'] = 'paid';
}

/**
 * Logs: tenta ler colunas step/context_json se existirem.
 * Se não existirem, cai no fallback (sem step/context).
 */
try {
  $logsStmt = $pdo->prepare("
    SELECT created_at, level, step, message, context_json
    FROM automation_run_logs
    WHERE run_id = ?
    ORDER BY created_at ASC
  ");
  $logsStmt->execute([$runId]);
  $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($logs as &$l) {
    $ctx = null;
    if (!empty($l['context_json'])) {
      $decoded = json_decode((string)$l['context_json'], true);
      if (json_last_error() === JSON_ERROR_NONE) $ctx = $decoded;
    }
    $l['context'] = $ctx;
    unset($l['context_json']);
  }
  unset($l);

} catch (Throwable $e) {
  // Fallback se sua tabela não tem step/context_json
  $logsStmt = $pdo->prepare("
    SELECT created_at, level, message
    FROM automation_run_logs
    WHERE run_id = ?
    ORDER BY created_at ASC
  ");
  $logsStmt->execute([$runId]);
  $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($logs as &$l) {
    $l['step'] = null;
    $l['context'] = null;
  }
  unset($l);
}

try {
  $chatStmt = $pdo->prepare("
    SELECT status, error_text, http_code, response_json, payload_json, created_at
    FROM chat_messages
    WHERE source_ref = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 1
  ");
  $chatStmt->execute([$runId]);
  $chatMessage = $chatStmt->fetch(PDO::FETCH_ASSOC);
  if ($chatMessage) {
    $run['chat_status'] = $chatMessage['status'] ?? null;
    $run['chat_error_text'] = $chatMessage['error_text'] ?? null;
    $run['chat_http_code'] = $chatMessage['http_code'] ?? null;
    $run['chat_response_json'] = $chatMessage['response_json'] ?? null;
    $run['chat_payload_json'] = $chatMessage['payload_json'] ?? null;
    $run['chat_message_at'] = $chatMessage['created_at'] ?? null;
  }
} catch (Throwable $e) {
  // Chat pode nao existir em instalacoes antigas; detalhes continua funcionando.
}

echo json_encode([
  'ok' => true,
  'run' => $run,
  'logs' => $logs
], JSON_UNESCAPED_UNICODE);
