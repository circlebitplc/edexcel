/**
 * Edexcel College — Layout 1 Interactive Scripts
 * Sticky header, modern mobile bottom navigation, "More" bottom sheet,
 * stat counters, category filters, and smooth section navigation
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // -------------------------------------------------------------------------
    // 1. STICKY HEADER SCROLL EFFECT
    // -------------------------------------------------------------------------
    var navbar = document.getElementById('l1-navbar');
    function updateNavbar() {
        if (!navbar) return;
        if (window.scrollY > 20) {
            navbar.classList.add('is-scrolled');
        } else {
            navbar.classList.remove('is-scrolled');
        }
    }
    window.addEventListener('scroll', updateNavbar, { passive: true });
    updateNavbar();

    // -------------------------------------------------------------------------
    // 2. MODERN "MORE" BOTTOM SHEET POPUP
    // -------------------------------------------------------------------------
    var moreToggle = document.getElementById('l1-more-toggle');
    var moreSheet = document.getElementById('l1-more-sheet');
    var sheetBackdrop = document.getElementById('l1-sheet-backdrop');
    var sheetClose = document.getElementById('l1-sheet-close');

    function openBottomSheet() {
        if (!moreSheet || !sheetBackdrop) return;
        moreSheet.hidden = false;
        sheetBackdrop.hidden = false;
        // Trigger reflow for CSS transition
        moreSheet.offsetHeight;
        moreSheet.classList.add('is-open');
        sheetBackdrop.classList.add('is-open');
        if (moreToggle) moreToggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeBottomSheet() {
        if (!moreSheet || !sheetBackdrop) return;
        moreSheet.classList.remove('is-open');
        sheetBackdrop.classList.remove('is-open');
        if (moreToggle) moreToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        setTimeout(function () {
            if (!moreSheet.classList.contains('is-open')) {
                moreSheet.hidden = true;
                sheetBackdrop.hidden = true;
            }
        }, 320);
    }

    if (moreToggle) {
        moreToggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (moreSheet && moreSheet.classList.contains('is-open')) {
                closeBottomSheet();
            } else {
                openBottomSheet();
            }
        });
    }

    if (sheetClose) sheetClose.addEventListener('click', closeBottomSheet);
    if (sheetBackdrop) sheetBackdrop.addEventListener('click', closeBottomSheet);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && moreSheet && moreSheet.classList.contains('is-open')) {
            closeBottomSheet();
        }
    });

    // Close bottom sheet when any internal navigation link or portal button is clicked
    var sheetLinks = document.querySelectorAll('.l1-sheet-item, .l1-sheet-cta a, .l1-sp-btn');
    sheetLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            closeBottomSheet();
        });
    });

    // -------------------------------------------------------------------------
    // 3. MOBILE FLOATING BOTTOM NAVIGATION ACTIVE SYNC
    // -------------------------------------------------------------------------
    var mobileNav = document.getElementById('l1-mobile-bottom-nav');
    var mnItems = mobileNav ? Array.prototype.slice.call(mobileNav.querySelectorAll('[data-mn]')) : [];

    function setActiveMobileNav(targetKey) {
        mnItems.forEach(function (item) {
            var key = item.getAttribute('data-mn');
            var isActive = (key === targetKey);
            item.classList.toggle('is-active', isActive);
            if (item.tagName === 'A') {
                if (isActive) item.setAttribute('aria-current', 'page');
                else item.removeAttribute('aria-current');
            }
        });
    }

    mnItems.forEach(function (item) {
        item.addEventListener('click', function () {
            var key = item.getAttribute('data-mn');
            if (key) {
                setActiveMobileNav(key);
                closeBottomSheet();
            }
        });
    });

    // Scroll observer to update mobile bottom nav active state
    var sectionMap = {
        home: document.getElementById('home'),
        programmes: document.getElementById('programmes'),
        teachers: document.getElementById('teachers'),
        timetable: document.getElementById('timetable'),
        about: document.getElementById('about'),
        why: document.getElementById('why'),
        journey: document.getElementById('journey'),
        students: document.getElementById('students'),
        parents: document.getElementById('parents'),
        faq: document.getElementById('faq'),
        contact: document.getElementById('contact')
    };

    var observedSections = Object.keys(sectionMap)
        .map(function (k) { return sectionMap[k]; })
        .filter(Boolean);

    if ('IntersectionObserver' in window && observedSections.length > 0) {
        var ratios = {};
        var sectionObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                ratios[entry.target.id] = entry.isIntersecting ? entry.intersectionRatio : 0;
            });

            var bestId = 'home';
            var bestRatio = 0;
            Object.keys(ratios).forEach(function (id) {
                if (ratios[id] > bestRatio) {
                    bestRatio = ratios[id];
                    bestId = id;
                }
            });

            if (bestRatio > 0.08) {
                var targetKey = bestId;
                if (bestId === 'about' || bestId === 'why' || bestId === 'journey') {
                    targetKey = 'home';
                } else if (bestId === 'students' || bestId === 'parents') {
                    targetKey = 'programmes';
                } else if (bestId === 'faq' || bestId === 'contact') {
                    targetKey = 'timetable';
                }
                setActiveMobileNav(targetKey);
            }
        }, {
            root: null,
            rootMargin: '-15% 0px -50% 0px',
            threshold: [0.08, 0.2, 0.4, 0.6]
        });

        observedSections.forEach(function (sec) {
            sectionObserver.observe(sec);
        });
    }

    // -------------------------------------------------------------------------
    // 4. STATISTIC COUNT-UP ANIMATION
    // -------------------------------------------------------------------------
    var countElements = document.querySelectorAll('[data-count]');
    if ('IntersectionObserver' in window && countElements.length > 0) {
        var countedSet = new Set();
        var countObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !countedSet.has(entry.target)) {
                    countedSet.add(entry.target);
                    animateCount(entry.target);
                }
            });
        }, { threshold: 0.2 });

        countElements.forEach(function (el) {
            countObserver.observe(el);
        });
    }

    function animateCount(el) {
        var target = parseInt(el.getAttribute('data-count'), 10);
        if (isNaN(target) || target <= 0) return;
        var duration = 1200;
        var startTime = null;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var easeOut = 1 - Math.pow(1 - progress, 3);
            var current = Math.floor(easeOut * target);
            el.textContent = current.toLocaleString();
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString();
            }
        }
        window.requestAnimationFrame(step);
    }

    // -------------------------------------------------------------------------
    // 5. PROGRAMMES FILTERING
    // -------------------------------------------------------------------------
    var progFilterBtns = document.querySelectorAll('.l1-filter-btn');
    var progCards = document.querySelectorAll('.l1-prog-card');

    progFilterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var filter = btn.getAttribute('data-filter');
            progFilterBtns.forEach(function (b) {
                b.classList.remove('is-active');
                b.setAttribute('aria-selected', 'false');
            });
            btn.classList.add('is-active');
            btn.setAttribute('aria-selected', 'true');

            progCards.forEach(function (card) {
                var cat = card.getAttribute('data-category');
                if (filter === 'all' || cat === filter) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // -------------------------------------------------------------------------
    // 6. FAQ CATEGORY FILTERING & ACCORDION BEHAVIOR
    // -------------------------------------------------------------------------
    var faqTabs = document.querySelectorAll('.l1-faq-tab');
    var faqItems = document.querySelectorAll('.l1-faq-item');

    faqTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var cat = tab.getAttribute('data-faq-tab');
            faqTabs.forEach(function (t) {
                t.classList.remove('is-active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('is-active');
            tab.setAttribute('aria-selected', 'true');

            faqItems.forEach(function (item) {
                var itemCat = item.getAttribute('data-faq-cat');
                if (cat === 'all' || itemCat === cat) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    faqItems.forEach(function (item) {
        item.addEventListener('toggle', function () {
            if (item.open) {
                faqItems.forEach(function (other) {
                    if (other !== item && other.open) {
                        other.open = false;
                    }
                });
            }
        });
    });

    // -------------------------------------------------------------------------
    // 7. SMOOTH SCROLLING WITH STICKY HEADER OFFSET
    // -------------------------------------------------------------------------
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var href = anchor.getAttribute('href');
            if (!href || href === '#' || href.length < 2) return;
            var target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                var headerOffset = 85;
                var elementPosition = target.getBoundingClientRect().top;
                var offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
});
