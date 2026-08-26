<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/feedback.php';

require_admin();

$rows = feedback_list(200);
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Feedback — ERC Housing Digital Transformation</title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/style.css')) ?>">
</head>
<body class="auth-login-body">
<?= research_banner_html() ?>

<div class="auth-login-topbar">
    <img src="<?= e(asset_url('/assets/media/leaves.png')) ?>" alt="" class="topbar-leaves" width="28" height="20">
    <span>East Renfrewshire Council &mdash; Feedback inbox</span>
</div>

<main class="main-content" style="max-width:1100px;margin:2rem auto;padding:0 1.25rem 3rem;">
    <header style="margin-bottom:1.5rem;">
        <h1 style="margin:0 0 0.35rem;">Feedback submissions</h1>
        <p style="margin:0;color:var(--erc-text-light);">
            <?= count($rows) ?> submission<?= count($rows) === 1 ? '' : 's' ?> — most recent first.
        </p>
        <p style="margin:0.75rem 0 0;"><a href="<?= e(APP_URL) ?>/index.html" class="link-visible">← Back to portal</a></p>
    </header>

    <?php if (!$rows): ?>
        <div class="card"><p style="margin:0;color:var(--erc-text-light);">No feedback submitted yet.</p></div>
    <?php else: ?>
        <div class="card table-wrap" style="padding:0;overflow:hidden;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Page</th>
                        <th>Submitted by</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td style="white-space:nowrap;color:var(--erc-text-light);font-size:0.85rem;">
                            <?= e(date('d M Y H:i', strtotime((string)$r['created_at']))) ?>
                        </td>
                        <td><span class="badge badge-grey"><?= e((string)$r['type']) ?></span></td>
                        <td style="font-size:0.85rem;max-width:180px;">
                            <?php if (!empty($r['page'])): ?>
                                <a href="<?= e(APP_URL . (string)$r['page']) ?>" class="link-visible" title="<?= e((string)$r['page']) ?>">
                                    <?= e(feedback_page_label((string)$r['page'])) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--erc-text-light);font-size:0.85rem;"><?= e((string)$r['submitted_by']) ?></td>
                        <td style="max-width:360px;"><?= e((string)$r['message']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
