<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/users.php';

if (is_logged_in()) {
    redirect(resolve_next_redirect('', APP_URL . '/index.html'));
}

$userId        = (int)($_SESSION['activation_user_id'] ?? 0);
$prefilledCode = '';

// Accept direct link from email: activate.php?uid=X&token=XXXXXX
if ($userId === 0) {
    $urlUid   = (int)($_GET['uid'] ?? 0);
    $urlToken = preg_replace('/\D/', '', trim($_GET['token'] ?? ''));
    if ($urlUid > 0 && strlen($urlToken) === 6) {
        $chk = db()->prepare(
            'SELECT id FROM password_setup_tokens
              WHERE user_id = ? AND token = ? AND expires_at > NOW() AND used_at IS NULL
              LIMIT 1'
        );
        $chk->execute([$urlUid, $urlToken]);
        if ($chk->fetch()) {
            $userId        = $urlUid;
            $_SESSION['activation_user_id'] = $userId;
            $prefilledCode = $urlToken;
        }
    }
}

if ($userId === 0) {
    flash('info', 'Enter your username below with the password left blank — you\'ll be sent here to enter your code.');
    redirect(APP_URL . '/login.php');
}

$stmt = db()->prepare(
    'SELECT id, username, email, display_name FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
);
$stmt->execute([$userId]);
$activationUser = $stmt->fetch();
if (!$activationUser) {
    unset($_SESSION['activation_user_id'], $_SESSION['activation_attempts']);
    redirect(APP_URL . '/login.php');
}

$maskedEmail = '';
$email = (string)($activationUser['email'] ?? '');
if ($email !== '') {
    [$local, $domain] = explode('@', $email, 2);
    $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid form submission.';
        goto render;
    }

    $attempts = (int)($_SESSION['activation_attempts'] ?? 0);
    if ($attempts >= 5) {
        unset($_SESSION['activation_user_id'], $_SESSION['activation_attempts']);
        flash('error', 'Too many incorrect attempts. Please sign in again to receive a new code.');
        redirect(APP_URL . '/login.php');
    }

    $code            = trim($_POST['code'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^\d{6}$/', $code)) {
        $errors[] = 'Enter the 6-digit code from your email.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        if (!consume_password_setup_token($userId, $code)) {
            $_SESSION['activation_attempts'] = $attempts + 1;
            $errors[] = 'That code is incorrect or has expired. Check your email and try again.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);

            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $freshUser = $stmt->fetch();

            unset($_SESSION['activation_user_id'], $_SESSION['activation_attempts']);
            establish_session($freshUser);

            flash('success', 'Password created — welcome to the portal.');
            redirect(APP_URL . '/index.html');
        }
    }
}

render:
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Activate account — ERC Housing Digital Transformation</title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/style.css')) ?>">
</head>
<body class="auth-login-body">
<?= research_banner_html() ?>

<div class="auth-login-topbar">
    <img src="<?= e(asset_url('/assets/media/leaves.png')) ?>" alt="" class="topbar-leaves" width="28" height="20">
    <span>East Renfrewshire Council &mdash; Housing Digital Transformation</span>
</div>

<main class="auth-login-main">
    <div class="auth-login-card">
        <div class="auth-login-header">
            <h1>Activate your account</h1>
            <p>
                We've sent a 6-digit code to
                <?= $maskedEmail !== '' ? '<strong>' . e($maskedEmail) . '</strong>' : 'your email address' ?>.
                Enter it below along with a new password.
            </p>
        </div>

        <?= render_flash() ?>
        <?php if ($errors): ?>
            <div class="flash flash-error" style="margin-bottom:1rem;">
                <?php foreach ($errors as $er): ?>
                    <div><?= e($er) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off" class="auth-login-form">
            <?= csrf_field() ?>

            <div class="auth-field">
                <label for="code">6-digit code</label>
                <input type="text" id="code" name="code"
                       inputmode="numeric" pattern="\d{6}" maxlength="6"
                       placeholder="e.g. 482 031"
                       autocomplete="one-time-code"
                       value="<?= e($prefilledCode) ?>"
                       <?= $prefilledCode !== '' ? 'readonly' : 'autofocus' ?>
                       required
                       style="letter-spacing:.2em; font-size:1.25rem; text-align:center;">
            </div>

            <div class="auth-field">
                <label for="password">New password</label>
                <input type="password" id="password" name="password"
                       minlength="8" required autocomplete="new-password"
                       <?= $prefilledCode !== '' ? 'autofocus' : '' ?>>
                <p style="margin:.35rem 0 0; font-size:.8rem; color:#666;">Minimum 8 characters.</p>
            </div>

            <div class="auth-field">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       minlength="8" required autocomplete="new-password">
            </div>

            <button type="submit" class="auth-btn auth-btn--primary">Set password &amp; sign in</button>
        </form>

        <p style="margin-top:1rem; font-size:.8rem; text-align:center; color:#666;">
            Code not arrived? <a href="<?= e(APP_URL) ?>/login.php">Go back</a> and sign in again to request a new one.
        </p>
    </div>
</main>

</body>
</html>
