<?php
session_start();
require_once '../config/database.php';
require_once '../includes/badge_date_helper.php';

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

// Get badges earned using the helper function
$badges = BadgeDateHelper::getStudentBadgesWithDates($pdo, $user_id, 5);

// Get overall progress statistics for selected academic year
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT e.course_id) as total_courses,
        (SELECT COUNT(*) FROM course_enrollments e2 
         JOIN courses c ON e2.course_id = c.id
         WHERE e2.student_id = ? AND e2.status = 'active' AND c.academic_period_id = ? AND c.modules IS NOT NULL) as total_modules,
        (SELECT COUNT(*) FROM courses WHERE academic_period_id = ? AND status = 'active') as available_in_section,
        (SELECT COUNT(*) FROM courses WHERE academic_period_id = ? AND status = 'inactive') as inactive_periods,
        (SELECT COUNT(*) FROM courses WHERE academic_period_id = ? AND status = 'active' AND id NOT IN (SELECT course_id FROM course_enrollments WHERE student_id = ? AND status = 'active')) as other_sections
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = ? AND e.status = 'active' AND c.academic_period_id = ?
");
$stmt->execute([$user_id, $selected_year_id, $selected_year_id, $selected_year_id, $selected_year_id, $user_id, $user_id, $selected_year_id]);
$stats = $stmt->fetch();

// Calculate completed modules based on assessment attempts
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT a.id) as completed_modules
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE aa.student_id = ? AND aa.status = 'completed' AND c.academic_period_id = ?
");
$stmt->execute([$user_id, $selected_year_id]);
$completed_modules = $stmt->fetchColumn();

// Get additional progress statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT aa.assessment_id) as total_attempts,
        AVG(aa.score) as average_score,
        COUNT(CASE WHEN aa.score >= 70 THEN 1 END) as passed_assessments,
        COUNT(CASE WHEN aa.score < 70 THEN 1 END) as failed_assessments
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE aa.student_id = ? AND aa.status = 'completed' AND c.academic_period_id = ?
");
$stmt->execute([$user_id, $selected_year_id]);
$progress_stats = $stmt->fetch();

// Calculate overall progress with more realistic assessment-based calculation
$total_modules = $stats['total_modules'] ?? 0;
$total_attempts = $progress_stats['total_attempts'] ?? 0;
$passed_assessments = $progress_stats['passed_assessments'] ?? 0;

// More realistic progress calculation:
// 1. Base progress on passed assessments vs total attempts
// 2. Apply a completion factor based on assessment success rate
// 3. Cap at reasonable maximum to avoid unrealistic percentages

if ($total_attempts > 0) {
    $success_rate = $passed_assessments / $total_attempts;
    $base_progress = ($passed_assessments / max($total_modules, 1)) * 100;
    
    // Apply success rate as a multiplier (0.6 to 1.0 range)
    $success_multiplier = max(0.6, min(1.0, $success_rate));
    $adjusted_progress = $base_progress * $success_multiplier;
    
    // Cap at 95% to keep it realistic
    $overall_progress = min(95, round($adjusted_progress));
} else {
    $overall_progress = 0;
}

