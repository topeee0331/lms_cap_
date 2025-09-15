<?php
$page_title = 'Students';
require_once '../config/config.php';
requireRole('teacher');
require_once '../includes/header.php';

$message = '';
$message_type = '';

// 1. Fetch all academic periods for the dropdown
$ay_stmt = $db->prepare('SELECT id, academic_year, semester_name, is_active FROM academic_periods ORDER BY is_active DESC, academic_year DESC, semester_name');
$ay_stmt->execute();
$all_years = $ay_stmt->fetchAll();

// 2. Handle academic period selection (GET or SESSION)
if (isset($_GET['academic_period_id'])) {
    $_SESSION['teacher_students_academic_period_id'] = (int)$_GET['academic_period_id'];
}
// Find the first active academic year
$active_year = null;
foreach ($all_years as $year) {
    if ($year['is_active']) {
        $active_year = $year['id'];
        break;
    }
}
$selected_year_id = $_SESSION['teacher_students_academic_period_id'] ?? $active_year ?? ($all_years[0]['id'] ?? null);

// Handle student actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'enroll_student':
                $student_id = (int)($_POST['student_id'] ?? 0);
                $course_id = (int)($_POST['course_id'] ?? 0);
                
                if (!$student_id || !$course_id) {
                    $message = 'Student and course are required.';
                    $message_type = 'danger';
                } else {
                    // Verify course belongs to teacher and is in selected academic period
                    $stmt = $db->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ? AND academic_period_id = ?');
                    $stmt->execute([$course_id, $_SESSION['user_id'], $selected_year_id]);
                    if ($stmt->fetch()) {
                        // Check if already enrolled
                        $stmt = $db->prepare('SELECT id FROM course_enrollments WHERE student_id = ? AND course_id = ?');
                        $stmt->execute([$student_id, $course_id]);
                        
                        if ($stmt->fetch()) {
                            $message = 'Student is already enrolled in this course.';
                            $message_type = 'warning';
                        } else {
                            $stmt = $db->prepare('INSERT INTO course_enrollments (student_id, course_id, enrolled_at) VALUES (?, ?, NOW())');
                            $stmt->execute([$student_id, $course_id]);
                            $message = 'Student enrolled successfully.';
                            $message_type = 'success';
                        }
                    } else {
                        $message = 'Invalid course selected.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'kick_student':
                $student_id = (int)($_POST['student_id'] ?? 0);
                $course_id = (int)($_POST['course_id'] ?? 0);
                
                if (!$student_id || !$course_id) {
                    $message = 'Student and course are required.';
                    $message_type = 'danger';
                } else {
                    try {
                        // Verify course belongs to teacher and is in selected academic period
                        $stmt = $db->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ? AND academic_period_id = ?');
                        $stmt->execute([$course_id, $_SESSION['user_id'], $selected_year_id]);
                        if ($stmt->fetch()) {
                            // Start transaction for data integrity
                            $db->beginTransaction();
                            
                            // Remove student from course enrollments
                            $stmt = $db->prepare('DELETE FROM course_enrollments WHERE student_id = ? AND course_id = ?');
                            $stmt->execute([$student_id, $course_id]);
                            
                            // Remove assessment attempts for this student in this course
                            $stmt = $db->prepare('
                                DELETE aa FROM assessment_attempts aa 
                                JOIN assessments a ON aa.assessment_id = a.id
                                WHERE aa.student_id = ? AND a.course_id = ?
                            ');
                            $stmt->execute([$student_id, $course_id]);
                            
                            // Remove video views for this student in this course (if table exists)
                            try {
                                $stmt = $db->prepare('
                                    DELETE vv FROM video_views vv 
                                    JOIN videos v ON vv.video_id = v.id
                                    WHERE vv.student_id = ? AND v.course_id = ?
                                ');
                                $stmt->execute([$student_id, $course_id]);
                            } catch (PDOException $e) {
                                // Table doesn't exist, skip this step
                                error_log("Video views table not found, skipping: " . $e->getMessage());
                            }
                            
                            // Remove module progress for this student in this course (if table exists)
                            try {
                                $stmt = $db->prepare('
                                    DELETE mp FROM module_progress mp 
                                    JOIN modules m ON mp.module_id = m.id
                                    WHERE mp.student_id = ? AND m.course_id = ?
                                ');
                                $stmt->execute([$student_id, $course_id]);
                            } catch (PDOException $e) {
                                // Table doesn't exist, skip this step
                                error_log("Module progress table not found, skipping: " . $e->getMessage());
                            }
                            
                            // Remove any enrollment requests for this course
                            $stmt = $db->prepare('DELETE FROM enrollment_requests WHERE student_id = ? AND course_id = ?');
                            $stmt->execute([$student_id, $course_id]);
                            
                            // Create notification for the student
                            $stmt = $db->prepare('SELECT course_name, course_code FROM courses WHERE id = ?');
                            $stmt->execute([$course_id]);
                            $course_info = $stmt->fetch();
                            
                            if ($course_info) {
                                $notification_title = "Removed from Course";
                                $notification_message = "You have been removed from the course '{$course_info['course_name']}' ({$course_info['course_code']}) by your teacher. All your progress data has been cleared.";
                                
                                $stmt = $db->prepare("
                                    INSERT INTO notifications (user_id, title, message, type, related_id, priority) 
                                    VALUES (?, ?, ?, 'course_kicked', ?, 'high')
                                ");
                                $stmt->execute([$student_id, $notification_title, $notification_message, $course_id]);
                                
                                // Send real-time notification via Pusher
                                require_once '../includes/pusher_notifications.php';
                                PusherNotifications::sendSystemNotification(
                                    $student_id, 
                                    $notification_title, 
                                    $notification_message, 
                                    'warning'
                                );
                            }
                            
                            // Commit transaction
                            $db->commit();
                            
                            $message = 'Student has been KICKED from the course successfully. All their progress data has been removed.';
                            $message_type = 'success';
                        } else {
                            $message = 'Invalid course selected.';
                            $message_type = 'danger';
                        }
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        if ($db->inTransaction()) {
                            $db->rollback();
                        }
                        $message = 'Error kicking student: ' . $e->getMessage();
                        $message_type = 'danger';
                        error_log("Error kicking student: " . $e->getMessage());
                    }
                }
                break;
                
            case 'bulk_kick_students':
                $student_ids = $_POST['student_ids'] ?? [];
                
                if (empty($student_ids)) {
                    $message = 'No students selected.';
                    $message_type = 'warning';
                } else {
                    try {
                        $kicked_count = 0;
                        $db->beginTransaction();
                        
                        foreach ($student_ids as $student_id) {
                            $student_id = (int)$student_id;
                            
                            // Get all courses for this student that belong to this teacher
                            $stmt = $db->prepare('
                                SELECT ce.course_id 
                                FROM course_enrollments ce
                                JOIN courses c ON ce.course_id = c.id
                                WHERE ce.student_id = ? AND c.teacher_id = ? AND c.academic_period_id = ?
                            ');
                            $stmt->execute([$student_id, $_SESSION['user_id'], $selected_year_id]);
                            $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            foreach ($courses as $course_id) {
                                // Remove student from course enrollments
                                $stmt = $db->prepare('DELETE FROM course_enrollments WHERE student_id = ? AND course_id = ?');
                                $stmt->execute([$student_id, $course_id]);
                                
                                // Remove assessment attempts for this student in this course
                                $stmt = $db->prepare('
                                    DELETE aa FROM assessment_attempts aa 
                                    JOIN assessments a ON aa.assessment_id = a.id
                                    WHERE aa.student_id = ? AND a.course_id = ?
                                ');
                                $stmt->execute([$student_id, $course_id]);
                                
                                // Remove video views for this student in this course (if table exists)
                                try {
                                    $stmt = $db->prepare('
                                        DELETE vv FROM video_views vv 
                                        JOIN videos v ON vv.video_id = v.id
                                        WHERE vv.student_id = ? AND v.course_id = ?
                                    ');
                                    $stmt->execute([$student_id, $course_id]);
                                } catch (PDOException $e) {
                                    // Table doesn't exist, skip this step
                                    error_log("Video views table not found in bulk kick, skipping: " . $e->getMessage());
                                }
                                
                                // Remove module progress for this student in this course (if table exists)
                                try {
                                    $stmt = $db->prepare('
                                        DELETE mp FROM module_progress mp 
                                        JOIN modules m ON mp.module_id = m.id
                                        WHERE mp.student_id = ? AND m.course_id = ?
                                    ');
                                    $stmt->execute([$student_id, $course_id]);
                                } catch (PDOException $e) {
                                    // Table doesn't exist, skip this step
                                    error_log("Module progress table not found in bulk kick, skipping: " . $e->getMessage());
                                }
                                
                                // Remove any enrollment requests for this course
                                $stmt = $db->prepare('DELETE FROM enrollment_requests WHERE student_id = ? AND course_id = ?');
                                $stmt->execute([$student_id, $course_id]);
                                
                                // Create notification for the student for this course
                                $stmt = $db->prepare('SELECT course_name, course_code FROM courses WHERE id = ?');
                                $stmt->execute([$course_id]);
                                $course_info = $stmt->fetch();
                                
                                if ($course_info) {
                                    $notification_title = "Removed from Course";
                                    $notification_message = "You have been removed from the course '{$course_info['course_name']}' ({$course_info['course_code']}) by your teacher. All your progress data has been cleared.";
                                    
                                    $stmt = $db->prepare("
                                        INSERT INTO notifications (user_id, title, message, type, related_id, priority) 
                                        VALUES (?, ?, ?, 'course_kicked', ?, 'high')
                                    ");
                                    $stmt->execute([$student_id, $notification_title, $notification_message, $course_id]);
                                    
                                    // Send real-time notification via Pusher
                                    require_once '../includes/pusher_notifications.php';
                                    PusherNotifications::sendSystemNotification(
                                        $student_id, 
                                        $notification_title, 
                                        $notification_message, 
                                        'warning'
                                    );
                                }
                            }
                            
                            $kicked_count++;
                        }
                        
                        $db->commit();
                        $message = "Successfully KICKED {$kicked_count} student(s) from their courses. All their progress data has been removed.";
                        $message_type = 'success';
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        if ($db->inTransaction()) {
                            $db->rollback();
                        }
                        $message = 'Error bulk kicking students: ' . $e->getMessage();
                        $message_type = 'danger';
                        error_log("Error bulk kicking students: " . $e->getMessage());
                    }
                }
                break;
                
            case 'unenroll_student':
                $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
                
                $stmt = $db->prepare('DELETE FROM course_enrollments WHERE id = ? AND course_id IN (SELECT id FROM courses WHERE teacher_id = ? AND academic_period_id = ?)');
                $stmt->execute([$enrollment_id, $_SESSION['user_id'], $selected_year_id]);
                $message = 'Student unenrolled successfully.';
                $message_type = 'success';
                break;
        }
    }
}

// Remove student from section
if (isset($_POST['remove_student'])) {
    $student_id = intval($_POST['student_id']);
    $section_id = intval($_POST['section_id']);
    
    // Get current students in section
    $stmt = $db->prepare("SELECT students FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $current_students = json_decode($stmt->fetchColumn(), true) ?? [];
    
    // Remove student from array
    $current_students = array_filter($current_students, function($id) use ($student_id) {
        return $id != $student_id;
    });
    
    // Update section
    $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
    $stmt->execute([json_encode($current_students), $section_id]);
    
    echo "<script>window.location.href='students.php';</script>";
    exit;
}

// Get filters
$course_filter = (int)($_GET['course'] ?? 0);
$section_filter = (int)($_GET['section'] ?? 0);
$search_filter = sanitizeInput($_GET['search'] ?? '');
$sort_by = sanitizeInput($_GET['sort'] ?? 'name');

// Get teacher's courses for selected academic period
// If a section is selected, only show courses that have that section assigned
if ($section_filter > 0) {
    $stmt = $db->prepare('
        SELECT DISTINCT c.id, c.course_name, c.course_code 
        FROM courses c 
        WHERE c.teacher_id = ? 
        AND c.academic_period_id = ? 
        AND JSON_SEARCH(c.sections, "one", ?) IS NOT NULL
        ORDER BY c.course_name
    ');
    $stmt->execute([$_SESSION['user_id'], $selected_year_id, $section_filter]);
} else {
    $stmt = $db->prepare('SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? AND academic_period_id = ? ORDER BY course_name');
    $stmt->execute([$_SESSION['user_id'], $selected_year_id]);
}
$courses = $stmt->fetchAll();

// Get sections that are assigned to teacher's courses for selected academic period
// If a course is selected, only show sections assigned to that course
if ($course_filter > 0) {
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.section_name, s.year_level
        FROM sections s
        JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        WHERE s.is_active = 1 
        AND c.teacher_id = ? 
        AND c.academic_period_id = ?
        AND c.id = ?
        ORDER BY s.year_level, s.section_name
    ");
    $stmt->execute([$_SESSION['user_id'], $selected_year_id, $course_filter]);
} else {
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.section_name, s.year_level
        FROM sections s
        JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        WHERE s.is_active = 1 
        AND c.teacher_id = ? 
        AND c.academic_period_id = ?
        ORDER BY s.year_level, s.section_name
    ");
    $stmt->execute([$_SESSION['user_id'], $selected_year_id]);
}
$sections = $stmt->fetchAll();

// Get students from teacher's sections with filters
$where_conditions = [];
$params = [];

if ($course_filter > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

if ($section_filter > 0) {
    $where_conditions[] = "s.id = ?";
    $params[] = $section_filter;
}

if (!empty($search_filter)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.identifier LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_term = "%{$search_filter}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : '';

// Add option to show only enrolled students
$show_enrolled_only = isset($_GET['enrolled_only']) && $_GET['enrolled_only'] === '1';
$having_clause = $show_enrolled_only ? "HAVING COUNT(DISTINCT e.id) > 0" : "";

// Debug: Log the query and parameters for troubleshooting
error_log("Students Query Debug:");
error_log("Course Filter: " . $course_filter);
error_log("Section Filter: " . $section_filter);
error_log("Search Filter: " . $search_filter);
error_log("Enrolled Only: " . ($show_enrolled_only ? 'Yes' : 'No'));
error_log("Where conditions: " . implode(' AND ', $where_conditions));
error_log("Parameters: " . json_encode($params));
error_log("Having clause: " . $having_clause);

// Debug: Check if there are any enrollments for this course
if ($course_filter > 0) {
    $debug_stmt = $db->prepare("SELECT COUNT(*) as count FROM course_enrollments WHERE course_id = ?");
    $debug_stmt->execute([$course_filter]);
    $enrollment_count = $debug_stmt->fetch()['count'];
    error_log("Enrollments in course $course_filter: $enrollment_count");
}

// If filtering by course, show students from sections assigned to that course
if ($course_filter > 0) {
    $stmt = $db->prepare("
        SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at as user_created, u.identifier as neust_student_id,
               GROUP_CONCAT(DISTINCT s.section_name ORDER BY s.section_name SEPARATOR ', ') as section_names,
               GROUP_CONCAT(DISTINCT s.year_level ORDER BY s.year_level SEPARATOR ', ') as section_years,
               1 as total_courses,
               COUNT(DISTINCT e.id) as enrolled_courses,
               MAX(e.enrolled_at) as latest_enrollment,
               AVG(e.progress_percentage) as avg_progress,
               MAX(e.last_accessed) as last_activity,
               CASE 
                   WHEN COUNT(DISTINCT e.id) > 0 THEN 'Regular'
                   ELSE 'Irregular'
               END as student_status,
               
               -- Assessment Statistics for this specific course
               COALESCE(SUM(assessment_stats.total_assessments), 0) as total_assessments,
               COALESCE(SUM(assessment_stats.completed_assessments), 0) as completed_assessments,
               COALESCE(AVG(assessment_stats.avg_score), 0) as avg_score,
               COALESCE(MAX(assessment_stats.best_score), 0) as best_score,
               COALESCE(SUM(assessment_stats.total_attempts), 0) as total_attempts
               
        FROM sections s
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL 
            AND c.id = ? AND c.teacher_id = ? AND c.academic_period_id = ?
        LEFT JOIN course_enrollments e ON e.student_id = u.id AND e.course_id = c.id
        
        -- Assessment Statistics Subquery for this specific course
        LEFT JOIN (
            SELECT 
                aa.student_id,
                COUNT(DISTINCT aa.assessment_id) as total_assessments,
                COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.assessment_id END) as completed_assessments,
                ROUND(AVG(aa.score), 2) as avg_score,
                MAX(aa.score) as best_score,
                COUNT(*) as total_attempts
            FROM assessment_attempts aa
            WHERE aa.assessment_id IN (
                SELECT JSON_UNQUOTE(JSON_EXTRACT(c.modules, CONCAT('$[', numbers.n, ']')))
                FROM courses c
                CROSS JOIN (
                    SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
                ) numbers
                WHERE c.id = ? AND JSON_UNQUOTE(JSON_EXTRACT(c.modules, CONCAT('$[', numbers.n, ']'))) IS NOT NULL
            )
            GROUP BY aa.student_id
        ) assessment_stats ON assessment_stats.student_id = u.id
        
        WHERE s.is_active = 1
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at, u.identifier
        " . $having_clause . "
        ORDER BY " . getSortClause($sort_by) . "
    ");
    $stmt->execute([$course_filter, $_SESSION['user_id'], $selected_year_id, $course_filter]);
} else {
    // Original query for when not filtering by course
    $stmt = $db->prepare("
        SELECT u.id as student_id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at as user_created, u.identifier as neust_student_id,
               GROUP_CONCAT(DISTINCT s.section_name ORDER BY s.section_name SEPARATOR ', ') as section_names,
               GROUP_CONCAT(DISTINCT s.year_level ORDER BY s.year_level SEPARATOR ', ') as section_years,
               COUNT(DISTINCT c.id) as total_courses,
               COUNT(DISTINCT e.id) as enrolled_courses,
               MAX(e.enrolled_at) as latest_enrollment,
               AVG(e.progress_percentage) as avg_progress,
               MAX(e.last_accessed) as last_activity,
               CASE 
                   WHEN COUNT(DISTINCT e.id) = COUNT(DISTINCT c.id) THEN 'Regular'
                   WHEN COUNT(DISTINCT e.id) > 0 THEN 'Irregular'
                   ELSE 'Irregular'
               END as student_status,
               
               -- Overall Assessment Statistics
               COALESCE(SUM(assessment_stats.total_assessments), 0) as total_assessments,
               COALESCE(SUM(assessment_stats.completed_assessments), 0) as completed_assessments,
               COALESCE(AVG(assessment_stats.avg_score), 0) as avg_score,
               COALESCE(MAX(assessment_stats.best_score), 0) as best_score,
               COALESCE(SUM(assessment_stats.total_attempts), 0) as total_attempts
               
        FROM sections s
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL 
            AND c.teacher_id = ? AND c.academic_period_id = ?
        LEFT JOIN course_enrollments e ON e.student_id = u.id AND e.course_id = c.id
        
        -- Assessment Statistics Subquery
        LEFT JOIN (
            SELECT 
                aa.student_id,
                c.id as course_id,
                COUNT(DISTINCT aa.assessment_id) as total_assessments,
                COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.assessment_id END) as completed_assessments,
                ROUND(AVG(aa.score), 2) as avg_score,
                MAX(aa.score) as best_score,
                COUNT(*) as total_attempts
            FROM assessment_attempts aa
            JOIN courses c ON JSON_SEARCH(c.modules, 'one', aa.assessment_id) IS NOT NULL
            WHERE c.teacher_id = ? AND c.academic_period_id = ?
            GROUP BY aa.student_id, c.id
        ) assessment_stats ON assessment_stats.student_id = u.id AND assessment_stats.course_id = c.id
        
        WHERE s.is_active = 1
        " . ($where_clause ? "AND " . $where_clause : "") . "
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at, u.identifier
        " . $having_clause . "
        ORDER BY " . getSortClause($sort_by) . "
    ");
    $stmt->execute(array_merge([$_SESSION['user_id'], $selected_year_id, $_SESSION['user_id'], $selected_year_id], $params));
}
$course_enrollments = $stmt->fetchAll();

// Get available students for enrollment (for courses in selected academic period)
$stmt = $db->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email
    FROM users u
    WHERE u.role = 'student' AND u.id NOT IN (
        SELECT student_id FROM course_enrollments WHERE course_id IN (SELECT id FROM courses WHERE teacher_id = ? AND academic_period_id = ?)
    )
    ORDER BY u.first_name, u.last_name
");
$stmt->execute([$_SESSION['user_id'], $selected_year_id]);
$available_students = $stmt->fetchAll();

function getRandomIconClass($userId) {
    $icons = [
        'bi-person', 'bi-person-circle', 'bi-person-badge', 'bi-person-fill', 'bi-emoji-smile', 'bi-people', 'bi-person-lines-fill', 'bi-person-video', 'bi-person-check', 'bi-person-gear'
    ];
    return $icons[$userId % count($icons)];
}
function getRandomBgClass($userId) {
    $colors = [
        'bg-success', 'bg-warning', 'bg-danger', 'bg-info', 'bg-secondary', 'bg-dark', 'bg-green', 'bg-teal', 'bg-orange'
    ];
    return $colors[$userId % count($colors)];
}

function getSortClause($sort_by) {
    switch ($sort_by) {
        case 'name':
            return 'u.last_name ASC, u.first_name ASC';
        case 'course':
            return 'c.course_name ASC';
        case 'enrolled':
            return 'e.enrolled_at DESC';
        case 'progress':
            return 'e.progress_percentage DESC';
        case 'score':
            return 'assessment_stats.avg_score DESC';
        case 'activity':
            return 'e.last_accessed DESC';
        default:
            return 'u.last_name ASC, u.first_name ASC';
    }
}
?>

<!-- Modern Students Management Header -->
<div class="students-management-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-3">
                    <i class="bi bi-people me-3"></i>Students Management
                </h1>
                <p class="mb-0 opacity-90">Manage and monitor your students across all courses and sections.</p>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="student-stats">
                        <div class="student-stat-item">
                            <span class="student-stat-number"><?php echo count($course_enrollments); ?></span>
                            <span class="student-stat-label">Total Students</span>
                        </div>
                        <div class="student-stat-item">
                            <span class="student-stat-number"><?php echo count($courses); ?></span>
                            <span class="student-stat-label">Courses</span>
                        </div>
                        <div class="student-stat-item">
                            <span class="student-stat-number"><?php echo count($sections); ?></span>
                            <span class="student-stat-label">Sections</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

<div class="container-fluid">

    <!-- Academic Year Selection -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
            <form method="get" class="d-flex align-items-center">
                        <label for="academic_period_id" class="me-3 fw-semibold text-primary">
                            <i class="bi bi-calendar3 me-1"></i>Academic Period:
                        </label>
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
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Debug Information (remove in production) -->
    <?php if (isset($_GET['debug'])): ?>
        <div class="alert alert-info">
            <strong>Debug Info:</strong><br>
            Course Filter: <?php echo $course_filter ?: 'None'; ?><br>
            Section Filter: <?php echo $section_filter ?: 'None'; ?><br>
            Search Filter: <?php echo $search_filter ?: 'None'; ?><br>
            Enrolled Only: <?php echo $show_enrolled_only ? 'Yes' : 'No'; ?><br>
            Sort By: <?php echo $sort_by; ?><br>
            Where Clause: <?php echo $where_clause ?: 'None'; ?><br>
            Having Clause: <?php echo $having_clause ?: 'None'; ?><br>
            Total Students Found: <?php echo count($course_enrollments); ?><br><br>
            
            <strong>Student Status Breakdown:</strong><br>
            <?php 
            $regular_count = 0;
            $irregular_count = 0;
            foreach ($course_enrollments as $student) {
                if ($student['student_status'] === 'Regular') {
                    $regular_count++;
                } else {
                    $irregular_count++;
                }
            }
            echo "Regular: $regular_count students<br>";
            echo "Irregular: $irregular_count students<br><br>";
            ?>
            
            <strong>Sample Student Data:</strong><br>
            <?php 
            $sample_count = 0;
            foreach ($course_enrollments as $student) {
                if ($sample_count >= 3) break;
                echo "Student: " . $student['first_name'] . " " . $student['last_name'] . 
                     " | Enrolled: " . $student['enrolled_courses'] . 
                     " | Total: " . $student['total_courses'] . 
                     " | Status: " . $student['student_status'] . "<br>";
                $sample_count++;
            }
            ?>
            <br>
            
            <strong>Available Sections (assigned to your courses):</strong><br>
            <?php foreach ($sections as $section): ?>
                <?php 
                // Count students in this section for debug
                $debug_stmt = $db->prepare("
                    SELECT COUNT(*) as count
                    FROM sections s
                    JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
                    JOIN courses c ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL 
                        AND c.teacher_id = ? AND c.academic_period_id = ?
                    WHERE s.is_active = 1 AND s.id = ?
                ");
                $debug_stmt->execute([$_SESSION['user_id'], $selected_year_id, $section['id']]);
                $debug_count = $debug_stmt->fetch()['count'];
                ?>
                BSIT-<?php echo $section['year_level'] . $section['section_name']; ?> (ID: <?php echo $section['id']; ?>) - <?php echo $debug_count; ?> students<br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Enhanced Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card filter-card border-0">
                <div class="card-header filter-header">
                    <h6 class="mb-0">
                        <i class="bi bi-funnel me-2"></i>Filter & Search
                    </h6>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <input type="hidden" name="academic_period_id" value="<?= $selected_year_id ?>">
                        <div class="col-md-3">
                            <label for="course" class="form-label fw-semibold">
                                <i class="bi bi-book me-1"></i>Filter by Course
                            </label>
                            <select class="form-select" id="course" name="course" onchange="updateSectionsAndSubmit()">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="section" class="form-label fw-semibold">
                                <i class="bi bi-collection me-1"></i>Filter by Section
                            </label>
                            <select class="form-select" id="section" name="section" onchange="updateCoursesAndSubmit()">
                                <option value="">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['id']; ?>" 
                                            <?php echo $section_filter == $section['id'] ? 'selected' : ''; ?>>
                                        BSIT-<?php echo htmlspecialchars($section['year_level'] . $section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label fw-semibold">
                                <i class="bi bi-search me-1"></i>Search Students
                            </label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Name or Student ID..." 
                                       value="<?php echo htmlspecialchars($search_filter); ?>"
                                       onkeyup="handleSearchInput()">
                            </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-eye me-1"></i>View Options
                            </label>
                            <div class="d-grid gap-2">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['enrolled_only' => $show_enrolled_only ? '0' : '1'])); ?>" 
                                   class="btn <?php echo $show_enrolled_only ? 'btn-success' : 'btn-outline-success'; ?>">
                                    <i class="bi bi-<?php echo $show_enrolled_only ? 'eye-fill' : 'eye'; ?> me-1"></i>
                                    <?php echo $show_enrolled_only ? 'Show All' : 'Enrolled Only'; ?>
                                </a>
                                <a href="students.php?academic_period_id=<?= $selected_year_id ?>&enrolled_only=0" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                                </a>
                            </div>
                        </div>
                    </form>
                            </div>
                        </div>
                </div>
            </div>

    <!-- Bulk Actions Bar -->
    <div class="row mb-3" id="bulkActionsBar" style="display: none;">
        <div class="col-12">
            <div class="bulk-actions-bar">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="me-3 fw-semibold">
                            <i class="bi bi-check-square me-1"></i>
                            <span id="selectedCount">0</span> students selected
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light btn-sm" id="selectAllBtn">
                            <i class="bi bi-check-all me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-light btn-sm" id="deselectAllBtn">
                            <i class="bi bi-x-square me-1"></i>Deselect All
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="bulkKickBtn">
                            <i class="bi bi-person-x-fill me-1"></i>Kick Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Statistics Cards -->
    <div class="row mb-4 students-stats">
        <div class="col-md-3">
            <div class="card stats-card stats-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="stats-content">
                            <h3 class="stats-number total-students"><?php echo count($course_enrollments); ?></h3>
                            <p class="stats-label mb-0">Total Students</p>
                            <small class="stats-subtitle">All enrolled students</small>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card stats-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="stats-content">
                            <h3 class="stats-number active-students">
                                <?php 
                                $active_students = array_filter($course_enrollments, function($e) { 
                                    return ($e['enrolled_courses'] ?? 0) > 0; 
                                });
                                echo count($active_students);
                                ?>
                            </h3>
                            <p class="stats-label mb-0">Active Students</p>
                            <small class="stats-subtitle">Currently enrolled</small>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-person-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card stats-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="stats-content">
                            <h3 class="stats-number avg-progress">
                                <?php 
                                $progress_values = array_filter(array_column($course_enrollments, 'avg_progress'), function($val) {
                                    return $val !== null && $val !== '';
                                });
                                $avg_progress = count($progress_values) > 0 ? 
                                    array_sum($progress_values) / count($progress_values) : 0;
                                echo number_format($avg_progress, 1);
                                ?>%
                            </h3>
                            <p class="stats-label mb-0">Avg Progress</p>
                            <small class="stats-subtitle">Course completion</small>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card stats-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="stats-content">
                            <h3 class="stats-number avg-score">
                                <?php 
                                $score_values = array_filter(array_column($course_enrollments, 'avg_score'), function($val) {
                                    return $val !== null && $val !== '' && $val > 0;
                                });
                                $avg_score = count($score_values) > 0 ? 
                                    array_sum($score_values) / count($score_values) : 0;
                                echo number_format($avg_score, 1);
                                ?>%
                            </h3>
                            <p class="stats-label mb-0">Avg Score</p>
                            <small class="stats-subtitle">Assessment performance</small>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-award"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students List -->
    <div class="row">
        <div class="col-12">
            <div class="card students-card border-0 shadow-sm">
                <div class="card-header students-table-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                    <h5 class="mb-0">
                                <i class="bi bi-people me-2"></i>Students from My Sections
                                <span class="badge bg-primary ms-2"><?php echo count($course_enrollments); ?></span>
                            </h5>
                        <?php if (!empty($search_filter)): ?>
                                <span class="badge bg-info mt-1">
                                <i class="bi bi-search me-1"></i>Search: "<?php echo htmlspecialchars($search_filter); ?>"
                            </span>
                        <?php endif; ?>
                        <?php if ($show_enrolled_only): ?>
                                <span class="badge bg-success mt-1">
                                <i class="bi bi-eye-fill me-1"></i>Enrolled Only
                            </span>
                        <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                        <div id="updateIndicator" class="me-2" style="display: none;">
                            <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                            <small class="text-muted ms-1">Updating...</small>
                        </div>
                        <small class="text-muted" id="lastUpdate">
                            <i class="bi bi-clock me-1"></i>Last updated: Just now
                        </small>
                        <small class="text-success ms-2" id="realtimeStatus" style="display: none;">
                            <i class="bi bi-broadcast me-1"></i>Live Updates Active
                        </small>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateProgressData()" title="Refresh Progress">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                            <button type="button" class="btn btn-sm btn-success" id="exportStatsBtn" onclick="exportStudentStats()" title="Export Student Statistics">
                            <i class="bi bi-download me-1"></i>Export Stats
                        </button>
                            <button type="button" class="btn btn-sm btn-info" id="exportAssessmentsBtn" onclick="exportAssessmentDetails()" title="Export Detailed Assessment Data">
                            <i class="bi bi-file-earmark-text me-1"></i>Export Assessments
                        </button>
                    </div>
                </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($course_enrollments)): ?>
                        <div class="empty-state text-center py-5">
                            <div class="empty-state-content">
                                <i class="bi bi-people display-1 text-muted mb-4"></i>
                                <h4 class="text-muted mb-3">No Students Found</h4>
                                <p class="text-muted mb-4">No students assigned to your sections for the selected academic year. Students will appear here once they are assigned to your sections.</p>
                                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#enrollStudentModal">
                                    <i class="bi bi-person-plus me-2"></i>Enroll Student
                            </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Scrollable Students Table Container -->
                        <div class="students-table-scrollable-container">
                            <div class="table-responsive">
                                <table class="table table-hover students-table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="5%">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                            <th width="20%">
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name'])); ?>" class="text-decoration-none text-white">
                                                    <i class="bi bi-person me-1"></i>Student <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                            <th width="10%">Student ID</th>
                                            <th width="15%">
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'course'])); ?>" class="text-decoration-none text-white">
                                                    <i class="bi bi-book me-1"></i>Courses <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                            <th width="10%">Sections</th>
                                            <th width="10%">
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'progress'])); ?>" class="text-decoration-none text-white">
                                                    <i class="bi bi-graph-up me-1"></i>Progress <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                            <th width="8%">Assessments</th>
                                            <th width="8%">
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'score'])); ?>" class="text-decoration-none text-white">
                                                    <i class="bi bi-award me-1"></i>Avg % <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                            <th width="10%">
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'activity'])); ?>" class="text-decoration-none text-white">
                                                    <i class="bi bi-clock me-1"></i>Last Activity <i class="bi bi-arrow-down-up"></i>
                                            </a>
                                        </th>
                                            <th width="8%">Status</th>
                                            <th width="6%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_enrollments as $student): ?>
                                        <tr data-student-id="<?php echo $student['student_id']; ?>" class="student-row" style="cursor: pointer;" onclick="showStudentCourses(<?php echo $student['student_id']; ?>)">
                                            <td>
                                                <input type="checkbox" class="form-check-input student-checkbox" value="<?php echo $student['student_id']; ?>" onclick="event.stopPropagation();">
                                            </td>
                                            <td>
                                                <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'medium'); ?>" class="profile-picture me-2" alt="Student" style="width: 48px; height: 48px; object-fit: cover;">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($student['neust_student_id'])): ?>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($student['neust_student_id']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">No ID</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php echo $student['enrolled_courses']; ?> / <?php echo $student['total_courses']; ?> courses
                                                </div>
                                                <small class="text-muted">Click to view details</small>
                                            </td>
                                            <td>
                                                <?php if ($student['section_names'] && $student['section_names'] !== 'Not Assigned'): ?>
                                                    <?php 
                                                    $sections = explode(', ', $student['section_names']);
                                                    $years = explode(', ', $student['section_years']);
                                                    for ($i = 0; $i < count($sections); $i++): 
                                                        $year = isset($years[$i]) ? $years[$i] : 'N/A';
                                                    ?>
                                                        <span class="badge bg-light text-dark me-1 mb-1" style="font-size:0.85em; border-radius:1em; border:1px solid #e5e7eb; color:var(--main-green);">
                                                            BSIT-<?php echo htmlspecialchars($year . $sections[$i]); ?>
                                                    </span>
                                                    <?php endfor; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php 
                                                    $progress_percentage = $student['avg_progress'] ?? 0;
                                                    $progress_percentage = max(0, min(100, $progress_percentage)); // Ensure between 0-100
                                                    $progress_color = $progress_percentage >= 80 ? 'bg-success' : 
                                                                     ($progress_percentage >= 60 ? 'bg-warning' : 
                                                                     ($progress_percentage >= 40 ? 'bg-info' : 'bg-danger'));
                                                    ?>
                                                    <div class="progress me-2" style="width: 60px; height: 6px;">
                                                        <div class="progress-bar <?php echo $progress_color; ?>" style="width: <?php echo $progress_percentage; ?>%"></div>
                                                    </div>
                                                    <small class="fw-bold progress-text"><?php echo number_format($progress_percentage, 1); ?>%</small>
                                                </div>
                                                <small class="text-muted">
                                                    Avg Progress
                                                </small>
                                            </td>
                                            <td>
                                                <?php 
                                                $completed_assessments = (int)($student['completed_assessments'] ?? 0);
                                                $total_assessments = (int)($student['total_assessments'] ?? 0);
                                                $assessment_color = $completed_assessments == $total_assessments && $total_assessments > 0 ? 'bg-success' : 
                                                                   ($completed_assessments > 0 ? 'bg-warning' : 'bg-secondary');
                                                ?>
                                                <span class="badge <?php echo $assessment_color; ?> assessment-badge">
                                                    <i class="bi bi-file-text me-1"></i><?php echo $completed_assessments; ?>/<?php echo $total_assessments; ?>
                                                </span>
                                                <?php if ($student['total_attempts'] > 0): ?>
                                                    <small class="text-muted d-block">
                                                        <?php echo $student['total_attempts']; ?> attempts
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $avg_score = $student['avg_score'] ?? 0;
                                                $avg_score = max(0, min(100, $avg_score)); // Ensure between 0-100
                                                $best_score = $student['best_score'] ?? 0;
                                                $best_score = max(0, min(100, $best_score)); // Ensure between 0-100
                                                $score_color = $avg_score >= 80 ? 'bg-success' : 
                                                              ($avg_score >= 70 ? 'bg-warning' : 
                                                              ($avg_score >= 50 ? 'bg-info' : 'bg-danger'));
                                                ?>
                                                <span class="badge <?php echo $score_color; ?> score-badge clickable-score" 
                                                      data-student-id="<?php echo $student['student_id']; ?>"
                                                      data-course-id="<?php echo $course_filter; ?>"
                                                      data-academic-period-id="<?php echo $selected_year_id; ?>"
                                                      style="cursor: pointer;" 
                                                      title="Click to view detailed score breakdown">
                                                    <i class="bi bi-info-circle me-1"></i><?php echo number_format($avg_score, 1); ?>%
                                                </span>
                                                <?php if ($best_score > 0): ?>
                                                    <small class="text-muted d-block">
                                                        Best: <?php echo number_format($best_score, 1); ?>%
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $last_activity = $student['last_activity'] ?? null;
                                                if ($last_activity && $last_activity !== '0000-00-00 00:00:00') {
                                                    $time_ago = time() - strtotime($last_activity);
                                                    $days_ago = floor($time_ago / (24 * 60 * 60));
                                                    
                                                    if ($days_ago == 0) {
                                                        $activity_text = 'Today';
                                                        $activity_color = 'bg-success';
                                                    } elseif ($days_ago == 1) {
                                                        $activity_text = 'Yesterday';
                                                        $activity_color = 'bg-warning';
                                                    } elseif ($days_ago <= 7) {
                                                        $activity_text = $days_ago . ' days ago';
                                                        $activity_color = 'bg-info';
                                                    } else {
                                                        $activity_text = $days_ago . ' days ago';
                                                        $activity_color = 'bg-danger';
                                                    }
                                                    
                                                    echo '<span class="badge ' . $activity_color . ' activity-badge">';
                                                    echo '<i class="bi bi-clock me-1"></i>' . $activity_text;
                                                    echo '</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">No Activity</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $enrolled_courses = (int)($student['enrolled_courses'] ?? 0);
                                                $total_courses = (int)($student['total_courses'] ?? 0);
                                                
                                                if ($enrolled_courses == 0) {
                                                    $status_text = 'Not Enrolled';
                                                    $status_class = 'bg-danger';
                                                    $status_icon = 'bi-x-circle';
                                                } elseif ($enrolled_courses == $total_courses) {
                                                    $status_text = 'Regular';
                                                    $status_class = 'bg-success';
                                                    $status_icon = 'bi-check-circle';
                                                } else {
                                                    $status_text = 'Irregular';
                                                    $status_class = 'bg-warning';
                                                    $status_icon = 'bi-exclamation-triangle';
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <i class="bi <?php echo $status_icon; ?> me-1"></i><?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-outline-danger kick-student-btn" title="Kick Student from All Courses" onclick="event.stopPropagation(); kickStudentFromAllCourses(<?php echo $student['student_id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">
                                                        <i class="bi bi-person-x-fill"></i>
                                                    </button>
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
</div>

<!-- Student Courses Modal -->
<div class="modal fade" id="studentCoursesModal" tabindex="-1" aria-labelledby="studentCoursesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentCoursesModalLabel">Student Course Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="studentCoursesContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading course details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Score Details Modal -->
<div class="modal fade" id="scoreDetailsModal" tabindex="-1" aria-labelledby="scoreDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scoreDetailsModalLabel">
                    <i class="bi bi-graph-up me-2"></i>Score Breakdown & Assessment History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="scoreDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading score details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="refreshScoreDetails()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced Students Table Scrolling */
.students-table-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    position: relative;
}

/* Custom scrollbar for students table */
.students-table-container::-webkit-scrollbar {
    width: 8px;
}

.students-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.students-table-container::-webkit-scrollbar-thumb {
    background: #28a745;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.students-table-container::-webkit-scrollbar-thumb:hover {
    background: #218838;
}

/* Firefox scrollbar styling */
.students-table-container {
    scrollbar-width: thin;
    scrollbar-color: #28a745 #f1f1f1;
}

/* Enhanced table styling */
.students-table-container .table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

/* Student row hover effects */
.student-row {
    transition: all 0.3s ease;
}

.student-row:hover {
    background-color: rgba(40, 167, 69, 0.05) !important;
    transform: translateX(3px);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.1);
}

.students-table-container .table thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 16px 12px;
}

.students-table-container .table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f0f0f0;
}

