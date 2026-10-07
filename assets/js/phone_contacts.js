/**
 * Phone Contacts Management & Controlled SMS - Frontend Controller
 * Edexcel College
 */

(function () {
    'use strict';

    function initPhoneContacts() {
        const csrfToken = window.CSRF_TOKEN || '';
        let allowedLocations = Array.isArray(window.INITIAL_LOCATIONS) ? [...window.INITIAL_LOCATIONS] : ['Kandy', 'Kurunegala', 'Online'];
        const baseUrl = window.BASE_URL ? window.BASE_URL.replace(/\/$/, '') + '/' : '/';
        const ajaxActionUrl = baseUrl + 'ajax/phone_contacts_action.php';

    let currentPage = 1;
    let totalPages = 1;
    let selectedContactIds = new Set();
    let isAllFilteredSelected = false;
    let totalFilteredCount = 0;

    // Elements
    const tableBody = document.getElementById('contactsTableBody');
    const statTotalAll = document.getElementById('statTotalAll');
    const statTotalFiltered = document.getElementById('statTotalFiltered');
    const statSelectedCount = document.getElementById('statSelectedCount');
    const btnSelectAllFilteredCount = document.getElementById('btnSelectAllFilteredCount');
    const checkSelectPage = document.getElementById('checkSelectPage');
    const btnSelectAllFiltered = document.getElementById('btnSelectAllFiltered');
    const btnClearSelection = document.getElementById('btnClearSelection');
    const btnBulkDelete = document.getElementById('btnBulkDelete');
    const btnBulkDeleteCount = document.getElementById('btnBulkDeleteCount');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationControls = document.getElementById('paginationControls');
    const pageAlert = document.getElementById('pageAlert');

    // Filter elements
    const filterForm = document.getElementById('filterForm');
    const filterSearch = document.getElementById('filterSearch');
    const filterSourceGroup = document.getElementById('filterSourceGroup');
    const filterExamYear = document.getElementById('filterExamYear');
    const filterExamType = document.getElementById('filterExamType');
    const filterLocation = document.getElementById('filterLocation');
    const filterSmsStatus = document.getElementById('filterSmsStatus');
    const filterSmsOptOut = document.getElementById('filterSmsOptOut');
    const filterStatus = document.getElementById('filterStatus');
    const btnResetFilters = document.getElementById('btnResetFilters');

    // Modal elements
    const importModalEl = document.getElementById('importCsvModal');
    const sendSmsModalEl = document.getElementById('sendSmsModal');
    const locationsModalEl = document.getElementById('locationsModal');
    const contactEditModalEl = document.getElementById('contactEditModal');

    function getBootstrapModal(el) {
        if (!el || typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) return null;
        return window.bootstrap.Modal.getOrCreateInstance 
            ? window.bootstrap.Modal.getOrCreateInstance(el) 
            : (window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el));
    }

    function showAlert(msg, type = 'info', el = pageAlert) {
        if (!el) return;
        el.className = `alert alert-${type} small mb-4`;
        el.innerHTML = msg;
        el.classList.remove('d-none');
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideAlert(el = pageAlert) {
        if (!el) return;
        el.classList.add('d-none');
        el.innerHTML = '';
    }

    function getFilterParams() {
        return {
            search: filterSearch ? filterSearch.value.trim() : '',
            source_group: filterSourceGroup ? filterSourceGroup.value.trim() : '',
            exam_year: filterExamYear ? filterExamYear.value : '',
            exam_type: filterExamType ? filterExamType.value : '',
            location: filterLocation ? filterLocation.value : '',
            sms_status: filterSmsStatus ? filterSmsStatus.value : '',
            sms_opt_out: filterSmsOptOut ? filterSmsOptOut.value : '',
            status: filterStatus ? filterStatus.value : ''
        };
    }

    // ─────────────────────────────────────────────────────────────────
    // DATA LOADING & RENDERING
    // ─────────────────────────────────────────────────────────────────
    async function loadContacts(page = 1) {
        currentPage = page;
        if (tableBody) {
            tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading contacts...</td></tr>`;
        }

        const params = new URLSearchParams(getFilterParams());
        params.append('action', 'load_contacts');
        params.append('page', String(page));
        params.append('per_page', '50');

        try {
            const resp = await fetch(`/ajax/phone_contacts_action.php?${params.toString()}`);
            const data = await resp.json();

            if (!data.success) {
                showAlert(data.error || 'Failed to load contacts.', 'danger');
                if (tableBody) tableBody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger">Error: ${escapeHtml(data.error || 'Unknown error')}</td></tr>`;
                return;
            }

            renderTable(data);
        } catch (err) {
            console.error('loadContacts error:', err);
            showAlert('Network error while loading contacts.', 'danger');
        }
    }

    function renderTable(data) {
        const contacts = data.contacts || [];
        totalFilteredCount = data.total_filtered || 0;
        totalPages = data.total_pages || 1;

        if (statTotalAll) statTotalAll.textContent = Number(data.total_all || 0).toLocaleString();
        if (statTotalFiltered) statTotalFiltered.textContent = Number(totalFilteredCount).toLocaleString();
        if (btnSelectAllFilteredCount) btnSelectAllFilteredCount.textContent = Number(totalFilteredCount).toLocaleString();

        if (contacts.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                        No phone contacts found matching the selected filters.
                    </td>
                </tr>`;
            if (paginationInfo) paginationInfo.textContent = 'Showing 0 to 0 of 0 contacts';
            if (paginationControls) paginationControls.innerHTML = '';
            return;
        }

        let html = '';
        contacts.forEach(c => {
            const id = Number(c.id);
            const isChecked = isAllFilteredSelected || selectedContactIds.has(id);
            const nameDisplay = c.name ? escapeHtml(c.name) : '<span class="badge bg-secondary bg-opacity-75 text-light fw-normal">Unknown</span>';
            const schoolDisplay = c.school ? escapeHtml(c.school) : '<span class="text-muted">—</span>';
            
            let statusBadge = '<span class="badge bg-success bg-opacity-25 text-success border border-success">Allowed</span>';
            if (c.status === 'archived') {
                statusBadge = '<span class="badge bg-secondary text-white"><i class="bi bi-archive"></i> Archived</span>';
            } else if (c.sms_status === 'blocked') {
                statusBadge = '<span class="badge bg-dark text-white"><i class="bi bi-slash-circle"></i> Blocked</span>';
            } else if (c.sms_status === 'opted_out' || Number(c.sms_opt_out) === 1) {
                statusBadge = '<span class="badge bg-danger text-white"><i class="bi bi-bell-slash"></i> Opted Out</span>';
            }
            const conflictBadge = c.name_conflict
                ? ` <span class="badge bg-warning text-dark" title="${escapeHtml(c.name_conflict)}"><i class="bi bi-exclamation-triangle"></i> Conflict</span>`
                : '';

            html += `
                <tr data-contact-id="${id}" class="${isChecked ? 'table-active' : ''}">
                    <td class="text-center">
                        <input class="form-check-input contact-select-check" type="checkbox" value="${id}" ${isChecked ? 'checked' : ''}>
                    </td>
                    <td><strong class="text-dark">${nameDisplay}</strong>${conflictBadge}</td>
                    <td>
                        <span class="font-monospace text-dark fw-semibold">${escapeHtml(c.display_phone || c.phone)}</span>
                        <div class="text-muted small" style="font-size:0.75rem;">+${escapeHtml(c.normalized_phone)}</div>
                    </td>
                    <td>${c.summary_types ? `<span class="badge bg-info text-dark">${escapeHtml(c.summary_types)}</span>` : '<span class="text-muted">—</span>'}</td>
                    <td>${c.summary_years ? `<span class="badge bg-light text-dark border">${escapeHtml(c.summary_years)}</span>` : '<span class="text-muted">—</span>'}</td>
                    <td>${c.summary_locations ? `<span class="badge bg-light text-dark border">${escapeHtml(c.summary_locations)}</span>` : '<span class="text-muted">—</span>'}</td>
                    <td><small class="text-muted">${schoolDisplay}</small></td>
                    <td><small class="text-muted">${c.summary_groups ? escapeHtml(c.summary_groups) : '—'}</small></td>
                    <td>${statusBadge}</td>
                    <td class="text-end text-nowrap">
                        <a href="${baseUrl}admin/phone_contact_view.php?id=${id}" class="btn btn-outline-primary btn-sm py-0 px-2" title="View details and records">
                            <i class="bi bi-eye"></i> View
                        </a>
                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-delete-contact ms-1" data-id="${id}" data-name="${escapeHtml(c.name || c.display_phone || c.phone)}" title="Permanently delete contact" onclick="window.deleteSinglePhoneContact(${id}, '${escapeJsString(c.name || c.display_phone || c.phone)}', this, event)">
                            <i class="bi bi-trash" style="pointer-events: none;"></i> Delete
                        </button>
                    </td>
                </tr>
            `;
        });

        tableBody.innerHTML = html;
        updateSelectionUI();
        renderPagination(data);
    }

    function renderPagination(data) {
        if (!paginationInfo || !paginationControls) return;

        const page = data.page || 1;
        const perPage = data.per_page || 50;
        const total = data.total_filtered || 0;
        const start = (page - 1) * perPage + 1;
        const end = Math.min(page * perPage, total);

        paginationInfo.textContent = `Showing ${start.toLocaleString()} to ${end.toLocaleString()} of ${total.toLocaleString()} contacts`;

        let pagesHtml = '';
        // Previous
        pagesHtml += `<li class="page-item ${page <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page - 1}">Previous</a>
        </li>`;

        // Page numbers
        const maxPagesToShow = 5;
        let startPage = Math.max(1, page - 2);
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            pagesHtml += `<li class="page-item ${i === page ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        // Next
        pagesHtml += `<li class="page-item ${page >= totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page + 1}">Next</a>
        </li>`;

        paginationControls.innerHTML = pagesHtml;
    }

    // ─────────────────────────────────────────────────────────────────
    // SELECTION MANAGEMENT
    // ─────────────────────────────────────────────────────────────────
    function updateSelectionUI() {
        let count = 0;
        if (isAllFilteredSelected) {
            count = totalFilteredCount;
        } else {
            count = selectedContactIds.size;
        }

        if (statSelectedCount) statSelectedCount.textContent = count.toLocaleString();

        if (count > 0 && btnClearSelection) {
            btnClearSelection.style.display = 'inline-block';
        } else if (btnClearSelection) {
            btnClearSelection.style.display = 'none';
        }

        if (btnBulkDelete) {
            btnBulkDelete.disabled = false;
            if (count > 0) {
                btnBulkDelete.classList.remove('btn-outline-danger');
                btnBulkDelete.classList.add('btn-danger');
            } else {
                btnBulkDelete.classList.remove('btn-danger');
                btnBulkDelete.classList.add('btn-outline-danger');
            }
            if (btnBulkDeleteCount) btnBulkDeleteCount.textContent = count.toLocaleString();
        }

        // Check if all on current page are checked
        if (checkSelectPage) {
            const pageChecks = document.querySelectorAll('.contact-select-check');
            const checkedOnPage = document.querySelectorAll('.contact-select-check:checked');
            checkSelectPage.checked = pageChecks.length > 0 && pageChecks.length === checkedOnPage.length;
        }
    }

    // Exposed single delete function for direct onclick and delegation
    window.deleteSinglePhoneContact = async function (contactId, contactName, delBtn, e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        contactId = Number(contactId);
        if (!contactId || contactId <= 0) {
            showAlert('Invalid contact ID.', 'danger');
            return;
        }

        let confirmed = false;
        try {
            confirmed = window.confirm(`Are you sure you want to permanently delete ${contactName || 'this contact'}?\n\nThis will permanently delete the contact and all associated academic records.\nThis action CANNOT be undone.`);
        } catch (err) {
            confirmed = true;
        }
        if (!confirmed) return;

        if (delBtn) {
            delBtn.disabled = true;
            delBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
        }

        const formData = new FormData();
        formData.append('action', 'delete_contact');
        formData.append('csrf_token', window.CSRF_TOKEN || csrfToken);
        formData.append('contact_id', String(contactId));

        try {
            const resp = await fetch(ajaxActionUrl, {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (!data.success) {
                if (delBtn) {
                    delBtn.disabled = false;
                    delBtn.innerHTML = '<i class="bi bi-trash" style="pointer-events: none;"></i> Delete';
                }
                showAlert(data.error || 'Failed to delete contact.', 'danger');
                return;
            }

            selectedContactIds.delete(contactId);
            updateSelectionUI();
            showAlert('Contact was permanently deleted.', 'success');

            const tr = delBtn ? delBtn.closest('tr') : null;
            if (tr) {
                tr.remove();
            }
            loadContacts(currentPage);
        } catch (err) {
            console.error(err);
            if (delBtn) {
                delBtn.disabled = false;
                delBtn.innerHTML = '<i class="bi bi-trash" style="pointer-events: none;"></i> Delete';
            }
            showAlert('Network error while deleting contact.', 'danger');
        }
    };

    if (tableBody) {
        tableBody.addEventListener('change', function (e) {
            if (e.target.classList.contains('contact-select-check')) {
                const id = Number(e.target.value);
                const tr = e.target.closest('tr');
                if (e.target.checked) {
                    selectedContactIds.add(id);
                    if (tr) tr.classList.add('table-active');
                } else {
                    selectedContactIds.delete(id);
                    isAllFilteredSelected = false;
                    if (tr) tr.classList.remove('table-active');
                }
                updateSelectionUI();
            }
        });

        // Single contact delete action delegation
        tableBody.addEventListener('click', async function (e) {
            const delBtn = e.target.closest('.btn-delete-contact');
            if (!delBtn) return;
            const contactId = Number(delBtn.getAttribute('data-id'));
            const contactName = delBtn.getAttribute('data-name') || 'this contact';
            window.deleteSinglePhoneContact(contactId, contactName, delBtn, e);
        });
    }

    if (checkSelectPage) {
        checkSelectPage.addEventListener('change', function () {
            const isChecked = checkSelectPage.checked;
            document.querySelectorAll('.contact-select-check').forEach(chk => {
                chk.checked = isChecked;
                const id = Number(chk.value);
                const tr = chk.closest('tr');
                if (isChecked) {
                    selectedContactIds.add(id);
                    if (tr) tr.classList.add('table-active');
                } else {
                    selectedContactIds.delete(id);
                    if (tr) tr.classList.remove('table-active');
                }
            });
            isAllFilteredSelected = false;
            updateSelectionUI();
        });
    }

    if (btnSelectAllFiltered) {
        btnSelectAllFiltered.addEventListener('click', function () {
            isAllFilteredSelected = true;
            document.querySelectorAll('.contact-select-check').forEach(chk => {
                chk.checked = true;
                const tr = chk.closest('tr');
                if (tr) tr.classList.add('table-active');
            });
            updateSelectionUI();
            showAlert(`Selected all ${totalFilteredCount.toLocaleString()} filtered contacts.`, 'info');
        });
    }

    if (btnClearSelection) {
        btnClearSelection.addEventListener('click', function () {
            selectedContactIds.clear();
            isAllFilteredSelected = false;
            if (checkSelectPage) checkSelectPage.checked = false;
            document.querySelectorAll('.contact-select-check').forEach(chk => {
                chk.checked = false;
                const tr = chk.closest('tr');
                if (tr) tr.classList.remove('table-active');
            });
            updateSelectionUI();
        });
    }

    // Bulk Delete Action
    if (btnBulkDelete) {
        btnBulkDelete.addEventListener('click', async function () {
            const count = isAllFilteredSelected ? totalFilteredCount : selectedContactIds.size;
            if (count <= 0) {
                showAlert('Please select one or more contacts by checking the boxes in the table first, or click "Select All Filtered" to delete all matching contacts.', 'warning');
                return;
            }

            const targetDesc = isAllFilteredSelected
                ? `ALL ${count.toLocaleString()} filtered contacts`
                : `${count.toLocaleString()} selected contact(s)`;

            let confirmed = false;
            try {
                confirmed = window.confirm(`Are you sure you want to permanently delete ${targetDesc}?\n\nThis will permanently delete these contacts and all their associated exam/academic records.\nThis action CANNOT be undone.`);
            } catch (err) {
                confirmed = true;
            }
            if (!confirmed) return;

            btnBulkDelete.disabled = true;
            btnBulkDelete.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

            const formData = new FormData();
            formData.append('action', 'delete_contacts_bulk');
            formData.append('csrf_token', csrfToken);
            formData.append('all_filtered', isAllFilteredSelected ? '1' : '0');
            formData.append('filters', JSON.stringify(getFilterParams()));
            if (!isAllFilteredSelected) {
                Array.from(selectedContactIds).forEach(id => {
                    formData.append('selected_ids[]', String(id));
                });
            }

            try {
                const resp = await fetch(ajaxActionUrl, {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                btnBulkDelete.disabled = false;
                btnBulkDelete.innerHTML = '<i class="bi bi-trash me-1"></i> Delete Selected (<span id="btnBulkDeleteCount">0</span>)';

                if (!data.success) {
                    showAlert(data.error || 'Failed to delete contacts.', 'danger');
                    return;
                }

                selectedContactIds.clear();
                isAllFilteredSelected = false;
                updateSelectionUI();
                showAlert(`Successfully deleted ${Number(data.deleted_count || 0).toLocaleString()} contact(s) and their associated records.`, 'success');
                loadContacts(1);
            } catch (err) {
                console.error(err);
                btnBulkDelete.disabled = false;
                btnBulkDelete.innerHTML = '<i class="bi bi-trash me-1"></i> Delete Selected (<span id="btnBulkDeleteCount">0</span>)';
                showAlert('Network error while deleting contacts.', 'danger');
            }
        });
    }

    if (paginationControls) {
        paginationControls.addEventListener('click', function (e) {
            e.preventDefault();
            const link = e.target.closest('.page-link');
            if (!link || link.parentElement.classList.contains('disabled')) return;
            const targetPage = Number(link.getAttribute('data-page'));
            if (targetPage > 0 && targetPage <= totalPages) {
                loadContacts(targetPage);
            }
        });
    }

    // Filter submit & reset
    if (filterForm) {
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            selectedContactIds.clear();
            isAllFilteredSelected = false;
            loadContacts(1);
        });
    }

    if (btnResetFilters) {
        btnResetFilters.addEventListener('click', function () {
            if (filterSearch) filterSearch.value = '';
            if (filterSourceGroup) filterSourceGroup.value = '';
            if (filterExamYear) filterExamYear.value = '';
            if (filterExamType) filterExamType.value = '';
            if (filterLocation) filterLocation.value = '';
            if (filterSmsStatus) filterSmsStatus.value = '';
            if (filterSmsOptOut) filterSmsOptOut.value = '';
            if (filterStatus) filterStatus.value = '';
            selectedContactIds.clear();
            isAllFilteredSelected = false;
            loadContacts(1);
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // CSV EXPORT
    // ─────────────────────────────────────────────────────────────────
    const btnExportCsv = document.getElementById('btnExportCsv');
    if (btnExportCsv) {
        btnExportCsv.addEventListener('click', function () {
            const params = new URLSearchParams(getFilterParams());
            if (!isAllFilteredSelected && selectedContactIds.size > 0) {
                params.append('ids', Array.from(selectedContactIds).join(','));
            }
            window.location.href = `/admin/phone_contacts_export.php?${params.toString()}`;
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // MULTI-STEP CSV IMPORT WIZARD
    // ─────────────────────────────────────────────────────────────────
    const importStep1 = document.getElementById('importStep1');
    const importStep2 = document.getElementById('importStep2');
    const importStep3 = document.getElementById('importStep3');
    const importFooter1 = document.getElementById('importFooter1');
    const importFooter2 = document.getElementById('importFooter2');
    const importFooter3 = document.getElementById('importFooter3');
    const importFileInput = document.getElementById('importFileInput');
    const importSourceGroup = document.getElementById('importSourceGroup');
    const importUploadAlert = document.getElementById('importUploadAlert');
    const btnAnalyzeCsv = document.getElementById('btnAnalyzeCsv');
    const btnBackToStep1 = document.getElementById('btnBackToStep1');
    const btnConfirmImport = document.getElementById('btnConfirmImport');
    const btnCancelImportModal = document.getElementById('btnCancelImportModal');
    const btnCloseImportModal = document.getElementById('btnCloseImportModal');
    const btnMatchStudentNames = document.getElementById('btnMatchStudentNames');
    const btnApplyExistingUpdates = document.getElementById('btnApplyExistingUpdates');
    const studentMatchSummaryCard = document.getElementById('studentMatchSummaryCard');
    const existingContactsUpdateBanner = document.getElementById('existingContactsUpdateBanner');
    const existingContactsUpdateResult = document.getElementById('existingContactsUpdateResult');
    const thPreviewStudentName = document.getElementById('thPreviewStudentName');
    const thPreviewMatchStatus = document.getElementById('thPreviewMatchStatus');

    function showImportStep(step) {
        if (importStep1) importStep1.style.display = (step === 1 ? 'block' : 'none');
        if (importStep2) importStep2.style.display = (step === 2 ? 'block' : 'none');
        if (importStep3) importStep3.style.display = (step === 3 ? 'block' : 'none');

        if (importFooter1) {
            if (step === 1) {
                importFooter1.classList.remove('d-none');
                importFooter1.classList.add('d-flex');
            } else {
                importFooter1.classList.remove('d-flex');
                importFooter1.classList.add('d-none');
            }
        }
        if (importFooter2) {
            if (step === 2) {
                importFooter2.classList.remove('d-none');
                importFooter2.classList.add('d-flex');
            } else {
                importFooter2.classList.remove('d-flex');
                importFooter2.classList.add('d-none');
            }
        }
        if (importFooter3) {
            if (step === 3) {
                importFooter3.classList.remove('d-none');
                importFooter3.classList.add('d-flex');
            } else {
                importFooter3.classList.remove('d-flex');
                importFooter3.classList.add('d-none');
            }
        }
    }

    function resetImportWizard() {
        showImportStep(1);
        if (importFileInput) importFileInput.value = '';
        if (importSourceGroup) importSourceGroup.value = '';
        if (importUploadAlert) {
            importUploadAlert.className = 'alert d-none small mb-0';
            importUploadAlert.innerHTML = '';
        }
        const sampleBody = document.getElementById('previewSampleBody');
        if (sampleBody) sampleBody.innerHTML = '';
        [
            'prevValidRows', 'prevInvalidRows', 'prevNewContacts', 'prevExistingContacts',
            'prevNewRecords', 'prevDuplicateRecords', 'prevNamesAvail', 'prevNamesMiss',
            'prevSchoolsAvail', 'prevSchoolsMiss', 'repNewContacts', 'repNewRecords',
            'repDuplicates'
        ].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '0';
        });
        const invalidAlert = document.getElementById('previewInvalidAlert');
        if (invalidAlert) invalidAlert.classList.add('d-none');
        const dlContainer = document.getElementById('repErrorDownloadContainer');
        if (dlContainer) dlContainer.classList.add('d-none');
        if (btnAnalyzeCsv) {
            btnAnalyzeCsv.disabled = false;
            btnAnalyzeCsv.innerHTML = '<i class="bi bi-search me-1"></i> Inspect &amp; Preview';
        }
        if (btnConfirmImport) {
            btnConfirmImport.disabled = false;
            btnConfirmImport.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Import Contacts';
        }
        if (studentMatchSummaryCard) studentMatchSummaryCard.classList.add('d-none');
        if (existingContactsUpdateBanner) existingContactsUpdateBanner.classList.add('d-none');
        if (existingContactsUpdateResult) {
            existingContactsUpdateResult.className = 'alert d-none py-2 px-3 small mb-3';
            existingContactsUpdateResult.innerHTML = '';
        }
        if (thPreviewStudentName) thPreviewStudentName.classList.add('d-none');
        if (thPreviewMatchStatus) thPreviewMatchStatus.classList.add('d-none');
        if (btnMatchStudentNames) {
            btnMatchStudentNames.disabled = false;
            btnMatchStudentNames.innerHTML = '<i class="bi bi-person-check me-1"></i> Update Student Names';
        }
        if (btnApplyExistingUpdates) {
            btnApplyExistingUpdates.disabled = false;
            btnApplyExistingUpdates.innerHTML = '<i class="bi bi-check2-all me-1"></i> Apply Student Name Updates';
        }
    }

    if (importModalEl) {
        importModalEl.addEventListener('show.bs.modal', resetImportWizard);
        importModalEl.addEventListener('hidden.bs.modal', function () {
            resetImportWizard();
            try {
                const formData = new FormData();
                formData.append('action', 'cancel_import');
                formData.append('csrf_token', csrfToken);
                fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: formData }).catch(() => {});
            } catch (_) {}
        });
    }

    // 1. CANCEL BUTTON
    if (btnCancelImportModal) {
        btnCancelImportModal.addEventListener('click', function () {
            const modal = getBootstrapModal(importModalEl);
            if (modal) modal.hide();
            resetImportWizard();
            try {
                const formData = new FormData();
                formData.append('action', 'cancel_import');
                formData.append('csrf_token', csrfToken);
                fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: formData }).catch(() => {});
            } catch (_) {}
        });
    }

    // 2. RE-UPLOAD BUTTON (STEP 2 -> STEP 1)
    if (btnBackToStep1) {
        btnBackToStep1.addEventListener('click', function () {
            showImportStep(1);
            if (importFileInput) importFileInput.value = '';
            if (importUploadAlert) {
                importUploadAlert.className = 'alert d-none small mb-0';
                importUploadAlert.innerHTML = '';
            }
            try {
                const formData = new FormData();
                formData.append('action', 'cancel_import');
                formData.append('csrf_token', csrfToken);
                fetch('/ajax/phone_contacts_action.php', { method: 'POST', body: formData }).catch(() => {});
            } catch (_) {}
        });
    }

    // 3. INSPECT & PREVIEW BUTTON (STEP 1 -> STEP 2)
    if (btnAnalyzeCsv) {
        btnAnalyzeCsv.addEventListener('click', async function () {
            const file = importFileInput && importFileInput.files ? importFileInput.files[0] : null;
            if (!file) {
                showAlert('Please select a CSV file to inspect and preview.', 'danger', importUploadAlert);
                return;
            }

            const fileName = file.name || '';
            const ext = fileName.split('.').pop().toLowerCase();
            if (ext !== 'csv' && ext !== 'txt') {
                showAlert('Unsupported file format. Please upload a .csv file.', 'danger', importUploadAlert);
                return;
            }

            const maxBytes = 15 * 1024 * 1024; // 15MB
            if (file.size > maxBytes) {
                showAlert('The selected file exceeds the 15MB size limit.', 'danger', importUploadAlert);
                return;
            }

            const formData = new FormData();
            formData.append('action', 'analyze_csv');
            formData.append('csrf_token', csrfToken);
            formData.append('csv_file', file);
            formData.append('source_group', importSourceGroup ? importSourceGroup.value.trim() : '');

            btnAnalyzeCsv.disabled = true;
            btnAnalyzeCsv.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Inspecting...';
            hideAlert(importUploadAlert);

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                btnAnalyzeCsv.disabled = false;
                btnAnalyzeCsv.innerHTML = '<i class="bi bi-search me-1"></i> Inspect &amp; Preview';

                if (!data.success) {
                    showAlert(data.error || 'Failed to inspect CSV file.', 'danger', importUploadAlert);
                    return;
                }

                renderImportPreview(data.analysis);
            } catch (err) {
                console.error(err);
                btnAnalyzeCsv.disabled = false;
                btnAnalyzeCsv.innerHTML = '<i class="bi bi-search me-1"></i> Inspect &amp; Preview';
                showAlert('Network error while inspecting CSV file. Please check your connection and try again.', 'danger', importUploadAlert);
            }
        });
    }

    function renderImportPreview(analysis) {
        document.getElementById('previewFileName').textContent = analysis.filename || 'File Preview';
        document.getElementById('previewSourceGroup').textContent = analysis.source_group || 'Not specified';
        document.getElementById('previewTotalRowsBadge').textContent = `${Number(analysis.total_rows || 0).toLocaleString()} Total Rows`;

        document.getElementById('prevValidRows').textContent = Number(analysis.valid_rows || 0).toLocaleString();
        document.getElementById('prevInvalidRows').textContent = Number(analysis.invalid_rows || 0).toLocaleString();
        document.getElementById('prevNewContacts').textContent = Number(analysis.new_phone_contacts || 0).toLocaleString();
        document.getElementById('prevExistingContacts').textContent = Number(analysis.existing_phone_contacts || 0).toLocaleString();
        document.getElementById('prevNewRecords').textContent = Number(analysis.new_academic_records || 0).toLocaleString();
        document.getElementById('prevDuplicateRecords').textContent = Number(analysis.duplicate_records || 0).toLocaleString();
        document.getElementById('prevNamesAvail').textContent = Number(analysis.names_available || 0).toLocaleString();
        document.getElementById('prevNamesMiss').textContent = Number(analysis.names_missing || 0).toLocaleString();
        document.getElementById('prevSchoolsAvail').textContent = Number(analysis.schools_available || 0).toLocaleString();
        document.getElementById('prevSchoolsMiss').textContent = Number(analysis.schools_missing || 0).toLocaleString();

        // Sample rows
        const sampleBody = document.getElementById('previewSampleBody');
        const samples = analysis.sample_preview || [];
        if (samples.length === 0) {
            sampleBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No valid rows found.</td></tr>';
        } else {
            sampleBody.innerHTML = samples.map(s => `
                <tr>
                    <td>${s.line}</td>
                    <td>${escapeHtml(s.display || s.phone)}</td>
                    <td>${s.year}</td>
                    <td><span class="badge bg-info text-dark">${escapeHtml(s.type)}</span></td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(s.location)}</span></td>
                    <td>${escapeHtml(s.name)}</td>
                    <td>${escapeHtml(s.school)}</td>
                </tr>
            `).join('');
        }

        // Invalid breakdown
        const invalidAlert = document.getElementById('previewInvalidAlert');
        const invalidSummary = document.getElementById('previewInvalidSummary');
        if (analysis.invalid_rows > 0) {
            const b = analysis.invalid_breakdown || {};
            const parts = [];
            if (b.invalid_phone) parts.push(`${b.invalid_phone} invalid phone numbers`);
            if (b.invalid_location) parts.push(`${b.invalid_location} invalid locations`);
            if (b.invalid_year) parts.push(`${b.invalid_year} invalid exam years`);
            if (b.missing_fields) parts.push(`${b.missing_fields} missing required fields`);

            invalidSummary.innerHTML = `<strong>${analysis.invalid_rows} row(s) contain errors and will be skipped:</strong> ` + parts.join(', ') + '. You will be able to download the error CSV report.';
            invalidAlert.classList.remove('d-none');
        } else {
            invalidAlert.classList.add('d-none');
        }

        // Reset student match card & columns on fresh inspect
        if (studentMatchSummaryCard) studentMatchSummaryCard.classList.add('d-none');
        if (existingContactsUpdateBanner) existingContactsUpdateBanner.classList.add('d-none');
        if (existingContactsUpdateResult) {
            existingContactsUpdateResult.className = 'alert d-none py-2 px-3 small mb-3';
            existingContactsUpdateResult.innerHTML = '';
        }
        if (thPreviewStudentName) thPreviewStudentName.classList.add('d-none');
        if (thPreviewMatchStatus) thPreviewMatchStatus.classList.add('d-none');
        if (btnMatchStudentNames) {
            btnMatchStudentNames.disabled = false;
            btnMatchStudentNames.innerHTML = '<i class="bi bi-person-check me-1"></i> Update Student Names';
        }

        // Switch to Step 2
        showImportStep(2);
    }

    // 3b. UPDATE STUDENT NAMES BUTTON (STEP 2)
    if (btnMatchStudentNames) {
        btnMatchStudentNames.addEventListener('click', async function () {
            btnMatchStudentNames.disabled = true;
            btnMatchStudentNames.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Matching...';

            const formData = new FormData();
            formData.append('action', 'match_student_names');
            formData.append('csrf_token', csrfToken);

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                btnMatchStudentNames.disabled = false;
                btnMatchStudentNames.innerHTML = '<i class="bi bi-person-check me-1"></i> Update Student Names';

                if (!data.success) {
                    alert(data.error || 'Failed to match student names.');
                    return;
                }

                const summary = data.summary || {};
                const badgeTotal = document.getElementById('badgeMatchTotal');
                const statEnrich = document.getElementById('statMatchEnrich');
                const statAlready = document.getElementById('statMatchAlready');
                const statConflict = document.getElementById('statMatchConflict');
                const statMultiple = document.getElementById('statMatchMultiple');
                const statNotFound = document.getElementById('statMatchNotFound');

                if (badgeTotal) badgeTotal.textContent = `${Number(summary.matched_students_count || 0).toLocaleString()} Total Matches`;
                if (statEnrich) statEnrich.textContent = Number(summary.new_matches_found || 0).toLocaleString();
                if (statAlready) statAlready.textContent = Number(summary.already_matching || 0).toLocaleString();
                if (statConflict) statConflict.textContent = Number(summary.conflicts_found || 0).toLocaleString();
                if (statMultiple) statMultiple.textContent = Number(summary.multiple_students || 0).toLocaleString();
                if (statNotFound) statNotFound.textContent = Number(summary.not_found || 0).toLocaleString();

                if (studentMatchSummaryCard) studentMatchSummaryCard.classList.remove('d-none');

                // Existing contacts banner
                const existingToUpdate = Number(summary.existing_contacts_to_update || 0);
                if (existingToUpdate > 0 && existingContactsUpdateBanner) {
                    const bannerText = document.getElementById('existingContactsUpdateText');
                    if (bannerText) {
                        bannerText.textContent = `Found ${existingToUpdate.toLocaleString()} existing phone contact(s) in database without names that matched active student records.`;
                    }
                    existingContactsUpdateBanner.classList.remove('d-none');
                } else if (existingContactsUpdateBanner) {
                    existingContactsUpdateBanner.classList.add('d-none');
                }

                // Show match columns in preview table
                if (thPreviewStudentName) thPreviewStudentName.classList.remove('d-none');
                if (thPreviewMatchStatus) thPreviewMatchStatus.classList.remove('d-none');

                // Re-render sample rows with student info
                const sampleBody = document.getElementById('previewSampleBody');
                const samples = data.sample_matches || [];
                if (sampleBody && samples.length > 0) {
                    sampleBody.innerHTML = samples.map(s => {
                        let nameCol = escapeHtml(s.csv_name || '—');
                        if (!s.csv_name && s.suggested_name) {
                            nameCol = `<span class="text-success fw-bold">${escapeHtml(s.suggested_name)}</span> <span class="badge bg-success bg-opacity-25 text-success" style="font-size:0.65rem;">Enriched</span>`;
                        } else if (s.match_status === 'Name Conflict') {
                            nameCol = `<span class="text-danger fw-bold">${escapeHtml(s.csv_name)}</span> <span class="badge bg-warning text-dark" style="font-size:0.65rem;">CSV</span>`;
                        }

                        let studentCol = s.matched_student ? `<strong class="text-dark">${escapeHtml(s.matched_student)}</strong>` : '<span class="text-muted">—</span>';
                        if (s.conflict_details) {
                            studentCol += `<div class="small text-muted" style="font-size:0.7rem;">${escapeHtml(s.conflict_details)}</div>`;
                        }

                        return `
                            <tr>
                                <td>${s.line}</td>
                                <td>${escapeHtml(s.display || s.phone)}</td>
                                <td>${s.year}</td>
                                <td><span class="badge bg-info text-dark">${escapeHtml(s.type)}</span></td>
                                <td><span class="badge bg-light text-dark border">${escapeHtml(s.location)}</span></td>
                                <td>${nameCol}</td>
                                <td>${escapeHtml(s.school || '—')}</td>
                                <td>${studentCol}</td>
                                <td><span class="badge ${s.badge_class}">${escapeHtml(s.match_status)}</span></td>
                            </tr>
                        `;
                    }).join('');
                }
            } catch (err) {
                console.error(err);
                btnMatchStudentNames.disabled = false;
                btnMatchStudentNames.innerHTML = '<i class="bi bi-person-check me-1"></i> Update Student Names';
                alert('Network error while matching student names. Please try again.');
            }
        });
    }

    // 3c. APPLY EXISTING CONTACTS STUDENT NAME UPDATES
    if (btnApplyExistingUpdates) {
        btnApplyExistingUpdates.addEventListener('click', async function () {
            if (!confirm('Are you sure you want to update existing phone contacts in the database with their matched student names?')) {
                return;
            }

            btnApplyExistingUpdates.disabled = true;
            btnApplyExistingUpdates.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Applying...';

            const formData = new FormData();
            formData.append('action', 'apply_student_name_updates');
            formData.append('csrf_token', csrfToken);

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                btnApplyExistingUpdates.disabled = false;
                btnApplyExistingUpdates.innerHTML = '<i class="bi bi-check2-all me-1"></i> Apply Student Name Updates';

                if (!data.success) {
                    if (existingContactsUpdateResult) {
                        showAlert(data.error || 'Failed to apply name updates.', 'danger', existingContactsUpdateResult);
                    }
                    return;
                }

                if (existingContactsUpdateBanner) {
                    existingContactsUpdateBanner.classList.add('d-none');
                }
                if (existingContactsUpdateResult) {
                    showAlert(`Successfully updated ${data.updated_count} existing contact name(s) in the database.`, 'success', existingContactsUpdateResult);
                }

                // Refresh background contacts list to display updated names
                loadContacts(1);
            } catch (err) {
                console.error(err);
                btnApplyExistingUpdates.disabled = false;
                btnApplyExistingUpdates.innerHTML = '<i class="bi bi-check2-all me-1"></i> Apply Student Name Updates';
                if (existingContactsUpdateResult) {
                    showAlert('Network error while applying updates.', 'danger', existingContactsUpdateResult);
                }
            }
        });
    }

    // 4. IMPORT CONTACTS BUTTON (STEP 2 -> STEP 3)
    if (btnConfirmImport) {
        btnConfirmImport.addEventListener('click', async function () {
            const formData = new FormData();
            formData.append('action', 'execute_import');
            formData.append('csrf_token', csrfToken);
            formData.append('source_group', importSourceGroup ? importSourceGroup.value.trim() : '');
            const file = importFileInput && importFileInput.files ? importFileInput.files[0] : null;
            if (file) {
                formData.append('csv_file', file);
            }

            btnConfirmImport.disabled = true;
            btnConfirmImport.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importing...';

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                btnConfirmImport.disabled = false;
                btnConfirmImport.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Import Contacts';

                if (!data.success) {
                    alert(data.error || 'Import failed.');
                    return;
                }

                renderImportReport(data.report);
                loadContacts(1);
            } catch (err) {
                console.error(err);
                btnConfirmImport.disabled = false;
                btnConfirmImport.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Import Contacts';
                alert('Network error during import. Please try again.');
            }
        });
    }

    function renderImportReport(rep) {
        document.getElementById('repNewContacts').textContent = Number(rep.new_contacts || 0).toLocaleString();
        document.getElementById('repNewRecords').textContent = Number(rep.new_records || 0).toLocaleString();
        document.getElementById('repDuplicates').textContent = Number(rep.duplicate_records || 0).toLocaleString();

        const dlContainer = document.getElementById('repErrorDownloadContainer');
        const dlLink = document.getElementById('repErrorDownloadLink');
        const dlCount = document.getElementById('repErrorCount');

        if (rep.invalid_rows > 0 && (rep.error_csv_path || rep.import_id)) {
            dlCount.textContent = rep.invalid_rows;
            dlLink.href = '/admin/download_import_errors.php?import_id=' + (rep.import_id || 0);
            dlContainer.classList.remove('d-none');
        } else {
            dlContainer.classList.add('d-none');
        }

        showImportStep(3);
    }

    // 5. DONE BUTTON (STEP 3 -> CLOSE & REFRESH)
    if (btnCloseImportModal) {
        btnCloseImportModal.addEventListener('click', function () {
            const modal = getBootstrapModal(importModalEl);
            if (modal) modal.hide();
            resetImportWizard();
            loadContacts(1);
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // CONTROLLED SMS BROADCAST
    // ─────────────────────────────────────────────────────────────────
    const btnOpenSendSmsModal = document.getElementById('btnOpenSendSmsModal');
    const smsMessageText = document.getElementById('smsMessageText');
    const smsCampaignName = document.getElementById('smsCampaignName');
    const smsModalUniqueCount = document.getElementById('smsModalUniqueCount');
    const smsCharCount = document.getElementById('smsCharCount');
    const smsEncoding = document.getElementById('smsEncoding');
    const smsPartsCount = document.getElementById('smsPartsCount');
    const smsTotalUnits = document.getElementById('smsTotalUnits');
    const smsModalAlert = document.getElementById('smsModalAlert');
    const btnConfirmSendSms = document.getElementById('btnConfirmSendSms');

    let cachedUniqueRecipients = 0;

    if (btnOpenSendSmsModal) {
        btnOpenSendSmsModal.addEventListener('click', async function () {
            hideAlert(smsModalAlert);
            document.getElementById('smsSendingProgress').style.display = 'none';
            document.getElementById('smsComposeForm').style.display = 'block';
            document.getElementById('smsModalFooter').style.display = 'flex';
            btnConfirmSendSms.disabled = false;

            const smsModal = getBootstrapModal(sendSmsModalEl);
            if (smsModal) smsModal.show();
            await calculateSmsUnits();
        });
    }

    async function calculateSmsUnits() {
        const message = smsMessageText ? smsMessageText.value : '';
        const formData = new FormData();
        formData.append('action', 'calculate_sms');
        formData.append('csrf_token', csrfToken);
        formData.append('message', message);
        formData.append('filters', JSON.stringify(getFilterParams()));

        if (!isAllFilteredSelected && selectedContactIds.size > 0) {
            Array.from(selectedContactIds).forEach(id => {
                formData.append('selected_ids[]', String(id));
            });
        }

        try {
            const resp = await fetch('/ajax/phone_contacts_action.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (data.success) {
                cachedUniqueRecipients = data.unique_recipients;
                if (smsModalUniqueCount) smsModalUniqueCount.textContent = `${Number(data.unique_recipients).toLocaleString()} Contacts`;
                if (smsCharCount) smsCharCount.textContent = data.characters;
                if (smsEncoding) smsEncoding.textContent = data.encoding;
                if (smsPartsCount) smsPartsCount.textContent = data.segments;
                if (smsTotalUnits) smsTotalUnits.textContent = Number(data.total_units).toLocaleString();

                // Exclusion breakdown
                if (data.exclusion_breakdown) {
                    const eb = data.exclusion_breakdown;
                    const elTotal = document.getElementById('smsModalTotalCount');
                    const elEligible = document.getElementById('smsModalEligibleCount');
                    const elBlocked = document.getElementById('smsModalBlockedCount');
                    const elOptedOut = document.getElementById('smsModalOptedOutCount');
                    if (elTotal) elTotal.textContent = Number(eb.total_selected || 0).toLocaleString();
                    if (elEligible) elEligible.textContent = Number(eb.eligible || 0).toLocaleString();
                    if (elBlocked) elBlocked.textContent = Number(eb.blocked || 0).toLocaleString();
                    if (elOptedOut) elOptedOut.textContent = Number(eb.opted_out || 0).toLocaleString();
                }

                // Recent campaign duplicate warning
                const warnBanner = document.getElementById('smsDuplicateWarningBanner');
                const warnText = document.getElementById('smsDuplicateWarningText');
                if (warnBanner && data.duplicate_warning && data.duplicate_warning.has_duplicates) {
                    const dw = data.duplicate_warning;
                    if (warnText) {
                        warnText.textContent = `${dw.duplicate_count.toLocaleString()} of the selected recipients have already received an SMS campaign within the last ${dw.days} days.`;
                    }
                    warnBanner.classList.remove('d-none');
                } else if (warnBanner) {
                    warnBanner.classList.add('d-none');
                }
            }
        } catch (err) {
            console.error('calculateSmsUnits error:', err);
        }
    }

    const btnProceedDuplicateSend = document.getElementById('btnProceedDuplicateSend');
    if (btnProceedDuplicateSend) {
        btnProceedDuplicateSend.addEventListener('click', function () {
            const warnBanner = document.getElementById('smsDuplicateWarningBanner');
            if (warnBanner) warnBanner.classList.add('d-none');
        });
    }

    if (smsMessageText) {
        let debounceTimer;
        smsMessageText.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(calculateSmsUnits, 200);
        });
    }

    if (btnConfirmSendSms) {
        btnConfirmSendSms.addEventListener('click', async function () {
            const message = smsMessageText ? smsMessageText.value.trim() : '';
            if (!message) {
                showAlert('SMS message content cannot be empty.', 'danger', smsModalAlert);
                return;
            }

            if (cachedUniqueRecipients <= 0) {
                showAlert('No active recipients selected (or all matching contacts have opted out).', 'danger', smsModalAlert);
                return;
            }

            const gatewayInput = document.querySelector('input[name="sms_gateway"]:checked');
            const gateway = gatewayInput ? gatewayInput.value : 'ipromo';
            const campaignName = smsCampaignName ? smsCampaignName.value.trim() : '';

            btnConfirmSendSms.disabled = true;
            btnConfirmSendSms.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Preparing...';
            hideAlert(smsModalAlert);

            const formData = new FormData();
            formData.append('action', 'send_sms_campaign');
            formData.append('csrf_token', csrfToken);
            formData.append('message', message);
            formData.append('gateway', gateway);
            formData.append('campaign_name', campaignName);
            formData.append('filters', JSON.stringify(getFilterParams()));
            const idempKey = (typeof crypto !== 'undefined' && crypto.randomUUID)
                ? crypto.randomUUID()
                : 'idemp_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);
            formData.append('idempotency_key', idempKey);

            if (!isAllFilteredSelected && selectedContactIds.size > 0) {
                Array.from(selectedContactIds).forEach(id => {
                    formData.append('selected_ids[]', String(id));
                });
            }

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                if (!data.success) {
                    btnConfirmSendSms.disabled = false;
                    btnConfirmSendSms.innerHTML = '<i class="bi bi-send-fill me-1"></i> Send Campaign';
                    showAlert(data.error || 'Failed to initialize campaign.', 'danger', smsModalAlert);
                    return;
                }

                // Campaign created, start batch dispatching!
                const campaignId = data.campaign_id;
                document.getElementById('smsComposeForm').style.display = 'none';
                document.getElementById('smsModalFooter').style.display = 'none';
                document.getElementById('smsSendingProgress').style.display = 'block';

                await processCampaignBatches(campaignId);
            } catch (err) {
                console.error(err);
                btnConfirmSendSms.disabled = false;
                btnConfirmSendSms.innerHTML = '<i class="bi bi-send-fill me-1"></i> Send Campaign';
                showAlert('Network error initiating campaign.', 'danger', smsModalAlert);
            }
        });
    }

    async function processCampaignBatches(campaignId) {
        const progressBar = document.getElementById('smsProgressBar');
        const progressPercent = document.getElementById('smsProgressPercent');
        const progressStatus = document.getElementById('smsProgressStatus');
        const progressSent = document.getElementById('smsProgressSent');
        const progressTotal = document.getElementById('smsProgressTotal');
        const progressFailed = document.getElementById('smsProgressFailed');

        let isCompleted = false;

        while (!isCompleted) {
            const formData = new FormData();
            formData.append('action', 'process_batch');
            formData.append('csrf_token', csrfToken);
            formData.append('campaign_id', String(campaignId));
            formData.append('batch_size', '50');

            try {
                const resp = await fetch('/ajax/bulk_sms_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                if (!data.success) {
                    progressStatus.textContent = 'Batch processing paused: ' + (data.error || 'Unknown error');
                    break;
                }

                const total = data.total || 1;
                const processed = (data.sent || 0) + (data.failed || 0);
                const pct = Math.min(100, Math.round((processed / total) * 100));

                if (progressBar) progressBar.style.width = pct + '%';
                if (progressPercent) progressPercent.textContent = pct + '%';
                if (progressSent) progressSent.textContent = Number(data.sent || 0).toLocaleString();
                if (progressTotal) progressTotal.textContent = Number(total).toLocaleString();
                if (progressFailed) progressFailed.textContent = Number(data.failed || 0).toLocaleString();

                if (data.is_completed) {
                    isCompleted = true;
                    progressStatus.textContent = 'Broadcast completed successfully!';
                    if (progressBar) {
                        progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                    }
                    setTimeout(() => {
                        const smsModal = getBootstrapModal(sendSmsModalEl);
                        if (smsModal) smsModal.hide();
                        showAlert(`SMS campaign broadcast finished: ${data.sent} sent, ${data.failed} failed.`, 'success');
                    }, 1500);
                }
            } catch (err) {
                console.error('Batch error:', err);
                progressStatus.textContent = 'Network interruption. Retrying...';
                await new Promise(r => setTimeout(r, 2000));
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // LOCATIONS MANAGEMENT MODAL
    // ─────────────────────────────────────────────────────────────────
    const locationsBadgesContainer = document.getElementById('locationsBadgesContainer');
    const newLocationInput = document.getElementById('newLocationInput');
    const btnAddLocation = document.getElementById('btnAddLocation');
    const btnSaveLocations = document.getElementById('btnSaveLocations');
    const locationsModalAlert = document.getElementById('locationsModalAlert');

    function renderLocationsBadges() {
        if (!locationsBadgesContainer) return;
        locationsBadgesContainer.innerHTML = allowedLocations.map(loc => `
            <span class="badge bg-secondary p-2 d-inline-flex align-items-center gap-1">
                ${escapeHtml(loc)}
                <button type="button" class="btn-close btn-close-white btn-sm remove-location-btn" style="font-size:0.5rem;" data-loc="${escapeHtml(loc)}"></button>
            </span>
        `).join('');
    }

    if (locationsModalEl) {
        locationsModalEl.addEventListener('show.bs.modal', function () {
            hideAlert(locationsModalAlert);
            renderLocationsBadges();
        });
    }

    if (btnAddLocation) {
        btnAddLocation.addEventListener('click', function () {
            const val = newLocationInput ? newLocationInput.value.trim() : '';
            if (!val) return;
            if (!allowedLocations.some(l => l.toLowerCase() === val.toLowerCase())) {
                allowedLocations.push(val);
                renderLocationsBadges();
            }
            if (newLocationInput) newLocationInput.value = '';
        });
    }

    if (locationsBadgesContainer) {
        locationsBadgesContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-location-btn')) {
                const loc = e.target.getAttribute('data-loc');
                allowedLocations = allowedLocations.filter(l => l !== loc);
                renderLocationsBadges();
            }
        });
    }

    if (btnSaveLocations) {
        btnSaveLocations.addEventListener('click', async function () {
            const formData = new FormData();
            formData.append('action', 'save_locations');
            formData.append('csrf_token', csrfToken);
            formData.append('locations', allowedLocations.join(','));

            btnSaveLocations.disabled = true;
            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();
                btnSaveLocations.disabled = false;

                if (data.success) {
                    allowedLocations = data.locations;
                    showAlert('Locations updated successfully.', 'success', locationsModalAlert);
                    setTimeout(() => {
                        const modal = getBootstrapModal(locationsModalEl);
                        if (modal) modal.hide();
                    }, 1000);
                } else {
                    showAlert(data.error || 'Failed to save locations.', 'danger', locationsModalAlert);
                }
            } catch (err) {
                console.error(err);
                btnSaveLocations.disabled = false;
                showAlert('Network error.', 'danger', locationsModalAlert);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // WHATSAPP GROUP STATS MODAL
    // ─────────────────────────────────────────────────────────────────
    const groupStatsModalEl = document.getElementById('groupStatsModal');
    const groupStatsTableBody = document.getElementById('groupStatsTableBody');

    if (groupStatsModalEl) {
        groupStatsModalEl.addEventListener('show.bs.modal', async function () {
            if (groupStatsTableBody) {
                groupStatsTableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Loading group stats...</td></tr>';
            }
            try {
                const resp = await fetch('/ajax/phone_contacts_action.php?action=get_group_stats');
                const data = await resp.json();
                if (!data.success) {
                    if (groupStatsTableBody) {
                        groupStatsTableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(data.error || 'Failed to load group stats.')}</td></tr>`;
                    }
                    return;
                }
                const groups = data.groups || [];
                if (groups.length === 0) {
                    if (groupStatsTableBody) {
                        groupStatsTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No WhatsApp group data found.</td></tr>';
                    }
                    return;
                }
                let rows = '';
                groups.forEach(g => {
                    rows += `
                        <tr>
                            <td><strong class="text-dark">${escapeHtml(g.source_group || 'Direct/Unknown')}</strong></td>
                            <td class="text-center"><span class="badge bg-primary">${Number(g.total_contacts || 0).toLocaleString()}</span></td>
                            <td class="text-center"><span class="badge bg-info text-dark">${Number(g.total_records || 0).toLocaleString()}</span></td>
                            <td><small class="text-muted">${escapeHtml(g.exam_types || '—')}</small></td>
                            <td><small class="text-muted">${escapeHtml(g.exam_years || '—')}</small></td>
                            <td><small class="text-muted">${escapeHtml(g.locations || '—')}</small></td>
                            <td><small class="text-muted">${escapeHtml(g.first_imported_at || '—')}</small></td>
                            <td><small class="text-muted">${escapeHtml(g.last_imported_at || '—')}</small></td>
                        </tr>
                    `;
                });
                if (groupStatsTableBody) {
                    groupStatsTableBody.innerHTML = rows;
                }
            } catch (err) {
                console.error('Group stats error:', err);
                if (groupStatsTableBody) {
                    groupStatsTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Network error loading group stats.</td></tr>';
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // ADMIN TEST SMS
    // ─────────────────────────────────────────────────────────────────
    const btnSendTestSms = document.getElementById('btnSendTestSms');
    const testSmsPhone = document.getElementById('testSmsPhone');
    const testSmsGateway = document.getElementById('testSmsGateway');
    const testSmsMessage = document.getElementById('testSmsMessage');
    const testSmsAlert = document.getElementById('testSmsAlert');

    if (btnSendTestSms) {
        btnSendTestSms.addEventListener('click', async function () {
            hideAlert(testSmsAlert);
            const phone = testSmsPhone ? testSmsPhone.value.trim() : '';
            const gateway = testSmsGateway ? testSmsGateway.value : 'ipromo';
            const message = testSmsMessage ? testSmsMessage.value.trim() : '';

            if (!phone) {
                showAlert('Destination phone number is required.', 'danger', testSmsAlert);
                return;
            }
            if (!message) {
                showAlert('Test message cannot be empty.', 'danger', testSmsAlert);
                return;
            }

            btnSendTestSms.disabled = true;
            btnSendTestSms.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

            const formData = new FormData();
            formData.append('action', 'send_test_sms');
            formData.append('csrf_token', csrfToken);
            formData.append('phone', phone);
            formData.append('gateway', gateway);
            formData.append('message', message);

            try {
                const resp = await fetch('/ajax/phone_contacts_action.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();
                btnSendTestSms.disabled = false;
                btnSendTestSms.innerHTML = '<i class="bi bi-send me-1"></i> Send Test SMS';

                if (data.success) {
                    showAlert(`Test SMS sent successfully! [Gateway: ${escapeHtml(gateway)}, Details: ${escapeHtml(data.result?.status || 'OK')}]`, 'success', testSmsAlert);
                } else {
                    showAlert(data.error || 'Failed to send test SMS.', 'danger', testSmsAlert);
                }
            } catch (err) {
                console.error(err);
                btnSendTestSms.disabled = false;
                btnSendTestSms.innerHTML = '<i class="bi bi-send me-1"></i> Send Test SMS';
                showAlert('Network error while dispatching test SMS.', 'danger', testSmsAlert);
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeJsString(str) {
        if (!str) return '';
        return String(str)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/"/g, '&quot;')
            .replace(/\r?\n/g, ' ');
    }

        // Initial load on ready
        loadContacts(1);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPhoneContacts);
    } else {
        initPhoneContacts();
    }
})();
