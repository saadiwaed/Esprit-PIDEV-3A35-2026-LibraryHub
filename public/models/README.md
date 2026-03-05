Les fichiers de modèles `face-api.js` doivent être placés dans ce dossier.

Depuis le dépôt officiel `face-api.js`, téléchargez les modèles suivants (poids de quelques Mo chacun) :

- `face_recognition_model-weights_manifest.json` + fichiers binaires associés  
- `tiny_face_detector_model-weights_manifest.json` + binaires  
- `face_landmark_68_tiny_model-weights_manifest.json` + binaires

Puis copiez-les tels quels dans ce répertoire `public/models`.

Les scripts JavaScript utilisent le chemin `/models` :

- `faceapi.nets.faceRecognitionNet.loadFromUri('/models')`
- `faceapi.nets.tinyFaceDetector.loadFromUri('/models')`
- `faceapi.nets.faceLandmark68TinyNet.loadFromUri('/models')`

Assurez-vous que `http://localhost:8000/models/face_recognition_model-weights_manifest.json`
est bien accessible dans votre navigateur une fois les fichiers copiés.

