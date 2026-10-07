<?php
declare(strict_types=1);
/**
 * student/views/layout.php
 * Student portal uses the same top navigation as admin/teachers.
 */
include __DIR__ . '/../../includes/header.php';
?>

<style id="studentPortalModalStyles">
.modal-backdrop {
    z-index: 1050 !important;
}
.modal {
    z-index: 1055 !important;
}
.modal.show {
    z-index: 1055 !important;
}
#googleReviewModal,
#missingMobileModal,
#leaveClassModal {
    z-index: 1060 !important;
    pointer-events: auto !important;
}
#googleReviewModal .modal-dialog,
#missingMobileModal .modal-dialog,
#leaveClassModal .modal-dialog {
    z-index: 1061 !important;
    position: relative;
    pointer-events: auto !important;
}
#googleReviewModal .modal-content,
#missingMobileModal .modal-content,
#leaveClassModal .modal-content {
    background: var(--surface, #171c2b);
    color: var(--text, #f1f5f9);
    border: 1px solid var(--panel-border, rgba(255, 255, 255, 0.12));
    pointer-events: auto !important;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6) !important;
}
[data-bs-theme="light"] #googleReviewModal .modal-content,
[data-bs-theme="light"] #missingMobileModal .modal-content,
[data-bs-theme="light"] #leaveClassModal .modal-content {
    background: #ffffff;
    color: #0f172a;
    border-color: rgba(0, 0, 0, 0.1);
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.18) !important;
}
#googleReviewModal form,
#googleReviewModal input,
#googleReviewModal button,
#googleReviewModal a,
#missingMobileModal form,
#missingMobileModal input,
#missingMobileModal button,
#missingMobileModal a,
#missingMobileModal label,
#leaveClassModal button {
    pointer-events: auto !important;
}
</style>

