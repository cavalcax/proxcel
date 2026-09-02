<?php
/**
 * Proxcel - Proteção CSRF
 * 
 * Gera e valida tokens CSRF em formulários.
 */

class CSRF {
    
    /**
     * Gera token CSRF
     */
    public static function generate() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_time'] = time();
        
        return $token;
    }

    /**
     * Retorna campo hidden com token
     */
    public static function field() {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Retorna meta tag com token (para AJAX)
     */
    public static function meta() {
        if (!isset($_SESSION['csrf_token'])) {
            self::generate();
        }
        return '<meta name="csrf-token" content="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
    }

    /**
     * Valida token CSRF
     */
    public static function validate($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        
        if (empty($token) || !isset($_SESSION['csrf_token'])) {
            return false;
        }

        // Verifica se expirou
        if (isset($_SESSION['csrf_time']) && (time() - $_SESSION['csrf_time']) > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_time']);
            return false;
        }

        $valid = hash_equals($_SESSION['csrf_token'], $token);
        
        return $valid;
    }

    /**
     * Valida e aborta se inválido
     */
    public static function validateOrDie() {
        if (!self::validate()) {
            http_response_code(403);
            if (self::isAjax()) {
                echo json_encode(['success' => false, 'message' => 'Token de segurança inválido. Recarregue a página.']);
            } else {
                die('Token de segurança inválido. <a href="javascript:history.back()">Voltar</a>');
            }
            exit;
        }
    }

    /**
     * Verifica se é requisição AJAX
     */
    private static function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Retorna token atual
     */
    public static function getToken() {
        if (!isset($_SESSION['csrf_token'])) {
            self::generate();
        }
        return $_SESSION['csrf_token'];
    }
}
