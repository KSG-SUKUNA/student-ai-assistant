import os
os.environ["TF_CPP_MIN_LOG_LEVEL"] = "3"

import sys
import json
import cv2
import numpy as np
import tensorflow as tf
from tensorflow.keras.applications import ResNet50
from tensorflow.keras.layers import Dense, GlobalAveragePooling2D, Dropout
from tensorflow.keras.models import Model

BASE_DIR = os.path.dirname(__file__)
WEIGHTS_PATH = os.path.join(BASE_DIR, "emotion_weights.weights.h5")
CLASS_JSON_PATH = os.path.join(BASE_DIR, "class_indices.json")

EMOTION_TO_SCORE = {
    "happy": 90,
    "surprise": 80,
    "neutral": 70,
    "fear": 50,
    "sad": 40,
    "angry": 30,
    "disgust": 20
}

def level_from_score(score: float) -> str:
    if score <= 39:
        return "Disengaged"
    elif score <= 69:
        return "Moderate"
    return "Highly Engaged"

def aid_from_level(level: str) -> str:
    if level == "Disengaged":
        return "Topic Video Lesson"
    elif level == "Moderate":
        return "Slide Presentation"
    return "Continue Current Method"

def safe_exit(payload: dict, code: int = 0) -> None:
    print(json.dumps(payload))
    sys.exit(code)

def load_emotion_labels():
    if not os.path.exists(CLASS_JSON_PATH):
        raise FileNotFoundError("class_indices.json not found.")

    with open(CLASS_JSON_PATH, "r") as f:
        class_indices = json.load(f)

    labels = [None] * len(class_indices)
    for label, idx in class_indices.items():
        labels[int(idx)] = label

    return labels

def build_model(num_classes):
    base_model = ResNet50(
        weights="imagenet",
        include_top=False,
        input_shape=(224, 224, 3)
    )

    x = base_model.output
    x = GlobalAveragePooling2D()(x)
    x = Dropout(0.3)(x)
    x = Dense(256, activation="relu")(x)
    outputs = Dense(num_classes, activation="softmax")(x)

    model = Model(inputs=base_model.input, outputs=outputs)
    return model

def main():
    if len(sys.argv) < 2:
        safe_exit({"success": False, "error": "Video path not provided."}, 1)

    video_path = sys.argv[1]

    if not os.path.exists(video_path):
        safe_exit({"success": False, "error": "Video file not found."}, 1)

    if not os.path.exists(WEIGHTS_PATH):
        safe_exit({"success": False, "error": "Weights file not found."}, 1)

    try:
        emotion_labels = load_emotion_labels()
        model = build_model(num_classes=len(emotion_labels))
        model.load_weights(WEIGHTS_PATH)
    except Exception as e:
        safe_exit({"success": False, "error": f"Model load failed: {str(e)}"}, 1)

    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        safe_exit({"success": False, "error": "Failed to open video."}, 1)

    face_cascade = cv2.CascadeClassifier(
        cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
    )

    fps = cap.get(cv2.CAP_PROP_FPS)
    if fps <= 0:
        fps = 25

    frame_interval = int(fps * 2)  # one frame every 2 seconds
    frame_index = 0

    scores = []
    emotions_seen = []
    best_result_frame = None
    best_confidence = -1.0
    best_label = None

    while True:
        ret, frame = cap.read()
        if not ret:
            break

        if frame_index % frame_interval != 0:
            frame_index += 1
            continue

        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)

        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,
            minNeighbors=5,
            minSize=(50, 50)
        )

        if len(faces) > 0:
            faces = sorted(faces, key=lambda f: f[2] * f[3], reverse=True)
            x, y, w, h = faces[0]

            padding = 30
            x1 = max(0, x - padding)
            y1 = max(0, y - padding)
            x2 = min(frame.shape[1], x + w + padding)
            y2 = min(frame.shape[0], y + h + padding)

            face = frame[y1:y2, x1:x2]

            try:
                face_resized = cv2.resize(face, (224, 224))
                face_normalized = face_resized.astype("float32") / 255.0
                face_input = np.expand_dims(face_normalized, axis=0)

                prediction = model.predict(face_input, verbose=0)[0]
                emotion_index = int(np.argmax(prediction))
                confidence = float(np.max(prediction))
                emotion = emotion_labels[emotion_index]

                score = float(EMOTION_TO_SCORE.get(emotion, 0.0))
                scores.append(score)
                emotions_seen.append(emotion)

                if confidence > best_confidence:
                    best_confidence = confidence
                    best_label = f"{emotion.upper()} ({confidence*100:.1f}%)"
                    best_result_frame = frame.copy()
                    cv2.rectangle(best_result_frame, (x1, y1), (x2, y2), (0, 180, 0), 4)
                    cv2.putText(
                        best_result_frame,
                        best_label,
                        (x1, max(30, y1 - 10)),
                        cv2.FONT_HERSHEY_SIMPLEX,
                        1.0,
                        (0, 255, 0),
                        3,
                        cv2.LINE_AA
                    )

            except Exception:
                pass

        frame_index += 1

    cap.release()

    if len(scores) == 0:
        safe_exit({
            "success": True,
            "emotion": "unknown",
            "confidence": 0.0,
            "engagement_score": 0.0,
            "engagement_level": "Disengaged",
            "recommended_aid": "Topic Video Lesson",
            "result_image_path": None,
            "message": "No face detected in sampled frames."
        })

    avg_score = sum(scores) / len(scores)
    engagement_level = level_from_score(avg_score)
    recommended_aid = aid_from_level(engagement_level)

    dominant_emotion = max(set(emotions_seen), key=emotions_seen.count)

    result_image_web_path = None
    if best_result_frame is not None:
        outputs_dir = os.path.abspath(os.path.join(BASE_DIR, "..", "outputs"))
        os.makedirs(outputs_dir, exist_ok=True)

        output_filename = f"video_result_{os.path.basename(video_path)}.jpg"
        output_path = os.path.join(outputs_dir, output_filename)

        cv2.imwrite(output_path, best_result_frame)
        result_image_web_path = f"outputs/{output_filename}"

    safe_exit({
        "success": True,
        "emotion": dominant_emotion,
        "confidence": round(best_confidence if best_confidence > 0 else 0.0, 4),
        "engagement_score": round(avg_score, 2),
        "engagement_level": engagement_level,
        "recommended_aid": recommended_aid,
        "result_image_path": result_image_web_path
    })

if __name__ == "__main__":
    main()