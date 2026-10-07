(function () {
    const root = document.getElementById('publicAiRoot');
    if (!root) return;

    const api = root.getAttribute('data-api') || '/api/public_ai.php';
    const csrf = root.getAttribute('data-csrf') || '';
    const signedIn = root.getAttribute('data-signed-in') === '1';
    const panel = document.getElementById('publicAiPanel');
    const openBtn = document.getElementById('publicAiOpen');
    const closeBtn = document.getElementById('publicAiClose');
    const log = document.getElementById('publicAiLog');
    const form = document.getElementById('publicAiForm');
    const box = document.getElementById('publicAiMessage');
    const send = document.getElementById('publicAiSend');
    let loaded = false;

    function jsonHeaders() {
        return {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            Accept: 'application/json',
        };
    }

    async function call(action, payload, method) {
        const verb = method || (payload ? 'POST' : 'GET');
        const url = api + (api.indexOf('?') >= 0 ? '&' : '?') + 'action=' + encodeURIComponent(action);
        const opts = { method: verb, headers: jsonHeaders(), credentials: 'same-origin' };
        if (verb === 'POST') {
            opts.body = JSON.stringify(Object.assign({ csrf_token: csrf, action: action }, payload || {}));
        }
        const res = await fetch(url, opts);
        let data = {};
        try {
            data = await res.json();
        } catch (e) {
            data = { ok: false, error: 'Could not read the reply.' };
        }
        if (!res.ok || data.ok === false) {
            throw new Error(data.error || 'Please try again.');
        }
        return data;
    }

    function bubble(role, text, meta) {
        if (!log) return;
        const wrap = document.createElement('div');
        wrap.className = 'public-ai-bubble ' + (role === 'user' ? 'is-user' : 'is-ai');
        const body = document.createElement('div');
        body.textContent = text;
        wrap.appendChild(body);
        if (role === 'assistant' && !(meta && meta.skipRate)) {
            const bar = document.createElement('div');
            bar.className = 'public-ai-rate';
            bar.innerHTML = '<button type="button" data-rate="helpful">Helpful</button>'
                + '<button type="button" data-rate="unhelpful">Not quite</button>';
            const id = meta && meta.id ? parseInt(meta.id, 10) : 0;
            bar.querySelectorAll('button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    call('rate', {
                        message_id: id,
                        helpful: btn.getAttribute('data-rate') === 'helpful' ? 1 : 0,
                    }).then(function () {
                        bar.querySelectorAll('button').forEach(function (b) { b.classList.remove('is-on'); });
                        btn.classList.add('is-on');
                    }).catch(function () {});
                });
            });
            wrap.appendChild(bar);
        }
        log.appendChild(wrap);
        log.scrollTop = log.scrollHeight;
    }

    function setOpen(on) {
        if (!panel || !openBtn) return;
        panel.hidden = !on;
        openBtn.hidden = on;
        openBtn.setAttribute('aria-expanded', on ? 'true' : 'false');
        if (on && box) box.focus();
        if (on && !loaded) {
            loaded = true;
            call('history').then(function (data) {
                (data.messages || []).forEach(function (m) {
                    bubble(m.role, m.content, { id: m.id });
                });
                if (!(data.messages || []).length) {
                    bubble('assistant', signedIn
                        ? 'I already know your classes and marks. Ask what to study, or about a teacher or topic.'
                        : 'Ask about classes, teachers, how to register, or any IGCSE / IAL topic. No account needed.', { skipRate: true });
                }
            }).catch(function () {
                bubble('assistant', 'Ask about classes, teachers, how to register, or any IGCSE / IAL topic.', { skipRate: true });
            });
        }
    }

    if (openBtn) {
        openBtn.addEventListener('click', function () { setOpen(true); });
    }
    document.querySelectorAll('[data-open-public-ai]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            setOpen(true);
        });
    });
    if (closeBtn) {
        closeBtn.addEventListener('click', function () { setOpen(false); });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel && !panel.hidden) setOpen(false);
    });

    if (form) {
        form.setAttribute('data-ui-skip', '1');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const text = (box.value || '').trim();
            if (!text) return;
            bubble('user', text);
            box.value = '';
            send.disabled = true;
            call('chat', { message: text }).then(function (data) {
                bubble('assistant', data.reply || '', { id: data.message_id });
            }).catch(function (err) {
                var message = (err && err.message) || 'I could not reply just then.';
                bubble('assistant', message);
                if (window.showToast) {
                    window.showToast(message, 'error');
                }
            }).finally(function () {
                send.disabled = false;
                box.focus();
            });
        });
    }
})();
