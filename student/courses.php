<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/pusher.php';
require_once '../includes/pusher_notifications.php';


$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student information including irregular status
$stmt = $pdo->prepare("SELECT is_irregular FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$student_info = $stmt->fetch();
$is_irregular = $student_info['is_irregular'] ?? 0;

// Get student's section ID first
$stmt = $pdo->prepare("SELECT id FROM sections WHERE JSON_SEARCH(students, 'one', ?) IS NOT NULL");
$stmt->execute([$user_id]);
$student_section_id = $stmt->fetchColumn();

// 1. Fetch all academic years for the dropdown
$ay_stmt = $pdo->prepare('SELECT id, academic_year, semester_name, is_active FROM academic_periods ORDER BY academic_year DESC, semester_name');
$ay_stmt->execute();
$all_years = $ay_stmt->fetchAll();

// 2. Handle academic year selection (GET or SESSION)
if (isset($_GET['academic_period_id'])) {
    $_SESSION['student_courses_academic_period_id'] = (int)$_GET['academic_period_id'];
}
// Find the first active academic year
$active_year = null;
foreach ($all_years as $year) {
    if ($year['is_active']) {
        $active_year = $year['id'];
        break;
    }
}
$selected_year_id = $_SESSION['student_courses_academic_period_id'] ?? $active_year ?? ($all_years[0]['id'] ?? null);

// Handle course enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course'])) {
    // Validate CSRF token
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error'] = 'Invalid CSRF token. Please try again.';
        header('Location: courses.php');
        exit();
    }
    
    $course_id = $_POST['course_id'];
    
    // Check if already enrolled
    $stmt = $pdo->prepare("SELECT * FROM course_enrollments WHERE student_id = ? AND course_id = ? AND status = 'active'");
    $stmt->execute([$user_id, $course_id]);
    
    if ($stmt->rowCount() == 0) {
        // Check if course is assigned to student's section (new JSON-based approach)
        $stmt = $pdo->prepare("
            SELECT 1 FROM courses c 
            WHERE c.id = ? AND (c.sections IS NULL OR JSON_SEARCH(c.sections, 'one', ?) IS NOT NULL)
        ");
        $stmt->execute([$course_id, $student_section_id]);
        $is_section_assigned = $stmt->rowCount() > 0;
        
        // ALL course enrollments now require teacher verification to prevent assessment leaks
        // Check if there's already ANY enrollment request (pending, approved, or rejected)
        $stmt = $pdo->prepare("SELECT * FROM enrollment_requests WHERE student_id = ? AND course_id = ?");
        $stmt->execute([$user_id, $course_id]);
        $existing_request = $stmt->fetch();
        
        if ($existing_request) {
            // Handle existing request based on status
            switch ($existing_request['status']) {
                case 'pending':
                    $_SESSION['error'] = "You already have a pending enrollment request for this course.";
                    break;
                case 'approved':
                    $_SESSION['error'] = "Your enrollment request for this course has already been approved. You should be enrolled in the course.";
                    break;
                case 'rejected':
                    // Update rejected request to pending
                    $stmt = $pdo->prepare("UPDATE enrollment_requests SET status = 'pending', requested_at = NOW() WHERE student_id = ? AND course_id = ? AND status = 'rejected'");
                    $stmt->execute([$user_id, $course_id]);
                    
                    // Get course details for notification
                    $stmt = $pdo->prepare("SELECT c.course_name, c.teacher_id FROM courses c WHERE c.id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch();
                    
                    // Send real-time notification to teacher about resent enrollment request
                    if ($course && PusherConfig::isAvailable()) {
                        require_once __DIR__ . '/../includes/pusher_notifications.php';
                        PusherNotifications::sendNewEnrollmentRequest($course['teacher_id'], $course['course_name'], $user_id);
                    }
                    
                    $_SESSION['success'] = "Enrollment request resent! The teacher will review your request again.";
                    break;
            }
        } else {
            // Create new enrollment request
            try {
                $stmt = $pdo->prepare("INSERT INTO enrollment_requests (student_id, course_id, status, requested_at) VALUES (?, ?, 'pending', NOW())");
                $stmt->execute([$user_id, $course_id]);
                
                // Get course details for notification
                $stmt = $pdo->prepare("SELECT c.course_name, c.teacher_id FROM courses c WHERE c.id = ?");
                $stmt->execute([$course_id]);
                $course = $stmt->fetch();
                
                // Send real-time notification to teacher about new enrollment request
                if ($course && PusherConfig::isAvailable()) {
                    require_once __DIR__ . '/../includes/pusher_notifications.php';
                    PusherNotifications::sendNewEnrollmentRequest($course['teacher_id'], $course['course_name'], $user_id);
                }
                
                $_SESSION['success'] = "Enrollment request sent! The teacher will review your request.";
                error_log("Enrollment request created: Student ID: $user_id, Course ID: $course_id, Status: pending");
            } catch (PDOException $e) {
                error_log("Enrollment error: " . $e->getMessage());
                if ($e->getCode() == 23000) { // Duplicate entry error
                    $_SESSION['error'] = "You already have an enrollment request for this course. Please check your request status.";
                } else {
                    $_SESSION['error'] = "An error occurred while processing your enrollment request: " . $e->getMessage();
                }
            }
        }
    } else {
        $_SESSION['error'] = "You are already enrolled in this course.";
    }
    
    header('Location: courses.php');
    exit();
}

// Get courses assigned to student's section (for both regular and irregular students)
// Note: In new schema, sections are stored as JSON in courses.sections and students are in sections.students JSON
$stmt = $pdo->prepare("
    SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name, 
           (SELECT JSON_LENGTH(modules) FROM courses WHERE id = c.id) as module_count,
           (SELECT COUNT(*) FROM course_enrollments e WHERE e.course_id = c.id AND e.status = 'active') as enrolled_students,
           CASE WHEN e2.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled,
           CASE WHEN er.student_id IS NOT NULL AND er.status = 'pending' THEN 1 ELSE 0 END as has_pending_request,
           CASE WHEN er.student_id IS NOT NULL AND er.status = 'rejected' THEN 1 ELSE 0 END as has_rejected_request,
           er.rejection_reason,
           er.approved_at,
           1 as is_section_assigned,
           ay.is_active as academic_period_active,
           ay.is_active as semester_active,
           ay.is_active as academic_year_active,
           ay.academic_year,
           ay.semester_name,
           c.created_at,
           c.updated_at,
           c.year_level,
           (SELECT 
               COALESCE((
                   SELECT SUM(JSON_LENGTH(JSON_EXTRACT(modules, '$[*].assessments')))
                   FROM courses 
                   WHERE id = c.id
               ), 0)
           ) as assessment_count,
           (SELECT 
               COALESCE((
                   SELECT COUNT(DISTINCT aa.assessment_id)
                   FROM assessment_attempts aa
                   JOIN assessments a ON aa.assessment_id = a.id
                   WHERE a.course_id = c.id AND aa.student_id = ? AND aa.status = 'completed'
               ), 0)
           ) as finished_assessments
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    JOIN academic_periods ay ON c.academic_period_id = ay.id
    LEFT JOIN course_enrollments e2 ON c.id = e2.course_id AND e2.student_id = ? AND e2.status = 'active'
    LEFT JOIN enrollment_requests er ON c.id = er.course_id AND er.student_id = ?
    WHERE c.is_archived = 0 AND c.academic_period_id = ? 
    AND (c.sections IS NULL OR JSON_SEARCH(c.sections, 'one', ?) IS NOT NULL)
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $selected_year_id, $student_section_id]);
$section_courses = $stmt->fetchAll();

