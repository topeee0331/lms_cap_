<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Fetch all academic periods for the dropdown
$ay_stmt = $pdo->prepare('SELECT id, academic_year, semester_name, is_active FROM academic_periods ORDER BY academic_year DESC, semester_name');
$ay_stmt->execute();
$all_years = $ay_stmt->fetchAll();

// 2. Handle academic year selection (GET or SESSION)
if (isset($_GET['academic_period_id'])) {
    $_SESSION['student_dashboard_academic_period_id'] = (int)$_GET['academic_period_id'];
}
// Find the first active academic year
$active_year = null;
foreach ($all_years as $year) {
    if ($year['is_active']) {
        $active_year = $year['id'];
        break;
    }
}
$selected_year_id = $_SESSION['student_dashboard_academic_period_id'] ?? $active_year ?? ($all_years[0]['id'] ?? null);

// Get student information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

// Get enrolled courses for the selected academic period
$stmt = $pdo->prepare("
    SELECT c.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM course_enrollments e2 WHERE e2.course_id = c.id AND e2.status = 'active') as enrolled_students
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    WHERE e.student_id = ? AND e.status = 'active' AND c.academic_period_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id, $selected_year_id]);
$enrolled_courses = $stmt->fetchAll();

// Get enrollment requests status for the student
$stmt = $pdo->prepare("
    SELECT er.*, c.course_name, c.course_code, u.first_name, u.last_name as teacher_last_name
    FROM enrollment_requests er
    JOIN courses c ON er.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    WHERE er.student_id = ? AND c.academic_period_id = ?
    ORDER BY er.requested_at DESC
    LIMIT 5
");
$stmt->execute([$user_id, $selected_year_id]);
$enrollment_requests = $stmt->fetchAll();

// Get recent announcements (system-wide and course-specific for enrolled courses)
$enrolled_course_ids = array_column($enrolled_courses, 'id');

if (!empty($enrolled_course_ids)) {
    $placeholders = str_repeat('?,', count($enrolled_course_ids) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name, c.course_name, c.course_code
        FROM announcements a
        JOIN users u ON a.author_id = u.id
        LEFT JOIN courses c ON JSON_SEARCH(a.target_audience, 'one', c.id) IS NOT NULL
        WHERE a.is_global = 1 OR JSON_SEARCH(a.target_audience, 'one', ?) IS NOT NULL
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
} else {
    // No enrolled courses, get only system-wide announcements
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name, NULL as course_name, NULL as course_code
        FROM announcements a
        JOIN users u ON a.author_id = u.id
        WHERE a.is_global = 1
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
}
$recent_announcements = $stmt->fetchAll();

// Get recent activities (assessment attempts) for selected academic year
// Note: Video progress is now tracked in course_enrollments.video_progress JSON field
$stmt = $pdo->prepare("
    SELECT 'assessment' as type, a.assessment_title as title, a.created_at as activity_date, 'Attempted assessment' as action
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE aa.student_id = ? AND c.academic_period_id = ?
    ORDER BY aa.started_at DESC
    LIMIT 10
");
$stmt->execute([$user_id, $selected_year_id]);
$recent_activities = $stmt->fetchAll();

// Get badges earned from JSON awarded_to field
$stmt = $pdo->prepare("
    SELECT b.*, 
           JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', ?), '].awarded_at')) as earned_at
    FROM badges b
    WHERE JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL
    ORDER BY earned_at DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id]);
$badges = $stmt->fetchAll();

// Get overall progress statistics for selected academic year
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT e.course_id) as total_courses,
        0 as completed_modules, -- TODO: Calculate from course_enrollments.module_progress JSON
        (SELECT COUNT(*) FROM course_enrollments e2 
         JOIN courses c ON e2.course_id = c.id
         WHERE e2.student_id = ? AND e2.status = 'active' AND c.academic_period_id = ? AND c.modules IS NOT NULL) as total_modules,
        (SELECT COUNT(*) FROM courses WHERE academic_period_id = ? AND status = 'active') as available_in_section,
        (SELECT COUNT(*) FROM courses WHERE academic_period_id = ? AND status = 'inactive') as inactive_periods,
        (SELECT COUNT(*) FROM courses WHERE academic_period_id = ? AND status = 'active' AND id NOT IN (SELECT course_id FROM course_enrollments WHERE student_id = ? AND status = 'active')) as other_sections
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    -- Note: module_progress is now stored as JSON in course_enrollments.module_progress
    WHERE e.student_id = ? AND e.status = 'active' AND c.academic_period_id = ?
