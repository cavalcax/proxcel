<?php
/**
 * Proxcel - Redefinição de Senha
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::startSession();
Security::setSecurityHeaders();

$token = $_GET['token'] ?? '';
$valid = false;
$tokenData = null;
$success = false;
$error = '';

// Valida token
if (!empty($token)) {
    $tokenData = db()->fetch(
        "SELECT * FROM " . tb('tokens_recuperacao') . " WHERE token = ? AND usado = 0 AND expira_em > NOW()",
        [$token]
    );
    $valid = ($tokenData !== false);
}

// Processa nova senha
if (isPost() && $valid) {
    CSRF::validateOrDie();

    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';

    $errors = Auth::validatePassword($senha);

    if (!empty($errors)) {
        $error = implode(' ', $errors);
    } elseif ($senha !== $confirmar) {
        $error = 'As senhas não conferem.';
    } else {
        $hash = Auth::hashPassword($senha);
        $table = $tokenData['usuario_tipo'] === 'sistema' ? 'usuarios' : 'usuarios_clientes';

        db()->update(
            "UPDATE " . tb($table) . " SET senha = ? WHERE email = ?",
            [$hash, $tokenData['email']]
        );

        // Marca token como usado
        db()->update(
            "UPDATE " . tb('tokens_recuperacao') . " SET usado = 1 WHERE id = ?",
            [$tokenData['id']]
        );

        Security::logAccess('redefinir_senha', 'Senha redefinida para: ' . $tokenData['email']);
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
    <title>Redefinir Senha — <?= APP_NAME ?></title>

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
                </div>

                <?php if ($success): ?>
                    <div class="text-center py-6">
                        <div class="w-16 h-16 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-4">
                            <i class="fas fa-check-circle text-2xl text-proxcel"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Senha Alterada!</h3>
                        <p class="text-sm text-gray-500 mb-6">Sua senha foi redefinida com sucesso.</p>
                        <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Fazer Login
                        </a>
                    </div>
                <?php elseif (!$valid): ?>
                    <div class="text-center py-6">
                        <div class="w-16 h-16 mx-auto rounded-full bg-red-100 flex items-center justify-center mb-4">
                            <i class="fas fa-exclamation-triangle text-2xl text-red-500"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Link Inválido</h3>
                        <p class="text-sm text-gray-500 mb-6">Este link expirou ou já foi utilizado.</p>
                        <a href="<?= APP_URL ?>/pages/recuperar_senha.php" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Solicitar Novo Link
                        </a>
                    </div>
                <?php else: ?>
                    <h2 class="text-xl font-bold text-gray-800 text-center mb-6">Redefinir Senha</h2>
                    <form method="POST" action="">
                        <?= CSRF::field() ?>
                        <div class="space-y-5">
                            <div class="form-group">
                                <label for="senha">Nova Senha</label>
                                <input type="password" id="senha" name="senha" class="form-control" placeholder="••••••••"
                                    required>
                                <div id="senha-feedback" class="flex flex-wrap gap-2 mt-1"></div>
                            </div>
                            <div class="form-group">
                                <label for="confirmar_senha">Confirmar Senha</label>
                                <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control"
                                    placeholder="••••••••" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-full py-3">
                                <i class="fas fa-save"></i> Salvar Nova Senha
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
    <?php if ($error): ?>
        <script>Swal.fire({ icon: 'error', title: 'Erro!', text: <?= json_encode($error) ?>, confirmButtonColor: '#3EAF5C' });</script>
    <?php endif; ?>
</body>

</html>