.students-table-container .table tbody tr:hover {
    background-color: rgba(40, 167, 69, 0.05);
    transform: translateX(3px);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.1);
}

.students-table-container .table tbody td {
    padding: 16px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

/* Enhanced button styling */
.students-table-container .btn {
    padding: 6px 12px;
    font-size: 0.875rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    margin: 0 2px;
}

.students-table-container .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Kick student button styling */
.kick-student-btn {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.kick-student-btn:hover {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
    color: white !important;
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
}

.kick-student-btn:active {
    transform: scale(0.95);
}

.kick-student-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Loading animation for bulk kick - only when processing */
#bulkKickBtn.processing {
    position: relative;
}

#bulkKickBtn.processing::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    margin: auto;
    border: 2px solid transparent;
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Export button processing state */
#exportStatsBtn.processing {
    position: relative;
    opacity: 0.8;
}

#exportStatsBtn.processing::after {
    content: '';
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 12px;
    height: 12px;
    border: 2px solid transparent;
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Clickable score styling */
.clickable-score {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.clickable-score:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 10;
}

.clickable-score::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s ease;
}

.clickable-score:hover::after {
    left: 100%;
}

/* Score details modal styling */
.score-breakdown-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    background: #f8f9fa;
}

.score-summary {
    background: linear-gradient(135deg, #2E5E4E, #7DCB80);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.assessment-attempt-item {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    background: white;
    transition: all 0.3s ease;
}

.assessment-attempt-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

.score-trend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.score-trend.up {
    color: #28a745;
}

.score-trend.down {
    color: #dc3545;
}

.score-trend.stable {
    color: #6c757d;
}

/* Real-time score update animations */
.score-updated {
    animation: scorePulse 1s ease-in-out;
}

@keyframes scorePulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); box-shadow: 0 0 15px rgba(0,0,0,0.3); }
    100% { transform: scale(1); }
}

