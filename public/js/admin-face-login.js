const loginVideo = document.getElementById('face-login-video');
const loginCanvas = document.getElementById('face-login-canvas');
const btnStartLogin = document.getElementById('btn-start-webcam');
const btnScanFace = document.getElementById('btn-scan-face');
const loginAlert = document.getElementById('face-login-alert');

const stepFace = document.getElementById('step-face');
const loginForm = document.getElementById('face-login-form');
const emailInput = document.getElementById('email');
const tokenInput = document.getElementById('face_login_token');

// Emplacement des modèles face-api.js.
// On utilise ici l'hébergement public du dépôt face-api.js pour éviter les 404 sur /models.
// Pour un hébergement local, remplace par '/models' et copie les fichiers de poids dans public/models.
const loginModelPath = 'https://justadudewhohacks.github.io/face-api.js/models';
let loginDisplaySize;

function showLoginMessage(message, ok = false) {
    if (!loginAlert) {
        return;
    }
    loginAlert.classList.remove('d-none', 'alert-danger', 'alert-success');
    loginAlert.classList.add(ok ? 'alert-success' : 'alert-danger');
    loginAlert.textContent = message;
}

async function loadLoginModels() {
    await Promise.all([
        faceapi.nets.faceRecognitionNet.loadFromUri(loginModelPath),
        faceapi.nets.tinyFaceDetector.loadFromUri(loginModelPath),
        faceapi.nets.faceLandmark68TinyNet.loadFromUri(loginModelPath),
    ]);
}

btnStartLogin?.addEventListener('click', async (event) => {
    event.preventDefault();
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showLoginMessage('Votre navigateur ne supporte pas la webcam sur ce site. Essayez avec Chrome sur 127.0.0.1 ou localhost.');
            return;
        }

        await loadLoginModels();
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
        });
        loginVideo.srcObject = stream;
        loginVideo.addEventListener('loadedmetadata', () => {
            loginDisplaySize = {
                width: loginVideo.videoWidth,
                height: loginVideo.videoHeight,
            };
            loginCanvas.width = loginDisplaySize.width;
            loginCanvas.height = loginDisplaySize.height;
        });
        btnScanFace.disabled = false;
        showLoginMessage('Webcam activée. Cliquez sur « Scanner mon visage ».', true);
    } catch (e) {
        console.error('Erreur getUserMedia (login):', e);
        const reason = e && e.name === 'NotAllowedError'
            ? 'Accès caméra refusé. Vérifiez les autorisations du navigateur.'
            : 'Impossible d’activer la webcam. Vérifiez les autorisations du navigateur.';
        showLoginMessage(reason);
    }
});

btnScanFace?.addEventListener('click', async (event) => {
    event.preventDefault();
    try {
        const detection = await faceapi
            .detectSingleFace(loginVideo, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks(true);

        if (!detection) {
            showLoginMessage('Aucun visage détecté.');
            return;
        }

        const descriptor = await faceapi.computeFaceDescriptor(loginVideo);
        const descriptorArray = Array.from(descriptor);

        const formData = new FormData();
        formData.append('descriptor', JSON.stringify(descriptorArray));

        const response = await fetch('/admin/face-login/recognize', {
            method: 'POST',
            body: formData,
        });

        let data = null;
        try {
            data = await response.json();
        } catch (parseError) {
            // ignore, on garde un message générique plus bas
        }

        if (!response.ok) {
            const message = data && typeof data.message === 'string'
                ? data.message
                : 'Erreur lors de la reconnaissance faciale.';
            showLoginMessage(message);
            return;
        }

        if (!data || !data.isSuccessful) {
            const message = data && typeof data.message === 'string'
                ? data.message
                : 'Visage non reconnu.';
            showLoginMessage(message);
            return;
        }

        showLoginMessage(data.message || 'Visage reconnu.', true);

        // Étape 2 : remplir l'email + token et afficher le formulaire mot de passe
        if (emailInput && typeof data.email === 'string') {
            emailInput.value = data.email;
        }
        if (tokenInput && typeof data.token === 'string') {
            tokenInput.value = data.token;
        }

        stepFace.classList.add('d-none');
        loginForm.classList.remove('d-none');
    } catch (e) {
        showLoginMessage('Une erreur est survenue pendant la reconnaissance faciale.');
    }
});

