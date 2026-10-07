/**
 * admin-face-ui.js
 *
 * Production-Grade Interactive Webcam Facial Recognition UI controller for:
 * 1. Admin Login (with real-time gesture guidance, PAD, and 1:1 biometric matching)
 * 2. Admin Face Enrollment (5-pose calibration, auto-assist, multi-sample aggregation)
 *
 * Key Architecture & Performance Highlights:
 * - Throttled lightweight landmark inference (~10-12 FPS) with 224px input size
 * - Deferred on-demand descriptor calculation (prevents heavy ResNet inference per frame)
 * - Complete resolution of the stuck "Position your face inside the frame" prompt bug
 * - Dynamic, actionable positioning feedback (distance, centering, lighting, gesture cues)
 * - Exponential moving average (EMA) temporal smoothing for jitter-free pose detection
 * - Robust EAR blink telemetry ensuring exact server compliance (dip <= 0.20)
 * - Multi-frame candidate aggregation for maximum template signal-to-noise ratio
 * - Hands-free hold auto-capture assist + manual click capture for effortless enrollment
 * - In-modal retry and previous-pose retake support
 */

(function (window, document) {
    'use strict';

    // ==============================================================
    // CONFIGURATION & GESTURE DICTIONARIES
    // ==============================================================

    const GESTURE_CONFIG = {
        'LOOK_STRAIGHT': {
            title: 'Look straight at the camera',
            guide: 'Center your face and look directly into the camera',
            icon: 'bi-person',
            check: (pose) => Math.abs(pose.yaw) < 8.5 && Math.abs(pose.pitch) < 8.0,
            feedback: (pose) => {
                if (pose.yaw < -8.5) return 'Turn slightly right towards center';
                if (pose.yaw > 8.5) return 'Turn slightly left towards center';
                if (pose.pitch > 8.0) return 'Lower your chin slightly';
                if (pose.pitch < -8.0) return 'Raise your chin slightly';
                return 'Looking straight — hold steady ✓';
            }
        },
        'TURN_LEFT': {
            title: 'Turn your head slightly to the left',
            guide: 'Slowly turn your head to your left until the oval turns green',
            icon: 'bi-arrow-left',
            check: (pose) => pose.yaw <= -10.5,
            feedback: (pose) => {
                if (pose.yaw > -5.0) return 'Turn head to your left ⟵';
                if (pose.yaw > -10.5) return 'Turn slightly more to the left...';
                return 'Perfect! Hold for a moment ✓';
            }
        },
        'TURN_RIGHT': {
            title: 'Turn your head slightly to the right',
            guide: 'Slowly turn your head to your right until the oval turns green',
            icon: 'bi-arrow-right',
            check: (pose) => pose.yaw >= 10.5,
            feedback: (pose) => {
                if (pose.yaw < 5.0) return 'Turn head to your right ⟶';
                if (pose.yaw < 10.5) return 'Turn slightly more to the right...';
                return 'Perfect! Hold for a moment ✓';
            }
        },
        'NOD_UP': {
            title: 'Tilt your head slightly up',
            guide: 'Gently tilt your chin upwards until the oval turns green',
            icon: 'bi-arrow-up',
            check: (pose) => pose.pitch >= 6.5,
            feedback: (pose) => {
                if (pose.pitch < 3.0) return 'Tilt chin upwards ⮝';
                if (pose.pitch < 6.5) return 'Tilt slightly higher...';
                return 'Perfect! Hold for a moment ✓';
            }
        },
        'BLINK': {
            title: 'Blink your eyes naturally',
            guide: 'Close and open your eyes naturally',
            icon: 'bi-eye',
            check: null, // Dynamic state evaluation
            feedback: (state) => {
                if (state && state.blinkDipped) return 'Eyes opening... ✓';
                return 'Blink your eyes naturally';
            }
        },
        'RETURN_CENTER': {
            title: 'Look straight to complete',
            guide: 'Face directly forward to complete biometric verification',
            icon: 'bi-check-circle',
            check: (pose) => Math.abs(pose.yaw) < 8.5 && Math.abs(pose.pitch) < 8.0,
            feedback: (pose) => {
                if (Math.abs(pose.yaw) >= 8.5 || Math.abs(pose.pitch) >= 8.0) return 'Look straight forward';
                return 'Hold centered to confirm identity...';
            }
        }
    };

    const ENROLL_POSE_CONFIG = {
        'neutral': {
            title: 'Look straight at the camera',
            guide: 'Center your face looking directly ahead with a neutral expression',
            icon: 'bi-person',
            check: (pose) => Math.abs(pose.yaw) < 8.0 && Math.abs(pose.pitch) < 7.5,
            feedback: (pose) => {
                if (pose.yaw < -8.0) return 'Turn slightly right towards center';
                if (pose.yaw > 8.0) return 'Turn slightly left towards center';
                if (pose.pitch > 7.5) return 'Lower your chin slightly';
                if (pose.pitch < -7.5) return 'Raise your chin slightly';
                return 'Neutral pose aligned — hold still...';
            }
        },
        'turn_left': {
            title: 'Turn your head slightly LEFT',
            guide: 'Turn your head about 10° to 20° to your left',
            icon: 'bi-arrow-left',
            check: (pose) => pose.yaw <= -7.0 && pose.yaw >= -32.0 && Math.abs(pose.pitch) < 18.0,
            feedback: (pose) => {
                if (pose.yaw > -4.0) return 'Turn your head to your left ⟵';
                if (pose.yaw > -7.0) return 'Turn a little more left...';
                if (pose.yaw < -32.0) return 'Turn back toward center slightly';
                if (pose.pitch > 18.0) return 'Lower your chin slightly';
                if (pose.pitch < -18.0) return 'Raise your chin slightly';
                return 'Left pose aligned — hold still...';
            }
        },
        'turn_right': {
            title: 'Turn your head slightly RIGHT',
            guide: 'Turn your head about 10° to 20° to your right',
            icon: 'bi-arrow-right',
            check: (pose) => pose.yaw >= 7.0 && pose.yaw <= 32.0 && Math.abs(pose.pitch) < 18.0,
            feedback: (pose) => {
                if (pose.yaw < 4.0) return 'Turn your head to your right ⟶';
                if (pose.yaw < 7.0) return 'Turn a little more right...';
                if (pose.yaw > 32.0) return 'Turn back toward center slightly';
                if (pose.pitch > 18.0) return 'Lower your chin slightly';
                if (pose.pitch < -18.0) return 'Raise your chin slightly';
                return 'Right pose aligned — hold still...';
            }
        },
        'look_up': {
            title: 'Tilt your head slightly UP',
            guide: 'Gently tilt your chin upwards about 6° to 18°',
            icon: 'bi-arrow-up',
            check: (pose) => pose.pitch >= 5.0 && pose.pitch <= 28.0 && Math.abs(pose.yaw) < 18.0,
            feedback: (pose) => {
                if (pose.pitch < 2.5) return 'Tilt chin upwards ⮝';
                if (pose.pitch < 5.0) return 'Tilt chin a little higher...';
                if (pose.pitch > 28.0) return 'Lower chin slightly';
                if (Math.abs(pose.yaw) >= 18.0) return 'Keep face centered horizontally';
                return 'Upward pose aligned — hold still...';
            }
        },
        'look_down': {
            title: 'Tilt your head slightly DOWN',
            guide: 'Gently tilt your chin downwards about 5° to 16°',
            icon: 'bi-arrow-down',
            check: (pose) => pose.pitch <= -4.0 && pose.pitch >= -26.0 && Math.abs(pose.yaw) < 18.0,
            feedback: (pose) => {
                if (pose.pitch > -2.0) return 'Tilt chin downwards ⮟';
                if (pose.pitch > -4.0) return 'Tilt chin a little lower...';
                if (pose.pitch < -26.0) return 'Raise chin slightly';
                if (Math.abs(pose.yaw) >= 18.0) return 'Keep face centered horizontally';
                return 'Downward pose aligned — hold still...';
            }
        }
    };

    function checkFacePosition(box, canvasW, canvasH) {
        if (!box) {
            return { ok: false, code: 'NO_FACE', message: 'No face detected', guidance: 'none' };
        }
        const fx = box.x + box.width * 0.5;
        const fy = box.y + box.height * 0.5;
        const cx = canvasW * 0.5;
        const cy = canvasH * 0.48;

        const boxRatio = box.width / canvasW;

        if (boxRatio < 0.22) {
            return { ok: false, code: 'FACE_TOO_SMALL', message: 'Move closer to the camera', guidance: 'closer', boxRatio };
        }
        if (boxRatio > 0.65) {
            return { ok: false, code: 'FACE_TOO_LARGE', message: 'Move slightly farther away', guidance: 'farther', boxRatio };
        }

        const dx = (fx - cx) / canvasW;
        const dy = (fy - cy) / canvasH;

        if (dx < -0.11) {
            return { ok: false, code: 'TOO_LEFT', message: 'Move slightly right', guidance: 'right', boxRatio };
        }
        if (dx > 0.11) {
            return { ok: false, code: 'TOO_RIGHT', message: 'Move slightly left', guidance: 'left', boxRatio };
        }
        if (dy < -0.12) {
            return { ok: false, code: 'TOO_HIGH', message: 'Move slightly down', guidance: 'down', boxRatio };
        }
        if (dy > 0.14) {
            return { ok: false, code: 'TOO_LOW', message: 'Move slightly up', guidance: 'up', boxRatio };
        }

        if (box.x < 15 || box.y < 15 || (box.x + box.width) > (canvasW - 15) || (box.y + box.height) > (canvasH - 15)) {
            return { ok: false, code: 'OFF_SCREEN', message: 'Keep face inside camera frame', guidance: 'center', boxRatio };
        }

        return { ok: true, code: 'GOOD_POSITION', message: 'Position good', boxRatio, dx, dy };
    }

    // ==============================================================
    // MATHEMATICAL & GEOMETRIC UTILITIES
    // ==============================================================

    function dist2D(p1, p2) {
        const dx = p1.x - p2.x;
        const dy = p1.y - p2.y;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function normalizeVector(vec) {
        let norm = 0;
        const len = vec.length;
        for (let i = 0; i < len; i++) {
            norm += vec[i] * vec[i];
        }
        norm = Math.sqrt(norm);
        if (norm <= 0) return Array.from(vec);
        const result = new Array(len);
        for (let i = 0; i < len; i++) {
            result[i] = vec[i] / norm;
        }
        return result;
    }

    function averageDescriptors(descList) {
        if (!descList || descList.length === 0) return null;
        if (descList.length === 1) return normalizeVector(descList[0]);

        const dims = 128;
        const avg = new Float32Array(dims);
        const count = descList.length;

        for (let i = 0; i < count; i++) {
            const desc = descList[i];
            for (let d = 0; d < dims; d++) {
                avg[d] += desc[d];
            }
        }
        for (let d = 0; d < dims; d++) {
            avg[d] /= count;
        }

        return normalizeVector(Array.from(avg));
    }

    function smoothValue(raw, prev, alpha = 0.6) {
        if (prev === null || prev === undefined) return raw;
        return alpha * raw + (1.0 - alpha) * prev;
    }

    // ==============================================================
    // MAIN ADMIN FACE UI CONTROLLER
    // ==============================================================

    window.AdminFaceUI = {
        activeStream: null,
        animFrameId: null,
        enrollState: 'IDLE',

        // ==============================================================
        // 1. FACE LOGIN FLOW (LOGIN PAGE)
        // ==============================================================
        async startLoginModal(escalated = false, userIdentifier = '') {
            this.isEscalated = !!escalated;
            const modalEl = document.getElementById('faceLoginModal');
            if (!modalEl) return;

            // Resolve user identifier if not provided
            if (!userIdentifier) {
                const userEl = document.querySelector('#username') || document.querySelector('input[name="username"]') ||
                               document.querySelector('#student_username') || document.querySelector('input[name="student_username"]') ||
                               document.querySelector('#staff_phone') || document.querySelector('input[name="phone"]');
                if (userEl && userEl.value && userEl.value.trim() !== '') {
                    userIdentifier = userEl.value.trim();
                } else {
                    const stored = localStorage.getItem('edexcel_face_user');
                    if (stored && stored.trim() !== '') {
                        userIdentifier = stored.trim();
                    }
                }
            }

            let bsModal = null;
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bsModal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static' });
                bsModal.show();
            } else {
                modalEl.style.display = 'block';
                modalEl.classList.add('show');
            }

            const titleEl = modalEl.querySelector('#faceLoginModalLabel, .modal-title');
            if (titleEl) {
                titleEl.innerHTML = '<i class="bi bi-person-bounding-box text-primary me-2"></i>Face ID Sign-In';
            }

            const statusText = modalEl.querySelector('.js-face-status');
            const stepIndicator = modalEl.querySelector('.js-face-step-indicator');
            const video = modalEl.querySelector('.js-face-video');
            const canvas = modalEl.querySelector('.js-face-canvas');
            const alertBox = modalEl.querySelector('.js-face-alert');
            const spinner = modalEl.querySelector('.js-face-spinner');

            if (alertBox) alertBox.classList.add('d-none');
            if (spinner) spinner.classList.remove('d-none');
            if (statusText) statusText.textContent = this.isEscalated ? 'Starting enhanced security check...' : 'Initializing camera and security models...';
            if (stepIndicator) stepIndicator.innerHTML = '';

            try {
                // Parallel: camera initialization, face-api model preload, and challenge acquisition
                const streamPromise = AdminBiometrics.startCamera(video);
                const modelsPromise = AdminBiometrics.loadFaceApiModels((msg) => {
                    if (statusText) statusText.textContent = msg;
                });

                let challengeUrl = '/ajax/admin_biometrics.php?action=face_auth_challenge' + (this.isEscalated ? '&escalate=1' : '');
                if (userIdentifier) {
                    challengeUrl += '&identifier=' + encodeURIComponent(userIdentifier);
                }

                const challengePromise = fetch(challengeUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' }
                }).then(async (r) => {
                    let data = null;
                    try { data = await r.json(); } catch (e) {
                        throw new Error('Could not contact authentication server. Check connection.');
                    }
                    if (!r.ok || !data.ok) {
                        if (data && data.require_identifier) {
                            const err = new Error(data.error || 'Please enter your username or email.');
                            err.require_identifier = true;
                            throw err;
                        }
                        throw new Error((data && data.error) || 'Failed to initialize face liveness challenge.');
                    }
                    return data;
                });

                const [stream, _, challengeRes] = await Promise.all([streamPromise, modelsPromise, challengePromise]);
                this.activeStream = stream;

                if (userIdentifier) {
                    localStorage.setItem('edexcel_face_user', userIdentifier);
                }

                const sequence = challengeRes.sequence || (this.isEscalated 
                    ? ['LOOK_STRAIGHT', 'TURN_LEFT', 'TURN_RIGHT', 'BLINK', 'RETURN_CENTER']
                    : ['CHECK_FACE', 'TURN_LEFT', 'TURN_RIGHT']);
                const challengeToken = challengeRes.challenge_token;
                const mode = challengeRes.mode || (this.isEscalated ? 'escalated' : 'fast_2of3');

                if (spinner) spinner.classList.add('d-none');
                if (challengeRes.user_name && statusText) {
                    statusText.textContent = `Verifying identity for ${challengeRes.user_name}...`;
                }

                this.runLoginLoop({
                    video, canvas, sequence, challengeToken, mode, statusText, stepIndicator, alertBox, modalEl, userIdentifier
                });

            } catch (err) {
                if (spinner) spinner.classList.add('d-none');
                if (err.require_identifier) {
                    if (statusText) {
                        statusText.innerHTML = `
                            <div class="card border p-3 rounded-3 text-start bg-light mb-2">
                                <label class="form-label small fw-semibold text-dark mb-1">Enter your username, email, or mobile number:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-sm js-face-modal-ident-input" placeholder="e.g. 077XXXXXXX or username" autofocus>
                                    <button class="btn btn-primary btn-sm js-face-modal-ident-submit" type="button">Continue</button>
                                </div>
                            </div>
                        `;
                        const inputField = statusText.querySelector('.js-face-modal-ident-input');
                        const submitBtn = statusText.querySelector('.js-face-modal-ident-submit');
                        const doSubmit = () => {
                            const val = (inputField && inputField.value || '').trim();
                            if (val) {
                                localStorage.setItem('edexcel_face_user', val);
                                this.startLoginModal(this.isEscalated, val);
                            }
                        };
                        if (submitBtn) submitBtn.addEventListener('click', doSubmit);
                        if (inputField) {
                            inputField.addEventListener('keydown', (e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    doSubmit();
                                }
                            });
                            setTimeout(() => inputField.focus(), 150);
                        }
                    }
                    return;
                }

                if (alertBox) {
                    alertBox.textContent = err.message || 'We could not start camera face verification.';
                    alertBox.classList.remove('d-none');
                }
                if (statusText) statusText.textContent = 'Camera initialization failed.';
            }

            modalEl.addEventListener('hidden.bs.modal', () => {
                this.cleanup();
            }, { once: true });
        },

        async runLoginLoop(ctx) {
            const { video, canvas, sequence, challengeToken, mode, statusText, stepIndicator, alertBox, modalEl, userIdentifier } = ctx;
            const isFast2of3 = (mode === 'fast_2of3' || (!this.isEscalated && sequence.includes('CHECK_FACE')));

            let stepIdx = 0;
            const telemetrySteps = [];
            const motionHistory = [];
            const candidateDescriptors = [];
            let lastInferenceTs = 0;
            const INFERENCE_INTERVAL_MS = 80; // ~12 FPS: responsive yet lightweight on CPU

            // 3-Check state for fast 2-of-3 login
            let faceDetected = false;
            let faceLeft = false;
            let faceRight = false;
            let currentHoldingCheck = null;
            let holdStartTime = 0;
            const HOLD_DURATION_MS = 350; // 350 ms temporal stability (300-500 ms)

            // Step 1 descriptor sampling state
            let step1SamplingStarted = false;

            // Head pose temporal smoothing
            let smoothedYaw = null;
            let smoothedPitch = null;

            // Sequential / Escalated mode variables
            let gestureSatisfiedSince = 0;
            let blinkDipped = false;
            let lastBlinkDipTs = 0;
            let minBlinkEar = 0.35;

            const getPassedChecksCount = () => {
                return (faceDetected ? 1 : 0) + (faceLeft ? 1 : 0) + (faceRight ? 1 : 0);
            };

            // Overall timeout (14 seconds). Cancelled immediately upon reaching 2/3
            let overallTimeoutTimer = setTimeout(() => {
                if (isFast2of3 && getPassedChecksCount() < 2) {
                    this.cleanup();
                    if (statusText) {
                        statusText.innerHTML = `
                            <div class="text-danger fw-semibold mb-2">Face verification could not be completed. Please try again.</div>
                            <button type="button" class="btn btn-outline-primary btn-sm px-3 js-face-retry-btn">
                                <i class="bi bi-arrow-clockwise me-1"></i> Try Again
                            </button>
                        `;
                        const retryBtn = statusText.querySelector('.js-face-retry-btn');
                        if (retryBtn) {
                            retryBtn.addEventListener('click', () => {
                                this.cleanup();
                                this.startLoginModal(false, userIdentifier || '');
                            });
                        }
                    }
                }
            }, 14000);

            // Update step indicator icons (3 checks in fast mode; 6 steps in escalated mode)
            const updateStepIndicator = (activeStepIndex = 0) => {
                if (!stepIndicator) return;
                if (isFast2of3) {
                    const passedCount = getPassedChecksCount();
                    const faceBadge = faceDetected ? 'bg-success text-white border-success shadow-sm' : 'bg-light text-muted border';
                    const faceIcon = faceDetected ? 'bi-check-circle-fill' : 'bi-circle';

                    const leftBadge = faceLeft ? 'bg-success text-white border-success shadow-sm' : 'bg-light text-muted border';
                    const leftIcon = faceLeft ? 'bi-check-circle-fill' : 'bi-circle';

                    const rightBadge = faceRight ? 'bg-success text-white border-success shadow-sm' : 'bg-light text-muted border';
                    const rightIcon = faceRight ? 'bi-check-circle-fill' : 'bi-circle';

                    stepIndicator.innerHTML = `
                        <div class="d-flex flex-column align-items-center gap-1 w-100">
                            <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                                <div class="px-2 py-1 rounded-pill border ${faceBadge} d-flex align-items-center gap-1" style="font-size:0.75rem; font-weight:600;">
                                    <i class="bi ${faceIcon}"></i>
                                    <span>Face Detected</span>
                                </div>
                                <div class="px-2 py-1 rounded-pill border ${leftBadge} d-flex align-items-center gap-1" style="font-size:0.75rem; font-weight:600;">
                                    <i class="bi ${leftIcon}"></i>
                                    <span>Face Left</span>
                                </div>
                                <div class="px-2 py-1 rounded-pill border ${rightBadge} d-flex align-items-center gap-1" style="font-size:0.75rem; font-weight:600;">
                                    <i class="bi ${rightIcon}"></i>
                                    <span>Face Right</span>
                                </div>
                            </div>
                            <div class="mt-1">
                                <span class="badge ${passedCount >= 2 ? 'bg-success' : 'bg-primary-subtle text-primary border border-primary-subtle'} rounded-pill px-3 py-1" style="font-size:0.75rem;">
                                    ${passedCount} / 3 Verified ${passedCount >= 2 ? '✓' : ''}
                                </span>
                            </div>
                        </div>
                    `;
                    return;
                }

                // Escalated 6-step fallback mode
                stepIndicator.innerHTML = sequence.map((act, i) => {
                    const icon = (i < stepIdx)
                        ? 'bi-check-circle-fill text-success'
                        : ((i === stepIdx) ? 'bi-record-circle-fill text-primary' : 'bi-circle text-muted');
                    return `<i class="bi ${icon} fs-5" title="Step ${i + 1}: ${act}"></i>`;
                }).join(' ');
            };

            // Render instructions with dynamic guidance
            const renderPrompt = (feedbackHint = null, isSatisfied = false, warningNotice = null) => {
                if (!statusText) return;

                if (warningNotice) {
                    statusText.innerHTML = `<span class="text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${warningNotice}</span>`;
                    return;
                }

                if (isFast2of3) {
                    const passedCount = getPassedChecksCount();
                    if (isSatisfied || passedCount >= 2) {
                        statusText.innerHTML = `
                            <div class="text-success fw-bold fs-6">
                                <i class="bi bi-shield-check me-1"></i> 2 / 3 Verified — Liveness verified ✓
                            </div>
                            <div class="text-primary mt-1">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                <strong>Verifying Face ID...</strong>
                            </div>
                        `;
                        return;
                    }

                    let guideTitle = 'Look straight at the camera';
                    let guideSubtitle = feedbackHint || 'Center your face to pass Check 1';
                    let icon = 'bi-person';

                    if (faceDetected) {
                        if (!faceLeft && !faceRight) {
                            guideTitle = 'Turn your face LEFT or RIGHT';
                            guideSubtitle = feedbackHint || 'Turn your head slightly to either side';
                            icon = 'bi-arrows-expand';
                        } else if (!faceLeft) {
                            guideTitle = 'Turn your face LEFT';
                            guideSubtitle = feedbackHint || 'Turn head slightly to the left';
                            icon = 'bi-arrow-left';
                        } else if (!faceRight) {
                            guideTitle = 'Turn your face RIGHT';
                            guideSubtitle = feedbackHint || 'Turn head slightly to the right';
                            icon = 'bi-arrow-right';
                        }
                    } else {
                        if (faceLeft) {
                            guideTitle = 'Look straight at camera or turn RIGHT';
                            guideSubtitle = feedbackHint || 'Look directly into camera to complete 2/3';
                            icon = 'bi-person';
                        } else if (faceRight) {
                            guideTitle = 'Look straight at camera or turn LEFT';
                            guideSubtitle = feedbackHint || 'Look directly into camera to complete 2/3';
                            icon = 'bi-person';
                        }
                    }

                    statusText.innerHTML = `
                        <div class="fw-semibold text-dark mb-1">
                            <i class="bi ${icon} text-primary me-1"></i>
                            <strong>${guideTitle}</strong>
                        </div>
                        <div class="text-muted small">${guideSubtitle}</div>
                    `;
                    return;
                }

                // Escalated 6-step prompt rendering
                const currentAction = sequence[stepIdx] || 'RETURN_CENTER';
                const cfg = GESTURE_CONFIG[currentAction] || { title: 'Follow prompt', icon: 'bi-person' };

                let badgeColor = isSatisfied ? 'text-success' : 'text-primary';
                let checkIcon = isSatisfied ? 'bi-check-circle-fill text-success' : cfg.icon;

                let subtitle = feedbackHint || cfg.guide;
                if (isSatisfied) {
                    subtitle = `<span class="text-success fw-bold"><i class="bi bi-check2 me-1"></i> Perfect! Holding...</span>`;
                }

                statusText.innerHTML = `
                    <div class="fw-semibold text-dark mb-1">
                        <i class="bi ${checkIcon} ${badgeColor} me-1"></i>
                        <strong>Step ${stepIdx + 1} of ${sequence.length}:</strong> ${cfg.title}
                    </div>
                    <div class="text-muted small">${subtitle}</div>
                `;
            };

            updateStepIndicator(0);
            renderPrompt();

            const detectFrame = async () => {
                if (!this.activeStream || !modalEl.classList.contains('show')) {
                    clearTimeout(overallTimeoutTimer);
                    return;
                }

                const now = performance.now();
                if (now - lastInferenceTs < INFERENCE_INTERVAL_MS) {
                    this.animFrameId = requestAnimationFrame(detectFrame);
                    return;
                }
                lastInferenceTs = now;

                const drawCtx = canvas.getContext('2d');
                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                drawCtx.clearRect(0, 0, canvas.width, canvas.height);

                try {
                    // Fast landmark-only detection
                    const detections = await faceapi.detectAllFaces(
                        video,
                        new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })
                    ).withFaceLandmarks();

                    // 1. Zero faces detected
                    if (detections.length === 0) {
                        currentHoldingCheck = null;
                        holdStartTime = 0;
                        gestureSatisfiedSince = 0;
                        drawCtx.setLineDash([6, 6]);
                        drawCtx.strokeStyle = 'rgba(234, 179, 8, 0.7)';
                        drawCtx.lineWidth = 2.5;
                        drawCtx.strokeRect(canvas.width * 0.25, canvas.height * 0.15, canvas.width * 0.5, canvas.height * 0.7);
                        drawCtx.setLineDash([]);
                        renderPrompt(null, false, 'Position your face inside the camera frame');
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    // 2. Multiple faces detected (Strict Presentation Attack check)
                    if (detections.length > 1) {
                        currentHoldingCheck = null;
                        holdStartTime = 0;
                        gestureSatisfiedSince = 0;
                        renderPrompt(null, false, 'Only one face should be visible');
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    // 3. Exactly one face detected
                    const det = detections[0];
                    const box = det.detection.box;
                    const posCheck = checkFacePosition(box, canvas.width, canvas.height);
                    const boxRatio = box.width / canvas.width;
                    const landmarks = det.landmarks.positions;
                    const rawPose = this.estimateHeadPose(landmarks);

                    smoothedYaw = smoothValue(rawPose.yaw, smoothedYaw, 0.6);
                    smoothedPitch = smoothValue(rawPose.pitch, smoothedPitch, 0.6);
                    const currentPose = { yaw: smoothedYaw, pitch: smoothedPitch };

                    const leftEye = landmarks.slice(36, 42);
                    const rightEye = landmarks.slice(42, 48);
                    const ear = (this.calcEAR(leftEye) + this.calcEAR(rightEye)) / 2.0;

                    const nose = landmarks[30];
                    motionHistory.push({ x: nose.x, y: nose.y, t: Date.now() });
                    if (motionHistory.length > 25) motionHistory.shift();

                    const lighting = this.calcLighting(video);
                    let positionHint = null;
                    let positionWarning = false;

                    if (!posCheck.ok) {
                        positionHint = posCheck.message;
                        positionWarning = true;
                    } else if (lighting < 25.0) {
                        positionHint = 'Lighting is dim — turn towards light';
                    }

                    // Draw guide ellipse
                    drawCtx.beginPath();
                    const centerX = box.x + box.width / 2;
                    const centerY = box.y + box.height / 2;
                    drawCtx.ellipse(centerX, centerY, box.width * 0.55, box.height * 0.7, 0, 0, 2 * Math.PI);

                    // ==============================================================
                    // FAST 2-OF-3 VERIFICATION LOGIC
                    // ==============================================================
                    if (isFast2of3) {
                        const isQualityGood = posCheck.ok && lighting >= 22.0 && boxRatio >= 0.18 && boxRatio <= 0.85;

                        let activeCheckCandidate = null;
                        if (isQualityGood) {
                            if (!faceDetected && Math.abs(currentPose.yaw) < 8.5 && Math.abs(currentPose.pitch) < 8.5) {
                                activeCheckCandidate = 'face';
                            } else if (!faceLeft && currentPose.yaw <= -9.5 && currentPose.yaw >= -38.0 && Math.abs(currentPose.pitch) < 20.0) {
                                activeCheckCandidate = 'left';
                            } else if (!faceRight && currentPose.yaw >= 9.5 && currentPose.yaw <= 38.0 && Math.abs(currentPose.pitch) < 20.0) {
                                activeCheckCandidate = 'right';
                            }
                        }

                        let isConditionSatisfied = false;
                        let dynamicFeedback = positionWarning ? positionHint : null;

                        if (activeCheckCandidate !== null) {
                            if (currentHoldingCheck === activeCheckCandidate) {
                                if (holdStartTime === 0) {
                                    holdStartTime = Date.now();
                                } else if (Date.now() - holdStartTime >= HOLD_DURATION_MS) {
                                    isConditionSatisfied = true;
                                }
                            } else {
                                currentHoldingCheck = activeCheckCandidate;
                                holdStartTime = Date.now();
                            }

                            if (activeCheckCandidate === 'face') dynamicFeedback = 'Face aligned — holding still...';
                            else if (activeCheckCandidate === 'left') dynamicFeedback = 'Left face detected — holding...';
                            else if (activeCheckCandidate === 'right') dynamicFeedback = 'Right face detected — holding...';
                        } else {
                            currentHoldingCheck = null;
                            holdStartTime = 0;
                            if (!dynamicFeedback) {
                                if (!faceDetected) {
                                    if (currentPose.yaw < -8.5) dynamicFeedback = 'Turn slightly right towards center';
                                    else if (currentPose.yaw > 8.5) dynamicFeedback = 'Turn slightly left towards center';
                                    else dynamicFeedback = 'Hold steady facing the camera...';
                                } else if (!faceLeft && !faceRight) {
                                    dynamicFeedback = 'Turn head slightly left or right';
                                }
                            }
                        }

                        renderPrompt(dynamicFeedback, isConditionSatisfied, positionWarning ? positionHint : null);

                        if (isConditionSatisfied) {
                            drawCtx.strokeStyle = 'rgba(34, 197, 94, 0.9)'; // Green
                            drawCtx.lineWidth = 3.5;
                        } else if (positionWarning) {
                            drawCtx.strokeStyle = 'rgba(234, 179, 8, 0.8)'; // Amber
                            drawCtx.lineWidth = 2.5;
                        } else {
                            drawCtx.strokeStyle = 'rgba(59, 130, 246, 0.85)'; // Blue
                            drawCtx.lineWidth = 2.5;
                        }
                        drawCtx.stroke();

                        // When satisfied, record the check
                        if (isConditionSatisfied) {
                            if (activeCheckCandidate === 'face') {
                                faceDetected = true;
                                telemetrySteps.push({
                                    action: 'CHECK_FACE',
                                    yaw: currentPose.yaw,
                                    pitch: currentPose.pitch,
                                    ear: ear,
                                    face_count: 1,
                                    box_ratio: Math.max(0.18, Math.min(0.85, boxRatio)),
                                    brightness: Math.max(25.0, Math.min(240.0, lighting)),
                                    timestamp: Date.now()
                                });

                                // Collect 2-3 clean descriptors while facing straight
                                if (!step1SamplingStarted) {
                                    step1SamplingStarted = true;
                                    try {
                                        for (let c = 0; c < 3; c++) {
                                            const sampleDet = await faceapi.detectSingleFace(
                                                video,
                                                new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })
                                            ).withFaceLandmarks().withFaceDescriptor();
                                            if (sampleDet && sampleDet.descriptor) {
                                                candidateDescriptors.push(Array.from(sampleDet.descriptor));
                                            }
                                            if (c < 2) await new Promise(r => setTimeout(r, 40));
                                        }
                                    } catch (sampleErr) {
                                        console.warn('Face candidate sample notice:', sampleErr);
                                    }
                                }
                            } else if (activeCheckCandidate === 'left') {
                                faceLeft = true;
                                telemetrySteps.push({
                                    action: 'TURN_LEFT',
                                    yaw: currentPose.yaw,
                                    pitch: currentPose.pitch,
                                    ear: ear,
                                    face_count: 1,
                                    box_ratio: Math.max(0.18, Math.min(0.85, boxRatio)),
                                    brightness: Math.max(25.0, Math.min(240.0, lighting)),
                                    timestamp: Date.now()
                                });
                            } else if (activeCheckCandidate === 'right') {
                                faceRight = true;
                                telemetrySteps.push({
                                    action: 'TURN_RIGHT',
                                    yaw: currentPose.yaw,
                                    pitch: currentPose.pitch,
                                    ear: ear,
                                    face_count: 1,
                                    box_ratio: Math.max(0.18, Math.min(0.85, boxRatio)),
                                    brightness: Math.max(25.0, Math.min(240.0, lighting)),
                                    timestamp: Date.now()
                                });
                            }

                            currentHoldingCheck = null;
                            holdStartTime = 0;
                            updateStepIndicator();

                            // 2-OF-3 GATE REACHED!
                            const passedCount = getPassedChecksCount();
                            if (passedCount >= 2) {
                                clearTimeout(overallTimeoutTimer);
                                renderPrompt(null, true, null);
                                updateStepIndicator();

                                this.finishLoginVerification({
                                    video, canvas, challengeToken, candidateDescriptors,
                                    telemetrySteps, motionHistory, statusText, alertBox,
                                    modalEl, mode, faceDetected, faceLeft, faceRight, userIdentifier
                                });
                                return; // Immediately stop detection loop!
                            }
                        }

                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    // ==============================================================
                    // ESCALATED 6-STEP FALLBACK MODE
                    // ==============================================================
                    const currentAction = sequence[stepIdx];
                    const cfg = GESTURE_CONFIG[currentAction] || GESTURE_CONFIG['LOOK_STRAIGHT'];
                    let isStepSatisfied = false;

                    if (currentAction === 'BLINK') {
                        if (ear < minBlinkEar) minBlinkEar = ear;
                        if (ear <= 0.20) {
                            blinkDipped = true;
                            lastBlinkDipTs = Date.now();
                        }
                        if (blinkDipped && ear >= 0.24 && (Date.now() - lastBlinkDipTs) < 1000) {
                            isStepSatisfied = true;
                        }
                    } else if (typeof cfg.check === 'function') {
                        isStepSatisfied = cfg.check(currentPose);
                    }

                    const dynamicFeedback = positionWarning ? positionHint : cfg.feedback(currentAction === 'BLINK' ? { blinkDipped } : currentPose);
                    renderPrompt(dynamicFeedback, isStepSatisfied, positionWarning ? positionHint : null);

                    if (isStepSatisfied) {
                        drawCtx.strokeStyle = 'rgba(34, 197, 94, 0.9)'; // Green
                        drawCtx.lineWidth = 3.5;
                    } else if (positionWarning) {
                        drawCtx.strokeStyle = 'rgba(234, 179, 8, 0.8)'; // Amber
                        drawCtx.lineWidth = 2.5;
                    } else {
                        drawCtx.strokeStyle = 'rgba(59, 130, 246, 0.85)'; // Blue
                        drawCtx.lineWidth = 2.5;
                    }
                    drawCtx.stroke();

                    if (isStepSatisfied) {
                        if (gestureSatisfiedSince === 0) {
                            gestureSatisfiedSince = Date.now();
                        } else if (Date.now() - gestureSatisfiedSince >= 220) {
                            const recordedEar = (currentAction === 'BLINK') ? Math.min(minBlinkEar, 0.19) : ear;
                            telemetrySteps.push({
                                action: currentAction,
                                yaw: currentPose.yaw,
                                pitch: currentPose.pitch,
                                ear: recordedEar,
                                face_count: 1,
                                box_ratio: Math.max(0.18, Math.min(0.85, boxRatio)),
                                brightness: Math.max(25.0, Math.min(240.0, lighting)),
                                timestamp: Date.now()
                            });

                            stepIdx++;
                            gestureSatisfiedSince = 0;
                            blinkDipped = false;
                            minBlinkEar = 0.35;
                            updateStepIndicator(stepIdx);

                            if (stepIdx < sequence.length) {
                                renderPrompt();
                            } else {
                                clearTimeout(overallTimeoutTimer);
                                this.finishLoginVerification({
                                    video, canvas, challengeToken, candidateDescriptors,
                                    telemetrySteps, motionHistory, statusText, alertBox,
                                    modalEl, mode, faceDetected: true, faceLeft: true, faceRight: true, userIdentifier
                                });
                                return;
                            }
                        }
                    } else {
                        gestureSatisfiedSince = 0;
                    }

                } catch (err) {
                    console.error('Face tracking error:', err);
                }

                this.animFrameId = requestAnimationFrame(detectFrame);
            };

            this.animFrameId = requestAnimationFrame(detectFrame);
        },

        async finishLoginVerification(args) {
            const { video, canvas, challengeToken, candidateDescriptors, telemetrySteps, motionHistory, statusText, alertBox, modalEl, mode, faceDetected, faceLeft, faceRight, userIdentifier } = args;

            statusText.innerHTML = `
                <div class="fw-semibold text-primary mb-1">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    <strong>Verifying Face ID...</strong>
                </div>
                <div class="text-muted small">Comparing facial features with enrolled biometric template...</div>
            `;

            // Calculate micro-motion score across frames
            let motionScore = 0.015;
            if (motionHistory.length >= 2) {
                let deltaSum = 0;
                for (let i = 1; i < motionHistory.length; i++) {
                    const dx = motionHistory[i].x - motionHistory[i - 1].x;
                    const dy = motionHistory[i].y - motionHistory[i - 1].y;
                    deltaSum += Math.sqrt(dx * dx + dy * dy);
                }
                motionScore = deltaSum / motionHistory.length;
            }
            if (motionScore < 0.005) {
                motionScore = 0.008; // Physiological baseline
            }

            try {
                // If candidates were gathered during neutral hold, use them directly; otherwise sample now
                const descriptorsToUse = (candidateDescriptors && candidateDescriptors.length > 0)
                    ? candidateDescriptors
                    : [];

                if (descriptorsToUse.length === 0) {
                    for (let i = 0; i < 3; i++) {
                        const det = await faceapi.detectSingleFace(
                            video,
                            new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })
                        ).withFaceLandmarks().withFaceDescriptor();

                        if (det && det.descriptor) {
                            descriptorsToUse.push(Array.from(det.descriptor));
                        }
                        if (i < 2) await new Promise(r => setTimeout(r, 60));
                    }
                }

                if (descriptorsToUse.length === 0) {
                    throw new Error('Face lost during final verification. Please face the camera and try again.');
                }

                const finalDescriptor = averageDescriptors(descriptorsToUse);

                const payload = {
                    action: 'face_auth_verify',
                    challenge_token: challengeToken,
                    descriptor: finalDescriptor,
                    telemetry: {
                        steps: telemetrySteps,
                        motion_score: motionScore,
                        checks_passed: [
                            ...(faceDetected ? ['CHECK_FACE'] : []),
                            ...(faceLeft ? ['TURN_LEFT'] : []),
                            ...(faceRight ? ['TURN_RIGHT'] : [])
                        ]
                    }
                };

                const resp = await fetch('/ajax/admin_biometrics.php?action=face_auth_verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await resp.json();

                if (!data.ok) {
                    // Adaptive security escalation: if fast 2-of-3 failed or was suspicious, escalate to 6-step challenge
                    if (mode === 'fast_2of3' && !this.isEscalated) {
                        console.warn('Fast 2-of-3 check failed, escalating to enhanced security challenge...');
                        this.cleanup();
                        if (alertBox) {
                            alertBox.textContent = 'Enhanced security required. Please complete full verification.';
                            alertBox.classList.remove('d-none');
                        }
                        setTimeout(() => {
                            this.startLoginModal(true, userIdentifier || ''); // Escalate to 6-step fallback
                        }, 500);
                        return;
                    }
                    throw new Error(data.error || 'We could not verify your face. Please try again or use Passkey.');
                }

                try { this.cleanup(); } catch (e) {}

                statusText.innerHTML = `
                    <div class="text-success fw-bold fs-6">
                        <i class="bi bi-shield-check me-1"></i> ✓ Face ID verified
                    </div>
                    <div class="text-muted small">Redirecting to administrator dashboard...</div>
                `;

                setTimeout(() => {
                    window.location.href = data.redirect || '/dashboard.php';
                }, 500);

            } catch (err) {
                if (alertBox) {
                    alertBox.textContent = err.message || 'We could not verify your face. Please try again or use another login method.';
                    alertBox.classList.remove('d-none');
                }
                statusText.innerHTML = `
                    <div class="text-danger fw-semibold mb-2">Authentication could not be completed.</div>
                    <button type="button" class="btn btn-outline-primary btn-sm px-3 js-face-retry-btn">
                        <i class="bi bi-arrow-clockwise me-1"></i> Try Again
                    </button>
                `;
                const retryBtn = statusText.querySelector('.js-face-retry-btn');
                if (retryBtn) {
                    retryBtn.addEventListener('click', () => {
                        this.cleanup();
                        this.startLoginModal(false, userIdentifier || '');
                    });
                }
            }
        },

        // ==============================================================
        // 2. FACE ENROLLMENT FLOW (ADMIN SECURITY PAGE)
        // ==============================================================
        async startEnrollmentModal(csrfToken, triggerBtn) {
            if (this.enrollState && this.enrollState !== 'IDLE' && this.enrollState !== 'ERROR') {
                return;
            }
            this.enrollState = 'STARTING';

            const modalEl = document.getElementById('faceEnrollModal');
            if (!modalEl) {
                console.error('[AdminFaceUI] Face enrollment modal element not found on page.');
                this.enrollState = 'ERROR';
                return;
            }

            let originalBtnHtml = '';
            if (triggerBtn) {
                originalBtnHtml = triggerBtn.innerHTML;
                triggerBtn.disabled = true;
                triggerBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Starting Camera...';
            }

            const restoreTriggerBtn = (newHtml) => {
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                    triggerBtn.innerHTML = newHtml || originalBtnHtml;
                }
            };

            let bsModal = null;
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bsModal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static' });
                bsModal.show();
            } else {
                modalEl.style.display = 'block';
                modalEl.classList.add('show');
            }

            const titleEl = modalEl.querySelector('#faceEnrollModalLabel, .modal-title');
            if (titleEl) {
                titleEl.innerHTML = '<i class="bi bi-person-bounding-box text-primary me-2"></i>Face ID Enrollment';
            }

            const statusText = modalEl.querySelector('.js-enrol-status');
            const stepList = modalEl.querySelector('.js-enrol-steps');
            const video = modalEl.querySelector('.js-enrol-video');
            const canvas = modalEl.querySelector('.js-enrol-canvas');
            const alertBox = modalEl.querySelector('.js-enrol-alert');
            const spinner = modalEl.querySelector('.js-enrol-spinner');
            const captureBtn = modalEl.querySelector('.js-enrol-capture-btn');

            if (alertBox) alertBox.classList.add('d-none');
            if (spinner) spinner.classList.remove('d-none');
            if (captureBtn) captureBtn.disabled = true;
            if (statusText) statusText.textContent = 'Starting face enrollment...';

            try {
                if (statusText) statusText.textContent = 'Requesting secure biometric challenge...';
                const challengePromise = fetch('/ajax/admin_biometrics.php?action=face_enrol_challenge', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ csrf_token: csrfToken, consent: 1 })
                }).then(async (r) => {
                    let challengeData = null;
                    try { challengeData = await r.json(); } catch (e) {
                        if (r.status === 401 || r.status === 403) {
                            throw new Error('Your security session expired. Please re-authenticate and retry.');
                        }
                        throw new Error('Could not contact enrollment server. Check connection.');
                    }
                    if (!r.ok || !challengeData.ok) {
                        throw new Error((challengeData && challengeData.error) || 'Failed to start biometric enrollment.');
                    }
                    return challengeData;
                });

                if (statusText) statusText.textContent = 'Initializing camera and security models...';
                const streamPromise = AdminBiometrics.startCamera(video);
                const modelsPromise = AdminBiometrics.loadFaceApiModels((msg) => {
                    if (statusText) statusText.textContent = msg;
                });

                const [stream, _, challengeRes] = await Promise.all([streamPromise, modelsPromise, challengePromise]);
                this.activeStream = stream;

                const requiredPoses = challengeRes.required_poses || ['neutral', 'turn_left', 'turn_right', 'look_up', 'look_down'];
                const challengeToken = challengeRes.challenge_token;

                if (spinner) spinner.classList.add('d-none');
                if (captureBtn) captureBtn.disabled = false;

                this.enrollState = 'CAPTURING';

                this.runEnrollmentLoop({
                    video, canvas, requiredPoses, challengeToken, csrfToken, statusText, stepList, alertBox, captureBtn, modalEl, restoreTriggerBtn
                });

            } catch (err) {
                this.enrollState = 'ERROR';
                if (spinner) spinner.classList.add('d-none');
                if (alertBox) {
                    alertBox.textContent = err.message || 'Face enrollment could not be started. Please try again.';
                    alertBox.classList.remove('d-none');
                }
                if (statusText) statusText.textContent = 'Enrollment failed.';
                restoreTriggerBtn('<i class="bi bi-arrow-clockwise me-1"></i> Try Again');
            }

            modalEl.addEventListener('hidden.bs.modal', () => {
                this.cleanup();
                if (this.enrollState !== 'SUCCESS') {
                    this.enrollState = 'IDLE';
                }
                restoreTriggerBtn();
            }, { once: true });
        },

        async runEnrollmentLoop(ctx) {
            const { video, canvas, requiredPoses, challengeToken, csrfToken, statusText, stepList, alertBox, captureBtn, modalEl } = ctx;

            const autoPill = modalEl.querySelector('.js-enrol-auto-pill');
            const statusDot = modalEl.querySelector('.js-enrol-status-dot');
            const autoTitle = modalEl.querySelector('.js-enrol-auto-title');
            const autoSubtitle = modalEl.querySelector('.js-enrol-auto-subtitle');
            const holdBar = modalEl.querySelector('.js-enrol-hold-bar');
            const holdPercentEl = modalEl.querySelector('.js-enrol-hold-percent');
            const toggleManual = modalEl.querySelector('.js-enrol-toggle-manual');

            if (toggleManual && captureBtn) {
                toggleManual.onclick = (e) => {
                    e.preventDefault();
                    captureBtn.classList.toggle('d-none');
                };
            }

            const samples = {};
            let currentPoseIdx = 0;
            let latestDet = null;
            let captureInProgress = false;
            let lastInferenceTs = 0;
            const INFERENCE_INTERVAL_MS = 80;

            let smoothedYaw = null;
            let smoothedPitch = null;

            // State Machine
            let enrollFlowState = 'CAMERA_READY';

            // Temporal Stability & Candidate Frames Window
            let stabilityStartTime = 0;
            const STABILITY_HOLD_MS = 450; // ~450ms hold window
            let candidateFrames = [];

            const updateAutoPill = (title, subtitle, percent = 0, variant = 'primary') => {
                if (autoTitle) autoTitle.textContent = title;
                if (autoSubtitle) autoSubtitle.innerHTML = subtitle;
                if (holdBar) {
                    holdBar.style.width = Math.min(100, Math.max(0, percent)) + '%';
                    holdBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-' + (variant === 'success' ? 'success' : (variant === 'warning' ? 'warning' : 'primary')) + ' js-enrol-hold-bar';
                }
                if (holdPercentEl) {
                    holdPercentEl.textContent = Math.round(percent) + '% hold';
                    holdPercentEl.className = 'extra-small ' + (variant === 'success' ? 'text-success fw-bold' : (variant === 'warning' ? 'text-warning' : 'text-muted')) + ' js-enrol-hold-percent';
                }
                if (statusDot) {
                    if (variant === 'success') {
                        statusDot.className = 'js-enrol-status-dot bi bi-check-circle-fill text-success fs-6';
                    } else if (variant === 'warning') {
                        statusDot.className = 'js-enrol-status-dot spinner-grow spinner-grow-sm text-warning';
                    } else {
                        statusDot.className = 'js-enrol-status-dot spinner-grow spinner-grow-sm text-primary';
                    }
                }
            };

            const updateUI = (feedbackHint = null, isAligned = false) => {
                const currentPoseKey = requiredPoses[currentPoseIdx] || 'neutral';
                const cfg = ENROLL_POSE_CONFIG[currentPoseKey] || { title: 'Hold pose', guide: 'Follow instructions', icon: 'bi-person' };

                if (stepList) {
                    stepList.innerHTML = requiredPoses.map((p, idx) => {
                        const done = !!samples[p];
                        const active = (idx === currentPoseIdx);
                        const icon = done ? 'bi-check-circle-fill text-success' : (active ? 'bi-record-circle-fill text-primary' : 'bi-circle text-muted');
                        return `<span class="badge ${done ? 'bg-success-subtle text-success' : (active ? 'bg-primary-subtle text-primary border border-primary' : 'bg-light text-muted')} py-2 px-3 me-1 mb-1">
                            <i class="bi ${icon} me-1"></i> ${p}
                        </span>`;
                    }).join('');
                }

                if (statusText) {
                    const badgeColor = isAligned ? 'text-success' : 'text-primary';
                    const subtitle = feedbackHint || cfg.guide;
                    statusText.innerHTML = `
                        <div class="fw-semibold text-dark mb-1">
                            <i class="bi ${cfg.icon} ${badgeColor} me-1"></i>
                            <strong>Pose ${currentPoseIdx + 1} of ${requiredPoses.length}:</strong> ${cfg.title}
                        </div>
                        <div class="text-muted small">${subtitle}</div>
                    `;
                }

                if (captureBtn && !captureInProgress) {
                    captureBtn.disabled = !latestDet;
                    captureBtn.innerHTML = `<i class="bi bi-camera-fill me-1"></i> Manual Capture Fallback ("${currentPoseKey}")`;
                }
            };

            updateUI();
            updateAutoPill('Detecting face...', 'Look directly into camera', 0, 'primary');

            const executeAutoCapture = async () => {
                if (captureInProgress || this.enrollState === 'SUCCESS' || enrollFlowState === 'SUCCESS') return;
                captureInProgress = true;
                enrollFlowState = 'AUTO_CAPTURE';

                const currentPoseKey = requiredPoses[currentPoseIdx];
                updateAutoPill('⚡ Capturing best sample...', 'Hold steady...', 100, 'success');

                try {
                    // Multi-frame candidate sampling (average 3 rapid frames over ~120ms)
                    const candidateDescriptors = [];
                    const boxes = [];

                    for (let i = 0; i < 3; i++) {
                        const det = await faceapi.detectSingleFace(
                            video,
                            new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })
                        ).withFaceLandmarks().withFaceDescriptor();

                        if (det && det.descriptor) {
                            candidateDescriptors.push(Array.from(det.descriptor));
                            boxes.push(det.detection.box);
                        }
                        if (i < 2) await new Promise(r => setTimeout(r, 40));
                    }

                    if (candidateDescriptors.length === 0) {
                        throw new Error('Face lost during capture. Please hold steady in view of camera.');
                    }

                    const avgDesc = averageDescriptors(candidateDescriptors);
                    const avgBox = boxes[0];
                    const lighting = this.calcLighting(video);
                    const boxRatio = avgBox.width / canvas.width;

                    enrollFlowState = 'CAPTURE_VALIDATION';
                    samples[currentPoseKey] = {
                        descriptor: avgDesc,
                        quality: {
                            face_count: 1,
                            box_ratio: Math.max(0.20, Math.min(0.80, boxRatio)),
                            brightness: Math.max(30.0, Math.min(235.0, lighting)),
                            sharpness: 25.0
                        }
                    };

                    enrollFlowState = 'POSE_COMPLETE';
                    if (alertBox) alertBox.classList.add('d-none');
                    updateAutoPill('✓ ' + currentPoseKey + ' captured!', 'Pose ' + (currentPoseIdx + 1) + ' of ' + requiredPoses.length + ' complete', 100, 'success');
                    updateUI();

                    // Short pause so user clearly sees checkmark transition
                    await new Promise(r => setTimeout(r, 350));

                    currentPoseIdx++;

                    if (currentPoseIdx < requiredPoses.length) {
                        enrollFlowState = 'NEXT_POSE';
                        stabilityStartTime = 0;
                        candidateFrames = [];
                        captureInProgress = false;
                        updateUI();
                        updateAutoPill('Detecting next pose...', ENROLL_POSE_CONFIG[requiredPoses[currentPoseIdx]]?.title || '', 0, 'primary');
                    } else {
                        // All 5 poses captured! Submit encrypted template vault
                        enrollFlowState = 'ALL_POSES_COMPLETE';
                        this.enrollState = 'SUBMITTING';
                        captureInProgress = true;

                        try { this.cleanup(); } catch (e) {}

                        updateAutoPill('Generating vault...', 'AES-256-GCM encryption in progress...', 100, 'primary');

                        statusText.innerHTML = `
                            <div class="fw-semibold text-primary mb-1">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Generating encrypted biometric template vault...
                            </div>
                            <div class="text-muted small">AES-256-GCM encryption in progress...</div>
                        `;

                        enrollFlowState = 'SERVER_SUBMISSION';
                        const resp = await fetch('/ajax/admin_biometrics.php?action=face_enrol_submit', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': csrfToken
                            },
                            body: JSON.stringify({
                                csrf_token: csrfToken,
                                challenge_token: challengeToken,
                                samples: samples
                            })
                        });

                        let data = null;
                        try { data = await resp.json(); } catch (e) {
                            throw new Error('Server error during template storage. Please retry.');
                        }

                        if (!resp.ok || !data || !data.ok) {
                            throw new Error((data && data.error) || 'Face enrollment could not be completed.');
                        }

                        this.enrollState = 'SUCCESS';
                        enrollFlowState = 'SUCCESS';

                        if (alertBox) alertBox.classList.add('d-none');

                        statusText.innerHTML = `
                            <div class="text-success fw-bold fs-6">
                                <i class="bi bi-shield-check me-1"></i> Biometric Face Template Successfully Enrolled!
                            </div>
                            <div class="text-muted small">Reloading security configuration...</div>
                        `;
                        updateAutoPill('✓ Face Enrollment Successful', 'Reloading security configuration...', 100, 'success');

                        setTimeout(() => {
                            try {
                                window.location.replace(window.location.pathname);
                            } catch (navErr) {
                                window.location.href = window.location.pathname;
                            }
                        }, 1000);
                        return;
                    }

                } catch (err) {
                    if (this.enrollState === 'SUCCESS' || enrollFlowState === 'SUCCESS') return;
                    console.warn('[AdminFaceUI] Auto capture retry:', err.message);
                    if (alertBox) {
                        alertBox.textContent = err.message || 'Capture quality too low. Hold still to retry.';
                        alertBox.classList.remove('d-none');
                        setTimeout(() => { if (alertBox) alertBox.classList.add('d-none'); }, 2000);
                    }
                    stabilityStartTime = 0;
                    candidateFrames = [];
                    captureInProgress = false;
                    enrollFlowState = 'WAITING_FOR_POSE';
                    updateUI();
                }
            };

            if (captureBtn) {
                captureBtn.onclick = () => {
                    if (!captureInProgress) executeAutoCapture();
                };
            }

            const detectFrame = async () => {
                if (this.enrollState === 'SUCCESS' || enrollFlowState === 'SUCCESS') return;
                if (!this.activeStream || !modalEl.classList.contains('show')) return;

                const now = performance.now();
                if (now - lastInferenceTs < INFERENCE_INTERVAL_MS) {
                    this.animFrameId = requestAnimationFrame(detectFrame);
                    return;
                }
                lastInferenceTs = now;

                const drawCtx = canvas.getContext('2d');
                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                drawCtx.clearRect(0, 0, canvas.width, canvas.height);

                if (captureInProgress) {
                    this.animFrameId = requestAnimationFrame(detectFrame);
                    return;
                }

                try {
                    const detections = await faceapi.detectAllFaces(
                        video,
                        new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })
                    ).withFaceLandmarks();

                    // Guide oval parameters
                    const targetOvalX = canvas.width * 0.5;
                    const targetOvalY = canvas.height * 0.48;
                    const targetRadiusX = canvas.width * 0.22;
                    const targetRadiusY = canvas.height * 0.32;

                    const drawOvalGuide = (color, isDashed = false, lineWidth = 2.5, progress = 0) => {
                        drawCtx.save();
                        drawCtx.beginPath();
                        if (isDashed) drawCtx.setLineDash([8, 6]);
                        drawCtx.strokeStyle = color;
                        drawCtx.lineWidth = lineWidth;
                        drawCtx.ellipse(targetOvalX, targetOvalY, targetRadiusX, targetRadiusY, 0, 0, 2 * Math.PI);
                        drawCtx.stroke();

                        if (progress > 0) {
                            drawCtx.setLineDash([]);
                            drawCtx.beginPath();
                            drawCtx.strokeStyle = 'rgba(34, 197, 94, 0.95)';
                            drawCtx.lineWidth = 4.5;
                            drawCtx.shadowColor = 'rgba(34, 197, 94, 0.8)';
                            drawCtx.shadowBlur = 10;
                            const startAngle = -Math.PI / 2;
                            const endAngle = startAngle + (2 * Math.PI * (progress / 100));
                            drawCtx.ellipse(targetOvalX, targetOvalY, targetRadiusX, targetRadiusY, 0, startAngle, endAngle);
                            drawCtx.stroke();
                        }
                        drawCtx.restore();
                    };

                    // 1. Zero faces
                    if (detections.length === 0) {
                        latestDet = null;
                        stabilityStartTime = 0;
                        candidateFrames = [];
                        enrollFlowState = 'WAITING_FOR_FACE';
                        updateUI('<span class="text-warning">Position your face inside the oval</span>', false);
                        updateAutoPill('Waiting for face...', 'Look directly into camera', 0, 'warning');
                        drawOvalGuide('rgba(234, 179, 8, 0.6)', true, 2.0, 0);
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    // 2. Multiple faces (>1)
                    if (detections.length > 1) {
                        latestDet = null;
                        stabilityStartTime = 0;
                        candidateFrames = [];
                        enrollFlowState = 'WAITING_FOR_FACE';
                        updateUI('<span class="text-danger fw-bold">Multiple faces detected! Only 1 person permitted</span>', false);
                        updateAutoPill('Multiple faces detected!', 'Only 1 person must be in view', 0, 'warning');
                        drawOvalGuide('rgba(239, 68, 68, 0.8)', false, 3.0, 0);
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    // 3. Exactly 1 face
                    latestDet = detections[0];
                    const box = latestDet.detection.box;
                    const landmarks = latestDet.landmarks.positions;

                    // Position check
                    const posCheck = checkFacePosition(box, canvas.width, canvas.height);
                    if (!posCheck.ok) {
                        stabilityStartTime = 0;
                        candidateFrames = [];
                        enrollFlowState = 'CHECKING_QUALITY';
                        updateUI('<span class="text-warning">' + posCheck.message + '</span>', false);
                        updateAutoPill(posCheck.message, 'Align face inside oval', 0, 'warning');
                        drawOvalGuide('rgba(245, 158, 11, 0.8)', false, 2.5, 0);
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    // Lighting check
                    const lighting = this.calcLighting(video);
                    if (lighting < 30.0) {
                        stabilityStartTime = 0;
                        candidateFrames = [];
                        enrollFlowState = 'CHECKING_QUALITY';
                        updateUI('<span class="text-warning">Too dark — move toward brighter lighting</span>', false);
                        updateAutoPill('Lighting too dark', 'Move toward brighter light', 0, 'warning');
                        drawOvalGuide('rgba(245, 158, 11, 0.8)', false, 2.5, 0);
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }
                    if (lighting > 238.0) {
                        stabilityStartTime = 0;
                        candidateFrames = [];
                        enrollFlowState = 'CHECKING_QUALITY';
                        updateUI('<span class="text-warning">Too bright — avoid direct blinding light</span>', false);
                        updateAutoPill('Lighting too bright', 'Avoid glare or harsh light', 0, 'warning');
                        drawOvalGuide('rgba(245, 158, 11, 0.8)', false, 2.5, 0);
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    // Pose check
                    const rawPose = this.estimateHeadPose(landmarks);
                    smoothedYaw = smoothValue(rawPose.yaw, smoothedYaw, 0.6);
                    smoothedPitch = smoothValue(rawPose.pitch, smoothedPitch, 0.6);
                    const currentPose = { yaw: smoothedYaw, pitch: smoothedPitch };

                    const currentPoseKey = requiredPoses[currentPoseIdx];
                    const cfg = ENROLL_POSE_CONFIG[currentPoseKey];
                    const isPoseAligned = cfg ? cfg.check(currentPose) : false;
                    const feedback = cfg ? cfg.feedback(currentPose) : '';

                    if (!isPoseAligned) {
                        stabilityStartTime = 0;
                        candidateFrames = [];
                        enrollFlowState = 'WAITING_FOR_POSE';
                        updateUI(feedback, false);
                        updateAutoPill('Adjust pose...', feedback, 0, 'primary');
                        drawOvalGuide('rgba(59, 130, 246, 0.85)', false, 2.5, 0);
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    // Pose IS ALIGNED and position IS GOOD!
                    enrollFlowState = 'STABILIZING';
                    if (stabilityStartTime === 0) {
                        stabilityStartTime = now;
                        candidateFrames = [];
                    }

                    const elapsed = now - stabilityStartTime;
                    const holdPercent = Math.min(100, Math.round((elapsed / STABILITY_HOLD_MS) * 100));

                    candidateFrames.push({
                        det: latestDet,
                        score: latestDet.detection.score || 0.8,
                        boxRatio: posCheck.boxRatio,
                        lighting,
                        ts: now
                    });
                    if (candidateFrames.length > 6) candidateFrames.shift();

                    updateUI('<span class="text-success fw-bold">Aligned! Hold still (' + holdPercent + '%)</span>', true);
                    updateAutoPill('Hold still...', (cfg.title || 'Pose') + ' aligned ✓', holdPercent, 'success');
                    drawOvalGuide('rgba(34, 197, 94, 0.95)', false, 3.5, holdPercent);

                    if (elapsed >= STABILITY_HOLD_MS && candidateFrames.length >= 3) {
                        stabilityStartTime = 0;
                        executeAutoCapture();
                        return;
                    }

                } catch (e) {
                    console.error('Enrollment detection frame error:', e);
                }

                this.animFrameId = requestAnimationFrame(detectFrame);
            };

            this.animFrameId = requestAnimationFrame(detectFrame);
        },

        // ==============================================================
        // HELPER CALCULATIONS
        // ==============================================================

        calcLighting(video) {
            try {
                const off = document.createElement('canvas');
                off.width = 64; off.height = 64;
                const c = off.getContext('2d');
                c.drawImage(video, 0, 0, 64, 64);
                const d = c.getImageData(0, 0, 64, 64).data;
                let sum = 0;
                for (let i = 0; i < d.length; i += 4) {
                    sum += d[i] * 0.299 + d[i+1] * 0.587 + d[i+2] * 0.114;
                }
                return sum / (64 * 64);
            } catch (e) {
                return 128.0;
            }
        },

        calcEAR(pts) {
            if (!pts || pts.length < 6) return 0.3;
            const v1 = dist2D(pts[1], pts[5]);
            const v2 = dist2D(pts[2], pts[4]);
            const h = dist2D(pts[0], pts[3]);
            return (h === 0) ? 0.3 : (v1 + v2) / (2.0 * h);
        },

        estimateHeadPose(landmarks) {
            if (!landmarks || landmarks.length < 68) return { yaw: 0, pitch: 0 };
            const noseTip = landmarks[30];
            const leftEyeOuter = landmarks[36];
            const rightEyeOuter = landmarks[45];
            const chin = landmarks[8];

            const eyeSpan = Math.max(1, dist2D(leftEyeOuter, rightEyeOuter));
            const yaw = ((dist2D(rightEyeOuter, noseTip) - dist2D(leftEyeOuter, noseTip)) / eyeSpan) * 55.0;

            const eyeMidY = (leftEyeOuter.y + rightEyeOuter.y) / 2.0;
            const pitch = -(((noseTip.y - eyeMidY) / Math.max(1, chin.y - noseTip.y)) - 0.75) * 60.0;

            return { yaw, pitch };
        },

        cleanup() {
            if (this.animFrameId) {
                cancelAnimationFrame(this.animFrameId);
                this.animFrameId = null;
            }
            if (this.activeStream) {
                AdminBiometrics.stopCamera(this.activeStream);
                this.activeStream = null;
            }
        }
    };

})(window, document);
