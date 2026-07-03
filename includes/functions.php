<?php
declare(strict_types=1);

function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals(csrf_token(), $token);
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function render_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = $f['type'] === 'success' ? 'flash-success' : ($f['type'] === 'error' ? 'flash-error' : 'flash-info');
        $out .= '<div class="flash ' . $cls . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

function resolve_next_redirect(string $next, string $fallback): string
{
    if ($next === '') {
        return $fallback;
    }
    if (str_starts_with($next, APP_URL . '/')) {
        return $next;
    }
    if (str_starts_with($next, '/') && !str_starts_with($next, '//')) {
        return APP_URL . $next;
    }
    return $fallback;
}

function login_next_url(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($uri === '' || $uri === '/') {
        return APP_URL . '/index.html';
    }
    if (str_starts_with($uri, '/')) {
        return APP_URL . $uri;
    }
    return APP_URL . '/index.html';
}

function asset_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $file = dirname(__DIR__) . $path;
    $v    = is_file($file) ? (string)filemtime($file) : '1';
    return APP_URL . $path . '?v=' . $v;
}

function enhance_static_html(string $html, string $baseDir): string
{
    $root = dirname(__DIR__);

    $html = preg_replace(
        '/\s*<nav class="breadcrumb" aria-label="Breadcrumb">.*?<\/nav>\s*/is',
        "\n",
        $html
    );
    $html = preg_replace('/\s*<!--\s*Breadcrumb\s*-->\s*/i', "\n", $html);

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

    if (stripos($html, 'critical-portal-ui') === false) {
        $criticalCss = '<style id="critical-portal-ui">'
            . '.breadcrumb{display:none!important}'
            . '.site-topbar a.topbar-signout,.site-topbar a.topbar-signout:link,'
            . '.site-topbar a.topbar-signout:visited,.site-topbar a.topbar-signout:active'
            . '{color:#fff!important;text-decoration:none!important;font-size:.72rem;font-weight:600;'
            . 'padding:.28rem .65rem;border:1px solid rgba(255,255,255,.55);border-radius:4px;'
            . 'background:rgba(255,255,255,.14);white-space:nowrap}'
            . '.site-topbar a.topbar-signout:hover{color:#fff!important;background:rgba(255,255,255,.24)!important}'
            . '.footer-tools-strip{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;'
            . 'padding:.65rem 1.25rem;border-top:1px solid rgba(255,255,255,.1);font-size:.75rem;}'
            . '.footer-tools-strip .ft-label{color:rgba(255,255,255,.45);font-weight:600;'
            . 'text-transform:uppercase;letter-spacing:.05em;font-size:.68rem;margin-right:.25rem}'
            . '.footer-tools-strip a{color:rgba(255,255,255,.75);text-decoration:none;'
            . 'padding:.15rem .45rem;border:1px solid rgba(255,255,255,.25);border-radius:3px}'
            . '.footer-tools-strip a:hover{color:#fff;background:rgba(255,255,255,.15);'
            . 'border-color:rgba(255,255,255,.5)}'
            . '</style>';
        $html = preg_replace('/<\/head>/i', $criticalCss . '</head>', $html, 1);
    }

    // Inject related-tools links into the page footer
    if (stripos($html, 'footer-tools-strip') === false) {
        $sorUrl     = defined('SOR_SITE_URL')     ? htmlspecialchars(SOR_SITE_URL,     ENT_QUOTES, 'UTF-8') : '';
        $asisUrl    = defined('ASIS_SITE_URL')    ? htmlspecialchars(ASIS_SITE_URL,    ENT_QUOTES, 'UTF-8') : '';
        $ercUrl     = defined('APP_URL')          ? htmlspecialchars(APP_URL,          ENT_QUOTES, 'UTF-8') : '';
        $metricsUrl = defined('METRICS_SITE_URL') ? htmlspecialchars(METRICS_SITE_URL, ENT_QUOTES, 'UTF-8') : '';
        if ($sorUrl !== '' && $asisUrl !== '') {
            $strip = "\n" . '<div class="footer-tools-strip">'
                . '<span class="ft-label">ERC Digital Tools</span>'
                . '<a href="' . $sorUrl  . '/">SOR Management System</a>'
                . '<a href="' . $ercUrl  . '/">ERC Portal</a>'
                . '<a href="' . $asisUrl . '/">AS-IS Process Mapping</a>'
                . ($metricsUrl !== '' ? '<a href="' . $metricsUrl . '/">Housing Metrics</a>' : '')
                . '</div>';
            $html = preg_replace('/<\/footer>/i', $strip . "\n</footer>", $html, 1);
        }
    }

    if (!empty($_SESSION['admin_logged_in']) && stripos($html, 'id="feedback-toggle"') === false) {
        require_once __DIR__ . '/feedback.php';
        $widget = feedback_widget_html();
        if ($widget !== '') {
            $html = preg_replace('/<\/body>/i', $widget . "\n</body>", $html, 1);
        }
    }

    return $html;
}

function render_static_html(string $file): void
{
    $html = file_get_contents($file);
    if ($html === false) {
        http_response_code(500);
        echo 'Could not load page';
        exit;
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo enhance_static_html($html, dirname($file));
    exit;
}
