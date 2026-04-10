<?php
include 'db.php';

$class_name = isset($_GET['class_name']) ? trim($_GET['class_name']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$method_filter = isset($_GET['method']) ? trim($_GET['method']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$fully_filter = isset($_GET['fully']) ? trim($_GET['fully']) : '';

$classesQuery = $conn->query("
    SELECT DISTINCT class_name
    FROM students
    WHERE class_name IS NOT NULL AND class_name <> ''
    ORDER BY class_name ASC
");

$studentsData = [];
$totalStudents = 0;

$beforeCounts = [
    'Disengaged' => 0,
    'Moderate' => 0,
    'Highly Engaged' => 0
];

$afterCounts = [
    'Disengaged' => 0,
    'Moderate' => 0,
    'Highly Engaged' => 0
];

$learningPrefCounts = [];
$totalBeforeScore = 0;
$totalAfterScore = 0;
$beforeScoreCount = 0;
$afterScoreCount = 0;
$improvedCount = 0;
$fullyEngagedCount = 0;
$stillDisengaged = [];

$latestClassBefore = null;
$latestClassAfter = null;

function estimate_time_from_score($score, $stage = 'before') {
    $score = floatval($score);
    if ($stage === 'before') {
        return max(8, min(24, round($score / 4)));
    }
    return max(16, min(41, round($score / 2.6)));
}

function badge_class($level) {
    if ($level === 'Highly Engaged') return 'badge-high';
    if ($level === 'Disengaged') return 'badge-low';
    return 'badge-mid';
}

if ($class_name !== '') {
    // Latest class-level uploads
    $stmtClassBefore = $conn->prepare("
        SELECT * FROM class_uploads
        WHERE class_name = ? AND stage = 'before'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $stmtClassBefore->bind_param("s", $class_name);
    $stmtClassBefore->execute();
    $latestClassBefore = $stmtClassBefore->get_result()->fetch_assoc();

    $stmtClassAfter = $conn->prepare("
        SELECT * FROM class_uploads
        WHERE class_name = ? AND stage = 'after'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $stmtClassAfter->bind_param("s", $class_name);
    $stmtClassAfter->execute();
    $latestClassAfter = $stmtClassAfter->get_result()->fetch_assoc();

    // Student-level records
    $stmt = $conn->prepare("
        SELECT * FROM students
        WHERE class_name = ?
        ORDER BY student_code ASC
    ");
    $stmt->bind_param("s", $class_name);
    $stmt->execute();
    $students = $stmt->get_result();

    while ($student = $students->fetch_assoc()) {
        $student_id = $student['id'];

        $stmtBefore = $conn->prepare("
            SELECT * FROM engagement_results
            WHERE student_id = ? AND stage = 'before'
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmtBefore->bind_param("i", $student_id);
        $stmtBefore->execute();
        $before = $stmtBefore->get_result()->fetch_assoc();

        $stmtAfter = $conn->prepare("
            SELECT * FROM engagement_results
            WHERE student_id = ? AND stage = 'after'
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmtAfter->bind_param("i", $student_id);
        $stmtAfter->execute();
        $after = $stmtAfter->get_result()->fetch_assoc();

        $beforeScore = $before ? floatval($before['engagement_score']) : null;
        $afterScore = $after ? floatval($after['engagement_score']) : null;

        $beforeTime = $before ? estimate_time_from_score($beforeScore, 'before') : null;
        $afterTime = $after ? estimate_time_from_score($afterScore, 'after') : null;

        $improvement = null;
        if ($before && $after) {
            $improvement = $afterScore - $beforeScore;
            if ($improvement > 0) {
                $improvedCount++;
            }
        }

        $fullyEngaged = false;
        if ($after && $afterScore >= 70 && $afterTime >= 25) {
            $fullyEngaged = true;
            $fullyEngagedCount++;
        }

        if ($before) {
            $beforeLevel = $before['engagement_level'];
            if (isset($beforeCounts[$beforeLevel])) {
                $beforeCounts[$beforeLevel]++;
            }
            $totalBeforeScore += $beforeScore;
            $beforeScoreCount++;
        }

        if ($after) {
            $afterLevel = $after['engagement_level'];
            if (isset($afterCounts[$afterLevel])) {
                $afterCounts[$afterLevel]++;
            }
            $totalAfterScore += $afterScore;
            $afterScoreCount++;

            if ($after['engagement_level'] === 'Disengaged') {
                $stillDisengaged[] = [
                    'student_code' => $student['student_code'],
                    'name' => $student['name'],
                    'method' => $student['preferred_method'],
                    'score' => $after['engagement_score']
                ];
            }
        }

        $method = $student['preferred_method'] ?: 'Unknown';
        if (!isset($learningPrefCounts[$method])) {
            $learningPrefCounts[$method] = 0;
        }
        $learningPrefCounts[$method]++;

        $studentsData[] = [
            'student' => $student,
            'before' => $before,
            'after' => $after,
            'before_time' => $beforeTime,
            'after_time' => $afterTime,
            'improvement' => $improvement,
            'fully_engaged' => $fullyEngaged
        ];
    }

    $totalStudents = count($studentsData);

    usort($studentsData, function($a, $b) {
        return ($b['improvement'] ?? -9999) <=> ($a['improvement'] ?? -9999);
    });
}

$filteredStudents = [];
foreach ($studentsData as $row) {
    $student = $row['student'];
    $after = $row['after'];

    if ($search !== '') {
        $hay = strtolower($student['student_code'] . ' ' . $student['name']);
        if (strpos($hay, strtolower($search)) === false) {
            continue;
        }
    }

    if ($method_filter !== '' && $student['preferred_method'] !== $method_filter) {
        continue;
    }

    if ($status_filter !== '') {
        $finalStatus = $after['engagement_level'] ?? '';
        if ($finalStatus !== $status_filter) {
            continue;
        }
    }

    if ($fully_filter !== '') {
        $wantYes = $fully_filter === 'Yes';
        if ($row['fully_engaged'] !== $wantYes) {
            continue;
        }
    }

    $filteredStudents[] = $row;
}

$topImproved = array_filter($studentsData, function($row) {
    return $row['improvement'] !== null;
});
$topImproved = array_slice($topImproved, 0, 10);

$avgBefore = $beforeScoreCount > 0 ? $totalBeforeScore / $beforeScoreCount : 0;
$avgAfter = $afterScoreCount > 0 ? $totalAfterScore / $afterScoreCount : 0;

$scoreLabels = array_map(function($r){ return $r['student']['student_code']; }, $studentsData);
$beforeScoreSeries = array_map(function($r){ return $r['before'] ? floatval($r['before']['engagement_score']) : null; }, $studentsData);
$afterScoreSeries = array_map(function($r){ return $r['after'] ? floatval($r['after']['engagement_score']) : null; }, $studentsData);
$beforeTimeSeries = array_map(function($r){ return $r['before_time']; }, $studentsData);
$afterTimeSeries = array_map(function($r){ return $r['after_time']; }, $studentsData);

$classUploadBeforeScore = $latestClassBefore ? floatval($latestClassBefore['engagement_score']) : null;
$classUploadAfterScore = $latestClassAfter ? floatval($latestClassAfter['engagement_score']) : null;
$classUploadImprovement = ($latestClassBefore && $latestClassAfter)
    ? ($classUploadAfterScore - $classUploadBeforeScore)
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Student Engagement Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f5f9;
            color: #1f2937;
        }
        .container {
            max-width: 1450px;
            margin: 18px auto 40px;
            padding: 0 14px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .topbar-title {
            font-size: 24px;
            font-weight: 700;
        }
        .topbar a {
            text-decoration: none;
            color: #2563eb;
            font-weight: 700;
        }
        .card {
            background: #fff;
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            margin-bottom: 16px;
        }
        .hero h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .hero p {
            margin: 0;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.7;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 16px;
        }
        .metric-title {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #374151;
        }
        .metric-number {
            font-size: 34px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 6px;
        }
        .metric-detail {
            font-size: 22px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 6px;
        }
        .metric-sub {
            color: #4b5563;
            font-size: 14px;
        }
        .blue { color: #2563eb; }
        .green { color: #15803d; font-weight: 700; }
        .orange { color: #b45309; font-weight: 700; }
        .red { color: #b91c1c; font-weight: 700; }
        .section-title {
            margin: 0 0 14px 0;
            font-size: 19px;
            font-weight: 700;
            color: #1f2937;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .chart-wrap {
            position: relative;
            width: 100%;
            height: 280px;
        }
        .chart-wrap.small {
            height: 260px;
        }
        .class-upload-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .upload-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .upload-info-item {
            background: #f8fafc;
            border-radius: 14px;
            padding: 14px;
        }
        .upload-info-label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .upload-info-value {
            font-size: 17px;
            font-weight: 700;
            color: #1f2937;
            word-break: break-word;
        }
        .result-preview img {
            width: 100%;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
        }
        .top-table, .main-table {
            width: 100%;
            border-collapse: collapse;
        }
        .top-table th, .top-table td,
        .main-table th, .main-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
        }
        .top-table th, .main-table th {
            color: #9ca3af;
            font-weight: 700;
            background: #fafafa;
            font-size: 13px;
        }
        .top-table td { font-size: 14px; }
        .main-table td { font-size: 13px; }
        .filter-row {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 10px;
        }
        input[type="text"], select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            font-size: 14px;
            background: white;
        }
        .filter-actions {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        button {
            padding: 11px 16px;
            border: none;
            border-radius: 12px;
            background: #2563eb;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }
        .button-secondary {
            background: #6b7280;
        }
        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-high {
            background: #dcfce7;
            color: #166534;
        }
        .badge-low {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-mid {
            background: #fef3c7;
            color: #92400e;
        }
        .link-view {
            color: #2563eb;
            font-weight: 700;
            text-decoration: none;
        }
        .interpretation p {
            margin: 8px 0;
            font-size: 15px;
            line-height: 1.7;
        }
        .table-scroll {
            overflow-x: auto;
        }
        .empty {
            color: #6b7280;
            font-style: italic;
        }
        @media (max-width: 1200px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .chart-grid,
            .class-upload-grid { grid-template-columns: 1fr; }
            .filter-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 700px) {
            .summary-grid,
            .filter-row,
            .upload-info-grid { grid-template-columns: 1fr; }
            .metric-number { font-size: 28px; }
            .metric-detail { font-size: 20px; }
            .hero h1 { font-size: 24px; }
            .chart-wrap, .chart-wrap.small { height: 240px; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="topbar">
        <div class="topbar-title">Faculty Student Engagement Dashboard</div>
        <a href="index.php">← Back to Home</a>
    </div>

    <div class="card">
        <form method="GET" action="dashboard_class.php">
            <label for="class_name"><strong>Select Class:</strong></label><br><br>
            <select name="class_name" id="class_name" required>
                <option value="">-- Select Class --</option>
                <?php while ($row = $classesQuery->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($row['class_name']); ?>" <?php echo ($class_name === $row['class_name']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($row['class_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <div class="filter-actions">
                <button type="submit">Load Dashboard</button>
            </div>
        </form>
    </div>

    <?php if ($class_name !== ''): ?>
        <div class="card hero">
            <h1>Faculty Student Engagement Analytics Dashboard</h1>
            <p>
                This system shows student-wise engagement analytics for <?php echo $totalStudents; ?> students.
                It also shows the latest class-level upload results for before and after learning aid, together with student-level comparison analytics.
            </p>
        </div>

        <div class="summary-grid">
            <div class="card">
                <div class="metric-title">Total Students</div>
                <div class="metric-number"><?php echo $totalStudents; ?></div>
                <div class="metric-sub">Students enrolled in the course</div>
            </div>

            <div class="card">
                <div class="metric-title">Before Verbal Lecture</div>
                <div class="metric-detail">
                    <?php echo $beforeCounts['Disengaged']; ?> / <?php echo $beforeCounts['Moderate']; ?> / <?php echo $beforeCounts['Highly Engaged']; ?>
                </div>
                <div class="metric-sub">Disengaged / Moderate / High</div>
            </div>

            <div class="card">
                <div class="metric-title">After Preferred Learning Aid</div>
                <div class="metric-detail">
                    <?php echo $afterCounts['Disengaged']; ?> / <?php echo $afterCounts['Moderate']; ?> / <?php echo $afterCounts['Highly Engaged']; ?>
                </div>
                <div class="metric-sub">Disengaged / Moderate / High</div>
            </div>

            <div class="card">
                <div class="metric-title">Fully Engaged Students</div>
                <div class="metric-number blue"><?php echo $fullyEngagedCount; ?></div>
                <div class="metric-sub">Score ≥ 70 and Time ≥ 25 min</div>
            </div>
        </div>

        <div class="class-upload-grid">
            <div class="card">
                <div class="section-title">Latest Class Upload Before</div>
                <?php if ($latestClassBefore): ?>
                    <div class="upload-info-grid">
                        <div class="upload-info-item">
                            <div class="upload-info-label">Stage</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars(ucfirst($latestClassBefore['stage'])); ?></div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Media Type</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars($latestClassBefore['media_type']); ?></div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Engagement Score</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars($latestClassBefore['engagement_score']); ?></div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Engagement Level</div>
                            <div class="upload-info-value">
                                <span class="badge <?php echo badge_class($latestClassBefore['engagement_level']); ?>">
                                    <?php echo htmlspecialchars($latestClassBefore['engagement_level']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Recommended Aid</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars($latestClassBefore['recommended_aid']); ?></div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Uploaded At</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars($latestClassBefore['created_at']); ?></div>
                        </div>
                    </div>
                    <?php if (!empty($latestClassBefore['result_image_path'])): ?>
                        <div class="result-preview" style="margin-top:14px;">
                            <img src="<?php echo htmlspecialchars($latestClassBefore['result_image_path']); ?>" alt="Before Class Result">
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty">No class-level before upload found.</div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="section-title">Latest Class Upload After</div>
                <?php if ($latestClassAfter): ?>
                    <div class="upload-info-grid">
                        <div class="upload-info-item">
                            <div class="upload-info-label">Stage</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars(ucfirst($latestClassAfter['stage'])); ?></div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Media Type</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars($latestClassAfter['media_type']); ?></div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Engagement Score</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars($latestClassAfter['engagement_score']); ?></div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Engagement Level</div>
                            <div class="upload-info-value">
                                <span class="badge <?php echo badge_class($latestClassAfter['engagement_level']); ?>">
                                    <?php echo htmlspecialchars($latestClassAfter['engagement_level']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Recommended Aid</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars($latestClassAfter['recommended_aid']); ?></div>
                        </div>
                        <div class="upload-info-item">
                            <div class="upload-info-label">Uploaded At</div>
                            <div class="upload-info-value"><?php echo htmlspecialchars($latestClassAfter['created_at']); ?></div>
                        </div>
                    </div>
                    <?php if (!empty($latestClassAfter['result_image_path'])): ?>
                        <div class="result-preview" style="margin-top:14px;">
                            <img src="<?php echo htmlspecialchars($latestClassAfter['result_image_path']); ?>" alt="After Class Result">
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty">No class-level after upload found.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="section-title">Latest Class Upload Comparison</div>
            <div class="summary-grid" style="margin-bottom:0;">
                <div class="card" style="margin-bottom:0;">
                    <div class="metric-title">Before Upload Score</div>
                    <div class="metric-number"><?php echo $latestClassBefore ? htmlspecialchars($latestClassBefore['engagement_score']) : '--'; ?></div>
                    <div class="metric-sub">
                        <?php echo $latestClassBefore ? htmlspecialchars($latestClassBefore['engagement_level']) : 'No before class upload'; ?>
                    </div>
                </div>
                <div class="card" style="margin-bottom:0;">
                    <div class="metric-title">After Upload Score</div>
                    <div class="metric-number"><?php echo $latestClassAfter ? htmlspecialchars($latestClassAfter['engagement_score']) : '--'; ?></div>
                    <div class="metric-sub">
                        <?php echo $latestClassAfter ? htmlspecialchars($latestClassAfter['engagement_level']) : 'No after class upload'; ?>
                    </div>
                </div>
                <div class="card" style="margin-bottom:0;">
                    <div class="metric-title">Improvement</div>
                    <div class="metric-number <?php echo ($classUploadImprovement !== null && $classUploadImprovement > 0) ? 'green' : ''; ?>">
                        <?php
                        if ($classUploadImprovement !== null) {
                            echo ($classUploadImprovement >= 0 ? '+' : '') . number_format($classUploadImprovement, 2);
                        } else {
                            echo '--';
                        }
                        ?>
                    </div>
                    <div class="metric-sub">Class-level upload comparison</div>
                </div>
                <div class="card" style="margin-bottom:0;">
                    <div class="metric-title">Recommended Aid</div>
                    <div class="metric-detail" style="font-size:18px;">
                        <?php
                        if ($latestClassAfter) {
                            echo htmlspecialchars($latestClassAfter['recommended_aid']);
                        } elseif ($latestClassBefore) {
                            echo htmlspecialchars($latestClassBefore['recommended_aid']);
                        } else {
                            echo '--';
                        }
                        ?>
                    </div>
                    <div class="metric-sub">Latest class recommendation</div>
                </div>
            </div>
        </div>

        <div class="chart-grid">
            <div class="card">
                <div class="section-title">Overall Engagement Comparison</div>
                <div class="chart-wrap">
                    <canvas id="overallChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="section-title">Learning Preference Distribution</div>
                <div class="chart-wrap small">
                    <canvas id="preferenceChart"></canvas>
                </div>
            </div>
        </div>

        <div class="chart-grid">
            <div class="card">
                <div class="section-title">Student-wise Score Comparison</div>
                <div class="chart-wrap">
                    <canvas id="scoreChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="section-title">Student-wise Time Comparison</div>
                <div class="chart-wrap">
                    <canvas id="timeChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-title">Top 10 Improved Students</div>
            <table class="top-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Improve</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($topImproved)): ?>
                        <?php foreach ($topImproved as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['student']['student_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['student']['name']); ?></td>
                                <td class="green">
                                    <?php echo ($row['improvement'] >= 0 ? '+' : '') . number_format($row['improvement'], 0); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="empty">No improvement data available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="section-title">Search and Filter Students</div>
            <form method="GET" action="dashboard_class.php">
                <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($class_name); ?>">
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Search by name or student code" value="<?php echo htmlspecialchars($search); ?>">

                    <select name="method">
                        <option value="">All Preferred Methods</option>
                        <?php
                        $methodsQuery2 = $conn->query("
                            SELECT DISTINCT preferred_method
                            FROM students
                            WHERE preferred_method IS NOT NULL AND preferred_method <> ''
                            ORDER BY preferred_method ASC
                        ");
                        while ($m = $methodsQuery2->fetch_assoc()):
                        ?>
                            <option value="<?php echo htmlspecialchars($m['preferred_method']); ?>" <?php echo ($method_filter === $m['preferred_method']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['preferred_method']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <select name="status">
                        <option value="">All Final Status</option>
                        <option value="Disengaged" <?php echo ($status_filter === 'Disengaged') ? 'selected' : ''; ?>>Disengaged</option>
                        <option value="Moderate" <?php echo ($status_filter === 'Moderate') ? 'selected' : ''; ?>>Moderate</option>
                        <option value="Highly Engaged" <?php echo ($status_filter === 'Highly Engaged') ? 'selected' : ''; ?>>Highly Engaged</option>
                    </select>

                    <select name="fully">
                        <option value="">All Fully Engaged</option>
                        <option value="Yes" <?php echo ($fully_filter === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                        <option value="No" <?php echo ($fully_filter === 'No') ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit">Apply Filters</button>
                    <a href="dashboard_class.php?class_name=<?php echo urlencode($class_name); ?>">
                        <button type="button" class="button-secondary">Reset</button>
                    </a>
                </div>
            </form>
        </div>

        <div class="card table-scroll">
            <table class="main-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student Code</th>
                        <th>Name</th>
                        <th>Preferred Method</th>
                        <th>Verbal Score</th>
                        <th>Verbal Time</th>
                        <th>Verbal Status</th>
                        <th>Provided Aid</th>
                        <th>After Aid Score</th>
                        <th>After Aid Time</th>
                        <th>After Aid Status</th>
                        <th>Improvement</th>
                        <th>Fully Engaged</th>
                        <th>Profile</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($filteredStudents)): ?>
                        <?php foreach ($filteredStudents as $index => $row): ?>
                            <?php
                            $student = $row['student'];
                            $before = $row['before'];
                            $after = $row['after'];
                            $beforeTime = $row['before_time'];
                            $afterTime = $row['after_time'];
                            $improvement = $row['improvement'];
                            $fully = $row['fully_engaged'];
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($student['student_code']); ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['preferred_method']); ?></td>
                                <td><?php echo $before ? htmlspecialchars($before['engagement_score']) : '--'; ?></td>
                                <td><?php echo $beforeTime !== null ? htmlspecialchars($beforeTime) . ' min' : '--'; ?></td>
                                <td>
                                    <?php if ($before): ?>
                                        <span class="badge <?php echo badge_class($before['engagement_level']); ?>">
                                            <?php echo htmlspecialchars($before['engagement_level']); ?>
                                        </span>
                                    <?php else: ?>--<?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($after) {
                                        echo htmlspecialchars($after['recommended_aid']);
                                    } elseif ($before) {
                                        echo htmlspecialchars($before['recommended_aid']);
                                    } else {
                                        echo '--';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $after ? htmlspecialchars($after['engagement_score']) : '--'; ?></td>
                                <td><?php echo $afterTime !== null ? htmlspecialchars($afterTime) . ' min' : '--'; ?></td>
                                <td>
                                    <?php if ($after): ?>
                                        <span class="badge <?php echo badge_class($after['engagement_level']); ?>">
                                            <?php echo htmlspecialchars($after['engagement_level']); ?>
                                        </span>
                                    <?php else: ?>--<?php endif; ?>
                                </td>
                                <td class="<?php echo ($improvement !== null && $improvement > 0) ? 'green' : ''; ?>">
                                    <?php
                                    if ($improvement !== null) {
                                        echo ($improvement >= 0 ? '+' : '') . number_format($improvement, 0);
                                    } else {
                                        echo '--';
                                    }
                                    ?>
                                </td>
                                <td class="<?php echo $fully ? 'blue' : 'orange'; ?>">
                                    <?php echo $fully ? 'Yes' : 'No'; ?>
                                </td>
                                <td>
                                    <a class="link-view" href="dashboard_student.php?student_id=<?php echo $student['id']; ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="14" class="empty">No matching students found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="section-title">Students Still Disengaged After Support</div>
            <table class="top-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Method</th>
                        <th>After Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($stillDisengaged)): ?>
                        <?php foreach ($stillDisengaged as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['student_code']); ?></td>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['method']); ?></td>
                                <td class="red"><?php echo htmlspecialchars($s['score']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="empty">No students remain disengaged after support.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card interpretation">
            <div class="section-title">Interpretation of Analytics</div>
            <p><strong>Before verbal teaching:</strong> <?php echo $beforeCounts['Disengaged']; ?> students were disengaged, <?php echo $beforeCounts['Moderate']; ?> were moderately engaged, and <?php echo $beforeCounts['Highly Engaged']; ?> were highly engaged.</p>
            <p><strong>After preferred learning aid:</strong> <?php echo $afterCounts['Disengaged']; ?> students remained disengaged, <?php echo $afterCounts['Moderate']; ?> became moderately engaged, and <?php echo $afterCounts['Highly Engaged']; ?> became highly engaged.</p>
            <p><strong>Fully engaged students:</strong> <?php echo $fullyEngagedCount; ?> students achieved both strong score and strong engagement duration.</p>
            <?php if ($latestClassBefore || $latestClassAfter): ?>
                <p><strong>Latest class uploads:</strong>
                    Before score = <?php echo $latestClassBefore ? htmlspecialchars($latestClassBefore['engagement_score']) : '--'; ?>,
                    After score = <?php echo $latestClassAfter ? htmlspecialchars($latestClassAfter['engagement_score']) : '--'; ?>.
                </p>
            <?php endif; ?>
            <p>Final engagement model: Before verbal class 0–39 = Disengaged, 40–69 = Moderately Engaged, 70–100 = Highly Engaged. After learning aid same 3 levels again. Fully engaged score ≥ 70 and time ≥ 25 min.</p>
        </div>

    <?php endif; ?>
</div>

<?php if ($class_name !== ''): ?>
<script>
new Chart(document.getElementById('overallChart'), {
    type: 'bar',
    data: {
        labels: [
            'Before Disengaged',
            'Before Moderate',
            'Before High',
            'After Disengaged',
            'After Moderate',
            'After High',
            'Fully Engaged'
        ],
        datasets: [{
            data: [
                <?php echo $beforeCounts['Disengaged']; ?>,
                <?php echo $beforeCounts['Moderate']; ?>,
                <?php echo $beforeCounts['Highly Engaged']; ?>,
                <?php echo $afterCounts['Disengaged']; ?>,
                <?php echo $afterCounts['Moderate']; ?>,
                <?php echo $afterCounts['Highly Engaged']; ?>,
                <?php echo $fullyEngagedCount; ?>
            ],
            backgroundColor: ['#ef4444','#f59e0b','#22c55e','#ef4444','#f59e0b','#3b82f6','#8b5cf6'],
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

new Chart(document.getElementById('preferenceChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_keys($learningPrefCounts)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($learningPrefCounts)); ?>,
            backgroundColor: ['#3b82f6','#ef4444','#22c55e','#f59e0b','#8b5cf6','#14b8a6','#ec4899','#84cc16']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

new Chart(document.getElementById('scoreChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($scoreLabels); ?>,
        datasets: [
            {
                label: 'Verbal Score',
                data: <?php echo json_encode($beforeScoreSeries); ?>,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239,68,68,0.12)',
                tension: 0.35
            },
            {
                label: 'After Aid Score',
                data: <?php echo json_encode($afterScoreSeries); ?>,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34,197,94,0.12)',
                tension: 0.35
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

new Chart(document.getElementById('timeChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($scoreLabels); ?>,
        datasets: [
            {
                label: 'Verbal Time (min)',
                data: <?php echo json_encode($beforeTimeSeries); ?>,
                backgroundColor: '#fb923c'
            },
            {
                label: 'After Aid Time (min)',
                data: <?php echo json_encode($afterTimeSeries); ?>,
                backgroundColor: '#60a5fa'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>
<?php endif; ?>
</body>
</html>