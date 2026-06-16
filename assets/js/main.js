/**
 * ERC Housing Digital Transformation
 * Main JavaScript
 */

(function () {
    'use strict';

    // ----------------------------------------------------------
    // Mobile Navigation Toggle
    // ----------------------------------------------------------
    const hamburger = document.getElementById('hamburger');
    const mobileNav = document.getElementById('mobile-nav');

    if (hamburger && mobileNav) {
        hamburger.addEventListener('click', function () {
            const isOpen = mobileNav.classList.toggle('open');
            hamburger.classList.toggle('open', isOpen);
            hamburger.setAttribute('aria-expanded', isOpen);
        });

        // Close mobile nav when a link (not a group toggle button) is clicked
        mobileNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                mobileNav.classList.remove('open');
                hamburger.classList.remove('open');
                hamburger.setAttribute('aria-expanded', 'false');
            });
        });

        // Close if user clicks outside
        document.addEventListener('click', function (e) {
            if (!hamburger.contains(e.target) && !mobileNav.contains(e.target)) {
                mobileNav.classList.remove('open');
                hamburger.classList.remove('open');
                hamburger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ----------------------------------------------------------
    // Desktop Dropdown Toggles
    // ----------------------------------------------------------
    document.querySelectorAll('.nav-group-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const isExpanded = btn.getAttribute('aria-expanded') === 'true';
            // Close all other dropdowns first
            document.querySelectorAll('.nav-group-btn').forEach(function (b) {
                if (b !== btn) b.setAttribute('aria-expanded', 'false');
            });
            btn.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            e.stopPropagation();
        });
    });

    // Close desktop dropdowns when clicking outside
    document.addEventListener('click', function () {
        document.querySelectorAll('.nav-group-btn').forEach(function (btn) {
            btn.setAttribute('aria-expanded', 'false');
        });
    });

    // ----------------------------------------------------------
    // Mobile Sub-group Toggles
    // ----------------------------------------------------------
    document.querySelectorAll('.mobile-nav-group-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const sub = btn.nextElementSibling;
            if (!sub) return;
            const isOpen = sub.classList.toggle('open');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    // ----------------------------------------------------------
    // Active Navigation Link Detection
    // ----------------------------------------------------------
    function setActiveNavLinks() {
        const currentPath = window.location.pathname;
        const currentFile = currentPath.split('/').pop() || 'index.html';

        document.querySelectorAll('.site-nav a, .mobile-nav a').forEach(function (link) {
            const href = link.getAttribute('href');
            if (!href) return;
            const linkFile = href.split('/').pop();
            if (
                linkFile === currentFile ||
                (currentFile === '' && linkFile === 'index.html')
            ) {
                link.classList.add('active');
                link.setAttribute('aria-current', 'page');

                // Mark parent desktop dropdown button as active
                var dropdown = link.closest('.nav-dropdown');
                if (dropdown) {
                    var groupBtn = dropdown.previousElementSibling;
                    if (groupBtn && groupBtn.classList.contains('nav-group-btn')) {
                        groupBtn.classList.add('active');
                    }
                }

                // Mark parent mobile sub-group button as active and open the sub
                var sub = link.closest('.mobile-nav-sub');
                if (sub) {
                    sub.classList.add('open');
                    var mobileBtn = sub.previousElementSibling;
                    if (mobileBtn && mobileBtn.classList.contains('mobile-nav-group-btn')) {
                        mobileBtn.classList.add('active');
                        mobileBtn.setAttribute('aria-expanded', 'true');
                    }
                }
            }
        });
    }

    setActiveNavLinks();

    // ----------------------------------------------------------
    // Tab Switching
    // ----------------------------------------------------------
    function initTabs() {
        const tabContainers = document.querySelectorAll('.tabs');

        tabContainers.forEach(function (container) {
            const buttons = container.querySelectorAll('.tab-btn');
            const panels  = container.querySelectorAll('.tab-panel');

            buttons.forEach(function (btn, index) {
                btn.addEventListener('click', function () {
                    // Remove active from all buttons and panels
                    buttons.forEach(function (b) { b.classList.remove('active'); });
                    panels.forEach(function (p)  { p.classList.remove('active'); });

                    // Activate clicked button and corresponding panel
                    btn.classList.add('active');
                    if (panels[index]) {
                        panels[index].classList.add('active');
                    }
                });
            });

            // Activate first tab by default if none are active
            if (!container.querySelector('.tab-btn.active') && buttons.length > 0) {
                buttons[0].classList.add('active');
                if (panels[0]) panels[0].classList.add('active');
            }
        });
    }

    initTabs();

    // ----------------------------------------------------------
    // Print Button Handler
    // ----------------------------------------------------------
    function initPrintBtn(id) {
        var btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                window.print();
            });
        }
    }
    initPrintBtn('print-btn');
    initPrintBtn('print-btn-bottom');

    // ----------------------------------------------------------
    // Smooth Scroll to Sections
    // ----------------------------------------------------------
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                const headerOffset = 80;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ----------------------------------------------------------
    // Animate Progress Bars on Scroll
    // ----------------------------------------------------------
    function animateProgressBars() {
        const fills = document.querySelectorAll('.progress-fill');
        if (!fills.length) return;

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    const fill = entry.target;
                    const targetWidth = fill.getAttribute('data-width') || fill.style.width;
                    fill.style.width = '0';
                    setTimeout(function () {
                        fill.style.width = targetWidth;
                    }, 100);
                    observer.unobserve(fill);
                }
            });
        }, { threshold: 0.3 });

        fills.forEach(function (fill) {
            // Store the target width before zeroing
            if (!fill.getAttribute('data-width')) {
                fill.setAttribute('data-width', fill.style.width);
            }
            observer.observe(fill);
        });
    }

    animateProgressBars();

    // ----------------------------------------------------------
    // Current Date Display
    // ----------------------------------------------------------
    const dateDisplay = document.getElementById('current-date');
    if (dateDisplay) {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        dateDisplay.textContent = now.toLocaleDateString('en-GB', options);
    }

})();
