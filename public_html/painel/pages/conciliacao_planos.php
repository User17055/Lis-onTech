<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/simplesvet_sales.php';

if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }
svSalesEnsureTables($pdo);

function cph($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function cpVindiGet(array $cfg, string $path, array $query = []): array
{
    $key = cfg($cfg, 'VINDI_API_KEY');
    $base = rtrim(cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1'), '/');
    if ($key === '') throw new RuntimeException('VINDI_API_KEY nao configurada.');
    $url = $base . '/' . ltrim($path, '/');
    if ($query) $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Basic ' . base64_encode($key . ':')],
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $http < 200 || $http >= 300) {
        throw new RuntimeException('Falha ao consultar a Vindi' . ($error ? ': ' . $error : " (HTTP {$http})"));
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException('Resposta invalida recebida da Vindi.');
    return $json;
}

function cpVindiProducts(array $cfg): array
{
    $key = cfg($cfg, 'VINDI_API_KEY');
    $base = rtrim(cfg($cfg, 'VINDI_API_BASE', 'https://app.vindi.com.br/api/v1'), '/');
    if ($key === '') throw new RuntimeException('VINDI_API_KEY não configurada.');
    $all = [];
    for ($page = 1; $page <= 100; $page++) {
        $ch = curl_init($base . '/products?per_page=50&page=' . $page);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Basic ' . base64_encode($key . ':')],
        ]);
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $http < 200 || $http >= 300) {
            throw new RuntimeException('Falha ao consultar produtos na Vindi' . ($error ? ': ' . $error : " (HTTP {$http})"));
        }
        $json = json_decode($raw, true);
        $products = is_array($json['products'] ?? null) ? $json['products'] : [];
        foreach ($products as $product) {
            if (!is_array($product)) continue;
            $all[] = ['product' => $product, 'description' => $product['name'] ?? null];
        }
        if (count($products) < 50) break;
    }
    return $all;
}

function cpImportPaidToday(PDO $pdo, array $cfg): array
{
    $timezone = new DateTimeZone('America/Sao_Paulo');
    $start = new DateTimeImmutable('today', $timezone);
    $end = $start->modify('+1 day');
    $counts = ['found' => 0, 'new' => 0, 'existing' => 0, 'waiting_mapping' => 0, 'pending' => 0, 'ignored' => 0];
    $seenBills = [];
    $reachedOlder = false;

    for ($page = 1; $page <= 100 && !$reachedOlder; $page++) {
        $json = cpVindiGet($cfg, 'charges', [
            'per_page' => 50,
            'page' => $page,
            'query' => 'status=paid',
            'sort_by' => 'paid_at',
            'sort_order' => 'desc',
        ]);
        $charges = is_array($json['charges'] ?? null) ? $json['charges'] : [];
        foreach ($charges as $charge) {
            if (!is_array($charge) || empty($charge['paid_at'])) continue;
            try {
                $paidInstant = new DateTimeImmutable((string)$charge['paid_at']);
            } catch (Throwable $e) {
                continue;
            }
            $paidLocal = $paidInstant->setTimezone($timezone);
            if ($paidLocal >= $end) continue;
            if ($paidLocal < $start) {
                $reachedOlder = true;
                continue;
            }
            $billId = (int)($charge['bill']['id'] ?? $charge['bill_id'] ?? 0);
            if ($billId <= 0 || isset($seenBills[$billId])) continue;
            $seenBills[$billId] = true;
            $counts['found']++;

            $check = $pdo->prepare('SELECT id FROM simplesvet_sale_jobs WHERE bill_id=? LIMIT 1');
            $check->execute([$billId]);
            $alreadyExists = (bool)$check->fetchColumn();

            $billJson = cpVindiGet($cfg, 'bills/' . $billId);
            $bill = is_array($billJson['bill'] ?? null) ? $billJson['bill'] : $billJson;
            if (!is_array($bill) || (int)($bill['id'] ?? 0) !== $billId) {
                throw new RuntimeException("Fatura Vindi #{$billId} retornou dados invalidos.");
            }
            if (!is_array($bill['customer'] ?? null) && !empty($charge['customer_id'])) {
                $bill['customer'] = ['id' => (int)$charge['customer_id']];
            }
            $payload = [
                'event' => [
                    'type' => 'bill_paid',
                    'created_at' => (string)$charge['paid_at'],
                    'data' => ['bill' => $bill],
                ],
                'source' => 'panel_paid_today_import',
            ];
            $status = svSalesEnqueueBill($pdo, $bill, $payload, $paidLocal->format('Y-m-d H:i:s'));
            $counts[$alreadyExists ? 'existing' : 'new']++;
            if (isset($counts[$status])) $counts[$status]++;
        }
        if (count($charges) < 50) break;
    }
    $counts['date'] = $start->format('d/m/Y');
    return $counts;
}

