/* Project Hub Guide — sidebar, page contents, search, screenshot zoom. */
(function () {
    'use strict';

    var PROJECT_HUB_URL = 'https://erc-pf-for-planner-prod.crm11.dynamics.com/main.aspx?appid=3ba2c3fc-6d24-f111-8341-0022481ab95b&pagetype=entitylist&etn=msdyn_project&viewid=a18e3a4a-429d-4a98-9466-f7aa7068b5c3&viewType=1039';

    var ICONS = {
        home: '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
        compass: '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
        plus: '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M12 8v8M8 12h8"/>',
        share: '<circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.2 10.9l7.6-3.8M8.2 13.1l7.6 3.8"/>',
        tasks: '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>',
        risk: '<path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17v.5"/>',
        chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        cycle: '<path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 4v4h-4"/>',
        book: '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>',
        flag: '<path d="M5 21V4"/><path d="M5 4h12l-2.5 4L17 12H5"/>'
    };

    var PAGES = [
        { file: 'index.html', title: 'Start here', icon: 'home', group: 'Overview' },
        { file: 'getting-started.html', title: 'Access & finding your way', icon: 'compass', group: 'Set up' },
        { file: 'new-project.html', title: 'Create a project', icon: 'plus', group: 'Set up' },
        { file: 'sharing.html', title: 'Sharing, Teams & documents', icon: 'share', group: 'Set up' },
        { file: 'tasks.html', title: 'Tasks & milestones', icon: 'tasks', group: 'Run the project' },
        { file: 'risks-issues.html', title: 'Risks & issues', icon: 'risk', group: 'Run the project' },
        { file: 'status-reporting.html', title: 'Status & reporting', icon: 'chart', group: 'Run the project' },
        { file: 'lifecycle-closure.html', title: 'Lifecycle & closing', icon: 'cycle', group: 'Run the project' },
        { file: 'quick-reference.html', title: 'Quick reference', icon: 'book', group: 'Reference' },
        { file: 'our-project.html', title: 'Filling our plan', icon: 'flag', group: 'Our project' }
    ];

    function svg(name, size) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' + (size || 15) + '" height="' + (size || 15) + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
    }
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }

    var here = (location.pathname.split('/').pop() || 'index.html');
    if (here === '' || here === 'project-hub') { here = 'index.html'; }

    /* ---------- Sidebar ---------- */
    var aside = document.getElementById('ph-sidebar');
    if (aside) {
        var isMac = /Mac|iPhone|iPad/.test(navigator.platform || '');
        var html = ''
            + '<a class="ph-brand" href="index.html"><span class="ph-brand-mark">PH</span>'
            + '<span class="ph-brand-text"><strong>Project Hub Guide</strong><span>How to use ERC\'s project tool</span></span></a>'
            + '<span class="ph-private"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Private — only you can see this</span>'
            + '<button type="button" class="ph-search-btn" id="ph-search-open"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>Search the guide<kbd>' + (isMac ? '⌘K' : 'Ctrl K') + '</kbd></button>'
            + '<nav class="ph-nav" aria-label="Guide sections">';
        var lastGroup = '';
        PAGES.forEach(function (p) {
            if (p.group !== lastGroup) {
                html += '<div class="ph-nav-label">' + esc(p.group) + '</div>';
                lastGroup = p.group;
            }
            var current = p.file === here;
            html += '<a class="ph-nav-link" href="' + p.file + '"' + (current ? ' aria-current="page"' : '') + '>'
                + '<span class="ph-nav-ico">' + svg(p.icon) + '</span>' + esc(p.title) + '</a>';
            if (current) { html += '<div class="ph-toc" id="ph-toc"></div>'; }
        });
        html += '</nav>'
            + '<div class="ph-sidebar-foot">'
            + '<a class="ph-open-app" href="' + PROJECT_HUB_URL + '" target="_blank" rel="noopener">Open Project Hub <span aria-hidden="true">↗</span></a>'
            + '<a class="ph-back" href="../index.html">← Back to the portal</a>'
            + '</div>';
        aside.innerHTML = html;
    }

    /* ---------- Section anchors + page contents ---------- */
    var sections = Array.prototype.slice.call(document.querySelectorAll('.ph-section[id]'));
    var toc = document.getElementById('ph-toc');
    sections.forEach(function (sec) {
        var h2 = sec.querySelector('h2');
        if (!h2) { return; }
        var a = document.createElement('a');
        a.className = 'ph-anchor';
        a.href = '#' + sec.id;
        a.textContent = '#';
        a.setAttribute('aria-label', 'Link to this section');
        h2.appendChild(a);
        if (toc) {
            var link = document.createElement('a');
            link.href = '#' + sec.id;
            link.textContent = h2.getAttribute('data-short') || h2.firstChild.textContent.trim();
            link.dataset.target = sec.id;
            toc.appendChild(link);
        }
    });

    if (toc && 'IntersectionObserver' in window) {
        var links = toc.querySelectorAll('a');
        var visible = {};
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { visible[e.target.id] = e.isIntersecting ? e.intersectionRatio : 0; });
            var best = null;
            sections.forEach(function (s) { if (!best && visible[s.id] > 0) { best = s.id; } });
            if (best) {
                links.forEach(function (l) { l.classList.toggle('is-active', l.dataset.target === best); });
            }
        }, { rootMargin: '-10% 0px -60% 0px', threshold: [0, 0.01, 0.5] });
        sections.forEach(function (s) { io.observe(s); });
    }

    /* ---------- Prev / next ---------- */
    var pager = document.getElementById('ph-pager');
    if (pager) {
        var idx = PAGES.findIndex(function (p) { return p.file === here; });
        var prev = PAGES[idx - 1], next = PAGES[idx + 1];
        pager.innerHTML = (prev ? '<a class="prev" href="' + prev.file + '"><span>← Previous</span><b>' + esc(prev.title) + '</b></a>' : '<span></span>')
            + (next ? '<a class="next" href="' + next.file + '"><span>Next →</span><b>' + esc(next.title) + '</b></a>' : '');
    }

    /* ---------- Mobile menu ---------- */
    var toggle = document.getElementById('ph-menu-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () { document.body.classList.toggle('ph-nav-open'); });
        document.addEventListener('click', function (e) {
            if (document.body.classList.contains('ph-nav-open') && !e.target.closest('#ph-sidebar') && !e.target.closest('#ph-menu-toggle')) {
                document.body.classList.remove('ph-nav-open');
            }
        });
    }

    /* ---------- Screenshot zoom ---------- */
    var box = document.createElement('div');
    box.className = 'ph-lightbox';
    box.innerHTML = '<img alt="">';
    document.body.appendChild(box);
    document.addEventListener('click', function (e) {
        var img = e.target.closest('details.screen img');
        if (img) {
            box.querySelector('img').src = img.src;
            box.querySelector('img').alt = img.alt;
            box.classList.add('open');
        } else if (e.target.closest('.ph-lightbox')) {
            box.classList.remove('open');
        }
    });

    /* ---------- Search ---------- */
    var INDEX = window.PH_INDEX || [];
    var overlay = document.createElement('div');
    overlay.className = 'ph-search';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Search the guide');
    overlay.innerHTML = '<div class="ph-search-box">'
        + '<div class="ph-search-input"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>'
        + '<input type="text" id="ph-q" placeholder="Search — e.g. milestone, get to green, permissions" autocomplete="off" spellcheck="false"><kbd>Esc</kbd></div>'
        + '<div class="ph-search-results" id="ph-results"></div>'
        + '<div class="ph-search-foot"><span><kbd>↑</kbd> <kbd>↓</kbd> to move</span><span><kbd>Enter</kbd> to open</span></div>'
        + '</div>';
    document.body.appendChild(overlay);
    var q = overlay.querySelector('#ph-q');
    var results = overlay.querySelector('#ph-results');
    var active = -1;
    var SUGGEST = ['Critical milestone', 'Get to Green', 'Escalate risk', 'Permissions', 'Original End Date', 'Highlight Report', 'Naming convention', 'Close a project'];

    function hint() {
        results.innerHTML = '<div class="ph-search-hint">Search every page of the guide at once.<div class="sugg">'
            + SUGGEST.map(function (s) { return '<button type="button" data-q="' + esc(s) + '">' + esc(s) + '</button>'; }).join('')
            + '</div></div>';
    }
    function openSearch(initial) {
        overlay.classList.add('open');
        q.value = initial || '';
        run();
        setTimeout(function () { q.focus(); }, 10);
    }
    function closeSearch() { overlay.classList.remove('open'); }

    var STOP = ['to', 'the', 'a', 'an', 'of', 'in', 'on', 'and', 'or', 'is', 'it', 'my', 'for', 'how', 'do', 'i', 'what', 'with', 'from', 'can'];
    function terms(str) {
        var all = str.toLowerCase().replace(/[^a-z0-9%£ ]+/g, ' ').split(/\s+/).filter(function (t) { return t.length > 0; });
        var useful = all.filter(function (t) { return STOP.indexOf(t) === -1 && t.length > 1; });
        return useful.length ? useful : all.filter(function (t) { return t.length > 1; });
    }
    function highlight(text, ts) {
        var out = esc(text);
        ts.forEach(function (t) {
            var re = new RegExp('\\b(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
            out = out.replace(re, '<mark>$1</mark>');
        });
        return out;
    }
    function snippet(text, ts) {
        var lower = text.toLowerCase();
        var pos = -1;
        for (var i = 0; i < ts.length && pos === -1; i++) { pos = lower.indexOf(ts[i]); }
        if (pos === -1) { return text.slice(0, 150) + (text.length > 150 ? '…' : ''); }
        var start = Math.max(0, pos - 60);
        var s = (start > 0 ? '…' : '') + text.slice(start, start + 170);
        return s + (start + 170 < text.length ? '…' : '');
    }
    function run() {
        var ts = terms(q.value);
        active = -1;
        if (!ts.length) { hint(); return; }
        var scored = [];
        INDEX.forEach(function (entry) {
            var title = entry.title.toLowerCase();
            var body = entry.text.toLowerCase();
            var page = entry.pageTitle.toLowerCase();
            var score = 0, all = true;
            ts.forEach(function (t) {
                var hit = false;
                if (title.indexOf(t) !== -1) { score += 10; hit = true; }
                if (page.indexOf(t) !== -1) { score += 2; hit = true; }
                var n = body.split(t).length - 1;
                if (n) { score += Math.min(n, 6); hit = true; }
                if (!hit) { all = false; }
            });
            if (all && score) { scored.push({ e: entry, s: score }); }
        });
        scored.sort(function (a, b) { return b.s - a.s; });
        if (!scored.length) {
            results.innerHTML = '<div class="ph-search-empty">Nothing found for “' + esc(q.value) + '”. Try a shorter word.</div>';
            return;
        }
        results.innerHTML = scored.slice(0, 25).map(function (r) {
            return '<a class="ph-result" href="' + r.e.page + '#' + r.e.id + '">'
                + '<div class="r-page">' + esc(r.e.pageTitle) + '</div>'
                + '<div class="r-title">' + highlight(r.e.title, ts) + '</div>'
                + '<div class="r-snip">' + highlight(snippet(r.e.text, ts), ts) + '</div></a>';
        }).join('');
    }
    function move(d) {
        var items = results.querySelectorAll('.ph-result');
        if (!items.length) { return; }
        active = (active + d + items.length) % items.length;
        items.forEach(function (el, i) { el.classList.toggle('is-active', i === active); });
        items[active].scrollIntoView({ block: 'nearest' });
    }

    q.addEventListener('input', run);
    q.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Enter') {
            var items = results.querySelectorAll('.ph-result');
            var target = items[active >= 0 ? active : 0];
            if (target) { e.preventDefault(); location.href = target.getAttribute('href'); closeSearch(); }
        }
    });
    results.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-q]');
        if (b) { q.value = b.getAttribute('data-q'); run(); q.focus(); return; }
        if (e.target.closest('.ph-result')) { closeSearch(); }
    });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) { closeSearch(); } });
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openSearch(); }
        else if (e.key === '/' && !/input|textarea/i.test((document.activeElement || {}).tagName || '') && !overlay.classList.contains('open')) { e.preventDefault(); openSearch(); }
        else if (e.key === 'Escape') { closeSearch(); box.classList.remove('open'); }
    });
    var opener = document.getElementById('ph-search-open');
    if (opener) { opener.addEventListener('click', function () { openSearch(); }); }
    document.querySelectorAll('[data-open-search]').forEach(function (el) {
        el.addEventListener('click', function () { openSearch(el.getAttribute('data-open-search')); });
    });
})();
