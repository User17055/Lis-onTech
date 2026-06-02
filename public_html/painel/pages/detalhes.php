<?php
// ============================================================
// pages/detalhes.php  (ROTA DO LIS'ON)
// Abre em: /painel/index.php?pagina=detalhes&id=101
// Busca em: /painel/api/run_detail.php?run_id=101
// ============================================================

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!authIsLoggedIn()) { http_response_code(403); exit('Sem login'); }

$backHref = '/painel/index.php?pagina=index';
$backRaw = trim((string) ($_GET['back'] ?? ''));
if ($backRaw !== '') {
    $parts = parse_url($backRaw);
    $currentHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $backHost = (string) ($parts['host'] ?? '');
    $backPath = (string) ($parts['path'] ?? '');

    if (($backHost === '' || strcasecmp($backHost, $currentHost) === 0)
        && in_array($backPath, ['/painel/index.php', '/painel/'], true)
    ) {
        $backQuery = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $candidate = $backPath . $backQuery;
        if (!str_contains($candidate, 'pagina=detalhes')) {
            $backHref = $candidate;
        }
    }
}

if (!isset($_GET['id']) || trim((string) $_GET['id']) === '') {
    echo "<h3 style='font-family:Poppins'>
          Erro: Run ID obrigatório!
          <a href='" . htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') . "'>Voltar</a>
        </h3>";
    exit;
}

$run_id = (string) $_GET['id'];
?>

<!-- Prism (Syntax Highlight) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-json.min.js"></script>

