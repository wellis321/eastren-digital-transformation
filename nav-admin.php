<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nav_config.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $checked = array_flip((array)($_POST['visible'] ?? []));
    $user = (string)($_SESSION['admin_user'] ?? '');
    foreach (nav_leaf_items() as $item) {
        nav_set_hidden($item['id'], !isset($checked[$item['id']]), $user);
    }

    flash('success', 'Navigation updated.');
    redirect(APP_URL . '/nav-admin.php');
}

$hiddenIds = nav_hidden_ids();
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Menu settings — ERC Housing Digital Transformation</title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/style.css')) ?>">
</head>
<body class="auth-login-body">

<div class="auth-login-topbar">
    <img src="<?= e(asset_url('/assets/media/leaves.png')) ?>" alt="" class="topbar-leaves" width="28" height="20">
    <span>East Renfrewshire Council &mdash; Menu settings</span>
</div>

<main class="main-content nav-admin-page">
    <header class="nav-admin-page__header">
        <h1>Menu settings</h1>
        <p>Choose which items appear in the site's navigation menu, on every page, for every signed-in user. Unchecking every item in a section hides that whole menu.</p>
        <p><a href="<?= e(APP_URL) ?>/index.html" class="link-visible">← Back to portal</a></p>
    </header>

    <?= render_flash() ?>

    <form method="post" action="<?= e(APP_URL) ?>/nav-admin.php" class="nav-admin-form">
        <?= csrf_field() ?>
        <?php foreach (nav_definition() as $item): ?>
            <section class="card nav-admin-section">
                <?php if ($item['children'] === []): ?>
                    <label class="nav-admin-item">
                        <input type="checkbox" name="visible[]" value="<?= e($item['id']) ?>"<?= isset($hiddenIds[$item['id']]) ? '' : ' checked' ?>>
                        <span><?= e($item['label']) ?></span>
                    </label>
                <?php else: ?>
                    <h2 class="nav-admin-section__title"><?= e($item['label']) ?></h2>
                    <?php foreach ($item['children'] as $child): ?>
                        <label class="nav-admin-item">
                            <input type="checkbox" name="visible[]" value="<?= e($child['id']) ?>"<?= isset($hiddenIds[$child['id']]) ? '' : ' checked' ?>>
                            <span><?= e($child['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Save menu</button>
    </form>
</main>

</body>
</html>
