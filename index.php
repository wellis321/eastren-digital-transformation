<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/debug-log.php';

// #region agent log
auth_debug_log('index.php:entry', 'index.php invoked', [
    'logged_in' => is_logged_in(),
], 'H-B');
// #endregion

require_login();
readfile(__DIR__ . '/index.html');
