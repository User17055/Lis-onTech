<?php
$pagina = $_GET['pagina'] ?? 'index';

if (!function_exists('isActive')) {
    function isActive($p, $current)
    {
        return $p === $current ? 'active' : '';
    }
}
?>

<style>
    :root {
        --primary-blue: #38b6ff;
        --primary-light: #e6f2ff;
        --text-dark: #333;
        --text-gray: #5a6a85;
        --header-height: 70px;
        --sidebar-width: 260px;
        --bg-body: #f7f9fc;
    }

    body {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        background-color: var(--bg-body);
        overflow-x: hidden;
        padding-top: var(--header-height);
    }

    .top-header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: var(--header-height);
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 25px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        z-index: 1000;
        box-sizing: border-box;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .notify-btn {
        position: relative;
        width: 42px;
        height: 42px;
        border: 1px solid #e6edf5;
        border-radius: 8px;
        background: #fff;
        color: #7b8a9d;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.35rem;
        transition: .2s;
        flex: 0 0 42px;
    }

    .notify-btn:hover,
    .notify-btn.active {
        color: var(--primary-blue);
        border-color: #bfe8ff;
        background: #f5fbff;
    }

    .notify-btn.blocked {
        color: #b45309;
        background: #fff7ed;
        border-color: #fed7aa;
    }

    .notify-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: #ef4444;
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        line-height: 18px;
        text-align: center;
        box-sizing: border-box;
        display: none;
    }

    .notify-badge.show {
        display: block;
    }

    .global-toast {
        position: fixed;
        right: 18px;
        bottom: 18px;
        width: min(360px, calc(100vw - 32px));
        background: #172033;
        color: #fff;
        border-radius: 8px;
        padding: 14px 16px;
        box-shadow: 0 18px 50px rgba(23, 32, 51, .22);
        font-size: 13px;
        font-weight: 700;
        line-height: 1.35;
        z-index: 1300;
        opacity: 0;
        transform: translateY(12px);
        pointer-events: none;
        transition: .2s;
    }

    .global-toast.show {
        opacity: 1;
        transform: translateY(0);
    }

    .brand-area {
        display: flex;
        align-items: center;
        gap: 20px;
        height: 100%;
    }

    .logo-container {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        height: 100%;
    }

    .logo-container img {
        height: 52px;
        width: auto;
        display: block;
    }

    .logo-text {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-dark);
        letter-spacing: -0.5px;
        line-height: 1;
    }

    .logo-text span {
        color: var(--primary-blue);
    }

    .toggle-btn {
        background: transparent;
        border: none;
        border-radius: 8px;
        font-size: 2rem;
        color: #555;
        cursor: pointer;
        display: flex;
        align-items: center;
        padding: 5px;
        transition: 0.3s;
    }

    .toggle-btn:hover {
        color: var(--primary-blue);
        background: #f5f5f5;
    }

    .sidebar {
        position: fixed;
        top: var(--header-height);
        left: 0;
        height: calc(100vh - var(--header-height));
        width: var(--sidebar-width);
        background: #ffffff;
        border-right: 1px solid #eef2f6;
        transform: translateX(-100%);
        display: flex;
        flex-direction: column;
        padding: 24px 16px;
        box-sizing: border-box;
        z-index: 900;
        transition: transform 0.4s ease-in-out;
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .nav-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .nav-links li {
        margin-bottom: 8px;
    }

    .nav-links a {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 20px;
        color: var(--text-gray);
        text-decoration: none;
        border-radius: 10px;
        font-weight: 500;
        font-size: 1rem;
        transition: all 0.2s ease;
    }

    .nav-links a i {
        font-size: 1.35rem;
        min-width: 24px;
        text-align: center;
        color: #8d9eb5;
        transition: 0.2s;
    }

    .nav-links a:hover {
        background-color: #f4f9ff;
        color: var(--primary-blue);
        transform: translateX(5px);
    }

    .nav-links a:hover i {
        color: var(--primary-blue);
    }

    .nav-links a.active {
        background-color: var(--primary-light);
        color: var(--primary-blue);
        font-weight: 600;
    }

    .nav-links a.active i {
        color: var(--primary-blue);
    }

    .sidebar-divider {
        margin-top: 20px;
        border-top: 1px solid #f0f0f0;
        padding-top: 10px;
    }

    .main-content {
        padding: 40px;
        margin-left: 0;
        transition: margin-left 0.5s ease;
        min-width: 0;
        box-sizing: border-box;
    }

    .main-content.shift {
        margin-left: var(--sidebar-width);
    }

    @media (max-width: 768px) {
        :root {
            --header-height: 62px;
        }

        .top-header {
            padding: 0 12px;
        }

        .brand-area {
            gap: 10px;
            min-width: 0;
        }

        .toggle-btn {
            font-size: 1.75rem;
        }

        .logo-container {
            gap: 8px;
            min-width: 0;
        }

        .logo-container img {
            height: 42px;
        }

        .logo-text {
            font-size: 1.35rem;
        }

        .header-actions {
            gap: 8px;
        }

        .header-user-avatar {
            display: none;
        }

        .logout-btn {
            width: 42px;
            padding: 0;
            justify-content: center;
        }

        .logout-btn span {
            display: none;
        }

        .sidebar {
            width: min(86vw, var(--sidebar-width));
            padding: 18px 12px;
            box-shadow: 18px 0 42px rgba(23, 32, 51, .12);
        }

        .nav-links a {
            padding: 13px 16px;
            font-size: .95rem;
        }

        .main-content {
            padding: 18px 12px 28px;
        }

        .main-content.shift {
            margin-left: 0;
        }

        .global-toast {
            right: 12px;
            bottom: 12px;
        }
    }

    @media (max-width: 520px) {
        .logo-text {
            display: none;
        }

        .top-header {
            gap: 8px;
        }

        .main-content {
            padding-left: 10px;
            padding-right: 10px;
        }

        .pa-header,
        .toolbar,
        .panel-head {
            flex-direction: column;
            align-items: stretch !important;
            gap: 12px;
        }

        .container,
        .dash-container {
            width: 100%;
            max-width: 100%;
            padding-left: 0 !important;
            padding-right: 0 !important;
            box-sizing: border-box;
        }

        table {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .form-control,
        .btn-primary,
        .btn-secondary,
        select,
        input,
        button {
            max-width: 100% !important;
        }
    }
</style>

<header class="top-header">
    <div class="brand-area">
        <button class="toggle-btn" id="toggle-btn">
            <i class='bx bx-menu'></i>
        </button>

        <a href="?pagina=index" class="logo-container">
            <img src="/assets/lisonbb.svg" alt="Logo">
            <div class="logo-text">Lis'<span>on</span></div>
        </a>
    </div>

    <div class="header-actions">
        <button class="notify-btn" id="globalNotifyBtn" type="button" title="Ativar notificacoes do chat">
            <i class='bx bx-bell'></i>
            <span class="notify-badge" id="globalNotifyBadge"></span>
        </button>
        <form method="post" action="/painel/" style="margin:0;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <button class="logout-btn" name="logout" value="1" title="Sair" style="height:38px; padding:0 14px; border:1px solid #e6edf5; border-radius:8px; background:#fff; color:#5a6a85; cursor:pointer; font-weight:700; display:flex; align-items:center; gap:8px;">
                <i class='bx bx-log-out'></i> <span>Sair</span>
            </button>
        </form>
        <div class="header-user-avatar" style="width:38px; height:38px; background:#e0e0e0; border-radius:50%; overflow:hidden;">
            <img src="https://via.placeholder.com/38" alt="User" style="width:100%; height:100%; object-fit:cover;">
        </div>
    </div>
</header>
<div class="global-toast" id="globalNotifyToast" role="status" aria-live="polite"></div>

<nav class="sidebar" id="sidebar">
    <ul class="nav-links">
        <li>
            <a href="?pagina=index" class="<?= isActive('index', $pagina) ?>">
                <i class='bx bx-home-smile'></i> <span>Início</span>
            </a>
        </li>

        <li>
            <a href="?pagina=dashboard" class="<?= isActive('dashboard', $pagina) ?>">
                <i class='bx bx-grid-alt'></i> <span>Dashboard</span>
            </a>
        </li>

        <li>
            <a href="?pagina=recobrancas" class="<?= isActive('recobrancas', $pagina) ?>">
                <i class='bx bx-message-square-dots'></i> <span>Recobranças</span>
            </a>
        </li>

        <li>
            <a href="?pagina=chat" class="<?= isActive('chat', $pagina) ?>">
                <i class='bx bx-conversation'></i> <span>Chat</span>
            </a>
        </li>

        <li>
            <a href="?pagina=reports" class="<?= isActive('reports', $pagina) ?>">
                <i class='bx bx-bar-chart-square'></i> <span>Relatórios</span>
            </a>
        </li>

        <li>
            <a href="?pagina=finance" class="<?= isActive('finance', $pagina) ?>">
                <i class='bx bx-dollar-circle'></i> <span>Financeiro</span>
            </a>
        </li>

        <li class="sidebar-divider">
            <a href="?pagina=config" class="<?= isActive('config', $pagina) ?>">
                <i class='bx bx-cog'></i> <span>Configurações</span>
            </a>
        </li>
    </ul>
</nav>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const btn = document.getElementById('toggle-btn');

        function toggleMenu(isOpen) {
            if (isOpen) {
                sidebar.classList.add('open');
                if (content) content.classList.add('shift');
            } else {
                sidebar.classList.remove('open');
                if (content) content.classList.remove('shift');
            }
            localStorage.setItem('menuOpen', isOpen);
        }

        let isMenuOpen = localStorage.getItem('menuOpen') === 'true';
        toggleMenu(isMenuOpen);

        btn.addEventListener('click', () => {
            isMenuOpen = !isMenuOpen;
            toggleMenu(isMenuOpen);
        });
    });

    (function () {
        const state = {
            ready: false,
            baselineDone: false,
            snapshot: new Map(),
            audioContext: null,
            timer: null,
            titleTimer: null,
            originalTitle: document.title
        };

        const btn = document.getElementById('globalNotifyBtn');
        const badge = document.getElementById('globalNotifyBadge');
        const toast = document.getElementById('globalNotifyToast');

        function setBadge(count) {
            if (!badge) return;
            const total = Number(count || 0);
            badge.textContent = total > 99 ? '99+' : String(total);
            badge.classList.toggle('show', total > 0);
        }

        function updateButton() {
            if (!btn) return;
            const supported = 'Notification' in window;
            const granted = supported && Notification.permission === 'granted';
            state.ready = granted;
            btn.classList.toggle('active', granted);
            btn.classList.toggle('blocked', supported && Notification.permission === 'denied');
            btn.title = !supported
                ? 'Navegador sem notificacoes'
                : (granted ? 'Notificacoes do chat ativas' : 'Ativar notificacoes do chat');
        }

        function showToast(message) {
            if (!toast) return;
            toast.textContent = message;
            toast.classList.add('show');
            window.clearTimeout(toast._timer);
            toast._timer = window.setTimeout(() => toast.classList.remove('show'), 4500);
        }

        function unlockSound() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                if (!state.audioContext) state.audioContext = new Ctx();
                if (state.audioContext.state === 'suspended') state.audioContext.resume();
            } catch (e) {}
        }

        function playSound() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                const ctx = state.audioContext || (Ctx ? new Ctx() : null);
                if (!ctx) return;
                state.audioContext = ctx;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.001, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.13, ctx.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
                osc.connect(gain).connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.3);
            } catch (e) {}
        }

        function flashTitle(count) {
            window.clearTimeout(state.titleTimer);
            document.title = count > 0 ? `(${count}) Nova mensagem - Chat` : state.originalTitle;
            state.titleTimer = window.setTimeout(() => {
                document.title = state.originalTitle;
                state.titleTimer = null;
            }, 7000);
        }

        function openChat(phone) {
            const url = new URL('/painel/index.php', location.origin);
            url.searchParams.set('pagina', 'chat');
            url.searchParams.set('phone', String(phone || ''));
            location.href = url.toString();
        }

        function notify(row, totalUnread) {
            const phone = String(row.phone || '');
            const name = row.display_name || phone || 'Cliente';
            const preview = row.last_message_preview || 'Nova mensagem recebida';
            const body = `${name}: ${preview}`;

            showToast(body);
            playSound();
            flashTitle(totalUnread);

            if (state.ready && (document.hidden || !document.hasFocus())) {
                try {
                    const notification = new Notification('Nova mensagem no chat', {
                        body,
                        tag: `chat-${phone}`,
                        renotify: true
                    });
                    notification.onclick = () => {
                        window.focus();
                        openChat(phone);
                        notification.close();
                    };
                } catch (e) {}
            }
        }

        async function pollThreads() {
            try {
                const resp = await fetch('/painel/api/chat_threads.php?unread=1&direction=in', {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (!resp.ok) return;
                const data = await resp.json();
                if (!data || data.ok === false) return;
                const rows = Array.isArray(data.rows) ? data.rows : [];
                const next = new Map();
                const totalUnread = rows.reduce((sum, row) => sum + Number(row.unread_count || 0), 0);
                setBadge(totalUnread);

                rows.forEach(row => {
                    const phone = String(row.phone || '');
                    if (!phone) return;
                    next.set(phone, {
                        unread: Number(row.unread_count || 0),
                        at: String(row.last_message_at || ''),
                        preview: String(row.last_message_preview || '')
                    });
                });

                if (!state.baselineDone) {
                    state.snapshot = next;
                    state.baselineDone = true;
                    return;
                }

                rows.forEach(row => {
                    const phone = String(row.phone || '');
                    const prev = state.snapshot.get(phone);
                    const unread = Number(row.unread_count || 0);
                    const at = String(row.last_message_at || '');
                    const preview = String(row.last_message_preview || '');
                    const isNew = !prev
                        || unread > Number(prev.unread || 0)
                        || (at && at !== prev.at)
                        || (preview && preview !== prev.preview);
                    if (isNew) notify(row, totalUnread);
                });

                state.snapshot = next;
            } catch (e) {}
        }

        async function enable() {
            unlockSound();
            if ('Notification' in window && Notification.permission === 'default') {
                try { await Notification.requestPermission(); } catch (e) {}
            }
            updateButton();
            showToast(state.ready ? 'Notificacoes do chat ativadas' : 'Som do chat ativado neste navegador');
            pollThreads();
        }

        if (btn) btn.addEventListener('click', enable);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) pollThreads();
        });

        updateButton();
        pollThreads();
        state.timer = window.setInterval(pollThreads, 10000);
        window.LisOnGlobalNotify = { poll: pollThreads, enable, playSound };
    })();
</script>
