<?php
require_once '../config/config.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current user's basic info
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user_basic = $stmt->fetch();

// Get all courses for the course filter
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.course_name, c.course_code
    FROM courses c
    ORDER BY c.course_name
");
$stmt->execute();
$all_courses = $stmt->fetchAll();

// Get all sections for the section filter
$stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.section_name, s.year_level
    FROM sections s
    ORDER BY s.year_level, s.section_name
");
$stmt->execute();
$all_sections = $stmt->fetchAll();

// Get leaderboard data with simplified queries
$stmt = $pdo->prepare("
    SELECT 
        u.id, u.first_name, u.last_name, u.email, u.profile_picture,
        s.section_name, s.year_level as section_year,
        (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) as badge_count,
        (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as completed_assessments,
        (SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as average_score,
        (
            (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') * 10 +
            COALESCE((SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed'), 0) * 0.5 +
            (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) * 5
        ) as calculated_score
    FROM users u
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE u.role = 'student'
    ORDER BY calculated_score DESC
    LIMIT 50
");
$stmt->execute();
$leaderboard_data = $stmt->fetchAll();

// Get current user's rank
$stmt = $pdo->prepare("
    SELECT 
        u.id, u.first_name, u.last_name, u.email, u.profile_picture,
        s.section_name, s.year_level as section_year,
        (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) as badge_count,
        (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as completed_assessments,
        (SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as average_score,
        (
            (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') * 10 +
            COALESCE((SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed'), 0) * 0.5 +
            (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) * 5
        ) as calculated_score
    FROM users u
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$current_user_data = $stmt->fetch();

// Calculate current user's rank
$current_user_rank = 1;
foreach ($leaderboard_data as $index => $user) {
    if ($user['id'] == $user_id) {
        $current_user_rank = $index + 1;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">NEUST-MGT BSIT LMS</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <main class="col-12 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Leaderboard</h1>
                </div>

                <!-- Current User Rank -->
                <?php if ($current_user_data): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Your Rank</h5>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2 text-center">
                                        <h2 class="text-primary">#<?php echo $current_user_rank; ?></h2>
                                    </div>
                                    <div class="col-md-8">
                                        <h5><?php echo htmlspecialchars($current_user_data['first_name'] . ' ' . $current_user_data['last_name']); ?></h5>
                                        <p class="text-muted mb-0">
                                            <?php echo htmlspecialchars($current_user_data['section_name'] ?? 'No Section'); ?> - 
                                            Year <?php echo $current_user_data['section_year'] ?? 'N/A'; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small class="text-muted">Badges</small>
                                                <div class="h6"><?php echo $current_user_data['badge_count']; ?></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Assessments</small>
                                                <div class="h6"><?php echo $current_user_data['completed_assessments']; ?></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Score</small>
                                                <div class="h6"><?php echo round($current_user_data['average_score'] ?? 0, 1); ?>%</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Leaderboard -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top 50 Students</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($leaderboard_data)): ?>
                                    <p class="text-muted text-center">No leaderboard data available.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Student</th>
                                                    <th>Section</th>
                                                    <th>Badges</th>
                                                    <th>Assessments</th>
                                                    <th>Avg Score</th>
                                                    <th>Total Score</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($leaderboard_data as $index => $student): ?>
                                                <tr class="<?php echo $student['id'] == $user_id ? 'table-primary' : ''; ?>">
                                                    <td>
                                                        <?php if ($index < 3): ?>
                                                            <i class="fas fa-medal text-<?php echo $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'warning'); ?>"></i>
                                                        <?php endif; ?>
                                                        #<?php echo $index + 1; ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="me-3">
                                                                <?php if ($student['profile_picture']): ?>
                                                                    <img src="../uploads/profiles/<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                                                         class="rounded-circle" width="40" height="40" alt="Profile">
                                                                <?php else: ?>
                                                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                                         style="width: 40px; height: 40px;">
                                                                        <i class="fas fa-user text-white"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($student['section_name'] ?? 'No Section'); ?>
                                                        <br><small class="text-muted">Year <?php echo $student['section_year'] ?? 'N/A'; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $student['badge_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $student['completed_assessments']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo round($student['average_score'] ?? 0, 1); ?>%</span>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo round($student['calculated_score'] ?? 0, 1); ?></strong>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