.score-change-indicator {
    position: absolute;
    right: -20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.8rem;
    animation: scoreChangeSlide 3s ease-out forwards;
    z-index: 10;
}

@keyframes scoreChangeSlide {
    0% { 
        opacity: 0; 
        transform: translateY(-50%) translateX(0); 
    }
    20% { 
        opacity: 1; 
        transform: translateY(-50%) translateX(-5px); 
    }
    80% { 
        opacity: 1; 
        transform: translateY(-50%) translateX(-5px); 
    }
    100% { 
        opacity: 0; 
        transform: translateY(-50%) translateX(-10px); 
    }
}

/* Enhanced clickable score for real-time updates */
.clickable-score {
    position: relative;
    transition: all 0.3s ease;
}

.clickable-score:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 10;
}

/* Real-time update indicator */
.realtime-indicator {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    z-index: 9999;
    animation: realtimeIndicatorSlide 3s ease-out forwards;
    display: none;
}

@keyframes realtimeIndicatorSlide {
    0% { 
        opacity: 0; 
        transform: translateX(100%); 
    }
    20% { 
        opacity: 1; 
        transform: translateX(0); 
    }
    80% { 
        opacity: 1; 
        transform: translateX(0); 
    }
    100% { 
        opacity: 0; 
        transform: translateX(100%); 
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Badge enhancements */
.students-table-container .badge {
    font-size: 0.75rem;
    padding: 6px 10px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.students-table-container .badge:hover {
    transform: scale(1.05);
}

/* Student profile picture styling */
.students-table-container .table tbody td .profile-picture {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.students-table-container .table tbody tr:hover .profile-picture {
    transform: scale(1.1);
    border-color: #28a745;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

/* Student name styling */
.students-table-container .table tbody td .fw-bold {
    transition: all 0.3s ease;
}

.students-table-container .table tbody tr:hover .fw-bold {
    color: #28a745;
    transform: translateX(2px);
}

/* Progress bar enhancements */
.students-table-container .progress {
    transition: all 0.3s ease;
}

.students-table-container .table tbody tr:hover .progress {
    transform: scale(1.05);
}

/* Scroll indicators for students table */
.students-scroll-indicator {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 15;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.students-scroll-indicator.show {
    opacity: 1;
}

.students-scroll-indicator-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.students-scroll-indicator i {
    background: rgba(40, 167, 69, 0.8);
    color: white;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.students-scroll-indicator-top.hide,
.students-scroll-indicator-bottom.hide {
    opacity: 0.3;
}

/* Card enhancements */
.students-card {
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
}

.students-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    padding: 16px 20px;
}

.students-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #495057;
}

/* Statistics cards enhancements */
.students-stats .card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.students-stats .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.students-stats .card .card-body {
    padding: 20px;
}

.students-stats .card i {
    transition: transform 0.3s ease;
}

.students-stats .card:hover i {
    transform: scale(1.1);
}

/* Mobile responsiveness for students table */
@media (max-width: 991.98px) {
    .students-table-container {
        max-height: 450px;
    }
    
    .students-table-container .table thead th,
    .students-table-container .table tbody td {
        padding: 12px 8px;
        font-size: 0.9rem;
    }
}

@media (max-width: 575.98px) {
    .students-table-container {
        max-height: 350px;
    }
    
    .students-table-container .table thead th,
    .students-table-container .table tbody td {
        padding: 8px 4px;
        font-size: 0.85rem;
    }
    
    .students-table-container .btn {
        padding: 4px 8px;
        font-size: 0.75rem;
    }
    
    .students-table-container .table tbody td .profile-picture {
        width: 36px !important;
        height: 36px !important;
    }
}

/* Loading and animation states */
.students-table-loading {
    opacity: 0.6;
    pointer-events: none;
}

.student-row-enter {
    animation: studentRowEnter 0.5s ease-out;
}

@keyframes studentRowEnter {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.student-row-exit {
    animation: studentRowExit 0.5s ease-in;
}

@keyframes studentRowExit {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(-100%);
    }
}

/* Enhanced modal styling */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}

.modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    border-radius: 12px 12px 0 0;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 12px 12px;
}

/* Form enhancements */
.form-control:focus, .form-select:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* Filter loading states */
.form-select.loading {
    opacity: 0.7;
    pointer-events: none;
}

.form-select.loading::after {
    content: '';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #28a745;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Search input styling */
#search {
    transition: all 0.3s ease;
}

#search:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

#search.loading {
    opacity: 0.7;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='%2328a745' d='M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0zM4.5 7.5a.5.5 0 0 0 0 1h7a.5.5 0 0 0 0-1h-7z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 16px;
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0% { opacity: 0.7; }
    50% { opacity: 1; }
    100% { opacity: 0.7; }
}

@keyframes spin {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}

.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

/* Modern Students Management Styles - Matching Modules Design */

/* Students Management Header */
.students-management-header {
    background: linear-gradient(135deg, #1e3a2e 0%, #2d5a3d 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.students-management-header h1 {
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.students-management-header .opacity-90 {
    opacity: 0.9;
}

.student-stats {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.student-stat-item {
    text-align: center;
}

.student-stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.student-stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filter Card Styles */
.filter-card {
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.filter-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    border-radius: 12px 12px 0 0;
}

.filter-card .form-label {
    color: #495057;
    font-weight: 600;
}

.filter-card .form-control,
.filter-card .form-select {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: #2E5E4E;
    box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25);
}

/* Statistics Cards */
.stats-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stats-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
}

.stats-success {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
}

.stats-warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #212529;
}

.stats-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
}

.stats-number {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stats-label {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.stats-subtitle {
    font-size: 0.875rem;
    opacity: 0.8;
}

.stats-icon {
    font-size: 3rem;
    opacity: 0.3;
}

/* Students Table Styles */
.students-table-scrollable-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: hidden;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    background: white;
}

.students-table-scrollable-container::-webkit-scrollbar {
    width: 8px;
}

.students-table-scrollable-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.students-table-scrollable-container::-webkit-scrollbar-thumb {
    background: #2E5E4E;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.students-table-scrollable-container::-webkit-scrollbar-thumb:hover {
    background: #1e3d32;
}

.students-table-scrollable-container {
    scrollbar-width: thin;
    scrollbar-color: #2E5E4E #f1f1f1;
}

.students-table-scrollable-container .table thead th {
    position: sticky;
    top: 0;
    background: #2E5E4E !important;
    z-index: 10;
    border-bottom: 2px solid #1e3d32;
}

.students-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: none;
    margin-bottom: 0;
}

.students-table thead th {
    background: #2E5E4E !important;
    color: white;
    font-weight: 600;
    border: none;
    padding: 1rem 0.75rem;
    vertical-align: middle;
    font-size: 0.9rem;
}

.students-table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f0f0f0;
}

