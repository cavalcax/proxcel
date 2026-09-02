<?php
/**
 * Proxcel - Ações de Usuários (AJAX)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::startSession();
Auth::requireLogin();
CSRF::validateOrDie();

$action = post('action');

switch ($action) {
    case 'delete':
        Permissions::requirePermission('usuarios.excluir');
        $id = (int)post('id');
        
        if ($id == $_SESSION['user_id']) {
            jsonResponse(['success' => false, 'message' => 'Você não pode excluir seu próprio usuário.']);
        }
        
        $usuario = db()->fetch("SELECT * FROM " . tb('usuarios') . " WHERE id = ?", [$id]);
        if (!$usuario) jsonResponse(['success' => false, 'message' => 'Usuário não encontrado.'], 404);
        
        db()->delete("DELETE FROM " . tb('usuario_permissoes') . " WHERE usuario_id = ? AND usuario_tipo = 'sistema'", [$id]);
        db()->delete("DELETE FROM " . tb('usuarios') . " WHERE id = ?", [$id]);
        Security::logChange('usuarios', $id, 'excluir', $usuario, null);
        
        jsonResponse(['success' => true, 'message' => 'Usuário excluído com sucesso!']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação inválida.'], 400);
}
