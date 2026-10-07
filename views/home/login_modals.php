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
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-xs mb-3">
                        Sign In to Student Portal
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