<style>
    /* ✅ Isolado: não mexe na sidebar/header */
    .det-wrap {
        font-family: 'Nunito', sans-serif;
        max-width: 1100px;
        margin: 10px auto;
        padding: 0 12px;
        color: #0f172a;
    }

    /* Topo */
    .det-top {
        background: rgba(255, 255, 255, .92);
        border: 2px solid #eef2f6;
        border-radius: 18px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        backdrop-filter: blur(4px);
    }

    .det-back {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #f4f7fa;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #0f172a;
        transition: .2s;
        flex: 0 0 auto;
        border: 1px solid rgba(15, 23, 42, .06);
    }

    .det-back:hover {
        background: #e2e8f0;
        transform: translateX(-3px);
    }

    .det-top-actions {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 0 0 auto;
    }

    .det-delete {
        height: 42px;
        border: 1px solid #fecaca;
        border-radius: 14px;
        background: #fff;
        color: #991b1b;
        padding: 0 14px;
        display: none;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-weight: 1000;
        font-family: 'Nunito', sans-serif;
        transition: .2s;
    }

    .det-delete:hover {
        background: #fee2e2;
        border-color: #fee2e2;
    }

    .det-delete:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .det-head {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .det-title {
        font-weight: 1000;
        font-size: 18px;
        color: #0f172a;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .det-sub {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        font-weight: 900;
        color: #64748b;
        font-size: 13px;
    }

    .det-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid #eef2f6;
        background: #fff;
        color: #334155;
        font-weight: 1000;
    }

    .det-chip i {
        color: #38b6ff;
    }

    /* Cards */
    .det-card {
        margin-top: 18px;
        background: #fff;
        border: 2px solid #eef2f6;
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 8px 18px rgba(15, 23, 42, .05);
    }

    .det-card-title {
        font-weight: 1000;
        color: #38b6ff;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }

    .det-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    @media(max-width:860px) {
        .det-grid {
            grid-template-columns: 1fr;
        }
    }

    .det-box {
        border: 1px solid #eef2f6;
        border-radius: 16px;
        padding: 12px 14px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    .det-label {
        font-size: 12px;
        font-weight: 1000;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #64748b;
    }

    .det-value {
        margin-top: 6px;
        font-weight: 1000;
        font-size: 14px;
        color: #0f172a;
        word-break: break-word;
    }

    .det-muted {
        color: #64748b;
        font-weight: 900;
    }

    /* Status pill */
    .det-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        font-weight: 1100;
        font-size: 12px;
        text-transform: uppercase;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .det-pill.ok {
        background: #d1fae5;
        color: #065f46;
        border-color: #a7f3d0;
    }

    .det-pill.info {
        background: #dbeafe;
        color: #1e40af;
        border-color: #bfdbfe;
    }

    .det-pill.warn {
        background: #ffedd5;
        color: #9a3412;
        border-color: #fed7aa;
    }

    .det-pill.danger {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .det-link {
        color: #38b6ff;
        font-weight: 1100;
        text-decoration: none;
    }

    .det-link:hover {
        text-decoration: underline;
    }

    /* Abas */
    .det-tabs {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 6px;
    }

    .det-tab {
        border: 2px solid #eef2f6;
        background: #fff;
        border-radius: 999px;
        padding: 8px 14px;
        cursor: pointer;
        font-weight: 1100;
        color: #64748b;
        transition: .15s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        user-select: none;
    }

    .det-tab.active {
        border-color: #cfe0ff;
        color: #38b6ff;
        background: #f8fbff;
    }

    .det-tab:hover {
        transform: translateY(-1px);
    }

    .det-searchbar {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 12px;
        border: 2px solid #eef2f6;
        border-radius: 16px;
        padding: 10px 12px;
        background: #fff;
    }

    .det-searchbar input {
        border: none;
        outline: none;
        width: 100%;
        font-size: 14px;
        font-family: 'Nunito', sans-serif;
        font-weight: 900;
    }

    /* Fields */
    .det-fields {
        margin-top: 12px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    @media(max-width:780px) {
        .det-fields {
            grid-template-columns: 1fr;
        }
    }

    .det-field {
        border: 1px solid #eef2f6;
        border-radius: 16px;
        padding: 12px 12px;
        background: #fbfdff;
    }

    .det-field .k {
        font-size: 12px;
        color: #64748b;
        font-weight: 1100;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .det-field .v {
        margin-top: 6px;
        font-weight: 900;
        color: #0f172a;
        word-break: break-word;
        white-space: pre-wrap;
    }

    /* Logs */
    .det-logbox {
        margin-top: 12px;
        background: #0b1220;
        color: #cbd5e1;
        border: 1px solid #1f2a44;
        border-radius: 16px;
        padding: 14px;
        max-height: 360px;
        overflow: auto;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 12.5px;
    }

    .det-logline {
        padding: 10px 0;
        border-bottom: 1px solid #1f2a44;
        display: grid;
        grid-template-columns: 140px 90px 160px 1fr;
        gap: 12px;
        align-items: start;
    }

    .det-logline:last-child {
        border-bottom: none;
    }

    .det-ltime {
        color: #94a3b8;
        font-weight: 800;
    }

    .det-llevel {
        font-weight: 1100;
        text-transform: uppercase;
    }

    .det-lstep {
        color: #93c5fd;
        font-weight: 1000;
    }

    .det-lmsg {
        color: #e2e8f0;
        font-weight: 800;
        white-space: pre-wrap;
        word-break: break-word;
    }

    /* Prism */
    pre[class*="language-"] {
        margin: 0;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        font-size: 12px;
        overflow: auto;
        max-height: 520px;
    }

    /* Copy JSON discreto */
    .det-code-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
        margin-bottom: 8px;
    }

    .det-copy {
        border: 2px solid #eef2f6;
        background: #fff;
        border-radius: 14px;
        padding: 8px 12px;
        cursor: pointer;
        font-weight: 1100;
        transition: .15s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        user-select: none;
    }

    .det-copy:hover {
        transform: translateY(-1px);
        border-color: #cfe0ff;
        color: #38b6ff;
    }

    /* Error card */
    .det-error {
        background: #fee2e2;
        border: 1px solid #fecaca;
        border-radius: 16px;
        padding: 14px;
    }

    .det-error .h {
        font-weight: 1100;
        color: #991b1b;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .det-error .m {
        margin-top: 6px;
        color: #7f1d1d;
        font-weight: 900;
        white-space: pre-wrap;
        word-break: break-word;
    }

    /* Loader skeleton */
    .det-skel {
        height: 14px;
        border-radius: 999px;
        background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 40%, #f1f5f9 80%);
        background-size: 200% 100%;
        animation: detshimmer 1.2s infinite;
    }

    @keyframes detshimmer {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }

    /* ====== ADIÇÕES (Reenvio WhatsApp) - NÃO MEXE NO CSS EXISTENTE ====== */
    .det-actions {
        margin-top: 14px;
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
    }

    .det-actions-group {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: center;
    }

    .det-actions-right {
        margin-left: auto;
        flex: 0 0 auto;
    }

    .det-btn {
        border: 2px solid #eef2f6;
        border-radius: 14px;
        padding: 11px 18px;
        cursor: pointer;
        font-weight: 1100;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: .15s;
        user-select: none;
    }

    .det-btn.primary {
        background: #38b6ff;
        color: #fff;
        border-color: #38b6ff;
    }

    .det-btn.primary:hover {
        transform: translateY(-1px);
        filter: brightness(.98);
    }

    .det-btn.secondary {
        background: #fff;
        color: #0f172a;
    }

    .det-btn.secondary:hover {
        transform: translateY(-1px);
        border-color: #cfe0ff;
        color: #38b6ff;
    }

    .det-btn.chat {
        background: #0ea5e9;
        color: #fff;
        border-color: transparent;
    }

    .det-btn.chat:hover {
        background: #0ea5e9;
        border-color: transparent;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: inset 0 0 0 999px rgba(255, 255, 255, .12);
    }

    .det-btn.profile {
        text-decoration: none;
        white-space: nowrap;
    }

    .det-modal {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .55);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        z-index: 9999;
    }

    .det-modal.show {
        display: flex;
    }

    .det-modal-card {
        width: min(560px, 100%);
        background: #fff;
        border: 2px solid #eef2f6;
        border-radius: 18px;
        box-shadow: 0 22px 60px rgba(0, 0, 0, .18);
        padding: 16px;
    }

    .det-modal-title {
        font-weight: 1200;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .det-modal-sub {
        margin-top: 6px;
        color: #64748b;
        font-weight: 900;
    }

    .det-modal-row {
        margin-top: 12px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .det-modal-row input {
        flex: 1 1 260px;
        border: 2px solid #eef2f6;
        border-radius: 14px;
        padding: 10px 12px;
        outline: none;
        font-weight: 1000;
        font-family: 'Nunito', sans-serif;
    }

    .det-modal-row input:focus {
        border-color: #cfe0ff;
    }

    .det-modal-actions {
        margin-top: 14px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .det-toast {
        position: fixed;
        right: 16px;
        bottom: 16px;
        background: #0b1220;
        color: #e2e8f0;
        padding: 10px 12px;
        border-radius: 14px;
        border: 1px solid #1f2a44;
        box-shadow: 0 18px 40px rgba(0, 0, 0, .20);
        font-weight: 1000;
        display: none;
        z-index: 10000;
    }

    .det-toast.show {
        display: block;
    }

    /* ====== ADIÇÕES (Motivo do reenvio) ====== */
    .det-modal-row textarea {
        flex: 1 1 260px;
        border: 2px solid #eef2f6;
        border-radius: 14px;
        padding: 10px 12px;
        outline: none;
        font-weight: 1000;
        font-family: 'Nunito', sans-serif;
        resize: vertical;
    }

    .det-modal-row textarea:focus {
        border-color: #cfe0ff;
    }

    #resendPhone:disabled {
        background: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
    }
</style>

<div class="det-wrap">

    <div class="det-top">
        <a class="det-back" href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-arrow-left"></i>
        </a>

        <div class="det-head">
            <div class="det-title">
                <i class="fa-regular fa-file-code" style="color:#38b6ff"></i>
                Execução #<?= htmlspecialchars($run_id, ENT_QUOTES, 'UTF-8'); ?>
                <span id="titleStatus" style="margin-left:6px;"></span>
            </div>

            <div class="det-sub">
                <span class="det-chip">
                    <i class="fa-solid fa-hashtag"></i>
                    ID: <?= htmlspecialchars($run_id, ENT_QUOTES, 'UTF-8'); ?>
                </span>

                <span class="det-chip" id="chipCliente">
                    <i class="fa-solid fa-building"></i> <span class="det-skel"
                        style="width:160px;display:inline-block;"></span>
                </span>

                <span class="det-chip" id="chipData">
                    <i class="fa-regular fa-clock"></i> <span class="det-skel"
                        style="width:120px;display:inline-block;"></span>
                </span>
            </div>
        </div>

        <div class="det-top-actions">
            <button type="button" class="det-delete" id="btnDeleteRun">
                <i class="fa-solid fa-trash"></i> Excluir
            </button>
        </div>
    </div>

    <div id="loadingDet" class="det-card" style="text-align:center;color:#64748b;font-weight:1000;">
        <i class="fa-solid fa-spinner fa-spin" style="font-size:26px;color:#38b6ff;"></i><br><br>
        Carregando informações...
    </div>

    <div id="detOk" style="display:none;">

        <!-- RESUMO -->
        <div class="det-card">
            <div class="det-card-title">
                <i class="fa-solid fa-circle-info"></i> Resumo
            </div>

            <div class="det-actions">
                <div class="det-actions-group">
                    <button id="btnOpenChat" class="det-btn chat" style="display:none;">
                        <i class="fa-solid fa-comments"></i> Ir para o chat
                    </button>

                    <button id="btnResendWhats" class="det-btn primary" style="display:none;">
                        <i class="fa-solid fa-paper-plane"></i> Reenviar WhatsApp
                    </button>

                    <!-- ✅ NOVO: Reenviar a mesma mensagem (quando já foi enviado / sucesso) -->
                    <button id="btnResendSame" class="det-btn secondary" style="display:none;">
                        <i class="fa-solid fa-repeat"></i> Reenviar a mesma mensagem
                    </button>

                    <button id="btnRetryVindi" class="det-btn secondary" style="display:none;">
                        <i class="fa-solid fa-rotate-right"></i> Tentar novamente (puxar da Vindi)
                    </button>
                </div>

                <div class="det-actions-group det-actions-right">
                    <a id="btnVindiProfile" class="det-btn secondary profile" href="#" target="_blank" rel="noopener" style="display:none;">
                        <i class="fa-solid fa-user"></i> Perfil
                    </a>
                </div>
            </div>

            <div class="det-muted" id="resendHint" style="margin-top:8px; display:none;">
                Use quando falhar por falta de número ou número incorreto.
            </div>

            <div class="det-grid">
                <div class="det-box">
                    <div class="det-label">Cliente</div>
                    <div class="det-value" id="detCliente">--</div>
                </div>

                <div class="det-box">
                    <div class="det-label">Status</div>
                    <div class="det-value" id="detStatus">--</div>
                </div>

                <div class="det-box">
                    <div class="det-label">Event Type</div>
                    <div class="det-value" id="detEventType">--</div>
                </div>

                <div class="det-box">
                    <div class="det-label">Bill</div>
                    <div class="det-value" id="detBill">--</div>
                </div>
            </div>

            <div style="margin-top:12px;" class="det-muted">
                Criado: <span id="detCreated">--</span> • Atualizado: <span id="detUpdated">--</span>
            </div>
        </div>

        <!-- ERRO -->
        <div class="det-card" id="cardErroDet" style="display:none;">
            <div class="det-card-title">
                <i class="fa-solid fa-triangle-exclamation"></i> Erro
            </div>

            <div id="boxErroDet"></div>
        </div>

        <!-- RELATÓRIO -->
        <div class="det-card">
            <div class="det-card-title">
                <i class="fa-solid fa-layer-group"></i> Relatório completo
            </div>

            <div class="det-tabs">
                <button class="det-tab active" data-tab="input">
                    <i class="fa-solid fa-right-to-bracket"></i> Entrada
                </button>
                <button class="det-tab" data-tab="output">
                    <i class="fa-solid fa-right-from-bracket"></i> Saída
                </button>
                <button class="det-tab" data-tab="logs">
                    <i class="fa-solid fa-terminal"></i> Logs
                </button>
                <button class="det-tab" data-tab="json">
                    <i class="fa-solid fa-code"></i> JSON
                </button>
            </div>

            <div class="det-searchbar">
                <i class="fa-solid fa-magnifying-glass" style="color:#64748b"></i>
                <input id="detSearch" placeholder="Buscar (ex.: phone, nome, status, step...)" />
            </div>

            <!-- INPUT -->
            <div id="tab_input" class="det-pane">
                <div class="det-muted" style="margin-top:12px;">Campos de entrada (o que chegou no fluxo).</div>
                <div id="detInputFields" class="det-fields"></div>
            </div>

            <!-- OUTPUT -->
            <div id="tab_output" class="det-pane" style="display:none;">
                <div class="det-muted" style="margin-top:12px;">Campos de saída (resultados do processamento).</div>
                <div id="detOutputFields" class="det-fields"></div>
            </div>

            <!-- LOGS -->
            <div id="tab_logs" class="det-pane" style="display:none;">
                <div class="det-muted" style="margin-top:12px;">Linha do tempo por etapa.</div>
                <div class="det-logbox" id="detLogs"></div>
            </div>

            <!-- JSON -->
            <div id="tab_json" class="det-pane" style="display:none;">
                <div class="det-muted" style="margin-top:12px;">Run completa (técnico).</div>

                <div class="det-code-top">
                    <div class="det-muted">Visualização em JSON</div>
                    <button class="det-copy" id="detCopyJson">
                        <i class="fa-regular fa-copy"></i> Copiar JSON
                    </button>
                </div>

                <pre class="language-json"><code id="detJsonBox" class="language-json"></code></pre>
            </div>

        </div>

    </div>

    <!-- ✅ Modal Reenvio (Manual + Mesma Mensagem) -->
    <div class="det-modal" id="resendModal">
        <div class="det-modal-card">
            <div class="det-modal-title" id="resendModalTitle">
                <i class="fa-solid fa-paper-plane" style="color:#38b6ff;"></i>
                Reenviar WhatsApp
            </div>
            <div class="det-modal-sub" id="resendModalSub"></div>

            <div class="det-modal-row">
                <input id="resendPhone" type="tel" inputmode="numeric" placeholder="Digite o numero" />
            </div>

            <div class="det-modal-row">
                <textarea id="resendReason" rows="3"
                    placeholder="Motivo do reenvio (ex.: cliente disse que não viu)"></textarea>
            </div>

            <div class="det-modal-actions">
                <button class="det-btn secondary" id="btnResendCancel">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button class="det-btn primary" id="btnResendConfirm">
                    <i class="fa-solid fa-paper-plane"></i> Enviar agora
                </button>
            </div>
        </div>
    </div>

    <div class="det-toast" id="detToast"></div>

</div>

<script>
    (() => {
        const runId = "<?= htmlspecialchars($run_id, ENT_QUOTES, 'UTF-8'); ?>";
        const backHref = <?= json_encode($backHref, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

        const stepNameMap = {
            validate: 'Validação de dados',
            facebook_send: 'Envio ao Facebook',
            facebook: 'Facebook',
            webhook: 'Webhook',
            vindi: 'Vindi',
            build_payload: 'Montagem do payload',
            whatsapp_send: 'Envio WhatsApp',
        };

        function esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, m => ({
                "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
            }[m]));
        }

        function toast(msg) {
            const t = document.getElementById("detToast");
            if (!t) return alert(msg);
            t.textContent = msg;
            t.classList.add("show");
            setTimeout(() => t.classList.remove("show"), 1800);
        }

        async function fetchApiJson(url, options = {}) {
            const resp = await fetch(url, { credentials: "same-origin", ...options });
            const text = await resp.text();
            let json = null;
            try { json = JSON.parse(text); } catch (e) {}

            if (resp.status === 401) {
                location.href = "/painel/";
                throw new Error("Login obrigatorio");
            }

            if (!resp.ok || !json || json.ok === false) {
                throw new Error(json?.error || text.slice(0, 220) || "Falha na requisicao");
            }

            return json;
        }

        function pillForStatus(status) {
            const st = String(status || '').toLowerCase();
            if (st === 'paid') {
                return `<span class="det-pill info"><i class="fa-solid fa-circle-check"></i> J&aacute; pago</span>`;
            }
            if (st === 'processed' || st === 'success' || st === 'ok') {
                return `<span class="det-pill ok"><i class="fa-solid fa-check"></i> ${esc(st)}</span>`;
            }
            if (st === 'processing' || st === 'running') {
                return `<span class="det-pill info"><i class="fa-solid fa-spinner fa-spin"></i> ${esc(st)}</span>`;
            }
            if (st === 'warning') {
                return `<span class="det-pill.warn"><i class="fa-solid fa-triangle-exclamation"></i> ${esc(st)}</span>`;
            }
            if (st === 'error' || st === 'failed' || st === 'canceled') {
                return `<span class="det-pill danger"><i class="fa-solid fa-xmark"></i> ${esc(st)}</span>`;
            }
            return `<span class="det-pill info"><i class="fa-regular fa-circle"></i> ${esc(st || 'unknown')}</span>`;
        }

        function safeJsonParse(v) {
            if (!v) return null;
            if (typeof v === "object") return v;
            try { return JSON.parse(v); } catch { return null; }
        }

        function flatten(obj, prefix = '') {
            const out = [];
            const isObj = (x) => x && typeof x === 'object' && !Array.isArray(x);

            if (obj === null || obj === undefined) return out;

            if (!isObj(obj) && !Array.isArray(obj)) {
                out.push({ key: prefix || "value", value: String(obj) });
                return out;
            }

            if (Array.isArray(obj)) {
                out.push({ key: prefix || "array", value: JSON.stringify(obj, null, 2) });
                return out;
            }

            for (const [k, v] of Object.entries(obj)) {
                const nextKey = prefix ? `${prefix}.${k}` : k;
                if (v === null || v === undefined) out.push({ key: nextKey, value: "" });
                else if (Array.isArray(v)) out.push({ key: nextKey, value: JSON.stringify(v, null, 2) });
                else if (typeof v === "object") out.push(...flatten(v, nextKey));
                else out.push({ key: nextKey, value: String(v) });
            }
            return out;
        }

        function renderFields(containerId, items, q) {
            const el = document.getElementById(containerId);
            const query = (q || '').trim().toLowerCase();

            const filtered = !query ? items : items.filter(it =>
                it.key.toLowerCase().includes(query) || String(it.value || '').toLowerCase().includes(query)
            );

            if (!filtered.length) {
                el.innerHTML = `<div class="det-muted" style="margin-top:12px;">Nada encontrado.</div>`;
                return;
            }

            el.innerHTML = filtered.map(it => `
      <div class="det-field">
        <div class="k">${esc(it.key)}</div>
        <div class="v">${esc(it.value)}</div>
      </div>
    `).join('');
        }

        function renderLogs(logs, q) {
            const el = document.getElementById("detLogs");
            const query = (q || '').trim().toLowerCase();

            const filtered = !query ? logs : logs.filter(l => {
                const hay = [
                    l.created_at, l.level, l.step, l.message,
                    l.context ? JSON.stringify(l.context) : ''
                ].join(' ').toLowerCase();
                return hay.includes(query);
            });

            if (!filtered.length) {
                el.innerHTML = `<div class="det-muted">Nenhum log encontrado.</div>`;
                return;
            }

            el.innerHTML = filtered.map(l => {
                const lvl = String(l.level || '').toUpperCase();
                const stepKey = String(l.step || '');
                const stepLabel = stepKey ? (stepNameMap[stepKey] || stepKey) : '--';

                return `
        <div class="det-logline">
          <div class="det-ltime">${esc(l.created_at || '')}</div>
          <div class="det-llevel">${esc(lvl)}</div>
          <div class="det-lstep">${esc(stepLabel)}</div>
          <div class="det-lmsg">${esc(l.message || '')}</div>
        </div>
      `;
            }).join('');
        }

        function pickInputOutput(run, logs) {
            const meta = safeJsonParse(run.meta_http);

            let input = null;
            let output = null;

            if (meta && typeof meta === "object") {
                input = meta.input ?? meta.request ?? meta.payload ?? meta.in ?? null;
                output = meta.output ?? meta.response ?? meta.out ?? null;
            }

            if (!input) {
                const err = (logs || []).find(l => String(l.level || '').toLowerCase() === "error" && l.context);
                input = err?.context?.received ?? err?.context?.request ?? null;
            }
            if (!output) {
                const err = (logs || []).find(l => String(l.level || '').toLowerCase() === "error" && l.context);
                output = err?.context?.response ?? null;
            }

            return { input, output, meta };
        }

        function showErrorCard(run, logs) {
            const card = document.getElementById("cardErroDet");
            const box = document.getElementById("boxErroDet");

            const errorLog = (logs || []).find(l => String(l.level || '').toLowerCase() === "error") || null;
            const msg = run.error_message || errorLog?.message || '';

            if (!msg) {
                card.style.display = "none";
                return;
            }

            const ctx = errorLog?.context || {};
            const ctxStr = JSON.stringify(ctx || {}, null, 2);

            box.innerHTML = `
      <div class="det-error">
        <div class="h"><i class="fa-solid fa-bug"></i> Falha detectada</div>
        <div class="m">${esc(msg)}</div>

        <details style="margin-top:10px;">
          <summary style="cursor:pointer;font-weight:1000;">Ver contexto técnico</summary>
          <div style="margin-top:10px;">
            <pre class="language-json"><code id="errCtxCode" class="language-json"></code></pre>
          </div>
        </details>
      </div>
    `;

            const codeEl = document.getElementById("errCtxCode");
            codeEl.textContent = ctxStr;
            if (window.Prism) Prism.highlightElement(codeEl);

            card.style.display = "block";
        }

        function canDeleteRun(run) {
            const st = String(run?.status || "").toLowerCase();
            const sent = Number(run?.step_whatsapp || 0) === 1;
            if (sent) return false;
            return ["not_sent", "error", "failed", "processing", ""].includes(st);
        }

        function bindDeleteRun(run) {
            const btn = document.getElementById("btnDeleteRun");
            if (!btn) return;

            if (!canDeleteRun(run)) {
                btn.style.display = "none";
                return;
            }

            btn.style.display = "inline-flex";
            btn.onclick = async () => {
                const ok = confirm("Excluir este registro pendente/falhado? Isso remove a execucao da lista, mas nao apaga mensagem ja entregue no WhatsApp.");
                if (!ok) return;

                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Excluindo...`;

                try {
                    await fetchApiJson("/painel/api/run_delete.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ run_id: runId })
                    });
                    const redirect = new URL(backHref || "/painel/index.php?pagina=index", location.origin);
                    redirect.searchParams.set("deleted_message", "1");
                    location.href = redirect.pathname + redirect.search + redirect.hash;
                } catch (e) {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    toast(e.message || "Falha ao excluir registro", "error");
                }
            };
        }

        function setupTabs() {
            const tabs = document.querySelectorAll(".det-tab");
            tabs.forEach(btn => {
                btn.addEventListener("click", () => {
                    tabs.forEach(b => b.classList.remove("active"));
                    btn.classList.add("active");

                    const target = btn.dataset.tab;
                    document.querySelectorAll(".det-pane").forEach(p => p.style.display = "none");
                    document.getElementById("tab_" + target).style.display = "block";
                });
            });
        }

        // ===== Reenvio WhatsApp (Manual + Mesma Mensagem) =====
        let resendMode = "manual"; // manual | same | vindi

        function closeResendModal() {
            document.getElementById("resendModal").classList.remove("show");
        }

        function openResendModal(opts = {}) {
            const { mode = "manual", phone = "", lockPhone = false } = opts;
            resendMode = mode;

            const modal = document.getElementById("resendModal");
            const inpPhone = document.getElementById("resendPhone");
            const inpReason = document.getElementById("resendReason");
            const title = document.getElementById("resendModalTitle");
            const sub = document.getElementById("resendModalSub");

            if (mode === "same") {
                title.innerHTML = `<i class="fa-solid fa-repeat" style="color:#38b6ff;"></i> Reenviar a mesma mensagem`;
                sub.textContent = "Use quando o cliente disser que não viu. Motivo é obrigatório (fica registrado).";
            } else if (mode === "vindi") {
                title.innerHTML = `<i class="fa-solid fa-rotate-right" style="color:#38b6ff;"></i> Reenviar (Vindi)`;
                sub.textContent = "Telefone puxado da Vindi e reenvio automático.";
            } else {
                title.innerHTML = `<i class="fa-solid fa-paper-plane" style="color:#38b6ff;"></i> Reenviar WhatsApp manualmente`;
                sub.textContent = "Use quando falhar por falta de número ou número incorreto.";
            }

            inpPhone.value = phone || "";
            inpPhone.disabled = !!lockPhone;
            inpReason.value = "";

            modal.classList.add("show");
            setTimeout(() => (lockPhone ? inpReason.focus() : inpPhone.focus()), 50);
        }

        // ✅ Normalização mais robusta
        function normalizeBrPhone(raw) {
            let digits = String(raw || "").replace(/\D+/g, "");
            if (!digits) return "";

            digits = digits.replace(/^00+/, ""); // 0055...
            while (digits.startsWith("0") && digits.length > 13) {
                digits = digits.slice(1);
            }

            if (digits.startsWith("55") && (digits.length === 12 || digits.length === 13)) return digits;
            if (digits.length === 10 || digits.length === 11) return "55" + digits;

            if (digits.includes("55") && digits.length > 13) {
                const idx = digits.indexOf("55");
                const tail = digits.slice(idx);
                if (tail.startsWith("55") && (tail.length === 12 || tail.length === 13)) return tail;
                if (tail.startsWith("55") && tail.length > 13) return tail.slice(0, 13);
            }

            if ((digits.length === 12 || digits.length === 13) && !digits.startsWith("55")) {
                return "55" + digits.slice(-11);
            }

            return digits;
        }

        function initResendModal() {
            document.getElementById("btnResendCancel").onclick = closeResendModal;

            document.getElementById("resendModal").onclick = (e) => {
                if (e.target && e.target.id === "resendModal") closeResendModal();
            };

            document.addEventListener("keydown", (e) => {
                if (e.key === "Escape") closeResendModal();
            });

            document.getElementById("btnResendConfirm").onclick = async () => {
                const rawPhone = document.getElementById("resendPhone").value || "";
                const reason = (document.getElementById("resendReason").value || "").trim();

                if (resendMode === "same" && reason.length < 3) {
                    toast("Informe o motivo do reenvio.");
                    return;
                }

                const norm = normalizeBrPhone(rawPhone);
                const digitsOnly = norm.replace(/\D+/g, "");

                if (!(digitsOnly.length === 12 || digitsOnly.length === 13) || !digitsOnly.startsWith("55")) {
                    toast("Número inválido. Ex.: 11999998888");
                    return;
                }

                const btn = document.getElementById("btnResendConfirm");

                try {
                    btn.disabled = true;
                    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Enviando...`;

                    await fetchApiJson(`/painel/api/run_resend_whatsapp.php`, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            run_id: runId,
                            phone: digitsOnly,
                            mode: resendMode,
                            reason: reason
                        })
                    });

                    toast(resendMode === "same" ? "Reenvio (mesma mensagem) disparado" : "Reenvio disparado");
                    closeResendModal();
                    setTimeout(() => location.reload(), 700);

                } catch (e) {
                    toast("Erro: " + (e.message || "não foi possível reenviar"));
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="fa-solid fa-paper-plane"></i> Enviar agora`;
                }
            };
        }

        // ===== Extração de telefone (AGORA pega do run.phone e whatsapp_request.to) =====
        function looksLikePhone(v) {
            const s = String(v || "").trim();
            if (!s) return false;
            const d = s.replace(/\D+/g, "");
            return d.length >= 10 && d.length <= 15;
        }

        function extractPhoneFromItems(items) {
            const patterns = [
                "phone", "telefone", "cel", "celular", "whatsapp", "msisdn",
                "mobile", "fone", "numero", "número", "phone_number",
                ".to", " to", "recipient", "wa_id", "destination"
            ];

            let best = "";
            let bestScore = -1;

            for (const it of (items || [])) {
                const k = String(it.key || "").toLowerCase();
                const v = String(it.value || "").trim();
                if (!looksLikePhone(v)) continue;

                let score = 0;
                for (const p of patterns) {
                    if (k.includes(p)) score += 10;
                }
                if (k === "to" || k.endsWith(".to")) score += 15;
                if (k.includes("whatsapp")) score += 10;

                if (score > bestScore) {
                    bestScore = score;
                    best = v;
                }
            }

            if (!best) {
                for (const it of (items || [])) {
                    const v = String(it.value || "").trim();
                    if (looksLikePhone(v)) {
                        best = v;
                        break;
                    }
                }
            }

            return best;
        }

        function extractPhoneFromLogs(logs) {
            // tenta achar 55 + 10/11 dígitos
            for (const l of (logs || [])) {
                const hay = (l?.message || "") + " " + (l?.context ? JSON.stringify(l.context) : "");
                const m = String(hay).match(/55\d{10,11}/);
                if (m && m[0]) return m[0];
            }
            return "";
        }

        function extractPhoneFromRun(run) {
            // 1) run.phone
            if (run?.phone) return String(run.phone);
            if (run?.reminder_phone) return String(run.reminder_phone);

            // 2) run.whatsapp_request (JSON string) => { to: "55..." }
            const req = safeJsonParse(run?.whatsapp_request);
            if (req?.to) return String(req.to);

            // 3) run.whatsapp_response (JSON string) => contacts[0].wa_id / input
            const res = safeJsonParse(run?.whatsapp_response);
            if (res?.contacts?.[0]?.wa_id) return String(res.contacts[0].wa_id);
            if (res?.contacts?.[0]?.input) return String(res.contacts[0].input);

            return "";
        }

        function getBestPhone(run, inputItems, outputItems, logs) {
            const cRun = extractPhoneFromRun(run);
            const cIn = extractPhoneFromItems(inputItems);
            const cOut = extractPhoneFromItems(outputItems);
            const cLog = extractPhoneFromLogs(logs);

            const candidate = cRun || cIn || cOut || cLog || "";
            const norm = normalizeBrPhone(candidate);
            const digits = norm.replace(/\D+/g, "");
            return { candidate, norm, digits };
        }

        function bindOpenChat(run, logs, inputItems, outputItems) {
            const btn = document.getElementById("btnOpenChat");
            if (!btn) return;

            const { digits } = getBestPhone(run, inputItems, outputItems, logs);
            const canOpen = digits && digits.startsWith("55") && (digits.length === 12 || digits.length === 13);
            if (!canOpen) {
                btn.style.display = "none";
                return;
            }

            btn.style.display = "inline-flex";
            btn.onclick = () => {
                const url = `/painel/index.php?pagina=chat&phone=${encodeURIComponent(digits)}`;
                window.LisOnPageLoader?.show();
                window.location.href = url;
            };
        }

        function bindVindiProfile(run) {
            const btn = document.getElementById("btnVindiProfile");
            if (!btn) return;

            const name = String(run?.customer_name || "").trim();
            if (!name) {
                btn.style.display = "none";
                btn.removeAttribute("href");
                return;
            }

            const query = encodeURIComponent(name).replace(/%20/g, "+");
            btn.href = `https://app.vindi.com.br/admin/customers/search?utf8=%E2%9C%93&query=${query}`;
            btn.style.display = "inline-flex";
        }

        // Manual: mostrar apenas quando falhou por problema de telefone
        function shouldShowResend(run, logs, inputItems, outputItems) {
            const stLower = String(run.status || "").toLowerCase();
            const failedStatus = ["not_sent", "error", "failed", "canceled"].includes(stLower);
            if (!failedStatus) return false;

            const { digits } = getBestPhone(run, inputItems, outputItems, logs);
            const candidateInvalid = !digits || !(digits.length === 12 || digits.length === 13) || !digits.startsWith("55");

            const hay = (
                (run.error_message || "") + " " +
                (logs || []).map(l => (l.message || "") + " " + (l.context ? JSON.stringify(l.context) : "")).join(" ")
            ).toLowerCase();

            const mentionsPhone = [
                "sem telefone", "telefone", "celular", "whatsapp", "phone", "msisdn",
                "número", "numero", "invalid", "invál", "inval",
                "recipient", "wa_id", "to="
            ].some(x => hay.includes(x));

            return mentionsPhone || candidateInvalid;
        }

        function bindResend(run, logs, inputItems, outputItems) {
            const btnResend = document.getElementById("btnResendWhats");
            const hintResend = document.getElementById("resendHint");

            const show = shouldShowResend(run, logs, inputItems, outputItems);
            if (!show) {
                btnResend.style.display = "none";
                hintResend.style.display = "none";
                return;
            }

            const { digits } = getBestPhone(run, inputItems, outputItems, logs);

            btnResend.style.display = "inline-flex";
            hintResend.style.display = "block";

            btnResend.onclick = () => openResendModal({
                mode: "manual",
                phone: digits || "",
                lockPhone: false
            });
        }

        // ✅ Reenviar mesma mensagem: aparece em sucesso e usa run.phone/whatsapp_request.to
        function shouldShowResendSame(run) {
            const st = String(run.status || "").toLowerCase();
            return ["processed", "success", "ok"].includes(st);
        }

        function bindResendSame(run, logs, inputItems, outputItems) {
            const btn = document.getElementById("btnResendSame");
            if (!btn) return;

            if (!shouldShowResendSame(run)) {
                btn.style.display = "none";
                return;
            }

            btn.style.display = "inline-flex";

            btn.onclick = () => {
                const { digits } = getBestPhone(run, inputItems, outputItems, logs);

                // ✅ se achou válido, preenche (pode travar se quiser)
                if (digits && digits.startsWith("55") && (digits.length === 12 || digits.length === 13)) {
                    openResendModal({ mode: "same", phone: digits, lockPhone: true });
                } else {
                    // ✅ se não achou, NÃO bloqueia: abre vazio pra digitar
                    toast("Não consegui localizar o telefone automaticamente. Digite abaixo.");
                    openResendModal({ mode: "same", phone: "", lockPhone: false });
                }
            };
        }

        // ===== Retry automático puxando telefone da Vindi =====
        function shouldShowRetryVindi(run) {
            const stLower = String(run.status || "").toLowerCase();
            const failedStatus = ["not_sent", "error", "failed", "canceled"].includes(stLower);
            const hasBill = !!(run.bill_id && String(run.bill_id).trim() !== "");
            return failedStatus && hasBill;
        }

        function bindRetryVindi(run) {
            const btn = document.getElementById("btnRetryVindi");
            if (!btn) return;

            if (!shouldShowRetryVindi(run)) {
                btn.style.display = "none";
                return;
            }

            btn.style.display = "inline-flex";

            btn.onclick = async () => {
                const originalHtml = btn.innerHTML;

                try {
                    btn.disabled = true;
                    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Buscando na Vindi...`;

                    const j1 = await fetchApiJson(`/painel/api/run_get_vindi_phone.php`, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ run_id: runId })
                    });

                    const phoneDigits = String(j1.phone || "").replace(/\D+/g, "");
                    if (!phoneDigits) throw new Error("Telefone não encontrado na Vindi");

                    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Reenviando...`;

                    await fetchApiJson(`/painel/api/run_resend_whatsapp.php`, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ run_id: runId, phone: phoneDigits, mode: "vindi", reason: "" })
                    });

                    toast("Reenvio disparado (Vindi)");
                    setTimeout(() => location.reload(), 700);

                } catch (e) {
                    toast("Erro: " + (e.message || "falha no retry"));
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            };
        }

        async function load() {
            const loading = document.getElementById("loadingDet");
            const ok = document.getElementById("detOk");

            try {
                initResendModal();

                const j = await fetchApiJson(`/painel/api/run_detail.php?run_id=${encodeURIComponent(runId)}`, { cache: "no-store" });

                const run = j.run || {};
                const logs = j.logs || [];

                document.getElementById("chipCliente").innerHTML =
                    `<i class="fa-solid fa-building"></i> ${esc(run.customer_name || "Cliente --")}`;

                const createdShort = run.created_at ? String(run.created_at).slice(0, 16) : "--";
                document.getElementById("chipData").innerHTML =
                    `<i class="fa-regular fa-clock"></i> ${esc(createdShort)}`;

                document.getElementById("titleStatus").innerHTML = pillForStatus(run.status);

                document.getElementById("detCliente").innerText = run.customer_name || "--";
                document.getElementById("detStatus").innerHTML = pillForStatus(run.status);
                document.getElementById("detEventType").innerText = run.event_type || "--";
                document.getElementById("detCreated").innerText = run.created_at || "--";
                document.getElementById("detUpdated").innerText = run.updated_at || "--";

                if (run.bill_url) {
                    document.getElementById("detBill").innerHTML =
                        `<a class="det-link" href="${esc(run.bill_url)}" target="_blank" rel="noopener">
            Abrir Bill #${esc(run.bill_id || '')}
          </a>`;
                } else {
                    document.getElementById("detBill").innerText = run.bill_id ? ("#" + run.bill_id) : "--";
                }

                showErrorCard(run, logs);

                const { input, output, meta } = pickInputOutput(run, logs);
                const inputItems = input ? flatten(input) : [];
                const outputItems = output ? flatten(output) : [];

                bindVindiProfile(run);
                bindOpenChat(run, logs, inputItems, outputItems);
                bindResend(run, logs, inputItems, outputItems);
                bindResendSame(run, logs, inputItems, outputItems);
                bindRetryVindi(run);
                bindDeleteRun(run);

                renderFields("detInputFields", inputItems, "");
                renderFields("detOutputFields", outputItems, "");
                renderLogs(logs, "");

                const fullJson = { run, logs, meta_http_parsed: meta };
                const jsonCode = document.getElementById("detJsonBox");
                jsonCode.textContent = JSON.stringify(fullJson, null, 2);
                if (window.Prism) Prism.highlightElement(jsonCode);

                const search = document.getElementById("detSearch");
                search.oninput = () => {
                    const q = search.value || "";
                    renderFields("detInputFields", inputItems, q);
                    renderFields("detOutputFields", outputItems, q);
                    renderLogs(logs, q);
                };

                document.getElementById("detCopyJson").onclick = async () => {
                    try {
                        await navigator.clipboard.writeText(JSON.stringify(fullJson, null, 2));
                        const btn = document.getElementById("detCopyJson");
                        btn.innerHTML = `<i class="fa-solid fa-check"></i> Copiado`;
                        setTimeout(() => btn.innerHTML = `<i class="fa-regular fa-copy"></i> Copiar JSON`, 1200);
                    } catch {
                        alert("Não foi possível copiar (HTTP pode bloquear)");
                    }
                };

                setupTabs();

                loading.style.display = "none";
                ok.style.display = "block";

                if (!inputItems.length) {
                    document.getElementById("detInputFields").innerHTML =
                        `<div class="det-muted" style="margin-top:12px;">
            Sem dados de entrada. (Dica: meta_http como {"input":{...}})
          </div>`;
                }
                if (!outputItems.length) {
                    document.getElementById("detOutputFields").innerHTML =
                        `<div class="det-muted" style="margin-top:12px;">
            Sem dados de saída. (Dica: meta_http como {"output":{...}})
          </div>`;
                }

            } catch (err) {
                loading.innerHTML = `<span style="color:#991b1b;font-weight:1000;">Erro: ${esc(err.message)}</span>`;
            }
        }

        load();
    })();
</script>
