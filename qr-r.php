<?php
declare(strict_types=1);

/**
 * Public QR scan endpoint — increments hit count, then redirects to the destination URL.
 * Accessible without login. Pretty URL: /r/{id} (see .htaccess / router.php).
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/qrcode.php';

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '' || !preg_match('/^[a-f0-9]{8}$/', $id)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'QR code not found';
    exit;
}

$link = qr_record_hit($id);
if ($link === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'QR code not found';
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: ' . (string)$link['url'], true, 302);
exit;
