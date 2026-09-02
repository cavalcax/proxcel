<?php
/**
 * Proxcel - Download Seguro de Documentos
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::startSession();
Auth::requireLogin();
Permissions::requirePermission('documentos.download');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    die('ID inválido.');
}

$doc = db()->fetch(
    "SELECT * FROM " . tb('documentos') . " WHERE id = ? AND status = 'ativo'",
    [$id]
);

if (!$doc) {
    http_response_code(404);
    die('Documento não encontrado.');
}

// Cliente só pode baixar seus documentos
if (Auth::isCliente() && $doc['cliente_id'] != ($_SESSION['user_cliente_id'] ?? 0)) {
    http_response_code(403);
    die('Acesso negado.');
}

$filePath = Upload::getPath($doc['nome_arquivo']);

if (!file_exists($filePath)) {
    http_response_code(404);
    die('Arquivo não encontrado no servidor.');
}

// Log de download
Security::logAccess('documento.download', 'Download do documento: ' . $doc['nome'] . ' (ID: ' . $doc['id'] . ')');

// Headers para download
header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: attachment; filename="' . $doc['nome_original'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

// Envia arquivo
readfile($filePath);
exit;
