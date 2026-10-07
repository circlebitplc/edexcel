(function () {
    if (window.ECK_DEVICE_EMERGENCY_STARTED) {
        return;
    }
    window.ECK_DEVICE_EMERGENCY_STARTED = true;
    var cfg = window.ECK_DEVICE_EMERGENCY || {};
    if (!cfg.endpoint) {
        return;
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function firstName(name) {
        var parts = String(name || '').trim().split(/\s+/);
        return parts[0] || 'This student';
    }

    function clock(epoch) {
        var left = Math.max(0, Math.floor(epoch - (Date.now() / 1000)));
        var minutes = Math.floor(left / 60);
        var seconds = left % 60;
        return (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }

    if (cfg.mode === 'student') {
        var wait = document.getElementById('emergencyWait');
        var clockNode = document.getElementById('emergencyClock');
        var expires = clockNode ? parseInt(clockNode.getAttribute('data-expires') || '0', 10) : 0;
        function paintClock() {
            if (!clockNode || !expires) {
                return;
            }
            var left = Math.floor(expires - (Date.now() / 1000));
            clockNode.textContent = left > 0 ? ('Request expires in ' + clock(expires)) : 'Checking the request...';
        }
        paintClock();
        setInterval(paintClock, 1000);
        function pollStudent() {
            var url = cfg.endpoint + '?lesson=' + encodeURIComponent(cfg.lessonId || 0);
            fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    var row = data && data.request;
                    if (!row || !row.status) {
                        return;
                    }
                    if (row.expires_epoch) {
                        expires = row.expires_epoch;
                    }
                    if (row.status === 'approved') {
                        window.location.href = cfg.returnUrl || window.location.href;
                        return;
                    }
                    if (row.status === 'denied' || row.status === 'expired') {
                        window.location.reload();
                    }
                })
                .catch(function () {});
        }
        setInterval(pollStudent, 3000);
        return;
    }

    var style = document.createElement('style');
    style.textContent = [
        '#eckEmergencyStack{position:fixed;top:72px;right:16px;z-index:5000;width:min(360px,calc(100vw - 24px));display:flex;flex-direction:column;gap:10px;pointer-events:none}',
        'body.ck-body #eckEmergencyStack{top:72px}',
        '.eck-emergency-card{pointer-events:auto;background:#fff;color:#1c2430;border:1px solid rgba(16,24,40,.08);border-radius:14px;box-shadow:0 10px 30px rgba(16,24,40,.18);padding:14px 14px 12px;font:14px/1.4 Segoe UI,sans-serif}',
        '.eck-emergency-card strong{font-weight:700}',
        '.eck-emergency-card p{margin:6px 0}',
        '.eck-emergency-title{display:flex;align-items:center;gap:6px;font-weight:700}',
        '.eck-emergency-actions{display:flex;gap:8px;margin-top:10px}',
        '.eck-emergency-actions button{border:0;border-radius:8px;padding:7px 12px;font:inherit;cursor:pointer}',
        '.eck-emergency-allow{background:#157a45;color:#fff}',
        '.eck-emergency-deny{background:#e8edf5;color:#1c2430}',
        '.eck-emergency-meta{color:#5d6b82;font-size:12px;font-variant-numeric:tabular-nums}',
        '.eck-emergency-error{color:#9f2d2d;font-size:12px;margin-top:6px}',
        '@media (max-width:640px){#eckEmergencyStack{right:12px;width:min(360px,calc(100vw - 24px))}}'
    ].join('');
    document.head.appendChild(style);

    var stack = document.createElement('div');
    stack.id = 'eckEmergencyStack';
    stack.setAttribute('aria-live', 'polite');
    document.body.appendChild(stack);
    var cards = {};

    function removeCard(id) {
        if (cards[id]) {
            cards[id].remove();
            delete cards[id];
        }
    }

    function render(row) {
        var id = String(row.id || '');
        if (!id) {
            return;
        }
        var card = cards[id];
        if (card) {
            var existing = card.querySelector('.eck-emergency-meta');
            if (existing) {
                existing.setAttribute('data-expires', String(row.expires_epoch || 0));
            }
            return;
        }
        card = document.createElement('section');
        card.className = 'eck-emergency-card';
        card.setAttribute('role', 'status');
        stack.appendChild(card);
        cards[id] = card;
        var name = row.student_name || 'Student';
        var title = cfg.role === 'admin' ? 'Student Device Request' : 'Device Access Request';
        var body = cfg.role === 'admin'
            ? esc(name) + ' is requesting temporary access from a 3rd device.'
            : '<strong>Student:</strong> ' + esc(name) + '<br>' + esc(firstName(name)) + ' is trying to join this class from a new device.';
        var extra = '';
        if (cfg.role === 'admin') {
            extra = '<p><strong>Class:</strong> ' + esc(row.class_label || 'Live class') + '<br><strong>Teacher:</strong> ' + esc(row.teacher_name || 'Teacher') + '</p>';
        }
        card.innerHTML = ''
            + '<div class="eck-emergency-title">🔔 ' + esc(title) + '</div>'
            + '<p>' + body + '</p>'
            + extra
            + '<div class="eck-emergency-actions">'
            + '<button type="button" class="eck-emergency-allow" data-decision="approved">Allow</button>'
            + '<button type="button" class="eck-emergency-deny" data-decision="denied">Deny</button>'
            + '</div>'
            + '<div class="eck-emergency-meta" data-expires="' + esc(row.expires_epoch || 0) + '">Request expires in ' + esc(clock(row.expires_epoch || 0)) + '</div>';
        card.querySelectorAll('button').forEach(function (button) {
            button.addEventListener('click', function () {
                decide(id, button.getAttribute('data-decision') || '', card);
            });
        });
    }

    function decide(id, decision, card) {
        card.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
        fetch(cfg.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'decide',
                request_id: parseInt(id, 10),
                decision: decision,
                csrf_token: cfg.csrf || ''
            })
        }).then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                if (result.ok && result.data && result.data.ok) {
                    removeCard(id);
                    return;
                }
                var note = document.createElement('div');
                note.className = 'eck-emergency-error';
                note.textContent = (result.data && result.data.error) || 'This request was already decided.';
                card.appendChild(note);
                setTimeout(function () { removeCard(id); }, 1200);
            })
            .catch(function () {
                card.querySelectorAll('button').forEach(function (button) { button.disabled = false; });
            });
    }

    function tick() {
        Object.keys(cards).forEach(function (id) {
            var meta = cards[id].querySelector('.eck-emergency-meta');
            if (!meta) {
                return;
            }
            var epoch = parseInt(meta.getAttribute('data-expires') || '0', 10);
            var left = Math.floor(epoch - (Date.now() / 1000));
            meta.textContent = left > 0 ? ('Request expires in ' + clock(epoch)) : 'Request expired';
        });
    }

    function pollStaff() {
        fetch(cfg.endpoint, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var rows = (data && data.requests) || [];
                var live = {};
                rows.forEach(function (row) {
                    live[String(row.id)] = true;
                    render(row);
                });
                Object.keys(cards).forEach(function (id) {
                    if (!live[id]) {
                        removeCard(id);
                    }
                });
            })
            .catch(function () {});
    }

    setInterval(tick, 1000);
    setInterval(pollStaff, 3000);
    pollStaff();
})();