.students-table tbody tr:hover {
    background-color: rgba(46, 94, 78, 0.05);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.students-table tbody td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border: none;
    font-size: 0.9rem;
}

.students-table-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 1.25rem 1.5rem;
}

/* Empty State */
.empty-state {
    padding: 3rem 2rem;
    text-align: center;
}

.empty-state-content {
    max-width: 400px;
    margin: 0 auto;
}

.empty-state i {
    color: #6c757d;
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: #495057;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 2rem;
}

/* Bulk Actions Bar */
.bulk-actions-bar {
    background: #2E5E4E;
    color: white;
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .students-table thead th:nth-child(8),
    .students-table tbody td:nth-child(8),
    .students-table thead th:nth-child(9),
    .students-table tbody td:nth-child(9) {
        display: none;
    }
}

@media (max-width: 992px) {
    .students-table thead th:nth-child(10),
    .students-table tbody td:nth-child(10) {
        display: none;
    }
}

@media (max-width: 768px) {
    .students-table-scrollable-container {
        max-height: 500px;
    }
    
    .students-table {
        font-size: 0.875rem;
    }
    
    .students-table thead th,
    .students-table tbody td {
        padding: 0.75rem 0.5rem;
    }
    
    .students-table thead th:nth-child(3),
    .students-table tbody td:nth-child(3),
    .students-table thead th:nth-child(4),
    .students-table tbody td:nth-child(4) {
        display: none;
    }
}

