<?php
// Start output buffering to prevent any output before headers
ob_start();

// Include necessary files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear any output that might have been sent
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - Student access required'
    ]);
    ob_end_flush();
    exit();
}

try {
    // Get filter parameters
    $course_id = $_GET['course_id'] ?? '';
    $section_id = $_GET['section_id'] ?? '';
    $year = $_GET['year'] ?? '';
    
    // Build the leaderboard query based on filters
    $filter_where = "";
    $filter_params = [];

    if (!empty($course_id)) {
        $filter_where .= "AND u.id IN (
            SELECT DISTINCT e.student_id 
            FROM course_enrollments e 
            WHERE e.course_id = ? AND e.status = 'active'
        )";
        $filter_params[] = $course_id;
    }

    if (!empty($section_id)) {
        $filter_where .= "AND u.id IN (
            SELECT DISTINCT u.id 
            FROM sections s 
            JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
            WHERE s.id = ?
        )";
        $filter_params[] = $section_id;
    }

    if (!empty($year)) {
        $filter_where .= "AND u.id IN (
            SELECT DISTINCT u.id 
            FROM sections s 
            JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
            WHERE s.year_level = ?
        )";
        $filter_params[] = $year;
    }

    // Get leaderboard data with section information and filters
    $leaderboard_sql = "
        SELECT 
            u.id, u.first_name, u.last_name, u.username, u.email, u.profile_picture,
            s.section_name, s.year_level,
            COALESCE(SUM(aa.score), 0) as total_score,
            COUNT(DISTINCT aa.id) as total_attempts,
            AVG(aa.score) as average_score,
            COUNT(DISTINCT CASE WHEN aa.score >= 70 THEN aa.id END) as high_scores,
            COUNT(DISTINCT mp.module_id) as completed_modules,
            COUNT(DISTINCT vv.video_id) as watched_videos,
            COUNT(DISTINCT sb.badge_id) as badge_count
        FROM users u
        LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
        LEFT JOIN assessment_attempts aa ON u.id = aa.student_id AND aa.status = 'completed'
        LEFT JOIN module_progress mp ON u.id = mp.student_id AND mp.is_completed = 1
        LEFT JOIN video_views vv ON u.id = vv.student_id
        LEFT JOIN student_badges sb ON u.id = sb.student_id
        WHERE u.role = 'student' $filter_where
        GROUP BY u.id, u.first_name, u.last_name, u.username, u.email, u.profile_picture, s.section_name, s.year_level
        ORDER BY total_score DESC, average_score DESC
        LIMIT 100
    ";
    
    $stmt = $pdo->prepare($leaderboard_sql);
    $stmt->execute($filter_params);
    $leaderboard = $stmt->fetchAll();
    
    // Get current user's rank
    $current_user_id = $_SESSION['user_id'];
    $current_user_rank = null;
    
    foreach ($leaderboard as $index => $user) {
        if ($user['id'] == $current_user_id) {
            $current_user_rank = $index + 1;
            break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'leaderboard' => $leaderboard,
        'current_user_rank' => $current_user_rank,
        'filters' => [
            'course_id' => $course_id,
            'section_id' => $section_id,
            'year' => $year
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in ajax_get_leaderboard.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'leaderboard' => []
    ]);
}

ob_end_flush();
?>
