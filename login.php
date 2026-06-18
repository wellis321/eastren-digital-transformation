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
        $result = attempt_login($user, $pass);
        if ($result === true) {
            clear_attempts(client_ip());
            redirect(resolve_next_redirect($_GET['next'] ?? '', APP_URL . '/index.html'));
        } elseif ($result === 'needs_activation') {
            flash('info', 'A sign-in code has been sent to your email address. Enter it on the next page to set your password.');
            redirect(APP_URL . '/activate.php');
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

$sorSiteUrl = defined('SOR_SITE_URL') && SOR_SITE_URL !== ''
    ? SOR_SITE_URL
    : 'https://papayawhip-hamster-802775.hostingersite.com';
$asisSiteUrl = defined('ASIS_SITE_URL') && ASIS_SITE_URL !== ''
    ? ASIS_SITE_URL
    : 'https://slategray-cat-335719.hostingersite.com';
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in — ERC Housing Digital Transformation</title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/style.css')) ?>">
    <style>
        .auth-related-sites { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #d0d0d0; }
        .auth-related-sites__label { margin: 0 0 0.65rem; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: #555; }
        .auth-related-sites__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
        .auth-related-sites__link { display: block; padding: 0.65rem 0.75rem; border: 1px solid #d0d0d0; border-radius: 4px; background: #f5f5f5; text-decoration: none; color: inherit; }
        .auth-related-sites__link:hover { border-color: #006A51; background: #fff; }
        .auth-related-sites__name { display: block; font-size: 0.9rem; font-weight: 600; color: #005a44; }
        .auth-related-sites__desc { display: block; margin-top: 0.15rem; font-size: 0.8rem; color: #555; }
        .auth-related-sites__hint { margin: 0.75rem 0 0; font-size: 0.78rem; color: #555; }
    </style>
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
                <input type="password" id="password" name="password" autocomplete="current-password">
                <p style="margin:.35rem 0 0; font-size:.8rem; color:#666;">New user? Leave this blank and click Sign in — we'll email you a code.</p>
            </div>
            <button type="submit" class="auth-btn auth-btn--primary">Sign in</button>
        </form>

        <nav class="auth-related-sites" aria-label="Related systems">
            <p class="auth-related-sites__label">Other tools</p>
            <ul class="auth-related-sites__list">
                <li>
                    <a href="<?= e(rtrim($sorSiteUrl, '/')) ?>/" class="auth-related-sites__link">
                        <span class="auth-related-sites__name">SOR Management System</span>
                        <span class="auth-related-sites__desc">Schedule of Rates codes, trades and review</span>
                    </a>
                </li>
                <li>
                    <a href="<?= e(rtrim($asisSiteUrl, '/')) ?>/" class="auth-related-sites__link">
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