@media (max-width: 576px) {
    .students-table-scrollable-container {
        max-height: 400px;
    }
    
    .students-table thead th,
    .students-table tbody td {
        padding: 0.5rem 0.25rem;
        font-size: 0.8rem;
    }
    
    .student-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .students-management-header {
        padding: 1.5rem 0;
    }
}
</style>

<!-- Enroll Student Modal -->
<div class="modal fade" id="enrollStudentModal" tabindex="-1" aria-labelledby="enrollStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="enrollStudentModalLabel">Enroll Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="enroll_student">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Select Student</label>
                        <select class="form-select" id="student_id" name="student_id" required>
                            <option value="">Choose a student...</option>
                            <?php foreach ($available_students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Select Course</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Choose a course...</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Bulk selection functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateBulkKickButton();
});

document.querySelectorAll('.student-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkKickButton);
});

function updateBulkKickButton() {
    const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
    const bulkKickBtn = document.getElementById('bulkKickBtn');
    
    if (selectedCheckboxes.length > 0) {
        bulkKickBtn.disabled = false;
        bulkKickBtn.classList.remove('processing');
        bulkKickBtn.textContent = `Kick Selected (${selectedCheckboxes.length})`;
    } else {
        bulkKickBtn.disabled = true;
        bulkKickBtn.classList.remove('processing');
        bulkKickBtn.innerHTML = '<i class="bi bi-person-x-fill"></i> Kick Selected';
    }
}