<div class="student-dashboard-rebuild">

    <section class="sdr-hero">
        <div class="sdr-profile">
            <div class="sdr-avatar" id="studentAvatar">
                <?php if (!empty($studentProfilePhoto)): ?>
                    <img src="<?= student_e($studentProfilePhoto) ?>" alt="Profile photo" class="student-avatar-img">
                <?php else: ?>
                    <span id="studentAvatarInitials"><?= student_e($studentAvatarInitials ?? 'S') ?></span>
                <?php endif; ?>
            </div>
            <div>
                <div class="sdr-eyebrow">Student Portal</div>
                <h1 id="studentWelcomeTitle"><?= !empty($studentDisplayName) ? 'Welcome back, ' . student_e($studentDisplayName) : 'Welcome back' ?></h1>
                <div class="sdr-phone">
                    <i class="bi bi-phone"></i>
                    <?= student_e($studentDisplayPhone !== '' ? $studentDisplayPhone : ($student['username'] ?? 'Student')) ?>
                </div>
                <div class="sdr-status">
                    <?php if ($whatsappVerified): ?>
                        <i class="bi bi-whatsapp"></i>
                        WhatsApp verified
                    <?php elseif (!empty($phoneNumberVerified)): ?>
                        <i class="bi bi-phone"></i>
                        Phone number verified
                    <?php else: ?>
                        <i class="bi bi-whatsapp"></i>
                        WhatsApp linking active
                    <?php endif; ?>
                </div>
                <?php
                $studentSocialLinks = function_exists('student_social_icon_links')
                    ? student_social_icon_links($studentProfile ?? [])
                    : [];
                ?>
                <div id="studentSocialLinks" class="d-flex flex-wrap gap-2 mt-2<?= $studentSocialLinks ? '' : ' d-none' ?>">
                    <?php foreach ($studentSocialLinks as $link): ?>
                        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= student_e($link['url']) ?>"
                           target="_blank" rel="noopener noreferrer" title="<?= student_e($link['label']) ?>" aria-label="<?= student_e($link['label']) ?>">
                            <i class="bi <?= student_e($link['icon']) ?>"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-light position-relative" href="#studentNotificationPanel" id="studentNotifBell" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="studentNotificationPanel">
                <i class="bi bi-bell"></i>
                <?php if (!empty($studentUnreadNotifications)): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="studentNotifBadge">
                        <?= (int)$studentUnreadNotifications > 9 ? '9+' : (int)$studentUnreadNotifications ?>
                    </span>
                <?php endif; ?>
            </a>
            <a class="btn btn-light" href="?tab=settings">
                <i class="bi bi-gear"></i>
                Settings
            </a>
        </div>
    </section>

    <div class="collapse mb-3" id="studentNotificationPanel">
        <div class="card border shadow-sm rounded-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-bell me-1"></i> Notifications</strong>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="markAllNotificationsReadTop">Mark all read</button>
            </div>
            <div class="list-group list-group-flush">
                <?php if (!empty($studentNotifications)): ?>
                    <?php foreach (array_slice($studentNotifications, 0, 8) as $note): ?>
                        <?php
                        $link = (string)($note['link'] ?? '');
                        if ($link !== '' && !preg_match('#^https?://#i', $link) && !str_starts_with($link, '/')) {
                            $link = BASE_URL . 'student/' . ltrim($link, '/');
                        } elseif ($link !== '' && str_starts_with($link, '/')) {
                            $link = rtrim(BASE_URL, '/') . $link;
                        }
                        ?>
                        <a class="list-group-item list-group-item-action <?= empty($note['is_read']) ? 'fw-semibold' : '' ?>"
                           href="<?= student_e($link !== '' ? $link : '?tab=overview') ?>"
                           data-notification-id="<?= (int)$note['id'] ?>">
                            <?= student_e($note['title'] ?? 'Notice') ?>
                            <div class="small text-muted text-truncate"><?= student_e((string)($note['message'] ?? '')) ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="list-group-item text-muted">No notifications yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= student_e($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= student_e($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <nav class="sdr-tabs" aria-label="Student portal sections">
        <a class="<?= $tab === 'overview' ? 'active' : '' ?>" href="?tab=overview"><i class="bi bi-house"></i> Today</a>
        <a class="<?= $tab === 'timetable' ? 'active' : '' ?>" href="?tab=timetable"><i class="bi bi-calendar3"></i> Timetable</a>
        <a class="<?= $tab === 'classes' ? 'active' : '' ?>" href="?tab=classes"><i class="bi bi-collection"></i> My classes</a>
        <a class="<?= $tab === 'recordings' ? 'active' : '' ?>" href="?tab=recordings"><i class="bi bi-camera-reels"></i> Recordings</a>
        <a class="<?= $tab === 'join' ? 'active' : '' ?>" href="?tab=join"><i class="bi bi-search"></i> Find a class</a>
        <a class="<?= $tab === 'services' ? 'active' : '' ?>" href="?tab=services"><i class="bi bi-journal-check"></i> Homework & notes</a>
        <a class="<?= $tab === 'exams' ? 'active' : '' ?>" href="?tab=exams"><i class="bi bi-journal-text"></i> Exams</a>
        <a class="<?= $tab === 'teachers' ? 'active' : '' ?>" href="?tab=teachers"><i class="bi bi-person-badge"></i> Teachers</a>
        <a class="<?= $tab === 'courso' ? 'active' : '' ?>" href="?tab=courso"><i class="bi bi-stars"></i> Talk with AI</a>
        <a class="<?= $tab === 'fees' ? 'active' : '' ?>" href="?tab=fees"><i class="bi bi-wallet2"></i> Fees</a>
        <a class="<?= $tab === 'attendance' ? 'active' : '' ?>" href="?tab=attendance"><i class="bi bi-check2-circle"></i> Attendance</a>
        <a class="<?= $tab === 'documents' ? 'active' : '' ?>" href="?tab=documents"><i class="bi bi-file-earmark-person"></i> My documents</a>
        <a class="<?= $tab === 'settings' ? 'active' : '' ?>" href="?tab=settings"><i class="bi bi-gear"></i> Settings</a>
    </nav>

    <?php
    $tabFile = __DIR__ . "/tabs/{$tab}.php";
    if (is_file($tabFile)) {
        include $tabFile;
    } else {
        include __DIR__ . "/tabs/overview.php";
    }
    ?>

</div>

<div class="modal fade sdr-confirm-modal" id="leaveClassModal" tabindex="-1" aria-labelledby="leaveClassModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="sdr-confirm-icon"><i class="bi bi-box-arrow-left"></i></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <h2 class="modal-title h4 fw-bold mb-2" id="leaveClassModalTitle">Leave this class?</h2>
                <p class="mb-3" id="leaveClassModalText">Your timetable for this class will no longer appear.</p>
                <label class="form-label" for="leaveClassReason">Why are you leaving? <span class="text-muted">(teachers and the office will see this)</span></label>
                <textarea class="form-control" id="leaveClassReason" rows="3" minlength="5" maxlength="500" placeholder="e.g. timetable clash, moving to another group..."></textarea>
                <div class="form-text text-danger d-none" id="leaveClassReasonError">Please enter a short reason (at least 5 characters).</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="leaveClassConfirmBtn">
                    <i class="bi bi-box-arrow-left me-1"></i> Unenroll
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($showGoogleReviewPrompt)): ?>
<div class="modal fade" id="googleReviewModal" tabindex="-1" aria-labelledby="googleReviewModalTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h4 fw-bold" id="googleReviewModalTitle">
                    <i class="bi bi-star-fill text-warning me-1"></i> Enjoying Edexcel College?
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="googleReviewDismissX"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-0">If our classes have helped you, please leave a short Google review. It only takes a minute and helps other students find us.</p>
            </div>
            <div class="modal-footer border-0 pt-0 flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="googleReviewLaterBtn">Maybe later</button>
                <a class="btn btn-primary" id="googleReviewOpenBtn"
                   href="<?= student_e($googleReviewUrl ?? 'https://g.page/r/CSFab2Hr_d_qEAI/review') ?>"
                   target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-google me-1"></i> Leave a Google review
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        fetch('<?= BASE_URL ?>ajax/sync_whatsapp_dp.php')
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.profile_picture_url) {
                    const avatarBox = document.getElementById('studentAvatar');
                    const imgHtml = `<img src="${data.profile_picture_url}" alt="Profile photo" class="student-avatar-img">`;
                    if (avatarBox) {
                        avatarBox.innerHTML = imgHtml;
                    }
                    const topAvatarBox = document.querySelector('.sdr-user-avatar');
                    if (topAvatarBox) {
                        topAvatarBox.innerHTML = imgHtml;
                    }
                }
            })
            .catch(() => {});

        const reviewModalEl = document.getElementById('googleReviewModal');
        if (reviewModalEl && window.bootstrap && !document.getElementById('parentPhoneGate') && !document.getElementById('missingMobileModal')) {
            if (reviewModalEl.parentNode !== document.body) {
                document.body.appendChild(reviewModalEl);
            }

            // Clean up any stale or duplicate backdrops
            document.querySelectorAll('.modal-backdrop').forEach(function (b) {
                if (!document.querySelector('.modal.show')) {
                    b.parentNode && b.parentNode.removeChild(b);
                }
            });

            // Prevent external focus traps from interfering
            reviewModalEl.addEventListener('focusin', function (e) {
                e.stopImmediatePropagation();
            }, true);

            const reviewModal = bootstrap.Modal.getOrCreateInstance(reviewModalEl, {
                backdrop: 'static',
                keyboard: false
            });
            const csrf = <?= json_encode(function_exists('generate_csrf_token') ? generate_csrf_token() : (function_exists('csrf_token') ? csrf_token() : ''), JSON_UNESCAPED_SLASHES) ?>;
            const markShown = () => {
                const body = new URLSearchParams();
                body.set('csrf_token', csrf);
                fetch('<?= BASE_URL ?>ajax/student_google_review.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf },
                    body: body.toString(),
                    credentials: 'same-origin',
                }).catch(() => {});
            };
            reviewModalEl.addEventListener('hidden.bs.modal', function () {
                document.querySelectorAll('.modal-backdrop').forEach(function (b) {
                    b.parentNode && b.parentNode.removeChild(b);
                });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
                markShown();
            }, { once: true });
            document.getElementById('googleReviewOpenBtn')?.addEventListener('click', markShown);
            reviewModal.show();
        }

        const modalEl = document.getElementById('leaveClassModal');
        const confirmBtn = document.getElementById('leaveClassConfirmBtn');
        const titleEl = document.getElementById('leaveClassModalTitle');
        const textEl = document.getElementById('leaveClassModalText');
        const reasonEl = document.getElementById('leaveClassReason');
        const reasonError = document.getElementById('leaveClassReasonError');
        let pendingForm = null;

        if (modalEl && confirmBtn && window.bootstrap) {
            if (modalEl.parentNode !== document.body) {
                document.body.appendChild(modalEl);
            }
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

            document.querySelectorAll('form.js-leave-class').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    pendingForm = form;
                    const card = form.closest('.card');
                    const name = card ? (card.querySelector('h5')?.textContent || '').trim() : '';
                    titleEl.textContent = name ? ('Leave ' + name + '?') : 'Leave this class?';
                    textEl.textContent = 'Your timetable for this class will no longer appear. You can join again later.';
                    if (reasonEl) {
                        reasonEl.value = '';
                    }
                    if (reasonError) {
                        reasonError.classList.add('d-none');
                    }
                    modal.show();
                });
            });

            confirmBtn.addEventListener('click', () => {
                if (!pendingForm) {
                    return;
                }
                const reason = (reasonEl ? reasonEl.value : '').trim();
                if (reason.length < 5) {
                    if (reasonError) {
                        reasonError.classList.remove('d-none');
                    }
                    if (reasonEl) {
                        reasonEl.focus();
                    }
                    return;
                }
                const reasonInput = pendingForm.querySelector('input[name="reason"]');
                if (reasonInput) {
                    reasonInput.value = reason;
                }
                const form = pendingForm;
                pendingForm = null;
                modal.hide();
                form.submit();
            });
        }

        const labelFor = (ms) => {
            if (ms <= 0) {
                return 'Exam has started';
            }
            const total = Math.floor(ms / 1000);
            const days = Math.floor(total / 86400);
            const hours = Math.floor((total % 86400) / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const parts = [];
            if (days > 0) {
                parts.push(days + (days === 1 ? ' day' : ' days'));
            }
            if (hours > 0 || days > 0) {
                parts.push(hours + (hours === 1 ? ' hour' : ' hours'));
            }
            parts.push(minutes + (minutes === 1 ? ' minute' : ' minutes'));
            return parts.join(', ') + ' remaining';
        };
        const tickCountdowns = () => {
            document.querySelectorAll('[data-exam-at]').forEach((el) => {
                const iso = el.getAttribute('data-exam-at');
                if (!iso) {
                    return;
                }
                const start = new Date(iso);
                if (Number.isNaN(start.getTime())) {
                    return;
                }
                el.textContent = labelFor(start.getTime() - Date.now());
            });
        };
        tickCountdowns();
        setInterval(tickCountdowns, 30000);

        const csrfToken = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;

        <?php if (!empty($requireMobileNumberModal)): ?>
        const missingMobileModalEl = document.getElementById('missingMobileModal');
        const missingMobileForm = document.getElementById('missingMobileForm');
        const missingMobileAlert = document.getElementById('missingMobileAlert');
        const missingMobileAlertText = document.getElementById('missingMobileAlertText');
        const missingMobileSubmitBtn = document.getElementById('missingMobileSubmitBtn');
        const missingMobileSubmitBtnText = document.getElementById('missingMobileSubmitBtnText');
        const missingMobileSpinner = document.getElementById('missingMobileSpinner');
        const missingMobileInput = document.getElementById('modal_new_whatsapp');

        if (missingMobileModalEl && window.bootstrap && !document.getElementById('parentPhoneGate')) {
            // Crucial: Relocate modal element to document.body so it is not trapped inside <main>
            // or any flexbox stacking context underneath the .modal-backdrop overlay ("layer mask").
            if (missingMobileModalEl.parentNode !== document.body) {
                document.body.appendChild(missingMobileModalEl);
            }

            // Remove any orphaned backdrops from previous modals
            document.querySelectorAll('.modal-backdrop').forEach(function (b) {
                if (!document.querySelector('.modal.show')) {
                    b.parentNode && b.parentNode.removeChild(b);
                }
            });

            // Prevent any external focus trap from stealing focus from inputs
            missingMobileModalEl.addEventListener('focusin', function (e) {
                e.stopImmediatePropagation();
            }, true);

            const missingMobileModal = bootstrap.Modal.getOrCreateInstance(missingMobileModalEl, {
                backdrop: 'static',
                keyboard: false
            });

            missingMobileModalEl.addEventListener('shown.bs.modal', function () {
                if (missingMobileInput) {
                    missingMobileInput.focus();
                    try { missingMobileInput.select(); } catch (err) {}
                }
            });

            missingMobileModal.show();

            missingMobileModalEl.addEventListener('hidden.bs.modal', function () {
                document.querySelectorAll('.modal-backdrop').forEach(function (b) {
                    b.parentNode && b.parentNode.removeChild(b);
                });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');

                const body = new URLSearchParams({ form: 'dismiss_phone_modal' });
                if (csrfToken) body.append('csrf_token', csrfToken);
                fetch('settings.php?dismiss_phone_modal=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body
                }).catch(() => {});
            });

            if (missingMobileForm) {
                missingMobileForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const phoneVal = (missingMobileInput ? missingMobileInput.value : '').trim();
                    if (!phoneVal) {
                        if (missingMobileAlert && missingMobileAlertText) {
                            missingMobileAlertText.textContent = 'Please enter your mobile number.';
                            missingMobileAlert.classList.remove('d-none');
                        }
                        if (missingMobileInput) missingMobileInput.focus();
                        return;
                    }

                    const cleaned = phoneVal.replace(/[\s\-+.]/g, '');
                    const isValidLk = /^(?:0|94)?7[0-9]{8}$/.test(cleaned);
                    if (!isValidLk) {
                        if (missingMobileAlert && missingMobileAlertText) {
                            missingMobileAlertText.textContent = 'Enter a valid Sri Lankan mobile number, e.g. 0771234567.';
                            missingMobileAlert.classList.remove('d-none');
                        }
                        if (missingMobileInput) missingMobileInput.focus();
                        return;
                    }

                    if (missingMobileAlert) missingMobileAlert.classList.add('d-none');
                    if (missingMobileSubmitBtn) missingMobileSubmitBtn.disabled = true;
                    if (missingMobileSpinner) missingMobileSpinner.classList.remove('d-none');
                    if (missingMobileSubmitBtnText) missingMobileSubmitBtnText.textContent = 'Saving...';

                    const formData = new FormData(missingMobileForm);
                    formData.set('ajax', '1');

                    fetch('settings.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(async (response) => {
                        let data;
                        try {
                            data = await response.json();
                        } catch (err) {
                            throw new Error('Server returned an unexpected response. Please try again.');
                        }
                        if (!response.ok || !data.ok) {
                            const errorMsg = data.message || data.error;
                            throw new Error(errorMsg || 'Failed to save mobile number. Please try again.');
                        }
                        missingMobileModal.hide();
                        window.location.reload();
                    })
                    .catch((err) => {
                        if (missingMobileAlert && missingMobileAlertText) {
                            missingMobileAlertText.textContent = err.message || 'An error occurred while saving. Please try again.';
                            missingMobileAlert.classList.remove('d-none');
                        }
                        if (missingMobileSubmitBtn) missingMobileSubmitBtn.disabled = false;
                        if (missingMobileSpinner) missingMobileSpinner.classList.add('d-none');
                        if (missingMobileSubmitBtnText) missingMobileSubmitBtnText.textContent = 'Save and Continue';
                        if (missingMobileInput) missingMobileInput.focus();
                    });
                });
            }
        }
        <?php endif; ?>

        const notifUrl = <?= json_encode(BASE_URL . 'ajax/student_notifications.php') ?>;
        const markRead = (payload) => {
            const body = new URLSearchParams({ csrf_token: csrfToken, ...payload });
            return fetch(notifUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
                credentials: 'same-origin',
            }).then((r) => r.json()).catch(() => null);
        };
        const clearBadge = (unread) => {
            const badge = document.getElementById('studentNotifBadge');
            if (!badge) {
                return;
            }
            if (!unread) {
                badge.remove();
                return;
            }
            badge.textContent = unread > 9 ? '9+' : String(unread);
        };
        document.querySelectorAll('[data-notification-id]').forEach((el) => {
            el.addEventListener('click', () => {
                const id = el.getAttribute('data-notification-id');
                if (id) {
                    markRead({ id }).then((data) => {
                        if (data && data.ok) {
                            clearBadge(data.unread || 0);
                        }
                    });
                }
            });
        });
        const markAllHandler = () => {
            markRead({ all: '1' }).then((data) => {
                if (data && data.ok) {
                    clearBadge(0);
                    document.querySelectorAll('[data-notification-id]').forEach((el) => el.classList.remove('fw-semibold', 'bg-primary-subtle'));
                }
            });
        };
        document.getElementById('markAllNotificationsRead')?.addEventListener('click', markAllHandler);
        document.getElementById('markAllNotificationsReadTop')?.addEventListener('click', markAllHandler);
    });
