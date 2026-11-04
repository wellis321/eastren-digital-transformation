<?php
declare(strict_types=1);

$builtIn = PHP_SAPI === 'cli-server';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Reject dot-segments outright. Without this, /assets/../anything is handed to
// PHP's built-in server as a static asset, which resolves the ".." itself and
// serves files (including .env) with no auth check at all.
$decodedUri = rawurldecode($uri);
if (preg_match('#(^|/)\.{1,2}(/|$)#', $decodedUri) || str_contains($decodedUri, "\0")) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Bad request';
    exit;
}

$publicPaths = [
    '/login.php',
    '/logout.php',
    '/activate.php',
    '/qr-r.php',
    '/auth/microsoft/login.php',
    '/auth/microsoft/callback.php',
];

if (preg_match('#^/(assets|robots\.txt)(/|$)#', $uri)) {
    if ($builtIn) {
        return false;
    }
    http_response_code(404);
    exit;
}

// Public QR scan redirect: /r/{id}
if (preg_match('#^/r/([a-f0-9]{8})$#', $uri, $qrMatch)) {
    $_GET['id'] = $qrMatch[1];
    require __DIR__ . '/qr-r.php';
    exit;
}

if (in_array($uri, $publicPaths, true) || str_starts_with($uri, '/auth/microsoft/')) {
    if ($builtIn) {
        return false;
    }
    exit;
}

if (!is_logged_in()) {
    header('Location: ' . APP_URL . '/login.php?next=' . urlencode(login_next_url()));
    exit;
}

// Pages restricted to admin (or accounts with no role set)
$adminOnlyPages = ['/emails.html'];
if (in_array($uri, $adminOnlyPages, true)) {
    $role = $_SESSION['app_role'] ?? '';
    if ($role === 'viewer' || $role === 'editor') {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="en-GB"><head><meta charset="UTF-8"><title>Access restricted</title>
        <link rel="stylesheet" href="' . APP_URL . '/assets/css/style.css"></head><body>
        <div style="max-width:480px;margin:6rem auto;padding:2rem;text-align:center;">
        <h1 style="font-size:1.4rem;margin-bottom:0.75rem;">Access restricted</h1>
        <p style="color:#555;margin-bottom:1.5rem;">This page is only available to administrators.</p>
        <a href="' . APP_URL . '/" style="color:#006A51;">Return to home</a></div></body></html>';
        exit;
    }
}

if (str_ends_with($uri, '.php')) {
    if ($builtIn) {
        return false;
    }
    http_response_code(403);
    exit;
}

$file = __DIR__ . $uri;
if ($uri === '/' || $uri === '') {
    $file = __DIR__ . '/index.html';
}

if (is_dir($file)) {
    $index = rtrim($file, '/') . '/index.html';
    if (is_file($index)) {
        if (!str_ends_with($uri, '/')) {
            $query = $_SERVER['QUERY_STRING'] ?? '';
            redirect(APP_URL . $uri . '/' . ($query !== '' ? '?' . $query : ''));
        }
        $file = $index;
    }
}

if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
}

// The Project Hub guide is private to the site owner — checked against the
// resolved file path rather than the URL so path tricks can't sidestep it.
$privateDir = realpath(__DIR__ . '/project-hub');
$realFile = realpath($file);
if ($privateDir !== false && $realFile !== false
    && str_starts_with(strtolower($realFile), strtolower($privateDir . DIRECTORY_SEPARATOR))) {
    try {
        $isOwner = is_site_owner();
    } catch (Throwable) {
        $isOwner = false;
    }
    if (!$isOwner) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="en-GB"><head><meta charset="UTF-8"><title>Access restricted</title>
        <link rel="stylesheet" href="' . APP_URL . '/assets/css/style.css"></head><body>
        <div style="max-width:480px;margin:6rem auto;padding:2rem;text-align:center;">
        <h1 style="font-size:1.4rem;margin-bottom:0.75rem;">Access restricted</h1>
        <p style="color:#555;margin-bottom:1.5rem;">This page is private.</p>
        <a href="' . APP_URL . '/" style="color:#006A51;">Return to home</a></div></body></html>';
        exit;
    }
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$types = [
    'html' => 'text/html; charset=UTF-8',
    'css'  => 'text/css; charset=UTF-8',
    'js'   => 'application/javascript; charset=UTF-8',
    'json' => 'application/json; charset=UTF-8',
    'svg'  => 'image/svg+xml',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'ico'  => 'image/x-icon',
    'txt'  => 'text/plain; charset=UTF-8',
    'pdf'  => 'application/pdf',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'm4a'  => 'audio/mp4',
];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));

if ($ext === 'html') {
    render_static_html($file);
}

readfile($file);
exit;
