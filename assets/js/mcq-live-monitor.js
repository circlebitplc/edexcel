(function () {
    'use strict';

    var root = document.getElementById('mcqLive');
    if (!root || !window.fetch) {
        return;
    }
    var endpoint = root.getAttribute('data-endpoint') || '';
    var lesson = root.getAttribute('data-lesson') || '';
    var analyticsUrl = root.getAttribute('data-analytics') || '';
    var tbody = root.querySelector('[data-live-rows]');
    var cards = root.querySelector('[data-live-cards]');
    var empty = root.querySelector('[data-live-empty]');
    var updated = root.querySelector('[data-live-updated]');
    var statusSel = document.getElementById('liveStatus');
    var classSel = document.getElementById('liveClass');
    var lessonSel = document.getElementById('liveLesson');
    var itemSel = document.getElementById('liveItem');
    var attemptSel = document.getElementById('liveAttempt');
    var panel = root.querySelector('[data-live-panel]');

    var POLL_MS = 5000;
    var FULL_EVERY = 12;
    var rows = {};
    var idleSeconds = 120;
    var offlineSeconds = 150;
    var lastServerNow = '';
    var polls = 0;
    var timer = 0;
    var backoff = POLL_MS;
    var stopped = false;
    var inFlight = false;
    var selected = null;
    var detail = null;
    var detailAt = 0;
    var detailTimer = 0;

    var LABELS = {
        active: '🟢 Active',
        idle: '🟡 Idle',
        offline: '🔴 Offline',
        submitted: '✅ Submitted'
    };

    function itemTitle(id) {
        var opt = itemSel ? itemSel.querySelector('option[value="' + id + '"]') : null;
        return opt ? opt.textContent.trim() : '';
    }

    function clock(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        return (h > 0 ? h + ':' : '') + pad(m) + ':' + pad(s);
    }

    function ago(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        if (seconds < 10) {
            return 'Just now';
        }
        if (seconds < 60) {
            return seconds + ' s ago';
        }
        if (seconds < 3600) {
            return Math.floor(seconds / 60) + ' min ago';
        }
        return Math.floor(seconds / 3600) + ' h ago';
    }

    function aged(row) {
        var drift = (Date.now() - row._at) / 1000;
        var frozen = row.status === 'submitted';
        var inputAge = row.last_input_seconds + drift;
        var beatAge = row.last_heartbeat_seconds + drift;
        var status = 'active';
        if (row.stored_status === 'submitted') {
            status = 'submitted';
        } else if (row.stored_status === 'left' || beatAge > offlineSeconds) {
            status = 'offline';
        } else if (row.stored_status === 'idle' || inputAge > idleSeconds) {
            status = 'idle';
        }
        var running = !frozen && status !== 'offline';
        return {
            status: status,
            qTime: row.question_seconds + (running ? drift : 0),
            total: row.total_seconds + (running ? drift : 0),
            last: row.last_activity_seconds + drift
        };
    }

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function badge(status) {
        return el('span', 'live-dot live-' + status, LABELS[status] || status);
    }

    function questionText(row) {
        if (!row.question_no || !row.total) {
            return '—';
        }
        return 'Q' + row.question_no + ' / ' + row.total;
    }

    function progressNode(row) {
        var wrap = el('div', 'd-flex align-items-center gap-2');
        var bar = el('div', 'live-progress flex-grow-1');
        var fill = el('div');
        fill.style.width = Math.max(0, Math.min(100, row.progress)) + '%';
        bar.appendChild(fill);
        wrap.appendChild(bar);
        wrap.appendChild(el('span', 'small text-nowrap', row.progress + '% (' + row.answered + '/' + row.total + ')'));
        return wrap;
    }

    function sortedVisible() {
        var want = statusSel ? statusSel.value : '';
        var list = [];
        var counts = { active: 0, idle: 0, offline: 0, submitted: 0 };
        Object.keys(rows).forEach(function (key) {
            var row = rows[key];
            var live = aged(row);
            counts[live.status] = (counts[live.status] || 0) + 1;
            if (want === '' || want === live.status) {
                list.push({ row: row, live: live });
            }
        });
        var order = { active: 0, idle: 1, offline: 2, submitted: 3 };
        list.sort(function (a, b) {
            return (order[a.live.status] - order[b.live.status]) || a.row.name.localeCompare(b.row.name);
        });
        return { list: list, counts: counts };
    }

    function render() {
        var data = sortedVisible();
        Object.keys(data.counts).forEach(function (status) {
            var node = root.querySelector('[data-count="' + status + '"]');
            if (node) {
                node.textContent = String(data.counts[status]);
            }
        });
        var showActivity = itemSel && itemSel.value === '0';
        var bodyFrag = document.createDocumentFragment();
        var cardFrag = document.createDocumentFragment();
        data.list.forEach(function (entry) {
            var row = entry.row;
            var live = entry.live;
            var tr = el('tr');
            tr.tabIndex = 0;
            tr.setAttribute('data-key', row.key);
            if (selected === row.key) {
                tr.className = 'table-active';
            }
            var nameCell = el('td');
            nameCell.appendChild(el('div', 'fw-semibold', row.name));
            if (showActivity) {
                nameCell.appendChild(el('div', 'small text-muted', itemTitle(row.item_id)));
            }
            if (row.attempt_no > 1) {
                nameCell.appendChild(el('div', 'small text-muted', 'Attempt ' + row.attempt_no));
            }
            tr.appendChild(nameCell);
            var statusCell = el('td');
            statusCell.appendChild(badge(live.status));
            tr.appendChild(statusCell);
            tr.appendChild(el('td', 'fw-semibold', questionText(row)));
            var progCell = el('td');
            progCell.appendChild(progressNode(row));
            tr.appendChild(progCell);
            tr.appendChild(el('td', 'fw-semibold', row.answer || '—'));
            tr.appendChild(el('td', 'font-monospace', clock(live.qTime)));
            tr.appendChild(el('td', 'font-monospace', clock(live.total)));
            tr.appendChild(el('td', 'small', ago(live.last)));
            bodyFrag.appendChild(tr);

            var card = el('button', 'live-card');
            card.type = 'button';
            card.setAttribute('data-key', row.key);
            var top = el('div', 'd-flex justify-content-between align-items-center gap-2 mb-1');
            top.appendChild(el('span', 'fw-semibold', row.name));
            top.appendChild(badge(live.status));
            card.appendChild(top);
            if (showActivity) {
                card.appendChild(el('div', 'small text-muted mb-1', itemTitle(row.item_id)));
            }
            var meta = el('div', 'd-flex flex-wrap gap-3 small mb-1');
            meta.appendChild(el('span', 'fw-semibold', questionText(row)));
            meta.appendChild(el('span', '', 'Answer: ' + (row.answer || '—')));
            meta.appendChild(el('span', 'font-monospace', '⏱ ' + clock(live.qTime)));
            meta.appendChild(el('span', 'text-muted', ago(live.last)));
            card.appendChild(meta);
            card.appendChild(progressNode(row));
            cardFrag.appendChild(card);
        });
        var focused = document.activeElement && root.contains(document.activeElement) ? document.activeElement.getAttribute('data-key') : null;
        var focusedCard = focused && document.activeElement.tagName === 'BUTTON';
        tbody.replaceChildren(bodyFrag);
        cards.replaceChildren(cardFrag);
        if (focused) {
            var again = (focusedCard ? cards : tbody).querySelector('[data-key="' + focused.replace(/"/g, '') + '"]');
            if (again) {
                again.focus({ preventScroll: true });
            }
        }
        empty.hidden = data.list.length > 0;
        renderPanel();
    }

    function setMessage(text) {
        if (updated) {
            updated.textContent = text;
        }
    }

    function params(extra) {
        var q = new URLSearchParams();
        q.set('lesson', lesson);
        q.set('item', itemSel ? itemSel.value : '0');
        q.set('attempt', attemptSel ? attemptSel.value : '0');
        Object.keys(extra || {}).forEach(function (key) {
            q.set(key, extra[key]);
        });
        return endpoint + '?' + q.toString();
    }

    function sinceStamp() {
        if (!lastServerNow) {
            return '';
        }
        var t = new Date(lastServerNow.replace(' ', 'T'));
        if (isNaN(t.getTime())) {
            return '';
        }
        t = new Date(t.getTime() - 3000);
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        return t.getFullYear() + '-' + pad(t.getMonth() + 1) + '-' + pad(t.getDate()) + ' '
            + pad(t.getHours()) + ':' + pad(t.getMinutes()) + ':' + pad(t.getSeconds());
    }

    function schedule(ms) {
        window.clearTimeout(timer);
        if (!stopped) {
            timer = window.setTimeout(poll, ms);
        }
    }

    function poll(forceFull) {
        if (stopped || inFlight) {
            return;
        }
        if (document.hidden) {
            schedule(POLL_MS);
            return;
        }
        var full = forceFull === true || polls % FULL_EVERY === 0 || !lastServerNow;
        var extra = { action: 'list' };
        if (!full) {
            extra.since = sinceStamp();
        }
        inFlight = true;
        fetch(params(extra), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
            .then(function (res) {
                if (res.status === 401 || res.status === 403) {
                    stopped = true;
                    throw new Error(res.status === 401 ? 'Your session ended. Refresh the page to sign in.' : 'You cannot view this class.');
                }
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok) {
                    throw new Error((data && data.error) || 'Could not load the live monitor.');
                }
                idleSeconds = data.idle_seconds || idleSeconds;
                offlineSeconds = data.offline_seconds || offlineSeconds;
                lastServerNow = data.now;
                var at = Date.now();
                if (data.full) {
                    rows = {};
                }
                (data.rows || []).forEach(function (row) {
                    var existing = rows[row.key];
                    if (existing && existing.attempt_no > row.attempt_no) {
                        return;
                    }
                    row._at = at;
                    rows[row.key] = row;
                });
                polls++;
                backoff = POLL_MS;
                setMessage('Live · updated ' + new Date().toLocaleTimeString());
                render();
            })
            .catch(function (err) {
                backoff = Math.min(60000, backoff * 2);
                setMessage(err && err.message ? err.message : 'Connection problem. Retrying…');
            })
            .finally(function () {
                inFlight = false;
                schedule(backoff);
            });
    }

    function restart() {
        rows = {};
        lastServerNow = '';
        polls = 0;
        closePanel();
        render();
        poll(true);
    }

    function openPanel(key) {
        var row = rows[key];
        if (!row) {
            return;
        }
        selected = key;
        detail = null;
        panel.hidden = false;
        loadDetail();
        render();
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function closePanel() {
        selected = null;
        detail = null;
        window.clearTimeout(detailTimer);
        if (panel) {
            panel.hidden = true;
        }
    }

    function loadDetail() {
        window.clearTimeout(detailTimer);
        var row = selected ? rows[selected] : null;
        if (!row) {
            return;
        }
        var key = selected;
        fetch(params({ action: 'detail', item: String(row.item_id), student: String(row.student_id), attempt: '0' }), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (key !== selected) {
                    return;
                }
                if (data && data.ok) {
                    detail = data.student;
                    detailAt = Date.now();
                    renderPanel();
                }
            })
            .catch(function () {})
            .finally(function () {
                if (key === selected && !document.hidden) {
                    detailTimer = window.setTimeout(loadDetail, POLL_MS);
                } else if (key === selected) {
                    detailTimer = window.setTimeout(loadDetail, POLL_MS * 2);
                }
            });
    }

    function renderPanel() {
        if (!panel || panel.hidden || !selected) {
            return;
        }
        var row = rows[selected];
        var source = detail || row;
        if (!source) {
            return;
        }
        var basis = detail ? Object.assign({}, detail, { _at: detailAt }) : row;
        var live = aged(basis);
        var set = function (attr, text) {
            var node = panel.querySelector('[' + attr + ']');
            if (node) {
                node.textContent = text;
            }
        };
        set('data-panel-name', source.name);
        set('data-panel-activity', (detail && detail.activity_title) || itemTitle(source.item_id) + (source.attempt_no > 1 ? ' · attempt ' + source.attempt_no : ''));
        set('data-panel-question', questionText(source));
        set('data-panel-progress', source.answered + ' / ' + source.total + ' answered (' + source.progress + '%)');
        set('data-panel-answer', source.answer || '—');
        set('data-panel-qtime', clock(live.qTime));
        set('data-panel-total', clock(live.total));
        set('data-panel-last', ago(live.last));
        set('data-panel-status', LABELS[live.status] || live.status);
        var strip = panel.querySelector('[data-panel-strip]');
        if (strip && detail && detail.questions) {
            var frag = document.createDocumentFragment();
            detail.questions.forEach(function (q) {
                var mark = q.state === 'current' ? '●' : (q.answered ? '✓' : '○');
                var cls = 'live-q' + (q.state === 'current' ? ' is-current' : (q.answered ? ' is-answered' : ''));
                var chip = el('span', cls, 'Q' + q.n + ' ' + mark);
                chip.title = q.answered ? 'Answered' + (q.answer ? ': ' + q.answer : '') : (q.state === 'current' ? 'Current question' : 'Not answered yet');
                frag.appendChild(chip);
            });
            strip.replaceChildren(frag);
        }
    }

    root.addEventListener('click', function (event) {
        var target = event.target.closest ? event.target.closest('[data-key]') : null;
        if (target && root.contains(target)) {
            openPanel(target.getAttribute('data-key'));
        }
        if (event.target.closest && event.target.closest('[data-panel-close]')) {
            closePanel();
            render();
        }
    });
    root.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }
        var target = event.target.closest ? event.target.closest('tr[data-key]') : null;
        if (target) {
            openPanel(target.getAttribute('data-key'));
        }
    });

    if (statusSel) {
        statusSel.addEventListener('change', render);
    }
    if (itemSel) {
        itemSel.addEventListener('change', restart);
    }
    if (attemptSel) {
        attemptSel.addEventListener('change', restart);
    }
    function filterLessons() {
        if (!classSel || !lessonSel) {
            return;
        }
        var cls = classSel.value;
        Array.prototype.forEach.call(lessonSel.options, function (opt) {
            opt.hidden = cls !== '' && opt.getAttribute('data-class') !== cls;
        });
    }
    if (classSel && lessonSel) {
        classSel.addEventListener('change', function () {
            filterLessons();
            var first = Array.prototype.find.call(lessonSel.options, function (opt) { return !opt.hidden; });
            if (first && first.value !== lesson) {
                window.location.href = analyticsUrl + encodeURIComponent(first.value) + '#mcqLive';
            }
        });
        filterLessons();
    }
    if (lessonSel) {
        lessonSel.addEventListener('change', function () {
            if (lessonSel.value && lessonSel.value !== lesson) {
                window.location.href = analyticsUrl + encodeURIComponent(lessonSel.value) + '#mcqLive';
            }
        });
    }
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && !stopped) {
            poll(true);
        }
    });

    window.setInterval(function () {
        if (!document.hidden) {
            render();
        }
    }, 1000);
    poll(true);
})();
