<?php
/**
 * Proxcel - Funções de Segurança
 * 
 * Rate limiting, sanitização, logs de acesso e alteração.
 */

require_once __DIR__ . '/../config/database.php';

class Security {

    /**
     * Verifica rate limiting de login
     */
    public static function checkLoginAttempts($ip) {
        $count = db()->fetch(
            "SELECT COUNT(*) as total FROM " . tb('tentativas_login') . " 
             WHERE ip = ? AND sucesso = 0 AND criado_em > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$ip, LOGIN_LOCKOUT_TIME]
        );

        return ($count['total'] ?? 0) < LOGIN_MAX_ATTEMPTS;
    }

    /**
     * Retorna tempo restante de bloqueio
     */
    public static function getLockoutTimeRemaining($ip) {
        $last = db()->fetch(
            "SELECT criado_em FROM " . tb('tentativas_login') . " 
             WHERE ip = ? AND sucesso = 0 
             ORDER BY criado_em DESC LIMIT 1",
            [$ip]
        );

        if (!$last) return 0;

        $lockoutEnd = strtotime($last['criado_em']) + LOGIN_LOCKOUT_TIME;
        $remaining = $lockoutEnd - time();
        
        return max(0, $remaining);
    }

    /**
     * Registra tentativa de login
     */
    public static function logLoginAttempt($ip, $email, $sucesso = false) {
        db()->insert(
            "INSERT INTO " . tb('tentativas_login') . " (ip, email, sucesso) VALUES (?, ?, ?)",
            [$ip, $email, $sucesso ? 1 : 0]
        );
    }

    /**
     * Registra log de acesso (LGPD)
     */
    public static function logAccess($acao, $descricao = null) {
        if (!LOG_ACCESS) return;

        $user = null;
        if (isset($_SESSION['user_id'])) {
            $user = [
                'id' => $_SESSION['user_id'],
                'tipo' => $_SESSION['user_tipo_acesso'] ?? 'sistema',
                'nome' => $_SESSION['user_nome'] ?? 'Desconhecido'
            ];
        }

        try {
            db()->insert(
                "INSERT INTO " . tb('logs_acesso') . " 
                 (usuario_id, usuario_tipo, usuario_nome, acao, descricao, ip, user_agent, pagina) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $user['id'] ?? null,
                    $user['tipo'] ?? null,
                    $user['nome'] ?? null,
                    $acao,
                    $descricao,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    $_SERVER['REQUEST_URI'] ?? ''
                ]
            );
        } catch (\Exception $e) {
            // Silently fail to not break the application
            if (APP_DEBUG) error_log("Log access error: " . $e->getMessage());
        }
    }

    /**
     * Registra log de alteração (LGPD)
     */
    public static function logChange($tabela, $registroId, $acao, $dadosAnteriores = null, $dadosNovos = null) {
        if (!LOG_CHANGES) return;

        $user = null;
        if (isset($_SESSION['user_id'])) {
            $user = [
                'id' => $_SESSION['user_id'],
                'tipo' => $_SESSION['user_tipo_acesso'] ?? 'sistema',
                'nome' => $_SESSION['user_nome'] ?? 'Desconhecido'
            ];
        }

        try {
            db()->insert(
                "INSERT INTO " . tb('logs_alteracao') . " 
                 (usuario_id, usuario_tipo, usuario_nome, tabela, registro_id, acao, dados_anteriores, dados_novos, ip) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $user['id'] ?? null,
                    $user['tipo'] ?? null,
                    $user['nome'] ?? null,
                    $tabela,
                    $registroId,
                    $acao,
                    $dadosAnteriores ? json_encode($dadosAnteriores) : null,
                    $dadosNovos ? json_encode($dadosNovos) : null,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ]
            );
        } catch (\Exception $e) {
            if (APP_DEBUG) error_log("Log change error: " . $e->getMessage());
        }
    }

    /**
     * Sanitiza string de entrada
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitiza saída para HTML
     */
    public static function escape($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Limpa tentativas antigas de login
     */
    public static function cleanOldAttempts() {
        db()->delete(
            "DELETE FROM " . tb('tentativas_login') . " WHERE criado_em < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }

    /**
     * Limpa tokens de recuperação expirados
     */
    public static function cleanExpiredTokens() {
        db()->delete(
            "DELETE FROM " . tb('tokens_recuperacao') . " WHERE expira_em < NOW()"
        );
    }

    /**
     * Retorna o IP real do cliente
     */
    public static function getClientIp() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Define headers de segurança
     */
    public static function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        if (!APP_DEBUG) {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self'; frame-src https://www.google.com;");
        }
    }
}

/**
 * Helper rápido para escape de saída HTML
 */
function e($value) {
    return Security::escape($value);
}
