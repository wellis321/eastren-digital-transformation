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
        // Allow quoted values — required when password contains # or spaces
        if (
            (str_starts_with($val, '"') && str_ends_with($val, '"'))
            || (str_starts_with($val, "'") && str_ends_with($val, "'"))
        ) {
            $val = substr($val, 1, -1);
        }
        // .env file always wins (Hostinger may pre-populate empty $_ENV keys)
        $_ENV[$key] = $val;
        putenv($key . '=' . $val);
    }
}

function env_val(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v !== false && $v !== null && $v !== '') ? (string)$v : $default;
}

define('APP_ENV', env_val('APP_ENV', 'local'));
define('APP_URL', rtrim(env_val('APP_URL'), '/'));
if (APP_URL === '' && PHP_SAPI !== 'cli') {
    http_response_code(500);
    exit('APP_URL is not set. Create a .env file — see HOSTINGER-DEPLOY.md.');
}
define('DB_HOST', env_val('DB_HOST', 'localhost'));
define('DB_PORT', env_val('DB_PORT', '3306'));
define('DB_NAME', env_val('DB_NAME', 'sor_management'));
define('DB_USER', env_val('DB_USER', 'root'));
define('DB_PASS', env_val('DB_PASS'));
define('SESSION_SECRET', env_val('SESSION_SECRET', 'changeme'));

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
