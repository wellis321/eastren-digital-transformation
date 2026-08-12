<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/qrcode.php';

require_login();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $raw = (string)($_POST['url'] ?? '');
    $url = qr_normalize_url($raw);
    if ($url === null) {
        $error = 'Enter a valid URL.';
    } else {
        $user = (string)($_SESSION['admin_user'] ?? '');
        qr_create_link($url, $user);
        flash('success', 'QR code created.');
        redirect(APP_URL . '/qrcode.php');
    }
}

$links = qr_list_links();
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>QR scan tracker — ERC Housing Digital Transformation</title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/style.css')) ?>">
</head>
<body class="auth-login-body">

<div class="auth-login-topbar">
    <img src="<?= e(asset_url('/assets/media/leaves.png')) ?>" alt="" class="topbar-leaves" width="28" height="20">
    <span>East Renfrewshire Council &mdash; QR scan tracker</span>
</div>

<main class="main-content qr-page">
    <header class="qr-page__header">
        <h1>QR scan tracker</h1>
        <p>Paste a URL, generate a QR code, and see how many times it is scanned. That is the whole tool.</p>
        <p><a href="<?= e(APP_URL) ?>/index.html" class="link-visible">← Back to portal</a></p>
    </header>

    <?= render_flash() ?>

    <section class="card qr-composer" aria-labelledby="qr-create-heading">
        <h2 id="qr-create-heading">Create a tracked QR code</h2>
        <form method="post" action="<?= e(APP_URL) ?>/qrcode.php" class="qr-form">
            <?= csrf_field() ?>
            <label class="qr-form__label" for="qr-url">Destination URL</label>
            <div class="qr-form__row">
                <input
                    id="qr-url"
                    class="form-control"
                    type="text"
                    name="url"
                    value="<?= e($error !== '' ? (string)($_POST['url'] ?? '') : '') ?>"
                    placeholder="https://example.com"
                    required
                    autocomplete="url"
                    spellcheck="false"
                >
                <button type="submit" class="btn btn-primary">Make QR code</button>
            </div>
            <?php if ($error !== ''): ?>
                <p class="qr-form__error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
        </form>
    </section>

    <section class="qr-codes" aria-labelledby="qr-codes-heading">
        <div class="qr-codes__head">
            <h2 id="qr-codes-heading">Your codes</h2>
            <p>Scan counts update when this page is refreshed. Counts are stored in MySQL and survive restarts.</p>
        </div>

        <?php if (!$links): ?>
            <div class="card"><p class="qr-empty">No codes yet. Create one above.</p></div>
        <?php else: ?>
            <ul class="qr-grid">
                <?php foreach ($links as $link): ?>
                    <?php
                    $id = (string)$link['id'];
                    $trackUrl = qr_track_url($id);
                    $hits = (int)$link['hits'];
                    ?>
                    <li class="card qr-item">
                        <div
                            class="qr-item__code"
                            data-qr-url="<?= e($trackUrl) ?>"
                            aria-hidden="true"
                        ></div>
                        <div class="qr-item__meta">
                            <p class="qr-item__hits">
                                <span class="qr-item__count"><?= $hits ?></span>
                                <span class="qr-item__label"><?= $hits === 1 ? 'scan' : 'scans' ?></span>
                            </p>
                            <a class="qr-item__dest link-visible" href="<?= e((string)$link['url']) ?>" target="_blank" rel="noreferrer">
                                <?= e((string)$link['url']) ?>
                            </a>
                            <p class="qr-item__track">
                                Tracks via
                                <a href="<?= e($trackUrl) ?>"><?= e($trackUrl) ?></a>
                            </p>
                            <?php if (!empty($link['created_at'])): ?>
                                <p class="qr-item__when">
                                    Created <?= e(date('d M Y H:i', strtotime((string)$link['created_at']))) ?>
                                    <?php if (!empty($link['created_by'])): ?>
                                        by <?= e((string)$link['created_by']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>

<script src="<?= e(asset_url('/assets/js/qrcode.min.js')) ?>"></script>
<script>
(function () {
    var nodes = document.querySelectorAll('[data-qr-url]');
    if (!nodes.length || typeof QRCode === 'undefined') return;
    nodes.forEach(function (el) {
        var url = el.getAttribute('data-qr-url');
        if (!url) return;
        el.innerHTML = '';
        new QRCode(el, {
            text: url,
            width: 148,
            height: 148,
            colorDark: '#006A51',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    });
})();
</script>
</body>
</html>
