<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
logout();
header('Location: ' . APP_URL . '/login.php');
exit;