// Enhanced kick student confirmation
function confirmKickStudent(studentName, courseName) {
    const confirmMessage = `Are you sure you want to KICK "${studentName}" from "${courseName}"?\n\nThis action will:\n• Remove the student from the course\n• Delete ALL their progress data\n• Remove assessment attempts\n• Remove video views\n• Remove module progress\n\nThis action CANNOT be undone!`;
    
    return confirm(confirmMessage);
}

// Bulk kick functionality
document.getElementById('bulkKickBtn').addEventListener('click', function() {
    const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
    if (selectedCheckboxes.length === 0) return;
    
    const confirmMessage = `Are you sure you want to KICK ${selectedCheckboxes.length} student(s) from their courses?\n\nThis action will:\n• Remove students from all their courses\n• Delete ALL their progress data\n• Remove assessment attempts\n• Remove video views\n• Remove module progress\n\nThis action CANNOT be undone!`;
    
    if (confirm(confirmMessage)) {
        // Show loading state
        this.disabled = true;
        this.classList.add('processing');
        this.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="bulk_kick_students">
            ${Array.from(selectedCheckboxes).map(checkbox => 
                `<input type="hidden" name="student_ids[]" value="${checkbox.value}">`
            ).join('')}
        `;
        document.body.appendChild(form);
        
        // Add a timeout to reset the button if form submission takes too long
        setTimeout(() => {
            const bulkKickBtn = document.getElementById('bulkKickBtn');
            if (bulkKickBtn && bulkKickBtn.classList.contains('processing')) {
                bulkKickBtn.disabled = false;
                bulkKickBtn.classList.remove('processing');
                bulkKickBtn.innerHTML = '<i class="bi bi-person-x-fill"></i> Kick Selected';
            }
        }, 10000); // 10 second timeout
        
        form.submit();
    }
});

// Real-time progress and score updates
let progressUpdateInterval;
let isPageVisible = true;
let lastScoreData = {};

// Function to update progress and score data
function updateProgressData() {
    if (!isPageVisible) return;
    
    // Show update indicator
    const updateIndicator = document.getElementById('updateIndicator');
    const lastUpdate = document.getElementById('lastUpdate');
    
    if (updateIndicator) updateIndicator.style.display = 'block';
    
    const url = 'ajax_get_realtime_scores.php?' + new URLSearchParams({
        academic_period_id: '<?php echo $selected_year_id; ?>',
        course: '<?php echo $course_filter; ?>',
        section: '<?php echo $section_filter; ?>',
        search: '<?php echo $search_filter; ?>',
        enrolled_only: '<?php echo $show_enrolled_only ? '1' : '0'; ?>'
    });
    
    console.log('Fetching real-time data from:', url);
    
    fetch(url)
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Received data:', data);
        if (data.success) {
            updateProgressTable(data.students);
            updateSummaryStats(data.summary);
            updateScoreData(data.students);
            
            // Update last update time
            if (lastUpdate) {
                const now = new Date();
                lastUpdate.innerHTML = `<i class="bi bi-clock me-1"></i>Last updated: ${now.toLocaleTimeString()}`;
            }
        } else {
            console.error('API returned error:', data.error);
        }
    })
    .catch(error => {
        console.error('Error updating progress:', error);
    })
    .finally(() => {
        // Hide update indicator
        if (updateIndicator) updateIndicator.style.display = 'none';
    });
}

// Function to update progress table
function updateProgressTable(students) {
    console.log('Updating progress table with', students.length, 'students');
    students.forEach(student => {
        const row = document.querySelector(`tr[data-student-id="${student.student_id}"]`);
        console.log('Looking for student ID:', student.student_id, 'Found row:', row);
        
        if (row) {
            // Update progress bar
            const progressBar = row.querySelector('.progress-bar');
            const progressText = row.querySelector('.progress-text');
            const progressPercentage = parseFloat(student.course_progress) || 0;
            
            console.log('Student', student.student_id, 'Progress:', progressPercentage, 'Found elements:', {
                progressBar: !!progressBar,
                progressText: !!progressText
            });
            
            if (progressBar && progressText) {
                progressBar.style.width = Math.min(progressPercentage, 100) + '%';
                progressText.textContent = progressPercentage.toFixed(1) + '%';
                
                // Update color based on progress
                progressBar.className = 'progress-bar ' + getProgressColor(progressPercentage);
                console.log('Updated progress for student', student.student_id, 'to', progressPercentage + '%');
            }
            
            // Update assessment data
            const assessmentBadge = row.querySelector('.assessment-badge');
            if (assessmentBadge) {
                const completed = student.completed_assessments || 0;
                const total = student.total_assessments || 0;
                assessmentBadge.innerHTML = `<i class="bi bi-file-text me-1"></i>${completed}/${total}`;
                assessmentBadge.className = 'badge ' + getAssessmentColor(completed, total);
            }
            
            // Update average score
            const scoreBadge = row.querySelector('.score-badge');
            if (scoreBadge) {
                const avgScore = parseFloat(student.avg_score) || 0;
                scoreBadge.textContent = avgScore.toFixed(1) + '%';
                scoreBadge.className = 'badge ' + getScoreColor(avgScore);
            }
            
            // Update last activity
            const activityBadge = row.querySelector('.activity-badge');
            if (activityBadge && student.last_activity) {
                const timeAgo = getTimeAgo(student.last_activity);
                activityBadge.innerHTML = `<i class="bi bi-clock me-1"></i>${timeAgo.text}`;
                activityBadge.className = 'badge ' + timeAgo.color;
            }
        }
    });
}

// Function to update summary statistics
function updateSummaryStats(summary) {
    // Update total students
    const totalStudents = document.querySelector('.total-students');
    if (totalStudents) {
        totalStudents.textContent = summary.total_students || 0;
    }
    
    // Update active students
    const activeStudents = document.querySelector('.active-students');
    if (activeStudents) {
        activeStudents.textContent = summary.active_students || 0;
    }
    
    // Update average progress
    const avgProgress = document.querySelector('.avg-progress');
    if (avgProgress) {
        avgProgress.textContent = (summary.avg_progress || 0).toFixed(1) + '%';
    }
    
    // Update average score
    const avgScore = document.querySelector('.avg-score');
    if (avgScore) {
        avgScore.textContent = (summary.avg_score || 0).toFixed(1) + '%';
    }
}

// Function to update score data with visual indicators
function updateScoreData(students) {
    console.log('Updating score data with', students.length, 'students');
    
    students.forEach(student => {
        const row = document.querySelector(`tr[data-student-id="${student.student_id}"]`);
        if (!row) return;
        
        const scoreBadge = row.querySelector('.clickable-score');
        if (!scoreBadge) return;
        
        const currentScore = parseFloat(student.avg_score) || 0;
        const previousScore = lastScoreData[student.student_id] || currentScore;
        
        // Update the score display
        scoreBadge.innerHTML = `<i class="bi bi-info-circle me-1"></i>${currentScore.toFixed(1)}%`;
        
        // Update color based on score
        const scoreColor = currentScore >= 80 ? 'bg-success' : 
                          (currentScore >= 70 ? 'bg-warning' : 
                          (currentScore >= 50 ? 'bg-info' : 'bg-danger'));
        scoreBadge.className = `badge ${scoreColor} score-badge clickable-score`;
        
        // Add visual indicator for score changes
        if (previousScore !== currentScore) {
            const changeIndicator = document.createElement('span');
            changeIndicator.className = 'score-change-indicator';
            
            if (currentScore > previousScore) {
                changeIndicator.innerHTML = '<i class="bi bi-arrow-up text-success"></i>';
                changeIndicator.style.color = '#28a745';
            } else if (currentScore < previousScore) {
                changeIndicator.innerHTML = '<i class="bi bi-arrow-down text-danger"></i>';
                changeIndicator.style.color = '#dc3545';
            }
            
            // Add the indicator temporarily
            scoreBadge.appendChild(changeIndicator);
            
            // Remove the indicator after 3 seconds
            setTimeout(() => {
                if (changeIndicator.parentNode) {
                    changeIndicator.parentNode.removeChild(changeIndicator);
                }
            }, 3000);
            
            // Add pulse animation to the score badge
            scoreBadge.classList.add('score-updated');
            setTimeout(() => {
                scoreBadge.classList.remove('score-updated');
            }, 1000);
            
            // Show real-time update notification
            showRealtimeUpdateNotification(`${student.first_name} ${student.last_name}'s score updated to ${currentScore.toFixed(1)}%`);
        }
        
        // Update best score if available
        const bestScoreElement = row.querySelector('.text-muted.d-block');
        if (bestScoreElement && student.best_score > 0) {
            bestScoreElement.innerHTML = `Best: ${parseFloat(student.best_score).toFixed(1)}%`;
        }
        
        // Store current score for next comparison
        lastScoreData[student.student_id] = currentScore;
    });
}

// Helper functions for color coding
function getProgressColor(percentage) {
    if (percentage >= 80) return 'bg-success';
    if (percentage >= 60) return 'bg-warning';
    if (percentage >= 40) return 'bg-info';
    return 'bg-danger';
}

function getAssessmentColor(completed, total) {
    if (completed === total && total > 0) return 'bg-success';
    if (completed > 0) return 'bg-warning';
    return 'bg-secondary';
}

