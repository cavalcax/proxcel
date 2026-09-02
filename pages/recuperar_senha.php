<?php
/**
 * Proxcel - Recuperação de Senha
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mail_helper.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::startSession();
Security::setSecurityHeaders();

if (Auth::check()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

$success = false;
$error = '';

if (isPost()) {
    CSRF::validateOrDie();

    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } else {
        // Verifica se é usuário do sistema
        $user = db()->fetch(
            "SELECT id, nome, email FROM " . tb('usuarios') . " WHERE email = ? AND status = 'ativo'",
            [$email]
        );
        $tipo = 'sistema';

        // Se não, verifica se é usuário de cliente
        if (!$user) {
            $user = db()->fetch(
                "SELECT id, nome, email FROM " . tb('usuarios_clientes') . " WHERE email = ? AND status = 'ativo'",
                [$email]
            );
            $tipo = 'cliente';
        }

        if ($user) {
            // Invalida tokens anteriores
            db()->update(
                "UPDATE " . tb('tokens_recuperacao') . " SET usado = 1 WHERE email = ? AND usado = 0",
                [$email]
            );

            // Gera novo token
            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

            db()->insert(
                "INSERT INTO " . tb('tokens_recuperacao') . " (email, token, usuario_tipo, expira_em) VALUES (?, ?, ?, ?)",
                [$email, $token, $tipo, $expira]
            );

            // Envia e-mail
            MailHelper::sendPasswordReset($email, $token, $user['nome']);

            Security::logAccess('recuperar_senha', 'Solicitação de recuperação de senha para: ' . $email);
        }

        // Sempre mostra sucesso (evita enumeração de e-mails)
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#3EAF5C">
    <title>Recuperar Senha — <?= APP_NAME ?></title>

    <link rel="icon" type="image/png" sizes="96x96" href="<?= APP_URL ?>/assets/icons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/icons/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { proxcel: { DEFAULT: '#3EAF5C', dark: '#2D8A47' } }, fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] } } }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>

<body class="h-full font-sans antialiased login-bg">
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md animate-fadeInUp">
            <div class="login-card p-8 sm:p-10">
                <div class="text-center mb-8">
                    <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Proxcel" class="h-14 mx-auto mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Recuperar Senha</h2>
                    <p class="text-gray-500 text-sm mt-1">Informe seu e-mail para receber o link de recuperação</p>
                </div>

                <?php if ($success): ?>
                    <div class="text-center py-6">
                        <div class="w-16 h-16 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-4">
                            <i class="fas fa-envelope-circle-check text-2xl text-proxcel"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">E-mail Enviado!</h3>
                        <p class="text-sm text-gray-500 mb-6">Se o e-mail estiver cadastrado, você receberá um link para
                            redefinir sua senha.</p>
                        <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Voltar ao Login
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <?= CSRF::field() ?>
                        <div class="space-y-5">
                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                                        <i class="fas fa-envelope text-sm"></i>
                                    </span>
                                    <input type="email" id="email" name="email" class="form-control !pl-10"
                                        placeholder="seu@email.com" required autofocus>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-full py-3">
                                <i class="fas fa-paper-plane"></i> Enviar Link
                            </button>
                        </div>
                    </form>
                    <div class="text-center mt-6">
                        <a href="<?= APP_URL ?>/pages/login.php"
                            class="text-sm text-gray-500 hover:text-proxcel transition font-medium">
                            <i class="fas fa-arrow-left text-xs"></i> Voltar ao Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if ($error): ?>
        <script>Swal.fire({ icon: 'error', title: 'Erro!', text: <?= json_encode($error) ?>, confirmButtonColor: '#3EAF5C' });</script>
    <?php endif; ?>
</body>

</html>