/**
 * ============================================================================
 * EDEXCEL COLLEGE — PROMOTIONAL LANDING PAGE INTERACTIONS (CLASS 2027)
 * ============================================================================
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initStickyHeader();
        initMobileNav();
        initSmoothAnchors();
        initBackToTop();
        initMockPaperViewer();
        initScrollAnimations();
        initClassAnalytics();
    });

    /**
     * 1. Sticky Header scroll state
     */
    function initStickyHeader() {
        var header = document.getElementById('cls-header');
        if (!header) return;

        function updateHeader() {
            if (window.scrollY > 40) {
                header.classList.add('is-scrolled');
            } else {
                header.classList.remove('is-scrolled');
            }
        }

        window.addEventListener('scroll', updateHeader, { passive: true });
        updateHeader();
    }

    /**
     * 2. Mobile Navigation Menu Toggle
     */
    function initMobileNav() {
        var toggle = document.getElementById('clsMobileToggle');
        var menu = document.getElementById('clsNavMenu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', function () {
            var isOpen = menu.classList.contains('is-open');
            if (isOpen) {
                menu.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            } else {
                menu.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });

        var links = menu.querySelectorAll('.cls-nav-link');
        links.forEach(function (link) {
            link.addEventListener('click', function () {
                menu.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    /**
     * 3. Smooth scrolling for internal anchor links with sticky header offset
     */
    function initSmoothAnchors() {
        var anchorLinks = document.querySelectorAll('a[href^="#"]');
        var header = document.getElementById('cls-header');

        anchorLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                var targetId = this.getAttribute('href');
                if (!targetId || targetId === '#' || targetId === '#!') return;

                var targetEl = document.querySelector(targetId);
                if (!targetEl) return;

                e.preventDefault();
                var headerHeight = header ? header.offsetHeight : 70;
                var targetPosition = targetEl.getBoundingClientRect().top + window.pageYOffset - (headerHeight + 16);

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });

                if (history.pushState) {
                    history.pushState(null, null, targetId);
                }
            });
        });
    }

    /**
     * 4. Floating Back to Top Button
     */
    function initBackToTop() {
        var btn = document.getElementById('clsBackToTop');
        if (!btn) return;

        window.addEventListener('scroll', function () {
            if (window.scrollY > 450) {
                btn.classList.add('is-visible');
            } else {
                btn.classList.remove('is-visible');
            }
        }, { passive: true });

        btn.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    /**
     * 5. Mock Paper Preview Controller (PDF.js + Interactive Examination Mockup)
     * Upgraded to strictly support Pages 1 through 5.
     */
    function initMockPaperViewer() {
        var viewerApp = document.getElementById('pdfViewerApp');
        if (!viewerApp) return;

        var pdfUrl = (viewerApp.getAttribute('data-pdf-url') || '').trim();
        var currentPage = 1;
        var maxPages = 5; // Upgraded: Showing Pages 1–5 Only
        var currentZoom = 1.0;

        // Elements
        var pageTabs = viewerApp.querySelectorAll('.cls-page-tab');
        var pageIndicator = document.getElementById('pageIndicator');
        var prevBtn = document.getElementById('prevPageBtn');
        var nextBtn = document.getElementById('nextPageBtn');
        var zoomInBtn = document.getElementById('zoomInBtn');
        var zoomOutBtn = document.getElementById('zoomOutBtn');
        var zoomResetBtn = document.getElementById('zoomResetBtn');
        var zoomDisplay = document.getElementById('zoomDisplay');
        var mockDoc = document.getElementById('interactiveMockDoc');
        var pdfCanvasContainer = document.getElementById('pdfCanvasContainer');
        var pdfCanvas = document.getElementById('pdfCanvas');

        // PDF.js State
        var pdfDoc = null;
        var pageRendering = false;
        var pageNumPending = null;

        // Tab click listeners
        pageTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var p = parseInt(this.getAttribute('data-page'), 10);
                if (p >= 1 && p <= maxPages) {
                    goToPage(p);
                }
            });
        });

        // Prev / Next listeners
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (currentPage > 1) {
                    goToPage(currentPage - 1);
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (currentPage < maxPages) {
                    goToPage(currentPage + 1);
                }
            });
        }

        // Zoom listeners
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function () {
                if (currentZoom < 1.6) {
                    applyZoom(currentZoom + 0.15);
                }
            });
        }

        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function () {
                if (currentZoom > 0.7) {
                    applyZoom(currentZoom - 0.15);
                }
            });
        }

        if (zoomResetBtn) {
            zoomResetBtn.addEventListener('click', function () {
                applyZoom(1.0);
            });
        }

        function applyZoom(zoom) {
            currentZoom = Math.max(0.7, Math.min(1.6, Math.round(zoom * 100) / 100));
            if (zoomDisplay) {
                zoomDisplay.textContent = Math.round(currentZoom * 100) + '%';
            }

            if (typeof trackClassEvent === 'function') {
                trackClassEvent('pdf_zoom', currentZoom > 1.0 ? 'zoom_in' : (currentZoom < 1.0 ? 'zoom_out' : 'fit_page'));
            }

            if (pdfDoc && pdfCanvas) {
                renderPdfPage(currentPage);
            } else if (mockDoc) {
                mockDoc.style.transform = 'scale(' + currentZoom + ')';
            }
        }

        function updateUIState() {
            // Indicator
            if (pageIndicator) {
                pageIndicator.textContent = 'Page ' + currentPage + ' of ' + maxPages;
            }

            // Buttons disabled states
            if (prevBtn) prevBtn.disabled = (currentPage <= 1);
            if (nextBtn) nextBtn.disabled = (currentPage >= maxPages);

            // Tabs active state
            pageTabs.forEach(function (tab) {
                var p = parseInt(tab.getAttribute('data-page'), 10);
                if (p === currentPage) {
                    tab.classList.add('is-active');
                    tab.setAttribute('aria-selected', 'true');
                } else {
                    tab.classList.remove('is-active');
                    tab.setAttribute('aria-selected', 'false');
                }
            });

            // If using the examination paper mockup
            if (mockDoc) {
                var pages = mockDoc.querySelectorAll('.cls-exam-page');
                pages.forEach(function (pageEl) {
                    var pNum = parseInt(pageEl.getAttribute('data-page-number'), 10);
                    if (pNum === currentPage) {
                        pageEl.style.display = 'block';
                        pageEl.classList.add('is-visible');
                    } else {
                        pageEl.style.display = 'none';
                        pageEl.classList.remove('is-visible');
                    }
                });
            }
        }

        function goToPage(num) {
            if (num < 1 || num > maxPages) return;
            currentPage = num;
            updateUIState();

            if (typeof trackClassEvent === 'function') {
                trackClassEvent('pdf_page_view', 'page_' + num);
            }

            if (pdfDoc) {
                queueRenderPage(currentPage);
            }
        }

        // PDF.js rendering logic
        function renderPdfPage(num) {
            if (!pdfDoc || !pdfCanvas) return;
            pageRendering = true;

            pdfDoc.getPage(num).then(function (page) {
                var viewport = page.getViewport({ scale: currentZoom * 1.5 });
                var context = pdfCanvas.getContext('2d');
                pdfCanvas.height = viewport.height;
                pdfCanvas.width = viewport.width;

                var renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };

                var renderTask = page.render(renderContext);
                renderTask.promise.then(function () {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPdfPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            }).catch(function (err) {
                console.warn('PDF.js page render warning:', err);
                pageRendering = false;
            });
        }

        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPdfPage(num);
            }
        }

        // Load configured 5-page preview PDF
        if (pdfUrl && window.pdfjsLib) {
            window.pdfjsLib.getDocument(pdfUrl).promise.then(function (loadedDoc) {
                pdfDoc = loadedDoc;
                maxPages = Math.min(pdfDoc.numPages, 5); // Ensure up to 5 pages
                
                if (pdfCanvasContainer && mockDoc) {
                    pdfCanvasContainer.style.display = 'block';
                    mockDoc.style.display = 'none';
                }
                updateUIState();
                renderPdfPage(currentPage);
            }).catch(function (err) {
                console.log('Using examination mockup view. Reason:', err.message);
                if (pdfCanvasContainer && mockDoc) {
                    pdfCanvasContainer.style.display = 'none';
                    mockDoc.style.display = 'block';
                }
                updateUIState();
            });
        } else {
            updateUIState();
        }
    }

    /**
     * 6. Subtle Entrance Animations using IntersectionObserver
     */
    function initScrollAnimations() {
        if (!('IntersectionObserver' in window)) return;

        var animElements = document.querySelectorAll(
            '.cls-video-card, .cls-class-card, .cls-why-card, .cls-perspective-card, .cls-loc-card, .cls-teacher-card, .cls-register-card'
        );

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('cls-animated');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -30px 0px'
        });

        animElements.forEach(function (el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(16px)';
            el.style.transition = 'opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1)';
            observer.observe(el);
        });

        var style = document.createElement('style');
        style.textContent = '.cls-animated { opacity: 1 !important; transform: translateY(0) !important; }';
        document.head.appendChild(style);
    }

    /**
     * ========================================================================
     * 7. DEDICATED CLASS PAGE VISITOR & CONVERSION ANALYTICS
     * ========================================================================
     */
    var classAnalytics = {
        vid: '',
        sid: '',
        startTime: Date.now(),
        heartbeatInterval: null,
        trackedOnce: {}
    };

    function generateHex32() {
        if (window.crypto && window.crypto.getRandomValues) {
            var arr = new Uint8Array(16);
            window.crypto.getRandomValues(arr);
            var str = '';
            for (var i = 0; i < 16; i++) {
                str += ('0' + arr[i].toString(16)).slice(-2);
            }
            return str;
        }
        var s = '';
        for (var j = 0; j < 32; j++) {
            s += Math.floor(Math.random() * 16).toString(16);
        }
        return s;
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[2]) : '';
    }

    function setCookie(name, val, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(val) + expires + '; path=/; SameSite=Lax';
    }

    function isValidHex32(str) {
        return typeof str === 'string' && /^[a-f0-9]{32}$/.test(str);
    }

    function getOrCreateVid() {
        var vid = getCookie('_cls_vid');
        if (!isValidHex32(vid)) {
            try {
                vid = localStorage.getItem('_cls_vid') || '';
            } catch (e) {}
        }
        if (!isValidHex32(vid)) {
            vid = generateHex32();
        }
        setCookie('_cls_vid', vid, 365);
        try {
            localStorage.setItem('_cls_vid', vid);
        } catch (e) {}
        return vid;
    }

    function getOrCreateSid() {
        var sid = getCookie('_cls_sid');
        if (!isValidHex32(sid)) {
            try {
                sid = sessionStorage.getItem('_cls_sid') || '';
            } catch (e) {}
        }
        if (!isValidHex32(sid)) {
            sid = generateHex32();
        }
        setCookie('_cls_sid', sid, 1 / 48); // ~30 mins
        try {
            sessionStorage.setItem('_cls_sid', sid);
        } catch (e) {}
        return sid;
    }

    function sendTelemetry(payload, useBeacon) {
        var url = '/ajax/track_class_analytics.php';
        var jsonStr = JSON.stringify(payload);

        if (useBeacon && navigator.sendBeacon) {
            try {
                var blob = new Blob([jsonStr], { type: 'application/json' });
                if (navigator.sendBeacon(url, blob)) {
                    return;
                }
            } catch (e) {}
        }

        if (window.fetch) {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: jsonStr,
                keepalive: !!useBeacon,
                credentials: 'same-origin'
            }).catch(function () {});
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(jsonStr);
        }
    }

    function trackClassEvent(name, target, value) {
        if (!classAnalytics.sid || !classAnalytics.vid) return;
        sendTelemetry({
            action: 'event',
            event_name: name,
            event_target: target || null,
            event_value: value || null,
            session_hash: classAnalytics.sid,
            visitor_hash: classAnalytics.vid
        });
    }

    function initClassAnalytics() {
        classAnalytics.vid = getOrCreateVid();
        classAnalytics.sid = getOrCreateSid();

        // Populate hidden form inputs if present
        var formVid = document.getElementById('reg_visitor_hash');
        var formSid = document.getElementById('reg_session_hash');
        if (formVid) formVid.value = classAnalytics.vid;
        if (formSid) formSid.value = classAnalytics.sid;

        // Parse UTM parameters
        var searchParams = new URLSearchParams(window.location.search);
        var pageviewData = {
            action: 'pageview',
            session_hash: classAnalytics.sid,
            visitor_hash: classAnalytics.vid,
            page_url: window.location.pathname + window.location.search,
            referrer: document.referrer || '',
            utm_source: searchParams.get('utm_source') || null,
            utm_medium: searchParams.get('utm_medium') || null,
            utm_campaign: searchParams.get('utm_campaign') || null,
            utm_content: searchParams.get('utm_content') || null,
            utm_term: searchParams.get('utm_term') || null
        };
        sendTelemetry(pageviewData);

        // Heartbeat timer (every 25 seconds)
        function sendHeartbeat(isLeaving) {
            var duration = Math.max(1, Math.floor((Date.now() - classAnalytics.startTime) / 1000));
            sendTelemetry({
                action: 'heartbeat',
                session_hash: classAnalytics.sid,
                duration_seconds: duration
            }, isLeaving);
        }

        classAnalytics.heartbeatInterval = setInterval(function () {
            if (!document.hidden) {
                sendHeartbeat(false);
            }
        }, 25000);

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                sendHeartbeat(true);
            }
        });
        window.addEventListener('pagehide', function () {
            sendHeartbeat(true);
        });

        // Autoplay video 2 tracking
        setTimeout(function () {
            trackClassEvent('video_autoplay', 'video_2', 'Autoplay Preview');
        }, 1500);

        // PDF viewer scroll detection
        var pdfViewer = document.getElementById('pdfViewerApp');
        if (pdfViewer && 'IntersectionObserver' in window) {
            var pdfObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting && !classAnalytics.trackedOnce['pdf_view']) {
                        classAnalytics.trackedOnce['pdf_view'] = true;
                        trackClassEvent('pdf_open', 'mock_paper_viewer');
                        pdfObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });
            pdfObserver.observe(pdfViewer);
        }

        // Global Event Delegation for clicks
        document.addEventListener('click', function (e) {
            var target = e.target;
            if (!target) return;

            // WhatsApp Button clicks
            var waLink = target.closest('a[href*="whatsapp.com"], a[href*="wa.me"], [data-track-action="whatsapp_click"]');
            if (waLink) {
                var waTarget = waLink.getAttribute('data-track-target') || 'whatsapp_button';
                var waHref = waLink.getAttribute('href') || '';
                trackClassEvent('whatsapp_click', waTarget, waHref);
                return;
            }

            // Format Selection Pills
            var formatPill = target.closest('.cls-format-pill');
            if (formatPill) {
                var formatTarget = (formatPill.getAttribute('href') || '').replace('#', '');
                trackClassEvent('class_select', formatTarget, formatPill.textContent.trim());
                return;
            }

            // General CTAs
            var ctaBtn = target.closest('.cls-btn, [data-track-action="cta_click"]');
            if (ctaBtn) {
                var ctaTarget = ctaBtn.getAttribute('data-track-target') || (ctaBtn.getAttribute('href') || '').replace('#', '') || 'cta_button';
                trackClassEvent('cta_click', ctaTarget, ctaBtn.textContent.trim());
                return;
            }

            // Video Cards
            var videoCard = target.closest('.cls-video-card');
            if (videoCard) {
                var cardTitle = videoCard.querySelector('.cls-video-title');
                var vNum = videoCard.querySelector('.cls-video-num');
                var vId = vNum ? ('video_' + parseInt(vNum.textContent, 10)) : 'video_click';
                trackClassEvent('video_play', vId, cardTitle ? cardTitle.textContent.trim() : '');
                return;
            }
        });

        // Registration form view & start tracking
        var regSection = document.getElementById('register');
        if (regSection && 'IntersectionObserver' in window) {
            var regObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting && !classAnalytics.trackedOnce['reg_view']) {
                        classAnalytics.trackedOnce['reg_view'] = true;
                        trackClassEvent('registration_open', 'registration_card');
                        regObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });
            regObserver.observe(regSection);
        }

        var regForm = document.getElementById('classRegForm');
        if (regForm) {
            regForm.addEventListener('focusin', function () {
                if (!classAnalytics.trackedOnce['reg_start']) {
                    classAnalytics.trackedOnce['reg_start'] = true;
                    trackClassEvent('registration_start', 'registration_form');
                }
            }, { once: true });

            regForm.addEventListener('submit', function () {
                var formatEl = document.getElementById('reg_format');
                var locEl = document.getElementById('reg_location');
                var subEl = document.getElementById('reg_subject');
                var targetStr = (formatEl ? formatEl.value : '') + ' — ' + (locEl ? locEl.value : '');
                trackClassEvent('registration_submit', targetStr, subEl ? subEl.value : '');
            });
        }
    }

})();

