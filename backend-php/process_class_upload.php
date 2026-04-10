<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $class_name = $_POST['class_name'];
    $stage = $_POST['stage'];

    $targetDir = "uploads/";
    $fileName = time() . "_" . basename($_FILES["media_file"]["name"]);
    $targetFile = $targetDir . $fileName;

    move_uploaded_file($_FILES["media_file"]["tmp_name"], $targetFile);

    $absoluteMediaPath = realpath($targetFile);

    // API CALL
    $apiUrl = "http://127.0.0.1:5000/predict-video";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $data = [
        'file' => new CURLFile($absoluteMediaPath)
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);

    if ($response === false) {
        die("API error: " . curl_error($ch));
    }

    curl_close($ch);

    $result = json_decode($response, true);

    $score = $result['engagement_score'] ?? 0;
    $level = $result['engagement_level'] ?? "Unknown";
    $aid = $result['recommended_aid'] ?? "N/A";
    $result_image = $result['result_image_path'] ?? null;

    $conn->exec("
    INSERT INTO class_uploads 
    (class_name, stage, media_type, file_path, result_image_path, engagement_score, engagement_level, recommended_aid)
    VALUES (
        '$class_name',
        '$stage',
        'video',
        '$targetFile',
        '$result_image',
        '$score',
        '$level',
        '$aid'
    )
    ");

    header("Location: dashboard_class.php?class=" . urlencode($class_name));
}
?>