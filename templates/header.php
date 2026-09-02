<?php
/**
 * Proxcel - Header Template
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::startSession();
Auth::requireLogin();
Security::setSecurityHeaders();

$currentUser = Auth::user();
$pageTitle = $pageTitle ?? 'Dashboard';
$pageDescription = $pageDescription ?? 'Sistema de gestão documental';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#3EAF5C">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Proxcel">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <?= CSRF::meta() ?>

    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>

    <!-- PWA -->
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="96x96" href="<?= APP_URL ?>/assets/icons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/icons/favicon.svg">
    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/icons/favicon.ico">

    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        proxcel: {
                            DEFAULT: '#3EAF5C',
                            dark: '#2D8A47',
                            light: '#5CC97A',
                            50: '#ECFDF0',
                            100: '#D1FAE5',
                            200: '#A7F3D0',
                            300: '#6EE7B7',
                            400: '#34D399',
                            500: '#3EAF5C',
                            600: '#2D8A47',
                            700: '#047857',
                            800: '#065F46',
                            900: '#064E3B'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>

<body class="h-full bg-gray-50 font-sans antialiased">
    <div id="app" class="flex h-full">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-h-screen lg:ml-64">
            <!-- Top Bar -->
            <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-lg border-b border-gray-200/60">
                <div class="flex items-center justify-between px-4 py-3 lg:px-6">
                    <div class="flex items-center gap-3">
                        <!-- Mobile menu toggle -->
                        <button onclick="toggleSidebar()"
                            class="lg:hidden p-2 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-proxcel transition-all">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                        <div>
                            <h1 class="text-lg font-bold text-gray-800"><?= e($pageTitle) ?></h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- User Menu -->
                        <div class="relative" id="userMenuContainer">
                            <button onclick="toggleUserMenu()"
                                class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-gray-100 transition-all">
                                <div
                                    class="w-9 h-9 rounded-xl bg-gradient-to-br from-proxcel to-proxcel-dark flex items-center justify-center text-white font-semibold text-sm shadow-sm">
                                    <?= getInitials($currentUser['nome']) ?>
                                </div>
                                <div class="hidden sm:block text-left">
                                    <p class="text-sm font-semibold text-gray-700 leading-tight">
                                        <?= e($currentUser['nome']) ?>
                                    </p>
                                    <p class="text-xs text-gray-400"><?= ucfirst(e($currentUser['tipo'])) ?></p>
                                </div>
                                <i class="fas fa-chevron-down text-xs text-gray-400 hidden sm:block"></i>
                            </button>

                            <!-- Dropdown -->
                            <div id="userMenu"
                                class="hidden absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200/60 py-2 z-50">
                                <div class="px-4 py-2 border-b border-gray-100">
                                    <p class="text-sm font-semibold text-gray-800"><?= e($currentUser['nome']) ?></p>
                                    <p class="text-xs text-gray-500"><?= e($currentUser['email']) ?></p>
                                </div>
                                <a href="<?= APP_URL ?>/pages/dashboard.php"
                                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition">
                                    <i class="fas fa-tachometer-alt w-4 text-center text-gray-400"></i> Dashboard
                                </a>
                                <div class="border-t border-gray-100 mt-1 pt-1">
                                    <a href="<?= APP_URL ?>/pages/logout.php"
                                        class="flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition">
                                        <i class="fas fa-sign-out-alt w-4 text-center"></i> Sair
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-4 lg:p-6">
                <?php
                // Flash messages
                $flashSuccess = getFlash('success');
                $flashError = getFlash('error');
                $flashInfo = getFlash('info');
                ?>
                <?php if ($flashSuccess): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            Swal.fire({ icon: 'success', title: 'Sucesso!', text: <?= json_encode($flashSuccess) ?>, timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' });
                        });
                    </script>
                <?php endif; ?>
                <?php if ($flashError): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            Swal.fire({ icon: 'error', title: 'Erro!', text: <?= json_encode($flashError) ?>, timer: 4000, showConfirmButton: true, toast: true, position: 'top-end' });
                        });
                    </script>
                <?php endif; ?>
                <?php if ($flashInfo): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            Swal.fire({ icon: 'info', title: 'Informação', text: <?= json_encode($flashInfo) ?>, timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' });
                        });
                    </script>
                <?php endif; ?>