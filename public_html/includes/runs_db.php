<?php
declare(strict_types=1);

require_once __DIR__ . '/chat_db.php';

function runCreate(PDO $pdo, array $data): string {
    $runId = bin2hex(random_bytes(16));

    $stmt = $pdo->prepare("
      INSERT INTO automation_runs
      (run_id, event_type, status, step_vindi, customer_id, customer_name, bill_id, bill_url, vindi_input)
      VALUES (?, ?, 'processing', 1, ?, ?, ?, ?, CAST(? AS JSON))
    ");

    $stmt->execute([
      $runId,
      (string)$data['event_type'],
      $data['customer_id'] ?? null,
      $data['customer_name'] ?? null,
      $data['bill_id'] ?? null,
      $data['bill_url'] ?? null,
      json_encode($data['vindi_input'] ?? [], JSON_UNESCAPED_UNICODE),
    ]);

    return $runId;
}

function runLog(PDO $pdo, string $runId, string $level, string $message): void {
    $stmt = $pdo->prepare("INSERT INTO automation_run_logs (run_id, level, message) VALUES (?, ?, ?)");
    $stmt->execute([$runId, $level, $message]);
}

function runMarkNotSent(PDO $pdo, string $runId, string $msg): void {
    $stmt = $pdo->prepare("
      UPDATE automation_runs
      SET status='not_sent', step_whatsapp=0, error_message=?
      WHERE run_id=?
    ");
    $stmt->execute([$msg, $runId]);
}

function runMarkProcessed(PDO $pdo, string $runId, string $phone, array $req, array $resp, int $http): void {
    $stmt = $pdo->prepare("
      UPDATE automation_runs
      SET status='processed',
          step_whatsapp=1,
          phone=?,
          meta_http=?,
          whatsapp_request=CAST(? AS JSON),
          whatsapp_response=CAST(? AS JSON),
          error_message=NULL,
          curl_error=NULL
      WHERE run_id=?
    ");
    $stmt->execute([
      $phone,
      $http,
      json_encode($req, JSON_UNESCAPED_UNICODE),
      json_encode($resp, JSON_UNESCAPED_UNICODE),
      $runId
    ]);

    if (empty($req['dry_run'])) {
        try {
            chatSaveOutgoingMessage(
                $pdo,
                $phone,
                chatDescribeWhatsAppPayload($req),
                $req,
                $resp,
                $http,
                null,
                'automation',
                $runId
            );
        } catch (Throwable $e) {
            try { runLog($pdo, $runId, 'error', 'Chat log falhou: ' . $e->getMessage()); } catch (Throwable $ignored) {}
        }
    }
}

function runMarkErrorFull(PDO $pdo, string $runId, string $msg, array $details): void {
    $stmt = $pdo->prepare("
      UPDATE automation_runs
      SET status='error',
          error_message=?,
          meta_http=?,
          curl_error=?,
          whatsapp_request=CAST(? AS JSON),
          whatsapp_response=CAST(? AS JSON),
          error_details=CAST(? AS JSON)
      WHERE run_id=?
    ");

    $stmt->execute([
        $msg,
        $details['meta_http'] ?? null,
      $details['curl_error'] ?? null,
      json_encode($details['whatsapp_request'] ?? [], JSON_UNESCAPED_UNICODE),
      json_encode($details['whatsapp_response'] ?? [], JSON_UNESCAPED_UNICODE),
      json_encode($details, JSON_UNESCAPED_UNICODE),
        $runId
    ]);

    $req = $details['whatsapp_request'] ?? [];
    $resp = $details['whatsapp_response'] ?? [];
    if (is_array($req) && !empty($req) && is_array($resp)) {
        $phone = (string)($details['phone'] ?? $req['to'] ?? '');
        if ($phone !== '' && empty($req['dry_run'])) {
            try {
                chatSaveOutgoingMessage(
                    $pdo,
                    $phone,
                    chatDescribeWhatsAppPayload($req),
                    $req,
                    $resp,
                    (int)($details['meta_http'] ?? 0),
                    isset($details['curl_error']) ? (string)$details['curl_error'] : null,
                    'automation',
                    $runId
                );
            } catch (Throwable $e) {
                try { runLog($pdo, $runId, 'error', 'Chat log falhou: ' . $e->getMessage()); } catch (Throwable $ignored) {}
            }
        }
    }
}
