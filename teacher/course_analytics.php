<?php
require_once '../includes/header.php';
requireRole('teacher');
$course_id = (int)($_GET['id'] ?? 0);
if (!$course_id) {
    echo '<div class="alert alert-danger">Invalid course ID.</div>';
    include '../includes/footer.php';
    exit;
}
// Verify teacher owns this course
$stmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();
if (!$course) {
    echo '<div class="alert alert-danger">Course not found or access denied.</div>';
    include '../includes/footer.php';
    exit;
}
$page_title = 'Course Analytics';

// 1. Get all sections assigned to this course
$section_stmt = $db->prepare("SELECT s.id, s.section_name as name, s.year_level as year FROM course_sections cs INNER JOIN sections s ON cs.section_id = s.id WHERE cs.course_id = ?");
$section_stmt->execute([$course_id]);
$sections = $section_stmt->fetchAll();
$section_ids = array_column($sections, 'id');

// 2. Get all students in these sections
$students = [];
if ($section_ids) {
    $in = str_repeat('?,', count($section_ids) - 1) . '?';
    $stu_stmt = $db->prepare("SELECT DISTINCT u.id, u.first_name, u.last_name, u.username, u.email, u.is_irregular FROM sections s JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL WHERE s.id IN ($in)");
    $stu_stmt->execute($section_ids);
    $students = $stu_stmt->fetchAll();
}
$student_ids = array_column($students, 'id');

// 3. Get all modules for this course
$mod_stmt = $db->prepare("SELECT id, module_title FROM course_modules WHERE course_id = ? ORDER BY module_order");
$mod_stmt->execute([$course_id]);
$modules = $mod_stmt->fetchAll();
$module_ids = array_column($modules, 'id');

// 4. Get all assessments for this course
$assess_stmt = $db->prepare("SELECT a.id, a.assessment_title, a.module_id, a.passing_rate FROM assessments a WHERE a.module_id IN (" . (count($module_ids) ? implode(',', $module_ids) : '0') . ")");
$assess_stmt->execute();
$assessments = $assess_stmt->fetchAll();
$assessment_ids = array_column($assessments, 'id');

// 5. Get all videos for this course
$video_stmt = $db->prepare("SELECT v.id, v.video_title, v.module_id FROM course_videos v WHERE v.module_id IN (" . (count($module_ids) ? implode(',', $module_ids) : '0') . ")");
$video_stmt->execute();
$videos = $video_stmt->fetchAll();
$video_ids = array_column($videos, 'id');

// Summary Statistics
$total_students = count($students);
$total_modules = count($modules);
$total_assessments = count($assessments);
$total_videos = count($videos);

// Calculate overall course statistics
$overall_stats = [];
if ($total_students > 0) {
    // Overall progress
    $stmt = $db->prepare("SELECT AVG(is_completed) * 100 as avg_progress FROM module_progress WHERE module_id IN (" . (count($module_ids) ? implode(',', $module_ids) : '0') . ")");
    $stmt->execute();
    $overall_stats['progress'] = round($stmt->fetchColumn() ?? 0, 1);
    
    // Overall assessment scores
    $stmt = $db->prepare("SELECT AVG(score) as avg_score FROM assessment_attempts WHERE assessment_id IN (" . (count($assessment_ids) ? implode(',', $assessment_ids) : '0') . ")");
    $stmt->execute();
    $overall_stats['avg_score'] = round($stmt->fetchColumn() ?? 0, 1);
    
    // Pass rate
    $stmt = $db->prepare("SELECT COUNT(*) as passed FROM assessment_attempts aa JOIN assessments a ON aa.assessment_id = a.id WHERE aa.assessment_id IN (" . (count($assessment_ids) ? implode(',', $assessment_ids) : '0') . ") AND aa.score >= a.passing_rate");
    $stmt->execute();
    $passed = $stmt->fetchColumn() ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM assessment_attempts WHERE assessment_id IN (" . (count($assessment_ids) ? implode(',', $assessment_ids) : '0') . ")");
    $stmt->execute();
    $total_attempts = $stmt->fetchColumn() ?? 0;
    
    $overall_stats['pass_rate'] = $total_attempts > 0 ? round(($passed / $total_attempts) * 100, 1) : 0;
    
    // Total video views
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total_views FROM video_stats WHERE video_id IN (" . (count($video_ids) ? implode(',', $video_ids) : '0') . ")");
        $stmt->execute();
        $overall_stats['total_views'] = $stmt->fetchColumn() ?? 0;
    } catch (Exception $e) {
        $overall_stats['total_views'] = 0;
    }
}

