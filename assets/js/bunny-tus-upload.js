(function () {
    'use strict';

    function setText(el, text) {
        if (el) el.textContent = text;
    }

    window.edexcelBunnyUpload = function (options) {
        var form = options.form;
        var fileInput = options.fileInput;
        var progressEl = options.progressEl;
        var statusEl = options.statusEl;
        var extra = options.extra || {};
        var endpoint = options.endpoint;
        var completeEndpoint = options.completeEndpoint;
        var csrf = options.csrf;
        var onDone = options.onDone;

        form.setAttribute('data-ui-skip', '1');
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (typeof tus === 'undefined') {
                var missing = 'Uploader library failed to load. Check your connection and try again.';
                setText(statusEl, missing);
                if (window.showToast) window.showToast(missing, 'error');
                return;
            }
            var file = fileInput && fileInput.files && fileInput.files[0];
            if (!file) {
                var choose = 'Choose a video file first.';
                setText(statusEl, choose);
                if (window.showToast) window.showToast(choose, 'warning');
                return;
            }
            var body = new FormData();
            body.append('csrf_token', csrf);
            Object.keys(extra).forEach(function (key) {
                var val = extra[key];
                if (typeof val === 'function') val = val();
                body.append(key, val == null ? '' : String(val));
            });
            var titleField = form.querySelector('[name="title"]');
            var descField = form.querySelector('[name="description"]');
            if (titleField) body.append('title', titleField.value);
            if (descField) body.append('description', descField.value);

            setText(statusEl, 'Preparing upload…');
            if (progressEl) progressEl.style.width = '0%';

            fetch(endpoint, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (pack) {
                    if (!pack.data || !pack.data.ok || !pack.data.tus) {
                        throw new Error((pack.data && pack.data.error) || 'Could not start the upload.');
                    }
                    var tusInfo = pack.data.tus;
                    var upload = new tus.Upload(file, {
                        endpoint: tusInfo.endpoint,
                        retryDelays: [0, 3000, 5000, 10000, 20000, 60000],
                        chunkSize: 5 * 1024 * 1024,
                        headers: {
                            AuthorizationSignature: tusInfo.signature,
                            AuthorizationExpire: String(tusInfo.expiration),
                            LibraryId: String(tusInfo.libraryId),
                            VideoId: String(tusInfo.videoId)
                        },
                        metadata: {
                            filetype: file.type || 'video/mp4',
                            title: (titleField && titleField.value) || file.name
                        },
                        onError: function () {
                            var failed = 'Upload failed. You can try again.';
                            setText(statusEl, failed);
                            if (window.showToast) window.showToast(failed, 'error');
                        },
                        onProgress: function (sent, total) {
                            var pct = total ? Math.round((sent / total) * 100) : 0;
                            if (progressEl) progressEl.style.width = pct + '%';
                            setText(statusEl, 'Uploading… ' + pct + '%');
                        },
                        onSuccess: function () {
                            var done = new FormData();
                            done.append('csrf_token', csrf);
                            done.append('kind', extra.kind || 'recording');
                            done.append('id', String(pack.data.id || ''));
                            done.append('video_id', String(tusInfo.videoId));
                            fetch(completeEndpoint, { method: 'POST', body: done, credentials: 'same-origin' })
                                .finally(function () {
                                    setText(statusEl, 'Uploaded. Bunny is processing the video.');
                                    if (typeof onDone === 'function') onDone(pack.data);
                                });
                        }
                    });
                    upload.start();
                })
                .catch(function (err) {
                    var message = (err && err.message) || 'Upload could not start.';
                    setText(statusEl, message);
                    if (window.showToast) {
                        window.showToast(message, 'error');
                    }
                });
        });
    };
})();