");
$stmt->execute([$user_id, $selected_year_id, $selected_year_id, $selected_year_id, $selected_year_id, $user_id, $user_id, $selected_year_id]);
$stats = $stmt->fetch();

$overall_progress = $stats['total_modules'] > 0 ? round(($stats['completed_modules'] / $stats['total_modules']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - NEUST LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(#007bff 0deg, #007bff <?php echo $overall_progress * 3.6; ?>deg, #e9ecef <?php echo $overall_progress * 3.6; ?>deg, #e9ecef 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        /* General card styling for user-friendliness */
        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .progress-circle::before {
            content: '';
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
        }
        .progress-text {
            position: absolute;
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .course-card {
            transition: transform 0.2s;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .badge-item {
            display: inline-block;
            margin: 5px;
            text-align: center;
        }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 10px;
        }
        
        /* Course card styling with background icons */
        .course-card {
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            position: relative;
        }
        .course-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        .course-image {
            height: 200px;
            object-fit: cover;
            border-radius: 15px 15px 0 0;
            position: relative;
        }
        
        /* Clean card patterns */
        .course-card:nth-child(3n+1) .course-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(
                circle at 30% 30%,
                rgba(255,255,255,0.08) 0%,
                transparent 50%
            );
            z-index: 1;
        }
        
        .course-card:nth-child(3n+2) .course-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(
                circle at 70% 70%,
                rgba(255,255,255,0.06) 0%,
                transparent 50%
            );
            z-index: 1;
        }
        
        .course-card:nth-child(3n+3) .course-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(
                circle at 20% 80%,
                rgba(255,255,255,0.08) 0%,
                transparent 50%
            );
            z-index: 1;
        }
        
        /* Clean border accents */
        .course-card:nth-child(4n+1) {
            border-top: 3px solid #28a745;
        }
        
        .course-card:nth-child(4n+2) {
            border-top: 3px solid #007bff;
        }
        
        .course-card:nth-child(4n+3) {
            border-top: 3px solid #ffc107;
        }
        
        .course-card:nth-child(4n+4) {
            border-top: 3px solid #dc3545;
        }
        
        /* Subtle corner indicators */
        .course-card::after {
            content: '';
            position: absolute;
            top: 12px;
            right: 12px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            z-index: 3;
        }
        
        .course-card:nth-child(5n+1)::after {
            background: #28a745;
        }
        
        .course-card:nth-child(5n+2)::after {
            background: #007bff;
        }
        
        .course-card:nth-child(5n+3)::after {
            background: #ffc107;
        }
        
        .course-card:nth-child(5n+4)::after {
            background: #dc3545;
        }
        
        .course-card:nth-child(5n+5)::after {
            background: #6f42c1;
        }
        
        /* Creative Course Code Styling */
        .course-code-text {
            font-family: 'Poppins', 'Arial', sans-serif;
            font-size: 2.8rem;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            mix-blend-mode: overlay;
            position: relative;
            z-index: 2;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        /* Different text styles for variety */
        .course-card:nth-child(2n+1) .course-code-text {
            font-weight: 900;
            letter-spacing: -1px;
        }
        
        .course-card:nth-child(2n+2) .course-code-text {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        /* Clean progress bar styling */
        .progress {
            border-radius: 10px;
            height: 8px;
            background: #f8f9fa;
        }
        
        .progress-bar {
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap');
        
        /* Button styling for better user experience */
        .btn {
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Progress bar styling */
        .progress {
            border-radius: 10px;
            height: 8px;
        }
        
        .progress-bar {
            border-radius: 10px;
        }
        
        /* Activity and badge items */
        .activity-item, .badge-item {
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        
        .activity-item:hover {
            background-color: rgba(0,123,255,0.05);
        }
        
        /* Card content enhancements */
        .course-card .card-body {
            position: relative;
        }
        
        /* Course title styling variations */
        .course-card:nth-child(3n+1) .card-title {
            color: #28a745;
            font-weight: 600;
        }
        
        .course-card:nth-child(3n+2) .card-title {
            color: #007bff;
            font-weight: 600;
        }
        
        .course-card:nth-child(3n+3) .card-title {
            color: #6f42c1;
            font-weight: 600;
        }
        
        /* Clean button styling variations */
        .course-card:nth-child(4n+1) .btn-primary {
            background: #28a745;
            border: none;
        }
        
        .course-card:nth-child(4n+2) .btn-primary {
            background: #007bff;
            border: none;
        }
        
        .course-card:nth-child(4n+3) .btn-primary {
            background: #ffc107;
            border: none;
            color: #212529;
        }
        
        .course-card:nth-child(4n+4) .btn-primary {
            background: #dc3545;
            border: none;
        }
        
        /* Subtle card footer accent */
        .course-card .card-body::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            opacity: 0.5;
        }

        /* Enrollment request status styling */
        .enrollment-status {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Announcement styling */
        .announcement-item {
            border-left: 3px solid #17a2b8;
            padding-left: 15px;
            margin-bottom: 15px;
            transition: background-color 0.2s;
        }
        
        .announcement-item:hover {
            background-color: rgba(23, 162, 184, 0.05);
        }
        
        .announcement-course {
            font-size: 0.75rem;
            color: #6c757d;
            background-color: #e9ecef;
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            display: inline-block;
            margin-bottom: 0.25rem;
        }
        
        .announcement-system {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/badge_notification.php'; ?>
    <?php displayBadgeNotifications(); ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Removed Sidebar -->
            <!-- Main content -->
            <main class="col-12 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Welcome back, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>!</h1>
                        <?php if (!empty($student['student_id'])): ?>
                            <p class="text-muted mb-0">Student ID: <span class="badge bg-primary"><?php echo htmlspecialchars($student['student_id']); ?></span></p>
                        <?php endif; ?>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="courses.php" class="btn btn-sm btn-outline-primary">View All Courses</a>
                        </div>
                    </div>
                </div>

                <!-- Academic Year Selection -->
                <div class="row mb-3">
                    <div class="col-12">
                        <form method="get" class="d-flex align-items-center">
                            <label for="academic_period_id" class="me-2 fw-bold">Academic Year:</label>
                            <select name="academic_period_id" id="academic_period_id" class="form-select w-auto me-2" onchange="this.form.submit()">
                                <?php foreach ($all_years as $year): ?>
                                    <option value="<?= $year['id'] ?>" <?= $selected_year_id == $year['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['academic_year'] . ' - ' . $year['semester_name']) ?><?= !$year['is_active'] ? ' (Inactive)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button type="submit" class="btn btn-primary btn-sm">Go</button></noscript>
                        </form>
                    </div>
                </div>

                <!-- Course Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-book fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $stats['total_courses'] ?? 0; ?></h4>
                                <p class="card-text small">Enrolled Courses</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $stats['available_in_section'] ?? 0; ?></h4>
                                <p class="card-text small">Available in Section</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-layer-group fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $stats['other_sections'] ?? 0; ?></h4>
                                <p class="card-text small">Other Sections</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #212529; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-clock fa-2x mb-2" style="color: rgba(33,37,41,0.8);"></i>
                                <h4 class="card-title mb-1"><?php echo $stats['inactive_periods'] ?? 0; ?></h4>
                                <p class="card-text small">Inactive Periods</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Overview -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="position-relative">
                                    <div class="progress-circle"></div>
                                    <div class="progress-text"><?php echo $overall_progress; ?>%</div>
                                </div>
                                <h5 class="card-title mt-3">Overall Progress</h5>
                                <p class="card-text"><?php echo $stats['completed_modules']; ?> of <?php echo $stats['total_modules']; ?> modules completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-trophy fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Badges Earned</h5>
                                <p class="card-text display-6"><?php echo count($badges); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enrollment Requests & Announcements Row -->
                <div class="row mb-4">
                    <!-- Enrollment Requests -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-user-plus"></i> Enrollment Requests</h5>
                                <a href="enrollment_requests.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($enrollment_requests)): ?>
                                    <p class="text-muted">No enrollment requests found.</p>
                                <?php else: ?>
                                    <?php foreach ($enrollment_requests as $request): ?>
                                        <div class="d-flex justify-content-between align-items-start mb-3 p-2 rounded">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-1">
                                                    <strong class="me-2"><?php echo htmlspecialchars($request['course_name']); ?></strong>
                                                    <span class="enrollment-status status-<?php echo $request['status']; ?>">
                                                        <?php echo ucfirst($request['status']); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-user-tie me-1"></i>
                                                    <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['teacher_last_name']); ?>
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($request['requested_at'])); ?>
                                                </small>
                                                <?php if ($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
                                                    <div class="mt-2 p-2 bg-light rounded">
                                                        <small class="text-danger">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                                            <strong>Reason:</strong> <?php echo htmlspecialchars($request['rejection_reason']); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Announcements -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bullhorn"></i> Recent Announcements</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_announcements)): ?>
                                    <p class="text-muted">No announcements available.</p>
                                <?php else: ?>
                                    <?php foreach ($recent_announcements as $announcement): ?>
                                        <div class="announcement-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <?php if ($announcement['course_name']): ?>
                                                        <span class="announcement-course"><?php echo htmlspecialchars($announcement['course_code'] . ' - ' . $announcement['course_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="announcement-course announcement-system">System Announcement</span>
                                                    <?php endif; ?>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                    <p class="mb-1 small"><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . (strlen($announcement['content']) > 100 ? '...' : ''); ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                                        <span class="ms-2">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                        </span>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enrolled Courses -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h3>My Courses</h3>
                        <?php if (empty($enrolled_courses)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> You haven't enrolled in any courses yet. 
                                <a href="courses.php" class="alert-link">Browse available courses</a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach (array_slice($enrolled_courses, 0, 6) as $course): ?>
                                    <?php
                                        // Define course themes
                                        $course_themes = [
                                            ['bg' => 'bg-primary', 'icon' => 'fas fa-book'],
                                            ['bg' => 'bg-success', 'icon' => 'fas fa-graduation-cap'],
                                            ['bg' => 'bg-info', 'icon' => 'fas fa-laptop-code'],
                                            ['bg' => 'bg-warning', 'icon' => 'fas fa-chart-line'],
                                            ['bg' => 'bg-danger', 'icon' => 'fas fa-flask'],
                                            ['bg' => 'bg-secondary', 'icon' => 'fas fa-calculator']
                                        ];
                                        $theme = $course_themes[$course['id'] % count($course_themes)];
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card course-card">
                                            <div class="card-img-top course-image d-flex align-items-center justify-content-center <?php echo $theme['bg']; ?>" style="height: 200px; position: relative; overflow: hidden;">
                                                <i class="<?php echo $theme['icon']; ?>" style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0.4; pointer-events: none; font-size: 10rem; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6);"></i>
                                                <h2 class="course-code-text">
                                                    <?php echo htmlspecialchars($course['course_code'] ?? 'N/A'); ?>
                                                </h2>
                                            </div>
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                                <p class="card-text text-muted">by <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></p>
                                                <div class="progress mb-3">
                                                    <?php 
                                                    // TODO: Calculate progress from course_enrollments.module_progress JSON
                                                    $course_progress = 0; // Placeholder until JSON progress is implemented
                                                    ?>
                                                    <div class="progress-bar" style="width: <?php echo $course_progress; ?>%">
                                                        <?php echo $course_progress; ?>%
                                                    </div>
                                                </div>
                                                <p class="card-text small">
                                                    Progress tracking coming soon
                                                </p>
                                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                                    Continue Learning
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($enrolled_courses) > 6): ?>
                                <div class="text-center">
                                    <a href="courses.php" class="btn btn-outline-primary">View All Courses</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Activities -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activities)): ?>
                                    <p class="text-muted">No recent activities</p>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $activity['action']; ?></small>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($activity['activity_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Badges -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-trophy"></i> Recent Badges</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($badges)): ?>
                                    <p class="text-muted">No badges earned yet. Keep learning to earn badges!</p>
                                <?php else: ?>
                                    <?php foreach ($badges as $badge): ?>
                                        <div class="badge-item">
                                            <?php
                                            $icon_path = "../uploads/badges/" . htmlspecialchars($badge['badge_icon']);
                                            if (!empty($badge['badge_icon']) && file_exists(__DIR__ . "/../uploads/badges/" . $badge['badge_icon'])): ?>
                                                <img src="<?php echo $icon_path; ?>" alt="<?php echo htmlspecialchars($badge['badge_name']); ?>" class="img-fluid" style="width: 50px; height: 50px;">
                                            <?php else: ?>
                                                <span class="d-inline-flex align-items-center justify-content-center bg-secondary text-white rounded-circle" style="width: 50px; height: 50px; font-size: 2rem;">
                                                    <i class="fas fa-award"></i>
                                                </span>
                                            <?php endif; ?>
                                            <br>
                                            <small><?php echo htmlspecialchars($badge['badge_name']); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo !empty($badge['earned_at']) ? date('M j, Y', strtotime($badge['earned_at'])) : 'Date not available'; ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Include Pusher for real-time updates -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="../assets/js/pusher-client.js"></script>
    <script>
        // Initialize Pusher for student dashboard
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PusherClient !== 'undefined') {
                const pusherClient = new PusherClient();
                pusherClient.initializeStudentDashboard(<?php echo $user_id; ?>);
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 