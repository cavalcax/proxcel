<?php
/**
 * Proxcel - Logout
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';

Auth::startSession();

if (Auth::check()) {
    Security::logAccess('logout', 'Logout realizado');
}

Auth::logout();
header('Location: ' . APP_URL . '/pages/login.php');
exit;
