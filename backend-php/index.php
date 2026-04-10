<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Student Engagement System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="page">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="brand">
            <div class="brand-badge"></div>
            <div>
                <h1>Faculty Student Engagement System</h1>
                <p>AI-powered engagement analytics platform</p>
            </div>
        </div>

        <a href="dashboard_class.php" class="btn btn-primary">Open Analytics</a>
    </div>

    <!-- HERO -->
    <div class="hero">
        <h2>Emotion-based engagement intelligence</h2>
        <p>
            Measure, analyze, and improve student engagement using AI.
            Upload image or video, compare before vs after learning interventions,
            and generate actionable insights for faculty.
        </p>
    </div>

    <!-- MAIN ACTION CARDS -->
    <div class="grid-4">

        <a href="upload_student.php" class="card stat-card">
            <div>
                <div class="stat-label">Student Analysis</div>
                <div class="stat-value stat-primary">
                    <span class="icon">📤</span> Upload
                </div>
            </div>
            <div class="stat-sub">Analyze individual student engagement</div>
        </a>

        <a href="upload_class.php" class="card stat-card">
            <div>
                <div class="stat-label">Class Analysis</div>
                <div class="stat-value stat-purple">
                    <span class="icon">🏫</span> Upload
                </div>
            </div>
            <div class="stat-sub">Analyze class-level engagement</div>
        </a>

        <a href="dashboard_student.php" class="card stat-card">
            <div>
                <div class="stat-label">Student Dashboard</div>
                <div class="stat-value stat-success">
                    <span class="icon">👤</span> View
                </div>
            </div>
            <div class="stat-sub">View student insights & history</div>
        </a>

        <a href="dashboard_class.php" class="card stat-card">
            <div>
                <div class="stat-label">Class Dashboard</div>
                <div class="stat-value stat-warning">
                    <span class="icon">📊</span> View
                </div>
            </div>
            <div class="stat-sub">View analytics & comparisons</div>
        </a>

    </div>

    <!-- FEATURES -->
    <div class="grid-3">

        <div class="card">
            <h3 class="card-title">AI Emotion Detection</h3>
            <p class="muted">
                ResNet50-based model detects emotion from uploaded media
                and converts it into engagement scores.
            </p>
        </div>

        <div class="card">
            <h3 class="card-title">Before vs After Comparison</h3>
            <p class="muted">
                Compare verbal lecture vs preferred learning method
                and track engagement improvement.
            </p>
        </div>

        <div class="card">
            <h3 class="card-title">Smart Recommendations</h3>
            <p class="muted">
                Suggest personalized learning aids such as diagrams,
                videos, slides, and interactive tools.
            </p>
        </div>

    </div>

    <!-- EXTRA SECTION -->
    <div class="grid-2">

        <div class="card">
            <h3 class="card-title">System Overview</h3>
            <p class="muted">
                Upload student or class media → AI analysis → engagement score →
                recommendation → dashboard insights.
            </p>
        </div>

        <div class="card">
            <h3 class="card-title">Workflow</h3>
            <p class="muted">
                Upload → AI Processing → Score Mapping → Recommendation →
                Visualization in dashboards.
            </p>
        </div>

    </div>

</div>

</body>
</html>