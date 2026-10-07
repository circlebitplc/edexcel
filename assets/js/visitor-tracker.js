/**
 * Edexcel College - Privacy-First Visitor Tracking Beacon
 * Lightweight, non-blocking telemetry. Zero personal data collected.
 */
(function () {
    'use strict';

    // Respect Do-Not-Track
    if (navigator.doNotTrack === '1' || window.doNotTrack === '1') {
        return;
    }

    // Do not track internal AJAX/API calls
    var currentPath = window.location.pathname || '/';
    if (currentPath.indexOf('/ajax/') !== -1 || currentPath.indexOf('/api/') !== -1) {
        return;
    }

    var ENDPOINT = '/ajax/track_visitor.php';
    var SESSION_TIMEOUT_MS = 30 * 60 * 1000; // 30 minutes

    // Generate or retrieve persistent anonymous visitor ID
    function getVisitorId() {
        var vid = null;
        try {
            vid = localStorage.getItem('_eck_vid');
            if (!vid || !/^[a-f0-9]{32}$/.test(vid)) {
                vid = generateRandomToken();
                localStorage.setItem('_eck_vid', vid);
            }
        } catch (e) {
            vid = getCookie('_eck_vid');
            if (!vid) {
                vid = generateRandomToken();
                setCookie('_eck_vid', vid, 365);
            }
        }
        return vid;
    }

    // Generate or retrieve session ID (expires after 30m idle)
    function getSessionId() {
        var now = Date.now();
        var sid = null;
        var lastAct = 0;
        try {
            sid = sessionStorage.getItem('_eck_sid');
            lastAct = parseInt(sessionStorage.getItem('_eck_sid_time') || '0', 10);
            if (!sid || (now - lastAct > SESSION_TIMEOUT_MS)) {
                sid = generateRandomToken();
                sessionStorage.setItem('_eck_sid', sid);
            }
            sessionStorage.setItem('_eck_sid_time', now.toString());
        } catch (e) {
            sid = getCookie('_eck_sid');
            if (!sid) {
                sid = generateRandomToken();
                setCookie('_eck_sid', sid, 1 / 48); // 30 min
            }
        }
        return sid;
    }

    function generateRandomToken() {
        if (window.crypto && window.crypto.getRandomValues) {
            var arr = new Uint8Array(16);
            window.crypto.getRandomValues(arr);
            var str = '';
            for (var i = 0; i < arr.length; i++) {
                str += ('0' + arr[i].toString(16)).slice(-2);
            }
            return str;
        }
        return 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.replace(/[x]/g, function () {
            return (Math.random() * 16 | 0).toString(16);
        });
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }

    function setCookie(name, val, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + (val || '') + expires + '; path=/; SameSite=Lax';
    }

    var startTime = Date.now();
    var visitorId = getVisitorId();
    var sessionId = getSessionId();

    function sendPayload(data) {
        var payload = JSON.stringify(data);
        if (window.fetch) {
            try {
                fetch(ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: payload,
                    keepalive: true
                }).then(function (res) {
                    return res.json();
                }).then(function (resData) {
                    if (resData && resData.session_rotated && resData.session_id) {
                        sessionId = resData.session_id;
                        try {
                            sessionStorage.setItem('_eck_sid', sessionId);
                            sessionStorage.setItem('_eck_sid_time', Date.now().toString());
                        } catch (e) {}
                    }
                }).catch(function () {});
                return;
            } catch (e) {}
        }
        if (navigator.sendBeacon) {
            try {
                var blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon(ENDPOINT, blob);
            } catch (e) {}
        }
    }

    // 1. Initial Pageview Telemetry
    function trackPageView() {
        var pageUrl = window.location.pathname + window.location.search;
        var referrer = document.referrer || '';

        sendPayload({
            action: 'pageview',
            visitor_id: visitorId,
            session_id: sessionId,
            page_url: pageUrl,
            page_title: document.title || '',
            referrer_url: referrer,
            screen_width: window.screen ? window.screen.width : 0,
            screen_height: window.screen ? window.screen.height : 0
        });
    }

    // 2. Heartbeat & Exit Beacon
    function sendHeartbeat() {
        var duration = Math.round((Date.now() - startTime) / 1000);
        sendPayload({
            action: 'heartbeat',
            session_id: sessionId,
            duration_seconds: duration,
            exit_page: window.location.pathname
        });
    }

    // Start tracking
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        trackPageView();
    } else {
        document.addEventListener('DOMContentLoaded', trackPageView);
    }

    // Heartbeat every 30 seconds
    setInterval(sendHeartbeat, 30000);

    // Send final duration and exit page when leaving or hiding tab
    var exitSent = false;
    function handleExit() {
        if (!exitSent) {
            sendHeartbeat();
        }
    }

    window.addEventListener('pagehide', handleExit);
    window.addEventListener('beforeunload', handleExit);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            sendHeartbeat();
        }
    });

})();
