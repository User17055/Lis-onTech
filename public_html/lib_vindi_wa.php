<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

function logLine(string $file, string $msg): void {
    $line = "[" . date('d/m/Y H:i:s') . "] " . $msg . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND);
}

function waClean(string $s): string {
    $s = preg_replace("/[\r\n\t]+/", " ", $s);
    $s = preg_replace("/ {2,}/", " ", $s);
    return trim($s);
}

function normalizePhoneToWA(string $destinatario): string {
    $destinatario = preg_replace('/[^0-9]/', '', $destinatario);

    // Se vier só DDD+numero (10/11 dígitos) coloca 55
    if (strlen($destinatario) > 0 && strlen($destinatario) <= 11) {
        $destinatario = "55" . $destinatario;
    }
    return $destinatario;
}

function buildBillItemsText(array $bill): string {
    $itens = $bill['bill_items'] ?? [];

    if (!is_array($itens) || empty($itens)) {
        return "Sem itens informados";
    }

    $linhas = [];
    foreach ($itens as $item) {
        if (!is_array($item)) continue;

        $nomeItem = $item['product']['name'] ?? $item['description'] ?? 'Item';
        $qtd = $item['quantity'] ?? null;

        if (!empty($qtd) && (int)$qtd > 1) {
            $linhas[] = "{$nomeItem}, " . (int)$qtd . " un";
        } else {
            $linhas[] = "{$nomeItem}";
        }
    }

    return waClean(implode(" | ", $linhas));
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
        if (is_string($cand) && trim($cand) !== '') {
            return $cand;
        }
    }
    return null;
}

function curlGetJson(string $url, string $apiKey): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($apiKey . ':'),
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $http < 200 || $http >= 300) {
        return null;
    }

    $json = json_decode($res, true);
    return is_array($json) ? $json : null;
}

function getCustomerPhoneFromVindi(int $customerId, string $base, string $apiKey, string $logFile): ?string {
    if (empty($apiKey) || $apiKey === 'COLOQUE_NO_ENV') {
        logLine($logFile, "AVISO: VINDI_API_KEY não configurada, não dá para buscar telefone via API.");
        return null;
    }

    $url = rtrim($base, '/') . "/customers/" . $customerId;

    $respRaw = curlGetJson($url, $apiKey);
    if ($respRaw === null) {
        logLine($logFile, "ERRO: Falha ao chamar Vindi GET /customers/{$customerId}");
        return null;
    }

    $customer = $respRaw['customer'] ?? $respRaw;
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
        if (is_string($cand) && trim($cand) !== '') {
            return $cand;
        }
    }

    return null;
}

function enviarTemplateWhatsApp(
    string $phoneNumberId,
    string $token,
    string $destinatario,
    string $templateNome,
    string $lang,
    array $paramsBody
): string {
    $destinatario = normalizePhoneToWA($destinatario);

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

    $res = curl_exec($ch);
    curl_close($ch);

    return $res ?: '';
}

/* =========================
   SQLite (controle cobrança)
   ========================= */

function dbInit(string $dbFile): PDO {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bills (
            bill_id INTEGER PRIMARY KEY,
            customer_id INTEGER,
            name TEXT,
            phone TEXT,
            url TEXT,
            items_text TEXT,
            due_at TEXT,
            status TEXT
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reminders_sent (
            bill_id INTEGER NOT NULL,
            day_offset INTEGER NOT NULL,
            sent_at TEXT NOT NULL,
            PRIMARY KEY (bill_id, day_offset)
        );
    ");

    return $pdo;
}

function getBillDueAt(array $bill): ?string {
    if (!empty($bill['due_at'])) return (string)$bill['due_at'];
    if (!empty($bill['charges'][0]['due_at'])) return (string)$bill['charges'][0]['due_at'];
    return null;
}

function upsertBill(PDO $pdo, array $bill, string $nome, string $telefone, string $url, string $itemsText, ?string $dueAt, string $status): void {
    $stmt = $pdo->prepare("
        INSERT INTO bills (bill_id, customer_id, name, phone, url, items_text, due_at, status)
        VALUES (:bill_id, :customer_id, :name, :phone, :url, :items_text, :due_at, :status)
        ON CONFLICT(bill_id) DO UPDATE SET
            customer_id=excluded.customer_id,
            name=excluded.name,
            phone=excluded.phone,
            url=excluded.url,
            items_text=excluded.items_text,
            due_at=excluded.due_at,
            status=excluded.status
    ");

    $stmt->execute([
        ':bill_id'     => (int)($bill['id'] ?? 0),
        ':customer_id' => (int)($bill['customer']['id'] ?? 0),
        ':name'        => $nome,
        ':phone'       => normalizePhoneToWA($telefone),
        ':url'         => $url,
        ':items_text'  => $itemsText,
        ':due_at'      => $dueAt,
        ':status'      => $status,
    ]);
}

function updateBillStatus(PDO $pdo, int $billId, string $status): void {
    $st = $pdo->prepare("UPDATE bills SET status=:s WHERE bill_id=:id");
    $st->execute([':s' => $status, ':id' => $billId]);
}

function reminderSent(PDO $pdo, int $billId, int $dayOffset): bool {
    $st = $pdo->prepare("SELECT 1 FROM reminders_sent WHERE bill_id=:b AND day_offset=:d");
    $st->execute([':b' => $billId, ':d' => $dayOffset]);
    return (bool)$st->fetchColumn();
}

function markReminderSent(PDO $pdo, int $billId, int $dayOffset): void {
    $st = $pdo->prepare("INSERT OR IGNORE INTO reminders_sent (bill_id, day_offset, sent_at) VALUES (:b,:d,:s)");
    $st->execute([':b' => $billId, ':d' => $dayOffset, ':s' => date('c')]);
}

function daysOverdue(string $dueAt): int {
    $due = new DateTime($dueAt);
    $due->setTime(0,0,0);

    $today = new DateTime('now');
    $today->setTime(0,0,0);

    if ($today <= $due) return 0;
    return (int)$due->diff($today)->format('%a');
}
