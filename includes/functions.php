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

/**
 * True only for the site owner's own account(s) — a narrower check than the
 * general admin role, used anywhere something should be visible/usable to
 * William specifically and nobody else (including other admins/confirmers).
 * Single source of truth so every such gate stays in sync with each other.
 */
function is_site_owner(): bool
{
    if (empty($_SESSION['admin_logged_in'])) {
        return false;
    }
    $role = $_SESSION['app_role'] ?? '';
    if ($role === 'viewer' || $role === 'editor') {
        return false;
    }
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }
    $allowedUsernames = ['ellisw', 'admin', 'wellis'];
    $stmt = db()->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $username = strtolower(trim((string)($stmt->fetchColumn() ?: '')));
    return in_array($username, $allowedUsernames, true);
}

/**
 * Same disclaimer banner used on the SOR site and the other sibling tools —
 * kept here as a single source so it's identical everywhere it's used,
 * rather than copy-pasted into every page. $namesCouncil is false only for
 * pages that have deliberately had "East Renfrewshire Council" scrubbed
 * from their content (currently just continuous-improvement.html) — the
 * banner shouldn't reintroduce the name onto a page that specifically
 * shouldn't carry it.
 */
function research_banner_html(bool $namesCouncil = true): string
{
    $org = $namesCouncil ? 'East Renfrewshire Council' : 'Council';
    return '<div class="landing-notice">'
        . '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" style="width:16px;height:16px;flex-shrink:0;">'
        . '<path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />'
        . '</svg>'
        . '<span><strong>Research &amp; Learning Resource</strong> &mdash; This is a demonstration system built for research and learning purposes. It is not an official ' . e($org) . ' reporting tool.</span>'
        . '</div>';
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

function enhance_static_html(string $html, string $baseDir, string $fileName = ''): string
{
    $root = dirname(__DIR__);

    if (stripos($html, 'landing-notice') === false) {
        $namesCouncil = $fileName !== 'continuous-improvement.html';
        $html = preg_replace('/<body([^>]*)>/i', '<body$1>' . "\n" . research_banner_html($namesCouncil), $html, 1) ?? $html;
    }

    $html = preg_replace(
        '/\s*<nav class="breadcrumb" aria-label="Breadcrumb">.*?<\/nav>\s*/is',
        "\n",
        $html
    );
    $html = preg_replace('/\s*<!--\s*Breadcrumb\s*-->\s*/i', "\n", $html);

    $html = preg_replace_callback(
        '#\b(href|src)=(["\'])([^"\']*(?:assets/css/style\.css|assets/js/[\w.-]+\.js))\2#',
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
        $phase2Url  = defined('PHASE2_SITE_URL')  ? htmlspecialchars(PHASE2_SITE_URL,  ENT_QUOTES, 'UTF-8') : '';
        if ($sorUrl !== '' && $asisUrl !== '') {
            $strip = "\n" . '<div class="footer-tools-strip">'
                . '<span class="ft-label">ERC Digital Tools</span>'
                . '<a href="' . $sorUrl  . '/">SOR Management System</a>'
                . '<a href="' . $ercUrl  . '/">ERC Portal</a>'
                . '<a href="' . $asisUrl . '/">AS-IS Process Mapping</a>'
                . ($metricsUrl !== '' ? '<a href="' . $metricsUrl . '/">Housing Metrics</a>' : '')
                . ($phase2Url !== '' ? '<a href="' . $phase2Url . '/">Phase 2 Tracker</a>' : '')
                . '<a href="' . $ercUrl . '/qrcode.php">QR scan tracker</a>'
                . '</div>';
            $html = preg_replace('/<\/footer>/i', $strip . "\n</footer>", $html, 1);
        }
    }

    // Nav item visibility is a site-wide setting, not admin-only, so this
    // runs regardless of session state — but never let a DB hiccup take the
    // page's navigation down; fall back to the nav as authored in the file.
    // The "Menu settings" link is restricted to specific named accounts
    // (nav_can_manage), not the general admin role — other admins should
    // not see it.
    try {
        require_once __DIR__ . '/nav_config.php';
        $hiddenIds = nav_hidden_ids();
        $canManageNav = nav_can_manage();
        if ($hiddenIds !== [] || $canManageNav) {
            $transformNav = static function (array $m) use ($hiddenIds, $canManageNav): string {
                $inner = nav_strip_hidden_links($m[2], $hiddenIds);
                if ($canManageNav) {
                    $inner = nav_inject_admin_link($inner);
                }
                return $m[1] . $inner . $m[3];
            };
            $html = preg_replace_callback('#(<nav class="site-nav"[^>]*>)(.*?)(</nav>)#is', $transformNav, $html, 1) ?? $html;
            $html = preg_replace_callback('#(<nav class="mobile-nav"[^>]*>)(.*?)(</nav>)#is', $transformNav, $html, 1) ?? $html;
        }
        if ($hiddenIds !== []) {
            $html = nav_strip_hidden_cards($html, $hiddenIds);
        }
    } catch (Throwable) {
        // DB may be unavailable in some local contexts
    }

    if (!empty($_SESSION['admin_logged_in'])) {
        if (stripos($html, 'name="csrf-token"') === false) {
            $meta = '<meta name="csrf-token" content="' . e(csrf_token()) . '">';
            $html = preg_replace('/<\/head>/i', $meta . "\n</head>", $html, 1) ?? $html;
        }
        // Governance site banner hook (assessment nags retired — banner always empty)
        if (stripos($html, 'gov-notify-banner') === false) {
            try {
                require_once __DIR__ . '/governance_membership.php';
                $banner = governance_banner_html();
                if ($banner !== '') {
                    $html = preg_replace('/<body([^>]*)>/i', '<body$1>' . "\n" . $banner, $html, 1) ?? $html;
                }
            } catch (Throwable) {
                // DB may be unavailable in some local contexts
            }
        }
        if (stripos($html, 'id="feedback-toggle"') === false) {
            require_once __DIR__ . '/feedback.php';
            $widget = feedback_widget_html();
            if ($widget !== '') {
                $html = preg_replace('/<\/body>/i', $widget . "\n</body>", $html, 1) ?? $html;
            }
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
    echo enhance_static_html($html, dirname($file), basename($file));
    exit;
}
