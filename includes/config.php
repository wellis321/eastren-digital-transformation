<?php
declare(strict_types=1);

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

define('APP_ENV', getenv('APP_ENV') ?: 'local');
define('APP_URL', rtrim(getenv('APP_URL') ?: '', '/'));
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'sor_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('SESSION_SECRET', getenv('SESSION_SECRET') ?: 'changeme');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Permitted-Cross-Domain-Policies: none');
if (APP_ENV !== 'local') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Same session name as sor-system — shared credentials and SSO when deployed on the same host.
if (session_status() === PHP_SESSION_NONE) {
    session_name('sor_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (APP_ENV !== 'local'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
