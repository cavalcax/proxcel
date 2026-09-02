<?php
/**
 * Proxcel - Sistema de Permissões
 * 
 * Verifica permissões por tipo de usuário com override individual.
 */

require_once __DIR__ . '/../config/database.php';

class Permissions {

    private static $cache = [];

    /**
     * Verifica se o usuário tem determinada permissão
     */
    public static function has($chave) {
        if (!isset($_SESSION['user_id'])) return false;

        $userId = $_SESSION['user_id'];
        $userTipo = $_SESSION['user_tipo'];
        $userTipoAcesso = $_SESSION['user_tipo_acesso'] ?? 'sistema';

        // Admin sempre tem acesso total
        if ($userTipo === 'admin') return true;

        // Cache de permissões
        $cacheKey = $userId . '_' . $userTipoAcesso . '_' . $chave;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // Verifica override individual
        $override = db()->fetch(
            "SELECT up.concedida FROM " . tb('usuario_permissoes') . " up 
             JOIN " . tb('permissoes') . " p ON p.id = up.permissao_id 
             WHERE up.usuario_id = ? AND up.usuario_tipo = ? AND p.chave = ?",
            [$userId, $userTipoAcesso, $chave]
        );

        if ($override !== false) {
            self::$cache[$cacheKey] = (bool)$override['concedida'];
            return self::$cache[$cacheKey];
        }

        // Verifica permissão por tipo
        $tipoPerm = db()->fetch(
            "SELECT tp.id FROM " . tb('tipo_usuario_permissoes') . " tp 
             JOIN " . tb('permissoes') . " p ON p.id = tp.permissao_id 
             WHERE tp.tipo_usuario = ? AND p.chave = ?",
            [$userTipo, $chave]
        );

        self::$cache[$cacheKey] = ($tipoPerm !== false);
        return self::$cache[$cacheKey];
    }

    /**
     * Verifica permissão e redireciona se não tiver
     */
    public static function requirePermission($chave) {
        if (!self::has($chave)) {
            if (self::isAjax()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Você não tem permissão para realizar esta ação.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Você não tem permissão para acessar esta página.';
            header('Location: ' . APP_URL . '/pages/dashboard.php');
            exit;
        }
    }

    /**
     * Retorna todas as permissões do usuário
     */
    public static function getUserPermissions($userId, $userTipo, $userTipoAcesso = 'sistema') {
        // Permissões do tipo
        $tipoPerms = db()->fetchAll(
            "SELECT p.* FROM " . tb('permissoes') . " p 
             JOIN " . tb('tipo_usuario_permissoes') . " tp ON tp.permissao_id = p.id 
             WHERE tp.tipo_usuario = ?
             ORDER BY p.modulo, p.acao",
            [$userTipo]
        );

        // Overrides individuais
        $overrides = db()->fetchAll(
            "SELECT p.chave, up.concedida FROM " . tb('usuario_permissoes') . " up 
             JOIN " . tb('permissoes') . " p ON p.id = up.permissao_id 
             WHERE up.usuario_id = ? AND up.usuario_tipo = ?",
            [$userId, $userTipoAcesso]
        );

        $overrideMap = [];
        foreach ($overrides as $o) {
            $overrideMap[$o['chave']] = (bool)$o['concedida'];
        }

        // Todas as permissões disponíveis
        $allPerms = db()->fetchAll(
            "SELECT * FROM " . tb('permissoes') . " ORDER BY modulo, acao"
        );

        $tipoPermKeys = array_column($tipoPerms, 'chave');

        $result = [];
        foreach ($allPerms as $perm) {
            $hasFromTipo = in_array($perm['chave'], $tipoPermKeys);
            $hasOverride = isset($overrideMap[$perm['chave']]);
            
            $result[] = [
                'id' => $perm['id'],
                'modulo' => $perm['modulo'],
                'acao' => $perm['acao'],
                'descricao' => $perm['descricao'],
                'chave' => $perm['chave'],
                'from_tipo' => $hasFromTipo,
                'override' => $hasOverride ? $overrideMap[$perm['chave']] : null,
                'efetiva' => $hasOverride ? $overrideMap[$perm['chave']] : $hasFromTipo
            ];
        }

        return $result;
    }

    /**
     * Salva permissões individuais de um usuário
     */
    public static function saveUserPermissions($userId, $userTipoAcesso, $permissoes) {
        // Remove overrides atuais
        db()->delete(
            "DELETE FROM " . tb('usuario_permissoes') . " WHERE usuario_id = ? AND usuario_tipo = ?",
            [$userId, $userTipoAcesso]
        );

        // Insere novos overrides
        foreach ($permissoes as $permId => $concedida) {
            db()->insert(
                "INSERT INTO " . tb('usuario_permissoes') . " (usuario_id, usuario_tipo, permissao_id, concedida) VALUES (?, ?, ?, ?)",
                [$userId, $userTipoAcesso, $permId, $concedida ? 1 : 0]
            );
        }

        // Limpa cache
        self::$cache = [];
    }

    /**
     * Verifica se é requisição AJAX
     */
    private static function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Limpa cache de permissões
     */
    public static function clearCache() {
        self::$cache = [];
    }
}
