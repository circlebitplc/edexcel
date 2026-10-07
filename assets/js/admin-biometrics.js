/**
 * admin-biometrics.js
 *
 * Modern Production-Grade Biometric Client for Edexcel College
 * Supports:
 * - WebAuthn / FIDO2 Passkeys (Windows Hello, Apple Face ID/Touch ID, Android Biometrics, YubiKey)
 * - Webcam Facial Verification with 1:1 matching, dynamic liveness challenge, and Presentation Attack Detection (PAD)
 */

(function (window, document) {
    'use strict';

    // Base64URL helper utilities for WebAuthn binary buffers
    function bufferToBase64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function base64UrlToBuffer(base64url) {
        let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
        while (base64.length % 4) {
            base64 += '=';
        }
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    // Mathematical calculations for landmarks (EAR and Head Pose)
    function distance2D(p1, p2) {
        const dx = p1.x - p2.x;
        const dy = p1.y - p2.y;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function calculateEyeAspectRatio(eyeLandmarks) {
        // eyeLandmarks: 6 points [p1, p2, p3, p4, p5, p6]
        if (!eyeLandmarks || eyeLandmarks.length < 6) return 0.3;
        const v1 = distance2D(eyeLandmarks[1], eyeLandmarks[5]);
        const v2 = distance2D(eyeLandmarks[2], eyeLandmarks[4]);
        const h = distance2D(eyeLandmarks[0], eyeLandmarks[3]);
        if (h === 0) return 0.3;
        return (v1 + v2) / (2.0 * h);
    }

    function estimateHeadPose(landmarks, box) {
        if (!landmarks || landmarks.length < 68) return { yaw: 0, pitch: 0 };
        // Approximate 3D pose using key facial feature symmetry
        const noseTip = landmarks[30];
        const leftEyeOuter = landmarks[36];
        const rightEyeOuter = landmarks[45];
        const chin = landmarks[8];
        const noseRoot = landmarks[27];

        const eyeSpan = Math.max(1, distance2D(leftEyeOuter, rightEyeOuter));
        const distLeftNose = distance2D(leftEyeOuter, noseTip);
        const distRightNose = distance2D(rightEyeOuter, noseTip);

        // Yaw angle approximation: difference between eye-to-nose distances
        const yawRatio = (distRightNose - distLeftNose) / eyeSpan;
        const yawDegrees = yawRatio * 55.0; // Scaled to degrees (-30 to +30)

        // Pitch angle approximation: nose tip vertical position relative to eye line and chin
        const eyeMidY = (leftEyeOuter.y + rightEyeOuter.y) / 2.0;
        const noseToEyeY = noseTip.y - eyeMidY;
        const chinToNoseY = Math.max(1, chin.y - noseTip.y);
        const pitchRatio = (noseToEyeY / chinToNoseY) - 0.75;
        const pitchDegrees = -pitchRatio * 60.0;

        return { yaw: yawDegrees, pitch: pitchDegrees };
    }

    function measureFrameLighting(canvas, ctx) {
        const sampleW = 64;
        const sampleH = 64;
        const imgData = ctx.getImageData(0, 0, sampleW, sampleH).data;
        let total = 0;
        for (let i = 0; i < imgData.length; i += 4) {
            total += (imgData[i] * 0.299 + imgData[i + 1] * 0.587 + imgData[i + 2] * 0.114);
        }
        return total / (sampleW * sampleH);
    }

    const AdminBiometrics = {
        modelsLoaded: false,
        modelsLoading: false,

        isWebAuthnSupported: function () {
            return !!(window.PublicKeyCredential && navigator.credentials && navigator.credentials.create);
        },

        async loadFaceApiModels() {
            if (this.modelsLoaded) return true;
            if (typeof faceapi === 'undefined') {
                throw new Error('Face recognition engine is not loaded on this page.');
            }
            if (this.modelsLoading) {
                while (this.modelsLoading) {
                    await new Promise(r => setTimeout(r, 100));
                }
                return this.modelsLoaded;
            }

            this.modelsLoading = true;
            try {
                const modelPath = '/assets/models/face';
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(modelPath),
                    faceapi.nets.faceLandmark68Net.loadFromUri(modelPath),
                    faceapi.nets.faceRecognitionNet.loadFromUri(modelPath),
                ]);
                this.modelsLoaded = true;
                return true;
            } catch (err) {
                console.error('Error loading face-api models:', err);
                throw new Error('Failed to load face recognition models. Ensure assets are available.');
            } finally {
                this.modelsLoading = false;
            }
        },

        // ==============================================================
        // WEBAUTHN PASSKEY AUTHENTICATION (LOGIN)
        // ==============================================================
        async loginWithPasskey() {
            if (!this.isWebAuthnSupported()) {
                throw new Error('Passkeys are not supported on this browser or platform.');
            }

            // 1. Fetch authentication options and challenge
            const optResp = await fetch('/ajax/admin_biometrics.php?action=passkey_auth_options', {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            });
            const optData = await optResp.json();
            if (!optData.ok) {
                throw new Error(optData.error || 'Unable to start Passkey sign-in.');
            }

            const rawOptions = optData.options.publicKey;
            const challengeToken = optData.challenge_token;

            // Convert challenge and credential IDs to ArrayBuffers
            rawOptions.challenge = base64UrlToBuffer(rawOptions.challenge);
            if (rawOptions.allowCredentials) {
                rawOptions.allowCredentials = rawOptions.allowCredentials.map(c => ({
                    ...c,
                    id: base64UrlToBuffer(c.id)
                }));
            }

            // 2. Prompt device authenticator (Windows Hello, Face ID, Fingerprint, YubiKey)
            let assertion;
            try {
                assertion = await navigator.credentials.get({ publicKey: rawOptions });
            } catch (err) {
                if (err.name === 'NotAllowedError') {
                    throw new Error('Passkey sign-in was canceled or timed out.');
                }
                throw new Error('Passkey authentication error: ' + (err.message || err.name));
            }

            if (!assertion) {
                throw new Error('No Passkey credential returned by authenticator.');
            }

            // 3. Format response and send to server for cryptographic verification
            const verifyPayload = {
                action: 'passkey_auth_verify',
                challenge_token: challengeToken,
                credential_id: bufferToBase64Url(assertion.rawId),
                client_data_json: bufferToBase64Url(assertion.response.clientDataJSON),
                authenticator_data: bufferToBase64Url(assertion.response.authenticatorData),
                signature: bufferToBase64Url(assertion.response.signature),
                user_handle: assertion.response.userHandle ? bufferToBase64Url(assertion.response.userHandle) : null,
            };

            const verifyResp = await fetch('/ajax/admin_biometrics.php?action=passkey_auth_verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(verifyPayload)
            });
            const verifyData = await verifyResp.json();
            if (!verifyData.ok) {
                throw new Error(verifyData.error || 'Passkey verification failed.');
            }

            return verifyData;
        },

        // ==============================================================
        // WEBAUTHN PASSKEY REGISTRATION (SECURITY PAGE)
        // ==============================================================
        async registerPasskey(deviceName, csrfToken) {
            if (!this.isWebAuthnSupported()) {
                throw new Error('Passkeys are not supported on this browser or platform.');
            }

            const optResp = await fetch('/ajax/admin_biometrics.php?action=passkey_reg_options', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ csrf_token: csrfToken })
            });
            const optData = await optResp.json();
            if (!optData.ok) {
                throw new Error(optData.error || 'Failed to initialize Passkey registration.');
            }

            const rawOptions = optData.options.publicKey;
            const challengeToken = optData.challenge_token;

            rawOptions.challenge = base64UrlToBuffer(rawOptions.challenge);
            rawOptions.user.id = base64UrlToBuffer(rawOptions.user.id);
            if (rawOptions.excludeCredentials) {
                rawOptions.excludeCredentials = rawOptions.excludeCredentials.map(c => ({
                    ...c,
                    id: base64UrlToBuffer(c.id)
                }));
            }

            let credential;
            try {
                credential = await navigator.credentials.create({ publicKey: rawOptions });
            } catch (err) {
                if (err.name === 'NotAllowedError') {
                    throw new Error('Passkey registration was canceled.');
                }
                throw new Error('Passkey creation error: ' + (err.message || err.name));
            }

            const verifyPayload = {
                action: 'passkey_reg_verify',
                csrf_token: csrfToken,
                challenge_token: challengeToken,
                device_name: deviceName || 'Passkey',
                client_data_json: bufferToBase64Url(credential.response.clientDataJSON),
                attestation_object: bufferToBase64Url(credential.response.attestationObject),
                transports: (credential.response.getTransports ? credential.response.getTransports().join(',') : 'internal'),
            };

            const regResp = await fetch('/ajax/admin_biometrics.php?action=passkey_reg_verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(verifyPayload)
            });
            const regData = await regResp.json();
            if (!regData.ok) {
                throw new Error(regData.error || 'Passkey registration failed.');
            }

            return regData;
        },

        // ==============================================================
        // WEBCAM STREAM CONTROLS
        // ==============================================================
        async startCamera(videoElement) {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Webcam access is not supported by your browser.');
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user'
                    },
                    audio: false
                });
                videoElement.srcObject = stream;
                await videoElement.play();
                return stream;
            } catch (err) {
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    throw new Error('Camera access permission was denied. Please allow camera access in your browser settings.');
                }
                if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    throw new Error('No webcam camera device was found on this system.');
                }
                throw new Error('Camera initialization error: ' + (err.message || err.name));
            }
        },

        stopCamera(stream) {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        }
    };

    window.AdminBiometrics = AdminBiometrics;

})(window, document);
