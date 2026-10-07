(function () {
    'use strict';

    var lastAuthTrigger = null;
    var AUTH_TARGETS = {
        login: 'student-login',
        student: 'student-login',
        teacher: 'teacher-login',
        staff: 'teacher-login',
        parent: 'parent-login',
        'student-login': 'student-login',
        'teacher-login': 'teacher-login',
        'parent-login': 'parent-login'
    };

    window.homepageBoot = function (opts) {
        opts = opts || {};
        var section = resolveAuthTarget(opts.activeSection);
        if (section) {
            openAuth(section);
            if (window.location.hash !== '#' + section) {
                history.replaceState(null, '', '#' + section);
            }
        }
        bindAuth();
        bindCounters();
        bindReveal();
        bindAuthSubmitGuard();
    };

    function resolveAuthTarget(value) {
        if (!value) return null;
        var key = String(value).replace(/^#/, '').toLowerCase();
        return AUTH_TARGETS[key] || null;
    }

    function openAuth(which) {
        which = resolveAuthTarget(which) || 'student-login';
        var root = document.querySelector('[data-hp-auth="modal"]');
        if (!root) {
            var inline = document.getElementById(which);
            if (inline) {
                ['student-login', 'teacher-login', 'parent-login'].forEach(function (id) {
                    var panel = document.getElementById(id);
                    if (!panel) return;
                    panel.hidden = id !== which;
                });
                inline.scrollIntoView({ behavior: 'smooth', block: 'start' });
                focusFirstField(inline);
                syncRoleTabs(which);
            }
            return;
        }
        var backdrop = root.querySelector('.hp-auth-backdrop');
        var panels = {
            'teacher-login': document.getElementById('teacher-login'),
            'student-login': document.getElementById('student-login'),
            'parent-login': document.getElementById('parent-login')
        };
        if (backdrop) backdrop.hidden = false;
        Object.keys(panels).forEach(function (id) {
            var panel = panels[id];
            if (!panel) return;
            panel.hidden = id !== which;
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
        });
        document.body.style.overflow = 'hidden';
        syncRoleTabs(which);
        focusFirstField(panels[which]);
        if (window.location.hash !== '#' + which) {
            try { history.replaceState(null, '', '#' + which); } catch (e) {}
        }
    }

    function syncRoleTabs(which) {
        document.querySelectorAll('.hp-auth-role').forEach(function (btn) {
            var active = btn.getAttribute('data-auth-target') === which;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function closeAuth() {
        var root = document.querySelector('[data-hp-auth="modal"]');
        if (!root) return;
        var backdrop = root.querySelector('.hp-auth-backdrop');
        ['teacher-login', 'student-login', 'parent-login'].forEach(function (id) {
            var panel = document.getElementById(id);
            if (panel) panel.hidden = true;
        });
        if (backdrop) backdrop.hidden = true;
        document.body.style.overflow = '';
        if (lastAuthTrigger && typeof lastAuthTrigger.focus === 'function') {
            try { lastAuthTrigger.focus(); } catch (e) {}
        }
    }

    function focusFirstField(panel) {
        if (!panel) return;
        var alertEl = panel.querySelector('[role="alert"]');
        if (alertEl) {
            try { alertEl.focus(); } catch (e) {}
        }
        var field = panel.querySelector('input:not([type="hidden"]):not([disabled]), button.hp-auth-close');
        if (field && typeof field.focus === 'function') {
            setTimeout(function () {
                try { field.focus(); } catch (e) {}
            }, 30);
        }
    }

    function bindAuth() {
        document.querySelectorAll('[data-open-auth]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                lastAuthTrigger = el;
                openAuth(el.getAttribute('data-open-auth') || 'login');
            });
        });
        document.querySelectorAll('[data-auth-role]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                openAuth(el.getAttribute('data-auth-target') || el.getAttribute('data-auth-role'));
            });
        });
        document.querySelectorAll('[data-close-auth]').forEach(function (el) {
            el.addEventListener('click', closeAuth);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAuth();
        });
        var hashTarget = resolveAuthTarget(window.location.hash);
        if (hashTarget) {
            openAuth(hashTarget);
        }
    }

    function bindAuthSubmitGuard() {
        document.querySelectorAll('.hp-auth-panel form').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (window.uiFormBusy) {
                    return;
                }
                var btn = form.querySelector('button[type="submit"]');
                if (!btn || btn.disabled) return;
                btn.classList.add('is-busy');
                btn.disabled = true;
                var label = btn.getAttribute('data-busy-label') || 'Please wait…';
                if (!btn.getAttribute('data-original-label')) {
                    btn.setAttribute('data-original-label', btn.textContent || '');
                }
                btn.textContent = label;
                // Re-enable after a short delay if the browser stays on the page (validation bounce).
                setTimeout(function () {
                    if (!document.body.contains(btn)) return;
                    btn.disabled = false;
                    btn.classList.remove('is-busy');
                    btn.textContent = btn.getAttribute('data-original-label') || btn.textContent;
                }, 8000);
            });
        });
    }

    function bindCounters() {
        var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-count]'));
        if (!nodes.length) return;
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var run = function (el) {
            var end = parseInt(el.getAttribute('data-count'), 10) || 0;
            if (reduce) {
                el.textContent = String(end);
                return;
            }
            var start = 0;
            var dur = 900;
            var t0 = null;
            function step(ts) {
                if (!t0) t0 = ts;
                var p = Math.min(1, (ts - t0) / dur);
                el.textContent = String(Math.round(start + (end - start) * p));
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        };
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        run(entry.target);
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.4 });
            nodes.forEach(function (n) { io.observe(n); });
        } else {
            nodes.forEach(run);
        }
    }

    function bindReveal() {
        function reveal(nodes) {
            if (!nodes.length) return;
            if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                nodes.forEach(function (n) { n.classList.add('is-in'); });
                return;
            }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-in');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.08, rootMargin: '80px 0px' });
            nodes.forEach(function (n) { io.observe(n); });
            setTimeout(function () {
                nodes.forEach(function (n) {
                    if (!n.classList.contains('is-in')) n.classList.add('is-in');
                });
            }, 400);
        }
        reveal(Array.prototype.slice.call(document.querySelectorAll('[data-reveal]')));
        window.homepageReveal = function () {
            reveal(Array.prototype.slice.call(document.querySelectorAll('[data-reveal]:not(.is-in)')));
        };
        setTimeout(function () {
            if (window.homepageReveal) window.homepageReveal();
        }, 50);
    }
})();