// Update stats array
$stats['completed_modules'] = $completed_modules;
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
        
        /* Enhanced Activity and Badge Items */
        .activity-item {
            border-radius: 12px;
            transition: all 0.3s ease;
            padding: 15px;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 0 2px 2px 0;
        }
        
        .activity-item:hover {
            background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,123,255,0.1);
        }
        
        .badge-item {
            border-radius: 12px;
            transition: all 0.3s ease;
            padding: 15px;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #fff8e1 0%, #ffffff 100%);
            border: 1px solid #ffecb3;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .badge-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #ffc107, #ff9800);
        }
        
        .badge-item:hover {
            background: linear-gradient(135deg, #fff3e0 0%, #ffffff 100%);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255,193,7,0.2);
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

        /* Enhanced Enrollment Request Styling */
        .enrollment-request-item {
            border-radius: 12px;
            transition: all 0.3s ease;
            padding: 18px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }
        
        .enrollment-request-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 0 2px 2px 0;
        }
        
        .enrollment-request-item:hover {
            background: linear-gradient(135deg, #e8f5e8 0%, #f8f9fa 100%);
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(40,167,69,0.1);
        }
        
        .enrollment-status {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffc107;
        }
        
        .status-approved {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #28a745;
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #dc3545;
        }

        /* Enhanced Announcement Styling */
        .announcement-item {
            border-radius: 12px;
            transition: all 0.3s ease;
            padding: 18px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }
        
        .announcement-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #17a2b8, #20c997);
            border-radius: 0 2px 2px 0;
        }
        
        .announcement-item:hover {
            background: linear-gradient(135deg, #e0f7fa 0%, #f8f9fa 100%);
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(23,162,184,0.1);
        }
        
        .announcement-course {
            font-size: 0.7rem;
            font-weight: 600;
            color: #495057;
            background: linear-gradient(135deg, #e9ecef, #f8f9fa);
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .announcement-system {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            box-shadow: 0 2px 8px rgba(0,123,255,0.3);
        }

        /* Enhanced Card Headers - Matching Page Color Scheme */
        .enhanced-card .card-header {
            color: white;
            border: none;
            border-radius: 12px 12px 0 0;
            padding: 1.25rem 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        /* Recent Activities - Blue theme */
        .enhanced-card.recent-activities .card-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        
        /* Recent Badges - Yellow theme */
        .enhanced-card.recent-badges .card-header {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
        }
        
        /* Enrollment Requests - Green theme */
        .enhanced-card.enrollment-requests .card-header {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
        
        /* Recent Announcements - Teal theme */
        .enhanced-card.recent-announcements .card-header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        .enhanced-card .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }
        
        .enhanced-card .card-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        .enhanced-card .card-header i {
            margin-right: 8px;
            opacity: 0.9;
        }
        
        .enhanced-card .card-header .btn {
            position: relative;
            z-index: 1;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .enhanced-card .card-header .btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-1px);
        }
        
        /* Special styling for yellow theme (badges) */
        .enhanced-card.recent-badges .card-header .btn {
            background: rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.2);
            color: #212529;
        }
        
        .enhanced-card.recent-badges .card-header .btn:hover {
            background: rgba(0,0,0,0.2);
            border-color: rgba(0,0,0,0.3);
        }
        
        .enhanced-card .card-body {
            padding: 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .enhanced-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .enhanced-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }

        /* Scrollable Container Styles */
        .scrollable-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .scrollable-container::-webkit-scrollbar {
            width: 8px;
        }

        .scrollable-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .scrollable-container::-webkit-scrollbar-thumb {
            background: #2E5E4E;
            border-radius: 4px;
        }

        .scrollable-container::-webkit-scrollbar-thumb:hover {
            background: #7DCB80;
        }

        /* Firefox scrollbar styling */
        .scrollable-container {
            scrollbar-width: thin;
            scrollbar-color: #2E5E4E #f1f1f1;
        }

        /* Enhanced Welcome Section */
        .welcome-section {
            background: #2E5E4E;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }
        
        .welcome-title {
            color: white;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .welcome-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 1.1rem;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        .student-id-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 0.5rem;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }
        
        .student-id-badge:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .welcome-actions {
            position: relative;
            z-index: 1;
        }
        
        .welcome-actions .btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .welcome-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        /* Decorative elements */
        .welcome-section::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
        }
        
        .welcome-decoration {
            position: absolute;
            top: 60px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }
        
        .welcome-decoration i {
            font-size: 1.5rem;
            color: rgba(255,255,255,0.8);
        }
        
        /* Additional system-themed decorative elements */
        .welcome-section .accent-line {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #7DCB80;
            border-radius: 0 0 20px 20px;
        }
        
        .welcome-section .floating-shapes {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            z-index: 1;
        }
        
        .welcome-section .floating-shapes::before {
            content: '';
            position: absolute;
            top: 50px;
            left: 20px;
            width: 20px;
            height: 20px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        /* Enhanced Progress Overview Section */
        .progress-overview-section {
            margin-bottom: 2rem;
        }
        
        .progress-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        
        .progress-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        
        .progress-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff 0%, #0056b3 50%, #004085 100%);
        }
        
        .progress-card-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 1.5rem;
            margin: -1rem -1rem 1rem -1rem;
            position: relative;
            overflow: hidden;
        }
        
        .progress-card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            pointer-events: none;
        }
        
        .progress-card-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .progress-card-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
            position: relative;
            z-index: 1;
        }
        
        /* Enhanced Progress Circle */
        .enhanced-progress-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(#007bff 0deg, #007bff <?php echo $overall_progress * 3.6; ?>deg, #e9ecef <?php echo $overall_progress * 3.6; ?>deg, #e9ecef 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            position: relative;
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .enhanced-progress-circle:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
        }
        
        .enhanced-progress-circle::before {
            content: '';
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: white;
            position: absolute;
            z-index: 1;
        }
        
        .enhanced-progress-text {
            position: relative;
            z-index: 2;
            font-size: 1.4rem;
            font-weight: 800;
            color: #007bff;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .progress-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0;
            padding: 1rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            border: 1px solid #e9ecef;
        }
        
        .progress-stat-item {
            text-align: center;
            flex: 1;
        }
        
        .progress-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #007bff;
            margin-bottom: 0.25rem;
        }
        
        .progress-stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .progress-divider {
            width: 1px;
            height: 40px;
            background: linear-gradient(to bottom, transparent, #dee2e6, transparent);
            margin: 0 1rem;
        }
        
        /* Enhanced Assessment Performance */
        .assessment-performance {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .assessment-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .assessment-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 35px rgba(0, 123, 255, 0.3);
        }
        
        .assessment-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .assessment-stats {
            display: flex;
            justify-content: space-around;
            margin: 1.5rem 0;
        }
        
        .assessment-stat {
            text-align: center;
            padding: 1rem;
            border-radius: 15px;
            transition: all 0.3s ease;
            flex: 1;
            margin: 0 0.5rem;
        }
        
        .assessment-stat.passed {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #28a745;
        }
        
        .assessment-stat.failed {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #dc3545;
        }
        
        .assessment-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .assessment-stat-value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .assessment-stat.passed .assessment-stat-value {
            color: #28a745;
        }
        
        .assessment-stat.failed .assessment-stat-value {
            color: #dc3545;
        }
        
        .assessment-stat-label {
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .assessment-stat.passed .assessment-stat-label {
            color: #155724;
        }
        
        .assessment-stat.failed .assessment-stat-label {
            color: #721c24;
        }
        
        .total-attempts {
            background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
            border: 1px solid #2196f3;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            margin-top: 1rem;
        }
        
        .total-attempts-text {
            color: #1976d2;
            font-weight: 600;
            margin: 0;
        }

        /* Enhanced Academic Year Selector */
        .academic-year-selector {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        .academic-year-selector:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .academic-year-label {
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            margin-right: 0.75rem;
            opacity: 0.9;
        }
        
        .academic-year-select {
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 15px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            color: #2E5E4E;
            min-width: 200px;
            transition: all 0.3s ease;
        }
        
        .academic-year-select:focus {
            background: white;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
            outline: none;
        }
        
        .academic-year-select option {
            color: #2E5E4E;
            font-weight: 500;
        }
        
        .academic-year-icon {
            color: rgba(255,255,255,0.8);
            margin-right: 0.5rem;
            font-size: 1rem;
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
                <!-- Enhanced Welcome Section -->
                <div class="welcome-section">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>!</h1>
                            <p class="welcome-subtitle">Ready to continue your learning journey?</p>
                            
                            <!-- Academic Year Selector -->
                            <div class="academic-year-selector d-inline-block mt-3">
                                <form method="get" class="d-flex align-items-center">
                                    <i class="fas fa-calendar-alt academic-year-icon"></i>
                                    <label for="academic_period_id" class="academic-year-label">Academic Year:</label>
                                    <select name="academic_period_id" id="academic_period_id" class="academic-year-select" onchange="this.form.submit()">
                                        <?php foreach ($all_years as $year): ?>
                                            <option value="<?= $year['id'] ?>" <?= $selected_year_id == $year['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($year['academic_year'] . ' - ' . $year['semester_name']) ?><?= !$year['is_active'] ? ' (Inactive)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button type="submit" class="btn btn-sm ms-2" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white;">Go</button></noscript>
                                </form>
                            </div>
                            
                            <?php if (!empty($student['student_id'])): ?>
                                <div class="mt-3">
                                    <span class="student-id-badge">
                                        <i class="fas fa-id-card me-2"></i>
                                        Student ID: <?php echo htmlspecialchars($student['student_id']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="welcome-actions">
                                <a href="courses.php" class="btn">
                                    <i class="fas fa-book me-2"></i>
                                    View All Courses
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="welcome-decoration">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="floating-shapes"></div>
                    <div class="accent-line"></div>
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
                                <i class="fas fa-tasks fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $completed_modules; ?></h4>
                                <p class="card-text small">Completed Modules</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-layer-group fa-2x mb-2" style="color: rgba(255,255,255,0.9);"></i>
                                <h4 class="card-title mb-1"><?php echo $total_modules; ?></h4>
                                <p class="card-text small">Total Modules</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #212529; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <div class="card-body">
                                <i class="fas fa-percentage fa-2x mb-2" style="color: rgba(33,37,41,0.8);"></i>
                                <h4 class="card-title mb-1"><?php echo $overall_progress; ?>%</h4>
                                <p class="card-text small">Overall Progress</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Progress Overview -->
                <div class="progress-overview-section">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card progress-card">
                                <div class="progress-card-header">
                                    <h5 class="progress-card-title">
                                        <i class="fas fa-tasks me-2"></i>
                                        Overall Progress
                                    </h5>
                                    <p class="progress-card-subtitle">Your learning journey progress</p>
                                </div>
                                <div class="card-body text-center">
                                    <div class="enhanced-progress-circle">
                                        <div class="enhanced-progress-text"><?php echo $overall_progress; ?>%</div>
                                    </div>
                                    
                                    <div class="progress-stats">
                                        <div class="progress-stat-item">
                                            <div class="progress-stat-value"><?php echo $completed_modules; ?></div>
                                            <div class="progress-stat-label">Completed</div>
                                        </div>
                                        <div class="progress-divider"></div>
                                        <div class="progress-stat-item">
                                            <div class="progress-stat-value"><?php echo $total_modules; ?></div>
                                            <div class="progress-stat-label">Total Modules</div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($progress_stats['average_score']): ?>
                                        <div class="total-attempts">
                                            <p class="total-attempts-text">
                                                <i class="fas fa-star me-2"></i>
                                                Average Score: <?php echo round($progress_stats['average_score'], 1); ?>%
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card progress-card assessment-performance">
                                <div class="progress-card-header">
                                    <h5 class="progress-card-title">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Assessment Performance
                                    </h5>
                                    <p class="progress-card-subtitle">Your test and quiz results</p>
                                </div>
                                <div class="card-body text-center">
                                    <div class="assessment-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    
                                    <div class="assessment-stats">
                                        <div class="assessment-stat passed">
                                            <div class="assessment-stat-value"><?php echo $progress_stats['passed_assessments'] ?? 0; ?></div>
                                            <div class="assessment-stat-label">Passed</div>
                                        </div>
                                        <div class="assessment-stat failed">
                                            <div class="assessment-stat-value"><?php echo $progress_stats['failed_assessments'] ?? 0; ?></div>
                                            <div class="assessment-stat-label">Failed</div>
                                        </div>
                                    </div>
                                    
                                    <div class="total-attempts">
                                        <p class="total-attempts-text">
                                            <i class="fas fa-list-check me-2"></i>
                                            <?php echo $progress_stats['total_attempts'] ?? 0; ?> total attempts
                                        </p>
                                    </div>
                                </div>
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
                                                    // Calculate course progress based on completed assessments
                                                    $course_progress_stmt = $pdo->prepare("
                                                        SELECT 
                                                            COUNT(DISTINCT a.id) as total_assessments,
                                                            COUNT(DISTINCT aa.assessment_id) as completed_assessments
                                                        FROM assessments a
                                                        LEFT JOIN assessment_attempts aa ON a.id = aa.assessment_id AND aa.student_id = ? AND aa.status = 'completed'
                                                        WHERE a.course_id = ?
                                                    ");
                                                    $course_progress_stmt->execute([$user_id, $course['id']]);
                                                    $course_progress_data = $course_progress_stmt->fetch();
                                                    
                                                    $total_assessments = $course_progress_data['total_assessments'] ?? 0;
                                                    $completed_assessments = $course_progress_data['completed_assessments'] ?? 0;
                                                    $course_progress = $total_assessments > 0 ? round(($completed_assessments / $total_assessments) * 100) : 0;
                                                    ?>
                                                    <div class="progress-bar" style="width: <?php echo $course_progress; ?>%">
                                                        <?php echo $course_progress; ?>%
                                                    </div>
                                                </div>
                                                <p class="card-text small">
                                                    <?php echo $completed_assessments; ?> of <?php echo $total_assessments; ?> assessments completed
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
                        <div class="card enhanced-card recent-activities">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activities)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No recent activities</p>
                                    </div>
                                <?php else: ?>
                                    <div class="scrollable-container">
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas fa-tasks text-primary me-2"></i>
                                                        <strong class="text-dark"><?php echo htmlspecialchars($activity['title']); ?></strong>
                                                    </div>
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        <?php echo $activity['action']; ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?php echo date('M j, Y', strtotime($activity['activity_date'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Badges -->
                    <div class="col-md-6">
                        <div class="card enhanced-card recent-badges">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-trophy"></i> Recent Badges</h5>
                                <span class="badge bg-warning text-dark fw-bold px-3 py-2" style="border-radius: 20px; font-size: 0.8rem;">
                                    <?php echo count($badges); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($badges)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No badges earned yet. Keep learning to earn badges!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="scrollable-container">
                                    <?php foreach ($badges as $badge): ?>
                                        <div class="badge-item">
                                            <div class="d-flex align-items-center mb-3">
                                                <?php
                                                $icon_path = "../uploads/badges/" . htmlspecialchars($badge['badge_icon']);
                                                if (!empty($badge['badge_icon']) && file_exists(__DIR__ . "/../uploads/badges/" . $badge['badge_icon'])): ?>
                                                    <img src="<?php echo $icon_path; ?>" alt="<?php echo htmlspecialchars($badge['badge_name']); ?>" class="img-fluid me-3" style="width: 60px; height: 60px; border-radius: 50%; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                                                <?php else: ?>
                                                    <span class="d-inline-flex align-items-center justify-content-center bg-gradient text-white rounded-circle me-3" style="width: 60px; height: 60px; font-size: 2rem; background: linear-gradient(135deg, #ffc107, #ff9800); box-shadow: 0 4px 8px rgba(255,193,7,0.3);">
                                                        <i class="fas fa-award"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($badge['badge_name']); ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo $badge['formatted_date']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enrollment Requests & Announcements Row -->
                <div class="row mb-4">
                    <!-- Enrollment Requests -->
                    <div class="col-md-6">
                        <div class="card enhanced-card enrollment-requests">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-user-plus"></i> Enrollment Requests</h5>
                                <a href="enrollment_requests.php" class="btn btn-sm">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($enrollment_requests)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No enrollment requests found.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="scrollable-container">
                                    <?php foreach ($enrollment_requests as $request): ?>
                                        <div class="enrollment-request-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas fa-book text-success me-2"></i>
                                                        <strong class="text-dark me-2"><?php echo htmlspecialchars($request['course_name']); ?></strong>
                                                        <span class="enrollment-status status-<?php echo $request['status']; ?>">
                                                            <?php echo ucfirst($request['status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-12 mb-1">
                                                            <small class="text-muted">
                                                                <i class="fas fa-user-tie me-1"></i>
                                                                <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['teacher_last_name']); ?>
                                                            </small>
                                                        </div>
                                                        <div class="col-12">
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                <?php echo date('M j, Y g:i A', strtotime($request['requested_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <?php if ($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
                                                        <div class="mt-3 p-3 bg-light rounded" style="border-left: 3px solid #dc3545;">
                                                            <small class="text-danger">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                                <strong>Reason:</strong> <?php echo htmlspecialchars($request['rejection_reason']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Announcements -->
                    <div class="col-md-6">
                        <div class="card enhanced-card recent-announcements">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bullhorn"></i> Recent Announcements</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_announcements)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No announcements available.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="scrollable-container">
                                    <?php foreach ($recent_announcements as $announcement): ?>
                                        <div class="announcement-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="mb-2">
                                                        <?php if ($announcement['course_name']): ?>
                                                            <span class="announcement-course"><?php echo htmlspecialchars($announcement['course_code'] . ' - ' . $announcement['course_name']); ?></span>
                                                        <?php else: ?>
                                                            <span class="announcement-course announcement-system">System Announcement</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <h6 class="mb-2 text-dark fw-bold"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                    <p class="mb-3 small text-muted"><?php echo htmlspecialchars(substr($announcement['content'], 0, 120)) . (strlen($announcement['content']) > 120 ? '...' : ''); ?></p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
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

<?php require_once '../includes/footer.php'; ?> 