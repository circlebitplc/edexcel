(function () {
    const root = document.getElementById('coursoApp');
    if (!root) return;

    const api = root.getAttribute('data-api') || '/api/courso.php';
    const csrf = root.getAttribute('data-csrf') || '';

    const qs = (id) => document.getElementById(id);

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

    function showPanel(name) {
        root.querySelectorAll('.courso-panel').forEach(function (el) {
            const on = el.getAttribute('data-panel') === name;
            el.hidden = !on;
            el.classList.toggle('is-on', on);
        });
        root.querySelectorAll('[data-courso-panel]').forEach(function (a) {
            a.classList.toggle('is-on', a.getAttribute('data-courso-panel') === name);
        });
        if (name === 'community') loadThread();
        try {
            history.replaceState(null, '', '#' + name);
        } catch (e) {}
    }

    root.querySelectorAll('[data-courso-panel]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            showPanel(a.getAttribute('data-courso-panel'));
        });
    });

    const hash = (location.hash || '#assistant').replace('#', '') || 'assistant';
    showPanel(['assistant', 'progress', 'practice', 'search', 'community', 'prefs'].indexOf(hash) >= 0 ? hash : 'assistant');

    function bubble(role, text, meta) {
        const log = qs('coursoLog');
        if (!log) return;
        const wrap = document.createElement('div');
        wrap.className = 'courso-bubble ' + (role === 'user' ? 'is-user' : 'is-ai');
        const body = document.createElement('div');
        body.textContent = text;
        wrap.appendChild(body);
        const id = meta && meta.id ? parseInt(meta.id, 10) : 0;
        if (role === 'assistant' && id > 0) {
            const bar = document.createElement('div');
            bar.className = 'courso-rate';
            bar.innerHTML = '<button type="button" data-rate="helpful" title="This helped">Helpful</button>'
                + '<button type="button" data-rate="unhelpful" title="Not strong enough">Not quite</button>';
            const current = (meta.rating || '');
            bar.querySelectorAll('button').forEach(function (btn) {
                if (btn.getAttribute('data-rate') === current) btn.classList.add('is-on');
                btn.addEventListener('click', function () {
                    call('rate', { message_id: id, helpful: btn.getAttribute('data-rate') === 'helpful' ? 1 : 0 })
                        .then(function (data) {
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

    call('history').then(function (data) {
        (data.messages || []).forEach(function (m) {
            bubble(m.role, m.content, { id: m.id, rating: m.rating });
        });
        if (!(data.messages || []).length) {
            bubble('assistant', 'I already know your classes, marks, and streak. Tell me a goal (for example “A in IAL Chemistry”) or ask what to study today.');
        }
    }).catch(function () {});

    const chatForm = qs('coursoChatForm');
    if (chatForm) {
        chatForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const box = qs('coursoMessage');
            const text = (box.value || '').trim();
            if (!text) return;
            bubble('user', text);
            box.value = '';
            const send = qs('coursoSend');
            send.disabled = true;
            call('chat', { message: text }).then(function (data) {
                bubble('assistant', data.reply || '', { id: data.message_id });
                if (data.snapshot) paintSnapshot(data.snapshot);
            }).catch(function (err) {
                bubble('assistant', err.message || 'I could not reply just then.');
            }).finally(function () {
                send.disabled = false;
            });
        });
    }

    function paintSnapshot(s) {
        if (!s) return;
        const set = function (id, v) { const el = qs(id); if (el) el.textContent = String(v); };
        set('coursoStreak', s.streak || 0);
        set('coursoLessons', s.completed_lessons || 0);
        set('coursoQuizzes', s.quizzes_done || 0);
        set('coursoDiffLabel', s.difficulty || 'core');
        const steps = qs('coursoSteps');
        if (steps && s.next_steps) {
            steps.innerHTML = s.next_steps.map(function (step) {
                return '<a class="list-group-item list-group-item-action py-3" href="' + escapeAttr(step.href) + '">'
                    + '<div class="fw-semibold">' + escapeHtml(step.title) + '</div>'
                    + '<div class="small text-muted">' + escapeHtml(step.why) + '</div></a>';
            }).join('');
        }
        fillList('coursoStrengths', s.strengths, 'Marks and quizzes will fill this in.');
        fillList('coursoWeak', s.weaknesses, 'No weak spots flagged yet.');
    }

    function fillList(id, items, empty) {
        const el = qs(id);
        if (!el) return;
        if (!items || !items.length) {
            el.innerHTML = '<p class="text-muted mb-0">' + escapeHtml(empty) + '</p>';
            return;
        }
        el.innerHTML = '<ul class="mb-0">' + items.map(function (x) { return '<li>' + escapeHtml(x) + '</li>'; }).join('') + '</ul>';
    }

    qs('coursoStartQuiz') && qs('coursoStartQuiz').addEventListener('click', function () {
        const btn = qs('coursoStartQuiz');
        btn.disabled = true;
        call('quiz_start', { topic: (qs('coursoTopic') && qs('coursoTopic').value) || '' }).then(function (data) {
            renderQuiz(data.quiz, false);
        }).catch(function (err) {
            qs('coursoQuiz').innerHTML = '<div class="alert alert-warning">' + escapeHtml(err.message) + '</div>';
        }).finally(function () { btn.disabled = false; });
    });

    function renderQuiz(quiz, revealed) {
        const box = qs('coursoQuiz');
        if (!box || !quiz) return;
        const items = quiz.items || [];
        let html = '<form id="coursoQuizForm">';
        items.forEach(function (item, i) {
            html += '<div class="courso-q"><div class="fw-semibold mb-2">' + (i + 1) + '. ' + escapeHtml(item.prompt) + '</div>';
            (item.choices || []).forEach(function (c, idx) {
                const name = 'q' + item.id;
                html += '<label><input type="radio" name="' + name + '" value="' + idx + '"'
                    + (revealed && item.student_choice === idx ? ' checked' : '')
                    + (revealed ? ' disabled' : '') + '> ' + escapeHtml(c) + '</label>';
            });
            if (revealed) {
                const ok = item.is_correct;
                html += '<div class="courso-feedback ' + (ok ? 'is-ok' : 'is-no') + '">'
                    + escapeHtml(item.feedback || item.explanation || '') + '</div>';
                if (item.example) {
                    html += '<div class="small text-muted mt-1">Example: ' + escapeHtml(item.example) + '</div>';
                }
            }
            html += '</div>';
        });
        if (!revealed) {
            html += '<button class="btn btn-primary" type="submit">See feedback</button>';
        } else {
            html += '<p class="fw-semibold">Score: ' + (quiz.score ?? '—') + ' / ' + quiz.max_score + '</p>';
        }
        html += '</form>';
        box.innerHTML = html;
        const form = qs('coursoQuizForm');
        if (form && !revealed) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const answers = {};
                items.forEach(function (item) {
                    const picked = form.querySelector('input[name="q' + item.id + '"]:checked');
                    answers[item.id] = picked ? parseInt(picked.value, 10) : -1;
                });
                call('quiz_submit', { quiz_id: quiz.id, answers: answers }).then(function (data) {
                    renderQuiz(data.quiz, true);
                    if (data.snapshot) paintSnapshot(data.snapshot);
                }).catch(function (err) {
                    if (err && (err.name === 'TypeError' || err.name === 'AbortError') && window.showToast) {
                        return;
                    }
                    alert(err && err.message ? err.message : 'Could not submit the quiz.');
                });
            });
        }
    }

    let searchTimer = null;
    const searchInput = qs('coursoSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            const q = searchInput.value.trim();
            searchTimer = setTimeout(function () {
                if (q.length < 2) {
                    qs('coursoSearchHits').innerHTML = '';
                    return;
                }
                fetch(api + '?action=search&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        const hits = data.results || [];
                        qs('coursoSearchHits').innerHTML = hits.length
                            ? hits.map(function (h) {
                                return '<a class="list-group-item list-group-item-action" href="' + escapeAttr(h.href) + '">'
                                    + '<span class="badge text-bg-secondary me-2">' + escapeHtml(h.type) + '</span>'
                                    + '<strong>' + escapeHtml(h.title) + '</strong>'
                                    + '<div class="small text-muted">' + escapeHtml(h.blurb || '') + '</div></a>';
                            }).join('')
                            : '<div class="text-muted">No matches. Try a subject name or teacher.</div>';
                    }).catch(function () {});
            }, 250);
        });
    }

    function loadThread() {
        const sel = qs('coursoClass');
        const thread = qs('coursoThread');
        if (!sel || !thread || !sel.value) return;
        fetch(api + '?action=community&class_id=' + encodeURIComponent(sel.value), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                thread.innerHTML = renderThread(data.thread || []);
                thread.querySelectorAll('[data-reply]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const id = btn.getAttribute('data-reply');
                        const text = prompt('Reply:');
                        if (!text) return;
                        call('community', { class_id: parseInt(sel.value, 10), parent_id: parseInt(id, 10), body: text })
                            .then(function () { loadThread(); })
                            .catch(function (err) { alert(err.message); });
                    });
                });
            }).catch(function () {});
    }

    function renderThread(posts) {
        return posts.map(function (p) {
            const replies = (p.replies || []).map(function (r) {
                return '<div class="courso-post"><div class="small text-muted">' + escapeHtml(r.author || 'Student')
                    + '</div><div>' + escapeHtml(r.body) + '</div></div>';
            }).join('');
            return '<div class="courso-post"><div class="small text-muted">' + escapeHtml(p.author || 'Student')
                + '</div><div>' + escapeHtml(p.body) + '</div>'
                + '<button type="button" class="btn btn-link btn-sm px-0" data-reply="' + p.id + '">Reply</button>'
                + (replies ? '<div class="courso-replies">' + replies + '</div>' : '')
                + '</div>';
        }).join('') || '<p class="text-muted">No questions yet. Be the first.</p>';
    }

    const classSel = qs('coursoClass');
    if (classSel) classSel.addEventListener('change', loadThread);

    const postForm = qs('coursoPostForm');
    if (postForm) {
        postForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const sel = qs('coursoClass');
            const body = (qs('coursoPostBody').value || '').trim();
            if (!body || !sel) return;
            call('community', { class_id: parseInt(sel.value, 10), body: body }).then(function () {
                qs('coursoPostBody').value = '';
                loadThread();
            }).catch(function (err) {
                if (err && (err.name === 'TypeError' || err.name === 'AbortError') && window.showToast) {
                    return;
                }
                alert(err && err.message ? err.message : 'Could not post that message.');
            });
        });
    }

    const prefForm = qs('coursoPrefForm');
    if (prefForm) {
        prefForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const days = [];
            prefForm.querySelectorAll('input[name="study_days"]:checked').forEach(function (cb) {
                days.push(cb.value);
            });
            const payload = {
                goals: qs('coursoGoals').value,
                interests: qs('coursoInterests').value,
                ai_style: qs('coursoStyle').value,
                difficulty: qs('coursoDifficulty').value,
                density: qs('coursoDensity').value,
                study_hour: qs('coursoHour').value,
                study_days: days.join(','),
                notify_study: qs('nStudy').checked ? 1 : 0,
                notify_streak: qs('nStreak').checked ? 1 : 0,
                notify_community: qs('nComm').checked ? 1 : 0,
            };
            call('profile', payload).then(function (data) {
                qs('coursoPrefStatus').textContent = 'Saved.';
                root.setAttribute('data-density', payload.density);
                if (data.snapshot) paintSnapshot(data.snapshot);
            }).catch(function (err) {
                qs('coursoPrefStatus').textContent = err.message;
            });
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escapeAttr(s) {
        return escapeHtml(s).replace(/'/g, '&#39;');
    }
})();
