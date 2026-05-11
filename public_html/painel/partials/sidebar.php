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
    }

    .main-content.shift {
        margin-left: var(--sidebar-width);
    }

    @media (max-width: 768px) {
        .main-content.shift {
            margin-left: 0;
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

    <div style="display:flex; align-items:center; gap:15px;">
        <i class='bx bx-bell' style="font-size:1.6rem; color:#999; cursor:pointer;"></i>
        <form method="post" action="/painel/" style="margin:0;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <button name="logout" value="1" title="Sair" style="height:38px; padding:0 14px; border:1px solid #e6edf5; border-radius:8px; background:#fff; color:#5a6a85; cursor:pointer; font-weight:700; display:flex; align-items:center; gap:8px;">
                <i class='bx bx-log-out'></i> Sair
            </button>
        </form>
        <div style="width:38px; height:38px; background:#e0e0e0; border-radius:50%; overflow:hidden;">
            <img src="https://via.placeholder.com/38" alt="User" style="width:100%; height:100%; object-fit:cover;">
        </div>
    </div>
</header>

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
</script>
