<?php
/**
 * Proxcel - Entry Point
 * Redireciona para login ou dashboard
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

Auth::startSession();

if (Auth::check()) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/pages/login.php');
}
exit;
