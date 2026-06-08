<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

function out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensureReportMarks(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS report_customer_marks (
            mark_key VARCHAR(220) NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            customer_name VARCHAR(180) NULL,
            reason TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (mark_key),
            KEY idx_report_customer_marks_active (active),
            KEY idx_report_customer_marks_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['ok' => false, 'error' => 'Metodo nao permitido'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$action = strtolower(trim((string)($data['action'] ?? 'mark')));
$markKey = trim((string)($data['mark_key'] ?? ''));
$reason = trim((string)($data['reason'] ?? ''));
$customerId = (int)($data['customer_id'] ?? 0);
$customerName = trim((string)($data['customer_name'] ?? ''));

if ($markKey === '') {
    out(['ok' => false, 'error' => 'Cliente nao informado'], 422);
}

ensureReportMarks($pdo);

if ($action === 'unmark') {
    $st = $pdo->prepare("UPDATE report_customer_marks SET active = 0, updated_at = NOW() WHERE mark_key = ?");
    $st->execute([$markKey]);
    out(['ok' => true, 'marked' => false]);
}

if ($reason === '') {
    out(['ok' => false, 'error' => 'Informe o motivo da marcacao'], 422);
}

$st = $pdo->prepare("
    INSERT INTO report_customer_marks (mark_key, customer_id, customer_name, reason, active)
    VALUES (?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
        customer_id = VALUES(customer_id),
        customer_name = VALUES(customer_name),
        reason = VALUES(reason),
        active = 1,
        updated_at = NOW()
");
$st->execute([
    $markKey,
    $customerId > 0 ? $customerId : null,
    $customerName !== '' ? $customerName : null,
    $reason,
]);

out(['ok' => true, 'marked' => true, 'reason' => $reason]);
