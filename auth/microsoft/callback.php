<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/entra_auth.php';

$result = entra_handle_callback();

if ($result['ok']) {
    redirect($result['next'] ?? APP_URL . '/index.html');
}

flash('error', $result['error'] ?? 'Microsoft sign-in failed.');
redirect(APP_URL . '/login.php');
