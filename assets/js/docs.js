/* Dorguzen Docs — docs.js */

(function () {
    'use strict';

    // ── Mobile sidebar toggle ──────────────────────────────────
    const toggle   = document.getElementById('sidebarToggle');
    const sidebar  = document.getElementById('docsSidebar');
    const overlay  = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (toggle) toggle.addEventListener('click', openSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Close sidebar when a nav link is clicked on mobile
    document.querySelectorAll('.docs-nav-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 900) closeSidebar();
        });
    });

    // ── Keyboard shortcut: ⌘K / Ctrl+K to focus search ───────
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            var input = document.querySelector('.docs-search-input');
            if (input) input.focus();
        }
    });

    // ── Fix intro page logo path using the same base as the header logo ──
    var introLogo  = document.getElementById('dgz-intro-logo');
    var headerLogo = document.querySelector('.docs-logo img');
    if (introLogo && headerLogo) {
        // Derive base from header logo src: strip the filename, keep the path
        var headerSrc = headerLogo.getAttribute('src');
        var basePath  = headerSrc.substring(0, headerSrc.lastIndexOf('/') + 1);
        introLogo.setAttribute('src', basePath + 'dorguzen-logo.png');
    }

    // ── Scroll sidebar to active nav link on page load ────────
    var activeLink = document.querySelector('.docs-nav-link.active');
    if (activeLink && sidebar) {
        var sidebarRect = sidebar.getBoundingClientRect();
        var linkRect    = activeLink.getBoundingClientRect();
        sidebar.scrollTop = sidebar.scrollTop
            + (linkRect.top - sidebarRect.top)
            - (sidebar.offsetHeight / 2)
            + (activeLink.offsetHeight / 2);
    }

    // ── Back to top button ─────────────────────────────────────
    var backToTop = document.getElementById('dgz-back-to-top');
    if (backToTop) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 400) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        }, { passive: true });

        backToTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ── Add copy buttons to all <pre> blocks ───────────────────
    document.querySelectorAll('pre').forEach(function (pre) {
        var btn = document.createElement('button');
        btn.className = 'docs-copy-btn';
        btn.title = 'Copy code';
        btn.textContent = 'Copy';

        btn.addEventListener('click', function () {
            var code = pre.querySelector('code');
            var text = code ? code.innerText : pre.innerText;
            navigator.clipboard.writeText(text).then(function () {
                btn.textContent = 'Copied!';
                setTimeout(function () {
                    btn.textContent = 'Copy';
                }, 1800);
            }).catch(function () {
                btn.textContent = 'Error';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1800);
            });
        });

        pre.appendChild(btn);
    });

}());
