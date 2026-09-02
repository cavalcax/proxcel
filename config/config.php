<?php
if (!ob_get_level()) {
    ob_start();
}
/**
 * Proxcel - Configurações Gerais do Sistema
 * 
 * Este arquivo contém todas as configurações globais do sistema.
 * Edite conforme necessário para seu ambiente.
 */

// ============================================================
// MODO DE OPERAÇÃO
// ============================================================
define('APP_DEBUG', true); // false em produção
define('APP_NAME', 'Proxcel');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/proxcel'); // URL base do sistema

// ============================================================
// PREFIXO DE TABELAS DO BANCO DE DADOS
// ============================================================
define('DB_TABLE_PREFIX', 'ctempresa_proxcel_');

// ============================================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'ctempresa');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// CONFIGURAÇÕES DE SESSÃO
// ============================================================
define('SESSION_LIFETIME', 1800); // 30 minutos em segundos
define('SESSION_NAME', 'PROXCEL_SESSID');
define('SESSION_SECURE', false); // true se HTTPS
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// ============================================================
// CONFIGURAÇÕES DE UPLOAD
// ============================================================
define('UPLOAD_MAX_SIZE', 20 * 1024 * 1024); // 20MB
define('UPLOAD_PATH', __DIR__ . '/../storage/documentos/');
define('UPLOAD_ALLOWED_TYPES', [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/csv',
    'text/plain',
    'application/zip',
    'application/x-zip-compressed',
    'application/octet-stream',
    'application/x-rar-compressed'
]);
define('UPLOAD_ALLOWED_EXTENSIONS', [
    'pdf',
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'csv',
    'txt',
    'zip',
    'rar',
    'pbix'
]);

// ============================================================
// CONFIGURAÇÕES DE SEGURANÇA
// ============================================================
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hora

// ============================================================
// CONFIGURAÇÕES DE E-MAIL (SMTP)
// ============================================================
define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_USER', '');
define('MAIL_SMTP_PASS', '');
define('MAIL_FROM_EMAIL', 'noreply@proxcel.com.br');
define('MAIL_FROM_NAME', 'Proxcel');
define('MAIL_USE_SMTP', false); // true para usar SMTP, false para mail() nativo

// ============================================================
// RECAPTCHA (OPCIONAL)
// ============================================================
define('RECAPTCHA_ENABLED', false);
define('RECAPTCHA_SITE_KEY', '');
define('RECAPTCHA_SECRET_KEY', '');

// ============================================================
// CONFIGURAÇÕES DE LOG
// ============================================================
define('LOG_ENABLED', true);
define('LOG_ACCESS', true);
define('LOG_CHANGES', true);

// ============================================================
// PAGINAÇÃO
// ============================================================
define('ITEMS_PER_PAGE', 15);

// ============================================================
// CAMINHOS DO SISTEMA
// ============================================================
define('BASE_PATH', dirname(__DIR__) . '/');
define('CONFIG_PATH', __DIR__ . '/');
define('INCLUDES_PATH', BASE_PATH . 'includes/');
define('TEMPLATES_PATH', BASE_PATH . 'templates/');
define('PAGES_PATH', BASE_PATH . 'pages/');
define('ASSETS_PATH', BASE_PATH . 'assets/');
define('STORAGE_PATH', BASE_PATH . 'storage/');

// ============================================================
// TRATAMENTO DE ERROS
// ============================================================
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . 'storage/error.log');
}

// Timezone
date_default_timezone_set('America/Sao_Paulo');
