<?php
/**
 * Proxcel - Download Seguro e Blindado de Documentos
 * 
 * Regras estritas de segurança:
 * 1. Acesso público/anônimo é terminantemente bloqueado.
 * 2. Somente usuários autenticados com sessão válida e permissão podem baixar.
 * 3. Usuários do tipo 'cliente' têm isolamento total: só baixam documentos do seu próprio cliente_id.
 * 4. Links expostos ou compartilhados não funcionam sem login prévio.
 * 5. Proteção contra Directory Traversal e Headers anti-caching/anti-sniffing.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/functions.php';

// 1. Inicializa sessão e valida autenticação obrigatória
Auth::startSession();

if (!Auth::check()) {
    http_response_code(401);
    // Redireciona para login e encerra imediatamente qualquer tentativa de download
    header('Location: ' . APP_URL . '/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit('Acesso restrito. Faça login para continuar.');
}

// 2. Valida integridade da sessão (anti-session hijacking)
Auth::requireLogin();

// 3. Valida permissão do usuário
if (!Permissions::has('documentos.download') && !Permissions::has('documentos.visualizar')) {
    http_response_code(403);
    Security::logAccess('documento.sem_permissao', 'Tentativa de download sem permissão: ID ' . (int)($_GET['id'] ?? 0));
    exit('Você não tem permissão para baixar documentos.');
}

// 4. Valida ID do documento
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Identificador de documento inválido.');
}

// 5. Busca documento ativo no banco de dados
$doc = db()->fetch(
    "SELECT d.*, c.status as cliente_status 
     FROM " . tb('documentos') . " d 
     JOIN " . tb('clientes') . " c ON c.id = d.cliente_id 
     WHERE d.id = ? AND d.status = 'ativo'",
    [$id]
);

if (!$doc) {
    http_response_code(404);
    exit('Documento não encontrado ou indisponível.');
}

// 6. ISOLAMENTO ESTRITO DE CLIENTE: Cliente só pode acessar documentos pertencentes a ele
if (Auth::isCliente()) {
    $userClienteId = (int)($_SESSION['user_cliente_id'] ?? 0);
    $docClienteId = (int)$doc['cliente_id'];

    if ($userClienteId <= 0 || $docClienteId !== $userClienteId) {
        http_response_code(403);
        Security::logAccess(
            'documento.tentativa_acesso_indevido',
            "Alerta de Segurança: Usuário cliente ID {$_SESSION['user_id']} ({$_SESSION['user_email']}) tentou baixar documento ID {$id} pertencente ao cliente {$docClienteId}"
        );
        exit('Acesso negado. Você só tem permissão para acessar os documentos da sua empresa.');
    }

    if ($doc['cliente_status'] !== 'ativo') {
        http_response_code(403);
        exit('Acesso negado. Cadastro da empresa inativo.');
    }
}

// 7. Validação física do arquivo e proteção contra Directory Traversal
$filePath = Upload::getPath($doc['nome_arquivo']);
$baseStoragePath = realpath(UPLOAD_PATH);
$realFilePath = realpath($filePath);

if (!$realFilePath || !file_exists($realFilePath) || !is_readable($realFilePath)) {
    http_response_code(404);
    Security::logAccess('documento.arquivo_inexistente', "Arquivo físico não encontrado para documento ID {$id}: {$doc['nome_arquivo']}");
    exit('Arquivo físico não encontrado no servidor.');
}

// Garante que o arquivo está estritamente dentro da pasta de storage permitida
if (strpos($realFilePath, $baseStoragePath) !== 0) {
    http_response_code(403);
    Security::logAccess('documento.path_traversal_tentativa', "Alerta crítico: Tentativa de path traversal no documento ID {$id}: {$doc['nome_arquivo']}");
    exit('Acesso inválido.');
}

// 8. Registro de auditoria do download
Security::logAccess(
    'documento.download',
    "Download realizado: '{$doc['nome']}' (ID: {$doc['id']}, Arquivo Original: '{$doc['nome_original']}') por {$_SESSION['user_email']}"
);

// 9. Limpa buffers de saída anteriores para garantir integridade do binário
if (ob_get_level()) {
    ob_end_clean();
}

// 10. Headers estritos de segurança, anti-caching e transferência
$nomeDownload = str_replace(['"', "\r", "\n"], '', $doc['nome_original'] ?: $doc['nome']);

header('Content-Description: File Transfer');
header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $nomeDownload . '"; filename*=UTF-8\'\'' . rawurlencode($nomeDownload));
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($realFilePath));

// Anti-caching estrito para impedir que proxies ou navegadores salvem em cache público
header('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');

// 11. Envio seguro do arquivo em chunks
$handle = fopen($realFilePath, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Erro ao ler arquivo.');
}

while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}
fclose($handle);
exit;
