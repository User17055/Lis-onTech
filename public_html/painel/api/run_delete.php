<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

function out(array $payload, int $status = 200): void
{
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['ok' => false, 'error' => 'Metodo invalido'], 405);
  }

  $raw = file_get_contents('php://input') ?: '';
  $body = json_decode($raw, true);
  if (!is_array($body)) {
    $body = $_POST;
  }

  $runId = trim((string)($body['run_id'] ?? ''));
  if ($runId === '') {
    out(['ok' => false, 'error' => 'run_id obrigatorio'], 400);
  }

  $st = $pdo->prepare("
    SELECT run_id, status, step_whatsapp
    FROM automation_runs
    WHERE run_id = ?
    LIMIT 1
  ");
  $st->execute([$runId]);
  $run = $st->fetch(PDO::FETCH_ASSOC);

  if (!$run) {
    out(['ok' => false, 'error' => 'Registro nao encontrado'], 404);
  }

  $status = strtolower((string)($run['status'] ?? ''));
  $whatsappSent = (int)($run['step_whatsapp'] ?? 0) === 1;

  if ($whatsappSent || in_array($status, ['processed', 'success', 'ok'], true)) {
    out([
      'ok' => false,
      'error' => 'Esta mensagem ja foi enviada. Por seguranca, so excluo pendentes ou falhas.'
    ], 403);
  }

  if (!in_array($status, ['not_sent', 'error', 'failed', ''], true)) {
    out(['ok' => false, 'error' => 'Status nao permitido para exclusao: ' . ($status ?: '-')], 403);
  }

  $pdo->beginTransaction();

  $logDel = $pdo->prepare("DELETE FROM automation_run_logs WHERE run_id = ?");
  $logDel->execute([$runId]);

  $runDel = $pdo->prepare("DELETE FROM automation_runs WHERE run_id = ?");
  $runDel->execute([$runId]);

  $pdo->commit();

  out([
    'ok' => true,
    'run_id' => $runId,
    'deleted_logs' => $logDel->rowCount(),
    'deleted_runs' => $runDel->rowCount(),
  ]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  out(['ok' => false, 'error' => $e->getMessage()], 500);
}
