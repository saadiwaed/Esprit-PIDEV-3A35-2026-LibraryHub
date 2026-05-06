/**
 * admin-face-login.js
 *
 * Sends a raw webcam frame (base64 PNG) to the Symfony controller.
 * The controller calls the InsightFace Python script server-side.
 * No face-api.js needed.
 */

const loginVideo  = document.getElementById('face-login-video');
const loginCanvas = document.getElementById('face-login-canvas');
const btnStart    = document.getElementById('btn-start-webcam');
const btnScan     = document.getElementById('btn-scan-face');
const loginAlert  = document.getElementById('face-login-alert');

const stepFace   = document.getElementById('step-face');
const loginForm  = document.getElementById('face-login-form');
const emailInput = document.getElementById('email');
const tokenInput = document.getElementById('face_login_token');

// ── Helpers ────────────────────────────────────────────────────────────────

function showMessage(message, ok = false) {
    if (!loginAlert) return;
    loginAlert.classList.remove('d-none', 'alert-danger', 'alert-success');
    loginAlert.classList.add(ok ? 'alert-success' : 'alert-danger');
    loginAlert.textContent = message;
}

/** Capture current video frame → base64 PNG string (no data-URI prefix). */
function captureFrame() {
    const canvas = loginCanvas || document.createElement('canvas');
    canvas.width  = loginVideo.videoWidth  || 640;
    canvas.height = loginVideo.videoHeight || 480;
    canvas.getContext('2d').drawImage(loginVideo, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/png').split(',')[1]; // strip "data:image/png;base64,"
}

// ── Start webcam ───────────────────────────────────────────────────────────

btnStart?.addEventListener('click', async (e) => {
    e.preventDefault();

    if (!navigator.mediaDevices?.getUserMedia) {
        showMessage('Votre navigateur ne supporte pas la webcam. Essayez Chrome sur localhost.');
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        loginVideo.srcObject = stream;
        if (btnScan) btnScan.disabled = false;
        showMessage('Webcam activée. Cliquez sur « Scanner mon visage ».', true);
    } catch (err) {
        showMessage(
            err?.name === 'NotAllowedError'
                ? 'Accès caméra refusé. Vérifiez les autorisations du navigateur.'
                : 'Impossible d\'activer la webcam.'
        );
    }
});

// ── Scan & recognize ───────────────────────────────────────────────────────

btnScan?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (btnScan) btnScan.disabled = true;
    showMessage('Analyse en cours…', true);

    try {
        const b64 = captureFrame();

        // Send as JSON so PHP can reliably parse it regardless of
        // multipart boundary issues with large base64 payloads.
        const response = await fetch('/admin/face-login/recognize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image: b64 }),
        });

        let data = null;
        try { data = await response.json(); } catch (_) {}

        if (!response.ok || !data?.isSuccessful) {
            showMessage(data?.message ?? 'Erreur lors de la reconnaissance faciale.');
            if (btnScan) btnScan.disabled = false;
            return;
        }

        showMessage(data.message || 'Visage reconnu.', true);

        if (typeof data.redirectUrl === 'string' && data.redirectUrl.length > 0) {
            window.location.href = data.redirectUrl;
            return;
        }

        if (emailInput && typeof data.email === 'string') emailInput.value = data.email;
        if (tokenInput && typeof data.token === 'string') tokenInput.value = data.token;

        stepFace?.classList.add('d-none');
        loginForm?.classList.remove('d-none');

    } catch (err) {
        console.error('Face-login error:', err);
        showMessage('Une erreur est survenue pendant la reconnaissance faciale.');
        if (btnScan) btnScan.disabled = false;
    }
});
