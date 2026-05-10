<?php
// vindi_wa_lib.php

function logLine(string $file, string $msg): void {
    file_put_contents($file, "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
}

function waClean(string $s): string {
    $s = preg_replace("/[\r\n\t]+/", " ", $s);
    $s = preg_replace("/ {2,}/", " ", $s);
    return trim($s);
}

function parseDueAtToTs(?string $dueAt): int {
    if (!$dueAt) return 0;
    $dueAt = trim($dueAt);
    if ($dueAt === '') return 0;

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueAt)) {
        $dueAt .= ' 23:59:59';
    }
    $ts = strtotime($dueAt);
    return $ts === false ? 0 : $ts;
}

// Dias de atraso baseado em data (ignora hora)
function daysOverdueFromDueAt(string $dueAt): int {
    $dueAt = trim($dueAt);
    if ($dueAt === '') return 0;

    $dueDate = substr($dueAt, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) return 0;

    $dueTs   = strtotime($dueDate . ' 00:00:00');
    $todayTs = strtotime(date('Y-m-d') . ' 00:00:00');

    if ($dueTs === false || $todayTs === false) return 0;

    $diff = $todayTs - $dueTs;
    if ($diff <= 0) return 0;

    return (int)floor($diff / 86400);
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

function vindiListBills(string $base, string $apiKey, string $query, int $page=1, int $perPage=50): array {
    $url = rtrim($base,'/') . "/bills?per_page={$perPage}&page={$page}&query=" . rawurlencode($query);
    $resp = curlGetJson($url, $apiKey);
    $bills = $resp['bills'] ?? [];
    if (!is_array($bills)) return [];
    $out = [];
    foreach ($bills as $wrap) {
        $bill = $wrap['bill'] ?? $wrap;
        if (is_array($bill)) $out[] = $bill;
    }
    return $out;
}

function getBillFromVindi(int $billId, string $base, string $apiKey, string $logFile): ?array {
    $url = rtrim($base, '/') . "/bills/" . $billId;
    $resp = curlGetJson($url, $apiKey);
    if (!$resp) {
        logLine($logFile, "GET bill falhou bill_id={$billId}");
        return null;
    }
    $bill = $resp['bill'] ?? $resp;
    return is_array($bill) ? $bill : null;
}

function extractPhoneFromPayload(array $bill): ?string {
    $customer = $bill['customer'] ?? [];
    if (!is_array($customer)) $customer = [];

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

    if (!empty($bill['metadata']) && is_array($bill['metadata'])) {
        $candidates[] = $bill['metadata']['whatsapp'] ?? null;
        $candidates[] = $bill['metadata']['telefone'] ?? null;
        $candidates[] = $bill['metadata']['phone'] ?? null;
    }

    if (!empty($customer['metadata']) && is_array($customer['metadata'])) {
        $candidates[] = $customer['metadata']['whatsapp'] ?? null;
        $candidates[] = $customer['metadata']['telefone'] ?? null;
        $candidates[] = $customer['metadata']['phone'] ?? null;
    }

    foreach ($candidates as $cand) {
        if (is_string($cand) && trim($cand) !== '') return $cand;
    }
    return null;
}

function getCustomerPhoneFromVindi(int $customerId, string $base, string $apiKey, string $logFile): ?string {
    $url = rtrim($base, '/') . "/customers/" . $customerId;
    $resp = curlGetJson($url, $apiKey);
    if (!$resp) {
        logLine($logFile, "GET customer falhou customer_id={$customerId}");
        return null;
    }
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
    if (!empty($customer['metadata']) && is_array($customer['metadata'])) {
        $candidates[] = $customer['metadata']['whatsapp'] ?? null;
        $candidates[] = $customer['metadata']['telefone'] ?? null;
        $candidates[] = $customer['metadata']['phone'] ?? null;
    }

    foreach ($candidates as $cand) {
        if (is_string($cand) && trim($cand) !== '') return $cand;
    }
    return null;
}

function buildBillItemsText(array $bill): string {
    $itens = $bill['bill_items'] ?? [];
    if (!is_array($itens) || empty($itens)) return "Fatura em aberto";

    $linhas = [];
    foreach ($itens as $item) {
        if (!is_array($item)) continue;
        $nomeItem = $item['product']['name'] ?? $item['description'] ?? 'Item';
        $qtd = $item['quantity'] ?? null;
        $linhas[] = (!empty($qtd) && (int)$qtd > 1) ? "{$nomeItem}, " . (int)$qtd . " un" : "{$nomeItem}";
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
        "recipient_type"    => "individual",
        "to"                => $destinatario,
        "type"              => "template",
        "template"          => [
            "name"     => $templateNome,
            "language" => [ "code" => $lang ],
            "components" => [
                [ "type" => "body", "parameters" => $paramsBody ]
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
        'response_raw' => $res ?: '',
    ];
}

function shouldSendOverdue(PDO $pdo, int $billId, int $intervalDays): bool {
    $st = $pdo->prepare("SELECT active, last_overdue_sent_at FROM bill_reminders WHERE bill_id=? LIMIT 1");
    $st->execute([$billId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ((int)$row['active'] === 0) return false;
        if (!empty($row['last_overdue_sent_at'])) {
            $last = strtotime($row['last_overdue_sent_at']);
            if ($last !== false && (time() - $last) < ($intervalDays * 86400)) return false;
        }
    }
    return true;
}

function markOverdueSent(PDO $pdo, int $billId, ?int $customerId): void {
    $st = $pdo->prepare("
        INSERT INTO bill_reminders (bill_id, customer_id, active, last_overdue_sent_at, overdue_sent_count)
        VALUES (?, ?, 1, NOW(), 1)
        ON DUPLICATE KEY UPDATE
            customer_id = VALUES(customer_id),
            active = 1,
            last_overdue_sent_at = NOW(),
            overdue_sent_count = overdue_sent_count + 1
    ");
    $st->execute([$billId, $customerId]);
}

function logSendAttemptSafe(PDO $pdo, int $billId, string $type, bool $ok, ?int $http, ?string $err, string $raw): void {
    try {
        $st = $pdo->prepare("
            INSERT INTO bill_send_attempts (bill_id, attempt_type, ok, http_code, error_msg, response_raw)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $st->execute([$billId, $type, $ok ? 1 : 0, $http, $err, $raw]);
    } catch (Throwable $e) {}
}

function upsertVindiBill(PDO $pdo, array $bill): void {
    $billId = (int)($bill['id'] ?? 0);
    if ($billId <= 0) return;

    $customer = $bill['customer'] ?? [];
    $customerId = isset($customer['id']) ? (int)$customer['id'] : null;
    $customerName = is_array($customer) ? ($customer['name'] ?? null) : null;

    $status = (string)($bill['status'] ?? '');
    $dueAt  = $bill['due_at'] ?? null;

    $amount = null;
    if (isset($bill['amount'])) $amount = (float)$bill['amount'];
    elseif (isset($bill['total'])) $amount = (float)$bill['total'];

    $url = $bill['url'] ?? null;

    $createdAt = $bill['created_at'] ?? null;
    $updatedAt = $bill['updated_at'] ?? null;

    $toDt = function($s) {
        if (!$s || !is_string($s)) return null;
        $ts = strtotime($s);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    };

    $st = $pdo->prepare("
      INSERT INTO vindi_bills
        (bill_id, customer_id, customer_name, status, due_at, amount, bill_url, created_at, updated_at)
      VALUES
        (:bill_id, :customer_id, :customer_name, :status, :due_at, :amount, :bill_url, :created_at, :updated_at)
      ON DUPLICATE KEY UPDATE
        customer_id=VALUES(customer_id),
        customer_name=VALUES(customer_name),
        status=VALUES(status),
        due_at=VALUES(due_at),
        amount=VALUES(amount),
        bill_url=VALUES(bill_url),
        created_at=VALUES(created_at),
        updated_at=VALUES(updated_at)
    ");
    $st->execute([
        ':bill_id' => $billId,
        ':customer_id' => $customerId,
        ':customer_name' => $customerName,
        ':status' => $status,
        ':due_at' => $toDt($dueAt),
        ':amount' => $amount,
        ':bill_url' => $url,
        ':created_at' => $toDt($createdAt),
        ':updated_at' => $toDt($updatedAt),
    ]);
}
