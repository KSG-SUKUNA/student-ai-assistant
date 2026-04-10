import sys
import json
import cv2
import numpy as np
from keras.models import load_model

# =========================
# LOAD MODEL
# =========================
MODEL_PATH = "emotion_model.keras"
WEIGHTS_PATH = "emotion_weights.weights.h5"

model = load_model(MODEL_PATH)
model.load_weights(WEIGHTS_PATH)

# emotion labels (adjust if yours different)
emotion_labels = ["Angry", "Disgust", "Fear", "Happy", "Sad", "Surprise", "Neutral"]


# =========================
# PROCESS VIDEO
# =========================
def analyze_video(video_path):
    cap = cv2.VideoCapture(video_path)

    emotions_count = {e: 0 for e in emotion_labels}
    total_frames = 0

    while True:
        ret, frame = cap.read()
        if not ret:
            break

        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        face = cv2.resize(gray, (48, 48))
        face = face / 255.0
        face = np.reshape(face, (1, 48, 48, 1))

        preds = model.predict(face, verbose=0)
        emotion_index = np.argmax(preds)
        emotion = emotion_labels[emotion_index]

        emotions_count[emotion] += 1
        total_frames += 1

    cap.release()

    # =========================
    # CALCULATE ENGAGEMENT
    # =========================
    if total_frames == 0:
        return {"error": "No frames processed"}

    positive = emotions_count["Happy"] + emotions_count["Surprise"]
    neutral = emotions_count["Neutral"]
    negative = total_frames - (positive + neutral)

    engagement_score = int((positive * 2 + neutral) / total_frames * 100)

    return {
        "engagement_score": engagement_score,
        "emotions": emotions_count,
        "total_frames": total_frames
    }


# =========================
# MAIN (IMPORTANT)
# =========================
if __name__ == "__main__":
    video_path = sys.argv[1]

    result = analyze_video(video_path)

    # MUST PRINT JSON (for app.py)
    print(json.dumps(result))