<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function feedback_ensure_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS feedback (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type         VARCHAR(30)  NOT NULL DEFAULT 'other',
        message      TEXT         NOT NULL,
        page         VARCHAR(500) NOT NULL DEFAULT '',
        submitted_by VARCHAR(120) NOT NULL DEFAULT '',
        ip           VARCHAR(45)  NOT NULL DEFAULT '',
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_feedback_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** @return list<array<string,mixed>> */
function feedback_list(int $limit = 200): array
{
    feedback_ensure_table();
    $stmt = db()->prepare('SELECT * FROM feedback ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function feedback_page_label(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $base = basename($path);

    $map = [
        '/'                  => 'Home',
        '/index.html'        => 'Home',
        '/index.php'         => 'Home',
        '/progress.html'     => 'Progress Log',
        '/governance.html'   => 'Governance',
        '/presentation.html' => 'Programme Overview',
        '/sector.html'       => 'Sector Comparisons',
        '/peer-benchmarks.html' => 'Peer Benchmarks',
        '/resources.html'    => 'Resources',
        '/reports.html'      => 'Reports',
        '/history.html'      => 'ERC Digital History',
        '/feedback-view.php' => 'Feedback inbox',
        '/qrcode.php'        => 'QR scan tracker',
    ];

    if (isset($map[$path])) {
        return $map[$path];
    }

    if (str_ends_with($path, '.html')) {
        return ucwords(str_replace(['-', '_'], ' ', pathinfo($path, PATHINFO_FILENAME)));
    }

    return ucwords(str_replace(['-', '_', '.php'], [' ', ' ', ''], $base));
}

function portal_user_is_admin(): bool
{
    $role = $_SESSION['app_role'] ?? '';
    return $role !== 'viewer' && $role !== 'editor';
}

function feedback_widget_html(): string
{
    if (empty($_SESSION['admin_logged_in'])) {
        return '';
    }

    $csrf = csrf_field();
    $adminLink = portal_user_is_admin()
        ? '<p class="feedback-panel__admin"><a href="' . e(APP_URL . '/feedback-view.php') . '">View all feedback (admin)</a></p>'
        : '';

    return <<<HTML
<div id="feedback-toggle" class="feedback-toggle" title="Share feedback on this page"
     role="button" tabindex="0" aria-haspopup="dialog" aria-expanded="false"
     aria-controls="feedback-panel">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    Feedback
</div>

<div id="feedback-panel" class="feedback-panel" hidden role="dialog" aria-label="Share feedback">
    <div class="feedback-panel__header">
        <div>
            <strong class="feedback-panel__title">Share feedback</strong>
            <p class="feedback-panel__intro">Tell us what works, what is unclear, or what is missing on <strong>this page</strong>. Your comments help improve the portal content.</p>
        </div>
        <button type="button" class="feedback-panel__close" id="feedback-close" aria-label="Close feedback">&times;</button>
    </div>
    <form id="feedback-form" class="feedback-form">
        <input type="hidden" id="fb-page" name="page" value="">
        {$csrf}
        <div class="feedback-field">
            <label for="fb-type">Type</label>
            <select id="fb-type" name="type">
                <option value="content">Page content</option>
                <option value="suggestion">Suggestion</option>
                <option value="issue">Issue / bug</option>
                <option value="question">Question</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="feedback-field">
            <label for="fb-message">Your comments</label>
            <textarea id="fb-message" name="message" rows="4" required
                placeholder="What was helpful or confusing on this page? Anything we should add, correct, or explain better?"></textarea>
        </div>
        <button type="submit" class="feedback-submit">Send feedback</button>
        <p id="fb-thanks" class="feedback-thanks" hidden>Thank you — your feedback has been received.</p>
        {$adminLink}
    </form>
</div>
<script>
(function () {
    var toggle = document.getElementById('feedback-toggle');
    var panel = document.getElementById('feedback-panel');
    var closeBtn = document.getElementById('feedback-close');
    var form = document.getElementById('feedback-form');
    var pageInput = document.getElementById('fb-page');
    if (!toggle || !panel || !form || !pageInput) return;

    function setPage() {
        pageInput.value = window.location.pathname + window.location.search;
    }
    setPage();

    function openPanel() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        var msg = document.getElementById('fb-message');
        if (msg) msg.focus();
    }

    function closePanel() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
        if (panel.hidden) openPanel(); else closePanel();
    });
    toggle.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle.click(); }
    });
    if (closeBtn) closeBtn.addEventListener('click', closePanel);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fetch('/feedback.php', { method: 'POST', body: new FormData(form) })
            .then(function (res) {
                if (!res.ok) throw new Error('failed');
                form.reset();
                setPage();
                document.getElementById('fb-thanks').hidden = false;
                setTimeout(function () {
                    closePanel();
                    document.getElementById('fb-thanks').hidden = true;
                }, 2200);
            })
            .catch(function () { alert('Could not send — please try again.'); });
    });
})();
</script>
HTML;
}
