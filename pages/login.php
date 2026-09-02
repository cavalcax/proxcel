<?php
/**
 * Proxcel - Página de Login
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::startSession();
Security::setSecurityHeaders();

// Se já logado, redireciona
if (Auth::check()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

$error = '';
$email = '';

// Processa login
if (isPost()) {
    CSRF::validateOrDie();

    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $ip = Security::getClientIp();

    // Rate limiting
    if (!Security::checkLoginAttempts($ip)) {
        $remaining = Security::getLockoutTimeRemaining($ip);
        $minutes = ceil($remaining / 60);
        $error = "Muitas tentativas. Aguarde {$minutes} minuto(s).";
    } elseif (empty($email) || empty($senha)) {
        $error = 'Preencha todos os campos.';
    } else {
        $result = Auth::login($email, $senha);

        if ($result['success']) {
            Security::logLoginAttempt($ip, $email, true);
            Security::logAccess('login', 'Login realizado com sucesso');
            redirect(APP_URL . '/pages/dashboard.php');
        } else {
            Security::logLoginAttempt($ip, $email, false);
            $error = $result['message'];
        }
    }
}

// Messages
$expired = isset($_GET['expired']);
$securityAlert = isset($_GET['security']);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Acesse o sistema Proxcel">
    <meta name="theme-color" content="#3EAF5C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Login — <?= APP_NAME ?></title>

    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <link rel="icon" type="image/png" sizes="96x96" href="<?= APP_URL ?>/assets/icons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/icons/favicon.svg">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { proxcel: { DEFAULT: '#3EAF5C', dark: '#2D8A47', light: '#5CC97A' } },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>

<body class="h-full font-sans antialiased login-bg">
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md animate-fadeInUp">

            <!-- Login Card -->
            <div class="login-card p-8 sm:p-10">
                <!-- Logo -->
                <div class="text-center mb-8">
                    <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Proxcel" class="h-14 mx-auto mb-4">
                    <p class="text-gray-500 text-sm">Acesse sua conta</p>
                </div>

                <!-- Form -->
                <form method="POST" action="" id="loginForm">
                    <?= CSRF::field() ?>

                    <div class="space-y-5">
                        <!-- Email -->
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                                    <i class="fas fa-envelope text-sm"></i>
                                </span>
                                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                                    class="form-control !pl-10" placeholder="seu@email.com" required autofocus>
                            </div>
                        </div>

                        <!-- Senha -->
                        <div class="form-group">
                            <label for="senha">Senha</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                                    <i class="fas fa-lock text-sm"></i>
                                </span>
                                <input type="password" id="senha" name="senha" class="form-control !pl-10 !pr-10"
                                    placeholder="••••••••" required>
                                <button type="button" onclick="togglePassword()"
                                    class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                    <i class="fas fa-eye text-sm" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn btn-primary w-full py-3 text-base">
                            <i class="fas fa-sign-in-alt"></i>
                            Entrar
                        </button>
                    </div>
                </form>

                <!-- Forgot Password -->
                <div class="text-center mt-6">
                    <a href="<?= APP_URL ?>/pages/recuperar_senha.php"
                        class="text-sm text-gray-500 hover:text-proxcel transition font-medium">
                        <i class="fas fa-key text-xs"></i>
                        Esqueci minha senha
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <p class="text-center text-gray-500 text-xs mt-6">
                &copy; <?= date('Y') ?> <?= APP_NAME ?> — Assessoria Empresarial
            </p>
        </div>
    </div>

    <!-- PWA Install Banner -->
    <div id="pwa-banner" class="pwa-banner">
        <div class="flex items-center gap-2">
            <i class="fas fa-mobile-alt"></i>
            <span class="text-sm font-medium">Instale o Proxcel no seu dispositivo!</span>
        </div>
        <div class="flex gap-2">
            <button onclick="installPWA()"
                class="px-3 py-1.5 bg-white text-proxcel rounded-lg text-sm font-semibold hover:bg-gray-100 transition">
                Instalar
            </button>
            <button onclick="dismissPWA()" class="p-1.5 text-white/70 hover:text-white transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>

    <script>
        function togglePassword() {
            const input = document.getElementById('senha');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        <?php if ($error): ?>
            Swal.fire({ icon: 'error', title: 'Erro!', text: <?= json_encode($error) ?>, confirmButtonColor: '#3EAF5C' });
        <?php endif; ?>

        <?php if ($expired): ?>
            Swal.fire({ icon: 'warning', title: 'Sessão Expirada', text: 'Sua sessão expirou. Faça login novamente.', confirmButtonColor: '#3EAF5C' });
        <?php endif; ?>

        <?php if ($securityAlert): ?>
            Swal.fire({ icon: 'error', title: 'Alerta de Segurança', text: 'Sua sessão foi encerrada por motivos de segurança.', confirmButtonColor: '#3EAF5C' });
        <?php endif; ?>
    </script>
</body>

</html>