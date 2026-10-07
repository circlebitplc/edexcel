/**
 * admin-face-ui.js
 *
 * Interactive Webcam Facial Recognition UI controller for:
 * 1. Admin Login (with liveness / Presentation Attack Detection)
 * 2. Admin Face Enrollment (5-pose calibration & template generation)
 */

(function (window, document) {
    'use strict';

    const GESTURE_LABELS = {
        'LOOK_STRAIGHT': { title: 'Look straight at the camera', icon: 'bi-person' },
        'TURN_LEFT': { title: 'Turn your head slightly to the left', icon: 'bi-arrow-left' },
        'TURN_RIGHT': { title: 'Turn your head slightly to the right', icon: 'bi-arrow-right' },
        'NOD_UP': { title: 'Tilt your head slightly up', icon: 'bi-arrow-up' },
        'BLINK': { title: 'Blink your eyes naturally', icon: 'bi-eye' },
        'RETURN_CENTER': { title: 'Look straight to complete', icon: 'bi-check-circle' }
    };

    const POSE_LABELS = {
        'neutral': { title: 'Look straight at the camera', icon: 'bi-person' },
        'turn_left': { title: 'Turn your head slightly left', icon: 'bi-arrow-left' },
        'turn_right': { title: 'Turn your head slightly right', icon: 'bi-arrow-right' },
        'look_up': { title: 'Tilt your head slightly up', icon: 'bi-arrow-up' },
        'look_down': { title: 'Tilt your head slightly down', icon: 'bi-arrow-down' }
    };

    window.AdminFaceUI = {
        activeStream: null,
        animFrameId: null,

        // ==============================================================
        // FACE LOGIN FLOW
        // ==============================================================
        async startLoginModal() {
            const modalEl = document.getElementById('faceLoginModal');
            if (!modalEl) return;

            const modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
            modal.show();

            const statusText = modalEl.querySelector('.js-face-status');
            const stepIndicator = modalEl.querySelector('.js-face-step-indicator');
            const video = modalEl.querySelector('.js-face-video');
            const canvas = modalEl.querySelector('.js-face-canvas');
            const alertBox = modalEl.querySelector('.js-face-alert');
            const spinner = modalEl.querySelector('.js-face-spinner');

            alertBox.classList.add('d-none');
            spinner.classList.remove('d-none');
            statusText.textContent = 'Initializing camera and security models...';
            stepIndicator.innerHTML = '';

            try {
                // 1. Start camera & load models in parallel
                const streamPromise = AdminBiometrics.startCamera(video);
                const modelsPromise = AdminBiometrics.loadFaceApiModels();
                const challengePromise = fetch('/ajax/admin_biometrics.php?action=face_auth_challenge', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' }
                }).then(r => r.json());

                const [stream, _, challengeRes] = await Promise.all([streamPromise, modelsPromise, challengePromise]);
                this.activeStream = stream;

                if (!challengeRes.ok) {
                    throw new Error(challengeRes.error || 'Failed to initialize face liveness challenge.');
                }

                const sequence = challengeRes.sequence;
                const challengeToken = challengeRes.challenge_token;

                spinner.classList.add('d-none');
                this.runLoginLoop({
                    video, canvas, sequence, challengeToken, statusText, stepIndicator, alertBox, modalEl
                });

            } catch (err) {
                spinner.classList.add('d-none');
                alertBox.textContent = err.message || 'We could not start camera face verification.';
                alertBox.classList.remove('d-none');
                statusText.textContent = 'Camera initialization failed.';
            }

            modalEl.addEventListener('hidden.bs.modal', () => {
                this.cleanup();
            }, { once: true });
        },

        async runLoginLoop(ctx) {
            const { video, canvas, sequence, challengeToken, statusText, stepIndicator, alertBox, modalEl } = ctx;
            const displaySize = { width: video.videoWidth || 640, height: video.videoHeight || 480 };
            canvas.width = displaySize.width;
            canvas.height = displaySize.height;

            let stepIdx = 0;
            const telemetrySteps = [];
            let motionHistory = [];
            let lastBlinkTs = 0;
            let lastActionSuccessTs = Date.now();
            let finalDescriptor = null;

            const updateStepUI = () => {
                const currentAction = sequence[stepIdx] || 'RETURN_CENTER';
                const info = GESTURE_LABELS[currentAction] || { title: 'Follow prompt', icon: 'bi-check' };
                statusText.innerHTML = `<i class="bi ${info.icon} me-1 text-primary"></i> <strong>Step ${stepIdx + 1} of ${sequence.length}:</strong> ${info.title}`;

                stepIndicator.innerHTML = sequence.map((act, i) => {
                    const icon = (i < stepIdx) ? 'bi-check-circle-fill text-success' : ((i === stepIdx) ? 'bi-record-circle-fill text-primary' : 'bi-circle text-muted');
                    return `<i class="bi ${icon} fs-5" title="${act}"></i>`;
                }).join(' ');
            };

            updateStepUI();

            const detectFrame = async () => {
                if (!this.activeStream || !modalEl.classList.contains('show')) {
                    return;
                }

                try {
                    const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
                        .withFaceLandmarks()
                        .withFaceDescriptors();

                    const drawCtx = canvas.getContext('2d');
                    drawCtx.clearRect(0, 0, canvas.width, canvas.height);

                    if (detections.length === 0) {
                        drawCtx.strokeStyle = 'rgba(239, 68, 68, 0.6)';
                        drawCtx.lineWidth = 3;
                        drawCtx.strokeRect(canvas.width * 0.25, canvas.height * 0.15, canvas.width * 0.5, canvas.height * 0.7);
                        statusText.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i> Position your face inside the frame</span>';
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    if (detections.length > 1) {
                        statusText.innerHTML = '<span class="text-danger"><i class="bi bi-people-fill me-1"></i> Multiple faces detected! Only the administrator must be present</span>';
                        this.animFrameId = requestAnimationFrame(detectFrame);
                        return;
                    }

                    const det = detections[0];
                    finalDescriptor = Array.from(det.descriptor);

                    // Compute quality, landmarks, lighting, EAR, and head pose
                    const box = det.detection.box;
                    const boxRatio = box.width / canvas.width;
                    const landmarks = det.landmarks.positions;
                    const pose = this.estimateHeadPose(landmarks);
                    const leftEye = landmarks.slice(36, 42);
                    const rightEye = landmarks.slice(42, 48);
                    const ear = (this.calcEAR(leftEye) + this.calcEAR(rightEye)) / 2.0;
                    const lighting = this.calcLighting(video);

                    // Track micro-motion across frames
                    const nose = landmarks[30];
                    motionHistory.push({ x: nose.x, y: nose.y, t: Date.now() });
                    if (motionHistory.length > 15) motionHistory.shift();

                    // Draw guide oval (green if good, yellow if slight offset)
                    drawCtx.beginPath();
                    drawCtx.ellipse(box.x + box.width / 2, box.y + box.height / 2, box.width * 0.55, box.height * 0.7, 0, 0, 2 * Math.PI);
                    drawCtx.strokeStyle = 'rgba(34, 197, 94, 0.7)';
                    drawCtx.lineWidth = 3;
                    drawCtx.stroke();

                    const currentAction = sequence[stepIdx];
                    let actionSatisfied = false;

                    if (currentAction === 'LOOK_STRAIGHT' || currentAction === 'RETURN_CENTER') {
                        if (Math.abs(pose.yaw) < 9.0 && Math.abs(pose.pitch) < 8.0) {
                            actionSatisfied = true;
                        }
                    } else if (currentAction === 'TURN_LEFT') {
                        if (pose.yaw <= -11.0) {
                            actionSatisfied = true;
                        }
                    } else if (currentAction === 'TURN_RIGHT') {
                        if (pose.yaw >= 11.0) {
                            actionSatisfied = true;
                        }
                    } else if (currentAction === 'NOD_UP') {
                        if (pose.pitch >= 7.0) {
                            actionSatisfied = true;
                        }
                    } else if (currentAction === 'BLINK') {
                        if (ear < 0.20) {
                            lastBlinkTs = Date.now();
                        }
                        // Eye opened back up after dipping
                        if (lastBlinkTs > 0 && ear >= 0.25 && (Date.now() - lastBlinkTs) < 800) {
                            actionSatisfied = true;
                        }
                    }

                    // Check if action held / satisfied
                    if (actionSatisfied && (Date.now() - lastActionSuccessTs) > 400) {
                        telemetrySteps.push({
                            action: currentAction,
                            yaw: pose.yaw,
                            pitch: pose.pitch,
                            ear: ear,
                            face_count: 1,
                            box_ratio: boxRatio,
                            brightness: lighting,
                            timestamp: Date.now()
                        });

                        stepIdx++;
                        lastActionSuccessTs = Date.now();

                        if (stepIdx < sequence.length) {
                            updateStepUI();
                        } else {
                            // All challenges successfully passed! Submit to server for mathematical matching
                            this.finishLoginVerification({
                                challengeToken,
                                descriptor: finalDescriptor,
                                telemetrySteps,
                                motionHistory,
                                statusText,
                                alertBox
                            });
                            return; // Stop animation loop
                        }
                    }

                } catch (err) {
                    console.error('Face frame error:', err);
                }

                this.animFrameId = requestAnimationFrame(detectFrame);
            };

            this.animFrameId = requestAnimationFrame(detectFrame);
        },

        async finishLoginVerification(args) {
            const { challengeToken, descriptor, telemetrySteps, motionHistory, statusText, alertBox } = args;

            // Calculate micro-motion score across frames
            let motionScore = 0.01;
            if (motionHistory.length >= 2) {
                let deltaSum = 0;
                for (let i = 1; i < motionHistory.length; i++) {
                    const dx = motionHistory[i].x - motionHistory[i - 1].x;
                    const dy = motionHistory[i].y - motionHistory[i - 1].y;
                    deltaSum += Math.sqrt(dx * dx + dy * dy);
                }
                motionScore = deltaSum / motionHistory.length;
            }

            statusText.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary" role="status"></span> Cryptographically verifying biometric identity...';

            const payload = {
                action: 'face_auth_verify',
                challenge_token: challengeToken,
                descriptor: descriptor,
                telemetry: {
                    steps: telemetrySteps,
                    motion_score: motionScore
                }
            };

            try {
                const resp = await fetch('/ajax/admin_biometrics.php?action=face_auth_verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await resp.json();

                if (!data.ok) {
                    throw new Error(data.error || 'We could not verify your face. Please try again or use Passkey.');
                }

                statusText.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Identity confirmed. Redirecting...</span>';
                setTimeout(() => {
                    window.location.href = data.redirect || '/dashboard.php';
                }, 600);

            } catch (err) {
                alertBox.textContent = err.message || 'We could not verify your face. Please try again or use Passkey.';
                alertBox.classList.remove('d-none');
                statusText.textContent = 'Authentication could not be completed.';
            }
        },

        // ==============================================================
        // FACE ENROLLMENT FLOW (ADMIN SECURITY PAGE)
        // ==============================================================
        async startEnrollmentModal(csrfToken) {
            const modalEl = document.getElementById('faceEnrollModal');
            if (!modalEl) return;

            const modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
            modal.show();

            const statusText = modalEl.querySelector('.js-enrol-status');
            const stepList = modalEl.querySelector('.js-enrol-steps');
            const video = modalEl.querySelector('.js-enrol-video');
            const canvas = modalEl.querySelector('.js-enrol-canvas');
            const alertBox = modalEl.querySelector('.js-enrol-alert');
            const spinner = modalEl.querySelector('.js-enrol-spinner');
            const consentCheckbox = modalEl.querySelector('.js-enrol-consent');
            const captureBtn = modalEl.querySelector('.js-enrol-capture-btn');

            alertBox.classList.add('d-none');
            spinner.classList.remove('d-none');
            captureBtn.disabled = true;

            try {
                const streamPromise = AdminBiometrics.startCamera(video);
                const modelsPromise = AdminBiometrics.loadFaceApiModels();
                const challengePromise = fetch('/ajax/admin_biometrics.php?action=face_enrol_challenge', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ csrf_token: csrfToken, consent: 1 })
                }).then(r => r.json());

                const [stream, _, challengeRes] = await Promise.all([streamPromise, modelsPromise, challengePromise]);
                this.activeStream = stream;

                if (!challengeRes.ok) {
                    throw new Error(challengeRes.error || 'Failed to start biometric enrollment.');
                }

                const requiredPoses = challengeRes.required_poses;
                const challengeToken = challengeRes.challenge_token;

                spinner.classList.add('d-none');
                captureBtn.disabled = false;

                this.runEnrollmentLoop({
                    video, canvas, requiredPoses, challengeToken, csrfToken, statusText, stepList, alertBox, captureBtn, modalEl
                });

            } catch (err) {
                spinner.classList.add('d-none');
                alertBox.textContent = err.message || 'We could not start biometric enrollment.';
                alertBox.classList.remove('d-none');
                statusText.textContent = 'Enrollment failed.';
            }

            modalEl.addEventListener('hidden.bs.modal', () => {
                this.cleanup();
            }, { once: true });
        },

        async runEnrollmentLoop(ctx) {
            const { video, canvas, requiredPoses, challengeToken, csrfToken, statusText, stepList, alertBox, captureBtn, modalEl } = ctx;
            const samples = {};
            let currentPoseIdx = 0;
            let latestDet = null;

            const updateUI = () => {
                const currentPose = requiredPoses[currentPoseIdx];
                const info = POSE_LABELS[currentPose] || { title: 'Hold pose', icon: 'bi-person' };
                statusText.innerHTML = `<i class="bi ${info.icon} me-1 text-primary"></i> <strong>Pose ${currentPoseIdx + 1} of ${requiredPoses.length}:</strong> ${info.title}`;
                captureBtn.innerHTML = `<i class="bi bi-camera-fill me-1"></i> Capture "${currentPose}" Sample`;

                stepList.innerHTML = requiredPoses.map((p, idx) => {
                    const done = !!samples[p];
                    const active = (idx === currentPoseIdx);
                    const icon = done ? 'bi-check-circle-fill text-success' : (active ? 'bi-arrow-right-circle-fill text-primary' : 'bi-circle text-muted');
                    return `<span class="badge ${done ? 'bg-success-subtle text-success' : (active ? 'bg-primary-subtle text-primary border border-primary' : 'bg-light text-muted')} py-2 px-3 me-1 mb-1">
                        <i class="bi ${icon} me-1"></i> ${p}
                    </span>`;
                }).join('');
            };

            updateUI();

            const detectFrame = async () => {
                if (!this.activeStream || !modalEl.classList.contains('show')) return;

                try {
                    const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
                        .withFaceLandmarks()
                        .withFaceDescriptors();

                    const drawCtx = canvas.getContext('2d');
                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;
                    drawCtx.clearRect(0, 0, canvas.width, canvas.height);

                    if (detections.length === 1) {
                        latestDet = detections[0];
                        const box = latestDet.detection.box;
                        drawCtx.beginPath();
                        drawCtx.ellipse(box.x + box.width / 2, box.y + box.height / 2, box.width * 0.55, box.height * 0.7, 0, 0, 2 * Math.PI);
                        drawCtx.strokeStyle = 'rgba(59, 130, 246, 0.8)';
                        drawCtx.lineWidth = 3;
                        drawCtx.stroke();
                        captureBtn.disabled = false;
                    } else {
                        latestDet = null;
                        captureBtn.disabled = true;
                    }
                } catch (e) {}

                this.animFrameId = requestAnimationFrame(detectFrame);
            };

            this.animFrameId = requestAnimationFrame(detectFrame);

            captureBtn.onclick = async () => {
                if (!latestDet) {
                    alertBox.textContent = 'Please position exactly 1 face in the frame before capturing.';
                    alertBox.classList.remove('d-none');
                    return;
                }

                const currentPose = requiredPoses[currentPoseIdx];
                const box = latestDet.detection.box;
                const lighting = this.calcLighting(video);

                samples[currentPose] = {
                    descriptor: Array.from(latestDet.descriptor),
                    quality: {
                        face_count: 1,
                        box_ratio: box.width / canvas.width,
                        brightness: lighting,
                        sharpness: 25.0
                    }
                };

                currentPoseIdx++;
                alertBox.classList.add('d-none');

                if (currentPoseIdx < requiredPoses.length) {
                    updateUI();
                } else {
                    // All 5 poses captured! Submit for encryption & storage
                    captureBtn.disabled = true;
                    statusText.innerHTML = '<span class="spinner-border spinner-border-sm me-2 text-primary" role="status"></span> Generating encrypted template vault...';

                    try {
                        const resp = await fetch('/ajax/admin_biometrics.php?action=face_enrol_submit', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                            body: JSON.stringify({
                                csrf_token: csrfToken,
                                challenge_token: challengeToken,
                                samples: samples
                            })
                        });
                        const data = await resp.json();
                        if (!data.ok) {
                            throw new Error(data.error || 'Face enrollment failed.');
                        }

                        statusText.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-shield-check me-1"></i> Biometric Face Template Successfully Enrolled!</span>';
                        setTimeout(() => {
                            window.location.reload();
                        }, 1200);

                    } catch (err) {
                        alertBox.textContent = err.message || 'Face enrollment could not be completed.';
                        alertBox.classList.remove('d-none');
                        captureBtn.disabled = false;
                    }
                }
            };
        },

        // Helper calculations
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
            const d = (p1, p2) => Math.sqrt(Math.pow(p1.x - p2.x, 2) + Math.pow(p1.y - p2.y, 2));
            const v1 = d(pts[1], pts[5]);
            const v2 = d(pts[2], pts[4]);
            const h = d(pts[0], pts[3]);
            return (h === 0) ? 0.3 : (v1 + v2) / (2.0 * h);
        },

        estimateHeadPose(landmarks) {
            if (!landmarks || landmarks.length < 68) return { yaw: 0, pitch: 0 };
            const d = (p1, p2) => Math.sqrt(Math.pow(p1.x - p2.x, 2) + Math.pow(p1.y - p2.y, 2));
            const noseTip = landmarks[30];
            const leftEyeOuter = landmarks[36];
            const rightEyeOuter = landmarks[45];
            const chin = landmarks[8];
            const eyeSpan = Math.max(1, d(leftEyeOuter, rightEyeOuter));
            const yaw = ((d(rightEyeOuter, noseTip) - d(leftEyeOuter, noseTip)) / eyeSpan) * 55.0;
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