// Get courses not assigned to student's section (available for enrollment requests)
$stmt = $pdo->prepare("
    SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name, 
           (SELECT JSON_LENGTH(modules) FROM courses WHERE id = c.id) as module_count,
           (SELECT COUNT(*) FROM course_enrollments e WHERE e.course_id = c.id AND e.status = 'active') as enrolled_students,
           CASE WHEN e2.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled,
           CASE WHEN er.student_id IS NOT NULL AND er.status = 'pending' THEN 1 ELSE 0 END as has_pending_request,
           CASE WHEN er.student_id IS NOT NULL AND er.status = 'rejected' THEN 1 ELSE 0 END as has_rejected_request,
           er.rejection_reason,
           er.approved_at,
           0 as is_section_assigned,
           ay.is_active as academic_period_active,
           ay.is_active as semester_active,
           ay.is_active as academic_year_active,
           ay.academic_year,
           ay.semester_name,
           c.created_at,
           c.updated_at,
           c.year_level,
           (SELECT 
               COALESCE((
                   SELECT SUM(JSON_LENGTH(JSON_EXTRACT(modules, '$[*].assessments')))
                   FROM courses 
                   WHERE id = c.id
               ), 0)
           ) as assessment_count,
           (SELECT 
               COALESCE((
                   SELECT COUNT(DISTINCT aa.assessment_id)
                   FROM assessment_attempts aa
                   JOIN assessments a ON aa.assessment_id = a.id
                   WHERE a.course_id = c.id AND aa.student_id = ? AND aa.status = 'completed'
               ), 0)
           ) as finished_assessments
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    JOIN academic_periods ay ON c.academic_period_id = ay.id
    LEFT JOIN course_enrollments e2 ON c.id = e2.course_id AND e2.student_id = ? AND e2.status = 'active'
    LEFT JOIN enrollment_requests er ON c.id = er.course_id AND er.student_id = ?
    WHERE c.is_archived = 0 AND c.academic_period_id = ? 
    AND (c.sections IS NULL OR JSON_SEARCH(c.sections, 'one', ?) IS NULL)
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $selected_year_id, $student_section_id]);
$non_section_courses = $stmt->fetchAll();

// Combine both course lists
$courses = array_merge($section_courses, $non_section_courses);

