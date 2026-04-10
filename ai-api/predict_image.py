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
        safe_exit({"success": False, "error": "Image path not provided."}, 1)

    image_path = sys.argv[1]

    if not os.path.exists(image_path):
        safe_exit({"success": False, "error": "Image file not found."}, 1)

    if not os.path.exists(WEIGHTS_PATH):
        safe_exit({"success": False, "error": "Weights file not found."}, 1)

    try:
        emotion_labels = load_emotion_labels()
        model = build_model(num_classes=len(emotion_labels))
        model.load_weights(WEIGHTS_PATH)
    except Exception as e:
        safe_exit({"success": False, "error": f"Model load failed: {str(e)}"}, 1)

    image = cv2.imread(image_path)
    if image is None:
        safe_exit({"success": False, "error": "Failed to read image."}, 1)

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

    face_cascade = cv2.CascadeClassifier(
        cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
    )

    faces = face_cascade.detectMultiScale(
        gray,
        scaleFactor=1.1,
        minNeighbors=5,
        minSize=(50, 50)
    )

    if len(faces) == 0:
        safe_exit({
            "success": True,
            "emotion": "unknown",
            "confidence": 0.0,
            "engagement_score": 0.0,
            "engagement_level": "Disengaged",
            "recommended_aid": "Topic Video Lesson",
            "result_image_path": None,
            "message": "No face detected."
        })

    faces = sorted(faces, key=lambda f: f[2] * f[3], reverse=True)
    x, y, w, h = faces[0]

    padding = 30
    x1 = max(0, x - padding)
    y1 = max(0, y - padding)
    x2 = min(image.shape[1], x + w + padding)
    y2 = min(image.shape[0], y + h + padding)

    face = image[y1:y2, x1:x2]

    try:
        face_resized = cv2.resize(face, (224, 224))
        face_normalized = face_resized.astype("float32") / 255.0
        face_input = np.expand_dims(face_normalized, axis=0)

        prediction = model.predict(face_input, verbose=0)[0]
        emotion_index = int(np.argmax(prediction))
        confidence = float(np.max(prediction))

        emotion = emotion_labels[emotion_index]
        engagement_score = float(EMOTION_TO_SCORE.get(emotion, 0.0))
        engagement_level = level_from_score(engagement_score)
        recommended_aid = aid_from_level(engagement_level)

        outputs_dir = os.path.abspath(os.path.join(BASE_DIR, "..", "outputs"))
        os.makedirs(outputs_dir, exist_ok=True)

        output_filename = f"result_{os.path.basename(image_path)}"
        output_path = os.path.join(outputs_dir, output_filename)

        cv2.rectangle(image, (x1, y1), (x2, y2), (0, 180, 0), 4)

        label = f"{emotion.upper()} ({confidence*100:.1f}%)"
        cv2.putText(
            image,
            label,
            (x1, max(30, y1 - 10)),
            cv2.FONT_HERSHEY_SIMPLEX,
            1.0,
            (0, 255, 0),
            3,
            cv2.LINE_AA
        )

        cv2.imwrite(output_path, image)

        result_image_web_path = f"outputs/{output_filename}"

        safe_exit({
            "success": True,
            "emotion": emotion,
            "confidence": round(confidence, 4),
            "engagement_score": round(engagement_score, 2),
            "engagement_level": engagement_level,
            "recommended_aid": recommended_aid,
            "result_image_path": result_image_web_path
        })

    except Exception as e:
        safe_exit({"success": False, "error": f"Prediction failed: {str(e)}"}, 1)

if __name__ == "__main__":
    main()