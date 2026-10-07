(function () {
    'use strict';

    function digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function looksLikeLkMobile(value) {
        var d = digits(value);
        return /^07\d{8}$/.test(d) || /^947\d{8}$/.test(d);
    }

    function setHidden(el, hidden) {
        if (!el) {
            return;
        }
        if (hidden) {
            el.setAttribute('hidden', 'hidden');
        } else {
            el.removeAttribute('hidden');
        }
    }

    function setOtpMode(form, on, opts) {
        opts = opts || {};
        var firstLogin = !!opts.firstLogin;
        var isStudent = form.getAttribute('data-student-login') === '1';
        form.setAttribute('data-otp-mode', on ? '1' : '0');
        if (firstLogin) {
            form.setAttribute('data-first-login', '1');
        } else {
            form.removeAttribute('data-first-login');
        }

        var otpFlag = form.querySelector('.js-otp-flag');
        var passFlag = form.querySelector('.js-password-flag');
        var passWrap = form.querySelector('.js-pass-wrap');
        var phoneWrap = form.querySelector('.js-phone-wrap');
        var userWrap = form.querySelector('.js-user-wrap');
        var passInput = form.querySelector('.js-pass-input');
        var phoneInput = form.querySelector('.js-phone-input');
        var userInput = form.querySelector('.js-user-input');
        var submit = form.querySelector('.js-login-submit');
        var forgot = form.querySelector('.js-forgot-link');
        var back = form.querySelector('.js-back-password');
        var intro = document.querySelector('.js-student-login-intro');

        if (otpFlag) {
            otpFlag.disabled = !on;
        }
        if (passFlag) {
            passFlag.disabled = on;
        }
        var manualOtp = form.getAttribute('data-manual-otp') === '1';
        var hidePassword = isStudent
            ? (firstLogin || manualOtp)
            : on;
        setHidden(passWrap, hidePassword);
        if (passInput) {
            passInput.disabled = hidePassword;
            passInput.required = !hidePassword;
            if (hidePassword) {
                passInput.value = '';
            }
        }

        if (isStudent) {
            setHidden(userWrap, false);
            setHidden(phoneWrap, true);
            if (phoneInput) {
                phoneInput.disabled = true;
                phoneInput.required = false;
                if (userInput) {
                    phoneInput.value = userInput.value;
                }
            }
            if (userInput) {
                userInput.required = true;
            }
        } else {
            setHidden(phoneWrap, !on);
            if (phoneInput) {
                phoneInput.disabled = !on;
                phoneInput.required = on;
                if (on && userInput && !phoneInput.value) {
                    phoneInput.value = userInput.value;
                }
            }
        }

        if (submit) {
            var loginLabel = submit.getAttribute('data-login-label') || 'Sign In';
            var otpLabel = submit.getAttribute('data-otp-label') || 'Send OTP';
            submit.textContent = on ? otpLabel : loginLabel;
        }

        var modeInput = form.querySelector('.js-login-mode');
        if (modeInput) {
            modeInput.value = on ? 'otp' : 'password';
        }

        setHidden(forgot, on);
        setHidden(back, !on || firstLogin);
        document.querySelectorAll('.js-student-register').forEach(function (el) {
            setHidden(el, firstLogin);
        });

        if (intro && isStudent) {
            if (firstLogin) {
                intro.textContent = intro.getAttribute('data-first-intro') ||
                    'First sign-in: we will send a verification code to this WhatsApp number.';
            } else if (on) {
                intro.textContent = intro.getAttribute('data-otp-intro') ||
                    'Enter your mobile number to receive a login code.';
            } else {
                intro.textContent = intro.getAttribute('data-password-intro') ||
                    'Enter your WhatsApp number and password.';
            }
        }
    }

    function bindForgot(form) {
        var forgot = form.querySelector('.js-forgot-link');
        var back = form.querySelector('.js-back-password');
        if (forgot) {
            forgot.addEventListener('click', function (event) {
                event.preventDefault();
                form.setAttribute('data-manual-otp', '1');
                setOtpMode(form, true, { firstLogin: false });
            });
        }
        if (back) {
            back.addEventListener('click', function (event) {
                event.preventDefault();
                form.removeAttribute('data-manual-otp');
                setOtpMode(form, false, { firstLogin: false });
            });
        }
    }

    function bindStudentLookup(form) {
        if (form.getAttribute('data-student-login') !== '1') {
            return;
        }
        var userInput = form.querySelector('.js-user-input');
        var statusUrl = form.getAttribute('data-status-url') || '/ajax/student_login_status.php';
        if (!userInput) {
            return;
        }

        var timer = null;
        var controller = null;
        var lastKey = '';

        function applyStatus(firstLogin, hasLoggedIn) {
            hasLoggedIn = !!hasLoggedIn;
            firstLogin = !!firstLogin && !hasLoggedIn;
            if (hasLoggedIn) {
                form.setAttribute('data-has-logged-in', '1');
            } else {
                form.removeAttribute('data-has-logged-in');
            }
            if (form.getAttribute('data-manual-otp') === '1' && !firstLogin) {
                setOtpMode(form, true, { firstLogin: false });
                return;
            }
            setOtpMode(form, firstLogin, { firstLogin: firstLogin });
        }

        function check() {
            var phone = userInput.value;
            if (!looksLikeLkMobile(phone)) {
                lastKey = '';
                if (form.getAttribute('data-manual-otp') === '1') {
                    setOtpMode(form, true, { firstLogin: false });
                } else {
                    setOtpMode(form, false, { firstLogin: false });
                }
                return;
            }
            var key = digits(phone);
            if (key === lastKey) {
                return;
            }
            lastKey = key;
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();
            var url = statusUrl + (statusUrl.indexOf('?') >= 0 ? '&' : '?') +
                'phone=' + encodeURIComponent(phone);

            fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                var firstLogin = !!(data && data.first_login);
                var hasLoggedIn = !!(data && data.has_logged_in);
                if (hasLoggedIn) {
                    firstLogin = false;
                }
                applyStatus(firstLogin, hasLoggedIn);
            }).catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
            });
        }

        function schedule() {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(check, 350);
        }

        userInput.addEventListener('input', schedule);
        userInput.addEventListener('blur', check);
        userInput.addEventListener('change', check);
        if (looksLikeLkMobile(userInput.value)) {
            check();
        }
    }

    function bindOtpAuto() {
        document.querySelectorAll('.js-otp-auto').forEach(function (input) {
            input.addEventListener('input', function () {
                var v = digits(input.value).slice(0, 6);
                input.value = v;
                if (v.length === 6 && input.form) {
                    input.form.requestSubmit ? input.form.requestSubmit() : input.form.submit();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form.js-login-form').forEach(function (form) {
            bindForgot(form);
            bindStudentLookup(form);
            if (form.getAttribute('data-otp-mode') === '1') {
                var first = form.getAttribute('data-first-login') === '1';
                if (form.getAttribute('data-student-login') === '1' && !first) {
                    form.setAttribute('data-manual-otp', '1');
                }
                setOtpMode(form, true, { firstLogin: first });
            }
        });
        bindOtpAuto();
    });
})();
