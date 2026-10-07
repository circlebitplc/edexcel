<?php
declare(strict_types=1);
/**
 * views/home/login_modals.php
 * Staff and Student Portal Authentication Modals
 */
?>
<!-- Staff Login Modal -->
<div class="modal fade" id="staffLoginModal" tabindex="-1" aria-labelledby="staffLoginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg p-3">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h4 class="modal-title fw-bold text-dark" id="staffLoginModalLabel">Staff Sign In</h4>
                    <p class="text-muted small mb-0">For Teachers and Institute Administrators</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <?php if (!empty($teacherLoginError)): ?>
                    <div class="alert alert-danger py-2 small mb-3"><?= e($teacherLoginError) ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= e(BASE_URL) ?>index.php#teacher-login" autocomplete="on">
                    <?= csrf_field() ?>
                    <input type="hidden" name="teacher_login" value="1">
                    
                    <div class="mb-3">
                        <label for="teacher_username" class="form-label small fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="teacher_username" name="teacher_username" value="<?= e($_POST['teacher_username'] ?? '') ?>" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="teacher_password" class="form-label small fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="teacher_password" name="teacher_password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-xs">
                        Sign In as Staff
                    </button>

                    <div class="position-relative my-3 text-center">
                        <hr class="border-secondary-subtle">
                        <span class="position-absolute top-50 start-50 translate-middle px-3 text-muted small bg-white">OR</span>
                    </div>

                    <button type="button" class="btn btn-outline-primary w-100 py-2 fw-bold rounded-3 js-home-face-login" data-user-field="teacher_username">
                        <i class="bi bi-person-bounding-box me-1"></i> Sign In with Face ID
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Student Login Modal -->
<div class="modal fade" id="studentLoginModal" tabindex="-1" aria-labelledby="studentLoginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg p-3">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h4 class="modal-title fw-bold text-dark" id="studentLoginModalLabel">Student Portal Sign In</h4>
                    <p class="text-muted small mb-0">Access your classes, timetable, and study materials</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <?php if (!empty($studentLoginError)): ?>
                    <div class="alert alert-danger py-2 small mb-3"><?= e($studentLoginError) ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= e(BASE_URL) ?>index.php#student-login" autocomplete="on">
                    <?= csrf_field() ?>
                    <input type="hidden" name="student_login" value="1">
                    
                    <div class="mb-3">
                        <label for="student_username" class="form-label small fw-semibold">Username / Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-phone"></i></span>
                            <input type="text" class="form-control" id="student_username" name="student_username" value="<?= e($_POST['student_username'] ?? '') ?>" placeholder="077XXXXXXX" autocomplete="username" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="student_password" class="form-label small fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="student_password" name="student_password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-xs">
                        Sign In to Student Portal
                    </button>

                    <div class="position-relative my-3 text-center">
                        <hr class="border-secondary-subtle">
                        <span class="position-absolute top-50 start-50 translate-middle px-3 text-muted small bg-white">OR</span>
                    </div>

                    <button type="button" class="btn btn-outline-primary w-100 py-2 fw-bold rounded-3 mb-3 js-home-face-login" data-user-field="student_username">
                        <i class="bi bi-person-bounding-box me-1"></i> Sign In with Face ID
                    </button>
                    
                    <div class="text-center">
                        <a href="<?= BASE_URL ?>student/register.php" class="small text-decoration-none">
                            New student? Create an account here &rarr;
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Shared Face Login Modal for Homepage -->
<div class="modal fade" id="faceLoginModal" tabindex="-1" aria-labelledby="faceLoginModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="faceLoginModalLabel">
                    <i class="bi bi-person-bounding-box text-primary me-2"></i>Face ID Sign-In
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="alert alert-danger d-none js-face-alert small py-2 text-start" data-ui-keep="1"></div>

                <div class="position-relative mx-auto rounded-4 overflow-hidden shadow-sm" style="width: 100%; max-width: 420px; aspect-ratio: 4/3; background: #000;">
                    <video class="js-face-video w-100 h-100 object-fit-cover" playsinline autoplay muted></video>
                    <canvas class="js-face-canvas position-absolute top-0 start-0 w-100 h-100" style="pointer-events:none;"></canvas>
                    <div class="position-absolute top-50 start-50 translate-middle" style="width: 200px; height: 260px; border: 2px dashed rgba(255,255,255,0.35); border-radius: 50%; pointer-events:none;"></div>
                </div>

                <div class="mt-3">
                    <div class="js-face-status text-muted small mb-2">Position your face inside the frame.</div>
                    <div class="js-face-step-indicator d-flex justify-content-center gap-2 mb-2"></div>
                    <div class="js-face-spinner spinner-border text-primary spinner-border-sm mt-1" role="status"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
window.BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>assets/vendor/face-api/face-api.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/admin-biometrics.js?v=<?= filemtime(__DIR__ . '/../../assets/js/admin-biometrics.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/admin-face-ui.js?v=<?= filemtime(__DIR__ . '/../../assets/js/admin-face-ui.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-home-face-login').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var fieldName = this.getAttribute('data-user-field');
            var ident = '';
            if (fieldName) {
                var field = document.getElementById(fieldName);
                if (field && field.value) ident = field.value.trim();
            }
            var parentModal = this.closest('.modal');
            if (parentModal && typeof bootstrap !== 'undefined') {
                var inst = bootstrap.Modal.getInstance(parentModal);
                if (inst) inst.hide();
            }
            setTimeout(function() {
                if (window.AdminFaceUI) {
                    window.AdminFaceUI.startLoginModal(false, ident);
                }
            }, 300);
        });
    });
});
</script>
