<?php
/**
 * Proxcel - Sidebar Template
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $dir = '')
{
    global $currentPage, $currentDir;
    if ($dir && $page) {
        return ($currentDir === $dir && $currentPage === $page) ? 'active' : '';
    }
    if ($dir) {
        return $currentDir === $dir ? 'active' : '';
    }
    return $currentPage === $page ? 'active' : '';
}

function menuClass($page, $dir = '')
{
    $active = isActive($page, $dir);
    $base = 'sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200';
    if ($active) {
        return $base . ' bg-white/15 text-white shadow-sm';
    }
    return $base . ' text-gray-300 hover:bg-white/10 hover:text-white';
}
?>

<!-- Sidebar Overlay (Mobile) -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden backdrop-blur-sm"
    onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar"
    class="fixed top-0 left-0 z-50 w-64 h-full bg-gradient-to-b from-gray-900 via-gray-900 to-gray-800 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col shadow-2xl">

    <!-- Logo -->
    <div class="relative flex items-center justify-center px-5 py-5 border-b border-white/10">
        <a href="<?= APP_URL ?>/pages/dashboard.php" class="flex items-center justify-center">
            <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Proxcel" class="h-9 w-auto brightness-0 invert mx-auto">
        </a>
        <button onclick="toggleSidebar()"
            class="lg:hidden absolute right-4 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-gray-400 hover:bg-white/10 hover:text-white transition">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto scrollbar-thin">

        <!-- Dashboard -->
        <a href="<?= APP_URL ?>/pages/dashboard.php" class="<?= menuClass('dashboard') ?>">
            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                <i class="fas fa-tachometer-alt text-sm"></i>
            </div>
            <span>Dashboard</span>
        </a>

        <?php if (Auth::isSistema()): ?>
            <!-- Separador -->
            <div class="pt-4 pb-2 px-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cadastros</p>
            </div>

            <!-- Clientes -->
            <?php if (Permissions::has('clientes.visualizar')): ?>
                <a href="<?= APP_URL ?>/pages/clientes/index.php" class="<?= menuClass('index', 'clientes') ?>">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                        <i class="fas fa-building text-sm"></i>
                    </div>
                    <span>Clientes</span>
                </a>
            <?php endif; ?>

            <!-- Usuários -->
            <?php if (Permissions::has('usuarios.visualizar')): ?>
                <a href="<?= APP_URL ?>/pages/usuarios/index.php" class="<?= menuClass('index', 'usuarios') ?>">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                        <i class="fas fa-users-cog text-sm"></i>
                    </div>
                    <span>Usuários</span>
                </a>
            <?php endif; ?>

            <!-- Usuários Clientes -->
            <?php if (Permissions::has('usuarios_clientes.visualizar')): ?>
                <a href="<?= APP_URL ?>/pages/usuarios_clientes/index.php"
                    class="<?= menuClass('index', 'usuarios_clientes') ?>">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                        <i class="fas fa-user-tie text-sm"></i>
                    </div>
                    <span>Usuários de Clientes</span>
                </a>
            <?php endif; ?>

            <!-- Separador -->
            <div class="pt-4 pb-2 px-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Documentos</p>
            </div>

            <!-- Documentos -->
            <?php if (Permissions::has('documentos.visualizar')): ?>
                <a href="<?= APP_URL ?>/pages/documentos/index.php" class="<?= menuClass('index', 'documentos') ?>">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                        <i class="fas fa-folder-open text-sm"></i>
                    </div>
                    <span>Documentos</span>
                </a>
            <?php endif; ?>

            <!-- Importação Mensal -->
            <?php if (Permissions::has('documentos.criar')): ?>
                <a href="<?= APP_URL ?>/pages/documentos/importar_mensal.php" class="<?= menuClass('importar_mensal', 'documentos') ?>">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-sm"></i>
                    </div>
                    <span>Importação Mensal</span>
                </a>

                <a href="<?= APP_URL ?>/pages/documentos/importar_trimestral.php" class="<?= menuClass('importar_trimestral', 'documentos') ?>">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                        <i class="fas fa-chart-pie text-sm"></i>
                    </div>
                    <span>Importação Trimestral</span>
                </a>
            <?php endif; ?>

            <!-- Separador -->
            <div class="pt-4 pb-2 px-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sistema</p>
            </div>

            <!-- Relatórios -->
            <?php if (Permissions::has('relatorios.visualizar')): ?>
                <a href="<?= APP_URL ?>/pages/relatorios/index.php" class="<?= menuClass('index', 'relatorios') ?>">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                        <i class="fas fa-chart-bar text-sm"></i>
                    </div>
                    <span>Relatórios</span>
                </a>
            <?php endif; ?>

            <!-- Logs -->
            <?php if (Permissions::has('logs.visualizar')): ?>
                <a href="<?= APP_URL ?>/pages/logs/index.php" class="<?= menuClass('index', 'logs') ?>">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                        <i class="fas fa-history text-sm"></i>
                    </div>
                    <span>Logs de Acesso</span>
                </a>
            <?php endif; ?>

        <?php else: ?>
            <!-- MENU CLIENTE -->
            <div class="pt-4 pb-2 px-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Documentos</p>
            </div>

            <a href="<?= APP_URL ?>/pages/documentos/index.php" class="<?= menuClass('index', 'documentos') ?>">
                <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                    <i class="fas fa-folder-open text-sm"></i>
                </div>
                <span>Meus Documentos</span>
            </a>
        <?php endif; ?>

    </nav>

    <!-- Sidebar Footer -->
    <div class="px-3 py-4 border-t border-white/10">
        <a href="<?= APP_URL ?>/pages/logout.php"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-all duration-200">
            <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center">
                <i class="fas fa-sign-out-alt text-sm"></i>
            </div>
            <span>Sair</span>
        </a>
        <div class="px-4 mt-3">
            <p class="text-xs text-gray-600"><?= APP_NAME ?> v<?= APP_VERSION ?></p>
        </div>
    </div>
</aside>