// Get enrolled courses for quick access (including approved enrollment requests) for selected academic year
$stmt = $pdo->prepare("
    SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name, e.enrolled_at,
           ay.academic_year, ay.semester_name,
           ay.is_active as academic_period_active,
           ay.is_active as semester_active,
           ay.is_active as academic_year_active,
           (SELECT JSON_LENGTH(modules) FROM courses WHERE id = c.id) as module_count,
           (SELECT 
               COALESCE((
                   SELECT SUM(JSON_LENGTH(JSON_EXTRACT(modules, '$[*].assessments')))
                   FROM courses 
                   WHERE id = c.id
               ), 0)
           ) as assessment_count,
           (SELECT 
               COALESCE((
                   SELECT COUNT(DISTINCT aa.assessment_id)
                   FROM assessment_attempts aa
                   JOIN assessments a ON aa.assessment_id = a.id
                   WHERE a.course_id = c.id AND aa.student_id = ? AND aa.status = 'completed'
               ), 0)
           ) as finished_assessments,
           c.year_level, c.created_at, c.updated_at
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    JOIN course_enrollments e ON c.id = e.course_id
    JOIN academic_periods ay ON c.academic_period_id = ay.id
    WHERE e.student_id = ? AND e.status = 'active' AND c.is_archived = 0 AND c.academic_period_id = ?
    ORDER BY e.enrolled_at ASC
");
$stmt->execute([$user_id, $user_id, $selected_year_id]);
$enrolled_courses = $stmt->fetchAll();

// Debug: Let's see the enrolled courses with their timestamps
if (!empty($enrolled_courses)) {
    error_log("Enrolled courses order:");
    foreach ($enrolled_courses as $course) {
        error_log("Course: " . $course['course_name'] . " - Enrolled at: " . $course['enrolled_at']);
    }
}

// Filter courses for different sections
// Section-assigned courses that student is NOT enrolled in (for "Other Available Courses")
$section_available_courses = array_filter($section_courses, function($course) {
    return !$course['is_enrolled'];
});

// Non-section-assigned courses (for "Browse All Courses" modal)
$non_section_available_courses = array_filter($non_section_courses, function($course) {
    return !$course['is_enrolled'];
});

// Legacy variable for backward compatibility (will be removed)
$available_courses = $section_available_courses;

// Define course themes with IT icons
$course_themes = [
    ['bg' => 'bg-primary', 'icon' => 'fas fa-code'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-database'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-network-wired'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-server'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-shield-alt'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-cloud'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-microchip'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-laptop-code'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-mobile-alt'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-wifi'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-keyboard'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-bug'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-terminal'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-cogs'],
    ['bg' => 'bg-info', 'icon' => 'fas fa-rocket'],
    ['bg' => 'bg-warning', 'icon' => 'fas fa-robot'],
    ['bg' => 'bg-danger', 'icon' => 'fas fa-brain'],
    ['bg' => 'bg-secondary', 'icon' => 'fas fa-chart-line'],
    ['bg' => 'bg-primary', 'icon' => 'fas fa-fire'],
    ['bg' => 'bg-success', 'icon' => 'fas fa-lightbulb']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .course-card {
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .course-image {
            height: 240px;
            object-fit: cover;
        }
        .enrolled-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
        
        /* Course Status Badge Styling */
        .course-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .course-status-badge.bg-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
            color: white;
        }
        
        .course-status-badge.bg-success {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%) !important;
            color: white;
        }
        
        .course-status-badge.bg-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
            color: #212529;
        }
        
        .course-status-badge.bg-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
            color: white;
        }
        
        .course-status-badge.bg-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
            color: white;
        }
        
        /* Ensure badges don't interfere with course content */
        .course-card {
            overflow: visible;
        }
        
        .course-card .card-img-top {
            overflow: visible;
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
        }
        
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap');
        
        /* Button Hover Effects */
        .btn:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-primary:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-success:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-secondary:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
        .btn-outline-danger:hover {
            background-color: #1e5631 !important;
            border-color: #1e5631 !important;
            color: white !important;
        }
        
                 .btn-outline-warning:hover {
             background-color: #1e5631 !important;
             border-color: #1e5631 !important;
             color: white !important;
         }
         
         /* Ensure rejection reason modal content stays visible */
         #rejectionReasonModal .modal-content {
             opacity: 1 !important;
             visibility: visible !important;
         }
         
         #rejectionReasonModal .modal-body {
             opacity: 1 !important;
             visibility: visible !important;
         }
         
         #rejectionReasonModal #rejectionReason {
             opacity: 1 !important;
             visibility: visible !important;
             color: #856404 !important;
             font-weight: 500 !important;
         }
         
                   /* Prevent any fade effects on modal content */
          .modal.fade .modal-dialog {
              transition: none !important;
          }
          
          .modal.fade .modal-content {
              transition: none !important;
          }
          
          /* Force left alignment for rejection reason text */
          #rejectionReasonModal .alert-warning {
              text-align: left !important;
          }
          
          #rejectionReasonModal #rejectionReason {
              text-align: left !important;
              text-align-last: left !important;
              direction: ltr !important;
          }
          
          /* Override any Bootstrap alert centering */
          .alert {
              text-align: left !important;
          }
          
          .alert-warning {
              text-align: left !important;
          }
          
          /* Specific classes for rejection reason */
          .rejection-reason-alert {
              text-align: left !important;
              text-align-last: left !important;
          }
          
          .rejection-reason-text {
              text-align: left !important;
              text-align-last: left !important;
              direction: ltr !important;
              unicode-bidi: normal !important;
          }

        .academic-year-inactive {
            color: #dc3545;
        }
        
        /* Loading States */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .loading::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Real-time Update Indicators */
        .update-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
            display: none;
        }
        
        .update-indicator.show {
            display: block;
        }
        
        /* Refresh Button Animation */
        .btn-refresh {
            transition: transform 0.3s ease;
        }
        
        .btn-refresh:active {
            transform: rotate(180deg);
        }
        
        .btn-refresh.loading {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Toast Notifications */
        .toast-container {
            z-index: 1060;
        }
        
        .toast {
            min-width: 300px;
        }
        
        /* Course Card Update Animation */
        .course-card.updating {
            animation: pulse 0.5s ease-in-out;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        /* Course Slider Styles */
        .course-slider-container {
            position: relative;
            overflow: hidden;
            margin: 0 -15px;
            padding: 0 15px;
        }

        .course-slider {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            gap: 1rem;
            padding: 0.5rem 0;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        .course-slider::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .course-slide {
            flex: 0 0 400px;
            min-width: 400px;
            max-width: 400px;
        }

        @media (max-width: 768px) {
            .course-slide {
                flex: 0 0 360px;
                min-width: 360px;
                max-width: 360px;
            }
        }

        @media (max-width: 480px) {
            .course-slide {
                flex: 0 0 320px;
                min-width: 320px;
                max-width: 320px;
            }
        }

        /* Ensure all course cards have the same height */
        .course-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .course-card .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .course-card .card-text {
            flex: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            line-height: 1.4;
            max-height: 4.2em; /* 3 lines * 1.4 line-height */
        }

        /* Ensure buttons stay at the bottom */
        .course-card .btn {
            margin-top: auto;
        }

        /* Ensure consistent spacing for course statistics */
        .course-card .row.text-center {
            margin-bottom: 1rem;
        }

        /* Ensure course details section has consistent height */
        .course-card .row.text-center.mb-3 {
            min-height: 60px;
            display: flex;
            align-items: center;
        }

        /* Ensure course statistics section has consistent height */
        .course-card .row.text-center.mb-3:last-of-type {
            min-height: 50px;
        }

        /* Ensure enrollment date section has consistent height */
        .course-card .text-center.mb-3 {
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Ensure semester status warning has consistent height */
        .course-card .alert-warning.alert-sm {
            min-height: 60px;
            display: flex;
            align-items: center;
        }

        .slider-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
        }

        .slider-nav:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: translateY(-50%) scale(1.1);
        }

        .slider-nav.prev {
            left: 10px;
        }

        .slider-nav.next {
            right: 10px;
        }

        .slider-nav:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .slider-nav:disabled:hover {
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.7);
        }

        /* Section headers with slider controls */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .slider-controls {
            display: flex;
            gap: 0.5rem;
        }

        .slider-dots {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .slider-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #dee2e6;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .slider-dot.active {
            background: #2E5E4E;
        }

        .slider-dot:hover {
            background: #7DCB80;
        }

        /* Ensure modal course cards also have consistent heights */
        .modal .course-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .modal .course-card .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .modal .course-card .card-text {
            flex: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-height: 1.4;
            max-height: 2.8em; /* 2 lines * 1.4 line-height */
        }

        .modal .course-card .btn {
            margin-top: auto;
        }

        /* Ensure modal course statistics have consistent height */
        .modal .course-card .row.text-center.mb-2 {
            min-height: 50px;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Removed Sidebar -->
            <!-- Main content -->
            <main class="col-12 px-md-4">
               <!--  <div class="d-flex justify-content-end mb-3">
                    <a href="add_course.php" class="btn btn-success btn-lg">
                        <i class="fas fa-plus"></i> Enroll / Add Course
                    </a>
                </div>-->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Courses</h1>
                    <div>
                        <button id="refresh-courses" class="btn btn-outline-primary btn-sm btn-refresh" title="Refresh course data">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
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

                <!-- Course Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                                <h4 id="enrolled-courses"><?php echo count($enrolled_courses); ?></h4>
                                <small>Enrolled Courses</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-book fa-2x mb-2"></i>
                                <h4 id="available-section"><?php echo count($section_available_courses); ?></h4>
                                <small>Available in Section</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-globe fa-2x mb-2"></i>
                                <h4 id="other-sections"><?php echo count($non_section_available_courses); ?></h4>
                                <small>Other Sections</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                <h4 id="inactive-periods"><?php echo count(array_filter($courses, function($c) { return !$c['semester_active'] || !$c['academic_year_active']; })); ?></h4>
                                <small>Inactive Periods</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Semester Status Legend -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Course Status Legend</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-success me-2">●</span>
                                        <small>Active Semester - Full access to assessments and content</small>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-warning me-2">●</span>
                                        <small>Inactive Semester - View-only access for review</small>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-primary me-2">●</span>
                                        <small>Enrolled - You have access to this course</small>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-info me-2">●</span>
                                        <small>Available - You can request enrollment (requires teacher approval)</small>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <small class="text-muted">
                                            <i class="fas fa-lightbulb me-1"></i>
                                            <strong>Note:</strong> All course enrollments require teacher verification to prevent assessment leaks. Courses in inactive semesters cannot be enrolled in and assessments cannot be taken, but all content remains accessible for review purposes.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Enrolled Courses Section -->
                <?php if (!empty($enrolled_courses)): ?>
                    <div class="mb-4">
                        <div class="section-header">
                            <h3>My Enrolled Courses</h3>
                            <div class="slider-controls">
                                <button class="btn btn-outline-secondary btn-sm" id="enrolled-prev">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" id="enrolled-next">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="course-slider-container">
                            <div class="course-slider" id="enrolled-slider">
                                <?php foreach ($enrolled_courses as $index => $course): ?>
                                    <?php
                                        $theme = $course_themes[$course['id'] % count($course_themes)];
                                    ?>
                                    <div class="course-slide">
                                        <div class="card course-card position-relative h-100">
                                            <!-- Course Status Badge - Top Right -->
                                            <div class="course-status-badge bg-primary">
                                                <i class="fas fa-user-check"></i> Enrolled
                                            </div>
                                            
                                            <div class="card-img-top course-image d-flex align-items-center justify-content-center <?php echo $theme['bg']; ?>" style="height: 200px; position: relative; overflow: hidden;">
                                                <i class="<?php echo $theme['icon']; ?>" style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0.4; pointer-events: none; font-size: 10rem; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6);"></i>
                                                <h2 class="course-code-text">
                                                    <?php echo htmlspecialchars($course['course_code'] ?? 'N/A'); ?>
                                                </h2>
                                            </div>
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></h5>
                                                <p class="card-text text-muted">by <?php echo htmlspecialchars($course['teacher_name'] ?? ''); ?></p>
                                                
                                                <!-- Course Details -->
                                                <div class="row text-center mb-3">
                                                    <div class="col-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar-alt"></i><br>
                                                            <?php echo htmlspecialchars($course['academic_year'] ?? 'N/A'); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock"></i><br>
                                                            <?php echo htmlspecialchars($course['semester_name'] ?? 'N/A'); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-layer-group"></i><br>
                                                            <?php echo $course['module_count'] ?? 0; ?> modules
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <p class="card-text"><?php echo htmlspecialchars(substr($course['description'] ?? '', 0, 100)); ?>...</p>
                                                
                                                <!-- Course Statistics -->
                                                <div class="row text-center mb-3">
                                                    <div class="col-6">
                                                        <small class="text-muted">
                                                            <i class="fas fa-tasks"></i><br>
                                                            <?php echo $course['assessment_count'] ?? 0; ?> assessments
                                                        </small>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">
                                                            <i class="fas fa-check-circle"></i><br>
                                                            <?php echo $course['finished_assessments'] ?? 0; ?> finished
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <!-- Enrollment Date -->
                                                <div class="text-center mb-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar-check"></i> Enrolled: <?php echo date('M j, Y', strtotime($course['enrolled_at'])); ?>
                                                    </small>
                                                </div>
                                                
                                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary w-100">
                                                    Continue Learning
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Other Available Courses Section (Section-Assigned Only) -->
                <div class="mb-4">
                    <div class="section-header">
                        <h3><?php echo empty($enrolled_courses) ? 'Available Courses' : 'Other Available Courses'; ?></h3>
                        <div class="slider-controls">
                            <button class="btn btn-outline-secondary btn-sm" id="available-prev">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="available-next">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    <p class="text-muted mb-3">
                        <i class="fas fa-info-circle"></i> These are courses available in your assigned section. All enrollments require teacher verification to prevent assessment leaks.
                    </p>
                    <?php if (empty($section_available_courses)): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle"></i> No additional courses are currently available in your section.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php else: ?>
                        <div class="course-slider-container">
                            <div class="course-slider" id="available-slider">
                                <?php foreach ($section_available_courses as $index => $course): ?>
                                    <?php
                                        $theme = $course_themes[$course['id'] % count($course_themes)];
                                    ?>
                                    <div class="course-slide">
                                        <div class="card course-card position-relative h-100">
                                            <!-- Course Status Badge - Top Right -->
                                            <div class="course-status-badge <?php 
                                                if ($course['is_enrolled']): 
                                                    echo 'bg-primary';
                                                elseif ($course['has_pending_request']): 
                                                    echo 'bg-warning';
                                                elseif ($course['has_rejected_request']): 
                                                    echo 'bg-danger';
                                                elseif (!$course['semester_active'] || !$course['academic_year_active']): 
                                                    echo 'bg-warning';
                                                else: 
                                                    echo 'bg-info';
                                                endif; 
                                            ?>">
                                                <?php if ($course['is_enrolled']): ?>
                                                    <i class="fas fa-user-check"></i> Enrolled
                                                <?php elseif ($course['has_pending_request']): ?>
                                                    <i class="fas fa-clock"></i> Pending
                                                <?php elseif ($course['has_rejected_request']): ?>
                                                    <i class="fas fa-times-circle"></i> Rejected
                                                <?php elseif (!$course['semester_active'] || !$course['academic_year_active']): ?>
                                                    <i class="fas fa-exclamation-triangle"></i> Inactive Semester
                                                <?php else: ?>
                                                    <i class="fas fa-plus-circle"></i> Available
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Semester Status Indicator - Top Left -->
                                            <div class="position-absolute top-0 start-0 m-2">
                                                <div class="d-flex flex-column align-items-start">
                                                    <span class="badge <?php echo $course['academic_year_active'] ? 'bg-success' : 'bg-danger'; ?> mb-1">
                                                        <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($course['academic_year'] ?? 'N/A'); ?>
                                                    </span>
                                                    <span class="badge <?php echo $course['semester_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                        <i class="fas fa-clock"></i> <?php echo htmlspecialchars($course['semester_name'] ?? 'N/A'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($course['is_enrolled']): ?>
                                                <div class="enrolled-badge">
                                                    <span class="badge bg-success">Enrolled</span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="card-img-top course-image d-flex align-items-center justify-content-center <?php echo $theme['bg']; ?>" style="height: 200px; position: relative; overflow: hidden;">
                                                <i class="<?php echo $theme['icon']; ?>" style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0.4; pointer-events: none; font-size: 10rem; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6);"></i>
                                                <h2 class="course-code-text">
                                                    <?php echo htmlspecialchars($course['course_code'] ?? 'N/A'); ?>
                                                </h2>
                                            </div>
                                            
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></h5>
                                                <p class="card-text text-muted">by <?php echo htmlspecialchars($course['teacher_name'] ?? ''); ?></p>
                                                
                                                <!-- Course Details -->
                                                <div class="row text-center mb-3">
                                                    <div class="col-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar-alt"></i><br>
                                                            <?php echo htmlspecialchars($course['academic_year'] ?? 'N/A'); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock"></i><br>
                                                            <?php echo htmlspecialchars($course['semester_name'] ?? 'N/A'); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-layer-group"></i><br>
                                                            <?php echo $course['module_count']; ?> modules
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <p class="card-text"><?php echo htmlspecialchars(substr($course['description'] ?? '', 0, 100)); ?>...</p>
                                                
                                                <!-- Course Statistics -->
                                                <div class="row text-center mb-3">
                                                    <div class="col-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-tasks"></i><br>
                                                            <?php echo $course['assessment_count'] ?? 0; ?> assessments
                                                        </small>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-users"></i><br>
                                                            <?php echo $course['enrolled_students']; ?> students
                                                        </small>
                                                    </div>
                                                    <div class="col-4">
                                                        <small class="text-muted">
                                                            <i class="fas fa-check-circle"></i><br>
                                                            <?php echo $course['finished_assessments'] ?? 0; ?> finished
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <!-- Course Creation Info -->
                                                <div class="text-center mb-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar-plus"></i> Created: <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                                                    </small>
                                                </div>
                                                
                                                <!-- Semester Status Warning -->
                                                <?php if (!$course['semester_active'] || !$course['academic_year_active']): ?>
                                                    <div class="alert alert-warning alert-sm mb-3">
                                                        <small>
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                            <?php if (!$course['academic_year_active']): ?>
                                                                <strong>Academic Year <?php echo htmlspecialchars($course['academic_year'] ?? 'N/A'); ?> is inactive.</strong><br>
                                                            <?php endif; ?>
                                                            <?php if (!$course['semester_active']): ?>
                                                                <strong>Semester <?php echo htmlspecialchars($course['semester_name'] ?? 'N/A'); ?> is inactive.</strong><br>
                                                            <?php endif; ?>
                                                            This course is view-only for review purposes.
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($course['is_enrolled']): ?>
                                                    <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary w-100">
                                                        Continue Learning
                                                    </a>
                                                <?php elseif ($course['is_section_assigned']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                                        <button type="submit" name="enroll_course" class="btn btn-outline-primary w-100" <?php echo (!$course['semester_active'] || !$course['academic_year_active']) ? 'disabled' : ''; ?>>
                                                            <i class="fas fa-plus"></i> Request Enrollment
                                                        </button>
                                                    </form>
                                                <?php elseif ($course['has_pending_request']): ?>
                                                    <button class="btn btn-warning w-100" disabled>
                                                        <i class="fas fa-clock"></i> Request Pending
                                                    </button>
                                                <?php elseif ($course['has_rejected_request']): ?>
                                                    <button class="btn btn-danger w-100" disabled>
                                                        <i class="fas fa-times-circle"></i> Request Rejected
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info w-100 mt-2" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#rejectionReasonModal" 
                                                            data-course-name="<?php echo htmlspecialchars($course['course_name']); ?>"
                                                            data-rejection-reason="<?php echo htmlspecialchars($course['rejection_reason'] ?? ''); ?>"
                                                            data-request-date="<?php echo isset($course['approved_at']) ? date('M j, Y g:i A', strtotime($course['approved_at'])) : ''; ?>">
                                                        <i class="fas fa-eye"></i> View Reason
                                                    </button>
                                                    <form method="POST" class="d-inline mt-2">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                                        <button type="submit" name="enroll_course" class="btn btn-outline-primary w-100">
                                                            <i class="fas fa-redo"></i> Request Again
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                                        <button type="submit" name="enroll_course" class="btn btn-outline-primary w-100" <?php echo (!$course['semester_active'] || !$course['academic_year_active']) ? 'disabled' : ''; ?>>
                                                            <i class="fas fa-plus"></i> Request Enrollment
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Browse Courses from Other Sections -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Browse Courses from Other Sections</h3>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollCourseModal">
                            <i class="fas fa-plus"></i> Browse All Courses
                        </button>
                    </div>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle"></i> You can request enrollment in courses from other sections. Click "Browse All Courses" to see all available courses outside your assigned section.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Browse All Courses Modal (Courses from Other Sections) -->
    <div class="modal fade" id="enrollCourseModal" tabindex="-1" aria-labelledby="enrollCourseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="enrollCourseModalLabel">
                            <i class="fas fa-globe me-2"></i>Browse Courses from Other Sections
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle"></i> These are courses from other sections. All enrollments require teacher verification to prevent assessment leaks.
                        </div>
                        <input type="text" id="modalCourseFilter" class="form-control mb-3" placeholder="Filter by course name or teacher...">
                        <div class="row" id="modalCourseList">
                            <?php
                            // Use the pre-filtered non-section courses for the modal
                            $modal_courses = $non_section_available_courses;
                            ?>
                            
                            <?php if (empty($modal_courses)): ?>
                                <div class="col-12">
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle"></i> No courses from other sections are currently available.
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($modal_courses as $index => $course): ?>
                                <?php
                                    $theme = $course_themes[$course['id'] % count($course_themes)];
                                ?>
                                <div class="col-md-6 col-lg-4 mb-3 modal-course-item">
                                    <div class="card h-100 position-relative">
                                        <!-- Course Status Badge - Top Right -->
                                        <div class="course-status-badge <?php 
                                            if ($course['is_enrolled']): 
                                                echo 'bg-primary';
                                            elseif ($course['has_pending_request']): 
                                                echo 'bg-warning';
                                            elseif ($course['has_rejected_request']): 
                                                echo 'bg-danger';
                                            elseif (!$course['semester_active'] || !$course['academic_year_active']): 
                                                echo 'bg-warning';
                                            else: 
                                                echo 'bg-info';
                                            endif; 
                                        ?>">
                                            <?php if ($course['is_enrolled']): ?>
                                                <i class="fas fa-user-check"></i> Enrolled
                                            <?php elseif ($course['has_pending_request']): ?>
                                                <i class="fas fa-clock"></i> Pending
                                            <?php elseif ($course['has_rejected_request']): ?>
                                                <i class="fas fa-times-circle"></i> Rejected
                                            <?php elseif (!$course['semester_active'] || !$course['academic_year_active']): ?>
                                                <i class="fas fa-exclamation-triangle"></i> Inactive Semester
                                            <?php else: ?>
                                                <i class="fas fa-plus-circle"></i> Available
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="card-img-top course-image d-flex align-items-center justify-content-center <?php echo $theme['bg']; ?>" style="height: 120px; position: relative; overflow: hidden;">
                                                                                         <i class="<?php echo $theme['icon']; ?>" style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0.4; pointer-events: none; font-size: 8rem; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6);"></i>
                                            <h2 class="course-code-text">
                                                <?php echo htmlspecialchars($course['course_code'] ?? 'N/A'); ?>
                                            </h2>
                                        </div>
                                        <div class="card-body">
                                            <h6 class="card-title course-name"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></h6>
                                            <p class="card-text text-muted small teacher-name">by <?php echo htmlspecialchars($course['teacher_name'] ?? ''); ?></p>
                                            <p class="card-text small"><?php echo htmlspecialchars(substr($course['description'] ?? '', 0, 80)); ?>...</p>
                                            <div class="row text-center mb-2">
                                                <div class="col-6">
                                                    <small class="text-muted">
                                                        <i class="fas fa-layer-group"></i><br>
                                                        <?php echo $course['module_count']; ?> modules
                                                    </small>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">
                                                        <i class="fas fa-users"></i><br>
                                                        <?php echo $course['enrolled_students']; ?> students
                                                    </small>
                                                </div>
                                            </div>
                                            <?php if ($course['is_enrolled']): ?>
                                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary w-100">
                                                    Continue Learning
                                                </a>
                                            <?php elseif ($course['is_section_assigned']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                                    <button type="submit" name="enroll_course" class="btn btn-outline-primary w-100">
                                                        <i class="fas fa-plus"></i> Request Enrollment
                                                    </button>
                                                </form>
                                            <?php elseif ($course['has_pending_request']): ?>
                                                <button class="btn btn-warning w-100" disabled>
                                                    <i class="fas fa-clock"></i> Request Pending
                                                </button>
                                            <?php elseif ($course['has_rejected_request']): ?>
                                                <button class="btn btn-danger w-100" disabled>
                                                    <i class="fas fa-times-circle"></i> Request Rejected
                                                </button>
                                                <form method="POST" class="d-inline mt-2">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                                    <button type="submit" name="enroll_course" class="btn btn-outline-primary w-100">
                                                        <i class="fas fa-redo"></i> Request Again
                                                    </button>
                                                </form>
                                                <?php if (!empty($course['rejection_reason'])): ?>
                                                    <small class="text-muted d-block mt-1">
                                                        <i class="fas fa-info-circle"></i> Reason: <?php echo htmlspecialchars($course['rejection_reason']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                                    <button type="submit" name="enroll_course" class="btn btn-outline-primary w-100">
                                                        <i class="fas fa-plus"></i> Request Enrollment
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="rejectionReasonModal" tabindex="-1" aria-labelledby="rejectionReasonModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectionReasonModalLabel">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Rejection Reason
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="fw-bold text-muted">Course:</label>
                                <p class="mb-0" id="rejectionCourseName"></p>
                            </div>
                                                                                     <div class="mb-3">
                                <label class="fw-bold text-muted">Rejection Reason:</label>
                                <div class="alert alert-warning rejection-reason-alert" role="alert" style="opacity: 1 !important; visibility: visible !important; background-color: #fff3cd !important; border-color: #ffeaa7 !important; text-align: left !important;">
                                    <div id="rejectionReason" class="rejection-reason-text" style="opacity: 1 !important; visibility: visible !important; color: #856404 !important; font-weight: 500 !important; font-size: 14px !important; line-height: 1.5 !important; display: block !important; min-height: 20px !important; text-align: left !important;"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold text-muted">Request Date:</label>
                                <p class="mb-0" id="rejectionRequestDate"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container for Notifications -->
    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    var filterInput = document.getElementById('modalCourseFilter');
    var courseItems = document.querySelectorAll('#modalCourseList .modal-course-item');
    filterInput.addEventListener('input', function() {
        var filter = filterInput.value.toLowerCase();
        courseItems.forEach(function(item) {
            var name = item.querySelector('.course-name').textContent.toLowerCase();
            var teacher = item.querySelector('.teacher-name').textContent.toLowerCase();
            if (name.includes(filter) || teacher.includes(filter)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Rejection Reason Modal
    var rejectionModal = document.getElementById('rejectionReasonModal');
    
    // Set content immediately when modal is triggered
    rejectionModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var courseName = button.getAttribute('data-course-name');
        var rejectionReason = button.getAttribute('data-rejection-reason');
        var requestDate = button.getAttribute('data-request-date');

        // Set the content immediately with forced visibility
        var courseNameElement = document.getElementById('rejectionCourseName');
        var rejectionReasonElement = document.getElementById('rejectionReason');
        var requestDateElement = document.getElementById('rejectionRequestDate');
        
        courseNameElement.textContent = courseName || 'Unknown Course';
        courseNameElement.style.opacity = '1';
        courseNameElement.style.visibility = 'visible';
        
                         rejectionReasonElement.innerHTML = rejectionReason || 'No reason provided';
        rejectionReasonElement.style.opacity = '1';
        rejectionReasonElement.style.visibility = 'visible';
        rejectionReasonElement.style.color = '#856404';
        rejectionReasonElement.style.fontWeight = '500';
        rejectionReasonElement.style.fontSize = '14px';
        rejectionReasonElement.style.lineHeight = '1.5';
        rejectionReasonElement.style.display = 'block';
        rejectionReasonElement.style.minHeight = '20px';
        rejectionReasonElement.style.textAlign = 'left';
        rejectionReasonElement.style.textAlignLast = 'left';
        rejectionReasonElement.style.direction = 'ltr';
        rejectionReasonElement.style.unicodeBidi = 'normal';
        
        requestDateElement.textContent = requestDate || 'N/A';
        requestDateElement.style.opacity = '1';
        requestDateElement.style.visibility = 'visible';
    });

    // Ensure modal content stays visible after modal is shown
    rejectionModal.addEventListener('shown.bs.modal', function (event) {
        // Force all content to be visible
        var modalBody = rejectionModal.querySelector('.modal-body');
        var rejectionReasonElement = document.getElementById('rejectionReason');
        
        if (modalBody) {
            modalBody.style.opacity = '1';
            modalBody.style.visibility = 'visible';
        }
        
                 if (rejectionReasonElement) {
             rejectionReasonElement.style.opacity = '1';
             rejectionReasonElement.style.visibility = 'visible';
             rejectionReasonElement.style.color = '#856404';
             rejectionReasonElement.style.fontWeight = '500';
             rejectionReasonElement.style.fontSize = '14px';
             rejectionReasonElement.style.lineHeight = '1.5';
             rejectionReasonElement.style.display = 'block';
             rejectionReasonElement.style.minHeight = '20px';
             rejectionReasonElement.style.textAlign = 'left';
             rejectionReasonElement.style.textAlignLast = 'left';
             rejectionReasonElement.style.direction = 'ltr';
             rejectionReasonElement.style.unicodeBidi = 'normal';
         }
        
        // Force focus to keep modal active
        var closeButton = rejectionModal.querySelector('.btn-close');
        if (closeButton) {
            closeButton.focus();
        }
    });

    // Prevent modal from closing on backdrop click
    rejectionModal.addEventListener('click', function (event) {
        if (event.target === rejectionModal) {
            event.stopPropagation();
        }
    });
    
         // Additional event listener to ensure content stays visible
     rejectionModal.addEventListener('transitionend', function (event) {
         var rejectionReasonElement = document.getElementById('rejectionReason');
         if (rejectionReasonElement) {
             rejectionReasonElement.style.opacity = '1';
             rejectionReasonElement.style.visibility = 'visible';
             rejectionReasonElement.style.color = '#856404';
             rejectionReasonElement.style.fontWeight = '500';
             rejectionReasonElement.style.fontSize = '14px';
             rejectionReasonElement.style.lineHeight = '1.5';
             rejectionReasonElement.style.display = 'block';
             rejectionReasonElement.style.minHeight = '20px';
             rejectionReasonElement.style.textAlign = 'left';
             rejectionReasonElement.style.textAlignLast = 'left';
             rejectionReasonElement.style.direction = 'ltr';
             rejectionReasonElement.style.unicodeBidi = 'normal';
         }
     });
     
     // Set up a timer to continuously ensure the rejection reason is visible
     setInterval(function() {
         var rejectionReasonElement = document.getElementById('rejectionReason');
         if (rejectionReasonElement && rejectionReasonElement.textContent.trim() !== '') {
             rejectionReasonElement.style.opacity = '1';
             rejectionReasonElement.style.visibility = 'visible';
             rejectionReasonElement.style.color = '#856404';
             rejectionReasonElement.style.fontWeight = '500';
             rejectionReasonElement.style.fontSize = '14px';
             rejectionReasonElement.style.lineHeight = '1.5';
             rejectionReasonElement.style.display = 'block';
             rejectionReasonElement.style.minHeight = '20px';
             rejectionReasonElement.style.textAlign = 'left';
             rejectionReasonElement.style.textAlignLast = 'left';
             rejectionReasonElement.style.direction = 'ltr';
             rejectionReasonElement.style.unicodeBidi = 'normal';
         }
     }, 100);
});

// Course Slider Functionality
function initCourseSlider(sliderId, prevBtnId, nextBtnId) {
    const slider = document.getElementById(sliderId);
    const prevBtn = document.getElementById(prevBtnId);
    const nextBtn = document.getElementById(nextBtnId);
    
    if (!slider || !prevBtn || !nextBtn) return;
    
    const slides = slider.querySelectorAll('.course-slide');
    const slideWidth = 400; // Match CSS flex-basis
    const gap = 16; // Match CSS gap
    const visibleSlides = Math.floor(slider.offsetWidth / (slideWidth + gap));
    const totalSlides = slides.length;
    let currentIndex = 0;
    
    function updateButtons() {
        prevBtn.disabled = currentIndex === 0;
        nextBtn.disabled = currentIndex >= totalSlides - visibleSlides;
    }
    
    function scrollToSlide(index) {
        const scrollAmount = index * (slideWidth + gap);
        slider.scrollTo({
            left: scrollAmount,
            behavior: 'smooth'
        });
    }
    
    prevBtn.addEventListener('click', () => {
        if (currentIndex > 0) {
            currentIndex = Math.max(0, currentIndex - 1);
            scrollToSlide(currentIndex);
            updateButtons();
        }
    });
    
    nextBtn.addEventListener('click', () => {
        if (currentIndex < totalSlides - visibleSlides) {
            currentIndex = Math.min(totalSlides - visibleSlides, currentIndex + 1);
            scrollToSlide(currentIndex);
            updateButtons();
        }
    });
    
    // Handle scroll events to update current index
    slider.addEventListener('scroll', () => {
        const scrollLeft = slider.scrollLeft;
        currentIndex = Math.round(scrollLeft / (slideWidth + gap));
        updateButtons();
    });
    
    // Initialize button states
    updateButtons();
    
    // Hide navigation if all slides are visible
    if (totalSlides <= visibleSlides) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
    }
}

// Initialize sliders when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initCourseSlider('enrolled-slider', 'enrolled-prev', 'enrolled-next');
    initCourseSlider('available-slider', 'available-prev', 'available-next');
});

</script>
</body>
</html> 