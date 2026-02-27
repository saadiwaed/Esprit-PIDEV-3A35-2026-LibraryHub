const video = document.getElementById('face-video');
const canvas = document.getElementById('face-canvas');
const btnStart = document.getElementById('btn-start-webcam');
const btnSave = document.getElementById('btn-save-face');
const alertBox = document.getElementById('face-alert');

// Emplacement des modèles face-api.js.
// Pour éviter d'avoir à les copier en local, on pointe vers l'hébergement officiel.
// Si tu veux les héberger toi‑même plus tard, remplace par '/models' et ajoute les fichiers dans public/models.
const modelPath = 'https://justadudewhohacks.github.io/face-api.js/models';
let displaySize;

function showMessage(message, ok = false) {
    if (!alertBox) {
        return;
    }
    alertBox.classList.remove('d-none', 'alert-danger', 'alert-success');
    alertBox.classList.add(ok ? 'alert-success' : 'alert-danger');
    alertBox.textContent = message;
}

async function loadModels() {
    await Promise.all([
        faceapi.nets.faceRecognitionNet.loadFromUri(modelPath),
        faceapi.nets.tinyFaceDetector.loadFromUri(modelPath),
        faceapi.nets.faceLandmark68TinyNet.loadFromUri(modelPath),
    ]);
}

btnStart?.addEventListener('click', async (event) => {
    event.preventDefault();
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showMessage('Votre navigateur ne supporte pas la webcam sur ce site. Essayez avec Chrome sur 127.0.0.1 ou localhost.');
            return;
        }

        await loadModels();
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
        });
        video.srcObject = stream;
        video.addEventListener('loadedmetadata', () => {
            displaySize = { width: video.videoWidth, height: video.videoHeight };
            canvas.width = displaySize.width;
            canvas.height = displaySize.height;
        });
        btnSave.disabled = false;
        showMessage('Webcam activée. Positionnez votre visage puis cliquez sur « Enregistrer mon visage ».', true);
    } catch (e) {
        console.error('Erreur getUserMedia (register):', e);
        const reason = e && e.name === 'NotAllowedError'
            ? 'Accès caméra refusé. Autorisez la caméra pour 127.0.0.1 dans votre navigateur.'
            : 'Impossible d’activer la webcam. Vérifiez les autorisations du navigateur.';
        showMessage(reason);
    }
});

btnSave?.addEventListener('click', async (event) => {
    event.preventDefault();
    try {
        const detection = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks(true);

        if (!detection) {
            showMessage('Aucun visage détecté. Approchez-vous de la caméra.');
            return;
        }

        const descriptor = await faceapi.computeFaceDescriptor(video);
        const descriptorArray = Array.from(descriptor);

        const formData = new FormData();
        formData.append('descriptor', JSON.stringify(descriptorArray));

        const response = await fetch('/admin/face/descriptor', {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            showMessage("Erreur lors de l'enregistrement du descripteur.");
            return;
        }

        const data = await response.json();
        if (!data.isSuccessful) {
            showMessage(data.message || "Impossible d'enregistrer le descripteur.");
            return;
        }

        showMessage(data.message || 'Visage enregistré avec succès.', true);
    } catch (e) {
        showMessage('Une erreur est survenue pendant la capture du visage.');
    }
});

