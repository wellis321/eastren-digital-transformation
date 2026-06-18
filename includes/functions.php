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

/** Accept full APP_URL paths or site-relative paths for post-login redirect. */
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