// 1. Student Progress Overview (avg. completion per module)
$progress_data = [];
foreach ($modules as $mod) {
    $stmt = $db->prepare("SELECT AVG(is_completed) * 100 as avg_progress FROM module_progress WHERE module_id = ?");
    $stmt->execute([$mod['id']]);
    $row = $stmt->fetch();
    $progress_data[] = [
        'label' => $mod['module_title'],
        'value' => round($row['avg_progress'] ?? 0, 1)
    ];
}

// 2. Assessment Analytics (avg. score per assessment)
$assessment_data = [];
$assessment_details = [];
foreach ($assessments as $assess) {
    $stmt = $db->prepare("SELECT AVG(score) as avg_score, COUNT(*) as attempts, MIN(score) as min_score, MAX(score) as max_score FROM assessment_attempts WHERE assessment_id = ?");
    $stmt->execute([$assess['id']]);
    $row = $stmt->fetch();
    $avg_score = round($row['avg_score'] ?? 0, 1);
    $assessment_data[] = [
        'label' => $assess['assessment_title'],
        'value' => $avg_score
    ];
    $assessment_details[] = [
        'title' => $assess['assessment_title'],
        'avg_score' => $avg_score,
        'attempts' => $row['attempts'] ?? 0,
        'min_score' => $row['min_score'] ?? 0,
        'max_score' => $row['max_score'] ?? 0,
        'passing_rate' => $assess['passing_rate'] ?? 70
    ];
}

// 3. Engagement Analytics (views per video)
$engagement_data = [];
foreach ($videos as $vid) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as views FROM video_stats WHERE video_id = ?");
        $stmt->execute([$vid['id']]);
        $row = $stmt->fetch();
        $engagement_data[] = [
            'label' => $vid['video_title'],
            'value' => (int)($row['views'] ?? 0)
        ];
    } catch (Exception $e) {
        $engagement_data[] = [
            'label' => $vid['video_title'],
            'value' => 0
        ];
    }
}

// 4. Section Comparison (avg. assessment score per section)
$section_data = [];
foreach ($sections as $sec) {
    $stmt = $db->prepare("SELECT AVG(aa.score) as avg_score FROM sections s JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL JOIN assessment_attempts aa ON u.id = aa.student_id WHERE s.id = ?");
    $stmt->execute([$sec['id']]);
    $row = $stmt->fetch();
    $section_data[] = [
        'label' => 'BSIT-' . $sec['year'] . $sec['name'],
        'value' => round($row['avg_score'] ?? 0, 1)
    ];
}

// 5. Student Leaderboard (top by avg. assessment score)
$leaderboard = [];
foreach ($students as $stu) {
    $stmt = $db->prepare("SELECT AVG(score) as avg_score, COUNT(*) as attempts FROM assessment_attempts WHERE student_id = ?");
    $stmt->execute([$stu['id']]);
    $row = $stmt->fetch();
    $avg_score = round($row['avg_score'] ?? 0, 1);
    if ($row['attempts'] > 0) {
    $leaderboard[] = [
        'name' => $stu['last_name'] . ', ' . $stu['first_name'],
            'score' => $avg_score,
            'attempts' => $row['attempts'],
            'student_id' => $stu['id']
    ];
    }
}
usort($leaderboard, function($a, $b) { return $b['score'] <=> $a['score']; });
$leaderboard = array_slice($leaderboard, 0, 10);

