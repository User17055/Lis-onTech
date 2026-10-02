<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/simplesvet_sales.php';

svSalesEnsureTables($pdo);

$status = trim((string)($_GET['status'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));
$month = trim((string)($_GET['month'] ?? ''));
[$whereSql, $params] = svSalesJobsWhere($pdo, $status, $query, $month);
$stmt = $pdo->prepare("
    SELECT id, bill_id, customer_id, customer_name, amount, paid_at, status, attempts,
           simplesvet_sale_id, last_error, completed_at, source_payload
      FROM simplesvet_sale_jobs
     WHERE {$whereSql}
     ORDER BY COALESCE(paid_at, created_at) DESC, id DESC
");
$stmt->execute($params);

$labels = svSalesStatusLabels();
// Texto iniciado por = + - @ vira formula no Excel.
$cell = static function ($value): string {
    $text = (string)$value;
    return preg_match('/^[=+\-@\t\r]/', $text) ? "'" . $text : $text;
};
$date = static function ($value): string {
    $timestamp = $value ? strtotime((string)$value) : false;
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '';
};

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="baixas-sv-' . date('Y-m-d-His') . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [
    'Tarefa', 'Fatura Vindi', 'Cliente', 'Cliente Vindi', 'Itens', 'Forma de pagamento', 'Valor',
    'Pago em', 'Status', 'Tentativas', 'Venda SV', 'Concluída em', 'Ocorrência',
], ';', '"', '');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $bill = svSalesJobBill($row['source_payload']);
    $items = [];
    foreach (svSalesBillItemsSummary($bill) as $item) {
        $items[] = $item['name'] . ($item['quantity'] > 1 ? ' x' . $item['quantity'] : '');
    }
    fputcsv($out, array_map($cell, [
        $row['id'],
        $row['bill_id'],
        $row['customer_name'],
        $row['customer_id'] ?? '',
        implode(' | ', $items),
        svSalesPaymentMethodName($bill),
        $row['amount'] === null ? '' : number_format((float)$row['amount'], 2, ',', ''),
        $date($row['paid_at']),
        $labels[$row['status']] ?? $row['status'],
        $row['attempts'],
        $row['simplesvet_sale_id'] ?? '',
        $date($row['completed_at']),
        $row['last_error'] ?? '',
    ]), ';', '"', '');
}
fclose($out);
