/**
 * Instant theme switching, localStorage + cookie persistence,
 * and optional database save for logged-in users.
 */
(function () {
    'use strict';

    var ALLOWED = ['dark', 'light', 'midnight', 'ocean', 'purple', 'emerald', 'sunset', 'rose', 'cyber', 'glass'];
    var DEFAULT = 'dark';
    var KEY = 'eck_theme';
    var COOKIE = 'eck_theme';

    function state() {
        return window.ECK_THEME_STATE || {
            current: DEFAULT,
            loggedIn: false,
            userId: 0,
            csrfToken: '',
            saveUrl: '/api/theme.php',
            storageKey: KEY,
            allowed: ALLOWED,
            catalog: {}
        };
    }

    function csrfToken() {
        var fromState = state().csrfToken;
        if (fromState) {
            return fromState;
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content');
        }
        if (window.EDX_CSRF_TOKEN) {
            return window.EDX_CSRF_TOKEN;
        }
        return '';
    }

    function normalize(theme) {
        theme = String(theme || '').toLowerCase();
        var allowed = state().allowed || ALLOWED;
        return allowed.indexOf(theme) !== -1 ? theme : DEFAULT;
    }

    function family(theme) {
        return theme === 'light' ? 'light' : 'dark';
    }

    function writeCookie(theme) {
        try {
            document.cookie = COOKIE + '=' + encodeURIComponent(theme)
                + ';path=/;max-age=31536000;SameSite=Lax';
            document.cookie = 'dark_mode=' + (family(theme) === 'dark' ? 'true' : 'false')
                + ';path=/;max-age=31536000;SameSite=Lax';
            var uid = parseInt(state().userId, 10) || 0;
            if (state().loggedIn && uid > 0) {
                document.cookie = 'eck_theme_dirty=' + uid + ';path=/;max-age=31536000;SameSite=Lax';
            }
        } catch (e) {}
    }

    function persistLocal(theme) {
        try {
            localStorage.setItem(state().storageKey || KEY, theme);
        } catch (e) {}
    }

    function applyDom(theme) {
        var html = document.documentElement;
        var fam = family(theme);
        html.setAttribute('data-theme', theme);
        html.setAttribute('data-bs-theme', fam);
        html.style.colorScheme = fam;
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            var catalog = state().catalog || {};
            if (catalog[theme] && catalog[theme].color) {
                meta.setAttribute('content', catalog[theme].color);
            }
        }
        document.querySelectorAll('[data-eck-theme]').forEach(function (btn) {
            var on = btn.getAttribute('data-eck-theme') === theme;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        var toggle = document.getElementById('eckThemeToggle');
        if (toggle) {
            var catalog = state().catalog || {};
            var name = catalog[theme] && catalog[theme].name ? catalog[theme].name : theme;
            toggle.setAttribute('aria-label', 'Choose theme, current ' + name);
            toggle.setAttribute('title', 'Theme: ' + name);
        }
        if (window.ECK_THEME_STATE) {
            window.ECK_THEME_STATE.current = theme;
        }
        document.dispatchEvent(new CustomEvent('eck-theme-change', { detail: { theme: theme } }));
    }

    function saveRemote(theme) {
        var cfg = state();
        if (!cfg.loggedIn || !cfg.saveUrl) {
            return;
        }
        var token = csrfToken();
        var form = new URLSearchParams();
        form.set('theme', theme);
        form.set('csrf_token', token);
        try {
            fetch(cfg.saveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': token
                },
                body: form.toString()
            }).then(function (res) {
                if (res && res.ok) {
                    document.cookie = 'eck_theme_dirty=0;path=/;max-age=0;SameSite=Lax';
                    return;
                }
                return fetch(cfg.saveUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token
                    },
                    body: JSON.stringify({ theme: theme, csrf_token: token })
                }).then(function (retry) {
                    if (retry && retry.ok) {
                        document.cookie = 'eck_theme_dirty=0;path=/;max-age=0;SameSite=Lax';
                    }
                });
            }).catch(function () {});
        } catch (e) {}
    }

    function apply(theme, options) {
        options = options || {};
        theme = normalize(theme);
        applyDom(theme);
        if (options.persist !== false) {
            persistLocal(theme);
            writeCookie(theme);
            if (options.remote !== false) {
                saveRemote(theme);
            }
        }
        return theme;
    }

    function current() {
        return normalize(
            (window.ECK_THEME_STATE && window.ECK_THEME_STATE.current)
            || document.documentElement.getAttribute('data-theme')
            || DEFAULT
        );
    }

    function setOpen(open) {
        var picker = document.getElementById('eckThemePicker');
        var panel = document.getElementById('eckThemePanel');
        var toggle = document.getElementById('eckThemeToggle');
        if (!panel || !toggle) {
            return;
        }
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (picker) {
            picker.classList.toggle('is-open', open);
        }
    }

    function bind() {
        var toggle = document.getElementById('eckThemeToggle');
        var panel = document.getElementById('eckThemePanel');

        document.addEventListener('click', function (event) {
            var pick = event.target.closest('[data-eck-theme]');
            if (pick) {
                event.preventDefault();
                apply(pick.getAttribute('data-eck-theme'));
                return;
            }
            if (event.target.closest('[data-eck-theme-close]')) {
                event.preventDefault();
                setOpen(false);
                return;
            }
            if (toggle && (event.target === toggle || toggle.contains(event.target))) {
                event.preventDefault();
                setOpen(panel && panel.hidden);
                return;
            }
            if (panel && !panel.hidden && !event.target.closest('#eckThemePicker')) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        var cfg = state();
        var server = normalize(cfg.current || current());
        if (cfg.loggedIn) {
            persistLocal(server);
            applyDom(server);
            return;
        }
        applyDom(current());
    }

    window.EckTheme = {
        apply: apply,
        current: current,
        normalize: normalize,
        setOpen: setOpen
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
