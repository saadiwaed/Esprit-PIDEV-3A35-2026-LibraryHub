import sys
import os
import cv2
import numpy as np

if len(sys.argv) > 2:
    INPUT_FILE = sys.argv[1]
    OUTPUT_FILE = sys.argv[2]
else:
    OUTPUT_FILE = os.path.join(os.environ.get('TEMP', '/tmp'), 'face_result.txt')
    INPUT_FILE  = os.path.join(os.environ.get('TEMP', '/tmp'), 'face_capture.png')

def encode_from_file():
    with open(OUTPUT_FILE, 'w') as f:
        f.write("PROCESSING")

    try:
        from insightface.app import FaceAnalysis

        # Use buffalo_l for better 512-dim detection
        app = FaceAnalysis(name='buffalo_l', providers=['CPUExecutionProvider'])
        app.prepare(ctx_id=0, det_size=(640, 640))

        img = cv2.imread(INPUT_FILE)
        if img is None:
            with open(OUTPUT_FILE, 'w') as f:
                f.write("ERROR: Cannot read image")
            return

        # Improve detection: resize + light preprocessing
        h, w = img.shape[:2]
        if max(h, w) > 1280:
            scale = 1280 / max(h, w)
            img = cv2.resize(img, (0, 0), fx=scale, fy=scale)

        # Optional: slight enhancement
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        if cv2.mean(gray)[0] < 80:  # too dark
            img = cv2.convertScaleAbs(img, alpha=1.3, beta=30)

        faces = app.get(img)

        print(f"Image size: {img.shape} | Faces detected: {len(faces)}", file=sys.stderr)

        if not faces:
            with open(OUTPUT_FILE, 'w') as f:
                f.write("ERROR: No face detected")
            return

        embedding = faces[0].embedding

        # L2 normalize
        norm = np.linalg.norm(embedding)
        if norm > 0:
            embedding = embedding / norm

        with open(OUTPUT_FILE, 'w') as f:
            f.write(",".join([f"{x:.8f}" for x in embedding]))

        print(f"SUCCESS: {len(embedding)}-dimensional descriptor", file=sys.stderr)

    except Exception as ex:
        import traceback
        traceback.print_exc(file=sys.stderr)
        with open(OUTPUT_FILE, 'w') as f:
            f.write(f"ERROR: {str(ex)}")

if __name__ == "__main__":
    encode_from_file()