from flask import Flask, request, jsonify
import subprocess
import os
import json
import urllib.request

app = Flask(__name__)

# =========================
# MODEL CONFIG
# =========================
MODEL_PATH = "emotion_model.keras"
WEIGHTS_PATH = "emotion_weights.weights.h5"

# Google Drive direct links
MODEL_URL = "https://drive.google.com/uc?id=18aC0jocrEqAlkm4sNGBcmFtva1ma3SBL"
WEIGHTS_URL = "https://drive.google.com/uc?id=1EKlKUap9y_Hp-1RxVGYbiE5Oc_UrubYD"


def download_file(url, path):
    if not os.path.exists(path):
        print(f"Downloading {path}...")
        urllib.request.urlretrieve(url, path)
        print(f"{path} downloaded!")


# =========================
# DOWNLOAD MODEL AT STARTUP
# =========================
download_file(MODEL_URL, MODEL_PATH)
download_file(WEIGHTS_URL, WEIGHTS_PATH)


# =========================
# HOME ROUTE
# =========================
@app.route("/")
def home():
    return "AI API Running ✅"


# =========================
# IMAGE PREDICTION
# =========================
@app.route("/predict-image", methods=["POST"])
def predict_image():
    try:
        if 'file' not in request.files:
            return jsonify({"success": False, "error": "No file uploaded"}), 400

        file = request.files['file']
        path = "temp.jpg"
        file.save(path)

        result = subprocess.check_output(
            ["python", "predict_image.py", path]
        ).decode()

        return jsonify(json.loads(result))

    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

    finally:
        if os.path.exists("temp.jpg"):
            os.remove("temp.jpg")


# =========================
# VIDEO PREDICTION
# =========================
@app.route("/predict-video", methods=["POST"])
def predict_video():
    try:
        if 'file' not in request.files:
            return jsonify({"success": False, "error": "No file uploaded"}), 400

        file = request.files['file']
        path = "temp.mp4"
        file.save(path)

        result = subprocess.check_output(
            ["python", "predict_video.py", path]
        ).decode()

        return jsonify(json.loads(result))

    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

    finally:
        if os.path.exists("temp.mp4"):
            os.remove("temp.mp4")


# =========================
# RUN SERVER (RENDER READY)
# =========================
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=10000)