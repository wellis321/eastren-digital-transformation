<?php
declare(strict_types=1);

/**
 * Canonical site navigation. Structure (labels, hrefs, grouping) lives here
 * in code; only VISIBILITY is stored in the database (nav_hidden_items) —
 * mirrors how governance-people.json (structure) + governance_people (DB
 * overrides) already split source-of-truth vs. per-item overrides.
 *
 * Each top-level entry is either a plain link (href set, children empty) or
 * a dropdown group (href null, children list). There is no separate
 * group-level visibility flag: hiding every child in a group is enough —
 * the now-empty dropdown collapses itself visually via CSS
 * (.nav-group:has(.nav-dropdown:empty)), so there's nothing extra to store
 * or keep in sync for "hide the whole section".
 *
 * @return list<array{id:string,label:string,href:?string,children:list<array{id:string,label:string,href:string}>}>
 */

/**
 * Menu settings is restricted to the site owner specifically, not the
 * general admin role — other admins (e.g. other confirmers) should not see
 * or use it. See is_site_owner() in functions.php for the actual check.
 */
function nav_can_manage(): bool
{
    return is_site_owner();
}

function nav_definition(): array
{
    return [
        ['id' => 'home', 'label' => 'Home', 'href' => 'index.html', 'children' => []],
        ['id' => 'programme-overview', 'label' => 'Programme Overview', 'href' => 'presentation.html', 'children' => []],
        ['id' => 'themes', 'label' => 'Themes', 'href' => 'continuous-improvement.html', 'children' => []],
        [
            'id' => 'tenant-engagement-group',
            'label' => 'Tenant Engagement',
            'href' => null,
            'children' => [
                ['id' => 'tenant-engagement-why', 'label' => 'Why It Matters', 'href' => 'tenant-engagement.html'],
                ['id' => 'tenant-engagement-satsd', 'label' => 'Scottish Approach to Service Design', 'href' => 'satsd.html'],
            ],
        ],
        [
            'id' => 'voids-group',
            'label' => 'Voids',
            'href' => null,
            'children' => [
                ['id' => 'voids-why', 'label' => 'Why It Matters', 'href' => 'voids.html'],
                ['id' => 'voids-sector', 'label' => 'What Others Are Doing', 'href' => 'voids-sector-practice.html'],
                ['id' => 'voids-challenges', 'label' => 'The Challenges', 'href' => 'voids-challenges.html'],
                ['id' => 'voids-best-practice', 'label' => "What Works & What Doesn't", 'href' => 'voids-best-practice.html'],
                ['id' => 'voids-resources', 'label' => 'Resources', 'href' => 'voids-resources.html'],
            ],
        ],
        [
            'id' => 'programme-group',
            'label' => 'Programme',
            'href' => null,
            'children' => [
                ['id' => 'programme-progress', 'label' => 'Progress', 'href' => 'progress.html'],
                ['id' => 'programme-progress-report', 'label' => 'Progress Report', 'href' => 'progress-report.html'],
                ['id' => 'programme-governance', 'label' => 'Governance', 'href' => 'governance.html'],
                ['id' => 'programme-membership-tool', 'label' => 'Membership tool', 'href' => 'governance-membership.html'],
                ['id' => 'programme-qr-tracker', 'label' => 'QR scan tracker', 'href' => 'qrcode.php'],
                ['id' => 'programme-membership-guide', 'label' => 'About the membership tool', 'href' => 'governance-membership-guide.html'],
                ['id' => 'programme-stakeholder-engagement', 'label' => 'Stakeholder Engagement', 'href' => 'stakeholder-engagement.html'],
            ],
        ],
        [
            'id' => 'reference-group',
            'label' => 'Reference',
            'href' => null,
            'children' => [
                ['id' => 'reference-how-i-work', 'label' => 'How I Work', 'href' => 'how-i-work.html'],
                ['id' => 'reference-history', 'label' => 'ERC Digital History', 'href' => 'history.html'],
                ['id' => 'reference-scotland-policy', 'label' => "Scotland's Policy", 'href' => 'scotland-policy.html'],
                ['id' => 'reference-digital-direction', 'label' => 'Digital Direction', 'href' => 'digital-direction.html'],
                ['id' => 'reference-best-practice', 'label' => 'Best Practice', 'href' => 'best-practice.html'],
                ['id' => 'reference-sector', 'label' => 'Sector Comparisons', 'href' => 'sector.html'],
                ['id' => 'reference-peer-benchmarks', 'label' => 'Peer Benchmarks (costs)', 'href' => 'peer-benchmarks.html'],
                ['id' => 'reference-reports', 'label' => 'Reports', 'href' => 'reports.html'],
                ['id' => 'reference-resources', 'label' => 'Resources', 'href' => 'resources.html'],
            ],
        ],
    ];
}

/** Flat list of every controllable leaf item (standalone links + dropdown children). */
function nav_leaf_items(): array
{
    $out = [];
    foreach (nav_definition() as $item) {
        if ($item['children'] === []) {
            $out[] = $item;
        } else {
            foreach ($item['children'] as $child) {
                $out[] = $child;
            }
        }
    }
    return $out;
}