</script>

<?php if (!empty($requireMobileNumberModal)): ?>
<div class="modal fade" id="missingMobileModal" tabindex="-1" aria-labelledby="missingMobileModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow">
            <form id="missingMobileForm" method="POST" action="settings.php">
                <?= function_exists('csrf_field') ? csrf_field() : '' ?>
                <input type="hidden" name="form" value="phone_request">
                <input type="hidden" name="phone_action" value="save_direct">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="redirect_to" value="<?= student_e($_SERVER['REQUEST_URI'] ?? 'dashboard.php') ?>">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="missingMobileModalLabel">Add your mobile number</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div id="missingMobileAlert" class="alert alert-danger py-2 px-3 mb-3 small <?= empty($errorMessage) ? 'd-none' : '' ?>" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="missingMobileAlertText"><?= !empty($errorMessage) ? student_e($errorMessage) : '' ?></span>
                    </div>
                    <p class="text-muted mb-3">Please enter a valid Sri Lankan mobile number to continue using the student portal.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="modal_new_whatsapp">Mobile number</label>
                        <input class="form-control rounded-3" id="modal_new_whatsapp" name="new_whatsapp" inputmode="tel"
                               autocomplete="tel" placeholder="0771234567" required autofocus>
                        <div class="form-text">e.g. 0771234567 or 94771234567</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 d-flex gap-2">
                    <button type="button" class="btn btn-light rounded-3 py-2 fw-semibold flex-grow-1" data-bs-dismiss="modal" id="missingMobileSkipBtn">Skip for now</button>
                    <button type="submit" class="btn btn-primary rounded-3 py-2 fw-semibold flex-grow-1" id="missingMobileSubmitBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="missingMobileSpinner" role="status" aria-hidden="true"></span>
                        <span id="missingMobileSubmitBtnText">Save and Continue</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
