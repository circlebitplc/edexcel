(function () {
    'use strict';

    var script = document.currentScript;
    if (!script || script.getAttribute('data-student-session') !== '1') {
        var found = document.querySelector('script[data-student-session="1"]');
        script = found || script;
    }
    if (!script) {
        return;
    }
    var pingUrl = script.getAttribute('data-ping-url') || '';
    var loginUrl = script.getAttribute('data-login-url') || '/index.php#student-login';
    if (!pingUrl) {
        return;
    }

    var kicked = false;
    function goLogin() {
        if (kicked) {
            return;
        }
        kicked = true;
        window.location.href = loginUrl;
    }

    function ping() {
        if (document.hidden) {
            return;
        }
        fetch(pingUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'fetch'
            }
        }).then(function (res) {
            if (res.status === 401) {
                return res.json().then(function (body) {
                    window.location.href = (body && body.redirect) ? body.redirect : loginUrl;
                }).catch(goLogin);
            }
        }).catch(function () {});
    }

    ping();
    setInterval(ping, 8000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            ping();
        }
    });
})();
