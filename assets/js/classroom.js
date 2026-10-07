(function () {
    'use strict';

    if (window.__CK_CLASSROOM_BOOTED__) return;
    window.__CK_CLASSROOM_BOOTED__ = true;

    var cfg = window.CK_CONFIG || {};
    var LK = window.LivekitClient;
    var room = null;
    var previewStream = null;
    var lastChatId = 0;
    var lastStrokeId = 0;
    var boardAutoOpened = false;
    var boardApi = null;
    var chatMode = 'class';
    var privatePeers = {};
    var selectedPrivatePeer = 0;
    var privateTabUnlocked = !!cfg.isHost;
    var privateUnread = false;
    var handUp = false;
    var connected = false;
    var pollTimer = null;
    var hbTimer = null;
    var pagehideBound = false;
    var visibilityBound = false;
    var localCamBlocked = false;
    var localMicBlocked = false;
    var localShareAllowed = false;
    var pendingCamForce = '';
    var lastCamForceToastAt = 0;
    var camBlockState = {};
    var micBlockState = {};
    var shareAllowState = {};
    var viewMode = 'presentation';
    var studentDisplayMode = 'board';
    var activeSpeakerId = '';
    var audioSinkId = '';
    var startedAtMs = 0;
    var elapsedTimer = null;
    var lastHandKey = '';
    var lastWaitKind = cfg.waitKind || '';
    var settingsPreviewTrack = null;
    var stageFocus = '';
    var stageFocusHeld = false;
    var stageDismissed = false;
    var hostBoardFocus = false;
    var hostWasBoardFs = false;
    var boardFocusHint = false;
    var railSnap = null;
    var restoringRails = false;
    var studentChatHideTimer = null;
    var studentChatHover = false;
    var chatHydrated = false;
    var STUDENT_CHAT_IDLE_MS = 5000;
    var isScreenMinimized = false;

    // Pop-out Secondary Windows & Floating Student Camera Panel State
    var activePopouts = {}; // keyed by mode: 'camera', 'chat', 'monitor'
    var bridgeChannel = null;
    var lastPopoutAttempt = null;
    var scpViewMode = 'grid'; // 'grid' or 'speaker'
    var scpPinnedId = null;
    var scpStudents = {}; // keyed by identity: { identity, name, userId, isMuted, isSpeaking, isCameraBlocked, isMicBlocked, hasVideo, mediaStreamTrack, lastActivity }
    var chatHistoryCache = [];
    var currentHandsList = [];

    var $ = function (id) {
        if (id === 'ckSide' || id === 'ckChatRail') {
            return document.getElementById('ckChatRail') || document.getElementById('ckSide');
        }
        return document.getElementById(id);
    };

    if (cfg.isHost) document.body.classList.add('ck-is-host');
    else document.body.classList.add('ck-is-student');

    function toast(msg) {
        var el = $('ckToast');
        if (!el) return;
        el.hidden = false;
        el.textContent = msg;
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.hidden = true; }, 4000);
    }

    function api(path, body, method) {
        method = method || (body ? 'POST' : 'GET');
        var url = cfg.apiBase + path + (path.indexOf('?') >= 0 ? '&' : '?') + 'lesson=' + encodeURIComponent(cfg.lessonId);
        var opts = {
            method: method,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': cfg.csrf, Accept: 'application/json' }
        };
        if (body) {
            opts.headers['Content-Type'] = 'application/json';
            body.csrf_token = cfg.csrf;
            body.lesson = cfg.lessonId;
            opts.body = JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().then(function (data) {
                data._http = res.status;
                return data;
            }).catch(function () {
                return { ok: false, error: 'Something went wrong. Please try again.', _http: res.status };
            });
        });
    }

    function isMobile() {
        return window.matchMedia('(max-width: 860px)').matches;
    }

    function studentChatEngaged() {
        if (cfg.isHost) return true;
        var side = $('ckChatRail');
        if (!side) return false;
        if (studentChatHover) return true;
        var ae = document.activeElement;
        return !!(ae && side.contains(ae));
    }

    function clearStudentChatTimer() {
        if (studentChatHideTimer) {
            clearTimeout(studentChatHideTimer);
            studentChatHideTimer = null;
        }
    }

    function scheduleStudentChatHide() {
        if (cfg.isHost) return;
        if (!document.body.classList.contains('ck-in-room')) return;
        clearStudentChatTimer();
        studentChatHideTimer = setTimeout(function () {
            studentChatHideTimer = null;
            if (studentChatEngaged()) {
                scheduleStudentChatHide();
                return;
            }
            setChatOpen(false);
        }, STUDENT_CHAT_IDLE_MS);
    }

    function bumpStudentChatIdle() {
        if (cfg.isHost) return;
        if (!railIsOpen('chat')) return;
        scheduleStudentChatHide();
    }

    function setChatDockBadge(on) {
        var tog = $('ckChatToggle');
        if (tog) tog.classList.toggle('has-unread', !!on);
    }

    function peekStudentChat() {
        if (cfg.isHost || !connected || !document.body.classList.contains('ck-in-room')) return;
        setChatDockBadge(false);
        setChatOpen(true);
    }

    function setStatus(state, label) {
        var wrap = $('ckStatus');
        var lab = $('ckStatusLabel');
        if (wrap) wrap.setAttribute('data-state', state || '');
        if (lab) lab.textContent = label || state || '';
    }

    function setRecordingUi(on) {
        cfg.recording = !!on;
        var rec = $('ckRec');
        if (rec) rec.hidden = !on;
        var start = $('ckRecStart');
        var stop = $('ckRecStop');
        if (start) start.hidden = !cfg.isHost || !connected || on;
        if (stop) stop.hidden = !cfg.isHost || !connected || !on;
        var mobRec = $('ckMobileRecLabel');
        if (mobRec) mobRec.textContent = on ? 'Stop Recording' : 'Start Recording';
        var mobRecBtn = $('ckMobileRecBtn');
        if (mobRecBtn) mobRecBtn.classList.toggle('is-on', !!on);
    }

    function boardCanDraw() {
        return !!(cfg.isHost || cfg.studentsCanDraw);
    }

    function applyCanDraw(studentsOn) {
        if (typeof studentsOn !== 'undefined') {
            cfg.studentsCanDraw = !!studentsOn;
        }
        document.body.classList.toggle('ck-students-can-draw', !!cfg.studentsCanDraw);
        var box1 = $('ckAllowDraw');
        if (box1) box1.checked = !!cfg.studentsCanDraw;
        var boxMob = $('ckMobileDrawToggle');
        if (boxMob) boxMob.checked = !!cfg.studentsCanDraw;

        if (boardApi) boardApi.updateCanDrawUi();
        else {
            var can = boardCanDraw();
            var locked = $('ckBoardLocked');
            if (locked) locked.hidden = cfg.isHost || can;
            var canvas = $('ckBoard');
            if (canvas) canvas.style.cursor = can ? 'crosshair' : 'default';
            document.querySelectorAll('#ckBoardTools [data-tool]').forEach(function (b) {
                b.disabled = !can;
            });
        }
    }

    function ensureBoard() {
        if (boardApi) return boardApi;
        if (typeof window.CKCreateBoard !== 'function') return null;
        boardApi = window.CKCreateBoard({
            $: $,
            api: api,
            publishData: publishData,
            toast: toast,
            getCfg: function () { return cfg; },
            boardCanDraw: boardCanDraw,
            applyCanDraw: applyCanDraw,
            onHostFocus: function (on) { if (cfg.isHost) setHostBoardFocus(!!on); },
            onStudentShow: function (force) { maybeShowBoardForStudent(!!force); },
            isConnected: function () { return connected; },
            localIdentity: function () { return localIdentity(); }
        });
        if (window.CKPdf && typeof window.CKPdf.init === 'function') {
            window.CKPdf.init({
                $: $,
                api: api,
                publishData: publishData,
                toast: toast,
                getCfg: function () { return cfg; },
                getBoardApi: function () { return boardApi; },
                onStudentShow: function (force) { maybeShowBoardForStudent(!!force); }
            });
        }
        return boardApi;
    }

    function publishData(obj) {
        if (!room || !room.localParticipant) return;
        try {
            room.localParticipant.publishData(
                new TextEncoder().encode(JSON.stringify(obj)),
                { reliable: true }
            );
        } catch (e) {}
    }

    function publishChatPacket(obj, destinationIdentities) {
        if (!room || !room.localParticipant) return;
        var privateChat = !!(obj && (obj.is_private || obj.t === 'chat-private-open'));
        var dest = [];
        (destinationIdentities || []).forEach(function (id) {
            if (id && dest.indexOf(id) < 0) dest.push(id);
        });
        if (privateChat && !dest.length) return;
        var opts = { reliable: true };
        if (privateChat) {
            opts.destinationIdentities = dest;
        }
        try {
            room.localParticipant.publishData(
                new TextEncoder().encode(JSON.stringify(obj)),
                opts
            );
        } catch (e) {}
    }

    function syncMask() {
        var mask = $('ckDrawerMask');
        if (!mask) return;
        var open = isMobile() && document.body.classList.contains('ck-in-room') && (
            ($('ckPeopleRail') && $('ckPeopleRail').classList.contains('is-open')) ||
            ($('ckChatRail') && $('ckChatRail').classList.contains('is-open'))
        );
        mask.hidden = !open;
    }

    function syncMainGrid() {
        var main = $('ckMain');
        if (!main) return;
        if (document.body.classList.contains('ck-full-stage')) {
            main.classList.add('ck-people-off', 'ck-side-off');
            return;
        }
        if (isMobile() || !document.body.classList.contains('ck-in-room')) {
            main.classList.add('ck-people-off', 'ck-side-off');
            return;
        }
        var people = $('ckPeopleRail');
        var side = $('ckChatRail');
        main.classList.toggle('ck-people-off', !cfg.isHost || !people || people.hidden);
        main.classList.toggle('ck-side-off', !side || side.hidden);
    }

    var peoplePinned = false;
    var chatPinned = false;
    var peopleHideTimer = null;
    var chatHideTimer = null;
    var chatUnreadCount = 0;

    function setPeoplePinned(pinned) {
        peoplePinned = !!pinned;
        var btn = $('ckPeoplePinBtn');
        var rail = $('ckPeopleRail');
        if (btn) {
            btn.classList.toggle('is-pinned', peoplePinned);
            btn.setAttribute('aria-pressed', peoplePinned ? 'true' : 'false');
            btn.title = peoplePinned ? 'Unpin panel' : 'Pin panel';
        }
        if (rail) rail.classList.toggle('is-pinned', peoplePinned);
        if (peoplePinned) {
            if (peopleHideTimer) { clearTimeout(peopleHideTimer); peopleHideTimer = null; }
            setPeopleOpen(true, true);
            toast('People panel pinned');
        } else {
            toast('People panel unpinned');
        }
    }

    function setChatPinned(pinned) {
        chatPinned = !!pinned;
        var btn = $('ckChatPinBtn');
        var rail = $('ckChatRail');
        if (btn) {
            btn.classList.toggle('is-pinned', chatPinned);
            btn.setAttribute('aria-pressed', chatPinned ? 'true' : 'false');
            btn.title = chatPinned ? 'Unpin panel' : 'Pin panel';
        }
        if (rail) rail.classList.toggle('is-pinned', chatPinned);
        if (chatPinned) {
            if (chatHideTimer) { clearTimeout(chatHideTimer); chatHideTimer = null; }
            setChatOpen(true, true);
            toast('Classroom panel pinned');
        } else {
            toast('Classroom panel unpinned');
        }
    }

    function schedulePeopleHide() {
        if (peoplePinned) return;
        if (peopleHideTimer) clearTimeout(peopleHideTimer);
        peopleHideTimer = setTimeout(function () {
            setPeopleOpen(false);
        }, 320);
    }

    function cancelPeopleHide() {
        if (peopleHideTimer) { clearTimeout(peopleHideTimer); peopleHideTimer = null; }
        setPeopleOpen(true);
    }

    function scheduleChatHide() {
        if (chatPinned) return;
        var rail = $('ckChatRail');
        if (document.activeElement && rail && rail.contains(document.activeElement)) return;
        if (chatHideTimer) clearTimeout(chatHideTimer);
        chatHideTimer = setTimeout(function () {
            var r = $('ckChatRail');
            if (document.activeElement && r && r.contains(document.activeElement)) return;
            setChatOpen(false);
        }, 320);
    }

    function cancelChatHide() {
        if (chatHideTimer) { clearTimeout(chatHideTimer); chatHideTimer = null; }
        setChatOpen(true);
    }

    function updateChatUnread(count) {
        chatUnreadCount = Math.max(0, count || 0);
        var edgeBadge = $('ckChatEdgeBadge');
        if (edgeBadge) {
            edgeBadge.textContent = String(chatUnreadCount);
            edgeBadge.hidden = chatUnreadCount <= 0;
        }
        var mobBadge = $('ckMobileChatBadge');
        if (mobBadge) {
            mobBadge.textContent = String(chatUnreadCount);
            mobBadge.hidden = chatUnreadCount <= 0;
        }
        setChatDockBadge(chatUnreadCount > 0);
    }

    function clearChatUnread() {
        updateChatUnread(0);
    }

    function setPeopleOpen(on, force) {
        if (!cfg.isHost) {
            var closed = $('ckPeopleRail');
            if (closed) {
                closed.hidden = true;
                closed.classList.remove('is-open');
                closed.setAttribute('aria-hidden', 'true');
            }
            syncMask();
            syncMainGrid();
            return;
        }
        if (!on && peoplePinned && !force) return;
        if (on && peopleHideTimer) { clearTimeout(peopleHideTimer); peopleHideTimer = null; }

        var rail = $('ckPeopleRail');
        if (!rail) return;
        rail.hidden = false;
        rail.classList.toggle('is-open', !!on);
        var tog = $('ckPeopleToggle');
        if (tog) tog.classList.toggle('is-on', !!on);
        syncMask();
        syncMainGrid();
        if (on && boardPanelOpen()) {
            replayBoardLog();
            fetchBoard(true);
        }
    }

    function setChatOpen(on, force) {
        var side = $('ckChatRail');
        if (!side) return;
        if (!on && chatPinned && !force) return;
        if (!on && !force && document.activeElement && side.contains(document.activeElement)) return;

        if (on && chatHideTimer) { clearTimeout(chatHideTimer); chatHideTimer = null; }

        side.hidden = false;
        side.classList.toggle('is-open', !!on);
        var tog = $('ckChatToggle');
        if (tog) tog.classList.toggle('is-on', !!on);
        if (on) {
            clearChatUnread();
        }
        syncMask();
        syncMainGrid();
    }

    function hideRails() {
        if (peopleHideTimer) clearTimeout(peopleHideTimer);
        if (chatHideTimer) clearTimeout(chatHideTimer);
        clearStudentChatTimer();
        studentChatHover = false;
        setPeopleOpen(false, true);
        setChatOpen(false, true);
    }

    /* --- Student Display Mode & Auto-Switch State Machine --- */
    var studentDisplayMode = 'board';
    var studentManualMode = null;
    var currentTeacherActivity = 'board';
    var autoSwitchDebounceTimer = null;
    var lastBoardStrokeTime = 0;

    function onTeacherActivityChange(newActivity) {
        if (cfg.isHost) return;
        if (!document.body.classList.contains('ck-in-room')) return;
        if (newActivity === currentTeacherActivity) return;

        currentTeacherActivity = newActivity;
        // Teacher explicitly changed presentation activity -> clear student manual preference
        studentManualMode = null;

        if (autoSwitchDebounceTimer) clearTimeout(autoSwitchDebounceTimer);
        autoSwitchDebounceTimer = setTimeout(function () {
            if (studentManualMode) return;
            setStudentDisplayMode(currentTeacherActivity, false);
        }, 400);
    }

    function publishBoardFocus(on, identities) {
        if (!room || !room.localParticipant || !cfg.isHost) return;
        var obj = { t: 'board-focus', on: !!on };
        var opts = { reliable: true };
        if (identities && identities.length) opts.destinationIdentities = identities;
        try {
            room.localParticipant.publishData(new TextEncoder().encode(JSON.stringify(obj)), opts);
        } catch (e) {}
    }

    function setHostBoardFocus(on) {
        if (!cfg.isHost) return;
        on = !!on;
        if (on && anyoneSharing()) on = false;
        if (hostBoardFocus === on) return;
        hostBoardFocus = on;
        publishBoardFocus(on);
    }

    function boardStageEl() {
        return $('ckBoardStage') || $('ckBoardPanel');
    }

    function ensureStageChrome() {
        var stage = $('ckStage');
        if (!stage) return;
        if (!$('ckStageBoard')) {
            var wrap = document.createElement('div');
            wrap.id = 'ckStageBoard';
            wrap.className = 'ck-stage-board';
            wrap.hidden = true;
            var hero = $('ckHero');
            if (hero && hero.nextSibling) stage.insertBefore(wrap, hero.nextSibling);
            else if (hero) stage.appendChild(wrap);
            else stage.insertBefore(wrap, stage.firstChild);
        }
        if (!cfg.isHost && !$('ckStudentFeedFs')) {
            var fsBtn = document.createElement('button');
            fsBtn.type = 'button';
            fsBtn.id = 'ckStudentFeedFs';
            fsBtn.className = 'ck-feed-fs ck-pill-fs-btn';
            fsBtn.title = 'Full screen';
            fsBtn.setAttribute('aria-label', 'Full screen');
            fsBtn.innerHTML = '<i class="bi bi-fullscreen"></i>';
            stage.appendChild(fsBtn);
        }
        if (!$('ckStageExit')) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'ckStageExit';
            btn.className = 'ck-stage-exit';
            btn.hidden = true;
            btn.textContent = 'Exit full view';
            stage.appendChild(btn);
        }
    }

    function parkBoardHome() {
        var stageEl = $('ckBoardStage');
        var home = $('ckBoardPanel');
        if (stageEl && home && stageEl.parentNode !== home) {
            home.insertBefore(stageEl, home.firstChild);
        }
        var wrap = $('ckStageBoard');
        if (wrap) {
            wrap.hidden = true;
            wrap.classList.remove('is-on');
        }
    }

    function moveBoardToStage() {
        ensureStageChrome();
        var stageEl = $('ckBoardStage');
        var wrap = $('ckStageBoard');
        if (!stageEl || !wrap) return;
        setRailPanel('board');
        if (stageEl.parentNode !== wrap) wrap.appendChild(stageEl);
        wrap.hidden = false;
        wrap.classList.add('is-on');
    }

    function railIsOpen(kind) {
        if (kind === 'people') {
            var rail = $('ckPeopleRail');
            if (!rail) return false;
            return rail.classList.contains('is-open');
        }
        var side = $('ckChatRail');
        if (!side) return false;
        return side.classList.contains('is-open');
    }

    function snapshotRails() {
        if (railSnap || restoringRails) return;
        railSnap = {
            people: railIsOpen('people'),
            chat: railIsOpen('chat')
        };
    }

    function restoreRails() {
        if (restoringRails) return;
        restoringRails = true;
        var snap = railSnap;
        railSnap = null;
        document.body.classList.remove('ck-full-stage', 'ck-focus-board', 'ck-focus-share');
        var exitBtn = $('ckStageExit');
        if (exitBtn) exitBtn.hidden = true;
        try {
            if (snap) {
                setPeopleOpen(!!cfg.isHost && (peoplePinned || !!snap.people));
                setChatOpen(chatPinned || !!snap.chat);
            } else if (document.body.classList.contains('ck-in-room')) {
                setPeopleOpen(peoplePinned);
                setChatOpen(chatPinned);
            } else {
                setPeopleOpen(false);
                setChatOpen(false);
            }
        } finally {
            restoringRails = false;
            syncMainGrid();
            syncMask();
        }
    }

    function applyFullStageChrome(kind) {
        ensureStageChrome();
        document.body.classList.toggle('ck-full-stage', !!kind);
        document.body.classList.toggle('ck-focus-board', kind === 'board');
        document.body.classList.toggle('ck-focus-share', kind === 'share');
        var exitBtn = $('ckStageExit');
        if (exitBtn) exitBtn.hidden = !kind || !!cfg.isHost;
        if (kind) {
            snapshotRails();
            syncMainGrid();
            syncMask();
        }
    }

    function tryBrowserFullscreen(el) {
        if (!el || cfg.isHost || isFullscreen()) return;
        var req = el.requestFullscreen || el.webkitRequestFullscreen;
        if (!req) return;
        Promise.resolve(req.call(el)).catch(function () {});
    }

    function isBoardFullscreen() {
        var fs = document.fullscreenElement || document.webkitFullscreenElement;
        var stage = boardStageEl();
        if (!fs || !stage) return false;
        return fs === stage || (stage.contains && stage.contains(fs)) || (fs.contains && fs.contains(stage));
    }

    function onFullscreenChanged() {
        syncFsButtons();
        var boardFs = isBoardFullscreen();
        if (cfg.isHost) {
            if (boardFs) {
                hostWasBoardFs = true;
                setHostBoardFocus(true);
            } else if (hostWasBoardFs) {
                hostWasBoardFs = false;
                setHostBoardFocus(false);
            }
        }
        if (boardFs && stageFocus === 'board') stageFocusHeld = true;
        if (typeof window.dispatchEvent === 'function') {
            window.dispatchEvent(new Event('resize'));
        }
        refreshBoardAfterMove();
    }

    function refreshBoardAfterMove() {
        replayBoardLog();
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(function () { replayBoardLog(); });
        }
    }

    function leaveStageFocus(force) {
        if (!force && stageFocusHeld && stageFocus === 'board') return;
        var was = stageFocus;
        if (!was && !document.body.classList.contains('ck-full-stage')) {
            parkBoardHome();
            return;
        }
        if (isFullscreen() && cfg.isHost && (force || !stageFocusHeld)) {
            var fs = document.fullscreenElement || document.webkitFullscreenElement;
            var wrap = $('ckStageBoard');
            var hero = $('ckHero');
            var ours = (wrap && fs && (fs === wrap || (wrap.contains && wrap.contains(fs))))
                || (hero && fs && (fs === hero || (hero.contains && hero.contains(fs))))
                || isBoardFullscreen();
            if (ours) exitFullscreen();
        }
        stageFocus = '';
        if (force) stageFocusHeld = false;
        parkBoardHome();
        restoreRails();
        if (was === 'board') refreshBoardAfterMove();
        layoutStage();
    }

    function enterBoardFullStage(source) {
        if (cfg.isHost) return;
        if (!document.body.classList.contains('ck-in-room')) return;
        if (anyoneSharing()) return;
        if (source === 'auto' && stageDismissed) return;
        if (stageFocus === 'board') {
            applyFullStageChrome('board');
            refreshBoardAfterMove();
            return;
        }
        if (stageFocus === 'share') return;
        stageFocus = 'board';
        if (source === 'user') stageFocusHeld = true;
        moveBoardToStage();
        applyFullStageChrome('board');
        layoutStage();
        refreshBoardAfterMove();
        if (source === 'auto') tryBrowserFullscreen($('ckStage') || document.documentElement);
        setStudentDisplayMode('board', false);
    }

    function enterShareFullStage() {
        if (cfg.isHost) return;
        if (!document.body.classList.contains('ck-in-room')) return;
        if (stageFocus === 'board') {
            parkBoardHome();
            refreshBoardAfterMove();
            stageFocusHeld = false;
        }
        stageFocus = 'share';
        applyFullStageChrome('share');
        layoutStage();
        setStudentDisplayMode('share', false);
    }

    function ensureTeacherInHero() {
        var hero = $('ckHero');
        var grid = $('ckGrid');
        if (!hero || !grid) return;
        if (hero.querySelector('.ck-screen')) return;
        var pinId = teacherIdentity();
        if (!pinId || !document.getElementById('tile-' + pinId)) {
            var preferred = grid.querySelector('.ck-tile-staff');
            pinId = preferred ? tileIdentity(preferred) : '';
        }
        var tile = pinId ? document.getElementById('tile-' + pinId) : null;
        if (tile && tile.parentNode !== hero) {
            hero.classList.add('is-on');
            hero.appendChild(tile);
        } else if (tile) {
            hero.classList.add('is-on');
        }
    }

    function teacherCameraLive() {
        var id = teacherIdentity();
        if (!id || !room) return false;
        var person = null;
        if (room.localParticipant && room.localParticipant.identity === id) person = room.localParticipant;
        if (!person && room.remoteParticipants && room.remoteParticipants.get) person = room.remoteParticipants.get(id);
        if (!person && room.remoteParticipants && room.remoteParticipants.forEach) {
            room.remoteParticipants.forEach(function (p) {
                if (!person && p && p.identity === id) person = p;
            });
        }
        if (!person || !participantCameraOn(person)) return false;
        return true;
    }

    function resolveStudentFeed(requested) {
        if (requested !== 'teacher' && requested !== 'share') requested = 'board';
        var cameraOn = teacherCameraLive();
        var sharing = anyoneSharing();
        if (requested === 'teacher' && cameraOn) return 'teacher';
        if (sharing && (requested === 'share' || !cameraOn)) return 'share';
        if (requested === 'share' && cameraOn && (currentTeacherActivity === 'teacher' || studentManualMode === 'teacher')) return 'teacher';
        if (!cameraOn) return 'board';
        return requested === 'teacher' ? 'teacher' : 'board';
    }

    function refreshStudentFeed() {
        if (cfg.isHost || !document.body.classList.contains('ck-in-room')) return;
        var desired = studentManualMode || currentTeacherActivity || studentDisplayMode || 'board';
        setStudentDisplayMode(desired, false);
    }

    function paintTeacherCameraLive() {
        var live = teacherCameraLive();
        document.body.classList.toggle('ck-teacher-cam-off', !live);
    }

    function setStudentDisplayMode(mode, userInitiated) {
        if (cfg.isHost) return;
        if (mode !== 'teacher' && mode !== 'share') mode = 'board';
        var asked = mode;
        if (userInitiated) studentManualMode = mode;
        mode = resolveStudentFeed(mode);
        if (userInitiated && asked === 'share' && mode !== 'share') {
            toast('Teacher is not sharing screen right now.');
        }
        studentDisplayMode = mode;
        document.body.setAttribute('data-sd-mode', mode);

        // Sync Desktop buttons
        document.querySelectorAll('.ck-sd-btn[data-sd-mode]').forEach(function (btn) {
            var m = btn.getAttribute('data-sd-mode');
            var isCur = m === mode;
            btn.classList.toggle('is-active', isCur);
            btn.setAttribute('aria-pressed', isCur ? 'true' : 'false');
        });

        // Sync Mobile sheet tabs
        document.querySelectorAll('.ck-sheet-tab-btn[data-sd-mode]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-sd-mode') === mode);
        });

        // Sync Mobile/Desktop Dock buttons
        var mobBoard = $('ckMobBtnBoard') || $('ckMobileBoardToggle');
        if (mobBoard) {
            mobBoard.classList.toggle('is-active', mode === 'board');
            mobBoard.setAttribute('aria-pressed', mode === 'board' ? 'true' : 'false');
        }
        var mobTeacher = $('ckMobBtnTeacher');
        if (mobTeacher) {
            mobTeacher.classList.toggle('is-active', mode === 'teacher');
            mobTeacher.setAttribute('aria-pressed', mode === 'teacher' ? 'true' : 'false');
        }
        var mobShare = $('ckMobBtnShare') || $('ckMobileShare');
        if (mobShare && !cfg.isHost) {
            mobShare.classList.toggle('is-active', mode === 'share');
            mobShare.setAttribute('aria-pressed', mode === 'share' ? 'true' : 'false');
        }

        var wrap = $('ckStageBoard');
        var hero = $('ckHero');

        // CRITICAL GUARD: If still in lobby, never unhide the whiteboard or hero!
        if (!document.body.classList.contains('ck-in-room')) {
            if (wrap) {
                wrap.hidden = true;
                wrap.classList.remove('is-on');
            }
            if (hero) {
                hero.classList.remove('is-on');
            }
            return;
        }

        if (mode === 'board') {
            if (wrap) {
                wrap.hidden = false;
                wrap.classList.add('is-on');
            }
            if (hero) {
                ensureTeacherInHero();
            }
            if (boardApi && typeof boardApi.recalculateViewport === 'function') {
                boardApi.recalculateViewport();
            }
            refreshBoardAfterMove();
        } else if (mode === 'teacher') {
            if (wrap) {
                wrap.hidden = true;
                wrap.classList.remove('is-on');
            }
            if (hero) {
                ensureTeacherInHero();
                hero.classList.add('is-on');
            }
        } else if (mode === 'share') {
            if (wrap) {
                wrap.hidden = true;
                wrap.classList.remove('is-on');
            }
            if (anyoneSharing()) {
                if (isScreenMinimized) {
                    restoreTeacherScreen();
                } else if (hero) {
                    hero.classList.add('is-on');
                }
            } else {
                if (userInitiated) toast('Teacher is not sharing screen right now.');
                if (hero) {
                    ensureTeacherInHero();
                    hero.classList.add('is-on');
                }
            }
        }

        try {
            sessionStorage.setItem('ck_sd_mode_' + (cfg.meetingId || '0'), mode);
        } catch (e) {}
        applyCameraChrome();
        paintTeacherCameraLive();
        syncStudentFsChrome();
    }

    function cameraFloatActive() {
        if (cfg.isHost || window.innerWidth <= 860 || !teacherCameraLive()) return false;
        var mode = document.body.getAttribute('data-sd-mode') || 'board';
        return mode === 'board';
    }

    function camPosKey() {
        return 'ck_cam_pos_' + String(cfg.meetingId || '0');
    }

    function loadCamPos() {
        try {
            var raw = sessionStorage.getItem(camPosKey());
            if (!raw) return null;
            var pos = JSON.parse(raw);
            if (!pos || typeof pos.x !== 'number' || typeof pos.y !== 'number') return null;
            return pos;
        } catch (e) {
            return null;
        }
    }

    function saveCamPos(pos) {
        if (!pos) return;
        try { sessionStorage.setItem(camPosKey(), JSON.stringify({ x: pos.x, y: pos.y })); } catch (e) {}
    }

    function placeCamera(hero, x, y) {
        var stage = hero.parentElement;
        if (!stage) return null;
        var w = hero.offsetWidth || 170;
        var h = hero.offsetHeight || 110;
        var pad = 8;
        var maxX = Math.max(pad, stage.clientWidth - w - pad);
        var maxY = Math.max(pad, stage.clientHeight - h - pad);
        x = Math.min(maxX, Math.max(pad, x));
        y = Math.min(maxY, Math.max(pad, y));
        hero.classList.add('ck-cam-placed');
        hero.style.setProperty('left', x + 'px', 'important');
        hero.style.setProperty('top', y + 'px', 'important');
        hero.style.setProperty('right', 'auto', 'important');
        hero.style.setProperty('bottom', 'auto', 'important');
        return { x: x, y: y };
    }

    function applyCameraChrome() {
        var hero = $('ckHero');
        if (!hero || cfg.isHost) return;
        var floatOn = cameraFloatActive();
        hero.classList.toggle('ck-cam-float', floatOn);
        if (!floatOn) {
            hero.classList.remove('ck-cam-placed', 'ck-cam-drag');
            ['left', 'top', 'right', 'bottom'].forEach(function (prop) {
                hero.style.removeProperty(prop);
            });
            return;
        }
        var saved = loadCamPos();
        if (!saved) return;
        requestAnimationFrame(function () {
            if (!cameraFloatActive()) return;
            placeCamera(hero, saved.x, saved.y);
        });
    }

    function wireCameraDrag() {
        var hero = $('ckHero');
        if (!hero || hero._ckDrag) return;
        hero._ckDrag = true;
        var drag = null;
        hero.addEventListener('pointerdown', function (ev) {
            if (!cameraFloatActive()) return;
            if (ev.button != null && ev.button !== 0) return;
            if (ev.target && ev.target.closest && ev.target.closest('button, a, input, textarea')) return;
            var stage = hero.parentElement;
            if (!stage) return;
            var rect = hero.getBoundingClientRect();
            var stageRect = stage.getBoundingClientRect();
            drag = {
                dx: ev.clientX - rect.left,
                dy: ev.clientY - rect.top,
                stageX: stageRect.left,
                stageY: stageRect.top
            };
            hero.classList.add('ck-cam-drag');
            if (hero.setPointerCapture) {
                try { hero.setPointerCapture(ev.pointerId); } catch (e) {}
            }
            ev.preventDefault();
            ev.stopPropagation();
        });
        hero.addEventListener('pointermove', function (ev) {
            if (!drag) return;
            var x = ev.clientX - drag.stageX - drag.dx;
            var y = ev.clientY - drag.stageY - drag.dy;
            saveCamPos(placeCamera(hero, x, y));
            ev.preventDefault();
            ev.stopPropagation();
        });
        function endDrag(ev) {
            if (!drag) return;
            drag = null;
            hero.classList.remove('ck-cam-drag');
            if (ev && ev.pointerId != null && hero.releasePointerCapture) {
                try { hero.releasePointerCapture(ev.pointerId); } catch (e) {}
            }
        }
        hero.addEventListener('pointerup', endDrag);
        hero.addEventListener('pointercancel', endDrag);
        window.addEventListener('resize', function () {
            if (!cameraFloatActive() || !hero.classList.contains('ck-cam-placed')) return;
            var left = parseFloat(hero.style.left);
            var top = parseFloat(hero.style.top);
            if (isNaN(left) || isNaN(top)) return;
            saveCamPos(placeCamera(hero, left, top));
        });
    }

    function loadStudentDisplayMode() {
        if (cfg.isHost) return;
        var mode = 'board';
        try {
            var saved = sessionStorage.getItem('ck_sd_mode_' + (cfg.meetingId || '0'));
            if (saved === 'board' || saved === 'teacher' || saved === 'share') mode = saved;
        } catch (e) {}
        setStudentDisplayMode(mode, false);
    }

    function applyBoardFocusHint(on) {
        if (cfg.isHost) return;
        boardFocusHint = !!on;
        if (autoSwitchDebounceTimer) clearTimeout(autoSwitchDebounceTimer);
        if (on) {
            currentTeacherActivity = 'board';
            stageDismissed = false;
            if (!anyoneSharing()) enterBoardFullStage('auto');
        } else {
            currentTeacherActivity = 'teacher';
            studentManualMode = null;
            stageFocusHeld = false;
            if (stageFocus === 'board') leaveStageFocus(true);
            if (!anyoneSharing()) setStudentDisplayMode('teacher', false);
        }
    }

    function dismissStageFocus() {
        stageFocusHeld = false;
        stageDismissed = true;
        leaveStageFocus(true);
    }

    function showLobby(on) {
        var lobby = $('ckLobby');
        if (lobby) lobby.classList.toggle('is-off', !on);
        var dock = $('ckDock');
        if (dock) dock.hidden = on;
        if (on) {
            document.body.classList.remove('ck-is-sharing', 'ck-in-room', 'ck-filmstrip', 'ck-full-stage', 'ck-focus-board', 'ck-focus-share');
            stageFocus = '';
            stageFocusHeld = false;
            stageDismissed = false;
            boardFocusHint = false;
            hostBoardFocus = false;
            hostWasBoardFs = false;
            railSnap = null;
            if (isFullscreen()) exitFullscreen();
            parkBoardHome();
            var wrap = $('ckStageBoard');
            if (wrap) {
                wrap.hidden = true;
                wrap.classList.remove('is-on');
            }
            var hero = $('ckHero');
            if (hero) hero.classList.remove('is-on');
            hideRails();
            var presenting = $('ckPresenting');
            if (presenting) presenting.hidden = true;
            return;
        }
        document.body.classList.add('ck-in-room');
        if (cfg.isHost) {
            document.body.classList.add('ck-is-host');
        } else {
            document.body.classList.add('ck-is-student');
            loadStudentDisplayMode();
        }
        if (isMobile()) {
            var rail = $('ckPeopleRail');
            var side = $('ckChatRail');
            if (cfg.isHost) {
                if (rail) {
                    rail.hidden = false;
                    rail.classList.remove('is-open');
                }
                if (side) {
                    side.hidden = false;
                    side.classList.remove('is-open');
                }
                syncMask();
                syncMainGrid();
            } else {
                if (rail) {
                    rail.hidden = true;
                    rail.classList.remove('is-open');
                    rail.setAttribute('aria-hidden', 'true');
                }
                setChatOpen(chatPinned);
            }
        } else {
            setPeopleOpen(peoplePinned);
            setChatOpen(chatPinned);
        }
    }

    function shareError(err) {
        var name = (err && err.name) || '';
        var msg = String((err && err.message) || '');
        var low = (name + ' ' + msg).toLowerCase();
        if (name === 'NotAllowedError' || name === 'AbortError' || low.indexOf('denied') >= 0 || low.indexOf('dismiss') >= 0 || low.indexOf('cancel') >= 0) {
            return 'Screen share was cancelled or blocked. Click Share again, choose a window, and allow screen sharing for this site.';
        }
        if (name === 'NotFoundError') {
            return 'No screen or window was selected.';
        }
        if (name === 'NotSupportedError' || low.indexOf('getdisplaymedia') >= 0) {
            return 'Screen sharing is not supported here. Use Chrome or Edge on a computer (not a phone).';
        }
        if (low.indexOf('timed out') >= 0 || low.indexOf('no response from server') >= 0) {
            return 'Your screen was captured, but video never reached LiveKit. On the VPS run: bash /opt/livekit/apply-hostnet.sh — then leave, hard-refresh, and Join again. Do not run fix-webrtc.sh (it puts Docker UDP proxy back). Until media works, use the whiteboard.';
        }
        if (low.indexOf('permission') >= 0 || low.indexOf('publish') >= 0) {
            return 'LiveKit rejected the screen track. Leave the class and Join again so you get a new token, then Share.';
        }
        return 'Could not share the screen.' + (msg ? ' (' + msg + ')' : '');
    }

    function mediaError(err) {
        var name = (err && err.name) || '';
        if (name === 'NotAllowedError') {
            return 'We could not access your camera or microphone. Check browser permissions and try again.';
        }
        if (name === 'NotFoundError') {
            return 'No camera or microphone was found on this device.';
        }
        return 'Camera or microphone is unavailable. You can still join and listen.';
    }

    function isBrowserCameraDenied(err) {
        var name = (err && err.name) || '';
        var msg = String((err && err.message) || '').toLowerCase();
        if (msg.indexOf('publish') >= 0) return false;
        return name === 'NotAllowedError' || name === 'PermissionDeniedError'
            || msg.indexOf('permission denied') >= 0;
    }

    function isPublishSourceError(err) {
        if (!err || isBrowserCameraDenied(err) || err.name === 'NotFoundError' || err.name === 'NotReadableError') {
            return false;
        }
        var msg = String(err.message || '').toLowerCase();
        var name = String(err.name || '');
        return name === 'PublishTrackError'
            || msg.indexOf('insufficient') >= 0
            || msg.indexOf('canpublish') >= 0
            || msg.indexOf('publish source') >= 0
            || msg.indexOf('not allowed to publish') >= 0
            || msg.indexOf('cannot publish') >= 0;
    }

    function cameraForceOnToast(err) {
        if (isBrowserCameraDenied(err)) {
            return 'Allow camera in the browser once.';
        }
        if (err && err.name === 'NotFoundError') {
            return 'No camera was found on this device.';
        }
        return mediaError(err);
    }

    function toastCamForceOnce(msg) {
        var now = Date.now();
        if (now - lastCamForceToastAt < 1600) return;
        lastCamForceToastAt = now;
        toast(msg);
    }

    function forceEnableLocalCamera(attempt) {
        if (cfg.isHost || !room || !room.localParticipant || localCamBlocked) return;
        attempt = attempt || 0;
        room.localParticipant.setCameraEnabled(true).then(function () {
            pendingCamForce = '';
            setCamUi(true, false);
        }).catch(function (e) {
            if (attempt < 8 && isPublishSourceError(e)) {
                setTimeout(function () { forceEnableLocalCamera(attempt + 1); }, 300);
                return;
            }
            pendingCamForce = '';
            setCamUi(!!room.localParticipant.isCameraEnabled, localCamBlocked);
            toastCamForceOnce(cameraForceOnToast(e));
        });
    }

    function applyForcedCamera(blocked) {
        if (cfg.isHost || !room || !room.localParticipant) return;
        pendingCamForce = blocked ? 'off' : 'on';
        localCamBlocked = !!blocked;
        if (blocked) {
            room.localParticipant.setCameraEnabled(false).catch(function () {});
            setCamUi(false, true);
            pendingCamForce = '';
            toast('The teacher turned off your camera.');
            return;
        }
        forceEnableLocalCamera(0);
    }

    function patchPublishTimeout(r) {
        try {
            var P = r.engine && r.engine.constructor && r.engine.constructor.prototype;
            if (!P || typeof P.addTrack !== 'function' || P._eckPubMs) {
                return;
            }
            P._eckPubMs = true;
            P.addTrack = function (req) {
                if (this.pendingTrackResolvers[req.cid]) {
                    throw new Error('a track with the same ID has already been published');
                }
                var self = this;
                return new Promise(function (resolve, reject) {
                    var publicationTimeout = setTimeout(function () {
                        delete self.pendingTrackResolvers[req.cid];
                        reject(new Error('publication of local track timed out, no response from server'));
                    }, 30000);
                    self.pendingTrackResolvers[req.cid] = {
                        resolve: function (info) {
                            clearTimeout(publicationTimeout);
                            resolve(info);
                        },
                        reject: function () {
                            clearTimeout(publicationTimeout);
                            reject(new Error('Cancelled publication by calling unpublish'));
                        }
                    };
                    self.client.sendAddTrack(req);
                });
            };
        } catch (e) {}
    }

    function startPreview() {
        var video = $('ckPreview');
        if (!video || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return;
        }
        navigator.mediaDevices.getUserMedia({ audio: true, video: { width: 640, height: 360 } }).then(function (stream) {
            previewStream = stream;
            video.srcObject = stream;
        }).catch(function (err) {
            toast(mediaError(err));
        });
    }

    function stopPreview() {
        if (!previewStream) return;
        previewStream.getTracks().forEach(function (t) { t.stop(); });
        previewStream = null;
        var video = $('ckPreview');
        if (video) video.srcObject = null;
    }

    function setPreviewAllowed(allowed) {
        var wrap = $('ckPreviewWrap');
        if (wrap) wrap.hidden = !allowed;
        if (!allowed) {
            stopPreview();
        }
    }

    function applyAccessPreview(code, waitKind) {
        var blocked = code === 'PAY' || code === 'DENY' || code === 'SETUP' || waitKind === 'pay_pending' || waitKind === 'pay';
        var wasBlocked = cfg.accessCode === 'PAY' || cfg.waitKind === 'pay_pending' || cfg.waitKind === 'pay';
        setPreviewAllowed(!blocked);
        if (!blocked && wasBlocked && !previewStream) {
            startPreview();
        }
    }

    function renderLobbyActions(status, access) {
        var box = $('ckLobbyActions');
        if (!box) return;
        box.innerHTML = '';
        access = access || {};
        var code = access.code || cfg.accessCode;
        var waitKind = access.wait_kind || cfg.waitKind || '';
        if (typeof access.waiting_room === 'boolean') {
            cfg.waitingRoom = access.waiting_room;
        }
        if (!cfg.configured && !cfg.isHost && code !== 'ALLOW') return;

        function btn(label, cls, fn) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ck-btn ' + (cls || '');
            b.textContent = label;
            b.addEventListener('click', fn);
            box.appendChild(b);
        }

        function payNowForm() {
            var form = document.createElement('form');
            form.method = 'post';
            form.action = cfg.payUrl || '/student/pay_lesson.php';
            function hidden(name, value) {
                var i = document.createElement('input');
                i.type = 'hidden';
                i.name = name;
                i.value = value == null ? '' : String(value);
                form.appendChild(i);
            }
            hidden('csrf_token', cfg.csrf || '');
            hidden('timetable_id', cfg.lessonId || '');
            hidden('return', 'classroom');
            var b = document.createElement('button');
            b.type = 'submit';
            b.className = 'ck-btn ck-btn-primary';
            b.textContent = 'Pay Now';
            form.appendChild(b);
            return form;
        }

        if (code === 'DENY') {
            var home = document.createElement('a');
            home.className = 'ck-btn';
            home.href = cfg.homeUrl || '/';
            home.textContent = 'Go to home';
            box.appendChild(home);
            return;
        }

        if (code === 'PAY' || waitKind === 'pay') {
            box.appendChild(payNowForm());
            return;
        }

        if (waitKind === 'pay_pending') {
            return;
        }

        if (cfg.isHost) {
            if (status === 'live') {
                btn('Join class', 'ck-btn-primary', connect);
            } else if (status !== 'ended') {
                btn('Start class', 'ck-btn-primary', startClass);
            }
            return;
        }

        if (waitKind === 'lobby' || (code === 'WAIT' && cfg.waitingRoom && status === 'live')) {
            var note = document.createElement('div');
            note.className = 'ck-lobby-wait';
            note.textContent = 'Waiting for the teacher to let you in. After you join, you can open Board to see the teacher’s whiteboard.';
            box.appendChild(note);
            return;
        }

        if (status === 'live' && code !== 'WAIT') {
            btn('Join class', 'ck-btn-primary', connect);
        }
    }

    function startClass() {
        api('start.php', {}).then(function (data) {
            if (!data.ok) {
                toast(data.error || 'Could not start class.');
                return;
            }
            setStatus('live', 'Live now');
            $('ckLobbyMsg').textContent = data.recording
                ? 'Class is live and being recorded. Join when you are ready.'
                : 'Class is live. Join when you are ready.';
            cfg.recording = !!data.recording;
            setRecordingUi(!!data.recording);
            if (data.recording) toast('This class is being recorded.');
            applyStartedAt(data.startedAt || data.started_at || new Date().toISOString());
            renderLobbyActions('live');
            connect();
        }).catch(function () {
            toast('Could not start class. Please try again.');
        });
    }

    function returnTilesToGrid(keepStaff) {
        var grid = $('ckGrid');
        var hero = $('ckHero');
        if (!grid || !hero) return;
        hero.querySelectorAll('.ck-tile').forEach(function (tile) {
            if (keepStaff && tile.classList.contains('ck-tile-staff')) return;
            grid.appendChild(tile);
        });
    }

    function anyoneSharing() {
        return document.body.classList.contains('ck-is-sharing');
    }

    function viewStorageKey() {
        return 'ck-view-' + String(cfg.lessonId || '');
    }

    function updateViewButtons() {
        ['presentation', 'speaker', 'gallery'].forEach(function (m) {
            var id = 'ckView' + m.charAt(0).toUpperCase() + m.slice(1);
            var btn = $(id);
            if (!btn) return;
            var on = viewMode === m;
            btn.classList.toggle('is-on', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function teacherIdentity() {
        if (!room) return '';
        function staffId(p) {
            return p && participantIsStaff(p) ? p.identity : '';
        }
        var local = staffId(room.localParticipant);
        if (local) return local;
        var found = '';
        room.remoteParticipants.forEach(function (p) {
            if (!found && participantIsStaff(p)) found = p.identity;
        });
        return found;
    }

    function tileIdentity(tile) {
        return String((tile && tile.id) || '').replace(/^tile-/, '');
    }

    function classifyTile(tile, id) {
        if (!tile) return;
        id = id || tileIdentity(tile);
        var p = participantByIdentity(id);
        var staff = participantIsStaff(p) || !!(cfg.hostIdentity && id === cfg.hostIdentity);
        var localId = (room && room.localParticipant && room.localParticipant.identity) || cfg.identity || '';
        var self = !!(id && ((room && room.localParticipant && room.localParticipant.identity === id) || (localId && id === localId)));
        tile.classList.toggle('ck-tile-staff', !!staff);
        tile.classList.toggle('ck-tile-self', !!self);
        tile.classList.toggle('ck-tile-student', !staff);
    }

    function classifyAllTiles() {
        document.querySelectorAll('.ck-tile').forEach(function (tile) {
            classifyTile(tile);
        });
    }

    function arrangeHostStrip() {
        var grid = $('ckGrid');
        if (!grid || !cfg.isHost) return;
        var tiles = Array.prototype.slice.call(grid.querySelectorAll('.ck-tile'));
        tiles.sort(function (a, b) {
            var rank = function (t) {
                if (t.classList.contains('ck-tile-staff')) return 0;
                if (t.classList.contains('ck-tile-self')) return 1;
                return 2;
            };
            return rank(a) - rank(b);
        });
        tiles.forEach(function (t) { grid.appendChild(t); });
    }

    function layoutStage() {
        var hero = $('ckHero');
        var grid = $('ckGrid');
        if (!hero || !grid) return;
        var sharing = anyoneSharing();
        document.body.classList.toggle('ck-filmstrip', sharing || stageFocus === 'board' || viewMode !== 'gallery');
        if (cfg.isHost) document.body.classList.add('ck-is-host');
        classifyAllTiles();
        var keepTeacher = !cfg.isHost && !sharing && viewMode !== 'gallery';
        returnTilesToGrid(keepTeacher);
        if (sharing) {
            arrangeHostStrip();
            return;
        }
        if (stageFocus === 'board') {
            if (!cfg.isHost && !hero.querySelector('.ck-screen')) {
                ensureTeacherInHero();
            } else if (!hero.querySelector('.ck-screen')) {
                hero.classList.remove('is-on');
            }
            arrangeHostStrip();
            applyCameraChrome();
            return;
        }
        if (!cfg.isHost && viewMode === 'gallery') {
            if (!hero.querySelector('.ck-screen')) {
                hero.classList.remove('is-on');
            }
            return;
        }
        var pinId = '';
        if (cfg.isHost) {
            pinId = teacherIdentity();
            if (!pinId || !document.getElementById('tile-' + pinId)) {
                var hostTile = grid.querySelector('.ck-tile-staff');
                pinId = hostTile ? tileIdentity(hostTile) : '';
            }
            var hostCam = pinId ? document.getElementById('tile-' + pinId) : null;
            if (hostCam) {
                hero.classList.add('is-on');
                hero.appendChild(hostCam);
            } else if (!hero.querySelector('.ck-screen')) {
                hero.classList.remove('is-on');
            }
            arrangeHostStrip();
            return;
        }
        if (viewMode === 'speaker') {
            pinId = activeSpeakerId || teacherIdentity();
            if (pinId && !document.getElementById('tile-' + pinId)) pinId = teacherIdentity();
        } else {
            pinId = teacherIdentity();
        }
        if (pinId && !participantIsStaff(participantByIdentity(pinId)) && !(cfg.hostIdentity && pinId === cfg.hostIdentity)) {
            pinId = teacherIdentity();
        }
        if (!pinId || !document.getElementById('tile-' + pinId)) {
            var preferred = grid.querySelector('.ck-tile-staff');
            pinId = preferred ? tileIdentity(preferred) : '';
        }
        var tile = pinId ? document.getElementById('tile-' + pinId) : null;
        if (tile) {
            hero.classList.add('is-on');
            hero.appendChild(tile);
        } else if (!hero.querySelector('.ck-screen')) {
            hero.classList.remove('is-on');
        }
        arrangeHostStrip();
        applyCameraChrome();
    }

    function setViewMode(mode, persist) {
        if (mode !== 'speaker' && mode !== 'gallery') mode = 'presentation';
        if (!cfg.isHost && stageFocus === 'board' && !restoringRails && !anyoneSharing()) {
            stageDismissed = true;
            stageFocusHeld = false;
            leaveStageFocus(true);
        }
        viewMode = mode;
        document.body.setAttribute('data-ck-view', mode);
        if (persist !== false) {
            try { sessionStorage.setItem(viewStorageKey(), mode); } catch (e) {}
        }
        updateViewButtons();
        layoutStage();
    }

    function loadViewMode() {
        try {
            var v = sessionStorage.getItem(viewStorageKey());
            if (v === 'speaker' || v === 'gallery' || v === 'presentation') viewMode = v;
        } catch (e) {}
        document.body.setAttribute('data-ck-view', viewMode);
        updateViewButtons();
    }

    function minimizeTeacherScreen() {
        if (cfg.isHost) return;
        isScreenMinimized = true;
        document.body.classList.add('ck-screen-minimized');
        var hero = $('ckHero');
        if (hero) hero.classList.add('is-minimized');

        // Leave stage focus so student can see whiteboard or teacher feed without disconnecting WebRTC
        leaveStageFocus(true);

        // Show minimized floating dock at bottom
        var dock = $('ckScreenMinDock');
        if (dock) dock.hidden = false;

        // Switch student view to whiteboard if teacher is using it, or teacher video
        setStudentDisplayMode(boardFocusHint ? 'board' : 'teacher', false);
    }

    function restoreTeacherScreen() {
        if (cfg.isHost) return;
        isScreenMinimized = false;
        document.body.classList.remove('ck-screen-minimized');
        var hero = $('ckHero');
        if (hero) {
            hero.classList.remove('is-minimized');
            hero.classList.add('is-on');
        }
        var dock = $('ckScreenMinDock');
        if (dock) dock.hidden = true;

        enterShareFullStage();
        layoutStage();
    }

    function clearScreenUi() {
        isScreenMinimized = false;
        document.body.classList.remove('ck-screen-minimized');
        var dock = $('ckScreenMinDock');
        if (dock) dock.hidden = true;
        document.body.classList.remove('ck-is-sharing');
        var stage = $('ckStage');
        if (stage) stage.classList.remove('is-sharing');
        returnTilesToGrid();
        var hero = $('ckHero');
        if (hero) {
            hero.classList.remove('is-minimized');
            hero.querySelectorAll('.ck-screen, .ck-sharing-banner, .ck-hero-close-btn').forEach(function (n) { n.remove(); });
        }
        var presenting = $('ckPresenting');
        if (presenting) presenting.hidden = true;
        var share = $('ckShare');
        if (share) share.classList.remove('is-on');
        if (!cfg.isHost) {
            onTeacherActivityChange(stageFocus === 'board' || hostBoardFocus ? 'board' : 'teacher');
        }
        if (stageFocus === 'share') leaveStageFocus(true);
        else layoutStage();
        if (cfg.isHost && boardPanelOpen() && !anyoneSharing()) setHostBoardFocus(true);
        else if (!cfg.isHost && boardFocusHint && !anyoneSharing()) enterBoardFullStage('auto');
        else if (!cfg.isHost) refreshStudentFeed();
    }

    function setSharingLayout(on, isLocal) {
        document.body.classList.toggle('ck-is-sharing', !!on);
        var stage = $('ckStage');
        if (stage) stage.classList.toggle('is-sharing', !!on);
        var presenting = $('ckPresenting');
        if (presenting) presenting.hidden = !(on && isLocal);
        if (on && cfg.isHost) setHostBoardFocus(false);
        if (on && isLocal) {
            returnTilesToGrid();
            var hero = $('ckHero');
            if (hero) {
                hero.classList.remove('is-on');
                hero.querySelectorAll('.ck-screen, .ck-sharing-banner').forEach(function (n) { n.remove(); });
            }
        }
        if (!cfg.isHost) {
            if (on) {
                onTeacherActivityChange('share');
            } else {
                onTeacherActivityChange(stageFocus === 'board' || hostBoardFocus ? 'board' : 'teacher');
            }
        }
        if (on && viewMode === 'gallery') {
            setViewMode('presentation', true);
            if (!cfg.isHost) refreshStudentFeed();
            return;
        }
        layoutStage();
        if (!cfg.isHost) refreshStudentFeed();
    }

    function setMicUi(on, blocked) {
        var btns = [$('ckMic'), $('ckMobileMic'), $('ckMobBtnAudio')];
        blocked = !!blocked;
        btns.forEach(function (btn) {
            if (!btn) return;
            btn.disabled = blocked;
            btn.classList.toggle('is-blocked', blocked);
            btn.classList.toggle('is-off', !on || blocked);
            btn.setAttribute('aria-pressed', on && !blocked ? 'true' : 'false');
            var icon = btn.querySelector('i');
            if (icon) icon.className = (!on || blocked) ? 'bi bi-mic-mute-fill' : 'bi bi-mic-fill';
            var label = btn.querySelector('span');
            if (label && btn.id !== 'ckMobBtnAudio') label.textContent = blocked ? 'Blocked' : 'Mic';
        });
    }

    function setCamUi(on, blocked) {
        var btns = [$('ckCam'), $('ckMobileCam')];
        blocked = !!blocked;
        btns.forEach(function (btn) {
            if (!btn) return;
            btn.disabled = blocked;
            btn.classList.toggle('is-blocked', blocked);
            btn.classList.toggle('is-off', !on || blocked);
            btn.classList.toggle('is-on', on && !blocked);
            btn.setAttribute('aria-pressed', on && !blocked ? 'true' : 'false');
            var icon = btn.querySelector('i');
            if (icon) icon.className = (!on || blocked) ? 'bi bi-camera-video-off' : 'bi bi-camera-video';
            var label = btn.querySelector('span');
            if (label) label.textContent = blocked ? 'Blocked' : 'Camera';
        });
    }

    function setShareUi(allowed, sharing) {
        var btns = [$('ckShare'), $('ckMobileShare')];
        btns.forEach(function (btn) {
            if (!btn) return;
            if (btn.id === 'ckMobileShare' && !cfg.isHost) {
                btn.hidden = false;
                if (typeof sharing === 'boolean') btn.classList.toggle('is-on', sharing);
                return;
            }
            if (cfg.isHost) {
                btn.hidden = false;
            } else {
                btn.hidden = !allowed;
            }
            if (!allowed && !cfg.isHost) {
                btn.classList.remove('is-on');
                return;
            }
            if (typeof sharing === 'boolean') {
                btn.classList.toggle('is-on', sharing);
            }
        });
    }

    var sharePause = null;

    function localScreenSharePub() {
        if (!room || !room.localParticipant) return null;
        var ss = (LK.Track && LK.Track.Source && LK.Track.Source.ScreenShare) || 'screen_share';
        try {
            return room.localParticipant.getTrackPublication(ss) || null;
        } catch (e) {
            return null;
        }
    }

    function syncSharePauseUi() {
        var btn = $('ckSharePause');
        if (!btn) return;
        var pub = localScreenSharePub();
        var sharing = !!(pub && pub.track);
        var paused = !!sharePause;
        btn.hidden = !sharing;
        btn.classList.toggle('is-on', paused);
        btn.setAttribute('aria-pressed', paused ? 'true' : 'false');
        var label = paused ? 'Resume screen for students (Alt+P)' : 'Pause screen for students (Alt+P)';
        btn.title = label;
        btn.setAttribute('aria-label', label);
        var icon = btn.querySelector('i');
        if (icon) icon.className = paused ? 'bi bi-play-circle-fill' : 'bi bi-pause-circle';
        var span = btn.querySelector('span');
        if (span) span.textContent = paused ? 'Resume' : 'Pause';
        document.body.classList.toggle('ck-share-paused', paused);
    }

    function grabShareFrame(mst) {
        return new Promise(function (resolve, reject) {
            var video = document.createElement('video');
            video.muted = true;
            video.playsInline = true;
            video.srcObject = new MediaStream([mst]);
            var done = false;
            function finish() {
                if (done) return;
                var w = video.videoWidth;
                var h = video.videoHeight;
                if (!w || !h) return;
                done = true;
                var snap = document.createElement('canvas');
                snap.width = w;
                snap.height = h;
                snap.getContext('2d').drawImage(video, 0, 0, w, h);
                try { video.pause(); } catch (e) {}
                video.srcObject = null;
                resolve(snap);
            }
            video.addEventListener('loadeddata', finish);
            video.addEventListener('playing', finish);
            Promise.resolve(video.play()).catch(function () {});
            setTimeout(function () {
                finish();
                if (!done) {
                    done = true;
                    video.srcObject = null;
                    reject(new Error('No frame'));
                }
            }, 3000);
        });
    }

    function releaseSharePause() {
        var p = sharePause;
        sharePause = null;
        if (!p) return;
        if (p.timer) clearInterval(p.timer);
        try { p.track.stop(); } catch (e) {}
    }

    function pauseLocalShare() {
        var pub = localScreenSharePub();
        var track = pub && pub.track;
        var sender = track && track.sender;
        if (!track || !track.mediaStreamTrack) {
            toast('Start sharing your screen first, then press Alt+P to pause it.');
            return Promise.resolve(false);
        }
        if (!sender || typeof sender.replaceTrack !== 'function') {
            toast('Pausing the screen is not supported in this browser.');
            return Promise.resolve(false);
        }
        return grabShareFrame(track.mediaStreamTrack).then(function (snap) {
            var out = document.createElement('canvas');
            out.width = snap.width;
            out.height = snap.height;
            var ctx = out.getContext('2d');
            ctx.drawImage(snap, 0, 0);
            var stream = out.captureStream ? out.captureStream(5) : null;
            var frozen = stream && stream.getVideoTracks()[0];
            if (!frozen) throw new Error('captureStream unsupported');
            try { frozen.contentHint = 'detail'; } catch (e) {}
            // Repaint so late joiners and keyframe requests still get the frozen image;
            // re-apply if LiveKit ever swaps the live capture back onto the sender.
            var timer = setInterval(function () {
                ctx.drawImage(snap, 0, 0);
                reapplySharePause();
            }, 500);
            return sender.replaceTrack(frozen).then(function () {
                releaseSharePause();
                sharePause = { track: frozen, timer: timer, sender: sender };
                syncSharePauseUi();
                publishData({ t: 'share-pause', on: true });
                toast('Screen paused. Students see a frozen frame (Alt+P to resume).');
                return true;
            }, function (err) {
                clearInterval(timer);
                try { frozen.stop(); } catch (e) {}
                throw err;
            });
        }).catch(function (err) {
            try { console.error('Screen pause failed', err); } catch (e) {}
            toast('Could not pause the shared screen.');
            syncSharePauseUi();
            return false;
        });
    }

    function resumeLocalShare(silent) {
        var pub = localScreenSharePub();
        var track = pub && pub.track;
        var sender = track && track.sender;
        if (!sharePause) {
            syncSharePauseUi();
            return Promise.resolve(true);
        }
        if (!track || !sender || !track.mediaStreamTrack) {
            releaseSharePause();
            syncSharePauseUi();
            return Promise.resolve(true);
        }
        return sender.replaceTrack(track.mediaStreamTrack).then(function () {
            releaseSharePause();
            syncSharePauseUi();
            publishData({ t: 'share-pause', on: false });
            if (!silent) toast('Screen resumed. Students see your live screen again.');
            return true;
        }).catch(function (err) {
            try { console.error('Screen resume failed', err); } catch (e) {}
            toast('Could not resume the shared screen.');
            return false;
        });
    }

    function toggleLocalSharePause() {
        if (sharePause) return resumeLocalShare(false);
        return pauseLocalShare();
    }

    function reapplySharePause() {
        if (!sharePause) return;
        var pub = localScreenSharePub();
        var sender = pub && pub.track && pub.track.sender;
        if (!sender) {
            releaseSharePause();
            syncSharePauseUi();
            return;
        }
        if (sender === sharePause.sender && sender.track === sharePause.track) return;
        sender.replaceTrack(sharePause.track).then(function () {
            if (sharePause) sharePause.sender = sender;
        }).catch(function () {});
    }

    function stopLocalShare() {
        var presenting = $('ckPresenting');
        var localSharing = !!(presenting && !presenting.hidden);
        if (room && room.localParticipant && room.localParticipant.isScreenShareEnabled) {
            localSharing = true;
            room.localParticipant.setScreenShareEnabled(false).catch(function () {});
        }
        var btn = $('ckShare');
        if (btn) btn.classList.remove('is-on');
        if (localSharing) {
            clearScreenUi();
        }
    }

    function participantRole(p) {
        try {
            var m = JSON.parse((p && p.metadata) || '{}');
            return String(m.role || '');
        } catch (e) {
            return '';
        }
    }

    function participantIsStaff(p) {
        if (!p) return false;
        if (cfg.isHost && p.isLocal) return true;
        if (cfg.hostIdentity && p.identity === cfg.hostIdentity) return true;
        var role = participantRole(p);
        return role === 'teacher' || role === 'admin';
    }

    function participantByIdentity(id) {
        if (!room || !id) return null;
        if (room.localParticipant && room.localParticipant.identity === id) return room.localParticipant;
        if (typeof room.getParticipantByIdentity === 'function') {
            try {
                var found = room.getParticipantByIdentity(id);
                if (found) return found;
            } catch (e) {}
        }
        if (room.remoteParticipants && typeof room.remoteParticipants.get === 'function') {
            return room.remoteParticipants.get(id) || null;
        }
        var match = null;
        if (room.remoteParticipants && room.remoteParticipants.forEach) {
            room.remoteParticipants.forEach(function (p) {
                if (!match && p && p.identity === id) match = p;
            });
        }
        return match;
    }

    function localIdentity() {
        return (room && room.localParticipant && room.localParticipant.identity) || cfg.identity || '';
    }

    function isLocalParticipant(p) {
        if (!p) return false;
        if (p.isLocal) return true;
        if (room && room.localParticipant && (p === room.localParticipant || p.identity === room.localParticipant.identity)) {
            return true;
        }
        var id = localIdentity();
        return !!(id && p.identity === id);
    }

    function isScreenShareSource(publication) {
        if (!publication) return false;
        var ss = (LK.Track && LK.Track.Source && LK.Track.Source.ScreenShare) || 'screen_share';
        var src = publication.source;
        var low = String(src == null ? '' : src).toLowerCase().replace(/-/g, '_');
        return src === ss || src === 3 || low === 'screen_share' || low === 'screenshare';
    }

    function isCameraSource(publication) {
        if (!publication || isScreenShareSource(publication)) return false;
        var cam = (LK.Track && LK.Track.Source && LK.Track.Source.Camera) || 'camera';
        var src = publication.source;
        return src === cam || src === 'camera' || src === 1 || String(src).toLowerCase() === 'camera';
    }

    function isPeerStudentCamera(participant, publication) {
        if (cfg.isHost) return false;
        if (!publication || publication.isLocal) return false;
        if (!isCameraSource(publication)) return false;
        if (!participant || isLocalParticipant(participant)) return false;
        if (participantIsStaff(participant)) return false;
        return true;
    }

    function studentHidesCameraTile(participant, publication) {
        if (cfg.isHost) return false;
        if (!isCameraSource(publication)) return false;
        if (participantIsStaff(participant)) return false;
        return true;
    }

    function skipPeerStudentCamera(participant, publication) {
        if (!isPeerStudentCamera(participant, publication)) return;
        try {
            if (publication && typeof publication.setSubscribed === 'function' && publication.isSubscribed !== false) {
                publication.setSubscribed(false);
            }
        } catch (e) {}
        if (participant && participant.identity) {
            var tile = document.getElementById('tile-' + participant.identity);
            if (
                tile
                && tile.classList.contains('ck-tile-student')
                && !tile.classList.contains('ck-tile-self')
                && !tile.classList.contains('ck-tile-staff')
            ) {
                tile.remove();
            }
        }
    }

    function unsubAllPeerStudentCameras() {
        if (cfg.isHost || !room || !room.remoteParticipants) return;
        room.remoteParticipants.forEach(function (p) {
            if (isLocalParticipant(p) || participantIsStaff(p)) return;
            var pubs = p && p.trackPublications;
            if (!pubs || !pubs.forEach) return;
            pubs.forEach(function (pub) {
                if (isPeerStudentCamera(p, pub)) skipPeerStudentCamera(p, pub);
            });
        });
    }

    function sourcesIncludeMicrophone(src) {
        if (!src || !src.length) return true;
        var mic = (LK.Track && LK.Track.Source && LK.Track.Source.Microphone) || 'microphone';
        for (var i = 0; i < src.length; i++) {
            var s = src[i];
            var low = String(s).toLowerCase().replace(/-/g, '_');
            if (s === mic || s === 2 || low === 'microphone' || low === 'mic' || low === 'source_microphone') return true;
        }
        return false;
    }

    function sourcesIncludeCamera(src) {
        if (!src || !src.length) return true;
        var cam = (LK.Track && LK.Track.Source && LK.Track.Source.Camera) || 'camera';
        for (var i = 0; i < src.length; i++) {
            var s = src[i];
            if (s === cam || s === 1 || String(s).toLowerCase() === 'camera') return true;
        }
        return false;
    }

    function sourcesIncludeScreenShare(src) {
        if (!src || !src.length) return true;
        var ss = (LK.Track && LK.Track.Source && LK.Track.Source.ScreenShare) || 'screen_share';
        for (var i = 0; i < src.length; i++) {
            var s = src[i];
            var low = String(s).toLowerCase().replace(/-/g, '_');
            if (s === ss || s === 3 || low === 'screen_share' || low === 'screenshare') return true;
        }
        return false;
    }

    function micAllowedFor(p) {
        if (!p) return true;
        if (p.isLocal) {
            if (cfg.isHost) return true;
            if (localMicBlocked) return false;
            var localPerms = p.permissions;
            if (localPerms && localPerms.canPublish === false) return false;
            if (localPerms) return sourcesIncludeMicrophone(localPerms.canPublishSources);
            return true;
        }
        if (typeof micBlockState[p.identity] === 'boolean') return !micBlockState[p.identity];
        var perms = p.permissions;
        if (!perms) return true;
        if (perms.canPublish === false) return false;
        return sourcesIncludeMicrophone(perms.canPublishSources);
    }

    function cameraAllowedFor(p) {
        if (!p) return true;
        if (p.isLocal) {
            if (cfg.isHost) return true;
            if (localCamBlocked) return false;
            var localPerms = p.permissions;
            if (localPerms && localPerms.canPublish === false) return false;
            if (localPerms) return sourcesIncludeCamera(localPerms.canPublishSources);
            return true;
        }
        if (typeof camBlockState[p.identity] === 'boolean') return !camBlockState[p.identity];
        var perms = p.permissions;
        if (!perms) return true;
        if (perms.canPublish === false) return false;
        return sourcesIncludeCamera(perms.canPublishSources);
    }

    function participantCameraOn(p) {
        if (!p) return false;
        var pubs = p.videoTrackPublications || p.trackPublications;
        if (pubs && typeof pubs.forEach === 'function') {
            var on = false;
            var sawCamera = false;
            pubs.forEach(function (pub) {
                if (!isCameraSource(pub)) return;
                sawCamera = true;
                if (pub.isMuted || !pub.track) return;
                var media = pub.track.mediaStreamTrack;
                if (media && media.readyState === 'ended') return;
                on = true;
            });
            if (sawCamera) return on;
        }
        if (typeof p.isCameraEnabled === 'boolean') return !!p.isCameraEnabled;
        return false;
    }

    function shareAllowedFor(p) {
        if (!p) return !!cfg.isHost;
        if (p.isLocal) {
            if (cfg.isHost) return true;
            var localPerms = p.permissions;
            if (localPerms && localPerms.canPublish === false) return false;
            if (localPerms && localPerms.canPublishSources && localPerms.canPublishSources.length) {
                return sourcesIncludeScreenShare(localPerms.canPublishSources);
            }
            return !!localShareAllowed;
        }
        if (typeof shareAllowState[p.identity] === 'boolean') return shareAllowState[p.identity];
        var perms = p.permissions;
        if (!perms) return false;
        if (perms.canPublish === false) return false;
        if (perms.canPublishSources && perms.canPublishSources.length) {
            return sourcesIncludeScreenShare(perms.canPublishSources);
        }
        return false;
    }

    function applyLocalShareGate(denyMsg, allowMsg) {
        if (cfg.isHost) {
            localShareAllowed = true;
            setShareUi(true, !!(room && room.localParticipant && room.localParticipant.isScreenShareEnabled));
            return;
        }
        var perms = room && room.localParticipant ? room.localParticipant.permissions : null;
        var allowed = !!localShareAllowed;
        if (perms && perms.canPublish === false) {
            allowed = false;
        } else if (perms && perms.canPublishSources && perms.canPublishSources.length) {
            allowed = sourcesIncludeScreenShare(perms.canPublishSources);
        }
        var was = localShareAllowed;
        localShareAllowed = !!allowed;
        if (!allowed) {
            stopLocalShare();
            setShareUi(false, false);
            if (was && denyMsg) toast(denyMsg);
            return;
        }
        setShareUi(true, !!(room && room.localParticipant && room.localParticipant.isScreenShareEnabled));
        if (!was && allowMsg) toast(allowMsg);
    }

    function applyLocalMicGate(blockMsg, allowMsg) {
        if (cfg.isHost) {
            localMicBlocked = false;
            return;
        }
        var perms = room && room.localParticipant ? room.localParticipant.permissions : null;
        var allowed = true;
        if (perms && perms.canPublish === false) {
            allowed = false;
        } else if (perms) {
            allowed = sourcesIncludeMicrophone(perms.canPublishSources);
        }
        var wasBlocked = localMicBlocked;
        localMicBlocked = !allowed;
        if (!allowed) {
            if (room) {
                room.localParticipant.setMicrophoneEnabled(false).catch(function () {});
            }
            setMicUi(false, true);
            if (!wasBlocked && blockMsg) toast(blockMsg);
            return;
        }
        setMicUi(!!(room && room.localParticipant.isMicrophoneEnabled), false);
        if (wasBlocked && allowMsg) toast(allowMsg);
    }

    function applyLocalCameraGate(blockMsg, allowMsg) {
        if (cfg.isHost) {
            localCamBlocked = false;
            return;
        }
        var perms = room && room.localParticipant ? room.localParticipant.permissions : null;
        var allowed = true;
        if (perms && perms.canPublish === false) {
            allowed = false;
        } else if (perms) {
            allowed = sourcesIncludeCamera(perms.canPublishSources);
        }
        var wasBlocked = localCamBlocked;
        localCamBlocked = !allowed;
        if (!allowed) {
            if (room) {
                room.localParticipant.setCameraEnabled(false).catch(function () {});
            }
            setCamUi(false, true);
            if (pendingCamForce === 'off') pendingCamForce = '';
            if (!wasBlocked && blockMsg) toast(blockMsg);
            return;
        }
        setCamUi(!!(room && room.localParticipant.isCameraEnabled), false);
        if (pendingCamForce === 'on' || wasBlocked) {
            forceEnableLocalCamera(0);
            return;
        }
        if (wasBlocked && allowMsg) toast(allowMsg);
    }

    function isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function exitFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen().catch(function () {});
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        }
    }

    function fullscreenMedia(el) {
        if (!el) return null;
        var tag = (el.tagName || '').toUpperCase();
        if (tag === 'VIDEO' || tag === 'CANVAS') return el;
        if (!el.querySelector) return null;
        return el.querySelector('video') || el.querySelector('canvas');
    }

    function enterIosFullscreen(el) {
        var media = fullscreenMedia(el);
        if (media && media.webkitEnterFullscreen) {
            try { media.webkitEnterFullscreen(); } catch (e) {}
        }
    }

    function toggleFullscreen(el) {
        if (!el) el = document.documentElement;
        if (isFullscreen()) {
            exitFullscreen();
            return;
        }
        var req = el.requestFullscreen || el.webkitRequestFullscreen;
        if (req) {
            try {
                var promise = req.call(el);
                if (promise && promise.catch) {
                    promise.catch(function () {
                        if (el !== document.documentElement) {
                            toggleFullscreen(document.documentElement);
                        } else {
                            enterIosFullscreen(el);
                        }
                    });
                }
                return;
            } catch (err) {
                if (el !== document.documentElement) {
                    toggleFullscreen(document.documentElement);
                    return;
                }
            }
        }
        if (el !== document.documentElement) {
            toggleFullscreen(document.documentElement);
        } else {
            enterIosFullscreen(el);
        }
    }

    function handleBoardFullscreen(ev) {
        if (ev) {
            try { ev.preventDefault(); } catch (e) {}
            try { ev.stopPropagation(); } catch (e) {}
        }
        if (!cfg.isHost && stageFocus === 'board') stageFocusHeld = true;
        var target = cfg.isHost
            ? ($('ckBoardStage') || $('ckBoardPanel') || $('ckStageBoard') || document.documentElement)
            : ($('ckStage') || document.documentElement);
        toggleFullscreen(target);
    }
    window.toggleBoardFullscreen = handleBoardFullscreen;

    function syncFsButtons() {
        var on = isFullscreen();
        document.querySelectorAll('.ck-fs-btn i').forEach(function (icon) {
            icon.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        });
        document.querySelectorAll('.ck-board-fs').forEach(function (btn) {
            var label = on ? 'Exit full screen' : 'Full screen';
            btn.title = label;
            btn.setAttribute('aria-label', label);
            var span = btn.querySelector('span');
            if (span) span.textContent = label;
        });
        var mobFsLabel = $('ckMobileFsLabel');
        if (mobFsLabel) mobFsLabel.textContent = on ? 'Exit Fullscreen' : 'Fullscreen';
        var mobFsBtn = $('ckMobileFsBtn');
        if (mobFsBtn) {
            var mobFsIcon = mobFsBtn.querySelector('i');
            if (mobFsIcon) mobFsIcon.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        }
        document.querySelectorAll('#ckBoardFs, .ck-pill-fs-btn').forEach(function (boardFsBtn) {
            var bIcon = boardFsBtn.querySelector('i');
            if (bIcon) bIcon.className = on ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
            var label = (on ? 'Exit full screen' : 'Full screen') + (cfg.isHost ? ' (F)' : '');
            boardFsBtn.title = label;
            boardFsBtn.setAttribute('aria-label', label);
        });
        syncStudentFsChrome();
    }

    function syncStudentFsChrome() {
        if (cfg.isHost) return;
        var exitBtn = $('ckStageExit');
        if (exitBtn) exitBtn.hidden = true;
    }

    function addFsButton(container, dblEl) {
        if (!cfg.isHost || !container) return;
        var btn = container.querySelector('.ck-fs-btn');
        if (!btn) {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ck-fs-btn';
            btn.title = 'Full screen';
            btn.setAttribute('aria-label', 'Full screen');
            btn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
            container.appendChild(btn);
        }
        if (!btn._ckFsClick) {
            btn._ckFsClick = true;
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                toggleFullscreen(container);
            });
        }
        var target = dblEl || container;
        if (!target._ckFsDbl) {
            target._ckFsDbl = true;
            target.addEventListener('dblclick', function (ev) {
                if (ev.target && ev.target.closest && ev.target.closest('.ck-fs-btn')) return;
                ev.preventDefault();
                toggleFullscreen(container);
            });
        }
    }

    function attachScreenToHero(el, name) {
        var hero = $('ckHero');
        if (!hero || !el) return;
        returnTilesToGrid();
        hero.classList.add('is-on');
        hero.classList.remove('is-minimized');
        document.body.classList.remove('ck-screen-minimized');
        isScreenMinimized = false;
        var minDock = $('ckScreenMinDock');
        if (minDock) minDock.hidden = true;

        hero.querySelectorAll('.ck-screen, .ck-sharing-banner, .ck-hero-close-btn').forEach(function (n) { n.remove(); });
        el.classList.add('ck-screen');
        hero.appendChild(el);

        var banner = document.createElement('div');
        banner.className = 'ck-sharing-banner';

        var info = document.createElement('div');
        info.className = 'ck-sb-info';
        info.innerHTML = '<span class="ck-sb-dot"></span><i class="bi bi-display"></i><span class="ck-sb-title">' + (name || 'Teacher') + ' is sharing</span>';
        banner.appendChild(info);

        if (!cfg.isHost) {
            var actions = document.createElement('div');
            actions.className = 'ck-sb-actions';

            var minBtn = document.createElement('button');
            minBtn.type = 'button';
            minBtn.className = 'ck-sb-btn ck-sb-min-btn';
            minBtn.title = 'Minimize teacher screen';
            minBtn.setAttribute('aria-label', 'Minimize screen');
            minBtn.innerHTML = '<i class="bi bi-dash-lg"></i>';
            minBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                minimizeTeacherScreen();
            });
            actions.appendChild(minBtn);

            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'ck-sb-btn ck-sb-close-btn';
            closeBtn.title = 'Minimize / Close view';
            closeBtn.setAttribute('aria-label', 'Close view');
            closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                minimizeTeacherScreen();
            });
            actions.appendChild(closeBtn);

            banner.appendChild(actions);

            // Floating close / minimize button at top-right corner of teacher screen container
            var heroCloseBtn = document.createElement('button');
            heroCloseBtn.type = 'button';
            heroCloseBtn.className = 'ck-hero-close-btn';
            heroCloseBtn.title = 'Minimize teacher screen';
            heroCloseBtn.setAttribute('aria-label', 'Minimize screen');
            heroCloseBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
            heroCloseBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                minimizeTeacherScreen();
            });
            hero.appendChild(heroCloseBtn);
        }

        hero.appendChild(banner);
        addFsButton(hero);
        if (!cfg.isHost) enterShareFullStage();
    }

    function attachTile(id, name, element, speaking) {
        var grid = $('ckGrid');
        var tile = document.getElementById('tile-' + id);
        if (!tile) {
            tile = document.createElement('figure');
            tile.className = 'ck-tile';
            tile.id = 'tile-' + id;
            var cap = document.createElement('figcaption');
            cap.textContent = name;
            tile.appendChild(cap);
            if (grid) grid.appendChild(tile);
        }
        classifyTile(tile, id);
        tile.classList.toggle('is-speaking', !!speaking);
        var cap = tile.querySelector('figcaption');
        if (cap) cap.textContent = name;
        if (element) {
            var tag = (element.tagName || '').toLowerCase();
            var old = tag ? tile.querySelector(tag) : null;
            if (old && old !== element) old.remove();
            if (!element.parentNode || element.parentNode !== tile) {
                tile.insertBefore(element, tile.firstChild);
            }
        }
        addFsButton(tile);
        layoutStage();
    }

    function removeTile(id) {
        var tile = document.getElementById('tile-' + id);
        if (tile) tile.remove();
        layoutStage();
    }

    function trackId(participant, publication) {
        return participant.identity + '-' + (publication.source || publication.kind || 'media');
    }

    function playAudioEl(el) {
        if (!el) return;
        el.autoplay = true;
        el.muted = false;
        el.volume = 1;
        var p = el.play();
        if (p && typeof p.catch === 'function') {
            p.catch(function () {
                var b = $('ckEnableSound');
                if (b) b.hidden = false;
            });
        }
    }

    function applyAudioSink(el) {
        if (!audioSinkId) return;
        var nodes = el ? [el] : [];
        if (!el) {
            var box = $('ckAudio');
            if (box) nodes = box.querySelectorAll('audio, video');
        }
        Array.prototype.forEach.call(nodes, function (node) {
            if (node && typeof node.setSinkId === 'function') {
                node.setSinkId(audioSinkId).catch(function () {});
            }
        });
    }

    function warmTeacherCamera(publication, el) {
        try {
            if (publication && typeof publication.setVideoQuality === 'function' && LK.VideoQuality && LK.VideoQuality.HIGH != null) {
                publication.setVideoQuality(LK.VideoQuality.HIGH);
            }
        } catch (e) {}
        if (!el) return;
        el.autoplay = true;
        el.playsInline = true;
        var play = el.play && el.play();
        if (play && typeof play.catch === 'function') play.catch(function () {});
    }

    function attachRemoteAudio(el) {
        var box = $('ckAudio');
        if (!box || !el) return;
        box.appendChild(el);
        applyAudioSink(el);
        playAudioEl(el);
    }

    function attachTrack(participant, publication, track) {
        if (!track) return;
        var name = participant.name || participant.identity;
        if (publication.source === LK.Track.Source.ScreenShare) {
            setSharingLayout(true, !!participant.isLocal);
            if (participant.isLocal) {
                return;
            }
            var screenEl = track.attach();
            screenEl.autoplay = true;
            screenEl.playsInline = true;
            screenEl.muted = true;
            attachScreenToHero(screenEl, name);
            return;
        }
        if (track.kind === 'audio') {
            if (participant.isLocal) {
                return;
            }
            attachRemoteAudio(track.attach());
            return;
        }
        if (studentHidesCameraTile(participant, publication)) {
            if (!isLocalParticipant(participant) && !(publication && publication.isLocal)) {
                skipPeerStudentCamera(participant, publication);
            } else if (participant && participant.identity) {
                var selfTile = document.getElementById('tile-' + participant.identity);
                if (selfTile && !selfTile.classList.contains('ck-tile-staff')) {
                    selfTile.remove();
                }
            }
            return;
        }
        var el = track.attach();
        el.autoplay = true;
        el.playsInline = true;
        el.muted = true;
        if (participant.isLocal) {
            el.style.transform = 'scaleX(-1)';
        }
        attachTile(participant.identity, name, el, false);
        if (!participant.isLocal && participantIsStaff(participant) && track.kind === 'video') {
            warmTeacherCamera(publication, el);
            refreshStudentFeed();
        }
        if (cfg.isHost && !participantIsStaff(participant) && track.kind === 'video' && publication.source !== LK.Track.Source.ScreenShare) {
            attachScpTrack(participant, publication, track);
            bridgeBroadcast({
                t: 'track_subscribed',
                identity: participant.identity,
                name: name,
                kind: 'video',
                mediaStreamTrack: track.mediaStreamTrack
            });
        }
    }

    /* -------------------------------------------------------------
     * Floating Student Camera Panel & Pop-out Secondary Windows Bridge
     * ------------------------------------------------------------- */

    if (typeof BroadcastChannel !== 'undefined') {
        try {
            bridgeChannel = new BroadcastChannel('ck_monitor_' + cfg.lessonId);
            bridgeChannel.onmessage = function (ev) {
                var data = ev.data;
                if (!data || !data.t) return;
                if (data.t === 'popout_ready') {
                    sendFullStateToPopout(null, data.mode);
                } else if (data.t === 'chat_sent' && data.message) {
                    appendChat(data.message);
                    var dest = isPrivateMsg(data.message) ? privateDestinationIdentities(data.message) : null;
                    publishChatPacket({
                        t: 'chat',
                        id: data.message.id,
                        body: data.message.body,
                        name: data.message.display_name,
                        display_name: data.message.display_name,
                        user_id: data.message.user_id,
                        is_announcement: data.message.is_announcement,
                        is_private: data.message.is_private,
                        recipient_user_id: data.message.recipient_user_id,
                        created_at: data.message.created_at
                    }, dest);
                } else if (data.t === 'popout_closing') {
                    onPopoutClosed(data.mode);
                }
            };
        } catch (e) {}
    }

    function bridgeBroadcast(msg) {
        if (!cfg.isHost) return;
        if (bridgeChannel) {
            try { bridgeChannel.postMessage(msg); } catch (e) {}
        }
        Object.keys(activePopouts).forEach(function (mode) {
            var win = activePopouts[mode];
            if (win && !win.closed && win.CK_MONITOR) {
                try {
                    if (msg.t === 'participant_joined') win.CK_MONITOR.onParticipantJoined(msg.identity, msg.name);
                    else if (msg.t === 'participant_left') win.CK_MONITOR.onParticipantLeft(msg.identity);
                    else if (msg.t === 'track_subscribed') win.CK_MONITOR.onTrackSubscribed(msg.identity, msg.name, msg.kind, msg.mediaStreamTrack);
                    else if (msg.t === 'track_unsubscribed') win.CK_MONITOR.onTrackUnsubscribed(msg.identity, msg.kind);
                    else if (msg.t === 'track_muted') win.CK_MONITOR.onTrackMuted(msg.identity, msg.kind, msg.muted);
                    else if (msg.t === 'active_speakers') win.CK_MONITOR.onActiveSpeakersChanged(msg.speakerIds);
                    else if (msg.t === 'chat_message') win.CK_MONITOR.onChatMessage(msg.message);
                    else if (msg.t === 'chat_clear') win.CK_MONITOR.onChatCleared();
                    else if (msg.t === 'hands_updated') win.CK_MONITOR.onHandsUpdated(msg.hands);
                    else if (msg.t === 'class_ended') win.CK_MONITOR.onClassEnded();
                } catch (e) {}
            }
        });
    }

    function collectCurrentState() {
        var studentList = [];
        if (room && room.remoteParticipants) {
            room.remoteParticipants.forEach(function (p) {
                if (!p || isLocalParticipant(p) || participantIsStaff(p)) return;
                var hasVideo = false;
                var mediaStreamTrack = null;
                var isMuted = true;
                if (p.trackPublications) {
                    p.trackPublications.forEach(function (pub) {
                        if (isCameraSource(pub) && !pub.isMuted && pub.track) {
                            hasVideo = true;
                            if (pub.track.mediaStreamTrack) mediaStreamTrack = pub.track.mediaStreamTrack;
                        }
                        if (pub.kind === 'audio') {
                            isMuted = !!pub.isMuted;
                        }
                    });
                }
                studentList.push({
                    identity: p.identity,
                    name: p.name || p.identity,
                    isMuted: isMuted,
                    isSpeaking: activeSpeakerId === p.identity,
                    isCameraBlocked: !!camBlockState[p.identity],
                    isMicBlocked: !!micBlockState[p.identity],
                    hasVideo: hasVideo,
                    mediaStreamTrack: mediaStreamTrack
                });
            });
        }
        return {
            participants: studentList,
            activeSpeakerIds: activeSpeakerId ? [activeSpeakerId] : [],
            chatMessages: chatHistoryCache.slice(-50),
            hands: currentHandsList || []
        };
    }

    function sendFullStateToPopout(targetWin, mode) {
        var state = collectCurrentState();
        if (targetWin && !targetWin.closed && targetWin.CK_MONITOR) {
            try { targetWin.CK_MONITOR.syncState(state); } catch (e) {}
        } else if (bridgeChannel) {
            try { bridgeChannel.postMessage(Object.assign({ t: 'full_state' }, state)); } catch (e) {}
        }
    }

    window.CK_HOST_BRIDGE = {
        registerPopout: function (childWin, mode) {
            activePopouts[mode] = childWin;
            updatePopoutUiState();
            sendFullStateToPopout(childWin, mode);
            var poll = setInterval(function () {
                if (!childWin || childWin.closed) {
                    clearInterval(poll);
                    onPopoutClosed(mode);
                }
            }, 1500);
        },
        unregisterPopout: function (childWin, mode) {
            delete activePopouts[mode];
            updatePopoutUiState();
        },
        onPopoutSentChat: function (msg) {
            appendChat(msg);
            var dest = isPrivateMsg(msg) ? privateDestinationIdentities(msg) : null;
            publishChatPacket({
                t: 'chat',
                id: msg.id,
                body: msg.body,
                name: msg.display_name,
                display_name: msg.display_name,
                user_id: msg.user_id,
                is_announcement: msg.is_announcement,
                is_private: msg.is_private,
                recipient_user_id: msg.recipient_user_id,
                created_at: msg.created_at
            }, dest);
        }
    };

    function openPopout(mode) {
        if (!cfg.isHost) return null;
        var url = cfg.apiBase.replace(/\/api\/classroom\/$/, '/classroom/monitor.php') +
            '?lesson=' + encodeURIComponent(cfg.lessonId) +
            '&mode=' + encodeURIComponent(mode);
        var w = mode === 'monitor' ? 1260 : (mode === 'chat' ? 460 : 900);
        var h = mode === 'monitor' ? 840 : (mode === 'chat' ? 720 : 660);
        var left = Math.max(30, (window.screenLeft || window.screenX || 0) + 50);
        var top = Math.max(30, (window.screenTop || window.screenY || 0) + 50);
        var features = 'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top +
            ',menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes';
        var winName = 'ck_monitor_' + mode + '_' + cfg.lessonId;

        lastPopoutAttempt = { mode: mode };

        var win = window.open(url, winName, features);
        if (!win || win.closed || typeof win.closed === 'undefined') {
            showPopupBlockedBanner();
            return null;
        }
        hidePopupBlockedBanner();
        activePopouts[mode] = win;
        updatePopoutUiState();
        try { win.focus(); } catch (e) {}
        return win;
    }

    function showPopupBlockedBanner() {
        var b = $('ckPopupBlockedBanner');
        if (b) b.hidden = false;
        toast('Pop-up window blocked. Please allow pop-ups for Edexcel College.');
    }

    function hidePopupBlockedBanner() {
        var b = $('ckPopupBlockedBanner');
        if (b) b.hidden = true;
    }

    function onPopoutClosed(mode) {
        delete activePopouts[mode];
        updatePopoutUiState();
        if (mode === 'camera' || mode === 'monitor') {
            refreshScpTiles();
        }
    }

    function updatePopoutUiState() {
        var isCameraPopped = !!(activePopouts.camera && !activePopouts.camera.closed) ||
                             !!(activePopouts.monitor && !activePopouts.monitor.closed);
        var notice = $('ckScpPoppedState');
        var stage = $('ckScpStage');
        if (notice) notice.hidden = !isCameraPopped;
        if (stage) stage.hidden = isCameraPopped;

        var togBtn = $('ckStudentCamsToggle');
        if (togBtn) togBtn.classList.toggle('is-popped', isCameraPopped);

        var monBtn = $('ckTeachingMonitorBtn');
        var isMonPopped = !!(activePopouts.monitor && !activePopouts.monitor.closed);
        if (monBtn) monBtn.classList.toggle('is-popped', isMonPopped);
    }

    // In-Classroom Floating Student Camera Panel controller
    function getOrCreateScpStudent(identity, name) {
        if (!scpStudents[identity]) {
            scpStudents[identity] = {
                identity: identity,
                userId: identityUser(identity),
                name: name || identity,
                hasVideo: false,
                mediaStreamTrack: null,
                isMuted: true,
                isSpeaking: false,
                isCameraBlocked: false,
                isMicBlocked: false,
                lastActivity: Date.now()
            };
        } else if (name && scpStudents[identity].name !== name) {
            scpStudents[identity].name = name;
        }
        return scpStudents[identity];
    }

    function getScpInitials(name) {
        var parts = String(name || '').trim().split(/\s+/);
        if (!parts.length || !parts[0]) return '?';
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    function renderScpTile(student) {
        if (!cfg.isHost) return;
        var grid = $('ckScpGrid');
        if (!grid) return;

        var tileId = 'scp-tile-' + student.identity;
        var tile = document.getElementById(tileId);

        if (!tile) {
            tile = document.createElement('div');
            tile.id = tileId;
            tile.className = 'ck-scp-tile';
            tile.setAttribute('data-identity', student.identity);

            tile.innerHTML =
                '<div class="ck-scp-video-wrap">' +
                    '<video class="ck-scp-video" autoplay playsinline muted></video>' +
                    '<div class="ck-scp-avatar">' +
                        '<span class="ck-scp-avatar-initials">' + escapeHtml(getScpInitials(student.name)) + '</span>' +
                        '<span class="ck-scp-avatar-label"><i class="bi bi-camera-video-off"></i> Cam Off</span>' +
                    '</div>' +
                '</div>' +
                '<div class="ck-scp-meta">' +
                    '<div class="ck-scp-info">' +
                        '<span class="ck-scp-mic-icon" title="Microphone status">' +
                            '<i class="bi bi-mic-mute-fill"></i>' +
                        '</span>' +
                        '<strong class="ck-scp-name">' + escapeHtml(student.name) + '</strong>' +
                    '</div>' +
                    '<div class="ck-scp-tile-actions">' +
                        '<button type="button" class="ck-scp-tile-btn ck-scp-pin-btn" title="Pin / Spotlight student">' +
                            '<i class="bi bi-pin"></i>' +
                        '</button>' +
                        '<button type="button" class="ck-scp-tile-btn ck-scp-mute-btn" title="Mute student">' +
                            '<i class="bi bi-mic"></i>' +
                        '</button>' +
                        '<button type="button" class="ck-scp-tile-btn ck-scp-cam-btn" title="Camera off">' +
                            '<i class="bi bi-camera-video"></i>' +
                        '</button>' +
                    '</div>' +
                '</div>';

            var pinBtn = tile.querySelector('.ck-scp-pin-btn');
            if (pinBtn) {
                pinBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleScpPin(student.identity);
                });
            }

            var muteBtn = tile.querySelector('.ck-scp-mute-btn');
            if (muteBtn) {
                muteBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var action = student.isMicBlocked ? 'allow_mic' : 'mute';
                    api('control.php', { action: action, identity: student.identity, user_id: student.userId }).then(function (res) {
                        if (res && res.ok) {
                            student.isMicBlocked = (action === 'mute');
                            student.isMuted = (action === 'mute');
                            renderScpTile(student);
                            toast(action === 'mute' ? (student.name + ' muted.') : ('Mic allowed for ' + student.name));
                        }
                    });
                });
            }

            var camBtn = tile.querySelector('.ck-scp-cam-btn');
            if (camBtn) {
                camBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var action = student.isCameraBlocked ? 'unblock_camera' : 'block_camera';
                    api('control.php', { action: action, identity: student.identity, user_id: student.userId }).then(function (res) {
                        if (res && res.ok) {
                            student.isCameraBlocked = (action === 'block_camera');
                            renderScpTile(student);
                            toast(action === 'block_camera' ? (student.name + ' camera turned off.') : ('Camera allowed for ' + student.name));
                        }
                    });
                });
            }

            grid.appendChild(tile);
        }

        tile.classList.toggle('is-speaking', !!student.isSpeaking);
        tile.classList.toggle('is-pinned', scpPinnedId === student.identity);
        tile.classList.toggle('has-video', !!student.hasVideo);

        var nameEl = tile.querySelector('.ck-scp-name');
        if (nameEl) nameEl.textContent = student.name;

        var micIcon = tile.querySelector('.ck-scp-mic-icon i');
        if (micIcon) {
            if (student.isSpeaking) micIcon.className = 'bi bi-mic-fill text-success';
            else if (!student.isMuted) micIcon.className = 'bi bi-mic text-success';
            else micIcon.className = 'bi bi-mic-mute-fill text-danger';
        }

        var pinIcon = tile.querySelector('.ck-scp-pin-btn i');
        if (pinIcon) {
            pinIcon.className = scpPinnedId === student.identity ? 'bi bi-pin-fill text-accent' : 'bi bi-pin';
        }

        var videoEl = tile.querySelector('.ck-scp-video');
        if (videoEl) {
            videoEl.muted = true; // Permanent echo safety!
            videoEl.volume = 0;
            if (student.hasVideo && student.mediaStreamTrack) {
                if (!videoEl.srcObject || videoEl._trackId !== student.mediaStreamTrack.id) {
                    try {
                        videoEl.srcObject = new MediaStream([student.mediaStreamTrack]);
                        videoEl._trackId = student.mediaStreamTrack.id;
                        videoEl.play().catch(function () {});
                    } catch (e) {}
                }
            } else {
                if (videoEl.srcObject) {
                    videoEl.srcObject = null;
                    videoEl._trackId = null;
                }
            }
        }

        updateScpEmptyState();
        renderSidebarStudents();
    }

    function removeScpStudent(identity) {
        var tile = document.getElementById('scp-tile-' + identity);
        if (tile) {
            var video = tile.querySelector('video');
            if (video && video.srcObject) video.srcObject = null;
            tile.remove();
        }
        delete scpStudents[identity];
        if (scpPinnedId === identity) scpPinnedId = null;
        updateScpEmptyState();
        layoutScpStage();
        renderSidebarStudents();
    }

    function updateScpEmptyState() {
        var empty = $('ckScpEmpty');
        var countEl = $('ckScpCount');
        var count = Object.keys(scpStudents).length;
        if (countEl) countEl.textContent = String(count);
        if (empty) empty.hidden = count > 0;
    }

    function toggleScpPin(identity) {
        if (scpPinnedId === identity) scpPinnedId = null;
        else scpPinnedId = identity;
        layoutScpStage();
    }

    function layoutScpStage() {
        var spotlight = $('ckScpSpotlight');
        var grid = $('ckScpGrid');
        if (!spotlight || !grid) return;

        var spotId = scpPinnedId;
        if (!spotId && scpViewMode === 'speaker') {
            if (activeSpeakerId && scpStudents[activeSpeakerId]) {
                spotId = activeSpeakerId;
            }
        }

        if (spotId && scpStudents[spotId]) {
            spotlight.hidden = false;
            var spotTile = document.getElementById('scp-tile-' + spotId);
            if (spotTile && spotTile.parentNode !== spotlight) {
                spotlight.innerHTML = '';
                spotlight.appendChild(spotTile);
            }
        } else {
            spotlight.hidden = true;
            Array.prototype.forEach.call(spotlight.children, function (child) {
                grid.appendChild(child);
            });
        }
    }

    function setScpOpen(on) {
        var panel = $('ckStudentCamPanel');
        if (!panel) return;
        panel.hidden = !on;
        var tog = $('ckStudentCamsToggle');
        if (tog) tog.classList.toggle('is-on', !!on);
        if (on) {
            refreshScpTiles();
        }
    }

    function setScpMinimized(min) {
        var panel = $('ckStudentCamPanel');
        if (!panel) return;
        panel.classList.toggle('is-minimized', !!min);
    }

    function attachScpTrack(participant, publication, track) {
        if (!cfg.isHost || !participant || participantIsStaff(participant)) return;
        var student = getOrCreateScpStudent(participant.identity, participant.name);
        student.hasVideo = true;
        student.mediaStreamTrack = track.mediaStreamTrack;
        renderScpTile(student);
        layoutScpStage();
    }

    function removeScpTrack(participant, publication) {
        if (!cfg.isHost || !participant) return;
        var student = scpStudents[participant.identity];
        if (student && isCameraSource(publication)) {
            student.hasVideo = false;
            student.mediaStreamTrack = null;
            renderScpTile(student);
            layoutScpStage();
        }
    }

    function updateScpActiveSpeakers(speakers) {
        if (!cfg.isHost) return;
        var ids = {};
        (speakers || []).forEach(function (s) { ids[s.identity] = true; });
        Object.keys(scpStudents).forEach(function (id) {
            var isSpk = !!ids[id];
            if (scpStudents[id].isSpeaking !== isSpk) {
                scpStudents[id].isSpeaking = isSpk;
                if (isSpk) scpStudents[id].lastActivity = Date.now();
                renderScpTile(scpStudents[id]);
            }
        });
        if (scpViewMode === 'speaker') {
            layoutScpStage();
        }
        renderSidebarStudents();
    }

    function updateScpTrackMuted(participant, publication, muted) {
        if (!cfg.isHost || !participant) return;
        var student = scpStudents[participant.identity];
        if (student) {
            if (publication && publication.kind === 'audio') student.isMuted = !!muted;
            if (publication && isCameraSource(publication)) student.hasVideo = !muted;
            renderScpTile(student);
        }
    }

    function refreshScpTiles() {
        if (!cfg.isHost || !room || !room.remoteParticipants) return;
        room.remoteParticipants.forEach(function (p) {
            if (!p || isLocalParticipant(p) || participantIsStaff(p)) return;
            var s = getOrCreateScpStudent(p.identity, p.name);
            s.isCameraBlocked = !!camBlockState[p.identity];
            s.isMicBlocked = !!micBlockState[p.identity];
            if (p.trackPublications) {
                p.trackPublications.forEach(function (pub) {
                    if (isCameraSource(pub) && !pub.isMuted && pub.track) {
                        s.hasVideo = true;
                        s.mediaStreamTrack = pub.track.mediaStreamTrack;
                    }
                    if (pub.kind === 'audio') {
                        s.isMuted = !!pub.isMuted;
                    }
                });
            }
            renderScpTile(s);
        });
        layoutScpStage();
        renderSidebarStudents();
    }

    function initScpDrag() {
        var panel = $('ckStudentCamPanel');
        var head = $('ckStudentCamHead');
        if (!panel || !head) return;

        var isDragging = false;
        var startX = 0, startY = 0;
        var initLeft = 0, initTop = 0;

        head.addEventListener('pointerdown', function (e) {
            if (e.target.closest('button') || e.target.closest('input') || e.target.closest('select')) return;
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            var rect = panel.getBoundingClientRect();
            initLeft = rect.left;
            initTop = rect.top;
            panel.style.position = 'fixed';
            panel.style.left = initLeft + 'px';
            panel.style.top = initTop + 'px';
            panel.style.right = 'auto';
            panel.style.bottom = 'auto';
            head.setPointerCapture(e.pointerId);
            e.preventDefault();
        });

        head.addEventListener('pointermove', function (e) {
            if (!isDragging) return;
            var dx = e.clientX - startX;
            var dy = e.clientY - startY;
            var newL = Math.max(10, Math.min(window.innerWidth - panel.offsetWidth - 10, initLeft + dx));
            var newT = Math.max(60, Math.min(window.innerHeight - panel.offsetHeight - 60, initTop + dy));
            panel.style.left = newL + 'px';
            panel.style.top = newT + 'px';
        });

        var stopDrag = function (e) {
            if (isDragging) {
                isDragging = false;
                try { head.releasePointerCapture(e.pointerId); } catch (err) {}
            }
        };

        head.addEventListener('pointerup', stopDrag);
        head.addEventListener('pointercancel', stopDrag);
    }

    // Right Sidebar Students Controller & View
    var sidebarGridCols = 2;

    function renderSidebarStudents() {
        var sidePanel = $('ckPanelStudents');
        if (!sidePanel) return;

        // 1. Determine Hero / Active Speaker
        var heroId = scpPinnedId;
        if (!heroId && activeSpeakerId && scpStudents[activeSpeakerId]) {
            heroId = activeSpeakerId;
        }
        if (!heroId) {
            var studentIds = Object.keys(scpStudents);
            for (var i = 0; i < studentIds.length; i++) {
                if (scpStudents[studentIds[i]].hasVideo) {
                    heroId = studentIds[i];
                    break;
                }
            }
            if (!heroId && studentIds.length > 0) {
                heroId = studentIds[0];
            }
        }
        var heroCard = $('ckActiveSpeakerCard');
        var heroVidEl = $('ckHeroSpeakerVideo');
        var heroAvatarEl = $('ckHeroSpeakerAvatar');
        var heroInitialsEl = $('ckHeroSpeakerInitials');
        var heroNameEl = $('ckHeroSpeakerName');
        var heroMicEl = $('ckHeroSpeakerMic');

        if (heroId && scpStudents[heroId]) {
            var hSt = scpStudents[heroId];
            if (heroNameEl) heroNameEl.textContent = hSt.name;
            if (heroMicEl) {
                heroMicEl.className = 'ck-shc-mic ' + (hSt.isSpeaking ? 'is-speaking' : (hSt.isMuted ? 'is-muted' : ''));
                var micIcon = heroMicEl.querySelector('i');
                if (micIcon) {
                    if (hSt.isSpeaking) micIcon.className = 'bi bi-mic-fill text-success';
                    else if (!hSt.isMuted) micIcon.className = 'bi bi-mic text-success';
                    else micIcon.className = 'bi bi-mic-mute-fill text-danger';
                }
            }
            if (hSt.hasVideo && hSt.mediaStreamTrack && heroVidEl) {
                heroVidEl.hidden = false;
                if (heroAvatarEl) heroAvatarEl.hidden = true;
                if (!heroVidEl.srcObject || heroVidEl._trackId !== hSt.mediaStreamTrack.id) {
                    try {
                        heroVidEl.srcObject = new MediaStream([hSt.mediaStreamTrack]);
                        heroVidEl._trackId = hSt.mediaStreamTrack.id;
                        heroVidEl.play().catch(function () {});
                    } catch (e) {}
                }
            } else {
                if (heroVidEl) {
                    heroVidEl.hidden = true;
                    if (heroVidEl.srcObject) heroVidEl.srcObject = null;
                }
                if (heroAvatarEl) {
                    heroAvatarEl.hidden = false;
                    if (heroInitialsEl) heroInitialsEl.textContent = getScpInitials(hSt.name);
                }
            }
        } else {
            if (heroNameEl) heroNameEl.textContent = cfg.teacher ? cfg.teacher : 'Teacher';
            if (heroMicEl) {
                heroMicEl.className = 'ck-shc-mic';
                var mIcon = heroMicEl.querySelector('i');
                if (mIcon) mIcon.className = 'bi bi-mic-mute-fill text-muted';
            }
            if (heroVidEl) {
                heroVidEl.hidden = true;
                if (heroVidEl.srcObject) heroVidEl.srcObject = null;
            }
            if (heroAvatarEl) {
                heroAvatarEl.hidden = false;
                if (heroInitialsEl) heroInitialsEl.textContent = cfg.teacher ? getScpInitials(cfg.teacher) : 'T';
            }
        }

        // 2. Render student subgrid
        var subgrid = $('ckStudentsSubgrid');
        var emptyEl = $('ckStudentsEmpty');
        if (!subgrid) return;

        var allStudentKeys = Object.keys(scpStudents);
        var gridStudents = allStudentKeys.filter(function (id) {
            return id !== heroId;
        });

        if (gridStudents.length === 0) {
            if (emptyEl) emptyEl.hidden = false;
            var existingCards = subgrid.querySelectorAll('.ck-student-subcard');
            existingCards.forEach(function (c) {
                var v = c.querySelector('video');
                if (v && v.srcObject) v.srcObject = null;
                c.remove();
            });
            var badge = $('ckStudentsMoreBadge');
            if (badge) badge.hidden = true;
            return;
        }

        if (emptyEl) emptyEl.hidden = true;

        var pageSize = 6;
        var totalStudentPages = Math.max(1, Math.ceil(gridStudents.length / pageSize));
        if (sidebarStudentPage > totalStudentPages) sidebarStudentPage = 1;
        if (sidebarStudentPage < 1) sidebarStudentPage = 1;

        var pageIndicator = $('ckStudentsPageIndicator');
        if (pageIndicator) pageIndicator.textContent = sidebarStudentPage + ' / ' + totalStudentPages;

        var startIdx = (sidebarStudentPage - 1) * pageSize;
        var visibleKeys = gridStudents.slice(startIdx, startIdx + pageSize);
        var overflowCount = Math.max(0, gridStudents.length - (startIdx + pageSize));

        var badge = $('ckStudentsMoreBadge');
        if (badge) {
            badge.hidden = overflowCount === 0;
            if (overflowCount > 0) {
                var span = badge.querySelector('span');
                if (span) span.textContent = '+' + overflowCount + ' More students';
            }
        }

        var visibleSet = {};
        visibleKeys.forEach(function (k) { visibleSet['subcard-' + k] = true; });
        var cards = subgrid.querySelectorAll('.ck-student-subcard');
        cards.forEach(function (c) {
            if (!visibleSet[c.id]) {
                var v = c.querySelector('video');
                if (v && v.srcObject) v.srcObject = null;
                c.remove();
            }
        });

        visibleKeys.forEach(function (id) {
            var st = scpStudents[id];
            var cardId = 'subcard-' + id;
            var card = document.getElementById(cardId);
            if (!card) {
                card = document.createElement('div');
                card.id = cardId;
                card.className = 'ck-student-subcard';
                card.innerHTML =
                    '<div class="ck-subcard-media">' +
                        '<video class="ck-subcard-video" autoplay playsinline muted></video>' +
                        '<div class="ck-subcard-avatar"><span>' + escapeHtml(getScpInitials(st.name)) + '</span></div>' +
                    '</div>' +
                    '<div class="ck-subcard-badge">' +
                        '<span class="ck-subcard-name">' + escapeHtml(st.name) + '</span>' +
                        '<span class="ck-subcard-mic"><i class="bi bi-mic-mute-fill"></i></span>' +
                    '</div>' +
                    '<div class="ck-subcard-actions">' +
                        '<button type="button" class="ck-subcard-act-btn ck-act-pin" title="Pin to active view"><i class="bi bi-pin"></i></button>' +
                        '<button type="button" class="ck-subcard-act-btn ck-act-mute" title="Mute/Unmute"><i class="bi bi-mic"></i></button>' +
                    '</div>';

                var pinB = card.querySelector('.ck-act-pin');
                if (pinB) {
                    pinB.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                        toggleScpPin(st.identity);
                        renderSidebarStudents();
                    });
                }
                var muteB = card.querySelector('.ck-act-mute');
                if (muteB) {
                    muteB.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                        var action = st.isMicBlocked ? 'allow_mic' : 'mute';
                        api('control.php', { action: action, identity: st.identity, user_id: st.userId }).then(function (res) {
                            if (res && res.ok) {
                                st.isMicBlocked = (action === 'mute');
                                st.isMuted = (action === 'mute');
                                renderScpTile(st);
                                renderSidebarStudents();
                                toast(action === 'mute' ? (st.name + ' muted.') : ('Mic allowed for ' + st.name));
                            }
                        });
                    });
                }

                subgrid.appendChild(card);
            }

            card.classList.toggle('is-speaking', !!st.isSpeaking);
            card.classList.toggle('has-video', !!st.hasVideo);

            var nameSpan = card.querySelector('.ck-subcard-name');
            if (nameSpan) nameSpan.textContent = st.name;

            var micI = card.querySelector('.ck-subcard-mic i');
            if (micI) {
                if (st.isSpeaking) micI.className = 'bi bi-mic-fill text-success';
                else if (!st.isMuted) micI.className = 'bi bi-mic text-success';
                else micI.className = 'bi bi-mic-mute-fill text-danger';
            }

            var vid = card.querySelector('.ck-subcard-video');
            var av = card.querySelector('.ck-subcard-avatar');
            if (vid && av) {
                if (st.hasVideo && st.mediaStreamTrack) {
                    vid.hidden = false;
                    av.hidden = true;
                    if (!vid.srcObject || vid._trackId !== st.mediaStreamTrack.id) {
                        try {
                            vid.srcObject = new MediaStream([st.mediaStreamTrack]);
                            vid._trackId = st.mediaStreamTrack.id;
                            vid.play().catch(function () {});
                        } catch (e) {}
                    }
                } else {
                    vid.hidden = true;
                    if (vid.srcObject) vid.srcObject = null;
                    av.hidden = false;
                    var avSpan = av.querySelector('span');
                    if (avSpan) avSpan.textContent = getScpInitials(st.name);
                }
            }
        });
    }

    function setupSidebarTabs() {
        var prevBtn = $('ckStudentsPrevBtn');
        var nextBtn = $('ckStudentsNextBtn');
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                var allStudentKeys = Object.keys(scpStudents);
                var total = Math.max(1, Math.ceil(allStudentKeys.length / 6));
                if (sidebarStudentPage > 1) {
                    sidebarStudentPage--;
                    renderSidebarStudents();
                } else if (total > 1) {
                    sidebarStudentPage = total;
                    renderSidebarStudents();
                }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                var allStudentKeys = Object.keys(scpStudents);
                var total = Math.max(1, Math.ceil(allStudentKeys.length / 6));
                if (sidebarStudentPage < total) {
                    sidebarStudentPage++;
                    renderSidebarStudents();
                } else if (total > 1) {
                    sidebarStudentPage = 1;
                    renderSidebarStudents();
                }
            });
        }

        var viewAllBtn = $('ckViewAllStudentsBtn');
        if (viewAllBtn) {
            viewAllBtn.addEventListener('click', function () {
                var panel = $('ckStudentCamPanel');
                if (panel) {
                    panel.hidden = !panel.hidden;
                } else {
                    openPopout('camera');
                }
            });
        }

        var chatCollapseBtn = $('ckChatCollapseBtn');
        if (chatCollapseBtn) {
            chatCollapseBtn.addEventListener('click', function () {
                var body = $('ckPanelChat');
                if (body) {
                    var isCollapsed = body.classList.toggle('is-collapsed');
                    chatCollapseBtn.classList.toggle('is-collapsed', isCollapsed);
                    body.hidden = isCollapsed;
                }
            });
        }

        var emojiBtn = $('ckChatEmojiBtn');
        if (emojiBtn) {
            emojiBtn.addEventListener('click', function () {
                var inp = $('ckChatInput');
                if (inp) {
                    var emojis = ['👍', '😊', '✋', '💡', '🎉'];
                    var pick = emojis[Math.floor(Math.random() * emojis.length)];
                    inp.value = (inp.value ? inp.value + ' ' : '') + pick;
                    inp.focus();
                }
            });
        }

        document.querySelectorAll('.ck-gm-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cols = parseInt(btn.getAttribute('data-cols'), 10) || 2;
                sidebarGridCols = cols;
                document.querySelectorAll('.ck-gm-btn').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                var subgrid = $('ckStudentsSubgrid');
                if (subgrid) {
                    subgrid.classList.remove('ck-cols-1', 'ck-cols-2', 'ck-cols-3');
                    subgrid.classList.add('ck-cols-' + cols);
                }
            });
        });

        var popoutBtn = $('ckSidebarPopoutBtn');
        if (popoutBtn) {
            popoutBtn.addEventListener('click', function () {
                openPopout('camera');
            });
        }
    }

    function refreshPeople() {
        if (!room) return;
        var list = $('ckPeople');
        if (!list) return;
        list.innerHTML = '';
        function row(p, local) {
            var li = document.createElement('li');
            var left = document.createElement('div');
            left.innerHTML = '<strong>' + escapeHtml(p.name || p.identity) + '</strong>' +
                (local ? ' <span>(you)</span>' : '');
            li.appendChild(left);
            if (cfg.isHost && !local) {
                var tools = document.createElement('div');
                tools.className = 'ck-mini';
                if (!participantIsStaff(p)) {
                    tools.appendChild(miniBtn('Message', function () {
                        openPrivateThread({
                            userId: identityUser(p.identity),
                            name: p.name || p.identity,
                            identity: p.identity
                        }, true);
                    }));
                    var micOff = !micAllowedFor(p);
                    var muteBtn = miniBtn(micOff ? 'Allow mic' : 'Mute', function () {
                        api('control.php', {
                            action: micOff ? 'allow_mic' : 'mute',
                            identity: p.identity,
                            user_id: identityUser(p.identity)
                        }).then(function (res) {
                            if (!res || !res.ok) {
                                toast((res && res.error) || (micOff ? 'Could not allow that microphone.' : 'Could not mute that student.'));
                                return;
                            }
                            micBlockState[p.identity] = !micOff;
                            refreshPeople();
                            toast(micOff
                                ? 'Microphone allowed for ' + (p.name || 'student') + '.'
                                : (p.name || 'Student') + ' is muted.');
                        }).catch(function () {
                            toast(micOff ? 'Could not allow that microphone.' : 'Could not mute that student.');
                        });
                    });
                    muteBtn.setAttribute('data-ck-action', micOff ? 'allow_mic' : 'mute');
                    tools.appendChild(muteBtn);
                    var camOn = cameraAllowedFor(p) && participantCameraOn(p);
                    tools.appendChild(miniBtn(camOn ? 'Cam off' : 'Cam on', function () {
                        api('control.php', {
                            action: camOn ? 'block_camera' : 'unblock_camera',
                            identity: p.identity,
                            user_id: identityUser(p.identity)
                        }).then(function (res) {
                            if (!res.ok) {
                                toast(res.error || 'Could not update that camera.');
                                return;
                            }
                            camBlockState[p.identity] = camOn;
                            refreshPeople();
                            toast(camOn
                                ? 'Camera off for ' + (p.name || 'student') + '.'
                                : 'Camera on for ' + (p.name || 'student') + '.');
                        });
                    }));
                    var shareOn = shareAllowedFor(p);
                    tools.appendChild(miniBtn(shareOn ? 'Revoke share' : 'Allow share', function () {
                        api('control.php', {
                            action: shareOn ? 'revoke_share' : 'allow_share',
                            identity: p.identity,
                            user_id: identityUser(p.identity)
                        }).then(function (res) {
                            if (!res.ok) {
                                toast(res.error || 'Could not update screen share.');
                                return;
                            }
                            shareAllowState[p.identity] = !shareOn;
                            refreshPeople();
                            toast(shareOn
                                ? 'Screen share revoked for ' + (p.name || 'student') + '.'
                                : 'Screen share allowed for ' + (p.name || 'student') + '.');
                        });
                    }));
                }
                tools.appendChild(miniBtn('Remove', function () {
                    api('control.php', { action: 'kick', identity: p.identity, user_id: identityUser(p.identity) });
                }));
                li.appendChild(tools);
            }
            list.appendChild(li);
            if (cfg.isHost && !local && !participantIsStaff(p)) {
                addPrivatePeer(identityUser(p.identity), p.name || p.identity, p.identity);
            }
        }
        row(room.localParticipant, true);
        var totalParticipants = 1;
        forEachRemote(function (p) {
            totalParticipants++;
            row(p, false);
        });
        var countBadge = $('ckHeaderParticipantsCount');
        if (countBadge) countBadge.textContent = String(totalParticipants);
        var peopleEdgeBadge = $('ckPeopleEdgeBadge');
        if (peopleEdgeBadge) {
            peopleEdgeBadge.textContent = String(totalParticipants);
            peopleEdgeBadge.hidden = totalParticipants <= 0;
        }
        var mobCountBadge = $('ckMobilePeopleBadge');
        if (mobCountBadge) mobCountBadge.textContent = String(totalParticipants);
        layoutStage();
        renderSidebarStudents();
    }

    function miniBtn(label, fn) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        b.addEventListener('click', fn);
        return b;
    }

    function identityUser(identity) {
        var m = /^u(\d+)$/i.exec(identity || '');
        return m ? parseInt(m[1], 10) : 0;
    }

    function forEachRemote(fn) {
        if (!room) return;
        var maps = [room.remoteParticipants, room.participants];
        var seen = {};
        for (var i = 0; i < maps.length; i++) {
            var m = maps[i];
            if (!m) continue;
            if (typeof m.forEach === 'function') {
                m.forEach(function (p) {
                    if (!p || !p.identity || isLocalParticipant(p) || seen[p.identity]) return;
                    seen[p.identity] = true;
                    fn(p);
                });
                if (Object.keys(seen).length) return;
            }
        }
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function joinError(err) {
        var msg = '';
        if (err && typeof err === 'object') {
            msg = String(err.message || err.reason || err.status || '');
        } else if (err) {
            msg = String(err);
        }
        var low = msg.toLowerCase();
        if (low.indexOf('permission') >= 0 || low.indexOf('unauthorized') >= 0 || (low.indexOf('401') >= 0 && low.indexOf('pc') < 0)) {
            return 'The video server rejected this class. API key/secret must match livekit.yaml.' + (msg ? ' (' + msg + ')' : '');
        }
        if (low.indexOf('pc connection') >= 0 || low.indexOf('peerconnection') >= 0 || low.indexOf('ice') >= 0) {
            return 'LiveKit connected, but camera/mic UDP is blocked. Open UDP 50000–50100 and 3478 (VPS firewall and cloud security group).' + (msg ? ' (' + msg + ')' : '');
        }
        if (low.indexOf('signal') >= 0 || low.indexOf('websocket') >= 0 || low.indexOf('failed to fetch') >= 0) {
            return 'Could not open the LiveKit WebSocket. Port 8443 is reaching Caddy but LiveKit on 7880 is not (HTTP 502). On the VPS run: bash /opt/livekit/fix-signal-8443.sh — then Join from a new Incognito window (Ctrl+Shift+N).' + (msg ? ' (' + msg + ')' : '');
        }
        if (low.indexOf('network') >= 0) {
            return 'Network error talking to the video server. Check wss://live.kandy.edexcel.college:8443 and that LiveKit is running.' + (msg ? ' (' + msg + ')' : '');
        }
        if (msg) {
            return 'Could not join class: ' + msg;
        }
        return 'Could not join the video room. Please try again.';
    }

    function resetJoinUi() {
        connected = false;
        try { if (room) room.disconnect(); } catch (e) {}
        room = null;
        clearScreenUi();
        showLobby(true);
        renderLobbyActions('live');
    }

    function connect() {
        if (!LK) {
            toast('Could not load the classroom. Refresh and try again.');
            return;
        }
        if (connected && room) {
            return;
        }
        if (connect.busy) {
            return;
        }
        connect.busy = true;
        api('token.php', {}).then(function (data) {
            if (data.wait) {
                lastWaitKind = data.wait_kind || lastWaitKind || 'lobby';
                cfg.waitKind = lastWaitKind;
                cfg.accessCode = 'WAIT';
                if ($('ckLobbyMsg')) $('ckLobbyMsg').textContent = data.error || 'Waiting for the teacher to let you in';
                renderLobbyActions(data.status || 'live', {
                    code: 'WAIT',
                    wait_kind: lastWaitKind,
                    waiting_room: data.waiting_room
                });
                if (lastWaitKind !== 'lobby') {
                    toast(data.error);
                }
                return;
            }
            if (data.payment_required) {
                cfg.accessCode = 'PAY';
                cfg.waitKind = 'pay';
                setPreviewAllowed(false);
                if ($('ckLobbyMsg')) $('ckLobbyMsg').textContent = data.error || cfg.accessMessage || '';
                renderLobbyActions(data.status || cfg.state, { code: 'PAY', wait_kind: 'pay' });
                return;
            }
            if (!data.ok) {
                toast(data.error || 'Could not join class.');
                return;
            }
            if (data.identity) cfg.identity = data.identity;
            stopPreview();
            var shareCapture = {
                audio: false,
                systemAudio: 'exclude',
                selfBrowserSurface: 'exclude',
                contentHint: 'detail',
                resolution: { width: 1280, height: 720, frameRate: 12 }
            };
            var roomOpts = {
                adaptiveStream: true,
                dynacast: true,
                audioCaptureDefaults: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                },
                videoCaptureDefaults: { resolution: LK.VideoPresets.h720.resolution },
                screenShareCaptureDefaults: shareCapture,
                publishDefaults: {
                    screenShareEncoding: { maxBitrate: 900000, maxFramerate: 12 },
                    screenShareSimulcastLayers: []
                },
                rtcConfig: { iceTransportPolicy: 'all' }
            };
            if (connected && room) {
                return;
            }
            if (room && room.state && room.state !== 'disconnected') {
                try { room.disconnect(); } catch (e) {}
            }
            room = new LK.Room(roomOpts);
            patchPublishTimeout(room);
            bindRoom(room);
            return room.connect(data.url, data.token, {
                peerConnectionTimeout: 45000,
                websocketTimeout: 20000
            }).then(function () {
                connected = true;
                showLobby(false);
                setStatus('live', 'Live now');
                if (cfg.isHost) {
                    $('ckEnd').hidden = false;
                }
                localShareAllowed = !!(data.screenshare_allowed || (data.settings && data.settings.student_share));
                setShareUi(cfg.isHost || localShareAllowed, false);
                setRecordingUi(!!cfg.recording);
                localCamBlocked = !!data.camera_blocked;
                localMicBlocked = !cfg.isHost && !!data.mic_blocked;
                var camWanted = cfg.isHost || !localCamBlocked;
                return Promise.all([
                    room.startAudio().catch(function () {
                        var b = $('ckEnableSound');
                        if (b) b.hidden = false;
                    }),
                    camWanted
                        ? room.localParticipant.setCameraEnabled(true).then(function () {
                            setCamUi(true, false);
                        }).catch(function (e) { toast(mediaError(e)); })
                        : room.localParticipant.setCameraEnabled(false).then(function () {
                            setCamUi(false, localCamBlocked);
                        }).catch(function () {
                            setCamUi(false, localCamBlocked);
                        }),
                    cfg.isHost
                        ? room.localParticipant.setMicrophoneEnabled(true).then(function () {
                            setMicUi(true, false);
                        }).catch(function (e) {
                            setMicUi(false, false);
                            toast(mediaError(e));
                        })
                        : room.localParticipant.setMicrophoneEnabled(false).then(function () {
                            setMicUi(false, localMicBlocked);
                        }).catch(function () {
                            setMicUi(false, localMicBlocked);
                        })
                ]);
            }).then(function () {
                refreshPeople();
                layoutStage();
                startHeartbeat();
                loadChat();
                loadBoard();
                if (cfg.isHost && cfg.whiteboardEnabled) {
                    showHostBoardOnStage();
                }
                renderSidebarStudents();
                if (!cfg.isHost) {
                    unsubAllPeerStudentCameras();
                    if (anyoneSharing()) enterShareFullStage();
                    else if (boardFocusHint) enterBoardFullStage('auto');
                }
            });
        }).catch(function (err) {
            try { console.error('LiveKit join failed', err); } catch (e) {}
            resetJoinUi();
            toast(joinError(err));
        }).then(function () {
            connect.busy = false;
        });
    }

    function bindRoom(r) {
        r.on(LK.RoomEvent.TrackSubscribed, function (track, pub, participant) {
            attachTrack(participant, pub, track);
            refreshPeople();
        });
        if (LK.RoomEvent.TrackPublished) {
            r.on(LK.RoomEvent.TrackPublished, function (pub, participant) {
                if (isLocalParticipant(participant) || (pub && pub.isLocal)) return;
                if (isPeerStudentCamera(participant, pub)) skipPeerStudentCamera(participant, pub);
            });
        }
        r.on(LK.RoomEvent.TrackUnsubscribed, function (track, pub, participant) {
            track.detach().forEach(function (el) { el.remove(); });
            if (cfg.isHost && participant && !participantIsStaff(participant)) {
                removeScpTrack(participant, pub);
                bridgeBroadcast({ t: 'track_unsubscribed', identity: participant.identity, kind: pub.kind });
            }
            if (pub.source === LK.Track.Source.ScreenShare) {
                removeTile(participant.identity + '-screen');
                clearScreenUi();
            } else {
                layoutStage();
                if (!cfg.isHost && participant && participantIsStaff(participant) && isCameraSource(pub)) {
                    refreshStudentFeed();
                }
            }
        });
        r.on(LK.RoomEvent.AudioPlaybackStatusChanged, function () {
            var b = $('ckEnableSound');
            if (b) b.hidden = !!r.canPlaybackAudio;
        });
        r.on(LK.RoomEvent.LocalTrackPublished, function (pub, participant) {
            var who = participant && participant.identity ? participant : (room && room.localParticipant);
            if (pub && pub.track && who) attachTrack(who, pub, pub.track);
            if (pub && pub.source === LK.Track.Source.ScreenShare) syncSharePauseUi();
        });
        r.on(LK.RoomEvent.LocalTrackUnpublished, function (pub) {
            if (pub.source === LK.Track.Source.ScreenShare) {
                if (sharePause) publishData({ t: 'share-pause', on: false });
                releaseSharePause();
                syncSharePauseUi();
                clearScreenUi();
            } else {
                layoutStage();
            }
        });
        r.on(LK.RoomEvent.ParticipantConnected, function (p) {
            refreshPeople();
            if (!cfg.isHost) unsubAllPeerStudentCameras();
            if (cfg.isHost && p && p.identity && !participantIsStaff(p)) {
                getOrCreateScpStudent(p.identity, p.name);
                renderScpTile(scpStudents[p.identity]);
                bridgeBroadcast({ t: 'participant_joined', identity: p.identity, name: p.name || p.identity });
                setTimeout(function () {
                    if (hostBoardFocus) publishBoardFocus(true, [p.identity]);
                    if (window.CKPdf && window.CKPdf.active && typeof window.CKPdf.republishState === 'function') {
                        window.CKPdf.republishState([p.identity]);
                    }
                }, 400);
            }
        });
        r.on(LK.RoomEvent.ParticipantDisconnected, function (p) {
            removeTile(p.identity);
            delete camBlockState[p.identity];
            delete micBlockState[p.identity];
            delete shareAllowState[p.identity];
            if (cfg.isHost && p) {
                removeScpStudent(p.identity);
                bridgeBroadcast({ t: 'participant_left', identity: p.identity });
            }
            refreshPeople();
        });
        if (LK.RoomEvent.ParticipantPermissionsChanged) {
            r.on(LK.RoomEvent.ParticipantPermissionsChanged, function (a, b) {
                var participant = (b && b.identity) ? b : ((a && a.identity) ? a : null);
                if (participant && participant.isLocal) {
                    applyLocalMicGate('The teacher muted your microphone.', 'The teacher allowed your microphone. Tap Mic to turn it on.');
                    applyLocalCameraGate('The teacher turned off your camera.', '');
                    applyLocalShareGate('The teacher stopped your screen sharing.', 'The teacher allowed you to share your screen.');
                    return;
                }
                if (participant && participant.identity) {
                    delete camBlockState[participant.identity];
                    delete micBlockState[participant.identity];
                    delete shareAllowState[participant.identity];
                }
                refreshPeople();
            });
        }
        r.on(LK.RoomEvent.TrackMuted, function (pub, participant) {
            if (participant && participant.isLocal && pub && pub.source === LK.Track.Source.Microphone && !cfg.isHost) {
                setMicUi(false, localMicBlocked || !micAllowedFor(participant));
            }
            if (participant && participant.isLocal && pub && pub.source === LK.Track.Source.Camera && !cfg.isHost) {
                setCamUi(false, localCamBlocked || !cameraAllowedFor(participant));
            }
            if (participant && participant.isLocal && pub && pub.source === LK.Track.Source.ScreenShare) {
                setShareUi(cfg.isHost || localShareAllowed, false);
            }
            if (cfg.isHost && participant && !participantIsStaff(participant)) {
                updateScpTrackMuted(participant, pub, true);
                bridgeBroadcast({ t: 'track_muted', identity: participant.identity, kind: pub.kind, muted: true });
            }
            if (cfg.isHost) refreshPeople();
            if (!cfg.isHost && participant && participantIsStaff(participant) && isCameraSource(pub)) {
                refreshStudentFeed();
            }
        });
        if (LK.RoomEvent.TrackUnmuted) {
            r.on(LK.RoomEvent.TrackUnmuted, function (pub, participant) {
                if (participant && participant.isLocal && pub && pub.source === LK.Track.Source.Camera && !cfg.isHost) {
                    setCamUi(true, localCamBlocked);
                }
                if (cfg.isHost && participant && !participantIsStaff(participant)) {
                    updateScpTrackMuted(participant, pub, false);
                    bridgeBroadcast({ t: 'track_muted', identity: participant.identity, kind: pub.kind, muted: false });
                }
                if (cfg.isHost) refreshPeople();
                if (!cfg.isHost && participant && participantIsStaff(participant) && isCameraSource(pub)) {
                    refreshStudentFeed();
                }
            });
        }
        r.on(LK.RoomEvent.ActiveSpeakersChanged, function (speakers) {
            if (speakers && speakers.length) {
                activeSpeakerId = speakers[0].identity;
            }
            var ids = {};
            (speakers || []).forEach(function (s) { ids[s.identity] = true; });
            document.querySelectorAll('.ck-tile').forEach(function (tile) {
                var id = (tile.id || '').replace(/^tile-/, '').split('-')[0];
                tile.classList.toggle('is-speaking', !!ids[id]);
            });
            if (cfg.isHost) {
                updateScpActiveSpeakers(speakers);
                bridgeBroadcast({ t: 'active_speakers', speakerIds: (speakers || []).map(function (s) { return s.identity; }) });
            } else if (speakers && speakers.length) {
                var lead = speakers[0];
                if (lead && (participantIsStaff(lead) || (cfg.hostIdentity && lead.identity === cfg.hostIdentity))) {
                    if (!anyoneSharing() && !hostBoardFocus && (Date.now() - lastBoardStrokeTime > 12000)) {
                        onTeacherActivityChange('teacher');
                    }
                }
            }
            if (viewMode === 'speaker' && !anyoneSharing()) {
                layoutStage();
            }
        });
        if (LK.RoomEvent.ConnectionQualityChanged) {
            r.on(LK.RoomEvent.ConnectionQualityChanged, function (quality, participant) {
                if (participant && participant.isLocal === false) return;
                setQos(quality);
            });
        }
        r.on(LK.RoomEvent.DataReceived, function (payload, participant) {
            try {
                var msg = JSON.parse(new TextDecoder().decode(payload));
                if (msg.t === 'board-focus' && !cfg.isHost) {
                    var fromStaff = !participant
                        || participantIsStaff(participant)
                        || (cfg.hostIdentity && participant.identity === cfg.hostIdentity);
                    if (fromStaff) applyBoardFocusHint(!!msg.on);
                }
                if (msg.t === 'hand') applyHandSignal(msg, participant);
                if (msg.t === 'chat') appendChat(msg);
                if (msg.t === 'chat-private-open') acceptPrivateOpen(msg);
                if (msg.t === 'chat-clear') {
                    var log = $('ckChatLog');
                    if (log) log.innerHTML = '';
                }
                if (msg.t === 'wb' || msg.t === 'wb-ink' || msg.t === 'wb-laser' || msg.t === 'wb-spotlight' || msg.t === 'wb-clear' || msg.t === 'pdf_open' || msg.t === 'pdf_page') {
                    lastBoardStrokeTime = Date.now();
                    if (!cfg.isHost && (!participant || participantIsStaff(participant) || (cfg.hostIdentity && participant.identity === cfg.hostIdentity))) {
                        if (!anyoneSharing()) {
                            onTeacherActivityChange('board');
                        }
                    }
                }
                if (msg.t === 'wb-ink') {
                    var boardInk = ensureBoard();
                    if (boardInk && boardInk.receiveInk) boardInk.receiveInk(msg);
                    if (!cfg.isHost && connected && participant && participantIsStaff(participant) && !anyoneSharing()) {
                        enterBoardFullStage('auto');
                    }
                }
                if (msg.t === 'wb') {
                    receiveBoardStroke(msg.stroke);
                    if (!cfg.isHost && connected && participant && participantIsStaff(participant) && !anyoneSharing()) {
                        enterBoardFullStage('auto');
                    }
                }
                if (msg.t === 'wb-laser') {
                    var boardLaser = ensureBoard();
                    if (boardLaser) boardLaser.receiveLaser(msg, participant && (participant.identity || participant.sid));
                }
                if (msg.t === 'wb-spotlight') {
                    var boardSpot = ensureBoard();
                    if (boardSpot && boardSpot.receiveSpotlight) boardSpot.receiveSpotlight(msg);
                }
                if (msg.t === 'wb-clear') receiveBoardClear();
                if (msg.t === 'wb-allow') applyCanDraw(!!msg.allow);
                if (msg.t === 'pdf_open' || msg.t === 'pdf_page' || msg.t === 'pdf_close') {
                    ensureBoard();
                    if (window.CKPdf && typeof window.CKPdf.handleDataMessage === 'function') {
                        window.CKPdf.handleDataMessage(msg);
                    }
                    if (msg.t === 'pdf_open' && !cfg.isHost && connected && participant && participantIsStaff(participant) && !anyoneSharing()) {
                        enterBoardFullStage('auto');
                    }
                }
                if (msg.t === 'rec') setRecordingUi(!!msg.on);
                if (msg.t === 'mic' && !cfg.isHost) {
                    var micBlocked = !!msg.blocked;
                    micBlockState[localIdentity()] = micBlocked;
                    var wasMicBlocked = localMicBlocked;
                    localMicBlocked = micBlocked;
                    if (micBlocked) {
                        var stopMic = function () {
                            if (room && room.localParticipant) {
                                room.localParticipant.setMicrophoneEnabled(false).catch(function () {});
                            }
                        };
                        stopMic();
                        setTimeout(stopMic, 250);
                        setMicUi(false, true);
                        if (!wasMicBlocked) toast('The teacher muted your microphone.');
                    } else {
                        setMicUi(!!(room && room.localParticipant && room.localParticipant.isMicrophoneEnabled), false);
                        if (wasMicBlocked) toast('The teacher allowed your microphone. Tap Mic to turn it on.');
                    }
                }
                if (msg.t === 'cam' && !cfg.isHost) {
                    var blocked = !!msg.blocked;
                    var force = !!msg.force;
                    camBlockState[(room && room.localParticipant && room.localParticipant.identity) || cfg.identity || ''] = blocked;
                    if (force) {
                        applyForcedCamera(blocked);
                    } else if (blocked === localCamBlocked) {
                        setCamUi(!blocked && !!(room && room.localParticipant.isCameraEnabled), blocked);
                    } else {
                        localCamBlocked = blocked;
                        if (blocked) {
                            if (room) room.localParticipant.setCameraEnabled(false).catch(function () {});
                            setCamUi(false, true);
                            toast('The teacher turned off your camera.');
                        } else {
                            forceEnableLocalCamera(0);
                            toast('The teacher allowed your camera.');
                        }
                    }
                }
                if (msg.t === 'share' && !cfg.isHost) {
                    var shareOk = !!msg.allowed;
                    shareAllowState[(room && room.localParticipant && room.localParticipant.identity) || cfg.identity || ''] = shareOk;
                    if (shareOk === localShareAllowed) {
                        setShareUi(shareOk, !!(room && room.localParticipant && room.localParticipant.isScreenShareEnabled));
                        return;
                    }
                    localShareAllowed = shareOk;
                    if (!shareOk) {
                        stopLocalShare();
                        setShareUi(false, false);
                        toast('The teacher stopped your screen sharing.');
                    } else {
                        setShareUi(true, false);
                        toast('The teacher allowed you to share your screen.');
                    }
                }
            } catch (e) {}
        });
        r.on(LK.RoomEvent.Disconnected, function () {
            connected = false;
            releaseSharePause();
            syncSharePauseUi();
            toast('You left the class. You can rejoin if it is still live.');
            showLobby(true);
            renderLobbyActions('live');
        });
        r.on(LK.RoomEvent.Reconnecting, function () { toast('Your connection is unstable. Reconnecting…'); });
        r.on(LK.RoomEvent.Reconnected, function () {
            toast('You are back in class.');
            reapplySharePause();
            loadBoard();
            if (cfg.isHost && hostBoardFocus) publishBoardFocus(true);
            if (!cfg.isHost && boardFocusHint && !anyoneSharing()) enterBoardFullStage('auto');
        });
        r.on(LK.RoomEvent.MediaDevicesError, function (e) {
            if (pendingCamForce === 'on' && isBrowserCameraDenied(e)) {
                pendingCamForce = '';
                toastCamForceOnce('Allow camera in the browser once.');
                return;
            }
            toast(mediaError(e));
        });
    }

    function startHeartbeat() {
        clearInterval(hbTimer);
        var ping = function () {
            if (!connected) return;
            api('heartbeat.php', { action: 'ping', connected: true }).then(function (data) {
                if (data && data.device_revoked) {
                    resetJoinUi();
                    if ($('ckLobbyMsg')) $('ckLobbyMsg').textContent = data.error || 'This device is not allowed to stay in class.';
                    toast(data.error || 'This device is not allowed to stay in class.');
                }
            });
        };
        ping();
        hbTimer = setInterval(ping, 20000);
        if (!pagehideBound) {
            pagehideBound = true;
            window.addEventListener('pagehide', function () {
                try {
                    navigator.sendBeacon(
                        cfg.apiBase + 'heartbeat.php',
                        new Blob([JSON.stringify({ action: 'leave', lesson: cfg.lessonId, csrf_token: cfg.csrf })], { type: 'application/json' })
                    );
                } catch (e) {}
            });
        }
    }

    function formatChatTime(created) {
        var d;
        if (!created) {
            d = new Date();
        } else {
            d = new Date(String(created).replace(' ', 'T'));
        }
        if (isNaN(d.getTime())) return '';
        return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function isPrivateMsg(msg) {
        return !!(msg && (msg.is_private === 1 || msg.is_private === true || msg.t === 'chat-private-open'));
    }

    function canSeeChat(msg) {
        if (!msg) return false;
        if (!isPrivateMsg(msg)) return true;
        var me = parseInt(cfg.userId, 10) || 0;
        if (me < 1) return false;
        var sender = parseInt(msg.user_id, 10) || 0;
        var recip = parseInt(msg.recipient_user_id, 10) || 0;
        return me === sender || me === recip;
    }

    function hostUserId() {
        return parseInt(cfg.hostUserId, 10) || 0;
    }

    function hostIdentity() {
        if (cfg.hostIdentity) return cfg.hostIdentity;
        var hid = hostUserId();
        if (hid > 0) return 'u' + hid;
        return teacherIdentity();
    }

    function privateOtherId(msg) {
        var me = parseInt(cfg.userId, 10) || 0;
        var sender = parseInt(msg.user_id, 10) || 0;
        var recip = parseInt(msg.recipient_user_id, 10) || 0;
        if (sender === me) return recip;
        return sender;
    }

    function privateDestinationIdentities(msg) {
        if (!isPrivateMsg(msg)) return null;
        var me = parseInt(cfg.userId, 10) || 0;
        var sender = parseInt(msg.user_id, 10) || 0;
        var recip = parseInt(msg.recipient_user_id, 10) || 0;
        var other = sender === me ? recip : sender;
        if (!other) return [];
        return ['u' + other];
    }

    function addPrivatePeer(id, name, identity) {
        id = parseInt(id, 10) || 0;
        if (id < 1 || id === (parseInt(cfg.userId, 10) || 0)) return;
        var prev = privatePeers[id] || {};
        privatePeers[id] = {
            id: id,
            name: name || prev.name || ('Student'),
            identity: identity || prev.identity || ('u' + id)
        };
        renderPrivateSelect();
    }

    function renderPrivateSelect() {
        var sel = $('ckPrivatePeerSelect');
        if (!sel) return;
        var current = String(selectedPrivatePeer || '');
        var html = '<option value="">Choose a student</option>';
        Object.keys(privatePeers).forEach(function (key) {
            var p = privatePeers[key];
            html += '<option value="' + p.id + '"' + (String(p.id) === current ? ' selected' : '') + '>' +
                escapeHtml(p.name) + '</option>';
        });
        sel.innerHTML = html;
        if (current && privatePeers[current]) sel.value = current;
    }

    function privateRecipientId() {
        if (cfg.isHost) return parseInt(selectedPrivatePeer, 10) || 0;
        var hid = hostUserId();
        if (hid > 0) return hid;
        return identityUser(hostIdentity());
    }

    function setPrivateUnread(on) {
        privateUnread = !!on;
        var tab = $('ckChatTabPrivate');
        if (!tab) return;
        tab.classList.toggle('has-unread', privateUnread && chatMode !== 'private');
    }

    function showPrivateTab() {
        privateTabUnlocked = true;
        var tab = $('ckChatTabPrivate');
        if (tab) tab.hidden = false;
        var withEl = $('ckPrivateWith');
        if (!cfg.isHost && withEl) {
            withEl.hidden = false;
            withEl.textContent = 'Private with ' + (cfg.teacher || 'the teacher');
        }
    }

    function setChatMode(mode) {
        chatMode = mode === 'private' ? 'private' : 'class';
        if (chatMode === 'private') {
            showPrivateTab();
            setPrivateUnread(false);
        }
        var classTab = $('ckChatTabClass');
        var privTab = $('ckChatTabPrivate');
        if (classTab) classTab.classList.toggle('is-on', chatMode === 'class');
        if (privTab) privTab.classList.toggle('is-on', chatMode === 'private');
        var classLog = $('ckChatLog');
        var privLog = $('ckPrivateLog');
        if (classLog) classLog.hidden = chatMode !== 'class';
        if (privLog) privLog.hidden = chatMode !== 'private';
        var bar = $('ckPrivateBar');
        if (bar) bar.hidden = chatMode !== 'private';
        var announce = $('ckAnnounceForm');
        if (announce) announce.hidden = chatMode !== 'class';
        var input = $('ckChatInput');
        if (input) {
            input.placeholder = chatMode === 'private' ? 'Message privately' : 'Message the class';
        }
        filterPrivateLog();
    }

    function filterPrivateLog() {
        var log = $('ckPrivateLog');
        if (!log) return;
        var peer = cfg.isHost ? (parseInt(selectedPrivatePeer, 10) || 0) : 0;
        log.querySelectorAll('.ck-msg').forEach(function (div) {
            if (!cfg.isHost) {
                div.hidden = false;
                return;
            }
            var other = parseInt(div.getAttribute('data-peer'), 10) || 0;
            div.hidden = !peer || other !== peer;
        });
        log.scrollTop = log.scrollHeight;
    }

    function openPrivateThread(peer, notify) {
        var id = parseInt(peer && peer.userId, 10) || 0;
        if (id < 1) {
            toast('Could not open private chat.');
            return;
        }
        addPrivatePeer(id, peer.name, peer.identity);
        selectedPrivatePeer = id;
        renderPrivateSelect();
        var sel = $('ckPrivatePeerSelect');
        if (sel) sel.value = String(id);
        showPrivateTab();
        setChatOpen(true);
        setChatMode('private');
        if (notify && cfg.isHost && peer.identity) {
            publishChatPacket({
                t: 'chat-private-open',
                is_private: 1,
                user_id: parseInt(cfg.userId, 10) || 0,
                recipient_user_id: id,
                name: cfg.displayName
            }, [peer.identity]);
        }
    }

    function acceptPrivateOpen(msg) {
        if (cfg.isHost) return;
        var me = parseInt(cfg.userId, 10) || 0;
        var recip = parseInt(msg.recipient_user_id, 10) || 0;
        if (recip && me && recip !== me) return;
        var hostId = parseInt(msg.user_id, 10) || parseInt(msg.host_user_id, 10) || 0;
        if (hostId > 0) {
            cfg.hostUserId = hostId;
            cfg.hostIdentity = 'u' + hostId;
        }
        showPrivateTab();
        setPrivateUnread(chatMode !== 'private');
        if (chatHydrated) peekStudentChat();
    }

    function applyChatMeta(data) {
        if (!data) return;
        if (data.host_user_id) {
            cfg.hostUserId = parseInt(data.host_user_id, 10) || cfg.hostUserId;
        }
        if (data.host_identity) cfg.hostIdentity = data.host_identity;
        (data.threads || []).forEach(function (t) {
            addPrivatePeer(t.user_id, t.display_name, t.identity);
        });
        if (data.private_open || (data.threads && data.threads.length)) {
            showPrivateTab();
        }
        if (!cfg.isHost && hostUserId() > 0) {
            var withEl = $('ckPrivateWith');
            if (withEl && privateTabUnlocked) {
                withEl.hidden = false;
                withEl.textContent = 'Private with ' + (cfg.teacher || 'the teacher');
            }
        }
    }

    function appendChat(msg) {
        if (!msg) return;
        if (isPrivateMsg(msg) && !canSeeChat(msg)) return;
        if (msg.id && msg.id <= lastChatId) return;
        if (msg.id) lastChatId = Math.max(lastChatId, parseInt(msg.id, 10) || 0);
        var privateMsg = isPrivateMsg(msg);
        if (privateMsg) {
            showPrivateTab();
            var other = privateOtherId(msg);
            var otherName = msg.display_name || msg.name || 'Student';
            if (other && other !== (parseInt(cfg.userId, 10) || 0) && (parseInt(msg.user_id, 10) || 0) !== (parseInt(cfg.userId, 10) || 0)) {
                addPrivatePeer(other, otherName, 'u' + other);
            } else if (other) {
                addPrivatePeer(other, (privatePeers[other] && privatePeers[other].name) || 'Student', 'u' + other);
            }
            if (chatMode !== 'private') setPrivateUnread(true);
        }
        var log = $(privateMsg ? 'ckPrivateLog' : 'ckChatLog');
        if (!log) return;
        var div = document.createElement('div');
        div.className = 'ck-msg' + (msg.is_announcement ? ' is-announcement' : '') + (privateMsg ? ' is-private' : '');
        if (privateMsg) div.setAttribute('data-peer', String(privateOtherId(msg) || ''));
        var when = formatChatTime(msg.created_at);
        div.innerHTML = '<b>' + escapeHtml(msg.display_name || msg.name || 'Someone') +
            (privateMsg ? ' <span class="ck-msg-lock">Private</span>' : '') +
            (when ? ' <time>' + escapeHtml(when) + '</time>' : '') + '</b>' + escapeHtml(msg.body);
        log.appendChild(div);
        if (privateMsg) filterPrivateLog();
        else log.scrollTop = log.scrollHeight;
        if (cfg.isHost) {
            chatHistoryCache.push(msg);
            if (chatHistoryCache.length > 200) chatHistoryCache.shift();
            bridgeBroadcast({ t: 'chat_message', message: msg });
        }
        if (chatHydrated) {
            var mine = (parseInt(msg.user_id, 10) || 0) === (parseInt(cfg.userId, 10) || 0);
            if (!mine && !railIsOpen('chat')) {
                updateChatUnread(chatUnreadCount + 1);
            }
            if (!cfg.isHost) {
                if (!mine) peekStudentChat();
                else bumpStudentChatIdle();
            }
        }
    }

    function loadChat() {
        api('chat.php?after=' + lastChatId).then(function (data) {
            applyChatMeta(data);
            (data.messages || []).forEach(appendChat);
        }).catch(function () {}).then(function () {
            chatHydrated = true;
        });
        if (loadChat._t) return;
        loadChat._t = setInterval(function () {
            if (!connected || document.hidden) return;
            api('chat.php?after=' + lastChatId).then(function (data) {
                applyChatMeta(data);
                (data.messages || []).forEach(appendChat);
            });
        }, 4000);
    }

    function sendChat(ev) {
        ev.preventDefault();
        bumpStudentChatIdle();
        var input = $('ckChatInput');
        var text = (input.value || '').trim();
        if (!text) return;
        var privateMode = chatMode === 'private';
        var recipientId = 0;
        if (privateMode) {
            recipientId = privateRecipientId();
            if (!recipientId) {
                toast(cfg.isHost ? 'Choose a student to message privately.' : 'Private chat with the teacher is not available yet.');
                return;
            }
        }
        input.value = '';
        var payload = { body: text };
        if (privateMode) {
            payload.private = true;
            payload.recipient_user_id = recipientId;
        }
        api('chat.php', payload).then(function (data) {
            if (!data.ok || !data.message) {
                toast(data.error || 'Message was not sent.');
                return;
            }
            appendChat(data.message);
            var dest = privateMode ? privateDestinationIdentities(data.message) : null;
            publishChatPacket({
                t: 'chat',
                id: data.message.id,
                body: data.message.body,
                name: data.message.display_name,
                display_name: data.message.display_name,
                user_id: data.message.user_id,
                is_announcement: data.message.is_announcement,
                is_private: data.message.is_private,
                recipient_user_id: data.message.recipient_user_id,
                created_at: data.message.created_at
            }, dest);
        });
    }

    function sendAnnouncement(ev) {
        ev.preventDefault();
        var input = $('ckAnnounceInput');
        var text = (input.value || '').trim();
        if (!text) return;
        input.value = '';
        api('chat.php', { body: text, announcement: true }).then(function (data) {
            if (!data.ok || !data.message) {
                toast(data.error || 'Announcement was not sent.');
                return;
            }
            appendChat(data.message);
            publishChatPacket({
                t: 'chat',
                id: data.message.id,
                body: data.message.body,
                name: data.message.display_name,
                display_name: data.message.display_name,
                user_id: data.message.user_id,
                is_announcement: 1,
                is_private: 0,
                created_at: data.message.created_at
            }, null);
        });
    }

    function boardPanelOpen() {
        var panel = $('ckBoardPanel');
        return !!(panel && panel.classList.contains('is-on'));
    }

    function setRailPanel(panel) {
        var map = { board: 'ckBoardPanel', materials: 'ckMaterialsPanel', info: 'ckInfoPanel' };
        document.querySelectorAll('.ck-rail-tabs button').forEach(function (b) {
            b.classList.toggle('is-on', b.getAttribute('data-panel') === panel);
        });
        ['ckBoardPanel', 'ckMaterialsPanel', 'ckInfoPanel'].forEach(function (id) {
            var el = $(id);
            if (el) el.classList.toggle('is-on', id === map[panel]);
        });
        if (panel === 'board') {
            replayBoardLog();
            if (cfg.isHost && !anyoneSharing()) {
                if (stageFocus !== 'board') showHostBoardOnStage();
                else refreshBoardAfterMove();
            }
        } else if (cfg.isHost && stageFocus === 'board') {
            leaveStageFocus(true);
        }
        if (cfg.isHost) setHostBoardFocus(panel === 'board');
    }

    function showHostBoardOnStage() {
        if (!cfg.isHost) return;
        if (!document.body.classList.contains('ck-in-room')) return;
        if (anyoneSharing()) return;
        stageFocus = 'board';
        stageFocusHeld = true;
        moveBoardToStage();
        document.body.classList.add('ck-focus-board');
        document.body.classList.add('ck-filmstrip');
        document.body.classList.remove('ck-full-stage', 'ck-focus-share');
        var exitBtn = $('ckStageExit');
        if (exitBtn) exitBtn.hidden = true;
        layoutStage();
        refreshBoardAfterMove();
    }

    function maybeShowBoardForStudent(force) {
        if (cfg.isHost) return;
        if (boardAutoOpened && !force) return;
        boardAutoOpened = true;
        setRailPanel('board');
        // The board lives inside the people rail, which is hidden for students.
        // Do not depend on the separate LiveKit board-focus packet: strokes are
        // also delivered by the database polling fallback and must be visible.
        if (!anyoneSharing()) enterBoardFullStage('auto');
    }

    function replayBoardLog() {
        var b = ensureBoard();
        if (b) b.replay();
    }

    function receiveBoardStroke(stroke, id) {
        var b = ensureBoard();
        if (b) b.receiveStroke(stroke, id);
    }

    function receiveBoardClear() {
        var b = ensureBoard();
        if (b) b.receiveClear();
        lastStrokeId = 0;
    }

    function fetchBoard(full) {
        var b = ensureBoard();
        if (b) b.fetch(full);
    }

    function loadBoard() {
        var b = ensureBoard();
        if (b) b.load();
        else applyCanDraw(cfg.studentsCanDraw);
    }

    function renderHands(hands) {
        var ol = $('ckHands');
        var empty = $('ckHandsEmpty');
        if (!ol) return;
        hands = hands || [];
        currentHandsList = hands;
        if (cfg.isHost) {
            bridgeBroadcast({ t: 'hands_updated', hands: hands });
        }
        ol.innerHTML = '';
        if (empty) empty.hidden = hands.length > 0;
        hands.forEach(function (h, i) {
            var li = document.createElement('li');
            var n = document.createElement('span');
            n.className = 'ck-hand-n';
            n.textContent = String(i + 1);
            var name = document.createElement('span');
            name.textContent = h.display_name || 'Student';
            li.appendChild(n);
            li.appendChild(name);
            if (cfg.isHost) {
                li.appendChild(miniBtn('Dismiss', function () {
                    lowerRaisedHand(h.user_id);
                }));
            }
            ol.appendChild(li);
        });
        if (cfg.isHost) renderHandPopup(hands);
    }

    function handSignalKey(hands) {
        return (hands || []).map(function (h) { return String(h.user_id); }).join(',');
    }

    function applyHandSignal(msg, participant) {
        var id = String(msg.userId || '');
        if (!cfg.isHost) {
            if (msg.cleared && id === String(cfg.userId)) setHandUi(false);
            return;
        }
        if (!id) return;
        var next = (currentHandsList || []).filter(function (h) { return String(h.user_id) !== id; });
        if (msg.up) {
            next.push({
                user_id: msg.userId,
                display_name: msg.name || (participant && (participant.name || participant.identity)) || 'Student'
            });
        }
        lastHandKey = handSignalKey(next);
        renderHands(next);
    }

    function lowerRaisedHand(userId) {
        api('control.php', { action: 'lower_hand', user_id: userId }).then(function (res) {
            if (!res.ok) {
                toast(res.error || 'Could not lower that hand.');
                return;
            }
            publishData({ t: 'hand', up: false, cleared: true, userId: userId });
            var hands = res.hands || [];
            lastHandKey = handSignalKey(hands);
            renderHands(hands);
        });
    }

    function renderHandPopup(hands) {
        if (!cfg.isHost) return;
        var stack = $('ckHandStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'ckHandStack';
            stack.className = 'ck-hand-stack';
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        hands = hands || [];
        stack.innerHTML = '';
        if (!hands.length) {
            stack.hidden = true;
            return;
        }
        stack.hidden = false;
        var card = document.createElement('div');
        card.className = 'ck-hand-card';
        hands.forEach(function (h) {
            var row = document.createElement('div');
            row.className = 'ck-hand-row';
            var icon = document.createElement('span');
            icon.className = 'ck-hand-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = '<i class="bi bi-hand-index-thumb"></i>';
            var text = document.createElement('div');
            text.className = 'ck-hand-text';
            var name = document.createElement('strong');
            name.textContent = h.display_name || 'Student';
            var label = document.createElement('span');
            label.textContent = 'Raised Hand';
            text.appendChild(name);
            text.appendChild(label);
            var clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'ck-hand-clear';
            clear.textContent = 'Clear';
            clear.addEventListener('click', function () {
                lowerRaisedHand(h.user_id);
            });
            row.appendChild(icon);
            row.appendChild(text);
            row.appendChild(clear);
            card.appendChild(row);
        });
        stack.appendChild(card);
    }

    function setLockedUi(locked) {
        cfg.locked = !!locked;
        var btn = $('ckLock');
        if (btn) {
            btn.setAttribute('aria-pressed', locked ? 'true' : 'false');
            btn.textContent = locked ? 'Unlock class' : 'Lock class';
        }
        var mobLock = $('ckMobileLockLabel');
        if (mobLock) mobLock.textContent = locked ? 'Unlock Class' : 'Lock Class';
        var mobLockBtn = $('ckMobileLockBtn');
        if (mobLockBtn) mobLockBtn.classList.toggle('is-on', !!locked);
    }

    function setWaitingRoomUi(on) {
        cfg.waitingRoom = !!on;
        var btn = $('ckWaitingRoom');
        if (btn) {
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.textContent = on ? 'Turn off waiting room' : 'Turn on waiting room';
        }
        var mobWait = $('ckMobileWaitLabel');
        if (mobWait) mobWait.textContent = on ? 'Turn Off Waiting Room' : 'Turn On Waiting Room';
        var mobWaitBtn = $('ckMobileWaitBtn');
        if (mobWaitBtn) mobWaitBtn.classList.toggle('is-on', !!on);
        var wrap = $('ckWaitingWrap');
        if (wrap && !on) {
            var list = $('ckWaiting');
            var empty = !list || !list.children.length;
            wrap.hidden = empty;
        } else if (wrap && on) {
            wrap.hidden = false;
        }
    }

    function renderWaiting(rows) {
        var ol = $('ckWaiting');
        var empty = $('ckWaitingEmpty');
        var wrap = $('ckWaitingWrap');
        if (!ol) return;
        rows = rows || [];
        ol.innerHTML = '';
        if (wrap) wrap.hidden = !cfg.waitingRoom && rows.length === 0;
        if (empty) empty.hidden = rows.length > 0;
        rows.forEach(function (w) {
            var li = document.createElement('li');
            if ((w.waiting_status || '') === 'denied') li.className = 'ck-wait-deny';
            var name = document.createElement('span');
            name.textContent = w.display_name || 'Student';
            li.appendChild(name);
            if ((w.waiting_status || '') === 'denied') {
                var tag = document.createElement('span');
                tag.textContent = 'Denied';
                li.appendChild(tag);
            }
            if (cfg.isHost) {
                li.appendChild(miniBtn('Admit', function () {
                    api('control.php', { action: 'admit', user_id: w.user_id }).then(function (res) {
                        if (!res.ok) {
                            toast(res.error || 'Could not admit that student.');
                            return;
                        }
                        renderWaiting(res.waiting || []);
                        toast((w.display_name || 'Student') + ' can join now.');
                    });
                }));
                if ((w.waiting_status || '') !== 'denied') {
                    li.appendChild(miniBtn('Deny', function () {
                        api('control.php', { action: 'deny', user_id: w.user_id }).then(function (res) {
                            if (!res.ok) {
                                toast(res.error || 'Could not deny that student.');
                                return;
                            }
                            renderWaiting(res.waiting || []);
                            toast((w.display_name || 'Student') + ' was not let in.');
                        });
                    }));
                }
            }
            ol.appendChild(li);
        });
    }

    function applyStartedAt(iso) {
        if (!iso) return;
        var t = Date.parse(iso);
        if (isNaN(t)) return;
        startedAtMs = t;
        tickElapsed();
        if (!elapsedTimer) {
            elapsedTimer = setInterval(tickElapsed, 1000);
        }
    }

    function tickElapsed() {
        var el = $('ckElapsed');
        if (!el || !startedAtMs) return;
        var sec = Math.max(0, Math.floor((Date.now() - startedAtMs) / 1000));
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        el.textContent = (h > 0 ? h + ':' : '') + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        el.hidden = false;
    }

    function setQos(quality) {
        var el = $('ckQos');
        if (!el) return;
        var s = String(quality || '').toLowerCase();
        var label = '';
        var key = '';
        if (s === 'excellent') { label = 'Excellent'; key = 'excellent'; }
        else if (s === 'good') { label = 'Good'; key = 'good'; }
        else if (s === 'poor' || s === 'lost') { label = 'Poor'; key = 'poor'; }
        if (!label) {
            el.hidden = true;
            return;
        }
        el.hidden = false;
        el.textContent = label;
        el.setAttribute('data-qos', key);
    }

    function fillDeviceSelect(sel, devices, currentId) {
        if (!sel) return;
        sel.innerHTML = '';
        (devices || []).forEach(function (d, i) {
            var opt = document.createElement('option');
            opt.value = d.deviceId;
            opt.textContent = d.label || ('Device ' + (i + 1));
            if (currentId && d.deviceId === currentId) opt.selected = true;
            sel.appendChild(opt);
        });
        if (!sel.options.length) {
            var empty = document.createElement('option');
            empty.textContent = 'None found';
            empty.value = '';
            sel.appendChild(empty);
        }
    }

    function currentDeviceId(kind) {
        if (room && typeof room.getActiveDevice === 'function') {
            try { return room.getActiveDevice(kind) || ''; } catch (e) {}
        }
        return '';
    }

    function refreshDevices() {
        if (!LK || !LK.Room || typeof LK.Room.getLocalDevices !== 'function') {
            return Promise.resolve();
        }
        return Promise.all([
            LK.Room.getLocalDevices('audioinput', true).catch(function (e) { toast(mediaError(e)); return []; }),
            LK.Room.getLocalDevices('videoinput', true).catch(function (e) { toast(mediaError(e)); return []; }),
            LK.Room.getLocalDevices('audiooutput', false).catch(function () { return []; })
        ]).then(function (sets) {
            fillDeviceSelect($('ckMicSelect'), sets[0], currentDeviceId('audioinput'));
            fillDeviceSelect($('ckCamSelect'), sets[1], currentDeviceId('videoinput'));
            fillDeviceSelect($('ckSpeakerSelect'), sets[2], audioSinkId || currentDeviceId('audiooutput'));
        });
    }

    function attachSettingsPreview() {
        var video = $('ckSettingsPreview');
        if (!video) return;
        if (room && room.localParticipant && LK.Track && LK.Track.Source) {
            var pub = room.localParticipant.getTrackPublication(LK.Track.Source.Camera);
            if (pub && pub.track) {
                settingsPreviewTrack = pub.track;
                pub.track.attach(video);
                return;
            }
        }
        if (previewStream) {
            video.srcObject = previewStream;
        }
    }

    function closeSettings() {
        var modal = $('ckSettingsModal');
        var video = $('ckSettingsPreview');
        if (settingsPreviewTrack && video) {
            try { settingsPreviewTrack.detach(video); } catch (e) {}
        }
        settingsPreviewTrack = null;
        if (video && video.srcObject && video.srcObject !== previewStream) {
            try {
                video.srcObject.getTracks().forEach(function (t) { t.stop(); });
            } catch (e) {}
            video.srcObject = null;
        } else if (video && !room) {
            video.srcObject = previewStream || null;
        }
        if (modal) modal.hidden = true;
    }

    function openSettings() {
        var modal = $('ckSettingsModal');
        if (!modal) return;
        modal.hidden = false;
        refreshDevices();
        attachSettingsPreview();
    }

    function switchKind(kind, deviceId) {
        if (!deviceId) return;
        if (kind === 'audiooutput') {
            audioSinkId = deviceId;
            applyAudioSink();
            if (room && typeof room.switchActiveDevice === 'function') {
                room.switchActiveDevice(kind, deviceId).catch(function () {});
            }
            return;
        }
        if (room && typeof room.switchActiveDevice === 'function') {
            room.switchActiveDevice(kind, deviceId).then(function () {
                if (kind === 'videoinput') attachSettingsPreview();
            }).catch(function (e) {
                toast(mediaError(e));
            });
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
        var constraints = kind === 'videoinput'
            ? { video: { deviceId: { exact: deviceId }, width: 640, height: 360 } }
            : { audio: { deviceId: { exact: deviceId } } };
        navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
            if (previewStream) {
                previewStream.getTracks().forEach(function (t) {
                    if (t.kind === (kind === 'videoinput' ? 'video' : 'audio')) t.stop();
                });
                stream.getTracks().forEach(function (t) { previewStream.addTrack(t); });
            } else {
                previewStream = stream;
            }
            var lobby = $('ckPreview');
            if (lobby) lobby.srcObject = previewStream;
            var preview = $('ckSettingsPreview');
            if (preview) preview.srcObject = previewStream;
        }).catch(function (e) {
            toast(mediaError(e));
        });
    }

    function pollStatus() {
        if (document.hidden) return;
        api('status.php').then(function (data) {
            if (!data.ok) return;
            setStatus(data.status, data.status === 'live' ? 'Live now' : (data.message || data.status));
            if ($('ckLobbyMsg') && !connected) $('ckLobbyMsg').textContent = data.message;
            var access = {
                code: data.code || cfg.accessCode,
                wait_kind: data.wait_kind || '',
                waiting_room: data.waiting_room
            };
            applyAccessPreview(access.code, access.wait_kind);
            if (!connected) renderLobbyActions(data.status, access);
            applyStartedAt(data.startedAt || data.started_at);
            if (typeof data.locked !== 'undefined') setLockedUi(!!data.locked);
            if (typeof data.waiting_room !== 'undefined') setWaitingRoomUi(!!data.waiting_room);
            if (typeof data.recording !== 'undefined') setRecordingUi(!!data.recording);
            if (typeof data.students_can_draw !== 'undefined') applyCanDraw(!!data.students_can_draw);
            if (cfg.isHost) renderWaiting(data.waiting || []);
            var kind = data.wait_kind || '';
            if (!cfg.isHost && !connected && data.status === 'live' && data.code === 'ALLOW' && lastWaitKind === 'lobby') {
                connect();
            }
            if (data.code !== 'DENY' && data.code !== 'PAY') {
                lastWaitKind = kind;
            }
            cfg.accessCode = data.code || cfg.accessCode;
            cfg.waitKind = kind;
            var hands = data.hands || [];
            renderHands(hands);
            if (!cfg.isHost) {
                var mineRaised = hands.some(function (h) { return String(h.user_id) === String(cfg.userId); });
                if (mineRaised !== handUp) setHandUi(mineRaised);
            }
            var key = hands.map(function (h) { return String(h.user_id); }).join(',');
            if (cfg.isHost && connected && key && key !== lastHandKey) {
                var prev = ',' + lastHandKey + ',';
                var added = hands.filter(function (h) {
                    return lastHandKey === '' ? true : prev.indexOf(',' + h.user_id + ',') < 0;
                });
                if (added[0] && lastHandKey !== '') {
                    toast(added[0].display_name + ' wants to speak');
                }
            }
            lastHandKey = key;
        });
    }

    function setHandUi(up) {
        handUp = !!up;
        var btn = $('ckHand');
        if (btn) btn.classList.toggle('is-on', handUp);
        var mobBtn = $('ckMobileHandBtn');
        if (mobBtn) mobBtn.classList.toggle('is-on', handUp);
        var mobLabel = $('ckMobileHandLabel');
        if (mobLabel) mobLabel.textContent = handUp ? 'Lower Hand' : 'Raise Hand';
        var dockHand = $('ckMobBtnHand');
        if (dockHand) {
            dockHand.classList.toggle('is-on', handUp);
            dockHand.setAttribute('aria-pressed', handUp ? 'true' : 'false');
        }
        var dockHandLabel = $('ckMobHandLabel');
        if (dockHandLabel) dockHandLabel.textContent = handUp ? 'Hand Raised' : 'Raise Hand';
    }

    function openMobileMoreSheet() {
        var sheet = $('ckMobileMoreSheet');
        var backdrop = $('ckMobileMoreBackdrop');
        if (backdrop) backdrop.hidden = false;
        if (sheet) {
            sheet.classList.add('is-open');
        }
        document.body.classList.add('ck-sheet-open');
        if (typeof syncMobileStudentAudioVideoUi === 'function') {
            syncMobileStudentAudioVideoUi();
        }
    }

    function closeMobileMoreSheet() {
        var sheet = $('ckMobileMoreSheet');
        var backdrop = $('ckMobileMoreBackdrop');
        if (sheet) {
            sheet.classList.remove('is-open');
        }
        if (backdrop) backdrop.hidden = true;
        document.body.classList.remove('ck-sheet-open');
    }

    function initMobileClassroom() {
        if (initMobileClassroom.done) return;
        initMobileClassroom.done = true;

        // More sheet open / close
        var headerMenuBtn = $('ckMobileHeaderMenuBtn');
        if (headerMenuBtn) headerMenuBtn.addEventListener('click', openMobileMoreSheet);

        var dockMoreBtn = $('ckMobileMoreBtn');
        if (dockMoreBtn) dockMoreBtn.addEventListener('click', openMobileMoreSheet);

        var sheetCloseBtn = $('ckMobileMoreCloseBtn');
        if (sheetCloseBtn) sheetCloseBtn.addEventListener('click', closeMobileMoreSheet);

        var backdrop = $('ckMobileMoreBackdrop');
        if (backdrop) {
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) closeMobileMoreSheet();
            });
        }

        // Mobile Dock: Mic
        var mobMic = $('ckMobileMic');
        if (mobMic) {
            mobMic.addEventListener('click', function () {
                var btn = $('ckMic');
                if (btn) btn.click();
            });
        }

        // Mobile Dock: Cam
        var mobCam = $('ckMobileCam');
        if (mobCam) {
            mobCam.addEventListener('click', function () {
                var btn = $('ckCam');
                if (btn) btn.click();
            });
        }

        // Student Dedicated Mobile Bottom Dock
        var mobBtnBoard = $('ckMobBtnBoard');
        if (mobBtnBoard) {
            mobBtnBoard.addEventListener('click', function () {
                setStudentDisplayMode('board', true);
            });
        }

        var mobBtnShare = $('ckMobBtnShare');
        if (mobBtnShare) {
            mobBtnShare.addEventListener('click', function () {
                setStudentDisplayMode('share', true);
            });
        }

        var mobBtnTeacher = $('ckMobBtnTeacher');
        if (mobBtnTeacher) {
            mobBtnTeacher.addEventListener('click', function () {
                setStudentDisplayMode('teacher', true);
            });
        }

        var mobBtnHand = $('ckMobBtnHand');
        if (mobBtnHand) {
            mobBtnHand.addEventListener('click', function () {
                var btn = $('ckHand');
                if (btn) btn.click();
            });
        }

        var mobBtnChat = $('ckMobBtnChat');
        if (mobBtnChat) {
            mobBtnChat.addEventListener('click', function () {
                closeMobileMoreSheet();
                var side = $('ckChatRail');
                var isOpen = side && !side.hidden && side.classList.contains('is-open');
                setChatOpen(!isOpen, true);
            });
        }

        var mobBtnAudio = $('ckMobBtnAudio');
        if (mobBtnAudio) mobBtnAudio.addEventListener('click', toggleLocalMic);

        var mobBtnMore = $('ckMobBtnMore');
        if (mobBtnMore) {
            mobBtnMore.addEventListener('click', openMobileMoreSheet);
        }

        // Tap or click on floating Teacher PiP in Board mode to maximize teacher view
        var heroEl = $('ckHero');
        if (heroEl) {
            heroEl.addEventListener('click', function (e) {
                if (!cfg.isHost && studentDisplayMode === 'board') {
                    e.stopPropagation();
                    setStudentDisplayMode('teacher', true);
                }
            });
        }

        // Pre-join Lobby Media Controls (Microphone & Camera Toggles)
        var lobbyMicBtn = $('ckLobbyMicBtn');
        var lobbyCamBtn = $('ckLobbyCamBtn');
        if (lobbyMicBtn) {
            lobbyMicBtn.addEventListener('click', function () {
                var on = false;
                if (previewStream) {
                    previewStream.getAudioTracks().forEach(function (t) {
                        t.enabled = !t.enabled;
                        on = t.enabled;
                    });
                }
                lobbyMicBtn.classList.toggle('is-on', on);
                var icon = $('ckLobbyMicIcon');
                var label = $('ckLobbyMicLabel');
                if (icon) icon.className = on ? 'bi bi-mic-fill' : 'bi bi-mic-mute-fill text-danger';
                if (label) label.textContent = on ? 'Microphone' : 'Muted';
                var micBtn = $('ckMic');
                if (micBtn) {
                    micBtn.classList.toggle('is-on', on);
                    micBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                }
            });
        }
        if (lobbyCamBtn) {
            lobbyCamBtn.addEventListener('click', function () {
                var on = false;
                if (previewStream) {
                    previewStream.getVideoTracks().forEach(function (t) {
                        t.enabled = !t.enabled;
                        on = t.enabled;
                    });
                }
                lobbyCamBtn.classList.toggle('is-on', on);
                var icon = $('ckLobbyCamIcon');
                var label = $('ckLobbyCamLabel');
                if (icon) icon.className = on ? 'bi bi-camera-video-fill' : 'bi bi-camera-video-off-fill text-muted';
                if (label) label.textContent = on ? 'Camera' : 'Camera Off';
                var camBtn = $('ckCam');
                if (camBtn) {
                    camBtn.classList.toggle('is-on', on);
                    camBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                }
            });
        }

        // Student Download PDF pill button
        var pillDl = $('ckPillDownloadPdf');
        if (pillDl) {
            pillDl.addEventListener('click', function () {
                var mobDl = $('ckMobileDownloadPdfBtn');
                if (mobDl) {
                    mobDl.click();
                } else if (window.CKPdf && typeof window.CKPdf.downloadCurrentPdf === 'function') {
                    window.CKPdf.downloadCurrentPdf();
                }
            });
        }

        // Desktop Student Display Modes
        document.querySelectorAll('.ck-sd-btn[data-sd-mode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var mode = btn.getAttribute('data-sd-mode');
                setStudentDisplayMode(mode, true);
            });
        });

        // Host Mobile Dock: Share / View
        var mobShare = $('ckMobileShare');
        if (mobShare) {
            mobShare.addEventListener('click', function () {
                if (cfg.isHost) {
                    var btn = $('ckShare');
                    if (btn) btn.click();
                } else {
                    if (studentDisplayMode === 'board') {
                        setStudentDisplayMode('teacher', true);
                    } else {
                        setStudentDisplayMode('board', true);
                    }
                }
            });
        }

        // Host Mobile Dock: Board Toggle
        var mobBoard = $('ckMobileBoardToggle');
        if (mobBoard) {
            mobBoard.addEventListener('click', function () {
                if (cfg.isHost) {
                    setHostBoardFocus(true);
                    ensureBoard();
                    var panel = $('ckBoardPanel');
                    if (panel) panel.classList.add('is-on');
                    layoutStage();
                } else {
                    setStudentDisplayMode('board', true);
                }
            });
        }

        // Host Mobile Dock: People
        var mobPeople = $('ckMobilePeople');
        if (mobPeople) {
            mobPeople.addEventListener('click', function () {
                setPeopleOpen(!railIsOpen('people'), true);
            });
        }

        // Host Mobile Dock: Chat
        var mobChat = $('ckMobileChat');
        if (mobChat) {
            mobChat.addEventListener('click', function () {
                var side = $('ckChatRail');
                var isOpen = side && !side.hidden && side.classList.contains('is-open');
                if (!isOpen) {
                    setChatOpen(true, true);
                    scrollChatToBottom();
                } else {
                    setChatOpen(false, true);
                }
            });
        }

        // Host Actions in More Sheet
        var mobDrawToggle = $('ckMobileDrawToggle');
        if (mobDrawToggle) {
            mobDrawToggle.addEventListener('change', function () {
                var on = !!this.checked;
                var board = ensureBoard();
                if (board && typeof board.setStudentsCanDraw === 'function') {
                    board.setStudentsCanDraw(on);
                } else {
                    mobDrawToggle.checked = !!cfg.studentsCanDraw;
                }
            });
        }

        var mobRecBtn = $('ckMobileRecBtn');
        if (mobRecBtn) {
            mobRecBtn.addEventListener('click', function () {
                closeMobileMoreSheet();
                var btn = cfg.recording ? $('ckRecStop') : $('ckRecStart');
                if (btn) btn.click();
            });
        }

        var mobLockBtn = $('ckMobileLockBtn');
        if (mobLockBtn) {
            mobLockBtn.addEventListener('click', function () {
                var btn = $('ckLock');
                if (btn) btn.click();
            });
        }

        var mobWaitBtn = $('ckMobileWaitBtn');
        if (mobWaitBtn) {
            mobWaitBtn.addEventListener('click', function () {
                var btn = $('ckWaitingRoom');
                if (btn) btn.click();
            });
        }

        var mobMuteAll = $('ckMobileMuteAllBtn');
        if (mobMuteAll) {
            mobMuteAll.addEventListener('click', function () {
                closeMobileMoreSheet();
                var btn = $('ckMuteAll');
                if (btn) btn.click();
            });
        }

        var mobClearBoard = $('ckMobileClearBoardBtn');
        if (mobClearBoard) {
            mobClearBoard.addEventListener('click', function () {
                closeMobileMoreSheet();
                var btn = $('ckBoardClearAllBtn') || $('ckBoardClear');
                if (btn) btn.click();
            });
        }

        var mobExportPng = $('ckMobileExportPngBtn');
        if (mobExportPng) {
            mobExportPng.addEventListener('click', function () {
                closeMobileMoreSheet();
                var btn = $('ckBoardExportPngBtn');
                if (btn) btn.click();
            });
        }

        var mobExportPdf = $('ckMobileExportPdfBtn');
        if (mobExportPdf) {
            mobExportPdf.addEventListener('click', function () {
                closeMobileMoreSheet();
                var btn = $('ckBoardExportPdfBtn');
                if (btn) btn.click();
            });
        }

        // Student Actions in More Sheet
        document.querySelectorAll('.ck-sheet-tab-btn[data-sd-mode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var mode = btn.getAttribute('data-sd-mode');
                setStudentDisplayMode(mode, true);
                closeMobileMoreSheet();
            });
        });

        var mobHandBtn = $('ckMobileHandBtn');
        if (mobHandBtn) {
            mobHandBtn.addEventListener('click', function () {
                var btn = $('ckHand');
                if (btn) btn.click();
            });
        }

        var mobParticipants = $('ckMobileParticipantsBtn');
        if (mobParticipants) {
            mobParticipants.addEventListener('click', function () {
                closeMobileMoreSheet();
                setChatOpen(true, true);
                var card = $('ckCardStudents');
                if (card && typeof card.scrollIntoView === 'function') {
                    setTimeout(function () { card.scrollIntoView({ block: 'start' }); }, 100);
                }
            });
        }

        var mobStudentCam = $('ckMobileStudentCamBtn');
        if (mobStudentCam) {
            mobStudentCam.addEventListener('click', function () {
                var btn = $('ckCam');
                if (btn) btn.click();
                setTimeout(syncMobileStudentAudioVideoUi, 200);
            });
        }

        function syncMobileStudentAudioVideoUi() {
            var camBtn = $('ckCam');
            var camIcon = $('ckMobStudentCamIcon');
            var camLabel = $('ckMobStudentCamLabel');

            if (camIcon && camBtn) {
                var camOn = camBtn.classList.contains('is-on') || camBtn.getAttribute('aria-pressed') === 'true';
                camIcon.className = camOn ? 'bi bi-camera-video-fill text-success' : 'bi bi-camera-video-off-fill text-muted';
                if (camLabel) camLabel.textContent = camOn ? 'Turn Camera Off' : 'Turn Camera On';
            }
        }

        // Mobile Pages Drawer Bottom Sheet Wiring
        var pageInd = $('ckBoardPageIndicator');
        var pnsSidebar = $('ckPageNavSidebar');
        function toggleMobilePages(e) {
            if (e) e.stopPropagation();
            if (pnsSidebar) {
                pnsSidebar.classList.toggle('is-mobile-open');
                if (pnsSidebar.classList.contains('is-mobile-open')) {
                    var pnsDrawer = $('ckPnsDrawer');
                    if (pnsDrawer) pnsDrawer.hidden = false;
                }
            }
        }
        if (pageInd) {
            pageInd.style.cursor = 'pointer';
            pageInd.title = 'Tap to view all pages';
            pageInd.addEventListener('click', toggleMobilePages);
        }
        var mobPagesSheetBtn = $('ckMobilePagesSheetBtn');
        if (mobPagesSheetBtn) {
            mobPagesSheetBtn.addEventListener('click', function () {
                closeMobileMoreSheet();
                toggleMobilePages();
            });
        }
        var thumbList = $('ckPageThumbnails');
        if (thumbList) {
            thumbList.addEventListener('click', function () {
                if (pnsSidebar && pnsSidebar.classList.contains('is-mobile-open')) {
                    pnsSidebar.classList.remove('is-mobile-open');
                }
            });
        }
        document.addEventListener('click', function (e) {
            if (pnsSidebar && pnsSidebar.classList.contains('is-mobile-open')) {
                if (!pnsSidebar.contains(e.target) && e.target !== pageInd && (!mobPagesSheetBtn || !mobPagesSheetBtn.contains(e.target))) {
                    pnsSidebar.classList.remove('is-mobile-open');
                }
            }
        });

        // Shared Actions in More Sheet
        var mobFsBtn = $('ckMobileFsBtn');
        if (mobFsBtn) {
            mobFsBtn.addEventListener('click', function () {
                toggleFullscreen(document.documentElement);
            });
        }

        var mobSettingsBtn = $('ckMobileSettingsBtn');
        if (mobSettingsBtn) {
            mobSettingsBtn.addEventListener('click', function () {
                closeMobileMoreSheet();
                openSettings();
            });
        }

        var mobEndBtn = $('ckMobileEndClassBtn');
        if (mobEndBtn) {
            mobEndBtn.addEventListener('click', function () {
                closeMobileMoreSheet();
                var btn = $('ckEnd');
                if (btn) btn.click();
            });
        }

        // Viewport resize & orientation change listeners
        var resizeDebounce = null;
        function handleViewportResize() {
            clearTimeout(resizeDebounce);
            resizeDebounce = setTimeout(function () {
                layoutStage();
                if (boardApi && typeof boardApi.recalculateViewport === 'function') {
                    boardApi.recalculateViewport();
                }
            }, 100);
        }
        window.addEventListener('resize', handleViewportResize);
        window.addEventListener('orientationchange', function () {
            setTimeout(handleViewportResize, 200);
        });

        // Visual Viewport tracking for mobile virtual keyboard
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', function () {
                var side = $('ckChatRail');
                if (!side || !side.classList.contains('is-open')) return;
                if (window.innerWidth <= 860) {
                    var offset = window.innerHeight - window.visualViewport.height - (window.visualViewport.offsetTop || 0);
                    if (offset > 40) {
                        side.style.bottom = Math.max(0, offset) + 'px';
                        side.style.maxHeight = (window.visualViewport.height - 10) + 'px';
                    } else {
                        side.style.bottom = '';
                        side.style.maxHeight = '';
                    }
                }
            });
        }
    }

    function publishHandMetadata() {
        if (!room || !room.localParticipant) return;
        try {
            room.localParticipant.setMetadata(JSON.stringify({
                role: cfg.role,
                userId: cfg.userId,
                hand: handUp
            }));
        } catch (e) {}
    }

    function toggleLocalMic() {
        if (!room) return;
        if (!cfg.isHost && (localMicBlocked || !micAllowedFor(room.localParticipant))) {
            setMicUi(false, true);
            toast('The teacher has muted your microphone.');
            return;
        }
        var on = !room.localParticipant.isMicrophoneEnabled;
        room.localParticipant.setMicrophoneEnabled(on).then(function () {
            setMicUi(on, false);
        }).catch(function (e) {
            setMicUi(room.localParticipant.isMicrophoneEnabled, localMicBlocked);
            toast(mediaError(e));
        });
    }

    function wireUi() {
        if (wireUi.done) return;
        wireUi.done = true;
        wireCameraDrag();
        document.querySelectorAll('.ck-rail-tabs button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var panel = btn.getAttribute('data-panel');
                var already = btn.classList.contains('is-on');
                if (already) {
                    document.querySelectorAll('.ck-rail-tabs button').forEach(function (b) { b.classList.remove('is-on'); });
                    ['ckBoardPanel', 'ckMaterialsPanel', 'ckInfoPanel'].forEach(function (id) {
                        var el = $(id);
                        if (el) el.classList.remove('is-on');
                    });
                    if (cfg.isHost) {
                        setHostBoardFocus(false);
                        if (stageFocus === 'board') leaveStageFocus(true);
                    }
                    return;
                }
                setRailPanel(panel);
                if (panel === 'board') fetchBoard(true);
                if (panel === 'board' && !cfg.isHost && stageFocus === 'board') {
                    stageFocusHeld = true;
                }
            });
        });
        $('ckChatForm') && $('ckChatForm').addEventListener('submit', sendChat);
        $('ckAnnounceForm') && $('ckAnnounceForm').addEventListener('submit', sendAnnouncement);
        document.querySelectorAll('#ckChatTabs [data-chat]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setChatMode(btn.getAttribute('data-chat'));
            });
        });
        $('ckPrivatePeerSelect') && $('ckPrivatePeerSelect').addEventListener('change', function () {
            selectedPrivatePeer = parseInt(this.value, 10) || 0;
            filterPrivateLog();
            if (selectedPrivatePeer && privatePeers[selectedPrivatePeer]) {
                publishChatPacket({
                    t: 'chat-private-open',
                    is_private: 1,
                    user_id: parseInt(cfg.userId, 10) || 0,
                    recipient_user_id: selectedPrivatePeer,
                    name: cfg.displayName
                }, [privatePeers[selectedPrivatePeer].identity]);
            }
        });
        $('ckChatToggle') && $('ckChatToggle').addEventListener('click', function () {
            var side = $('ckChatRail');
            var isOpen = side && !side.hidden && side.classList.contains('is-open');
            if (!isOpen) {
                setChatOpen(true, true);
                scrollChatToBottom();
            } else {
                setChatOpen(false, true);
            }
        });
        $('ckChatClose') && $('ckChatClose').addEventListener('click', function () {
            setChatPinned(false);
            setChatOpen(false, true);
        });
        $('ckChatPinBtn') && $('ckChatPinBtn').addEventListener('click', function () {
            setChatPinned(!chatPinned);
        });

        var chatZone = $('ckChatTriggerZone');
        var chatHandle = $('ckChatEdgeHandle');
        var chatSide = $('ckChatRail');

        if (chatZone) {
            chatZone.addEventListener('mouseenter', cancelChatHide);
            chatZone.addEventListener('mouseleave', scheduleChatHide);
        }
        if (chatHandle) {
            chatHandle.addEventListener('mouseenter', cancelChatHide);
            chatHandle.addEventListener('mouseleave', scheduleChatHide);
            chatHandle.addEventListener('click', function (e) {
                e.stopPropagation();
                setChatOpen(!railIsOpen('chat'), true);
            });
        }
        if (chatSide) {
            chatSide.addEventListener('mouseenter', cancelChatHide);
            chatSide.addEventListener('mouseleave', scheduleChatHide);
            chatSide.addEventListener('focusin', cancelChatHide);
            chatSide.addEventListener('focusout', scheduleChatHide);
            if (!cfg.isHost) {
                chatSide.addEventListener('pointerdown', bumpStudentChatIdle);
                chatSide.addEventListener('scroll', bumpStudentChatIdle, true);
            }
        }
        var chatInput = $('ckChatInput');
        if (chatInput && !cfg.isHost) {
            chatInput.addEventListener('focus', function () { clearStudentChatTimer(); });
            chatInput.addEventListener('blur', function () { scheduleStudentChatHide(); });
            chatInput.addEventListener('input', bumpStudentChatIdle);
            chatInput.addEventListener('keydown', bumpStudentChatIdle);
        }

        $('ckPeopleToggle') && $('ckPeopleToggle').addEventListener('click', function () {
            setPeopleOpen(!railIsOpen('people'), true);
        });
        $('ckPeopleClose') && $('ckPeopleClose').addEventListener('click', function () {
            setPeoplePinned(false);
            setPeopleOpen(false, true);
        });
        $('ckPeoplePinBtn') && $('ckPeoplePinBtn').addEventListener('click', function () {
            setPeoplePinned(!peoplePinned);
        });

        var peopleZone = $('ckPeopleTriggerZone');
        var peopleHandle = $('ckPeopleEdgeHandle');
        var peopleRail = $('ckPeopleRail');

        function keepPeopleOpen() {
            if (peopleHideTimer) { clearTimeout(peopleHideTimer); peopleHideTimer = null; }
        }
        if (peopleZone) {
            peopleZone.addEventListener('mouseenter', function () {
                if (cfg.isHost) keepPeopleOpen();
            });
            peopleZone.addEventListener('mouseleave', function () {
                if (cfg.isHost) schedulePeopleHide();
            });
        }
        if (peopleHandle) {
            peopleHandle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (cfg.isHost) setPeopleOpen(!railIsOpen('people'), true);
            });
        }
        if (peopleRail) {
            peopleRail.addEventListener('mouseenter', function () {
                if (cfg.isHost) keepPeopleOpen();
            });
            peopleRail.addEventListener('mouseleave', function () {
                if (cfg.isHost) schedulePeopleHide();
            });
        }

        document.querySelectorAll('.ck-sd-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var mode = btn.getAttribute('data-sd-mode');
                setStudentDisplayMode(mode, true);
            });
        });
        $('ckDrawerMask') && $('ckDrawerMask').addEventListener('click', function () {
            setPeopleOpen(false);
            setChatOpen(false);
        });
        $('ckStopShare') && $('ckStopShare').addEventListener('click', function () {
            if ($('ckShare')) $('ckShare').click();
        });
        $('ckEnableSound') && $('ckEnableSound').addEventListener('click', function () {
            if (!room) return;
            var btn = this;
            room.startAudio().then(function () {
                btn.hidden = true;
            }).catch(function () {
                toast('Could not enable speakers. Check the browser site settings for sound.');
            });
        });
        $('ckMic') && $('ckMic').addEventListener('click', toggleLocalMic);
        document.addEventListener('fullscreenchange', onFullscreenChanged);
        document.addEventListener('webkitfullscreenchange', onFullscreenChanged);
        var boardStage = $('ckBoardStage') || $('ckBoardPanel');
        if (boardStage) addFsButton(boardStage, $('ckBoard'));
        ensureStageChrome();
        $('ckStageExit') && $('ckStageExit').addEventListener('click', function () {
            if (!cfg.isHost && isFullscreen()) {
                toggleFullscreen($('ckStage') || document.documentElement);
                return;
            }
            if (!cfg.isHost && (stageFocus === 'share' || anyoneSharing())) {
                minimizeTeacherScreen();
                return;
            }
            dismissStageFocus();
        });
        $('ckScreenMinDock') && $('ckScreenMinDock').addEventListener('click', function (ev) {
            ev.preventDefault();
            restoreTeacherScreen();
        });
        $('ckScreenRestoreBtn') && $('ckScreenRestoreBtn').addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            restoreTeacherScreen();
        });
        document.addEventListener('click', function (ev) {
            var btn = ev.target && ev.target.closest && ev.target.closest('#ckBoardFs, .ck-pill-fs-btn, .ck-board-fs');
            if (btn) {
                handleBoardFullscreen(ev);
            }
        });
        $('ckCam') && $('ckCam').addEventListener('click', function () {
            if (!room) return;
            if (!cfg.isHost) {
                if (localCamBlocked || !cameraAllowedFor(room.localParticipant)) {
                    setCamUi(false, true);
                    toast('The teacher has blocked your camera.');
                    return;
                }
                if (!room.localParticipant.isCameraEnabled) forceEnableLocalCamera(0);
                return;
            }
            var on = !room.localParticipant.isCameraEnabled;
            room.localParticipant.setCameraEnabled(on).then(function () {
                setCamUi(on, false);
            }).catch(function (e) {
                setCamUi(room.localParticipant.isCameraEnabled, localCamBlocked);
                toast(mediaError(e));
            });
        });
        $('ckShare') && $('ckShare').addEventListener('click', function () {
            if (!room) {
                toast('Join the class before sharing your screen.');
                return;
            }
            if (!cfg.isHost && !shareAllowedFor(room.localParticipant)) {
                stopLocalShare();
                setShareUi(false, false);
                toast('The teacher has not allowed you to share your screen.');
                return;
            }
            if (room.localParticipant.isScreenShareEnabled) {
                room.localParticipant.setScreenShareEnabled(false).then(function () {
                    $('ckShare').classList.remove('is-on');
                });
                return;
            }
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getDisplayMedia !== 'function') {
                toast('This browser cannot share the screen. Use Chrome or Edge on a computer.');
                return;
            }
            room.localParticipant.setScreenShareEnabled(true, {
                audio: false,
                systemAudio: 'exclude',
                selfBrowserSurface: 'exclude',
                contentHint: 'detail',
                resolution: { width: 1280, height: 720, frameRate: 12 }
            }, {
                simulcast: false,
                videoCodec: 'vp8',
                videoEncoding: { maxBitrate: 900000, maxFramerate: 12 }
            }).then(function () {
                $('ckShare').classList.add('is-on');
                syncSharePauseUi();
            }).catch(function (err) {
                try { console.error('Screen share failed', err); } catch (e) {}
                toast(shareError(err));
            });
        });
        $('ckSharePause') && $('ckSharePause').addEventListener('click', function () {
            if (!cfg.isHost) return;
            toggleLocalSharePause();
        });
        $('ckHand') && $('ckHand').addEventListener('click', function () {
            setHandUi(!handUp);
            var raised = handUp;
            api('control.php', { action: raised ? 'raise_hand' : 'lower_own_hand' }).then(function (res) {
                if (res && res.ok === false) setHandUi(!raised);
            });
            publishData({
                t: 'hand',
                up: raised,
                userId: cfg.userId,
                name: cfg.displayName || ''
            });
            publishHandMetadata();
        });
        $('ckLeave') && $('ckLeave').addEventListener('click', function () {
            api('heartbeat.php', { action: 'leave' });
            if (room) room.disconnect();
            window.location.href = cfg.homeUrl;
        });
        $('ckEnd') && $('ckEnd').addEventListener('click', function () {
            if (!window.confirm('End this class for everyone?')) return;
            bridgeBroadcast({ t: 'class_ended' });
            api('end.php', {}).then(function (data) {
                if (room) room.disconnect();
                toast(data.message || 'Class ended.');
                setTimeout(function () { window.location.href = cfg.homeUrl; }, 800);
            });
        });
        // Board tool, clear, and allow-draw handlers live in classroom-board.js
        $('ckRecStart') && $('ckRecStart').addEventListener('click', function () {
            api('control.php', { action: 'start_recording' }).then(function (data) {
                if (!data.ok) {
                    toast(data.error || 'Could not start recording.');
                    return;
                }
                setRecordingUi(!!data.recording);
                if (data.skipped || !data.recording) {
                    toast(data.message || data.error || 'Recording is not available.');
                    return;
                }
                toast(data.already ? (data.message || 'This class is already being recorded.') : (data.message || 'Recording started.'));
                if (data.recording) publishData({ t: 'rec', on: true });
            });
        });
        $('ckRecStop') && $('ckRecStop').addEventListener('click', function () {
            api('control.php', { action: 'stop_recording' }).then(function (data) {
                if (!data.ok) {
                    toast(data.error || 'Could not stop recording.');
                    return;
                }
                setRecordingUi(false);
                toast(data.message || 'Recording stopped.');
                publishData({ t: 'rec', on: false });
            });
        });
        document.querySelectorAll('#ckViewPresentation, #ckViewSpeaker, #ckViewGallery').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setViewMode(btn.getAttribute('data-view'), true);
            });
        });
        $('ckSettings') && $('ckSettings').addEventListener('click', openSettings);
        $('ckSettingsClose') && $('ckSettingsClose').addEventListener('click', closeSettings);
        $('ckSettingsModal') && $('ckSettingsModal').addEventListener('click', function (ev) {
            if (ev.target === this) closeSettings();
        });
        $('ckMicSelect') && $('ckMicSelect').addEventListener('change', function () {
            switchKind('audioinput', this.value);
        });
        $('ckCamSelect') && $('ckCamSelect').addEventListener('change', function () {
            switchKind('videoinput', this.value);
        });
        $('ckSpeakerSelect') && $('ckSpeakerSelect').addEventListener('change', function () {
            switchKind('audiooutput', this.value);
        });
        $('ckLock') && $('ckLock').addEventListener('click', function () {
            var next = !cfg.locked;
            api('control.php', { action: next ? 'lock' : 'unlock' }).then(function (res) {
                if (!res.ok) {
                    toast(res.error || 'Could not update the lock.');
                    return;
                }
                setLockedUi(next);
                toast(next ? 'Class is locked. New students cannot join.' : 'Class is unlocked.');
            });
        });
        $('ckWaitingRoom') && $('ckWaitingRoom').addEventListener('click', function () {
            var next = !cfg.waitingRoom;
            api('control.php', { action: next ? 'waiting_on' : 'waiting_off' }).then(function (res) {
                if (!res.ok) {
                    toast(res.error || 'Could not update the waiting room.');
                    return;
                }
                setWaitingRoomUi(typeof res.waiting_room === 'boolean' ? res.waiting_room : next);
                renderWaiting(res.waiting || []);
                toast(next
                    ? 'Waiting room is on. New students wait until you admit them.'
                    : 'Waiting room is off. Students can join.');
            });
        });
        $('ckMuteAll') && $('ckMuteAll').addEventListener('click', function () {
            api('control.php', { action: 'mute_all' }).then(function (res) {
                if (!res || !res.ok) {
                    toast((res && res.error) || 'Could not mute the class.');
                    return;
                }
                if (room && room.remoteParticipants) {
                    room.remoteParticipants.forEach(function (p) {
                        if (p && p.identity && !participantIsStaff(p)) micBlockState[p.identity] = true;
                    });
                }
                refreshPeople();
                toast('All student microphones are muted.');
            });
        });
        $('ckClearChat') && $('ckClearChat').addEventListener('click', function () {
            api('control.php', { action: 'clear_chat' }).then(function (res) {
                if (!res.ok) {
                    toast(res.error || 'Could not clear chat.');
                    return;
                }
                var log = $('ckChatLog');
                if (log) log.innerHTML = '';
                bridgeBroadcast({ t: 'chat_clear' });
                if (room) {
                    room.localParticipant.publishData(
                        new TextEncoder().encode(JSON.stringify({ t: 'chat-clear' })),
                        { reliable: true }
                    );
                }
                toast('Chat was cleared.');
            });
        });
        if (window.matchMedia) {
            var mq = window.matchMedia('(max-width: 860px)');
            var onMq = function () {
                if (!document.body.classList.contains('ck-in-room')) return;
                if (isMobile()) {
                    var rail = $('ckPeopleRail');
                    var side = $('ckChatRail');
                    if (cfg.isHost && rail) {
                        rail.hidden = false;
                        rail.classList.remove('is-open');
                    } else if (rail) {
                        rail.hidden = true;
                        rail.classList.remove('is-open');
                        rail.setAttribute('aria-hidden', 'true');
                    }
                    if (side) {
                        side.hidden = false;
                        side.classList.remove('is-open');
                    }
                    syncMask();
                    syncMainGrid();
                    if (!cfg.isHost) scheduleStudentChatHide();
                } else {
                    setPeopleOpen(peoplePinned);
                    setChatOpen(chatPinned);
                }
            };
            if (mq.addEventListener) mq.addEventListener('change', onMq);
            else if (mq.addListener) mq.addListener(onMq);
        }

        // Floating Student Camera Panel & Pop-out Secondary Windows Wiring
        if (cfg.isHost) {
            initScpDrag();

            $('ckStudentCamsToggle') && $('ckStudentCamsToggle').addEventListener('click', function () {
                var panel = $('ckStudentCamPanel');
                if (panel) setScpOpen(panel.hidden);
            });

            $('ckTeachingMonitorBtn') && $('ckTeachingMonitorBtn').addEventListener('click', function () {
                openPopout('monitor');
            });

            $('ckTopMonitorBtn') && $('ckTopMonitorBtn').addEventListener('click', function () {
                openPopout('monitor');
            });

            $('ckChatPopoutBtn') && $('ckChatPopoutBtn').addEventListener('click', function () {
                openPopout('chat');
            });

            $('ckScpPopoutBtn') && $('ckScpPopoutBtn').addEventListener('click', function () {
                openPopout('camera');
            });

            $('ckScpMinBtn') && $('ckScpMinBtn').addEventListener('click', function () {
                var panel = $('ckStudentCamPanel');
                if (panel) setScpMinimized(!panel.classList.contains('is-minimized'));
            });

            $('ckScpCloseBtn') && $('ckScpCloseBtn').addEventListener('click', function () {
                setScpOpen(false);
            });

            $('ckScpViewGrid') && $('ckScpViewGrid').addEventListener('click', function () {
                scpViewMode = 'grid';
                this.classList.add('is-on');
                var spk = $('ckScpViewSpeaker');
                if (spk) spk.classList.remove('is-on');
                layoutScpStage();
            });

            $('ckScpViewSpeaker') && $('ckScpViewSpeaker').addEventListener('click', function () {
                scpViewMode = 'speaker';
                this.classList.add('is-on');
                var grd = $('ckScpViewGrid');
                if (grd) grd.classList.remove('is-on');
                layoutScpStage();
            });

            $('ckScpFocusPopoutBtn') && $('ckScpFocusPopoutBtn').addEventListener('click', function () {
                var win = activePopouts.camera || activePopouts.monitor;
                if (win && !win.closed) {
                    try { win.focus(); } catch (e) {}
                }
            });

            $('ckScpRestoreBtn') && $('ckScpRestoreBtn').addEventListener('click', function () {
                ['camera', 'monitor'].forEach(function (m) {
                    if (activePopouts[m] && !activePopouts[m].closed) {
                        try { activePopouts[m].close(); } catch (e) {}
                    }
                    delete activePopouts[m];
                });
                updatePopoutUiState();
                refreshScpTiles();
            });

            $('ckPopupRetryBtn') && $('ckPopupRetryBtn').addEventListener('click', function () {
                hidePopupBlockedBanner();
                var mode = lastPopoutAttempt ? lastPopoutAttempt.mode : 'monitor';
                openPopout(mode);
            });

            $('ckPopupCloseBtn') && $('ckPopupCloseBtn').addEventListener('click', function () {
                hidePopupBlockedBanner();
            });
        }

        var hostFold = $('ckHostFold');
        var hostFoldBtn = $('ckHostFoldBtn');
        if (hostFold && hostFoldBtn) {
            hostFoldBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = hostFold.classList.toggle('is-open');
                hostFoldBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            document.addEventListener('click', function (e) {
                if (!hostFold.contains(e.target)) {
                    hostFold.classList.remove('is-open');
                    hostFoldBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Top header more menu dropdown toggle
        var topMoreBtn = $('ckTopMoreBtn');
        var topMoreMenu = $('ckTopMoreMenu');
        var topMoreWrap = $('ckTopMoreWrap');
        if (topMoreBtn && topMoreMenu) {
            topMoreBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                topMoreMenu.hidden = !topMoreMenu.hidden;
            });
            document.addEventListener('click', function (e) {
                if (topMoreWrap && !topMoreWrap.contains(e.target)) {
                    topMoreMenu.hidden = true;
                }
            });
            topMoreMenu.querySelectorAll('.ck-dropdown-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    topMoreMenu.hidden = true;
                });
            });
        }

        setupSidebarTabs();
        initMobileClassroom();
    }

    loadViewMode();
    loadStudentDisplayMode();
    setLockedUi(!!cfg.locked);
    setWaitingRoomUi(!!cfg.waitingRoom);
    setRecordingUi(!!cfg.recording);
    applyCanDraw(cfg.studentsCanDraw);
    applyStartedAt(cfg.startedAt);
    wireUi();
    window.connect = connect;
    window.startClass = startClass;
    if ((cfg.configured || cfg.accessCode === 'ALLOW' || cfg.isHost) && cfg.accessCode !== 'DENY' && cfg.accessCode !== 'SETUP') {
        if (cfg.accessCode !== 'PAY' && cfg.waitKind !== 'pay_pending') {
            startPreview();
        }
        renderLobbyActions(cfg.state, { code: cfg.accessCode, wait_kind: cfg.waitKind, waiting_room: cfg.waitingRoom });
        pollTimer = setInterval(pollStatus, 5000);
        pollStatus();
        bindClassroomVisibility();
    } else if (cfg.accessCode === 'DENY' || cfg.accessCode === 'PAY') {
        renderLobbyActions(cfg.state, { code: cfg.accessCode, wait_kind: cfg.waitKind });
        if (cfg.accessCode === 'PAY' && cfg.configured) {
            pollTimer = setInterval(pollStatus, 5000);
            pollStatus();
            bindClassroomVisibility();
        }
    }

    function bindClassroomVisibility() {
        if (visibilityBound) return;
        visibilityBound = true;
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) return;
            if (pollTimer) pollStatus();
            if (connected) {
                api('chat.php?after=' + lastChatId).then(function (data) {
                    applyChatMeta(data);
                    (data.messages || []).forEach(appendChat);
                }).catch(function () {});
            }
        });
    }
})();