function nav_ensure_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS nav_hidden_items (
      item_id     VARCHAR(80)  NOT NULL PRIMARY KEY,
      hidden_by   VARCHAR(120) NOT NULL DEFAULT '',
      hidden_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** @return array<string,true> set of currently-hidden leaf item ids */
function nav_hidden_ids(): array
{
    nav_ensure_table();
    $ids = db()->query('SELECT item_id FROM nav_hidden_items')->fetchAll(PDO::FETCH_COLUMN);
    return array_fill_keys($ids, true);
}

function nav_set_hidden(string $itemId, bool $hidden, string $updatedBy): void
{
    nav_ensure_table();
    $itemId = trim($itemId);
    if ($itemId === '') {
        return;
    }
    if ($hidden) {
        $stmt = db()->prepare(
            'INSERT INTO nav_hidden_items (item_id, hidden_by) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE hidden_by = VALUES(hidden_by), hidden_at = NOW()'
        );
        $stmt->execute([$itemId, $updatedBy]);
    } else {
        db()->prepare('DELETE FROM nav_hidden_items WHERE item_id = ?')->execute([$itemId]);
    }
}

/**
 * Remove the <a> tags for every hidden leaf item from within a nav block's
 * markup. Scoped to a single already-extracted <nav>...</nav> substring (the
 * caller does the extraction) so this can never touch a same-href plain-text
 * link living outside the nav — see nav_strip_hidden_cards() for the
 * separate "quick link" card grids that DO need to be hidden too.
 */
function nav_strip_hidden_links(string $navBlockHtml, array $hiddenIds): string
{
    if ($hiddenIds === []) {
        return $navBlockHtml;
    }
    foreach (nav_leaf_items() as $item) {
        if (!isset($hiddenIds[$item['id']])) {
            continue;
        }
        // Also matches href="X#some-anchor" / href="X?query" — a card or
        // link pointing at a specific section of a hidden page is still a
        // link to that hidden page. Delimiter is ~, not #, since the
        // pattern itself now contains a literal #.
        $href = preg_quote($item['href'], '~') . '(?:[#?][^"]*)?';
        $navBlockHtml = preg_replace(
            '~<a\s+href="' . $href . '"[^>]*>.*?</a>\s*~is',
            '',
            $navBlockHtml
        ) ?? $navBlockHtml;
    }
    // CSS's :empty requires literally zero child nodes — a dropdown whose
    // last link just got stripped above still has a whitespace text node
    // left over, so it wouldn't match .nav-dropdown:empty without this.
    $navBlockHtml = preg_replace(
        '#(<div class="(?:nav-dropdown|mobile-nav-sub)">)\s*(</div>)#',
        '$1$2',
        $navBlockHtml
    ) ?? $navBlockHtml;
    return $navBlockHtml;
}

/**
 * Several pages (the homepage's quick-link grid, "Next:" cross-link cards on
 * content pages) link to the same destinations via a `class="nav-card"`
 * element outside the <nav> — those are a second navigation surface, not
 * page content, so a hidden item needs to disappear from these too. Scoped
 * specifically to the nav-card class (not every same-href <a>) so a plain
 * prose mention of a hidden page's URL is never touched — only the ones
 * styled as a navigational card.
 */
function nav_strip_hidden_cards(string $html, array $hiddenIds): string
{
    if ($hiddenIds === []) {
        return $html;
    }
    foreach (nav_leaf_items() as $item) {
        if (!isset($hiddenIds[$item['id']])) {
            continue;
        }
        $href = preg_quote($item['href'], '~') . '(?:[#?][^"]*)?';
        // class="nav-card" can appear before or after href= depending on the page.
        // Delimiter is ~, not #, since the pattern itself now contains a literal #.
        $html = preg_replace(
            '~<a\s+(?:href="' . $href . '"\s+class="nav-card"|class="nav-card"\s+href="' . $href . '")[^>]*>.*?</a>\s*~is',
            '',
            $html
        ) ?? $html;
    }
    return $html;
}

/**
 * Add a "Menu settings" link into the Programme dropdown for admins — the
 * only entry point to nav-admin.php in the static HTML, since that page
 * isn't part of the nav definition itself. Run this AFTER
 * nav_strip_hidden_links() so the link survives even if every other item in
 * Programme has been hidden.
 *
 * The ">Programme " match (with trailing space, no further text) is what
 * distinguishes the dropdown button from the unrelated standalone
 * "Programme Overview" link elsewhere in the nav.
 */
function nav_inject_admin_link(string $navBlockHtml): string
{
    if (str_contains($navBlockHtml, 'nav-admin.php')) {
        return $navBlockHtml;
    }
    return preg_replace(
        '#(>Programme\s*<svg.*?</button>\s*<div class="(?:nav-dropdown|mobile-nav-sub)">)#s',
        '$1<a href="nav-admin.php">Menu settings</a>',
        $navBlockHtml,
        1
    ) ?? $navBlockHtml;
}