// 6. Performance Distribution
$performance_distribution = [
    'Excellent (90-100)' => 0,
    'Good (80-89)' => 0,
    'Satisfactory (70-79)' => 0,
    'Needs Improvement (60-69)' => 0,
    'Failing (0-59)' => 0
];

if ($assessment_ids) {
    $stmt = $db->prepare("SELECT score FROM assessment_attempts WHERE assessment_id IN (" . implode(',', $assessment_ids) . ")");
    $stmt->execute();
    $scores = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($scores as $score) {
        if ($score >= 90) $performance_distribution['Excellent (90-100)']++;
        elseif ($score >= 80) $performance_distribution['Good (80-89)']++;
        elseif ($score >= 70) $performance_distribution['Satisfactory (70-79)']++;
        elseif ($score >= 60) $performance_distribution['Needs Improvement (60-69)']++;
        else $performance_distribution['Failing (0-59)']++;
    }
}

// Section selection logic
$selected_section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : ($sections[0]['id'] ?? 0);
$selected_section = null;
foreach ($sections as $sec) {
    if ($sec['id'] == $selected_section_id) {
        $selected_section = $sec;
        break;
    }
}

// Fetch students and their scores for the selected section
$section_students = [];
$section_scores = [];
$assessment_titles = [];
if ($selected_section_id && $assessment_ids) {
    // Get students in section
    $stu_stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name 
                          FROM sections s 
                          JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
                          WHERE s.id = ?");
    $stu_stmt->execute([$selected_section_id]);
    $section_students = $stu_stmt->fetchAll();
    // Get all assessments for this course
    $assessment_titles = array_column($assessments, 'assessment_title', 'id');
    // Get scores for each student and assessment
    foreach ($section_students as $stu) {
        $scores = [];
        foreach ($assessments as $assess) {
            $stmt = $db->prepare("SELECT MAX(score) as score FROM assessment_attempts WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$stu['id'], $assess['id']]);
            $row = $stmt->fetch();
            $scores[$assess['id']] = is_null($row['score']) ? null : round($row['score'], 1);
        }
        $section_scores[$stu['id']] = $scores;
    }
}
?>

<div class="analytics-container">
    <!-- Hierarchical Header -->
    <div class="analytics-header">
        <div class="header-content">
            <div class="header-left">
                <a href="course.php?id=<?= $course_id ?>" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div class="course-info">
                    <h1><?= htmlspecialchars($course['course_name']) ?></h1>
                    <p><?= htmlspecialchars($course['course_code']) ?> • Analytics Dashboard</p>
                </div>
            </div>
            <div class="header-actions">
                <div class="date-filter">
                    <i class="bi bi-calendar3"></i>
                    <select class="form-select-sm">
                        <option>Last 30 days</option>
                        <option>Last 7 days</option>
                        <option>Last 90 days</option>
                    </select>
                </div>
                <button class="action-btn share-btn">
                    <i class="bi bi-share"></i>
                </button>
                <button class="action-btn export-btn" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel"></i>
                    Export
                </button>
            </div>
        </div>
    </div>

    <!-- Hierarchical Summary Grid -->
    <div class="summary-grid">
        <div class="summary-card primary-card">
            <div class="card-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="card-content">
                <div class="card-value"><?= $total_students ?></div>
                <div class="card-label">Total Students</div>
            </div>
        </div>
        
        <div class="summary-card success-card">
            <div class="card-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="card-content">
                <div class="card-value"><?= $overall_stats['progress'] ?? 0 ?>%</div>
                <div class="card-label">Avg. Progress</div>
            </div>
        </div>
        
        <div class="summary-card info-card">
            <div class="card-icon">
                <i class="bi bi-clipboard-data"></i>
            </div>
            <div class="card-content">
                <div class="card-value"><?= $overall_stats['avg_score'] ?? 0 ?>%</div>
                <div class="card-label">Avg. Score</div>
            </div>
        </div>
        
        <div class="summary-card warning-card">
            <div class="card-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="card-content">
                <div class="card-value"><?= $overall_stats['pass_rate'] ?? 0 ?>%</div>
                <div class="card-label">Pass Rate</div>
            </div>
        </div>
    </div>

    <!-- Modern Section Selector -->
    <div class="mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <form method="get" class="d-flex align-items-center gap-3">
            <input type="hidden" name="id" value="<?= $course_id ?>">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>
                        <label for="section_id" class="me-2 fw-semibold text-dark">Section:</label>
                    </div>
                    <select name="section_id" id="section_id" class="form-select form-select-sm w-auto border-0 bg-light" onchange="this.form.submit()">
                <?php foreach ($sections as $sec): ?>
                    <option value="<?= $sec['id'] ?>" <?= $selected_section_id == $sec['id'] ? 'selected' : '' ?>>
                        <?= 'BSIT-' . $sec['year'] . $sec['name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
        </div>
    </div>

    <?php if ($selected_section_id && $section_students): ?>
    <div class="row mb-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                <i class="bi bi-people text-primary"></i>
                            </div>
                            <h6 class="mb-0 fw-semibold text-dark">Students & Scores (<?= 'BSIT-' . $selected_section['year'] . $selected_section['name'] ?>)</h6>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" onclick="exportSectionToExcel()">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-search mb-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-0 bg-light" id="studentTableSearch" placeholder="Search student name...">
                </div>
                    </div>
                    <div class="table-scroll-x">
                        <table class="excel-table compact-table" id="studentScoresTable">
                            <thead>
                                <tr>
                                    <th class="sticky-col">Student</th>
                                    <?php foreach ($assessments as $assess): ?>
                                        <th><?= htmlspecialchars($assess['assessment_title']) ?></th>
                                    <?php endforeach; ?>
                                    <th>Average</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($section_students as $stu): ?>
                                    <tr>
                                        <td class="sticky-col fw-medium"><?= htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name']) ?></td>
                                        <?php 
                                        $scores = $section_scores[$stu['id']];
                                        $valid_scores = array_filter($scores, function($score) { return !is_null($score); });
                                        $avg_score = count($valid_scores) > 0 ? round(array_sum($valid_scores) / count($valid_scores), 1) : 0;
                                        ?>
                                        <?php foreach ($assessments as $assess): ?>
                                            <td>
                                                <?php $score = $scores[$assess['id']]; ?>
                                                <?php if (is_null($score)): ?>
                                                    <span class="text-muted">-</span>
                                                <?php else: ?>
                                                    <span class="badge bg-<?= $score >= 90 ? 'success' : ($score >= 80 ? 'info' : ($score >= 70 ? 'warning' : 'danger')) ?>">
                                                        <?= $score ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="fw-bold text-primary"><?= $avg_score ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 py-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                            <i class="bi bi-bar-chart text-info"></i>
                        </div>
                        <h6 class="mb-0 fw-semibold text-dark">Section Performance</h6>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <canvas id="sectionScoresChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

        <!-- Hierarchical Grid Layout -->
    <div class="analytics-grid">
        <!-- Level 1: Course Overview -->
        <div class="grid-section course-overview">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="bi bi-graph-up-arrow text-primary me-2"></i>
                    Course Overview
                </h5>
                <p class="section-subtitle text-muted">Key performance indicators and progress metrics</p>
            </div>
            
            <div class="row g-3">
                <!-- Module Progress -->
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-bar-chart text-primary"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold text-dark">Module Progress Overview</h6>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <canvas id="progressChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Performance Distribution -->
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-pie-chart text-success"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold text-dark">Performance Distribution</h6>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <canvas id="performanceChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Level 2: Assessment Analytics -->
        <div class="grid-section assessment-analytics">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="bi bi-clipboard-data text-info me-2"></i>
                    Assessment Analytics
                </h5>
                <p class="section-subtitle text-muted">Detailed assessment performance and analysis</p>
            </div>
            
            <div class="row g-3">
                <!-- Assessment Performance Chart -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-clipboard-data text-info"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold text-dark">Assessment Performance</h6>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <canvas id="assessmentChart" height="180"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Assessment Details Table -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-transparent border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="bg-secondary bg-opacity-10 rounded-circle p-2 me-3">
                                <i class="bi bi-table text-secondary"></i>
                            </div>
                            <h6 class="mb-0 fw-semibold text-dark">Assessment Details</h6>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportAssessments()">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>
                        <div class="card-body pt-0">
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-hover table-sm">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="border-0 small">Assessment</th>
                                            <th class="border-0 small">Avg Score</th>
                                            <th class="border-0 small">Attempts</th>
                                            <th class="border-0 small">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assessment_details as $assess): ?>
                                            <tr>
                                                <td class="fw-medium small"><?= htmlspecialchars(substr($assess['title'], 0, 20)) . (strlen($assess['title']) > 20 ? '...' : '') ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $assess['avg_score'] >= 90 ? 'success' : ($assess['avg_score'] >= 80 ? 'info' : ($assess['avg_score'] >= 70 ? 'warning' : 'danger')) ?> small">
                                                        <?= $assess['avg_score'] ?>%
                                                    </span>
                                                </td>
                                                <td class="small"><?= $assess['attempts'] ?></td>
                                                <td>
                                                    <?php if ($assess['avg_score'] >= $assess['passing_rate']): ?>
                                                        <span class="badge bg-success small">Passing</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger small">Below</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Level 3: Comparative Analysis -->
        <div class="grid-section comparative-analysis">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="bi bi-diagram-3 text-warning me-2"></i>
                    Comparative Analysis
                </h5>
                <p class="section-subtitle text-muted">Section comparisons and student rankings</p>
            </div>
            
            <div class="row g-3">
                <!-- Section Comparison -->
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-diagram-3 text-warning"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold text-dark">Section Performance Comparison</h6>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <canvas id="sectionChart" height="180"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Student Leaderboard -->
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-dark bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-trophy text-dark"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold text-dark">Top Performers</h6>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div id="leaderboardTable"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 1. Student Progress Overview
const progressData = <?= json_encode($progress_data) ?>;
const progressChart = new Chart(document.getElementById('progressChart'), {
    type: 'bar',
    data: {
        labels: progressData.map(d => d.label),
        datasets: [{
            label: 'Avg. Progress (%)',
            data: progressData.map(d => d.value),
            backgroundColor: 'rgba(13, 110, 253, 0.8)',
            borderColor: 'rgba(13, 110, 253, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Progress: ${context.parsed.y}%`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});

// 2. Assessment Analytics
const assessmentData = <?= json_encode($assessment_data) ?>;
const assessmentChart = new Chart(document.getElementById('assessmentChart'), {
    type: 'bar',
    data: {
        labels: assessmentData.map(d => d.label),
        datasets: [{
            label: 'Avg. Score (%)',
            data: assessmentData.map(d => d.value),
            backgroundColor: 'rgba(13, 202, 240, 0.8)',
            borderColor: 'rgba(13, 202, 240, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Score: ${context.parsed.y}%`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});

// 3. Performance Distribution
const performanceData = <?= json_encode($performance_distribution) ?>;
const performanceChart = new Chart(document.getElementById('performanceChart'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(performanceData),
        datasets: [{
            data: Object.values(performanceData),
            backgroundColor: [
                'rgba(25, 135, 84, 0.8)',
                'rgba(13, 202, 240, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(255, 152, 0, 0.8)',
                'rgba(220, 53, 69, 0.8)'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: { size: 11 },
                    padding: 8
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return `${context.label}: ${context.parsed} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// 4. Section Comparison
const sectionData = <?= json_encode($section_data) ?>;
const sectionChart = new Chart(document.getElementById('sectionChart'), {
    type: 'bar',
    data: {
        labels: sectionData.map(d => d.label),
        datasets: [{
            label: 'Avg. Score (%)',
            data: sectionData.map(d => d.value),
            backgroundColor: 'rgba(255, 193, 7, 0.8)',
            borderColor: 'rgba(255, 193, 7, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Score: ${context.parsed.y}%`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});

// 5. Student Leaderboard
const leaderboard = <?= json_encode($leaderboard) ?>;
let leaderboardHtml = `<div class="leaderboard-list">`;
leaderboard.forEach((row, i) => {
    const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '';
    const rankClass = i === 0 ? 'rank-1' : i === 1 ? 'rank-2' : i === 2 ? 'rank-3' : '';
    leaderboardHtml += `
        <div class="leaderboard-item ${rankClass}">
            <div class="rank-info">
                <span class="rank-number">${i+1}</span>
                <span class="medal">${medal}</span>
            </div>
            <div class="student-info">
                <div class="student-name">${row.name}</div>
                <div class="student-stats">
                    <span class="score-badge">${row.score}%</span>
                    <span class="attempts">${row.attempts} attempts</span>
                </div>
            </div>
        </div>`;
});
leaderboardHtml += `</div>`;
document.getElementById('leaderboardTable').innerHTML = leaderboardHtml;

// Section Scores Chart
<?php if ($selected_section_id && $section_students): ?>
const sectionScoresLabels = <?= json_encode(array_map(function($stu){return $stu['last_name'] . ', ' . $stu['first_name'];}, $section_students)) ?>;
const sectionScoresDatasets = <?=
    (count($assessments) === 0)
        ? '[]'
        : '[
' . implode(",\n", array_map(function($assess) use ($section_students, $section_scores) {
    return '{
        label: ' . json_encode($assess['assessment_title']) . ',
        data: ' . json_encode(array_map(function($stu) use ($assess, $section_scores){ return $section_scores[$stu['id']][$assess['id']]; }, $section_students)) . ',
        backgroundColor: "rgba(13,110,253,0.7)"
    }';
}, $assessments)) . '
]';
?>;
new Chart(document.getElementById('sectionScoresChart'), {
    type: 'bar',
    data: {
        labels: sectionScoresLabels,
        datasets: sectionScoresDatasets
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: { size: 10 },
                    padding: 6
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true, 
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Search functionality
document.getElementById('studentTableSearch').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('studentScoresTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        const nameCell = row.getElementsByTagName('td')[0];
        const name = nameCell.textContent.toLowerCase();
        if (name.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

// Export functions
function exportToExcel() {
    const courseId = <?= $course_id ?>;
    const exportType = 'overview';
    const filename = '<?= $course['course_name'] ?>_Overview_<?= date('Y-m-d') ?>.csv';
    
    // Show loading indicator
    const exportBtn = document.querySelector('.export-btn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
    exportBtn.disabled = true;
    
    // Use CSV export function
    const downloadUrl = `export_analytics_csv.php?course_id=${courseId}&type=${exportType}`;
    downloadExcelFile(downloadUrl, filename)
        .then(() => {
            // Success - show success message
            exportBtn.innerHTML = '<i class="bi bi-check-circle"></i> Exported!';
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 1500);
        })
        .catch(error => {
            // Error - show error message
            console.error('Export failed:', error);
            exportBtn.innerHTML = '<i class="bi bi-x-circle"></i> Failed!';
            alert('Export failed. Please try again.');
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 2000);
        });
}

function exportSectionToExcel() {
    const courseId = <?= $course_id ?>;
    const sectionId = <?= $selected_section_id ?? 0 ?>;
    const exportType = 'section';
    const filename = '<?= $course['course_name'] ?>_Section_<?= date('Y-m-d') ?>.csv';
    
    if (!sectionId) {
        alert('Please select a section first');
        return;
    }
    
    // Show loading indicator
    const exportBtn = document.querySelector('[onclick="exportSectionToExcel()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
    exportBtn.disabled = true;
    
    // Use CSV export function
    const downloadUrl = `export_analytics_csv.php?course_id=${courseId}&type=${exportType}&section_id=${sectionId}`;
    downloadExcelFile(downloadUrl, filename)
        .then(() => {
            // Success - show success message
            exportBtn.innerHTML = '<i class="bi bi-check-circle"></i> Exported!';
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 1500);
        })
        .catch(error => {
            // Error - show error message
            console.error('Export failed:', error);
            exportBtn.innerHTML = '<i class="bi bi-x-circle"></i> Failed!';
            alert('Export failed. Please try again.');
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 2000);
        });
}

function exportAssessments() {
    const courseId = <?= $course_id ?>;
    const exportType = 'assessments';
    const filename = '<?= $course['course_name'] ?>_Assessments_<?= date('Y-m-d') ?>.csv';
    
    // Show loading indicator
    const exportBtn = document.querySelector('[onclick="exportAssessments()"]');
    if (exportBtn) {
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting...';
        exportBtn.disabled = true;
        
            // Use CSV export function
    const downloadUrl = `export_analytics_csv.php?course_id=${courseId}&type=${exportType}`;
        downloadExcelFile(downloadUrl, filename)
            .then(() => {
                // Success - show success message
                exportBtn.innerHTML = '<i class="bi bi-check-circle"></i> Exported!';
                setTimeout(() => {
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;
                }, 1500);
            })
            .catch(error => {
                // Error - show error message
                console.error('Export failed:', error);
                exportBtn.innerHTML = '<i class="bi bi-x-circle"></i> Failed!';
                alert('Export failed. Please try again.');
                setTimeout(() => {
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;
                }, 2000);
            });
    }
}

function printAnalytics() {
    window.print();
}

// Enhanced download function with error handling
function downloadExcelFile(url, filename) {
    return new Promise((resolve, reject) => {
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.blob();
            })
            .then(blob => {
                // Create blob URL
                const blobUrl = window.URL.createObjectURL(blob);
                
                // Create download link
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = filename;
                link.style.display = 'none';
                
                // Trigger download
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Clean up blob URL
                window.URL.revokeObjectURL(blobUrl);
                
                resolve();
            })
            .catch(error => {
                console.error('Download error:', error);
                reject(error);
            });
    });
}
</script>

<style>
/* Enhanced Modern Dashboard Styling */
body {
    background-color: #f8f9fa;
    min-height: 100vh;
}

.analytics-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

/* Enhanced Header Styling */
.analytics-header {
    background-color: #0d6efd;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.15);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.back-btn {
    background-color: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    padding: 0.75rem;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.back-btn:hover {
    background-color: rgba(255,255,255,0.25);
    color: white;
    transform: translateX(-2px);
}

.course-info h1 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.course-info p {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1rem;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.date-filter {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background-color: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 8px;
}

.date-filter select {
    background-color: transparent;
    border: none;
    color: white;
    font-weight: 500;
}

.date-filter select option {
    background-color: #0d6efd;
    color: white;
}

.action-btn {
    background-color: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-weight: 500;
}

.action-btn:hover {
    background-color: rgba(255,255,255,0.25);
    color: white;
    transform: translateY(-1px);
}

.export-btn {
    background-color: #198754;
    border-color: #198754;
    font-weight: 600;
}

.export-btn:hover {
    background-color: #157347;
    border-color: #157347;
}

/* Enhanced Summary Grid */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.summary-card {
    background-color: white;
    border-radius: 12px;
    padding: 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    position: relative;
    border: 1px solid #e9ecef;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background-color: var(--card-color);
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

.primary-card {
    --card-color: #0d6efd;
}

.success-card {
    --card-color: #198754;
}

.info-card {
    --card-color: #0dcaf0;
}

.warning-card {
    --card-color: #ffc107;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    background-color: var(--card-color);
}

.card-content {
    flex: 1;
}

.card-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.card-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Enhanced Card Styling */
.card {
    transition: all 0.2s ease;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12) !important;
}

/* Enhanced Hierarchical Grid Layout */
.analytics-grid {
    display: flex;
    flex-direction: column;
    gap: 2.5rem;
}

.grid-section {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.grid-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 6px;
    height: 100%;
    background: linear-gradient(180deg, var(--section-color), var(--section-color-light));
}

.section-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #f8f9fa;
    position: relative;
}

.section-header::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 60px;
    height: 2px;
    background: linear-gradient(90deg, var(--section-color), var(--section-color-light));
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.section-title i {
    margin-right: 0.75rem;
    font-size: 1.25rem;
}

.section-subtitle {
    font-size: 1rem;
    margin-bottom: 0;
    color: #6c757d;
    font-weight: 400;
}

/* Enhanced Section-specific styling */
.course-overview {
    --section-color: #667eea;
    --section-color-light: #764ba2;
}

.assessment-analytics {
    --section-color: #17a2b8;
    --section-color-light: #6f42c1;
}

.comparative-analysis {
    --section-color: #ffc107;
    --section-color-light: #fd7e14;
}

/* Sticky table header */
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f8f9fa;
}

/* Leaderboard Styling */
.leaderboard-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.leaderboard-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.leaderboard-item:hover {
    background: #e9ecef;
    transform: translateX(4px);
}

.leaderboard-item.rank-1 {
    background-color: #fff3cd;
    border: 2px solid #ffc107;
}

.leaderboard-item.rank-2 {
    background-color: #f8f9fa;
    border: 2px solid #6c757d;
}

.leaderboard-item.rank-3 {
    background-color: #f4e4d6;
    border: 2px solid #d4a574;
}

.rank-info {
    display: flex;
    align-items: center;
    margin-right: 1rem;
    min-width: 40px;
}

.rank-number {
    font-weight: 700;
    font-size: 1.1rem;
    color: #495057;
}

.medal {
    margin-left: 0.25rem;
    font-size: 1.2rem;
}

.student-info {
    flex: 1;
}

.student-name {
    font-weight: 600;
    color: #212529;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.student-stats {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.score-badge {
    background: #198754;
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.attempts {
    font-size: 0.75rem;
    color: #6c757d;
}

/* Table Styling */
.excel-table {
    border-collapse: collapse;
    width: 100%;
    font-size: 0.85rem;
    background: #fff;
    min-width: 600px;
    border-radius: 8px;
    overflow: hidden;
}
.excel-table.compact-table {
    font-size: 0.8rem;
}
.excel-table th, .excel-table td {
    border: 1px solid #e9ecef;
    padding: 0.4rem 0.6rem;
    text-align: center;
    vertical-align: middle;
    font-family: 'Segoe UI', 'Arial', sans-serif;
    background: #fff;
}
.excel-table.compact-table th, .excel-table.compact-table td {
    padding: 0.3rem 0.5rem;
}
.excel-table th {
    background: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 2;
    font-weight: 600;
    color: #495057;
}
.excel-table tr:nth-child(even) td {
    background: #f8f9fa;
}
.excel-table tr:hover td {
    background: #e3f2fd;
}
.excel-table th.sticky-col, .excel-table td.sticky-col {
    position: sticky;
    left: 0;
    background: #f8f9fa;
    z-index: 3;
    box-shadow: 2px 0 2px -1px #e9ecef;
}
.table-scroll-x {
    overflow-x: auto;
    width: 100%;
    border-radius: 8px;
}
.table-search {
    margin-bottom: 1rem;
    max-width: 350px;
}

/* Modern Input Styling */
.input-group-text {
    border: none;
    background: #f8f9fa;
}

.form-control {
    border: none;
    background: #f8f9fa;
}

.form-control:focus {
    box-shadow: none;
    background: #fff;
    border: 1px solid #dee2e6;
}

/* Badge Styling */
.badge {
    font-weight: 500;
    padding: 0.4em 0.6em;
}

/* Chart Container Styling */
canvas {
    border-radius: 8px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .table-search {
        max-width: 100%;
    }
}

@media print {
    .btn, .form-select, .table-search {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        break-inside: avoid;
        box-shadow: none !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?> 