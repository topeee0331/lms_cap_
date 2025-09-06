<?php
session_start();
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

// Get all course IDs the student is enrolled in
$enrolled_stmt = $pdo->prepare('SELECT course_id FROM course_enrollments WHERE student_id = ? AND status = "active"');
$enrolled_stmt->execute([$user_id]);
$all_course_ids = $enrolled_stmt->fetchAll(PDO::FETCH_COLUMN);

$modules = [];
if (!empty($all_course_ids)) {
    $in = str_repeat('?,', count($all_course_ids) - 1) . '?';
    $params = $all_course_ids;
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.module_title, cm.module_description, c.course_name
        FROM course_modules cm
        JOIN courses c ON cm.course_id = c.id
        WHERE c.id IN ($in)
        ORDER BY c.course_name, cm.module_order
    ");
    $stmt->execute($params);
    $modules = $stmt->fetchAll();
}

// After fetching $modules, fetch files for each module
foreach ($modules as &$module) {
    $stmt = $pdo->prepare('SELECT id, file_name, file_path, uploaded_at FROM module_files WHERE module_id = ?');
    $stmt->execute([$module['id']]);
    $module['files'] = $stmt->fetchAll();
}
unset($module);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modules - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .module-card {
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="courses.php">
                                <i class="fas fa-book"></i> My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="modules.php">
                                <i class="fas fa-layer-group"></i> Modules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assessments.php">
                                <i class="fas fa-question-circle"></i> Assessments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="progress.php">
                                <i class="fas fa-chart-line"></i> Progress
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="badges.php">
                                <i class="fas fa-trophy"></i> Badges
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leaderboard.php">
                                <i class="fas fa-medal"></i> Leaderboard
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Modules</h1>
                </div>
                <div class="row">
                    <?php if (empty($modules)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No modules are currently available for your enrolled courses.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($modules as $module): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card module-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($module['module_title']) ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($module['course_name']) ?></h6>
                                        <p class="card-text"><?= htmlspecialchars($module['module_description']) ?></p>
                                        <?php if (!empty($module['files'])): ?>
                                            <div class="mt-2">
                                                <strong>Files:</strong>
                                                <ul class="mb-0 ps-3">
                                                    <?php foreach ($module['files'] as $file): ?>
                                                        <li>
                                                            <a href="/lms_cap/uploads/modules/<?php echo rawurlencode(basename($file['file_path'])); ?>" target="_blank"><?php echo htmlspecialchars($file['file_name']); ?></a>
                                                            <small class="text-muted">(uploaded <?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?>)</small>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 