function getScoreColor(score) {
    if (score >= 80) return 'bg-success';
    if (score >= 70) return 'bg-warning';
    if (score >= 50) return 'bg-info';
    return 'bg-danger';
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const activityTime = new Date(timestamp);
    const diffMs = now - activityTime;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) {
        return { text: 'Today', color: 'bg-success' };
    } else if (diffDays === 1) {
        return { text: 'Yesterday', color: 'bg-warning' };
    } else if (diffDays <= 7) {
        return { text: diffDays + ' days ago', color: 'bg-info' };
    } else {
        return { text: diffDays + ' days ago', color: 'bg-danger' };
    }
}

// Page visibility API to pause updates when tab is not visible
document.addEventListener('visibilitychange', function() {
    isPageVisible = !document.hidden;
    if (isPageVisible) {
        // Resume updates when page becomes visible
        updateProgressData();
    }
});

// Add CSS for smooth transitions
const style = document.createElement('style');
style.textContent = `
    .progress-bar, .badge, .progress-text {
        transition: all 0.3s ease-in-out;
    }
    .update-indicator {
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }
    .update-indicator.show {
        opacity: 1;
    }
`;
document.head.appendChild(style);

// Start real-time updates (every 15 seconds for more responsive score updates)
progressUpdateInterval = setInterval(updateProgressData, 15000);

// Show live status indicator
const realtimeStatus = document.getElementById('realtimeStatus');
if (realtimeStatus) {
    realtimeStatus.style.display = 'inline';
}

// Test function to manually update progress bars
function testProgressBars() {
    console.log('Testing progress bars...');
    const rows = document.querySelectorAll('tr[data-student-id]');
    console.log('Found', rows.length, 'student rows');
    
    rows.forEach((row, index) => {
        const progressBar = row.querySelector('.progress-bar');
        const progressText = row.querySelector('.progress-text');
        
        if (progressBar && progressText) {
            const testProgress = (index + 1) * 20; // 20%, 40%, 60%, 80%, 100%
            progressBar.style.width = Math.min(testProgress, 100) + '%';
            progressText.textContent = testProgress + '%';
            progressBar.className = 'progress-bar ' + getProgressColor(testProgress);
            console.log('Updated row', index, 'to', testProgress + '%');
        } else {
            console.log('Row', index, 'missing progress elements');
        }
    });
}

// Add test button
const testButton = document.createElement('button');
testButton.textContent = 'Test Progress Bars';
testButton.className = 'btn btn-sm btn-warning ms-2';
testButton.onclick = testProgressBars;
document.querySelector('.card-header .d-flex').appendChild(testButton);

// Initial update after 5 seconds
setTimeout(updateProgressData, 5000);

// Function to show student courses modal
function showStudentCourses(studentId) {
    // Get the course ID from the clicked row
    const row = document.querySelector(`tr[data-student-id="${studentId}"]`);
    if (!row) {
        alert('Error: Could not find student information.');
        return;
    }
    
    // Get the course ID from the row data or find the first course for this student
    const courseId = row.dataset.courseId || getFirstCourseForStudent(studentId);
    
    if (!courseId) {
        // If no course ID found, redirect to a page that will show course selection
        window.location.href = `student_course_details.php?student_id=${studentId}&academic_period_id=<?php echo $selected_year_id; ?>&select_course=1`;
        return;
    }
    
    // Redirect to the course details page
    window.location.href = `student_course_details.php?student_id=${studentId}&course_id=${courseId}&academic_period_id=<?php echo $selected_year_id; ?>`;
}

// Helper function to get the first course for a student
function getFirstCourseForStudent(studentId) {
    // This would need to be implemented based on your data structure
    // For now, we'll return null and handle it in the PHP redirect
    return null;
}

