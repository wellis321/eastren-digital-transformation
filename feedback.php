<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/feedback.php';

if (!is_logged_in()) {
    http_response_code(401);
    exit('Unauthorised.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$type    = trim((string)($_POST['type'] ?? 'other'));
$message = trim((string)($_POST['message'] ?? ''));
$page    = trim((string)($_POST['page'] ?? ''));
$user    = (string)($_SESSION['admin_user'] ?? 'anonymous');
$ip      = client_ip();
$at      = date('Y-m-d H:i:s');

$allowed = ['content', 'suggestion', 'issue', 'question', 'other'];
if (!in_array($type, $allowed, true)) {
    $type = 'other';
}

if ($message === '') {
    http_response_code(400);
    exit('No message.');
}

if (strlen($page) > 500) {
    $page = substr($page, 0, 500);
}

try {
    feedback_ensure_table();
    db()->prepare(
        'INSERT INTO feedback (type, message, page, submitted_by, ip, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$type, $message, $page, $user, $ip, $at]);

    http_response_code(200);
    echo 'ok';
} catch (Throwable) {
    http_response_code(500);
    echo 'error';
}