$flash = trim((string)($_GET['notice'] ?? ''));
$flashError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mapping_action'])) {
    if (!authCsrfValid((string)($_POST['csrf'] ?? ''))) {
        $flash = 'Sessão expirada. Atualize a página e tente novamente.';
        $flashError = true;
    } else {
        try {
            $action = (string)$_POST['mapping_action'];
            if ($action === 'sync_vindi') {
                $items = cpVindiProducts($cfg);
                svSalesObserveItems($pdo, $items);
                $released = svSalesRefreshJobs($pdo);
                $flash = count($items) . ' produtos sincronizados da Vindi. ' . $released . ' tarefa(s) reavaliada(s).';
            } elseif ($action === 'import_paid_today') {
                $import = cpImportPaidToday($pdo, $cfg);
                $flash = $import['found'] . ' pagamento(s) de ' . $import['date'] . ' encontrado(s): '
                    . $import['new'] . ' novo(s), ' . $import['existing'] . ' ja existente(s). '
                    . $import['waiting_mapping'] . ' aguardando conciliacao, ' . $import['pending'] . ' pronto(s) para a fila e '
                    . $import['ignored'] . ' ignorado(s).';
            } else {
                $id = (int)($_POST['mapping_id'] ?? 0);
                $status = in_array($action, ['mapped', 'ignored', 'pending'], true) ? $action : '';
                $code = mb_substr(trim((string)($_POST['simplesvet_product_code'] ?? '')), 0, 120);
                $name = mb_substr(trim((string)($_POST['simplesvet_product_name'] ?? '')), 0, 255);
                if ($id <= 0 || $status === '') throw new RuntimeException('Mapeamento inválido.');
                if ($status === 'mapped' && $code === '') throw new RuntimeException('Informe o código do produto/serviço no SimplesVet.');
                $stmt = $pdo->prepare("
                    UPDATE simplesvet_product_mappings
                       SET mapping_status=?, simplesvet_product_code=?, simplesvet_product_name=?
                     WHERE id=?
                ");
                $stmt->execute([
                    $status,
                    $status === 'mapped' ? $code : null,
                    $status === 'mapped' && $name !== '' ? $name : null,
                    $id,
                ]);
                $released = svSalesRefreshJobs($pdo);
                $flash = 'Conciliação salva. ' . $released . ' tarefa(s) reavaliada(s).';
            }
        } catch (Throwable $e) {
            $flash = $e->getMessage();
            $flashError = true;
        }
    }
}