// Function to generate student courses HTML
function generateStudentCoursesHTML(data) {
    const student = data.student;
    const courses = data.courses;
    
    let html = `
        <div class="row mb-3">
            <div class="col-md-3">
                <img src="${student.profile_picture ? getProfilePictureUrl(student.profile_picture, 'medium') : 'images/default-avatar.png'}" 
                     class="img-fluid rounded-circle" alt="Student" style="width: 80px; height: 80px; object-fit: cover;">
            </div>
            <div class="col-md-9">
                <h6 class="mb-1">${student.first_name} ${student.last_name}</h6>
                <p class="text-muted mb-1">${student.email}</p>
                <p class="text-muted mb-0">Student ID: ${student.identifier || 'N/A'}</p>
            </div>
        </div>
        <hr>
        <h6 class="mb-3">Enrolled Courses (${courses.length})</h6>
    `;
    
    if (courses.length === 0) {
        html += '<div class="alert alert-info">Student is not enrolled in any of your courses.</div>';
    } else {
        courses.forEach(course => {
            const progress = parseFloat(course.progress_percentage || 0);
            const progressColor = progress >= 80 ? 'bg-success' : 
                                (progress >= 60 ? 'bg-warning' : 
                                (progress >= 40 ? 'bg-info' : 'bg-danger'));
            
            html += `
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="card-title">${course.course_name}</h6>
                                <p class="text-muted mb-2">${course.course_code}</p>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="progress me-2" style="width: 100px; height: 8px;">
                                        <div class="progress-bar ${progressColor}" style="width: ${Math.min(course.progress_percentage || 0, 100)}%"></div>
                                    </div>
                                    <small class="fw-bold">${parseFloat(course.progress_percentage || 0).toFixed(1)}%</small>
                                </div>
                                <small class="text-muted">
                                    Enrolled: ${new Date(course.enrolled_at).toLocaleDateString()}
                                    ${course.last_accessed ? ' | Last Activity: ' + new Date(course.last_accessed).toLocaleDateString() : ''}
                                </small>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="mb-2">
                                    <span class="badge ${course.status === 'active' ? 'bg-success' : 'bg-warning'}">
                                        ${course.status}
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Assessments: ${course.completed_assessments}/${course.total_assessments}</small>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Avg Score: ${parseFloat(course.avg_score || 0).toFixed(1)}%</small>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a href="student_detail.php?id=${student.id}&course=${course.course_id}" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> Details
                                    </a>
                                    <a href="student_progress.php?id=${student.id}&course=${course.course_id}" 
                                       class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-graph-up"></i> Progress
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    return html;
}

// Function to show student progress (placeholder)
function showStudentProgress(studentId) {
    alert('Student progress view coming soon!');
}

// Function to kick student from all courses
function kickStudentFromAllCourses(studentId, studentName) {
    if (confirm(`Are you sure you want to KICK "${studentName}" from ALL courses?\n\nThis action will remove the student from all your courses and delete all their progress data.\n\nThis action CANNOT be undone!`)) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="bulk_kick_students">
            <input type="hidden" name="student_ids[]" value="${studentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Function to update sections based on selected course and submit form
function updateSectionsAndSubmit() {
    const courseSelect = document.getElementById('course');
    const sectionSelect = document.getElementById('section');
    const form = courseSelect.closest('form');
    
    // Add loading state
    courseSelect.classList.add('loading');
    sectionSelect.classList.add('loading');
    
    // Clear section selection when course changes
    sectionSelect.selectedIndex = 0;
    
    // Submit the form to update sections
    form.submit();
}

// Function to update courses based on selected section and submit form
function updateCoursesAndSubmit() {
    const courseSelect = document.getElementById('course');
    const sectionSelect = document.getElementById('section');
    const form = sectionSelect.closest('form');
    
    // Add loading state
    courseSelect.classList.add('loading');
    sectionSelect.classList.add('loading');
    
    // Clear course selection when section changes
    courseSelect.selectedIndex = 0;
    
    // Submit the form to update courses
    form.submit();
}

// Search input handling with debounce
let searchTimeout;
function handleSearchInput() {
    const searchInput = document.getElementById('search');
    const form = searchInput.closest('form');
    
    // Clear existing timeout
    clearTimeout(searchTimeout);
    
    // Add loading state
    searchInput.classList.add('loading');
    
    // Set new timeout for search (500ms delay)
    searchTimeout = setTimeout(() => {
        form.submit();
    }, 500);
}

// Clear search function
function clearSearch() {
    const searchInput = document.getElementById('search');
    const form = searchInput.closest('form');
    
    // Clear search input
    searchInput.value = '';
    
    // Submit form to clear search
    form.submit();
}

// Export student statistics function
function exportStudentStats() {
    // Get current filter parameters
    const urlParams = new URLSearchParams(window.location.search);
    const academicPeriodId = urlParams.get('academic_period_id') || '<?php echo $selected_year_id; ?>';
    const courseFilter = urlParams.get('course') || '';
    const sectionFilter = urlParams.get('section') || '';
    const searchFilter = urlParams.get('search') || '';
    const enrolledOnly = urlParams.get('enrolled_only') || '0';
    const sortBy = urlParams.get('sort') || 'name';
    
    // Build export URL
    const exportUrl = 'export_student_stats.php?' + new URLSearchParams({
        academic_period_id: academicPeriodId,
        course: courseFilter,
        section: sectionFilter,
        search: searchFilter,
        enrolled_only: enrolledOnly,
        sort: sortBy
    });
    
    // Show loading state
    const exportBtn = document.getElementById('exportStatsBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Exporting...';
    exportBtn.disabled = true;
    exportBtn.classList.add('processing');
    
    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = 'student_statistics.csv';
    link.style.display = 'none';
    document.body.appendChild(link);
    
    // Add error handling
    link.onerror = function() {
        alert('Error exporting data. Please try again.');
        resetExportButton();
    };
    
    // Trigger download
    link.click();
    
    // Show success message after a short delay
    setTimeout(() => {
        // Create a temporary success message
        const successMsg = document.createElement('div');
        successMsg.className = 'alert alert-success alert-dismissible fade show position-fixed';
        successMsg.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        successMsg.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>
            Student statistics exported successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(successMsg);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (successMsg.parentNode) {
                successMsg.parentNode.removeChild(successMsg);
            }
        }, 3000);
    }, 1000);
    
    // Clean up
    setTimeout(() => {
        document.body.removeChild(link);
        resetExportButton();
    }, 3000);
    
    function resetExportButton() {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
        exportBtn.classList.remove('processing');
    }
}

// Export detailed assessment data function
function exportAssessmentDetails() {
    // Get current filter parameters
    const urlParams = new URLSearchParams(window.location.search);
    const academicPeriodId = urlParams.get('academic_period_id') || '<?php echo $selected_year_id; ?>';
    const courseFilter = urlParams.get('course') || '';
    const sectionFilter = urlParams.get('section') || '';
    const searchFilter = urlParams.get('search') || '';
    const enrolledOnly = urlParams.get('enrolled_only') || '0';
    const sortBy = urlParams.get('sort') || 'name';
    
    // Build export URL with detailed flag
    const exportUrl = 'export_student_stats.php?' + new URLSearchParams({
        academic_period_id: academicPeriodId,
        course: courseFilter,
        section: sectionFilter,
        search: searchFilter,
        enrolled_only: enrolledOnly,
        sort: sortBy,
        detailed: '1'
    });
    
    // Show loading state
    const exportBtn = document.getElementById('exportAssessmentsBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Exporting...';
    exportBtn.disabled = true;
    exportBtn.classList.add('processing');
    
    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = 'detailed_assessment_data.csv';
    link.style.display = 'none';
    document.body.appendChild(link);
    
    // Add error handling
    link.onerror = function() {
        alert('Error exporting assessment data. Please try again.');
        resetAssessmentExportButton();
    };
    
    // Trigger download
    link.click();
    
    // Show success message after a short delay
    setTimeout(() => {
        // Create a temporary success message
        const successMsg = document.createElement('div');
        successMsg.className = 'alert alert-success alert-dismissible fade show position-fixed';
        successMsg.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        successMsg.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>
            Detailed assessment data exported successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(successMsg);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (successMsg.parentNode) {
                successMsg.parentNode.removeChild(successMsg);
            }
        }, 3000);
    }, 1000);
    
    // Clean up
    setTimeout(() => {
        document.body.removeChild(link);
        resetAssessmentExportButton();
    }, 3000);
    
    function resetAssessmentExportButton() {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
        exportBtn.classList.remove('processing');
    }
}

// Score details functionality
let currentScoreData = null;

// Add click event listeners to clickable scores
document.addEventListener('DOMContentLoaded', function() {
    // Add click event listeners to all clickable scores
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('clickable-score')) {
            e.preventDefault();
            e.stopPropagation();
            showScoreDetails(e.target);
        }
    });
});

// Show score details modal
function showScoreDetails(scoreElement) {
    const studentId = scoreElement.getAttribute('data-student-id');
    const courseId = scoreElement.getAttribute('data-course-id');
    const academicPeriodId = scoreElement.getAttribute('data-academic-period-id');
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('scoreDetailsModal'));
    modal.show();
    
    // Load score details
    loadScoreDetails(studentId, courseId, academicPeriodId);
}

// Load score details via AJAX
function loadScoreDetails(studentId, courseId, academicPeriodId) {
    const content = document.getElementById('scoreDetailsContent');
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading score details...</p>
        </div>
    `;
    
    const url = 'ajax_get_score_details.php?' + new URLSearchParams({
        student_id: studentId,
        course_id: courseId,
        academic_period_id: academicPeriodId
    });
    
    fetch(url)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentScoreData = data;
            displayScoreDetails(data);
        } else {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Error loading score details: ${data.error || 'Unknown error'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading score details:', error);
        content.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Error loading score details. Please try again.
            </div>
        `;
    });
}

// Display score details in modal
function displayScoreDetails(data) {
    const content = document.getElementById('scoreDetailsContent');
    const student = data.student;
    const attempts = data.attempts || [];
    const stats = data.stats || {};
    
    let html = `
        <div class="score-summary">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <img src="${student.profile_picture ? getProfilePictureUrl(student.profile_picture, 'medium') : 'images/default-avatar.png'}" 
                         class="img-fluid rounded-circle" alt="Student" style="width: 60px; height: 60px; object-fit: cover;">
                </div>
                <div class="col-md-9">
                    <h4 class="mb-1">${student.first_name} ${student.last_name}</h4>
                    <p class="mb-0 opacity-75">${student.email}</p>
                    ${student.identifier ? `<span class="badge bg-light text-dark me-2">${student.identifier}</span>` : ''}
                    ${data.course ? `<span class="badge bg-info">${data.course.course_name} (${data.course.course_code})</span>` : ''}
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="score-breakdown-card text-center">
                    <h3 class="text-primary mb-1">${stats.average_score || 0}%</h3>
                    <p class="mb-0 text-muted">Average Score</p>
                    <small class="text-muted">Based on ${stats.total_attempts || 0} attempts</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="score-breakdown-card text-center">
                    <h3 class="text-success mb-1">${stats.best_score || 0}%</h3>
                    <p class="mb-0 text-muted">Best Score</p>
                    <small class="text-muted">Highest achieved</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="score-breakdown-card text-center">
                    <h3 class="text-warning mb-1">${stats.worst_score || 0}%</h3>
                    <p class="mb-0 text-muted">Lowest Score</p>
                    <small class="text-muted">Needs improvement</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="score-breakdown-card text-center">
                    <h3 class="text-info mb-1">${stats.total_attempts || 0}</h3>
                    <p class="mb-0 text-muted">Total Attempts</p>
                    <small class="text-muted">Assessment tries</small>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <h5 class="mb-3">
                    <i class="bi bi-clock-history me-2"></i>Recent Assessment Attempts
                    <span class="badge bg-primary ms-2">${attempts.length} attempts</span>
                </h5>
    `;
    
    if (attempts.length === 0) {
        html += `
            <div class="text-center py-4">
                <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                <p class="text-muted mt-2">No assessment attempts found</p>
            </div>
        `;
    } else {
        html += '<div class="assessment-attempts-list">';
        
        attempts.forEach((attempt, index) => {
            const scoreClass = attempt.score >= 70 ? 'success' : attempt.score >= 50 ? 'warning' : 'danger';
            const trend = getScoreTrend(attempts, index);
            
            html += `
                <div class="assessment-attempt-item">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h6 class="mb-1">${attempt.assessment_title || 'Assessment'}</h6>
                            <div class="attempt-meta">
                                <span class="badge bg-${attempt.difficulty === 'easy' ? 'success' : attempt.difficulty === 'medium' ? 'warning' : 'danger'}">
                                    ${attempt.difficulty || 'Unknown'}
                                </span>
                                <span class="text-muted ms-2">Passing: ${attempt.passing_rate || 0}%</span>
                                <span class="text-muted ms-2">Time Limit: ${attempt.time_limit || 0} min</span>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="score-display">
                                <span class="badge bg-${scoreClass} fs-5">${attempt.score || 0}%</span>
                                <div class="score-trend ${trend.class}">
                                    <i class="bi bi-${trend.icon}"></i>
                                    <small>${trend.text}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <div class="attempt-details">
                                <small class="text-muted d-block">${attempt.completed_at ? new Date(attempt.completed_at).toLocaleDateString() : 'Unknown'}</small>
                                <small class="text-muted">${attempt.time_taken ? formatTime(attempt.time_taken) : 'N/A'}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    html += `
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="score-breakdown-card">
                    <h6 class="mb-3">
                        <i class="bi bi-calculator me-2"></i>How the Average Score is Calculated
                    </h6>
                    <p class="mb-2">
                        The average score of <strong>${stats.average_score || 0}%</strong> is calculated by:
                    </p>
                    <ul class="mb-0">
                        <li>Taking all ${stats.total_attempts || 0} assessment attempts</li>
                        <li>Summing all individual scores: ${stats.total_score || 0} points</li>
                        <li>Dividing by the total number of attempts: ${stats.total_attempts || 1}</li>
                        <li>Result: ${stats.total_score || 0} ÷ ${stats.total_attempts || 1} = ${stats.average_score || 0}%</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
}

// Get score trend for an attempt
function getScoreTrend(attempts, currentIndex) {
    if (currentIndex === 0) return { class: 'stable', icon: 'minus', text: 'First attempt' };
    
    const current = attempts[currentIndex].score || 0;
    const previous = attempts[currentIndex - 1].score || 0;
    
    if (current > previous) {
        return { class: 'up', icon: 'arrow-up', text: `+${(current - previous).toFixed(1)}%` };
    } else if (current < previous) {
        return { class: 'down', icon: 'arrow-down', text: `${(current - previous).toFixed(1)}%` };
    } else {
        return { class: 'stable', icon: 'minus', text: 'Same as previous' };
    }
}

// Format time in seconds to readable format
function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    } else {
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
}

// Refresh score details
function refreshScoreDetails() {
    if (currentScoreData) {
        const studentId = currentScoreData.student.id;
        const courseId = currentScoreData.course_id;
        const academicPeriodId = currentScoreData.academic_period_id;
        loadScoreDetails(studentId, courseId, academicPeriodId);
    }
}

// Show real-time update notification
function showRealtimeUpdateNotification(message) {
    // Remove any existing notification
    const existingNotification = document.querySelector('.realtime-indicator');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = 'realtime-indicator';
    notification.innerHTML = `
        <i class="bi bi-arrow-clockwise me-1"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}

// Enhanced scrolling behavior for students table
document.addEventListener('DOMContentLoaded', function() {
    function enhanceStudentsTableScrolling() {
        const tableContainer = document.querySelector('.students-table-container');
        
        if (tableContainer) {
            // Add smooth scrolling behavior
            tableContainer.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const cardContainer = tableContainer.closest('.card');
            if (cardContainer) {
                addStudentsTableScrollIndicators(tableContainer, cardContainer);
            }
        }
    }
    
    // Add scroll indicators to students table
    function addStudentsTableScrollIndicators(scrollContainer, cardContainer) {
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = 'students-scroll-indicator';
        scrollIndicator.innerHTML = `
            <div class="students-scroll-indicator-content">
                <i class="bi bi-chevron-up students-scroll-indicator-top"></i>
                <i class="bi bi-chevron-down students-scroll-indicator-bottom"></i>
            </div>
        `;
        
        cardContainer.style.position = 'relative';
        cardContainer.appendChild(scrollIndicator);
        
        // Update scroll indicators based on scroll position
        function updateStudentsScrollIndicators() {
            const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
            const isAtTop = scrollContainer.scrollTop === 0;
            const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
            
            if (isScrollable) {
                scrollIndicator.classList.add('show');
                scrollIndicator.querySelector('.students-scroll-indicator-top').classList.toggle('hide', isAtTop);
                scrollIndicator.querySelector('.students-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
            } else {
                scrollIndicator.classList.remove('show');
            }
        }
        
        // Initial check
        updateStudentsScrollIndicators();
        
        // Update on scroll
        scrollContainer.addEventListener('scroll', updateStudentsScrollIndicators);
        
        // Update on resize
        window.addEventListener('resize', updateStudentsScrollIndicators);
    }
    
    // Initialize enhanced students table scrolling
    enhanceStudentsTableScrolling();
});
</script>

<?php require_once '../includes/footer.php'; ?> 