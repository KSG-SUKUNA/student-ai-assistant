<?php
include 'db.php';

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

$student = null;
$before = null;
$after = null;
$history = null;

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

if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if ($student) {
        $stmt = $conn->prepare("
            SELECT * FROM engagement_results
            WHERE student_id = ? AND stage = 'before'
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $before = $stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("
            SELECT * FROM engagement_results
            WHERE student_id = ? AND stage = 'after'
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $after = $stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("
            SELECT * FROM engagement_results
            WHERE student_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $history = $stmt->get_result();
    }
}

$beforeScore = $before ? floatval($before['engagement_score']) : null;
$afterScore = $after ? floatval($after['engagement_score']) : null;

$beforeTime = $before ? estimate_time_from_score($beforeScore, 'before') : null;
$afterTime = $after ? estimate_time_from_score($afterScore, 'after') : null;

$improvement = ($before && $after) ? ($afterScore - $beforeScore) : null;
$verbalStatus = $before['engagement_level'] ?? null;
$finalStatus = $after['engagement_level'] ?? null;

$recommendedAid = null;
if ($after && !empty($after['recommended_aid'])) {
    $recommendedAid = $after['recommended_aid'];
} elseif ($before && !empty($before['recommended_aid'])) {
    $recommendedAid = $before['recommended_aid'];
}

$fullyEngaged = false;
if ($after && $afterScore >= 70 && $afterTime >= 25) {
    $fullyEngaged = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Engagement Dashboard</title>
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
        .filter-card form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        select, button {
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            font-size: 14px;
        }
        button {
            border: none;
            background: #2563eb;
            color: white;
            font-weight: 700;
            cursor: pointer;
        }
        .layout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .section-title {
            margin: 0 0 14px 0;
            font-size: 19px;
            font-weight: 700;
            color: #1f2937;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .profile-item {
            background: #f8fafc;
            border-radius: 14px;
            padding: 14px;
        }
        .profile-label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .profile-value {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }
        .blue { color: #2563eb; }
        .green { color: #15803d; font-weight: 700; }
        .orange { color: #b45309; font-weight: 700; }
        .red { color: #b91c1c; font-weight: 700; }
        .metric-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }
        .metric-card {
            background: #f8fafc;
            border-radius: 14px;
            padding: 14px;
        }
        .metric-title {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .metric-value {
            font-size: 26px;
            font-weight: 700;
            color: #1f2937;
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
        .chart-wrap {
            position: relative;
            width: 100%;
            height: 320px;
        }
        .compare-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .image-box img {
            width: 100%;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
        }
        .image-box .empty {
            padding: 30px;
            text-align: center;
            color: #6b7280;
            background: #f8fafc;
            border-radius: 14px;
        }
        .table-scroll {
            overflow-x: auto;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th, .history-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 13px;
        }
        .history-table th {
            background: #fafafa;
            color: #9ca3af;
            font-weight: 700;
        }
        .link-view {
            color: #2563eb;
            font-weight: 700;
            text-decoration: none;
        }
        .empty {
            color: #6b7280;
            font-style: italic;
        }
        @media (max-width: 1200px) {
            .layout-grid,
            .compare-grid,
            .metric-row,
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="topbar">
        <div class="topbar-title">Student Engagement Dashboard</div>
        <a href="index.php">← Back to Home</a>
    </div>

    <div class="card filter-card">
        <form method="GET" action="dashboard_student.php">
            <label for="student_id"><strong>Select Student:</strong></label>
            <select name="student_id" id="student_id" required>
                <option value="">-- Select Student --</option>
                <?php
                $studentsList = $conn->query("SELECT * FROM students ORDER BY student_code ASC");
                while ($s = $studentsList->fetch_assoc()):
                ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo ($student_id == $s['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['student_code'] . ' - ' . $s['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit">View Dashboard</button>
        </form>
    </div>

    <?php if ($student): ?>
        <div class="layout-grid">
            <div class="card">
                <div class="section-title">Selected Student Profile</div>
                <div class="profile-grid">
                    <div class="profile-item">
                        <div class="profile-label">Student Code</div>
                        <div class="profile-value"><?php echo htmlspecialchars($student['student_code']); ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Name</div>
                        <div class="profile-value"><?php echo htmlspecialchars($student['name']); ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Preferred Method</div>
                        <div class="profile-value"><?php echo htmlspecialchars($student['preferred_method']); ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Faculty Provided Aid</div>
                        <div class="profile-value"><?php echo htmlspecialchars($recommendedAid ?: '--'); ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Verbal Score</div>
                        <div class="profile-value"><?php echo $before ? htmlspecialchars($before['engagement_score']) . '/100' : '--'; ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">After Aid Score</div>
                        <div class="profile-value"><?php echo $after ? htmlspecialchars($after['engagement_score']) . '/100' : '--'; ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Verbal Time</div>
                        <div class="profile-value"><?php echo $beforeTime !== null ? $beforeTime . ' minutes' : '--'; ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">After Aid Time</div>
                        <div class="profile-value"><?php echo $afterTime !== null ? $afterTime . ' minutes' : '--'; ?></div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Improvement</div>
                        <div class="profile-value <?php echo ($improvement !== null && $improvement > 0) ? 'green' : ''; ?>">
                            <?php
                            if ($improvement !== null) {
                                echo ($improvement >= 0 ? '+' : '') . number_format($improvement, 0);
                            } else {
                                echo '--';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Verbal Status</div>
                        <div class="profile-value">
                            <?php if ($verbalStatus): ?>
                                <span class="badge <?php echo badge_class($verbalStatus); ?>">
                                    <?php echo htmlspecialchars($verbalStatus); ?>
                                </span>
                            <?php else: ?>--<?php endif; ?>
                        </div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Final Engagement</div>
                        <div class="profile-value">
                            <?php if ($finalStatus): ?>
                                <span class="badge <?php echo badge_class($finalStatus); ?>">
                                    <?php echo htmlspecialchars($finalStatus); ?>
                                </span>
                            <?php else: ?>--<?php endif; ?>
                        </div>
                    </div>
                    <div class="profile-item">
                        <div class="profile-label">Fully Engaged</div>
                        <div class="profile-value <?php echo $fullyEngaged ? 'blue' : 'orange'; ?>">
                            <?php echo $fullyEngaged ? 'Yes' : 'No'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="section-title">Selected Student Before vs After</div>
                <div class="chart-wrap">
                    <canvas id="studentCompareChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-title">Score and Time Summary</div>
            <div class="metric-row">
                <div class="metric-card">
                    <div class="metric-title">Before Score</div>
                    <div class="metric-value"><?php echo $before ? htmlspecialchars($before['engagement_score']) : '--'; ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">After Score</div>
                    <div class="metric-value"><?php echo $after ? htmlspecialchars($after['engagement_score']) : '--'; ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">Before Time</div>
                    <div class="metric-value"><?php echo $beforeTime !== null ? $beforeTime : '--'; ?></div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">After Time</div>
                    <div class="metric-value"><?php echo $afterTime !== null ? $afterTime : '--'; ?></div>
                </div>
            </div>
        </div>

        <div class="compare-grid">
            <div class="card image-box">
                <div class="section-title">Before Result Image</div>
                <?php if ($before && !empty($before['result_image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($before['result_image_path']); ?>" alt="Before Result">
                <?php else: ?>
                    <div class="empty">No before analyzed image available.</div>
                <?php endif; ?>
            </div>

            <div class="card image-box">
                <div class="section-title">After Result Image</div>
                <?php if ($after && !empty($after['result_image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($after['result_image_path']); ?>" alt="After Result">
                <?php else: ?>
                    <div class="empty">No after analyzed image available.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card table-scroll">
            <div class="section-title">Student Result History</div>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Media Type</th>
                        <th>Score</th>
                        <th>Level</th>
                        <th>Recommended Aid</th>
                        <th>Uploaded</th>
                        <th>Media</th>
                        <th>Analyzed Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history && $history->num_rows > 0): ?>
                        <?php while ($row = $history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(ucfirst($row['stage'])); ?></td>
                                <td><?php echo htmlspecialchars($row['media_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['engagement_score']); ?></td>
                                <td>
                                    <span class="badge <?php echo badge_class($row['engagement_level']); ?>">
                                        <?php echo htmlspecialchars($row['engagement_level']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['recommended_aid']); ?></td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td>
                                    <a class="link-view" href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank">Open</a>
                                </td>
                                <td>
                                    <?php if (!empty($row['result_image_path'])): ?>
                                        <a class="link-view" href="<?php echo htmlspecialchars($row['result_image_path']); ?>" target="_blank">View Result</a>
                                    <?php else: ?>
                                        <span class="empty">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty">No result history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($student_id > 0): ?>
        <div class="card">
            <div class="empty">Student not found.</div>
        </div>
    <?php endif; ?>
</div>

<?php if ($student): ?>
<script>
new Chart(document.getElementById('studentCompareChart'), {
    type: 'bar',
    data: {
        labels: ['Verbal Score', 'After Aid Score', 'Verbal Time', 'After Aid Time'],
        datasets: [{
            data: [
                <?php echo $beforeScore !== null ? $beforeScore : 'null'; ?>,
                <?php echo $afterScore !== null ? $afterScore : 'null'; ?>,
                <?php echo $beforeTime !== null ? $beforeTime : 'null'; ?>,
                <?php echo $afterTime !== null ? $afterTime : 'null'; ?>
            ],
            backgroundColor: ['#ef4444', '#22c55e', '#fb923c', '#60a5fa'],
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
</script>
<?php endif; ?>
</body>
</html>