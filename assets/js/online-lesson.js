(function () {
    'use strict';

    var toggle = document.querySelector('[data-ol-toc-toggle]');
    var sidebar = document.querySelector('[data-ol-sidebar]');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }

    document.querySelectorAll('[data-ol-complete]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.hasAttribute('data-ol-watch-gate') && form.getAttribute('data-ol-watch-gate') !== 'done') {
                event.preventDefault();
                return;
            }
            if (form.hasAttribute('data-ol-link-gate')) {
                event.preventDefault();
                return;
            }
            if (window.uiFormBusy) {
                return;
            }
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.dataset.originalText = btn.textContent;
                btn.textContent = 'Saving…';
            }
        });
    });

    document.querySelectorAll('[data-ol-external]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var endpoint = link.getAttribute('data-endpoint') || '';
            var sameTab = link.getAttribute('target') !== '_blank';
            if (endpoint === '') {
                return;
            }
            if (sameTab) {
                event.preventDefault();
            }
            var body = new URLSearchParams();
            body.set('csrf_token', link.getAttribute('data-csrf') || '');
            body.set('item_id', link.getAttribute('data-item-id') || '');
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (!data || !data.ok) {
                    return;
                }
                var gate = document.querySelector('[data-ol-link-gate]');
                if (gate) {
                    gate.removeAttribute('data-ol-link-gate');
                    var btn = gate.querySelector('[data-ol-next-btn]');
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = btn.getAttribute('data-ol-next-label') || 'Next';
                    }
                }
                var status = document.querySelector('[data-ol-link-status]');
                if (status) {
                    status.textContent = 'Opened';
                    status.className = 'small text-success mt-3 mb-0';
                }
            }).catch(function () {
            }).finally(function () {
                if (sameTab) {
                    window.location.href = link.href;
                }
            });
        });
    });

    document.querySelectorAll('[data-ol-timer]').forEach(function (box) {
        var label = box.querySelector('[data-ol-timer-label]');
        var form = document.querySelector('[data-ol-timed]');
        var endAt = Date.now() + Math.max(0, parseInt(box.getAttribute('data-remaining') || '0', 10)) * 1000;
        var sent = false;
        var tick = function () {
            var left = Math.max(0, Math.round((endAt - Date.now()) / 1000));
            if (label) {
                label.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
            }
            if (left <= 60) {
                box.classList.remove('alert-secondary');
                box.classList.add('alert-warning');
            }
            if (left <= 0 && form && !sent) {
                sent = true;
                form.noValidate = true;
                form.submit();
                return;
            }
            window.setTimeout(tick, 1000);
        };
        tick();
    });

    document.querySelectorAll('[data-ol-quiz]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var missing = false;
            form.querySelectorAll('[data-ol-required-group]').forEach(function (group) {
                var checked = group.querySelector('input[type="radio"]:checked');
                if (!checked) {
                    missing = true;
                    group.classList.add('border', 'border-danger');
                }
            });
            form.querySelectorAll('[data-ol-required-essay]').forEach(function (area) {
                if (!String(area.value || '').trim()) {
                    missing = true;
                    area.classList.add('is-invalid');
                }
            });
            if (missing) {
                event.preventDefault();
                if (window.showToast) {
                    window.showToast('Please complete the required questions before submitting.', 'warning');
                }
            }
        });
    });

    var liveForm = document.querySelector('[data-ol-live]');
    if (liveForm) {
        initLiveTracking(liveForm);
    }

    function initLiveTracking(form) {
        var endpoint = form.getAttribute('data-ol-live') || '';
        var csrf = form.getAttribute('data-csrf') || '';
        var itemId = form.getAttribute('data-item-id') || '';
        var groups = Array.prototype.slice.call(form.querySelectorAll('[data-ol-qid]'));
        if (!endpoint || !itemId || groups.length === 0) {
            return;
        }
        var current = '';
        var pendingQuestion = 0;
        var interacted = false;
        var submitted = false;
        var heartbeatMs = 30000;
        var textState = {};

        function qidOf(node) {
            var group = node && node.closest ? node.closest('[data-ol-qid]') : null;
            return group ? group.getAttribute('data-ol-qid') : '';
        }

        function answerMap() {
            var map = {};
            groups.forEach(function (group) {
                var qid = group.getAttribute('data-ol-qid');
                var radio = group.querySelector('input[type="radio"]:checked');
                var area = group.querySelector('textarea');
                if (radio) {
                    map[qid] = parseInt(radio.value, 10);
                } else if (area && String(area.value || '').trim() !== '') {
                    map[qid] = 't';
                    textState[qid] = true;
                }
            });
            return map;
        }

        function send(event, extra, useBeacon) {
            var body = new URLSearchParams();
            body.set('csrf_token', csrf);
            body.set('item_id', itemId);
            body.set('event', event);
            Object.keys(extra || {}).forEach(function (key) {
                body.set(key, String(extra[key]));
            });
            if (useBeacon && navigator.sendBeacon) {
                navigator.sendBeacon(endpoint, body);
                return;
            }
            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: useBeacon === true,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
                body: body.toString()
            }).catch(function () {});
        }

        function setCurrent(qid, immediate) {
            if (!qid || qid === current) {
                return;
            }
            current = qid;
            window.clearTimeout(pendingQuestion);
            pendingQuestion = window.setTimeout(function () {
                send('question', { question_id: qid });
            }, immediate ? 0 : 1500);
        }

        function sendOpen() {
            var first = current || groups[0].getAttribute('data-ol-qid');
            current = first;
            send('open', { question_id: first, answers: JSON.stringify(answerMap()) });
        }

        form.addEventListener('focusin', function (event) {
            setCurrent(qidOf(event.target), true);
        });
        form.addEventListener('change', function (event) {
            var target = event.target;
            var qid = qidOf(target);
            if (!qid) {
                return;
            }
            window.clearTimeout(pendingQuestion);
            current = qid;
            interacted = true;
            if (target.type === 'radio') {
                send('answer', { question_id: qid, choice: target.value });
            } else if (target.tagName === 'TEXTAREA') {
                var filled = String(target.value || '').trim() !== '';
                textState[qid] = filled;
                send('answer', { question_id: qid, text: filled ? 1 : 0 });
            }
        });
        form.addEventListener('input', function (event) {
            var target = event.target;
            if (target.tagName !== 'TEXTAREA') {
                return;
            }
            var qid = qidOf(target);
            var filled = String(target.value || '').trim() !== '';
            if (qid && !!textState[qid] !== filled) {
                textState[qid] = filled;
                current = qid;
                send('answer', { question_id: qid, text: filled ? 1 : 0 });
            }
        });

        if ('IntersectionObserver' in window) {
            var visible = {};
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    visible[entry.target.getAttribute('data-ol-qid')] = entry.isIntersecting ? entry.target : null;
                });
                var mid = window.innerHeight * 0.4;
                var best = '';
                var bestDist = Infinity;
                Object.keys(visible).forEach(function (qid) {
                    var el = visible[qid];
                    if (!el) {
                        return;
                    }
                    var rect = el.getBoundingClientRect();
                    var dist = rect.top <= mid && rect.bottom >= mid ? 0 : Math.min(Math.abs(rect.top - mid), Math.abs(rect.bottom - mid));
                    if (dist < bestDist) {
                        bestDist = dist;
                        best = qid;
                    }
                });
                if (best !== '') {
                    setCurrent(best, false);
                }
            }, { threshold: [0, 0.25, 0.5, 0.75, 1] });
            groups.forEach(function (group) { observer.observe(group); });
        }

        var markActive = function () { interacted = true; };
        ['keydown', 'pointerdown', 'touchstart', 'wheel', 'scroll'].forEach(function (name) {
            window.addEventListener(name, markActive, { passive: true });
        });
        var lastMove = 0;
        window.addEventListener('mousemove', function () {
            var now = Date.now();
            if (now - lastMove > 5000) {
                lastMove = now;
                interacted = true;
            }
        }, { passive: true });

        window.setInterval(function () {
            if (submitted) {
                return;
            }
            send('heartbeat', { interacted: interacted ? 1 : 0, visible: document.hidden ? 0 : 1 });
            interacted = false;
        }, heartbeatMs);

        document.addEventListener('visibilitychange', function () {
            if (submitted) {
                return;
            }
            send('heartbeat', { interacted: document.hidden ? 0 : 1, visible: document.hidden ? 0 : 1 });
        });
        window.addEventListener('pagehide', function () {
            if (!submitted) {
                send('leave', {}, true);
            }
        });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted && !submitted) {
                sendOpen();
            }
        });
        form.addEventListener('submit', function (event) {
            if (!event.defaultPrevented) {
                submitted = true;
            }
        });

        sendOpen();
    }

    var watchBox = document.querySelector('[data-ol-watch]');
    if (watchBox) {
        initWatchGate(watchBox);
    }

    function initWatchGate(box) {
        var required = box.getAttribute('data-required') === '1';
        var enough = box.getAttribute('data-enough') === '1';
        var minPercent = parseInt(box.getAttribute('data-min') || '0', 10) || 0;
        var maxWatched = parseInt(box.getAttribute('data-seconds') || '0', 10) || 0;
        var duration = parseInt(box.getAttribute('data-duration') || '0', 10) || 0;
        var itemId = box.getAttribute('data-item-id');
        var endpoint = box.getAttribute('data-endpoint');
        var csrf = box.getAttribute('data-csrf');
        var iframe = box.querySelector('iframe');
        var lastSent = 0;
        var lastPostedAt = 0;
        var seekingBack = false;

        function applyWatch(watch) {
            if (!watch) {
                return;
            }
            if (typeof watch.seconds === 'number') {
                maxWatched = Math.max(maxWatched, watch.seconds);
            }
            if (typeof watch.duration === 'number' && watch.duration > 0) {
                duration = watch.duration;
            }
            enough = !!watch.enough;
            updateUi(watch.percent);
            if (enough) {
                unlockNext();
            }
        }

        function percentNow() {
            if (duration < 1) {
                return 0;
            }
            return Math.floor(100 * Math.min(maxWatched, duration) / duration);
        }

        function updateUi(pct) {
            if (typeof pct !== 'number') {
                pct = percentNow();
            }
            var bar = document.querySelector('[data-ol-watch-bar]');
            var labelPct = document.querySelector('[data-ol-watch-pct]');
            if (bar) {
                bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
            }
            if (labelPct) {
                labelPct.textContent = Math.max(0, Math.min(100, pct)) + '%';
            }
        }

        function unlockNext() {
            var form = document.querySelector('[data-ol-watch-gate]');
            if (!form) {
                return;
            }
            form.setAttribute('data-ol-watch-gate', 'done');
            var btn = form.querySelector('[data-ol-next-btn]');
            if (btn) {
                btn.disabled = false;
                btn.textContent = btn.getAttribute('data-ol-next-label') || 'Next';
            }
            var ui = document.querySelector('[data-ol-watch-ui]');
            if (ui) {
                ui.classList.add('is-done');
                var lab = ui.querySelector('[data-ol-watch-label]');
                if (lab) {
                    lab.textContent = 'Clip watched — you can continue';
                }
            }
        }

        function postProgress(ended) {
            if (!endpoint || !csrf || !itemId) {
                return;
            }
            var now = Date.now();
            if (!ended && now - lastPostedAt < 4000 && Math.abs(maxWatched - lastSent) < 3) {
                return;
            }
            lastPostedAt = now;
            lastSent = maxWatched;
            var body = new FormData();
            body.append('csrf_token', csrf);
            body.append('item_id', itemId);
            body.append('seconds', String(Math.floor(maxWatched)));
            body.append('duration', String(Math.floor(duration)));
            if (ended) {
                body.append('ended', '1');
            }
            fetch(endpoint, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.ok && data.watch) {
                        applyWatch(data.watch);
                    }
                })
                .catch(function () {});
        }

        function onTime(seconds, dur, ended) {
            if (typeof dur === 'number' && dur > 0) {
                duration = dur;
            }
            if (!ended && duration > 0 && !enough && typeof seconds === 'number' && seconds > maxWatched + 5) {
                updateUi();
                return;
            }
            if (typeof seconds === 'number' && seconds > maxWatched) {
                maxWatched = seconds;
            }
            if (ended) {
                if (duration > 0) {
                    maxWatched = Math.max(maxWatched, duration);
                }
                enough = true;
            } else if (minPercent <= 0 || (duration > 0 && percentNow() >= minPercent)) {
                enough = true;
            }
            updateUi();
            if (enough) {
                unlockNext();
            }
            postProgress(!!ended);
        }

        function parseTimeData(raw) {
            var data = raw;
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (e) { return null; }
            }
            if (!data || typeof data !== 'object') {
                return null;
            }
            var seconds = data.seconds;
            var dur = data.duration;
            if (typeof seconds !== 'number' && data.value && typeof data.value === 'object') {
                seconds = data.value.seconds;
                dur = data.value.duration;
            }
            if (typeof seconds !== 'number') {
                return null;
            }
            return { seconds: seconds, duration: typeof dur === 'number' ? dur : duration };
        }

        if (!required || enough) {
            if (enough) {
                unlockNext();
            }
            return;
        }

        window.addEventListener('message', function (event) {
            var origin = String(event.origin || '');
            if (origin.indexOf('mediadelivery.net') === -1 && origin.indexOf('bunnycdn.com') === -1) {
                return;
            }
            var payload = event.data;
            if (typeof payload === 'string') {
                try { payload = JSON.parse(payload); } catch (e) { return; }
            }
            if (!payload || typeof payload !== 'object') {
                return;
            }
            var eventName = payload.event || payload.method || payload.type;
            if (eventName === 'ended' || eventName === 'complete') {
                onTime(duration || maxWatched, duration, true);
                return;
            }
            var parsed = parseTimeData(payload.value || payload.data || payload);
            if (parsed) {
                onTime(parsed.seconds, parsed.duration, false);
            }
        });

        function bindPlayerJs() {
            if (!iframe || !window.playerjs || typeof window.playerjs.Player !== 'function') {
                return;
            }
            var player = new window.playerjs.Player(iframe);
            player.on('ready', function () {
                if (typeof player.getDuration === 'function') {
                    player.getDuration(function (d) {
                        if (typeof d === 'number' && d > 0) {
                            duration = d;
                        }
                    });
                }
                player.on('timeupdate', function (timing) {
                    var parsed = parseTimeData(timing);
                    if (!parsed) {
                        return;
                    }
                    if (typeof parsed.duration === 'number' && parsed.duration > 0) {
                        duration = parsed.duration;
                    }
                    if (!seekingBack && duration > 0 && !enough && parsed.seconds > maxWatched + 5) {
                        seekingBack = true;
                        if (typeof player.setCurrentTime === 'function') {
                            player.setCurrentTime(maxWatched);
                        }
                        setTimeout(function () { seekingBack = false; }, 400);
                        return;
                    }
                    onTime(parsed.seconds, parsed.duration, false);
                });
                player.on('ended', function () {
                    onTime(duration || maxWatched, duration, true);
                });
            });
        }

        if (window.playerjs) {
            bindPlayerJs();
        } else {
            var script = document.createElement('script');
            script.src = 'https://assets.mediadelivery.net/playerjs/playerjs-latest.min.js';
            script.async = true;
            script.onload = bindPlayerJs;
            script.onerror = function () {
                var fallback = document.createElement('script');
                fallback.src = 'https://cdn.jsdelivr.net/npm/player.js@0.1.0/dist/player.min.js';
                fallback.async = true;
                fallback.onload = bindPlayerJs;
                document.head.appendChild(fallback);
            };
            document.head.appendChild(script);
        }
    }

    var timeHost = document.querySelector('[data-ol-time]');
    if (timeHost) {
        var timeLast = Date.now();
        var timeAcc = 0;
        var timeBusy = false;
        window.setInterval(function () {
            var now = Date.now();
            if (document.hidden) {
                timeLast = now;
                return;
            }
            var delta = Math.round((now - timeLast) / 1000);
            timeLast = now;
            if (delta < 1 || delta > 40) {
                return;
            }
            timeAcc += delta;
            if (timeAcc < 25 || timeBusy) {
                return;
            }
            var send = timeAcc;
            timeAcc = 0;
            timeBusy = true;
            var body = new URLSearchParams();
            body.set('csrf_token', timeHost.getAttribute('data-csrf') || '');
            body.set('item_id', timeHost.getAttribute('data-item-id') || '');
            body.set('seconds', String(send));
            fetch(timeHost.getAttribute('data-endpoint') || '', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).catch(function () {
                timeAcc += send;
            }).finally(function () {
                timeBusy = false;
            });
        }, 5000);

        // Send time still under the 25 s batch when the student leaves or hides the tab.
        var flushTime = function () {
            var now = Date.now();
            var delta = Math.round((now - timeLast) / 1000);
            timeLast = now;
            if (delta >= 1 && delta <= 40) {
                timeAcc += delta;
            }
            if (timeAcc < 1 || !navigator.sendBeacon) {
                return;
            }
            var body = new URLSearchParams();
            body.set('csrf_token', timeHost.getAttribute('data-csrf') || '');
            body.set('item_id', timeHost.getAttribute('data-item-id') || '');
            body.set('seconds', String(Math.min(timeAcc, 40)));
            body.set('final', '1');
            if (navigator.sendBeacon(timeHost.getAttribute('data-endpoint') || '', body)) {
                timeAcc = 0;
            }
        };
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                flushTime();
            } else {
                timeLast = Date.now();
            }
        });
        window.addEventListener('pagehide', function () {
            if (!document.hidden) {
                flushTime();
            }
        });
    }
})();
