<?php

$db_file = "database.sqlite";

$conn = new SQLite3($db_file);

// Create tables if not exists

$conn->exec("
CREATE TABLE IF NOT EXISTS engagement_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER,
    stage TEXT,
    media_type TEXT,
    file_path TEXT,
    result_image_path TEXT,
    engagement_score REAL,
    engagement_level TEXT,
    recommended_aid TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

$conn->exec("
CREATE TABLE IF NOT EXISTS class_uploads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_name TEXT,
    stage TEXT,
    media_type TEXT,
    file_path TEXT,
    result_image_path TEXT,
    engagement_score REAL,
    engagement_level TEXT,
    recommended_aid TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

?>