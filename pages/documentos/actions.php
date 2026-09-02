<?php
/**
 * Proxcel - Ações de Documentos (AJAX)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::startSession();
Auth::requireLogin();
CSRF::validateOrDie();

$action = post('action');

switch ($action) {
    case 'upload':
        Permissions::requirePermission('documentos.criar');
        $clienteId = (int)post('cliente_id');
        $categoriaId = (int)post('categoria_id') ?: null;
        $nome = post('nome');
        $descricao = post('descricao');
        $dataDoc = post('data_documento') ?: date('Y-m-d');
        $notificar = isset($_POST['notificar']) && $_POST['notificar'] == '1';

        if (empty($nome)) {
            jsonResponse(['success' => false, 'message' => 'Informe o nome do documento.'], 400);
        }
        if (!$clienteId) {
            jsonResponse(['success' => false, 'message' => 'Cliente inválido.'], 400);
        }
        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] === UPLOAD_ERR_NO_FILE) {
            jsonResponse(['success' => false, 'message' => 'Nenhum arquivo enviado.'], 400);
        }

        $resultado = Upload::process($_FILES['arquivo'], $clienteId);
        if (!$resultado['success']) {
            jsonResponse(['success' => false, 'message' => $resultado['message']], 400);
        }

        $docId = db()->insert(
            "INSERT INTO " . tb('documentos') . " 
             (cliente_id, categoria_id, nome, descricao, nome_original, nome_arquivo, mime_type, tamanho, extensao, data_documento, criado_por, criado_por_tipo) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $clienteId,
                $categoriaId,
                $nome,
                $descricao,
                $resultado['nome_original'],
                $resultado['nome_arquivo'],
                $resultado['mime_type'],
                $resultado['tamanho'],
                $resultado['extensao'],
                $dataDoc,
                $_SESSION['user_id'] ?? null,
                $_SESSION['user_tipo_acesso'] ?? 'sistema'
            ]
        );

        Security::logChange('documentos', $docId, 'criar', null, ['nome' => $nome, 'cliente_id' => $clienteId]);

        if ($notificar) {
            require_once __DIR__ . '/../../includes/mail_helper.php';
            $usersCliente = db()->fetchAll(
                "SELECT nome, email FROM " . tb('usuarios_clientes') . " WHERE cliente_id = ? AND status = 'ativo'",
                [$clienteId]
            );
            foreach ($usersCliente as $uc) {
                MailHelper::sendDocumentNotification($uc['email'], $uc['nome'], $nome);
            }
        }

        jsonResponse(['success' => true, 'message' => 'Documento enviado com sucesso!']);
        break;

    case 'delete':
        Permissions::requirePermission('documentos.excluir');
        $id = (int)post('id');
        
        $doc = db()->fetch("SELECT * FROM " . tb('documentos') . " WHERE id = ?", [$id]);
        if (!$doc) jsonResponse(['success' => false, 'message' => 'Documento não encontrado.'], 404);
        
        // Cliente só pode excluir seus documentos
        if (Auth::isCliente() && $doc['cliente_id'] != ($_SESSION['user_cliente_id'] ?? 0)) {
            jsonResponse(['success' => false, 'message' => 'Acesso negado.'], 403);
        }
        
        // Remove arquivo físico
        Upload::delete($doc['nome_arquivo']);
        
        // Remove registro
        db()->delete("DELETE FROM " . tb('documentos') . " WHERE id = ?", [$id]);
        Security::logChange('documentos', $id, 'excluir', $doc, null);
        
        jsonResponse(['success' => true, 'message' => 'Documento excluído com sucesso!']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação inválida.'], 400);
}
