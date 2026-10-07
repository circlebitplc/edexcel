/**
 * Shared toasts, submit loading, and double-submit protection.
 * Does not change request payloads, validation rules, or redirects.
 */
(function () {
    'use strict';

    var MAX_TOASTS = 4;
    var recent = [];
    var pendingForms = [];
    var submitters = typeof WeakMap === 'function' ? new WeakMap() : null;
    var stack = null;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function publicError(message) {
        var text = String(message == null ? '' : message).replace(/\s+/g, ' ').trim();
        if (!text) {
            return 'Something went wrong. Please try again.';
        }
        if (/^failed to fetch$/i.test(text) || /networkerror|load failed|network request failed/i.test(text)) {
            return 'The request could not be completed. Check your connection and try again.';
        }
        if (/sqlstate|stack trace|uncaught|pdoexception|mysqli|syntax error|fatal error|warning:\s|notice:\s|\/home\/|vendor\/|\.php(?:\s+on line|\(\d+\))|stacktrace|api[_ -]?key|secret key/i.test(text)) {
            return 'Something went wrong. Please try again.';
        }
        if (text.length > 280) {
            text = text.slice(0, 277) + '...';
        }
        return text;
    }

    function inferType(message) {
        var text = String(message || '').toLowerCase();
        if (/\b(error|failed|unable|invalid|not found|could not|cannot)\b/.test(text)) {
            return 'error';
        }
        if (/\b(warning|careful|temporarily|expired)\b/.test(text)) {
            return 'warning';
        }
        if (/\b(success|saved|deleted|updated|cloned|sent|created|signed)\b/.test(text)) {
            return 'success';
        }
        return 'info';
    }

    function ensureStack() {
        if (stack && document.body.contains(stack)) {
            return stack;
        }
        stack = document.getElementById('uiToastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'uiToastStack';
            stack.className = 'ui-toast-stack';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-relevant', 'additions');
            document.body.appendChild(stack);
        }
        return stack;
    }

    function showToast(message, type) {
        var kind = type || 'info';
        if (['success', 'error', 'warning', 'info'].indexOf(kind) === -1) {
            kind = 'info';
        }
        var text = publicError(message);
        if (!text) {
            return;
        }
        var now = Date.now();
        recent = recent.filter(function (item) { return now - item.at < 2000; });
        if (recent.some(function (item) { return item.text === text && item.type === kind; })) {
            return;
        }
        recent.push({ text: text, type: kind, at: now });

        var host = ensureStack();
        while (host.children.length >= MAX_TOASTS) {
            host.removeChild(host.firstChild);
        }

        var toast = document.createElement('div');
        toast.className = 'ui-toast is-' + kind;
        toast.setAttribute('role', kind === 'error' || kind === 'warning' ? 'alert' : 'status');
        toast.innerHTML = '<span class="ui-toast-mark" aria-hidden="true"></span>'
            + '<p class="ui-toast-text"></p>'
            + '<button type="button" class="ui-toast-close" aria-label="Close">×</button>';
        toast.querySelector('.ui-toast-text').textContent = text;

        var timer = null;
        var life = kind === 'error' ? 7000 : 5000;
        function dismiss() {
            if (timer) {
                clearTimeout(timer);
            }
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }
        function arm() {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(dismiss, life);
        }
        toast.querySelector('.ui-toast-close').addEventListener('click', dismiss);
        toast.addEventListener('mouseenter', function () {
            if (timer) {
                clearTimeout(timer);
            }
        });
        toast.addEventListener('mouseleave', arm);
        host.appendChild(toast);
        arm();
    }

    function loadingLabel(button, form) {
        var custom = button && button.getAttribute('data-loading-text');
        if (custom) {
            return custom;
        }
        var text = ((button && (button.tagName === 'INPUT' ? button.value : button.textContent)) || '').toLowerCase();
        if (/log\s*in|sign\s*in|verify/.test(text)) {
            return 'Logging in...';
        }
        if (/sign\s*up|register|create account|creating/.test(text)) {
            return 'Creating account...';
        }
        if (/\bsave\b/.test(text)) {
            return 'Saving...';
        }
        if (/\b(pay|checkout|process)\b/.test(text)) {
            return 'Processing...';
        }
        if (/\bsend\b/.test(text)) {
            return 'Sending...';
        }
        var action = ((form && (form.getAttribute('action') || form.action)) || '').toLowerCase();
        if (/login/.test(action) || (form && form.classList.contains('js-login-form'))) {
            return 'Logging in...';
        }
        if (/register/.test(action)) {
            return 'Creating account...';
        }
        return 'Submitting...';
    }

    function skipForm(form) {
        if (!(form instanceof HTMLFormElement)) {
            return true;
        }
        if (form.hasAttribute('data-ui-skip')) {
            return true;
        }
        if (form.closest('.public-ai, [data-classroom-root], .ck-app')) {
            return true;
        }
        if (/^(ckChatForm|ckAnnounceForm|ckMonitorChatForm|ckMonitorAnnounceForm)$/.test(form.id || '')) {
            return true;
        }
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get' && form.getAttribute('data-ui-loading') !== 'force') {
            return true;
        }
        return false;
    }

    function rememberedSubmitter(form) {
        if (submitters && submitters.has(form)) {
            return submitters.get(form);
        }
        return null;
    }

    function lockForm(form, ajax, submitter) {
        if (!(form instanceof HTMLFormElement) || form.getAttribute('data-ui-lock') === '1') {
            return;
        }
        form.setAttribute('data-ui-lock', '1');
        form.setAttribute('data-ui-ajax', ajax ? '1' : '0');
        var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        var primary = submitter && form.contains(submitter) ? submitter : (buttons[0] || null);
        Array.prototype.forEach.call(buttons, function (button) {
            if (button.getAttribute('data-ui-captured') === '1') {
                return;
            }
            button.setAttribute('data-ui-captured', '1');
            button.setAttribute('data-ui-orig-disabled', button.disabled ? '1' : '0');
            var label = loadingLabel(button, form);
            if (button.tagName === 'INPUT') {
                button.setAttribute('data-ui-orig-value', button.value);
                button.disabled = true;
                if (button === primary) {
                    button.value = label;
                }
                return;
            }
            button.setAttribute('data-ui-orig-html', button.innerHTML);
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            if (button === primary) {
                button.classList.add('ui-btn-loading');
                button.innerHTML = '<span class="ui-spinner" aria-hidden="true"></span><span class="ui-btn-label">'
                    + escapeHtml(label) + '</span>';
            }
        });
        if (ajax) {
            var timer = setTimeout(function () { unlockForm(form); }, 45000);
            form.setAttribute('data-ui-timer', String(timer));
        }
    }

    function unlockForm(form) {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        var timerId = form.getAttribute('data-ui-timer');
        if (timerId) {
            clearTimeout(Number(timerId));
            form.removeAttribute('data-ui-timer');
        }
        form.removeAttribute('data-ui-lock');
        form.removeAttribute('data-ui-ajax');
        var index = pendingForms.indexOf(form);
        if (index >= 0) {
            pendingForms.splice(index, 1);
        }
        Array.prototype.forEach.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'), function (button) {
            if (button.getAttribute('data-ui-captured') !== '1') {
                return;
            }
            if (button.hasAttribute('data-ui-orig-html')) {
                button.innerHTML = button.getAttribute('data-ui-orig-html');
                button.removeAttribute('data-ui-orig-html');
            }
            if (button.hasAttribute('data-ui-orig-value')) {
                button.value = button.getAttribute('data-ui-orig-value');
                button.removeAttribute('data-ui-orig-value');
            }
            button.classList.remove('ui-btn-loading');
            button.removeAttribute('aria-busy');
            button.disabled = button.getAttribute('data-ui-orig-disabled') === '1';
            button.removeAttribute('data-ui-orig-disabled');
            button.removeAttribute('data-ui-captured');
        });
    }

    function isAbort(err) {
        return !!(err && (err.name === 'AbortError' || /aborted/i.test(String(err.message || ''))));
    }

    function watchForms(forms) {
        forms.forEach(function (form) {
            if (skipForm(form) || form.getAttribute('data-ui-lock') === '1') {
                return;
            }
            lockForm(form, true, rememberedSubmitter(form));
        });
        return function settle() {
            forms.forEach(function (form) {
                if (form.getAttribute('data-ui-ajax') === '1') {
                    unlockForm(form);
                }
            });
        };
    }

    if (typeof window.fetch === 'function') {
        var origFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            var method = (init && init.method) || (input && input.method) || 'GET';
            var write = String(method).toUpperCase() !== 'GET' && String(method).toUpperCase() !== 'HEAD';
            var forms = (write && pendingForms.length) ? pendingForms.splice(0, pendingForms.length) : [];
            var promise = origFetch(input, init);
            if (!forms.length) {
                return promise;
            }
            var settle = watchForms(forms);
            return promise.then(function (res) {
                settle();
                return res;
            }, function (err) {
                settle();
                if (err && !isAbort(err)) {
                    window.__uiNetAt = Date.now();
                    showToast(err.message || 'The request could not be completed. Check your connection and try again.', 'error');
                }
                throw err;
            });
        };
    }

    if (window.XMLHttpRequest) {
        var origOpen = XMLHttpRequest.prototype.open;
        var origSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method) {
            this.__uiMethod = method;
            return origOpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function () {
            var method = String(this.__uiMethod || 'GET').toUpperCase();
            var write = method !== 'GET' && method !== 'HEAD';
            var forms = (write && pendingForms.length) ? pendingForms.splice(0, pendingForms.length) : [];
            if (forms.length) {
                var xhr = this;
                var settle = watchForms(forms);
                this.addEventListener('loadend', function () {
                    if (xhr.status === 0) {
                        window.__uiNetAt = Date.now();
                        showToast('The request could not be completed. Check your connection and try again.', 'error');
                    }
                    settle();
                });
            }
            return origSend.apply(this, arguments);
        };
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (skipForm(form)) {
            return;
        }
        if (form.getAttribute('data-ui-lock') === '1') {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        if (submitters) {
            submitters.set(form, event.submitter || null);
        }
        pendingForms.push(form);
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (skipForm(form)) {
            return;
        }
        if (event.defaultPrevented) {
            setTimeout(function () {
                if (form.getAttribute('data-ui-lock') === '1') {
                    return;
                }
                var index = pendingForms.indexOf(form);
                if (index >= 0) {
                    pendingForms.splice(index, 1);
                }
            }, 0);
            return;
        }
        var index = pendingForms.indexOf(form);
        if (index >= 0) {
            pendingForms.splice(index, 1);
        }
        lockForm(form, false, event.submitter || rememberedSubmitter(form));
    }, false);

    document.addEventListener('invalid', function (event) {
        var field = event.target;
        var form = field && field.form;
        if (!form || form.hasAttribute('data-ui-skip') || form.getAttribute('data-ui-invalid') === '1') {
            return;
        }
        form.setAttribute('data-ui-invalid', '1');
        showToast('Please check the highlighted fields and try again.', 'warning');
        setTimeout(function () { form.removeAttribute('data-ui-invalid'); }, 1200);
    }, true);

    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest
            ? event.target.closest('a[href*="/auth/google/start.php"]')
            : null;
        if (!link) {
            return;
        }
        if (link.getAttribute('data-ui-nav') === '1') {
            event.preventDefault();
            return;
        }
        link.setAttribute('data-ui-nav', '1');
        link.setAttribute('aria-busy', 'true');
        link.classList.add('ui-nav-busy');
    }, true);

    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-ui-lock="1"]').forEach(unlockForm);
        document.querySelectorAll('a[data-ui-nav="1"]').forEach(function (link) {
            link.removeAttribute('data-ui-nav');
            link.removeAttribute('aria-busy');
            link.classList.remove('ui-nav-busy');
        });
    });

    function alertText(node) {
        return (node.innerText || node.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function shouldToastAlert(node) {
        if (!node || node.closest('[data-ui-keep]')) {
            return false;
        }
        if (node.hasAttribute('hidden') || node.classList.contains('d-none') || node.hidden) {
            return false;
        }
        if (node.querySelector('form, table, ul, ol')) {
            return false;
        }
        var text = alertText(node);
        if (!text || text.length > 320) {
            return false;
        }
        if (node.classList.contains('alert-info') && node.querySelector('a') && text.length > 160) {
            return false;
        }
        return true;
    }

    function harvestAlerts() {
        document.querySelectorAll('.alert.alert-danger, .alert.alert-success, .alert.alert-warning, .alert.alert-info, .hp-auth-alert, .hp-auth-ok').forEach(function (node) {
            if (!shouldToastAlert(node)) {
                return;
            }
            var text = alertText(node);
            var safe = publicError(text);
            var type = 'info';
            if (node.classList.contains('alert-danger') || node.classList.contains('hp-auth-alert')) {
                type = 'error';
            } else if (node.classList.contains('alert-success') || node.classList.contains('hp-auth-ok')) {
                type = 'success';
            } else if (node.classList.contains('alert-warning')) {
                type = 'warning';
            }
            showToast(safe, type);
            if (safe !== text) {
                node.textContent = safe;
            }
            if (node.classList.contains('alert') && !node.classList.contains('hp-auth-alert')) {
                node.classList.add('ui-flash-sourced');
                node.setAttribute('aria-hidden', 'true');
            }
        });
    }

    function boot() {
        harvestAlerts();
    }

    if (typeof window.nativeAlert !== 'function') {
        window.nativeAlert = window.alert.bind(window);
    }
    window.alert = function (message) {
        var raw = message == null ? '' : String(message);
        if (/failed to fetch|networkerror|load failed|network request failed/i.test(raw) && window.__uiNetAt && (Date.now() - window.__uiNetAt) < 1500) {
            return;
        }
        showToast(raw, inferType(raw));
    };
    window.showToast = function (message, type) {
        showToast(message, type || inferType(message));
    };
    window.uiFormBusy = function (form, submitter) {
        lockForm(form, true, submitter || null);
    };
    window.uiFormIdle = function (form) {
        unlockForm(form);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
