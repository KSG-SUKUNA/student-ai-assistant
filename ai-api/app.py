from flask import Flask, request, jsonify
import subprocess
import os
import json

app = Flask(__name__)

# =========================
# Home route (for testing)
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
# RUN SERVER
# =========================
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=10000)