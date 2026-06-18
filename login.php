<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/entra_config.php';

if (is_logged_in()) {
    redirect(resolve_next_redirect($_GET['next'] ?? '', APP_URL . '/index.html'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (is_rate_limited(client_ip())) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (attempt_login($user, $pass)) {
            clear_attempts(client_ip());
            redirect(resolve_next_redirect($_GET['next'] ?? '', APP_URL . '/index.html'));
        } else {
            record_failed_attempt(client_ip());
            $error = 'Incorrect username or password.';
        }
    }
}

$flash = render_flash();
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in — ERC Housing Digital Transformation</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-login-body">

<div class="auth-login-topbar">
    <img src="assets/media/leaves.png" alt="" class="topbar-leaves" width="28" height="20">
    <span>East Renfrewshire Council &mdash; Housing Digital Transformation</span>
</div>

<main class="auth-login-main">
    <div class="auth-login-card">
        <div class="auth-login-header">
            <h1>Sign in</h1>
            <p>Working reference portal — same credentials as the SOR system.</p>
        </div>

        <?php if ($flash): ?>
            <?= $flash ?>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash flash-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (entra_enabled()): ?>
        <?php
            $msNext = $_GET['next'] ?? '';
            $msLoginUrl = APP_URL . '/auth/microsoft/login.php';
            if ($msNext !== '') {
                $msLoginUrl .= '?next=' . urlencode($msNext);
            }
        ?>
        <a href="<?= e($msLoginUrl) ?>" class="auth-btn auth-btn--microsoft">Sign in with Microsoft</a>
        <div class="auth-login-divider" aria-hidden="true"><span>or</span></div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-login-form">
            <?= csrf_field() ?>
            <div class="auth-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username"
                       value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div class="auth-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="auth-btn auth-btn--primary">Sign in</button>
        </form>
    </div>
</main>

</body>
</html>
