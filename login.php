<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/entra_config.php';

$userCount = active_users_count();

if (is_logged_in()) {
    redirect(resolve_next_redirect($_GET['next'] ?? '', APP_URL . '/index.html'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (is_rate_limited(client_ip())) {
        $error = 'Too many failed attempts. Please wait 15 minutes, or ask an administrator to run: DELETE FROM login_attempts; in phpMyAdmin.';
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (attempt_login($user, $pass)) {
            clear_attempts(client_ip());
            redirect(resolve_next_redirect($_GET['next'] ?? '', APP_URL . '/index.html'));
        } else {
            record_failed_attempt(client_ip());
            $reason = last_login_fail_reason();
            if ($reason === 'microsoft_only_no_local_password') {
                $error = 'This account uses Microsoft sign-in on the SOR system — local password login is not set. Use the same username with a password account, or enable Entra SSO on this portal.';
            } elseif ($reason === 'user_not_found') {
                $error = 'No matching user in the database. Try your email address if you sign in with that on SOR.';
            } else {
                $error = 'Incorrect username or password.';
            }
            if ($userCount === 0) {
                $error = 'No user accounts exist in this database yet. Add one in phpMyAdmin (see sql/seed-admin.example.sql).';
            }
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
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/style.css')) ?>">
</head>
<body class="auth-login-body">

<div class="auth-login-topbar">
    <img src="<?= e(asset_url('/assets/media/leaves.png')) ?>" alt="" class="topbar-leaves" width="28" height="20">
    <span>East Renfrewshire Council &mdash; Housing Digital Transformation</span>
</div>

<main class="auth-login-main">
    <div class="auth-login-card">
        <div class="auth-login-header">
            <h1>Sign in</h1>
            <p>Sign in with your portal account. If you use the SOR system on the <strong>same database</strong>, the same username works here.</p>
        </div>

        <?php if ($userCount === 0): ?>
            <div class="flash flash-info">
                <strong>Setup required:</strong> the <code>users</code> table in <code><?= e(DB_NAME) ?></code> has no accounts yet.
                Run <code>sql/auth-tables.sql</code> then <code>sql/seed-admin.example.sql</code> in phpMyAdmin, or point <code>.env</code> at the sor-system database if users already exist there.
            </div>
        <?php endif; ?>

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

        <nav class="auth-related-sites" aria-label="Related systems">
            <p class="auth-related-sites__label">Other ERC tools</p>
            <ul class="auth-related-sites__list">
                <li>
                    <a href="<?= e(SOR_SITE_URL) ?>/" class="auth-related-sites__link">
                        <span class="auth-related-sites__name">SOR Management System</span>
                        <span class="auth-related-sites__desc">Schedule of Rates codes, trades and review</span>
                    </a>
                </li>
                <li>
                    <a href="<?= e(ASIS_SITE_URL) ?>/" class="auth-related-sites__link">
                        <span class="auth-related-sites__name">AS-IS process mapping</span>
                        <span class="auth-related-sites__desc">Swimlane process maps and systems library</span>
                    </a>
                </li>
            </ul>
            <p class="auth-related-sites__hint">Same username and password when all three use the shared database.</p>
        </nav>
    </div>
</main>

</body>
</html>