$status = trim((string)($_GET['status'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));
$where = ['1=1'];
$params = [];
if (in_array($status, ['pending', 'mapped', 'ignored'], true)) { $where[] = 'mapping_status=:status'; $params[':status'] = $status; }
if ($query !== '') {
    $where[] = '(vindi_product_name LIKE :q OR vindi_product_code LIKE :q OR simplesvet_product_code LIKE :q)';
    $params[':q'] = '%' . $query . '%';
}
$whereSql = implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM simplesvet_product_mappings WHERE {$whereSql} ORDER BY FIELD(mapping_status,'pending','mapped','ignored'), vindi_product_name");
$stmt->execute($params);
$mappings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$counts = ['pending' => 0, 'mapped' => 0, 'ignored' => 0];
foreach ($pdo->query('SELECT mapping_status, COUNT(*) total FROM simplesvet_product_mappings GROUP BY mapping_status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($counts[$row['mapping_status']])) $counts[$row['mapping_status']] = (int)$row['total'];
}
$waiting = (int)$pdo->query("SELECT COUNT(*) FROM simplesvet_sale_jobs WHERE status='waiting_mapping'")->fetchColumn();
?>
<section class="cp-page">
<style>
.cp-page{font-family:'Nunito',sans-serif;color:#172033;max-width:1180px;margin:0 auto;padding:4px 24px 40px}.cp-head{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;margin-bottom:22px}.cp-head h1{font-size:28px;margin:0 0 5px}.cp-head p{margin:0;color:#68758b}.cp-sync{border:0;border-radius:11px;padding:11px 16px;background:#38b6ff;color:#fff;font-weight:900;cursor:pointer}.cp-cards{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:13px;margin-bottom:20px}.cp-card{background:#fff;border:1px solid #e6edf5;border-radius:16px;padding:17px;box-shadow:0 5px 18px rgba(28,48,78,.05)}.cp-card span{display:block;color:#7b8799;font-size:11px;font-weight:900;text-transform:uppercase}.cp-card strong{display:block;margin-top:5px;font-size:27px}.cp-card.warn strong{color:#b7791f}.cp-card.ok strong{color:#138a5b}.cp-panel{background:#fff;border:1px solid #e6edf5;border-radius:18px;overflow:hidden;box-shadow:0 5px 18px rgba(28,48,78,.05)}.cp-tools{display:grid;grid-template-columns:1fr 200px auto;gap:10px;padding:16px;border-bottom:1px solid #edf1f6}.cp-tools input,.cp-tools select,.cp-tools button,.cp-map input{height:40px;box-sizing:border-box;border:1px solid #dce4ee;border-radius:9px;padding:0 11px;font:inherit}.cp-tools button{background:#334155;color:#fff;font-weight:900;cursor:pointer}.cp-list{display:grid;gap:12px;padding:16px}.cp-row{display:grid;grid-template-columns:minmax(210px,1.2fr) minmax(280px,1.8fr) auto;gap:18px;align-items:center;border:1px solid #e6edf5;border-radius:14px;padding:16px}.cp-name{font-weight:900}.cp-sub{margin-top:4px;color:#8793a7;font-size:11px}.cp-map{display:grid;grid-template-columns:150px 1fr;gap:8px}.cp-actions{display:flex;gap:7px;flex-wrap:wrap}.cp-actions button{border:0;border-radius:9px;padding:9px 11px;font:inherit;font-size:11px;font-weight:900;cursor:pointer}.cp-save{background:#d1fae5;color:#06603f}.cp-ignore{background:#fee2e2;color:#991b1b}.cp-pending{background:#fef3c7;color:#8a5b00}.cp-badge{display:inline-flex;margin-top:7px;padding:5px 9px;border-radius:999px;font-size:10px;font-weight:900}.cp-badge.pending{background:#fef3c7;color:#8a5b00}.cp-badge.mapped{background:#d1fae5;color:#06603f}.cp-badge.ignored{background:#e5e7eb;color:#475467}.cp-flash{margin-bottom:15px;padding:12px 15px;border-radius:11px;background:#e8f8ef;color:#087f3f;font-weight:800}.cp-flash.error{background:#fee2e2;color:#991b1b}.cp-empty{padding:42px;text-align:center;color:#68758b}@media(max-width:850px){.cp-page{padding-left:10px;padding-right:10px}.cp-head{align-items:flex-start;flex-direction:column}.cp-cards{grid-template-columns:1fr 1fr}.cp-row{grid-template-columns:1fr}.cp-tools,.cp-map{grid-template-columns:1fr}}
.cp-head-actions{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end}.cp-import{background:#138a5b}@media(max-width:850px){.cp-head-actions{justify-content:flex-start}}
</style>
<div class="cp-head"><div><h1>Conciliação de planos</h1><p>Vincule cada produto da Vindi ao código correspondente no SimplesVet ou marque como “Não usar”.</p></div><div class="cp-head-actions"><form method="post"><input type="hidden" name="csrf" value="<?=cph(authCsrfToken())?>"><button class="cp-sync" name="mapping_action" value="sync_vindi"><i class="fa-solid fa-cloud-arrow-down"></i> Sincronizar Vindi</button></form><form method="post"><input type="hidden" name="csrf" value="<?=cph(authCsrfToken())?>"><button class="cp-sync cp-import" name="mapping_action" value="import_paid_today"><i class="fa-solid fa-file-import"></i> Importar pagos de hoje</button></form></div></div>
<?php if ($flash !== ''): ?><div class="cp-flash <?=$flashError ? 'error' : ''?>"><?=cph($flash)?></div><?php endif; ?>
<div class="cp-cards"><div class="cp-card warn"><span>Pendentes</span><strong><?=cph($counts['pending'])?></strong></div><div class="cp-card ok"><span>Vinculados</span><strong><?=cph($counts['mapped'])?></strong></div><div class="cp-card"><span>Não usar</span><strong><?=cph($counts['ignored'])?></strong></div><div class="cp-card warn"><span>Pagamentos aguardando</span><strong><?=cph($waiting)?></strong></div></div>
<div class="cp-panel"><form class="cp-tools" method="get"><input type="hidden" name="pagina" value="conciliacao_planos"><input name="q" value="<?=cph($query)?>" placeholder="Buscar produto ou código"><select name="status"><option value="">Todos</option><option value="pending" <?=$status==='pending'?'selected':''?>>Pendentes</option><option value="mapped" <?=$status==='mapped'?'selected':''?>>Vinculados</option><option value="ignored" <?=$status==='ignored'?'selected':''?>>Não usar</option></select><button>Filtrar</button></form>
<?php if (!$mappings): ?><div class="cp-empty">Clique em “Sincronizar Vindi” para carregar os produtos.</div><?php else: ?><div class="cp-list"><?php foreach ($mappings as $mapping): ?><form class="cp-row" method="post"><input type="hidden" name="csrf" value="<?=cph(authCsrfToken())?>"><input type="hidden" name="mapping_id" value="<?=cph($mapping['id'])?>"><div><div class="cp-name"><?=cph($mapping['vindi_product_name'])?></div><div class="cp-sub">Vindi: <?=cph($mapping['vindi_product_code'] ?: '#' . ($mapping['vindi_product_id'] ?: '-'))?> · visto <?=cph($mapping['seen_count'])?> vez(es)</div><span class="cp-badge <?=cph($mapping['mapping_status'])?>"><?=cph(['pending'=>'Pendente','mapped'=>'Vinculado','ignored'=>'Não usar'][$mapping['mapping_status']] ?? $mapping['mapping_status'])?></span></div><div class="cp-map"><input name="simplesvet_product_code" value="<?=cph($mapping['simplesvet_product_code'])?>" placeholder="Código no SimplesVet"><input name="simplesvet_product_name" value="<?=cph($mapping['simplesvet_product_name'])?>" placeholder="Nome no SimplesVet (opcional)"></div><div class="cp-actions"><button class="cp-save" name="mapping_action" value="mapped">Vincular</button><button class="cp-ignore" name="mapping_action" value="ignored">Não usar</button><button class="cp-pending" name="mapping_action" value="pending">Deixar pendente</button></div></form><?php endforeach; ?></div><?php endif; ?></div>
</section>
