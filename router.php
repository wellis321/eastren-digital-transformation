<?php
declare(strict_types=1);

$builtIn = PHP_SAPI === 'cli-server';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$publicPaths = [
    '/login.php',
    '/logout.php',
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
        $file = $index;
    }
}

if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
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
    $html = file_get_contents($file);
    if ($html !== false) {
        $root = __DIR__;
        $baseDir = dirname($file);
        $html = preg_replace_callback(
            '#\b(href|src)=(["\'])([^"\']*(?:assets/css/style\.css|assets/js/main\.js))\2#',
            static function (array $m) use ($root, $baseDir): string {
                $url = $m[3];
                $assetPath = $baseDir . '/' . $url;
                if (!is_file($assetPath) && str_starts_with($url, '/')) {
                    $assetPath = $root . $url;
                }
                if (is_file($assetPath)) {
                    $v = (string)filemtime($assetPath);
                    if (!preg_match('/[?&]v=/', $url)) {
                        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $v;
                    }
                }
                return $m[1] . '=' . $m[2] . $url . $m[2];
            },
            $html
        );
        echo $html;
        exit;
    }
}

readfile($file);
exit;
