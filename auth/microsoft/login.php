<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/entra_auth.php';

if (is_logged_in()) {
    redirect(resolve_next_redirect($_GET['next'] ?? '', APP_URL . '/index.html'));
}

$next = $_GET['next'] ?? '';
if ($next !== '') {
    $_SESSION['entra_login_next'] = resolve_next_redirect($next, APP_URL . '/index.html');
}

entra_start_login();
