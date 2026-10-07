document.addEventListener('DOMContentLoaded', function () {
    function togglePassword(inputId, buttonId) {
        var input = document.getElementById(inputId);
        var button = document.getElementById(buttonId);
        if (!input || !button) return;
        button.addEventListener('click', function () {
            var icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
                button.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
                button.setAttribute('aria-label', 'Show password');
            }
        });
    }
    togglePassword('teacher_password', 'toggleTeacherPassword');
    togglePassword('student_password', 'toggleStudentPassword');

    document.querySelectorAll('form.js-login-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (window.uiFormBusy) {
                return;
            }
            var button = form.querySelector('button[type="submit"]');
            if (!button) return;
            button.disabled = true;
            var otpMode = form.getAttribute('data-otp-mode') === '1';
            button.innerHTML = otpMode
                ? 'Sending code...'
                : '<i class="fas fa-spinner fa-spin"></i>&nbsp; Signing in...';
        });
    });
});
