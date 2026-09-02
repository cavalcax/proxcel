<?php
/**
 * Proxcel - Sistema de Autenticação
 * 
 * Gerencia login, logout, sessão e verificação de acesso.
 */

require_once __DIR__ . '/../config/database.php';

class Auth {
    
    /**
     * Inicia sessão segura
     */
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', SESSION_HTTPONLY ? 1 : 0);
            ini_set('session.cookie_secure', SESSION_SECURE ? 1 : 0);
            ini_set('session.cookie_samesite', SESSION_SAMESITE);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            
            session_name(SESSION_NAME);
            session_start();
        }

        // Verifica expiração
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                self::logout();
                header('Location: ' . APP_URL . '/pages/login.php?expired=1');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Realiza login
     */
    public static function login($email, $senha) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Tenta login como usuário do sistema
        $user = db()->fetch(
            "SELECT * FROM " . tb('usuarios') . " WHERE email = ? AND status = 'ativo'",
            [$email]
        );

        if ($user && password_verify($senha, $user['senha'])) {
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nome'] = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_tipo'] = $user['tipo'];
            $_SESSION['user_tipo_acesso'] = 'sistema';
            $_SESSION['user_avatar'] = $user['avatar'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];

            // Atualiza último acesso
            db()->update(
                "UPDATE " . tb('usuarios') . " SET ultimo_acesso = NOW() WHERE id = ?",
                [$user['id']]
            );

            return ['success' => true, 'tipo' => 'sistema', 'user' => $user];
        }

        // Tenta login como usuário de cliente
        $userCliente = db()->fetch(
            "SELECT uc.*, c.razao_social, c.nome_fantasia, c.status as cliente_status 
             FROM " . tb('usuarios_clientes') . " uc 
             JOIN " . tb('clientes') . " c ON c.id = uc.cliente_id 
             WHERE uc.email = ? AND uc.status = 'ativo' AND c.status = 'ativo'",
            [$email]
        );

        if ($userCliente && password_verify($senha, $userCliente['senha'])) {
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $userCliente['id'];
            $_SESSION['user_nome'] = $userCliente['nome'];
            $_SESSION['user_email'] = $userCliente['email'];
            $_SESSION['user_tipo'] = 'cliente';
            $_SESSION['user_tipo_acesso'] = 'cliente';
            $_SESSION['user_cliente_id'] = $userCliente['cliente_id'];
            $_SESSION['user_cliente_nome'] = $userCliente['nome_fantasia'] ?: $userCliente['razao_social'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];

            // Atualiza último acesso
            db()->update(
                "UPDATE " . tb('usuarios_clientes') . " SET ultimo_acesso = NOW() WHERE id = ?",
                [$userCliente['id']]
            );

            return ['success' => true, 'tipo' => 'cliente', 'user' => $userCliente];
        }

        return ['success' => false, 'message' => 'E-mail ou senha incorretos.'];
    }

    /**
     * Realiza logout
     */
    public static function logout() {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }

    /**
     * Verifica se o usuário está logado
     */
    public static function check() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_tipo']);
    }

    /**
     * Redireciona se não logado
     */
    public static function requireLogin() {
        self::startSession();
        if (!self::check()) {
            header('Location: ' . APP_URL . '/pages/login.php');
            exit;
        }
        // Verifica se IP mudou (roubo de sessão)
        if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
            self::logout();
            header('Location: ' . APP_URL . '/pages/login.php?security=1');
            exit;
        }
    }

    /**
     * Redireciona se não for do tipo especificado
     */
    public static function requireType($tipos) {
        if (!is_array($tipos)) {
            $tipos = [$tipos];
        }
        if (!in_array($_SESSION['user_tipo'], $tipos)) {
            header('Location: ' . APP_URL . '/pages/dashboard.php');
            exit;
        }
    }

    /**
     * Retorna dados do usuário logado
     */
    public static function user() {
        if (!self::check()) return null;
        return [
            'id' => $_SESSION['user_id'],
            'nome' => $_SESSION['user_nome'],
            'email' => $_SESSION['user_email'],
            'tipo' => $_SESSION['user_tipo'],
            'tipo_acesso' => $_SESSION['user_tipo_acesso'],
            'cliente_id' => $_SESSION['user_cliente_id'] ?? null,
            'cliente_nome' => $_SESSION['user_cliente_nome'] ?? null,
            'avatar' => $_SESSION['user_avatar'] ?? null
        ];
    }

    /**
     * Verifica se é admin
     */
    public static function isAdmin() {
        return isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'admin';
    }

    /**
     * Verifica se é do sistema (admin ou operador)
     */
    public static function isSistema() {
        return isset($_SESSION['user_tipo_acesso']) && $_SESSION['user_tipo_acesso'] === 'sistema';
    }

    /**
     * Verifica se é cliente
     */
    public static function isCliente() {
        return isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'cliente';
    }

    /**
     * Gera hash seguro de senha
     */
    public static function hashPassword($senha) {
        return password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    /**
     * Valida regras de senha
     */
    public static function validatePassword($senha) {
        $errors = [];
        if (strlen($senha) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'A senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres.';
        }
        if (!preg_match('/[A-Z]/', $senha)) {
            $errors[] = 'A senha deve conter pelo menos uma letra maiúscula.';
        }
        if (!preg_match('/[0-9]/', $senha)) {
            $errors[] = 'A senha deve conter pelo menos um número.';
        }
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $senha)) {
            $errors[] = 'A senha deve conter pelo menos um caractere especial.';
        }
        return $errors;
    }
}
