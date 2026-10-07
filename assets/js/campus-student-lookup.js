(function () {
    'use strict';

    function digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function looksLikeLkMobile(value) {
        var d = digits(value);
        return /^07\d{8}$/.test(d) || /^947\d{8}$/.test(d);
    }

    function looksLikeEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setText(el, text, className) {
        if (!el) {
            return;
        }
        el.textContent = text || '';
        el.className = 'form-text js-walkin-status' + (className ? ' ' + className : '');
    }

    function initForm(form) {
        var searchInput = form.querySelector('.js-student-search-input');
        var searchResults = form.querySelector('.js-student-search-results');
        var searchClearBtn = form.querySelector('.js-student-search-clear');
        var selectedBanner = form.querySelector('.js-selected-student-banner');
        var selectedNameEl = form.querySelector('.js-selected-student-name');
        var selectedMetaEl = form.querySelector('.js-selected-student-meta');
        var clearSelectionBtn = form.querySelector('.js-walkin-clear-selection');
        var studentIdInput = form.querySelector('.js-walkin-student-id');
        var googleEmailInput = form.querySelector('.js-walkin-google-email-val');
        var emailInput = form.querySelector('.js-walkin-email') || googleEmailInput;

        var whatsapp = form.querySelector('.js-walkin-whatsapp');
        var name = form.querySelector('.js-walkin-name');
        var parentName = form.querySelector('.js-walkin-parent-name');
        var parentWa = form.querySelector('.js-walkin-parent-whatsapp');
        var status = form.querySelector('.js-walkin-status');
        var submitBtn = form.querySelector('button[type="submit"]') || form.querySelector('.btn-success');
        var submitLabel = form.querySelector('.js-walkin-submit-label');
        var smsWrap = form.querySelector('.js-walkin-sms-wrap');
        var smsBtn = form.querySelector('.js-walkin-send-sms');
        var smsStatus = form.querySelector('.js-walkin-sms-status');
        var lookupUrl = form.getAttribute('data-lookup-url') || '';
        var smsUrl = form.getAttribute('data-sms-url') || '';

        if (!lookupUrl) {
            return;
        }

        var filledFromLookup = false;
        var phoneTimer = null;
        var phoneController = null;
        var emailTimer = null;
        var emailController = null;
        var searchTimer = null;
        var searchController = null;
        var smsBusy = false;
        var selectedStudent = null;
        var settingFields = false;

        function classId() {
            var input = form.querySelector('input[name="class_id"]');
            return input ? String(input.value || '') : '';
        }

        function timetableId() {
            var input = form.querySelector('input[name="timetable_id"]');
            return input ? String(input.value || '') : '';
        }

        function csrfToken() {
            var input = form.querySelector('input[name="csrf_token"]');
            return input ? String(input.value || '') : '';
        }

        function showSms(show) {
            if (!smsWrap) {
                return;
            }
            smsWrap.classList.toggle('d-none', !show);
        }

        function setSmsText(text, className) {
            if (!smsStatus) {
                return;
            }
            smsStatus.textContent = text || '';
            smsStatus.className = 'form-text js-walkin-sms-status' + (className ? ' ' + className : '');
        }

        function syncPhoneRequired() {
            if (!whatsapp) {
                return;
            }
            var emailOk = !!(emailInput && emailInput.classList.contains('js-walkin-email') && looksLikeEmail(emailInput.value));
            var selected = !!(studentIdInput && String(studentIdInput.value || '').trim());
            whatsapp.required = !(emailOk || selected);
        }

        function selectStudent(data) {
            settingFields = true;
            selectedStudent = data;
            filledFromLookup = true;

            if (studentIdInput) {
                studentIdInput.value = data.id || data.student_id || '';
            }
            if (googleEmailInput) {
                googleEmailInput.value = data.google_email || googleEmailInput.value || '';
            }
            if (name) {
                name.value = data.full_name || '';
                name.required = !(data.full_name || '').trim();
            }
            if (whatsapp) {
                whatsapp.value = data.whatsapp || '';
            }
            if (parentName) {
                parentName.value = data.parent_name || '';
            }
            if (parentWa) {
                parentWa.value = data.parent_whatsapp || '';
            }

            // Update banner
            if (selectedBanner) {
                selectedBanner.classList.remove('d-none');
            }
            if (selectedNameEl) {
                selectedNameEl.textContent = data.full_name || 'Selected Student';
            }
            if (selectedMetaEl) {
                var meta = [];
                if (data.google_email) {
                    meta.push('<span class="me-2"><i class="bi bi-google text-danger me-1"></i>' + escapeHtml(data.google_email) + '</span>');
                }
                if (data.whatsapp) {
                    meta.push('<span class="me-2"><i class="bi bi-telephone me-1"></i>' + escapeHtml(data.whatsapp) + '</span>');
                }
                if (data.parent_name) {
                    meta.push('<span><i class="bi bi-person me-1"></i>Parent: ' + escapeHtml(data.parent_name) + '</span>');
                }
                selectedMetaEl.innerHTML = meta.join(' · ') || 'Existing student account';
            }

            // Submit button & status
            if (data.already_enrolled) {
                if (submitLabel) {
                    submitLabel.textContent = 'Already in this class';
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                setText(status, 'This student is already enrolled in this class.', 'text-warning');
            } else {
                if (submitLabel) {
                    submitLabel.textContent = 'Add to this class';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                setText(status, 'Student selected. Click "Add to this class" below.', 'text-success');
            }

            // SMS option
            if (smsUrl && timetableId() && data.whatsapp && looksLikeLkMobile(data.whatsapp)) {
                showSms(true);
                setSmsText('Send a normal SMS with a tap-to-join class link.', 'text-muted');
            } else {
                showSms(false);
                setSmsText('', '');
            }

            // Hide dropdown
            if (searchResults) {
                searchResults.style.display = 'none';
            }
            syncPhoneRequired();
            settingFields = false;
        }

        function clearSelection() {
            settingFields = true;
            selectedStudent = null;
            filledFromLookup = false;

            if (studentIdInput) {
                studentIdInput.value = '';
            }
            if (googleEmailInput) {
                googleEmailInput.value = '';
            }
            if (selectedBanner) {
                selectedBanner.classList.add('d-none');
            }
            if (name) {
                name.value = '';
                name.required = true;
            }
            if (whatsapp) {
                whatsapp.value = '';
            }
            if (parentName) {
                parentName.value = '';
            }
            if (parentWa) {
                parentWa.value = '';
            }
            if (searchInput) {
                searchInput.value = '';
            }
            if (searchClearBtn) {
                searchClearBtn.classList.add('d-none');
            }
            if (searchResults) {
                searchResults.style.display = 'none';
            }
            if (submitLabel) {
                submitLabel.textContent = 'Register and add to class';
            }
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            showSms(false);
            setSmsText('', '');
            setText(status, '', '');
            settingFields = false;
            syncPhoneRequired();
        }

        function applyNew() {
            if (filledFromLookup) {
                if (name) {
                    name.value = '';
                }
                if (parentName) {
                    parentName.value = '';
                }
                if (parentWa) {
                    parentWa.value = '';
                }
                filledFromLookup = false;
            }
            if (studentIdInput) {
                studentIdInput.value = '';
            }
            if (selectedBanner) {
                selectedBanner.classList.add('d-none');
            }
            if (name) {
                name.required = true;
            }
            if (submitLabel) {
                submitLabel.textContent = 'Register and add to class';
            }
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            showSms(false);
            setSmsText('', '');
            setText(status, 'New student. Enter their name to register and add to this class. The join SMS is sent after you register them.', 'text-muted');
            syncPhoneRequired();
        }

        // --- Direct Phone Lookup ---
        function lookupPhone() {
            if (selectedStudent) {
                return;
            }
            if (!whatsapp) {
                return;
            }
            var phone = whatsapp.value;
            if (!looksLikeLkMobile(phone)) {
                if (filledFromLookup) {
                    clearSelection();
                }
                return;
            }

            setText(status, 'Checking this number…', 'text-muted');
            if (phoneController) {
                phoneController.abort();
            }
            phoneController = new AbortController();
            var url = lookupUrl + (lookupUrl.indexOf('?') >= 0 ? '&' : '?') +
                'whatsapp=' + encodeURIComponent(phone) +
                (classId() ? '&class_id=' + encodeURIComponent(classId()) : '');

            fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: phoneController.signal
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (!data || !data.ok) {
                    setText(status, '', '');
                    return;
                }
                if (data.found) {
                    selectStudent(data);
                } else {
                    applyNew();
                }
            }).catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                setText(status, '', '');
            });
        }

        function schedulePhoneLookup() {
            if (phoneTimer) {
                clearTimeout(phoneTimer);
            }
            phoneTimer = setTimeout(lookupPhone, 350);
        }

        function lookupEmail() {
            if (!emailInput || !emailInput.classList.contains('js-walkin-email')) {
                return;
            }
            var email = String(emailInput.value || '').trim();
            syncPhoneRequired();
            if (selectedStudent || settingFields || !looksLikeEmail(email)) {
                return;
            }

            setText(status, 'Checking this email…', 'text-muted');
            if (emailController) {
                emailController.abort();
            }
            emailController = new AbortController();
            var url = lookupUrl + (lookupUrl.indexOf('?') >= 0 ? '&' : '?') +
                'q=' + encodeURIComponent(email) +
                (classId() ? '&class_id=' + encodeURIComponent(classId()) : '');

            fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: emailController.signal
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (selectedStudent || settingFields) {
                    return;
                }
                if (!data || !data.ok) {
                    setText(status, '', '');
                    return;
                }
                var results = data.results || [];
                var typed = email.toLowerCase();
                var exact = results.filter(function (st) {
                    return String(st.google_email || '').trim().toLowerCase() === typed;
                });
                var pick = exact.length === 1 ? exact[0] : (exact.length === 0 && results.length === 1 ? results[0] : null);
                if (pick) {
                    selectStudent(pick);
                    return;
                }
                if (!results.length) {
                    setText(status, 'No student account uses this email. Enter a mobile number to register a new student.', 'text-warning');
                    return;
                }
                setText(status, 'More than one student matches. Choose them from the search box.', 'text-warning');
            }).catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                setText(status, '', '');
            });
        }

        function scheduleEmailLookup() {
            if (emailTimer) {
                clearTimeout(emailTimer);
            }
            emailTimer = setTimeout(lookupEmail, 350);
        }

        // --- Live Search Input (Google email, name, phone) ---
        function runSearch(q) {
            if (!searchResults) {
                return;
            }
            if (searchController) {
                searchController.abort();
            }
            searchController = new AbortController();

            var url = lookupUrl + (lookupUrl.indexOf('?') >= 0 ? '&' : '?') +
                'q=' + encodeURIComponent(q) +
                (classId() ? '&class_id=' + encodeURIComponent(classId()) : '');

            searchResults.innerHTML = '<div class="p-3 text-center text-muted small"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Searching students…</div>';
            searchResults.style.display = 'block';

            fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: searchController.signal
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (!data || !data.ok || !data.results || data.results.length === 0) {
                    searchResults.innerHTML = '<div class="p-3 text-center text-muted small"><i class="bi bi-info-circle me-1"></i>No student found matching "<strong>' + escapeHtml(q) + '</strong>".<br><span class="text-secondary">Enter details below to register a new student.</span></div>';
                    searchResults.style.display = 'block';
                    return;
                }

                var listHtml = '<div class="list-group list-group-flush">';
                data.results.forEach(function (st, idx) {
                    var enrolledBadge = st.already_enrolled
                        ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle small">Already in class</span>'
                        : '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle small"><i class="bi bi-plus me-1"></i>Select</span>';

                    var metaItems = [];
                    if (st.google_email) {
                        metaItems.push('<span><i class="bi bi-google text-danger me-1"></i>' + escapeHtml(st.google_email) + '</span>');
                    }
                    if (st.whatsapp) {
                        metaItems.push('<span><i class="bi bi-telephone me-1"></i>' + escapeHtml(st.whatsapp) + '</span>');
                    }
                    if (st.parent_name) {
                        metaItems.push('<span class="text-truncate" style="max-width:180px;"><i class="bi bi-person me-1"></i>Parent: ' + escapeHtml(st.parent_name) + '</span>');
                    }

                    listHtml += '<a href="#" class="list-group-item list-group-item-action py-2 px-3 js-search-result-item" data-index="' + idx + '">' +
                        '<div class="d-flex justify-content-between align-items-center mb-1">' +
                            '<span class="fw-semibold text-body">' + escapeHtml(st.full_name || 'Student #' + st.id) + '</span>' +
                            enrolledBadge +
                        '</div>' +
                        '<div class="d-flex flex-wrap gap-2 small text-muted">' +
                            metaItems.join(' · ') +
                        '</div>' +
                    '</a>';
                });
                listHtml += '</div>';

                searchResults.innerHTML = listHtml;
                searchResults.style.display = 'block';

                // Add click events to result items
                searchResults.querySelectorAll('.js-search-result-item').forEach(function (item) {
                    item.addEventListener('click', function (e) {
                        e.preventDefault();
                        var idx = parseInt(item.getAttribute('data-index'), 10);
                        if (!isNaN(idx) && data.results[idx]) {
                            selectStudent(data.results[idx]);
                            if (searchInput) {
                                searchInput.value = data.results[idx].full_name || '';
                            }
                            if (searchClearBtn) {
                                searchClearBtn.classList.remove('d-none');
                            }
                        }
                    });
                });
            }).catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (searchResults) {
                    searchResults.innerHTML = '<div class="p-3 text-center text-danger small">Search failed. Try again.</div>';
                }
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var q = (searchInput.value || '').trim();
                if (searchClearBtn) {
                    searchClearBtn.classList.toggle('d-none', !q);
                }
                if (searchTimer) {
                    clearTimeout(searchTimer);
                }
                if (q.length < 2) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        searchResults.style.display = 'none';
                    }
                    return;
                }
                searchTimer = setTimeout(function () {
                    runSearch(q);
                }, 280);
            });

            searchInput.addEventListener('focus', function () {
                var q = (searchInput.value || '').trim();
                if (q.length >= 2 && searchResults && searchResults.innerHTML.trim() !== '') {
                    searchResults.style.display = 'block';
                }
            });

            // Close search results when clicking outside
            document.addEventListener('click', function (e) {
                if (!form.contains(e.target)) {
                    if (searchResults) {
                        searchResults.style.display = 'none';
                    }
                }
            });

            // Escape key closes search results
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    if (searchResults) {
                        searchResults.style.display = 'none';
                    }
                }
            });
        }

        if (searchClearBtn) {
            searchClearBtn.addEventListener('click', function () {
                if (searchInput) {
                    searchInput.value = '';
                }
                searchClearBtn.classList.add('d-none');
                if (searchResults) {
                    searchResults.innerHTML = '';
                    searchResults.style.display = 'none';
                }
                if (selectedStudent) {
                    clearSelection();
                }
            });
        }

        if (clearSelectionBtn) {
            clearSelectionBtn.addEventListener('click', function () {
                clearSelection();
                if (searchInput) {
                    searchInput.focus();
                }
            });
        }

        // --- SMS Send Handler ---
        function sendJoinSms() {
            if (!smsUrl || smsBusy) {
                return;
            }
            var phone = whatsapp ? whatsapp.value : '';
            if (!looksLikeLkMobile(phone) || !timetableId()) {
                setSmsText('Enter a valid mobile number first.', 'text-danger');
                return;
            }
            smsBusy = true;
            if (smsBtn) {
                smsBtn.disabled = true;
            }
            setSmsText('Sending class join SMS…', 'text-muted');
            var body = new URLSearchParams();
            body.set('csrf_token', csrfToken());
            body.set('timetable_id', timetableId());
            body.set('whatsapp', phone);
            fetch(smsUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body.toString()
            }).then(function (res) {
                return res.json().then(function (data) {
                    return { status: res.status, data: data };
                });
            }).then(function (result) {
                var data = result.data || {};
                if (data.ok) {
                    setSmsText(data.message || 'Class join link sent by SMS.', 'text-success');
                    return;
                }
                setSmsText(data.message || 'Could not send the SMS.', 'text-danger');
            }).catch(function () {
                setSmsText('Could not send the SMS. Try again.', 'text-danger');
            }).finally(function () {
                smsBusy = false;
                if (smsBtn) {
                    smsBtn.disabled = false;
                }
            });
        }

        if (whatsapp) {
            whatsapp.addEventListener('input', schedulePhoneLookup);
            whatsapp.addEventListener('blur', lookupPhone);
            whatsapp.addEventListener('change', lookupPhone);
        }

        if (emailInput && emailInput.classList.contains('js-walkin-email')) {
            emailInput.addEventListener('input', function () {
                if (settingFields) {
                    return;
                }
                if (selectedStudent) {
                    var typed = String(emailInput.value || '').trim().toLowerCase();
                    var current = String(selectedStudent.google_email || '').trim().toLowerCase();
                    if (typed !== current) {
                        selectedStudent = null;
                        filledFromLookup = false;
                        if (studentIdInput) {
                            studentIdInput.value = '';
                        }
                        if (selectedBanner) {
                            selectedBanner.classList.add('d-none');
                        }
                        if (submitLabel) {
                            submitLabel.textContent = 'Register and add to class';
                        }
                        if (submitBtn) {
                            submitBtn.disabled = false;
                        }
                    }
                }
                syncPhoneRequired();
                scheduleEmailLookup();
            });
            emailInput.addEventListener('blur', lookupEmail);
        }

        form.addEventListener('submit', function (e) {
            syncPhoneRequired();
            if (!form.querySelector('.js-walkin-email')) {
                return;
            }
            var email = emailInput ? String(emailInput.value || '').trim() : '';
            var phone = whatsapp ? String(whatsapp.value || '').trim() : '';
            var selected = !!(studentIdInput && String(studentIdInput.value || '').trim());
            if (selected || looksLikeEmail(email) || looksLikeLkMobile(phone)) {
                return;
            }
            e.preventDefault();
            var message = email !== ''
                ? 'Enter a full email address, such as name@gmail.com, or a mobile number.'
                : 'Enter the student’s Google email, or a mobile number to register them.';
            setText(status, message, 'text-danger');
            if (window.showToast) {
                window.showToast(message, 'warning');
            }
        });

        if (smsBtn && smsUrl) {
            smsBtn.addEventListener('click', sendJoinSms);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form.js-walkin-form').forEach(initForm);
    });
})();
