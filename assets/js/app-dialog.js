(function () {
    'use strict';

    var modalEl;
    var titleEl;
    var textEl;
    var okBtn;
    var cancelBtn;
    var pending = null;
    var modal = null;

    function toneFromMessage(message, isConfirm, explicit) {
        if (explicit) {
            return explicit;
        }
        var text = (message || '').toLowerCase();
        if (/\b(delete|remove|destroy)\b/.test(text)) {
            return 'is-danger';
        }
        if (/\b(lock|unlock|clone|paid|payment)\b/.test(text)) {
            return 'is-warning';
        }
        if (isConfirm) {
            return 'is-warning';
        }
        if (/\b(deleted|saved|success|cloned|paid|updated|sent)\b/.test(text)) {
            return 'is-success';
        }
        if (/\b(error|failed|unable|invalid|not found)\b/.test(text)) {
            return 'is-danger';
        }
        return '';
    }

    function bindBackdrop() {
        document.querySelectorAll('.modal-backdrop').forEach(function (node) {
            node.classList.add('app-dialog-back');
        });
    }

    function ensure() {
        if (modalEl) {
            return true;
        }
        modalEl = document.getElementById('appDialogModal');
        if (!modalEl || !window.bootstrap) {
            return false;
        }
        titleEl = document.getElementById('appDialogTitle');
        textEl = document.getElementById('appDialogText');
        okBtn = document.getElementById('appDialogOk');
        cancelBtn = document.getElementById('appDialogCancel');
        modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: true });
        modalEl.addEventListener('shown.bs.modal', bindBackdrop);
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (pending) {
                var done = pending;
                pending = null;
                done(false);
            }
        });
        okBtn.addEventListener('click', function () {
            var done = pending;
            pending = null;
            modal.hide();
            if (done) {
                done(true);
            }
        });
        cancelBtn.addEventListener('click', function () {
            var done = pending;
            pending = null;
            modal.hide();
            if (done) {
                done(false);
            }
        });
        return true;
    }

    function show(options) {
        var opts = options || {};
        var message = String(opts.message || '');
        var isConfirm = !!opts.confirm;
        if (!ensure()) {
            if (isConfirm) {
                var fallbackConfirm = window.nativeConfirm || window.confirm;
                return (opts.onDone || function () {})(fallbackConfirm(message));
            }
            (window.nativeAlert || window.alert)(message);
            if (opts.onDone) {
                opts.onDone(true);
            }
            return;
        }
        if (pending) {
            var previous = pending;
            pending = null;
            previous(false);
        }
        titleEl.textContent = opts.title || (isConfirm ? 'Please confirm' : 'Notice');
        textEl.textContent = message;
        okBtn.textContent = opts.okLabel || (isConfirm ? 'Confirm' : 'OK');
        cancelBtn.classList.toggle('d-none', !isConfirm);
        modalEl.classList.remove('is-success', 'is-danger', 'is-warning');
        var tone = toneFromMessage(message, isConfirm, opts.tone);
        if (tone) {
            modalEl.classList.add(tone);
        }
        var icon = modalEl.querySelector('.app-dialog-icon i');
        if (icon) {
            icon.className = 'bi ' + (
                /\block\b/.test((opts.message || '').toLowerCase()) ? 'bi-lock'
                    : /\bunlock\b/.test((opts.message || '').toLowerCase()) ? 'bi-unlock'
                    : isConfirm ? 'bi-question-circle'
                    : tone === 'is-success' ? 'bi-check2-circle'
                    : tone === 'is-danger' ? 'bi-exclamation-triangle'
                    : 'bi-info-circle'
            );
        }
        pending = typeof opts.onDone === 'function' ? opts.onDone : function () {};
        modal.show();
        bindBackdrop();
    }

    window.nativeAlert = window.alert.bind(window);
    window.nativeConfirm = window.confirm.bind(window);
    window.alert = function (message) {
        show({
            title: 'Edexcel College',
            message: message == null ? '' : message,
            confirm: false,
            okLabel: 'OK'
        });
    };
    window.customConfirm = function (title, message, callback, options) {
        options = options || {};
        show({
            title: title || 'Please confirm',
            message: message || '',
            confirm: true,
            okLabel: options.okLabel || 'Confirm',
            tone: options.tone || '',
            onDone: callback
        });
    };
    window.appConfirm = function (message, title, options) {
        return new Promise(function (resolve) {
            window.customConfirm(title || 'Please confirm', message || '', resolve, options || {});
        });
    };
    window.appAlert = function (message, title) {
        show({
            title: title || 'Edexcel College',
            message: message || '',
            confirm: false
        });
    };

    var allowNativeConfirm = false;
    window.confirm = function (message) {
        if (allowNativeConfirm) {
            return true;
        }
        return window.nativeConfirm(message);
    };

    function confirmMessageFromHandler(attr) {
        if (!attr) {
            return '';
        }
        var match = String(attr).match(/(?:^|[^\w.$])confirm\s*\(\s*(['"])([\s\S]*?)\1\s*\)/);
        return match
            ? match[2].replace(/\\n/g, '\n').replace(/\\'/g, "'").replace(/\\"/g, '"')
            : '';
    }

    document.addEventListener('click', function (event) {
        var el = event.target.closest('[data-confirm], [onclick], a');
        if (!el || el instanceof HTMLFormElement) {
            return;
        }
        if (el.dataset.appConfirmArmed === '1') {
            delete el.dataset.appConfirmArmed;
            return;
        }
        var message = el.getAttribute('data-confirm') || confirmMessageFromHandler(el.getAttribute('onclick'));
        if (!message) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        show({
            title: 'Please confirm',
            message: message,
            confirm: true,
            onDone: function (ok) {
                if (!ok) {
                    return;
                }
                el.dataset.appConfirmArmed = '1';
                allowNativeConfirm = true;
                try {
                    if (el instanceof HTMLFormElement) {
                        if (typeof el.requestSubmit === 'function') {
                            el.requestSubmit();
                        } else {
                            HTMLFormElement.prototype.submit.call(el);
                        }
                    } else {
                        el.click();
                    }
                } finally {
                    allowNativeConfirm = false;
                }
            }
        });
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (allowNativeConfirm || form.dataset.appConfirmArmed === '1') {
            delete form.dataset.appConfirmArmed;
            return;
        }
        var message = form.getAttribute('data-confirm') || confirmMessageFromHandler(form.getAttribute('onsubmit'));
        if (!message) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        show({
            title: 'Please confirm',
            message: message,
            confirm: true,
            onDone: function (ok) {
                if (!ok) {
                    return;
                }
                form.dataset.appConfirmArmed = '1';
                allowNativeConfirm = true;
                try {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                } finally {
                    allowNativeConfirm = false;
                }
            }
        });
    }, true);
})();
