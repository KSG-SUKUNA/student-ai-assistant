from drive_upload import upload_file
import time

@app.route("/predict-image", methods=["POST"])
def predict_image():
    try:
        if 'file' not in request.files:
            return jsonify({"success": False, "error": "No file uploaded"}), 400

        file = request.files['file']
        path = f"temp_{int(time.time())}.jpg"
        file.save(path)

        result = subprocess.check_output(
            ["python3", "predict_image.py", path]
        ).decode()

        drive_url = upload_file(path, path)

        return jsonify({
            "success": True,
            "data": json.loads(result),
            "file_url": drive_url
        })

    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

    finally:
        if os.path.exists(path):
            os.remove(path)