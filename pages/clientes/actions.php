<?php
/**
 * Proxcel - Ações de Clientes (AJAX)
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
        Permissions::requirePermission('clientes.excluir');
        $id = (int)post('id');
        
        $cliente = db()->fetch("SELECT * FROM " . tb('clientes') . " WHERE id = ?", [$id]);
        if (!$cliente) {
            jsonResponse(['success' => false, 'message' => 'Cliente não encontrado.'], 404);
        }
        
        // Verifica se tem documentos vinculados
        $docs = db()->fetch("SELECT COUNT(*) as total FROM " . tb('documentos') . " WHERE cliente_id = ?", [$id]);
        if ($docs['total'] > 0) {
            jsonResponse(['success' => false, 'message' => 'Não é possível excluir. Existem ' . $docs['total'] . ' documento(s) vinculado(s).']);
        }
        
        db()->delete("DELETE FROM " . tb('clientes') . " WHERE id = ?", [$id]);
        Security::logChange('clientes', $id, 'excluir', $cliente, null);
        
        jsonResponse(['success' => true, 'message' => 'Cliente excluído com sucesso!']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação inválida.'], 400);
}
