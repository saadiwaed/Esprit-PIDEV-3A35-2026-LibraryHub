// public/js/admin-face-register.js
const video = document.getElementById('face-video');
const canvas = document.getElementById('face-canvas');
const btnStart = document.getElementById('btn-start-webcam');
const btnSave = document.getElementById('btn-save-face');
const alertBox = document.getElementById('face-alert');

function showMessage(msg, success = false) {
    alertBox.classList.remove('d-none', 'alert-danger', 'alert-success');
    alertBox.classList.add(success ? 'alert-success' : 'alert-danger');
    alertBox.textContent = msg;
}

function captureFrame() {
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0);
    return canvas.toDataURL('image/png').split(',')[1];
}

btnStart.addEventListener('click', async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        video.srcObject = stream;
        btnSave.disabled = false;
        showMessage('Webcam activée. Positionnez votre visage.', true);
    } catch (e) {
        showMessage('Erreur webcam: ' + e.message);
    }
});

btnSave.addEventListener('click', async () => {
    btnSave.disabled = true;
    showMessage('Encodage en cours (512-dim)...', true);

    try {
        const b64 = captureFrame();

        const res = await fetch('/admin/face/register-image', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image: b64 })
        });

        const data = await res.json();

        if (data.isSuccessful) {
            showMessage(data.message, true);
            setTimeout(() => location.reload(), 2000);
        } else {
            showMessage(data.message);
            btnSave.disabled = false;
        }
    } catch (e) {
        showMessage('Erreur de connexion au serveur.');
        btnSave.disabled = false;
    }
});