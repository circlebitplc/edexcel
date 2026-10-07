/**
 * Edexcel College Live Classroom — Pop-out Secondary Monitor Controller
 * Handles detached Student Camera Panel, Class Chat, and combined Teaching Monitor.
 * Keeps feeds synchronized with the main classroom window with zero duplicate audio/echo.
 */
(function () {
    'use strict';

    var cfg = window.CK_MONITOR_CONFIG || {};
    var students = {}; // keyed by identity
    var activeSpeakerIds = [];
    var pinnedIdentity = null;
    var viewMode = 'grid'; // 'grid' or 'speaker'
    var sortMode = 'activity'; // 'activity', 'name', 'cam_first'
    var chatMode = 'class';
    var lastChatId = 0;
    var privatePeers = {};
    var selectedPrivatePeer = 0;
    var channel = null;
    var pollTimer = null;
    var chatPollTimer = null;

    var $ = function (id) { return document.getElementById(id); };

    function toast(msg) {
        var el = $('ckMonitorToast');
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
                return { ok: false, error: 'Request failed.', _http: res.status };
            });
        });
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function formatChatTime(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        var h = d.getHours();
        var m = d.getMinutes();
        var am = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + (m < 10 ? '0' : '') + m + ' ' + am;
    }

    function identityUser(identity) {
        var m = /^u(\d+)$/i.exec(identity || '');
        return m ? parseInt(m[1], 10) : 0;
    }

    function getInitials(name) {
        var parts = String(name || '').trim().split(/\s+/);
        if (!parts.length || !parts[0]) return '?';
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    /* -------------------------------------------------------------
     * Student Video Feeds & Grid Management
     * ------------------------------------------------------------- */

    function getOrCreateStudent(identity, name) {
        if (!students[identity]) {
            students[identity] = {
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
        } else if (name && students[identity].name !== name) {
            students[identity].name = name;
        }
        return students[identity];
    }

    function renderTile(student) {
        var grid = $('ckMonitorGrid');
        if (!grid) return;

        var tileId = 'mon-tile-' + student.identity;
        var tile = document.getElementById(tileId);

        if (!tile) {
            tile = document.createElement('div');
            tile.id = tileId;
            tile.className = 'ck-monitor-tile';
            tile.setAttribute('data-identity', student.identity);

            tile.innerHTML =
                '<div class="ck-mon-video-wrap">' +
                    '<video class="ck-mon-video" autoplay playsinline muted></video>' +
                    '<div class="ck-mon-avatar">' +
                        '<span class="ck-mon-avatar-initials">' + escapeHtml(getInitials(student.name)) + '</span>' +
                        '<span class="ck-mon-avatar-label"><i class="bi bi-camera-video-off"></i> Camera Off</span>' +
                    '</div>' +
                '</div>' +
                '<div class="ck-mon-meta">' +
                    '<div class="ck-mon-info">' +
                        '<span class="ck-mon-mic-icon" title="Microphone status">' +
                            '<i class="bi bi-mic-mute-fill"></i>' +
                        '</span>' +
                        '<strong class="ck-mon-name">' + escapeHtml(student.name) + '</strong>' +
                    '</div>' +
                    '<div class="ck-mon-actions">' +
                        '<button type="button" class="ck-mon-btn ck-mon-pin-btn" title="Pin / Spotlight student">' +
                            '<i class="bi bi-pin"></i>' +
                        '</button>' +
                        '<button type="button" class="ck-mon-btn ck-mon-mute-btn" title="Mute / Unmute">' +
                            '<i class="bi bi-mic"></i>' +
                        '</button>' +
                        '<button type="button" class="ck-mon-btn ck-mon-cam-btn" title="Camera On / Off">' +
                            '<i class="bi bi-camera-video"></i>' +
                        '</button>' +
                    '</div>' +
                '</div>';

            // Wire action buttons
            var pinBtn = tile.querySelector('.ck-mon-pin-btn');
            if (pinBtn) {
                pinBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    togglePin(student.identity);
                });
            }

            var muteBtn = tile.querySelector('.ck-mon-mute-btn');
            if (muteBtn) {
                muteBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleMuteStudent(student);
                });
            }

            var camBtn = tile.querySelector('.ck-mon-cam-btn');
            if (camBtn) {
                camBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleCameraStudent(student);
                });
            }

            grid.appendChild(tile);
        }

        // Update state classes and indicators
        tile.classList.toggle('is-speaking', !!student.isSpeaking);
        tile.classList.toggle('is-pinned', pinnedIdentity === student.identity);
        tile.classList.toggle('has-video', !!student.hasVideo);

        var nameEl = tile.querySelector('.ck-mon-name');
        if (nameEl) nameEl.textContent = student.name;

        var micIcon = tile.querySelector('.ck-mon-mic-icon i');
        if (micIcon) {
            if (student.isSpeaking) {
                micIcon.className = 'bi bi-mic-fill text-success';
            } else if (!student.isMuted) {
                micIcon.className = 'bi bi-mic text-success';
            } else {
                micIcon.className = 'bi bi-mic-mute-fill text-danger';
            }
        }

        var muteBtnIcon = tile.querySelector('.ck-mon-mute-btn i');
        if (muteBtnIcon) {
            muteBtnIcon.className = student.isMicBlocked ? 'bi bi-mic-slash' : (student.isMuted ? 'bi bi-mic-mute' : 'bi bi-mic');
            tile.querySelector('.ck-mon-mute-btn').title = student.isMicBlocked ? 'Student mic is blocked' : (student.isMuted ? 'Unmute student' : 'Mute student');
        }

        var camBtnIcon = tile.querySelector('.ck-mon-cam-btn i');
        if (camBtnIcon) {
            camBtnIcon.className = student.isCameraBlocked ? 'bi bi-camera-video-off-fill text-danger' : (student.hasVideo ? 'bi bi-camera-video-fill' : 'bi bi-camera-video');
        }

        var pinBtnIcon = tile.querySelector('.ck-mon-pin-btn i');
        if (pinBtnIcon) {
            pinBtnIcon.className = pinnedIdentity === student.identity ? 'bi bi-pin-fill text-accent' : 'bi bi-pin';
        }

        // Attach video track safely (strictly muted!)
        var videoEl = tile.querySelector('.ck-mon-video');
        if (videoEl) {
            videoEl.muted = true; // Permanent echo safety!
            videoEl.volume = 0;
            if (student.hasVideo && student.mediaStreamTrack) {
                if (!videoEl.srcObject || videoEl._trackId !== student.mediaStreamTrack.id) {
                    try {
                        var stream = new MediaStream([student.mediaStreamTrack]);
                        videoEl.srcObject = stream;
                        videoEl._trackId = student.mediaStreamTrack.id;
                        videoEl.play().catch(function () {});
                    } catch (e) {
                        try { console.warn('Could not set video srcObject in monitor', e); } catch (err) {}
                    }
                }
            } else {
                if (videoEl.srcObject) {
                    videoEl.srcObject = null;
                    videoEl._trackId = null;
                }
            }
        }

        updateEmptyGridState();
    }

    function removeTile(identity) {
        var tile = document.getElementById('mon-tile-' + identity);
        if (tile) {
            var video = tile.querySelector('video');
            if (video && video.srcObject) video.srcObject = null;
            tile.remove();
        }
        if (pinnedIdentity === identity) {
            pinnedIdentity = null;
        }
        updateEmptyGridState();
        layoutCamStage();
    }

    function updateEmptyGridState() {
        var grid = $('ckMonitorGrid');
        var empty = $('ckMonitorEmpty');
        var countEl = $('ckStudentCamCount');
        if (!grid) return;

        var count = Object.keys(students).length;
        if (countEl) countEl.textContent = String(count);
        if (empty) empty.hidden = count > 0;
    }

    function togglePin(identity) {
        if (pinnedIdentity === identity) {
            pinnedIdentity = null;
        } else {
            pinnedIdentity = identity;
        }
        var unpinBtn = $('ckUnpinAllBtn');
        if (unpinBtn) unpinBtn.hidden = !pinnedIdentity;
        layoutCamStage();
    }

    function toggleMuteStudent(student) {
        var action = student.isMicBlocked ? 'allow_mic' : 'mute';
        api('control.php', {
            action: action,
            identity: student.identity,
            user_id: student.userId
        }).then(function (res) {
            if (!res || !res.ok) {
                toast((res && res.error) || 'Could not update student microphone.');
                return;
            }
            student.isMicBlocked = (action === 'mute');
            student.isMuted = (action === 'mute');
            renderTile(student);
            toast(action === 'mute' ? (student.name + ' was muted.') : ('Microphone allowed for ' + student.name + '.'));
        }).catch(function () {
            toast('Failed to update microphone.');
        });
    }

    function toggleCameraStudent(student) {
        var action = student.isCameraBlocked ? 'unblock_camera' : 'block_camera';
        api('control.php', {
            action: action,
            identity: student.identity,
            user_id: student.userId
        }).then(function (res) {
            if (!res || !res.ok) {
                toast((res && res.error) || 'Could not update student camera.');
                return;
            }
            student.isCameraBlocked = (action === 'block_camera');
            renderTile(student);
            toast(action === 'block_camera' ? (student.name + "'s camera was turned off.") : ('Camera allowed for ' + student.name + '.'));
        }).catch(function () {
            toast('Failed to update camera.');
        });
    }

    function layoutCamStage() {
        var stage = $('ckMonitorCamStage');
        var spotlight = $('ckMonitorSpotlight');
        var grid = $('ckMonitorGrid');
        if (!stage || !grid) return;

        var spotId = pinnedIdentity;
        if (!spotId && viewMode === 'speaker' && activeSpeakerIds.length) {
            // Find active speaker among students
            for (var i = 0; i < activeSpeakerIds.length; i++) {
                if (students[activeSpeakerIds[i]]) {
                    spotId = activeSpeakerIds[i];
                    break;
                }
            }
        }

        if (spotId && students[spotId] && spotlight) {
            spotlight.hidden = false;
            var spotTile = document.getElementById('mon-tile-' + spotId);
            if (spotTile && spotTile.parentNode !== spotlight) {
                spotlight.innerHTML = '';
                spotlight.appendChild(spotTile);
            }
            stage.classList.add('has-spotlight');
        } else if (spotlight) {
            spotlight.hidden = true;
            // Return any tile inside spotlight back to grid
            Array.prototype.forEach.call(spotlight.children, function (child) {
                grid.appendChild(child);
            });
            stage.classList.remove('has-spotlight');
        }

        // Sort tiles remaining in grid
        sortTiles();
    }

    function sortTiles() {
        var grid = $('ckMonitorGrid');
        if (!grid) return;

        var tiles = Array.prototype.slice.call(grid.querySelectorAll('.ck-monitor-tile'));
        tiles.sort(function (a, b) {
            var idA = a.getAttribute('data-identity');
            var idB = b.getAttribute('data-identity');
            var sA = students[idA];
            var sB = students[idB];
            if (!sA || !sB) return 0;

            if (sortMode === 'name') {
                return (sA.name || '').localeCompare(sB.name || '');
            }
            if (sortMode === 'cam_first') {
                if (sA.hasVideo !== sB.hasVideo) return sA.hasVideo ? -1 : 1;
                return (sA.name || '').localeCompare(sB.name || '');
            }
            // default: activity (speaking first, then active video, then recent)
            if (sA.isSpeaking !== sB.isSpeaking) return sA.isSpeaking ? -1 : 1;
            if (sA.hasVideo !== sB.hasVideo) return sA.hasVideo ? -1 : 1;
            return (sB.lastActivity || 0) - (sA.lastActivity || 0);
        });

        tiles.forEach(function (tile) { grid.appendChild(tile); });
    }

    /* -------------------------------------------------------------
     * Class & Private Chat Management
     * ------------------------------------------------------------- */

    function setChatMode(mode) {
        chatMode = mode === 'private' ? 'private' : 'class';
        var classTab = $('ckMonitorTabClass');
        var privTab = $('ckMonitorTabPrivate');
        if (classTab) classTab.classList.toggle('is-on', chatMode === 'class');
        if (privTab) privTab.classList.toggle('is-on', chatMode === 'private');

        var classLog = $('ckMonitorChatLog');
        var privLog = $('ckMonitorPrivateLog');
        if (classLog) classLog.hidden = chatMode !== 'class';
        if (privLog) privLog.hidden = chatMode !== 'private';

        var privBar = $('ckMonitorPrivateBar');
        if (privBar) privBar.hidden = chatMode !== 'private';

        var announce = $('ckMonitorAnnounceForm');
        if (announce) announce.hidden = chatMode !== 'class';

        var input = $('ckMonitorChatInput');
        if (input) {
            input.placeholder = chatMode === 'private' ? 'Message student privately' : 'Message the class';
        }

        filterPrivateLog();
    }

    function addPrivatePeer(userId, name, identity) {
        userId = parseInt(userId, 10) || 0;
        if (userId < 1 || userId === (parseInt(cfg.userId, 10) || 0)) return;
        if (!privatePeers[userId]) {
            privatePeers[userId] = { userId: userId, name: name || ('Student ' + userId), identity: identity || ('u' + userId) };
            renderPrivateSelect();
        }
    }

    function renderPrivateSelect() {
        var sel = $('ckMonitorPrivateSelect');
        if (!sel) return;
        var current = sel.value;
        sel.innerHTML = '<option value="">Choose a student</option>';
        Object.keys(privatePeers).forEach(function (uid) {
            var peer = privatePeers[uid];
            var opt = document.createElement('option');
            opt.value = String(peer.userId);
            opt.textContent = peer.name;
            if (String(peer.userId) === String(current) || String(peer.userId) === String(selectedPrivatePeer)) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });
    }

    function isPrivateMsg(msg) {
        return !!(msg && (msg.is_private || (msg.recipient_user_id && parseInt(msg.recipient_user_id, 10) > 0)));
    }

    function privateOtherId(msg) {
        var me = parseInt(cfg.userId, 10) || 0;
        var uid = parseInt(msg.user_id, 10) || 0;
        var recip = parseInt(msg.recipient_user_id, 10) || 0;
        return uid === me ? recip : uid;
    }

    function filterPrivateLog() {
        var log = $('ckMonitorPrivateLog');
        if (!log) return;
        var peer = parseInt(selectedPrivatePeer, 10) || 0;
        log.querySelectorAll('.ck-msg').forEach(function (div) {
            var other = parseInt(div.getAttribute('data-peer'), 10) || 0;
            div.hidden = !peer || other !== peer;
        });
        log.scrollTop = log.scrollHeight;
    }

    function appendChat(msg) {
        if (!msg) return;
        if (msg.id && msg.id <= lastChatId) return;
        if (msg.id) lastChatId = Math.max(lastChatId, parseInt(msg.id, 10) || 0);

        var privateMsg = isPrivateMsg(msg);
        if (privateMsg) {
            var other = privateOtherId(msg);
            var otherName = msg.display_name || msg.name || 'Student';
            if (other) addPrivatePeer(other, otherName, 'u' + other);
        }

        var log = $(privateMsg ? 'ckMonitorPrivateLog' : 'ckMonitorChatLog');
        if (!log) return;

        var div = document.createElement('div');
        div.className = 'ck-msg' + (msg.is_announcement ? ' is-announcement' : '') + (privateMsg ? ' is-private' : '');
        if (privateMsg) div.setAttribute('data-peer', String(privateOtherId(msg) || ''));

        var when = formatChatTime(msg.created_at);
        div.innerHTML = '<b>' + escapeHtml(msg.display_name || msg.name || 'Someone') +
            (privateMsg ? ' <span class="ck-msg-lock"><i class="bi bi-lock-fill"></i> Private</span>' : '') +
            (when ? ' <time>' + escapeHtml(when) + '</time>' : '') + '</b>' + escapeHtml(msg.body);

        log.appendChild(div);

        if (privateMsg) filterPrivateLog();
        else log.scrollTop = log.scrollHeight;
    }

    function sendChat(ev) {
        ev.preventDefault();
        var input = $('ckMonitorChatInput');
        var text = (input.value || '').trim();
        if (!text) return;

        var privateMode = chatMode === 'private';
        var recipientId = 0;
        if (privateMode) {
            recipientId = parseInt(selectedPrivatePeer, 10) || 0;
            if (!recipientId) {
                toast('Choose a student to message privately.');
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
            // Notify opener bridge so main window updates instantly and publishes packet
            if (window.opener && window.opener.CK_HOST_BRIDGE && typeof window.opener.CK_HOST_BRIDGE.onPopoutSentChat === 'function') {
                window.opener.CK_HOST_BRIDGE.onPopoutSentChat(data.message);
            }
            if (channel) {
                channel.postMessage({ t: 'chat_sent', message: data.message });
            }
        });
    }

    function sendAnnouncement(ev) {
        ev.preventDefault();
        var input = $('ckMonitorAnnounceInput');
        var text = (input.value || '').trim();
        if (!text) return;

        input.value = '';
        api('chat.php', { body: text, announcement: true }).then(function (data) {
            if (!data.ok || !data.message) {
                toast(data.error || 'Announcement was not sent.');
                return;
            }
            appendChat(data.message);
            if (window.opener && window.opener.CK_HOST_BRIDGE && typeof window.opener.CK_HOST_BRIDGE.onPopoutSentChat === 'function') {
                window.opener.CK_HOST_BRIDGE.onPopoutSentChat(data.message);
            }
            if (channel) {
                channel.postMessage({ t: 'chat_sent', message: data.message });
            }
        });
    }

    function loadChat() {
        api('chat.php?after=' + lastChatId).then(function (data) {
            if (!data || !data.ok) return;
            (data.threads || []).forEach(function (t) {
                addPrivatePeer(t.user_id, t.display_name, t.identity);
            });
            (data.messages || []).forEach(appendChat);
        }).catch(function () {});
    }

    /* -------------------------------------------------------------
     * Raised Hands & Waiting Room (Teaching Monitor Mode)
     * ------------------------------------------------------------- */

    function renderHands(hands) {
        var list = $('ckMonitorHandsList');
        var empty = $('ckMonitorHandsEmpty');
        var countEl = $('ckMonitorHandCount');
        if (!list) return;

        hands = hands || [];
        list.innerHTML = '';
        if (countEl) countEl.textContent = String(hands.length);
        if (empty) empty.hidden = hands.length > 0;

        hands.forEach(function (h, i) {
            var li = document.createElement('li');
            li.innerHTML = '<span class="ck-hand-n">' + (i + 1) + '</span>' +
                '<span>' + escapeHtml(h.display_name || 'Student') + '</span>';

            var dismissBtn = document.createElement('button');
            dismissBtn.type = 'button';
            dismissBtn.className = 'ck-btn ck-btn-sm';
            dismissBtn.textContent = 'Dismiss';
            dismissBtn.addEventListener('click', function () {
                api('control.php', { action: 'lower_hand', user_id: h.user_id }).then(function (res) {
                    if (res && res.ok) renderHands(res.hands || []);
                });
            });
            li.appendChild(dismissBtn);
            list.appendChild(li);
        });
    }

    /* -------------------------------------------------------------
     * Cross-Window Synchronization (Opener Bridge & BroadcastChannel)
     * ------------------------------------------------------------- */

    function setSyncStatus(connected) {
        var badge = $('ckSyncBadge');
        var text = $('ckSyncStatusText');
        var banner = $('ckMonitorOfflineBanner');
        if (badge) {
            badge.classList.toggle('is-connected', !!connected);
            badge.classList.toggle('is-disconnected', !connected);
        }
        if (text) {
            text.textContent = connected ? 'Synchronized' : 'Disconnected';
        }
        if (banner) {
            banner.hidden = !!connected;
        }
    }

    function checkOpenerLiveness() {
        var isLive = !!(window.opener && !window.opener.closed);
        setSyncStatus(isLive);
        return isLive;
    }

    function handleIncomingSyncMessage(data) {
        if (!data || !data.t) return;

        if (data.t === 'full_state') {
            setSyncStatus(true);
            // Hydrate participants
            (data.participants || []).forEach(function (p) {
                var s = getOrCreateStudent(p.identity, p.name);
                s.isMuted = !!p.isMuted;
                s.isSpeaking = !!p.isSpeaking;
                s.isCameraBlocked = !!p.isCameraBlocked;
                s.isMicBlocked = !!p.isMicBlocked;
                s.hasVideo = !!p.hasVideo;
                if (p.mediaStreamTrack) s.mediaStreamTrack = p.mediaStreamTrack;
                renderTile(s);
            });
            // Hydrate active speakers
            if (data.activeSpeakerIds) {
                activeSpeakerIds = data.activeSpeakerIds;
            }
            // Hydrate hands
            if (data.hands) renderHands(data.hands);
            // Hydrate chat
            (data.chatMessages || []).forEach(appendChat);
            layoutCamStage();
        } else if (data.t === 'participant_joined') {
            var s = getOrCreateStudent(data.identity, data.name);
            renderTile(s);
            layoutCamStage();
        } else if (data.t === 'participant_left') {
            delete students[data.identity];
            removeTile(data.identity);
        } else if (data.t === 'track_subscribed') {
            var s = getOrCreateStudent(data.identity, data.name);
            if (data.kind === 'video') {
                s.hasVideo = true;
                s.mediaStreamTrack = data.mediaStreamTrack || null;
            }
            renderTile(s);
            layoutCamStage();
        } else if (data.t === 'track_unsubscribed') {
            var s = students[data.identity];
            if (s && data.kind === 'video') {
                s.hasVideo = false;
                s.mediaStreamTrack = null;
                renderTile(s);
                layoutCamStage();
            }
        } else if (data.t === 'track_muted') {
            var s = students[data.identity];
            if (s) {
                if (data.kind === 'video') s.hasVideo = !data.muted;
                if (data.kind === 'audio') s.isMuted = !!data.muted;
                renderTile(s);
                layoutCamStage();
            }
        } else if (data.t === 'active_speakers') {
            activeSpeakerIds = data.speakerIds || [];
            Object.keys(students).forEach(function (id) {
                var isSpk = activeSpeakerIds.indexOf(id) >= 0;
                if (students[id].isSpeaking !== isSpk) {
                    students[id].isSpeaking = isSpk;
                    if (isSpk) students[id].lastActivity = Date.now();
                    renderTile(students[id]);
                }
            });
            layoutCamStage();
        } else if (data.t === 'chat_message') {
            appendChat(data.message);
        } else if (data.t === 'chat_clear') {
            var cLog = $('ckMonitorChatLog');
            if (cLog) cLog.innerHTML = '';
        } else if (data.t === 'hands_updated') {
            renderHands(data.hands || []);
        } else if (data.t === 'class_ended') {
            toast('The teacher has ended this class.');
            setTimeout(function () {
                var banner = $('ckMonitorOfflineBanner');
                if (banner) {
                    banner.innerHTML = '<i class="bi bi-info-circle-fill"></i> <span>This class has ended. You can close this window.</span>';
                    banner.hidden = false;
                }
            }, 1000);
        }
    }

    // Direct interface callable by window.opener
    window.CK_MONITOR = {
        syncState: function (state) {
            handleIncomingSyncMessage(Object.assign({ t: 'full_state' }, state));
        },
        onParticipantJoined: function (identity, name) {
            handleIncomingSyncMessage({ t: 'participant_joined', identity: identity, name: name });
        },
        onParticipantLeft: function (identity) {
            handleIncomingSyncMessage({ t: 'participant_left', identity: identity });
        },
        onTrackSubscribed: function (identity, name, kind, mediaStreamTrack) {
            handleIncomingSyncMessage({ t: 'track_subscribed', identity: identity, name: name, kind: kind, mediaStreamTrack: mediaStreamTrack });
        },
        onTrackUnsubscribed: function (identity, kind) {
            handleIncomingSyncMessage({ t: 'track_unsubscribed', identity: identity, kind: kind });
        },
        onTrackMuted: function (identity, kind, muted) {
            handleIncomingSyncMessage({ t: 'track_muted', identity: identity, kind: kind, muted: muted });
        },
        onActiveSpeakersChanged: function (speakerIds) {
            handleIncomingSyncMessage({ t: 'active_speakers', speakerIds: speakerIds });
        },
        onChatMessage: function (msg) {
            handleIncomingSyncMessage({ t: 'chat_message', message: msg });
        },
        onChatCleared: function () {
            handleIncomingSyncMessage({ t: 'chat_clear' });
        },
        onHandsUpdated: function (hands) {
            handleIncomingSyncMessage({ t: 'hands_updated', hands: hands });
        },
        onClassEnded: function () {
            handleIncomingSyncMessage({ t: 'class_ended' });
        }
    };

    function initBridge() {
        if (typeof BroadcastChannel !== 'undefined') {
            try {
                channel = new BroadcastChannel('ck_monitor_' + cfg.lessonId);
                channel.onmessage = function (ev) {
                    handleIncomingSyncMessage(ev.data);
                };
            } catch (e) {}
        }

        // Register with opener if available
        if (window.opener && window.opener.CK_HOST_BRIDGE && typeof window.opener.CK_HOST_BRIDGE.registerPopout === 'function') {
            try {
                window.opener.CK_HOST_BRIDGE.registerPopout(window, cfg.mode);
            } catch (e) {}
        } else if (channel) {
            // Broadcast ready signal
            channel.postMessage({ t: 'popout_ready', mode: cfg.mode });
        }

        // Poll opener liveness
        pollTimer = setInterval(checkOpenerLiveness, 3000);
        checkOpenerLiveness();
    }

    /* -------------------------------------------------------------
     * UI Event Handlers
     * ------------------------------------------------------------- */

    function wireControls() {
        // View modes
        var gridBtn = $('ckCamViewGrid');
        var spkBtn = $('ckCamViewSpeaker');
        if (gridBtn && spkBtn) {
            gridBtn.addEventListener('click', function () {
                viewMode = 'grid';
                gridBtn.classList.add('is-on');
                spkBtn.classList.remove('is-on');
                layoutCamStage();
            });
            spkBtn.addEventListener('click', function () {
                viewMode = 'speaker';
                spkBtn.classList.add('is-on');
                gridBtn.classList.remove('is-on');
                layoutCamStage();
            });
        }

        // Sort select
        var sortSel = $('ckCamSortSelect');
        if (sortSel) {
            sortSel.addEventListener('change', function () {
                sortMode = this.value;
                sortTiles();
            });
        }

        // Unpin all
        var unpinBtn = $('ckUnpinAllBtn');
        if (unpinBtn) {
            unpinBtn.addEventListener('click', function () {
                pinnedIdentity = null;
                unpinBtn.hidden = true;
                layoutCamStage();
            });
        }

        // Chat controls
        $('ckMonitorChatForm') && $('ckMonitorChatForm').addEventListener('submit', sendChat);
        $('ckMonitorAnnounceForm') && $('ckMonitorAnnounceForm').addEventListener('submit', sendAnnouncement);

        document.querySelectorAll('#ckMonitorChatTabs [data-chat]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setChatMode(btn.getAttribute('data-chat'));
            });
        });

        $('ckMonitorPrivateSelect') && $('ckMonitorPrivateSelect').addEventListener('change', function () {
            selectedPrivatePeer = parseInt(this.value, 10) || 0;
            filterPrivateLog();
        });

        // Top bar buttons
        $('ckFocusOpenerBtn') && $('ckFocusOpenerBtn').addEventListener('click', function () {
            if (window.opener && !window.opener.closed) {
                window.opener.focus();
            } else {
                toast('Main classroom window is not accessible.');
            }
        });

        $('ckClosePopoutBtn') && $('ckClosePopoutBtn').addEventListener('click', function () {
            window.close();
        });

        $('ckReconnectOpenerBtn') && $('ckReconnectOpenerBtn').addEventListener('click', function () {
            initBridge();
            loadChat();
            toast('Reconnecting to main classroom...');
        });

        window.addEventListener('beforeunload', function () {
            if (channel) {
                try { channel.postMessage({ t: 'popout_closing', mode: cfg.mode }); } catch (e) {}
            }
            if (window.opener && window.opener.CK_HOST_BRIDGE && typeof window.opener.CK_HOST_BRIDGE.unregisterPopout === 'function') {
                try { window.opener.CK_HOST_BRIDGE.unregisterPopout(window, cfg.mode); } catch (e) {}
            }
        });
    }

    // Initialize
    wireControls();
    initBridge();
    loadChat();
    // Fallback periodic poll for chat in case of network or tab backgrounding
    chatPollTimer = setInterval(loadChat, 4000);
})();

