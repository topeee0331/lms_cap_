<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get current academic year
    $stmt = $pdo->prepare("SELECT id FROM academic_periods WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
    $year_id = $current_year ? $current_year['id'] : null;
    
    if (!$year_id) {
        echo json_encode([]);
        exit();
    }
    
    // Fix approved enrollment requests that don't have corresponding enrollment records
    $stmt = $pdo->prepare("
        SELECT er.course_id, er.student_id 
        FROM enrollment_requests er 
        WHERE er.student_id = ? AND er.status = 'approved' 
        AND NOT EXISTS (
            SELECT 1 FROM course_enrollments ce 
            WHERE ce.student_id = er.student_id 
            AND ce.course_id = er.course_id 
            AND ce.status = 'active'
        )
    ");
    $stmt->execute([$user_id]);
    $missing_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create missing enrollment records
    foreach ($missing_enrollments as $missing) {
        try {
            $stmt = $pdo->prepare("INSERT INTO course_enrollments (student_id, course_id, enrolled_at, status) VALUES (?, ?, NOW(), 'active')");
            $stmt->execute([$missing['student_id'], $missing['course_id']]);
            error_log("Created missing enrollment record for student {$missing['student_id']} in course {$missing['course_id']}");
        } catch (PDOException $e) {
            error_log("Error creating missing enrollment record: " . $e->getMessage());
        }
    }

    // Get enrollment status for the student
    $stmt = $pdo->prepare("
        SELECT c.id as course_id, c.course_name, c.course_code, c.description,
               ap.academic_year, ap.semester_name,
               CASE WHEN JSON_SEARCH(s.students, 'one', ?) IS NOT NULL THEN 1 ELSE 0 END as is_section_assigned,
               CASE WHEN e2.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled,
               CASE WHEN er.student_id IS NOT NULL AND er.status = 'pending' THEN 1 ELSE 0 END as has_pending_request,
               CASE WHEN er.student_id IS NOT NULL AND er.status = 'rejected' THEN 1 ELSE 0 END as has_rejected_request,
               ap.is_active as academic_year_active,
               ap.is_active as semester_active
        FROM courses c
        JOIN academic_periods ap ON c.academic_period_id = ap.id
        LEFT JOIN sections s ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
        LEFT JOIN course_enrollments e2 ON c.id = e2.course_id AND e2.student_id = ? AND e2.status = 'active'
        LEFT JOIN enrollment_requests er ON c.id = er.course_id AND er.student_id = ?
        WHERE c.status = 'active'
        ORDER BY c.course_name
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statuses = [];
    foreach ($courses as $course) {
        $is_disabled = !$course['academic_year_active'] || !$course['semester_active'];
        
        if ($course['is_enrolled']) {
            $button_class = 'btn btn-primary w-100';
            $button_text = '<i class="fas fa-play"></i> Continue Learning';
            $disabled_reason = '';
        } elseif ($course['is_section_assigned']) {
            $button_class = $is_disabled ? 'btn btn-outline-primary w-100 disabled' : 'btn btn-outline-primary w-100';
            $button_text = '<i class="fas fa-plus"></i> Enroll Now';
            $disabled_reason = $is_disabled ? 'Academic period is inactive' : '';
        } elseif ($course['has_pending_request']) {
            $button_class = 'btn btn-warning w-100 disabled';
            $button_text = '<i class="fas fa-clock"></i> Request Pending';
            $disabled_reason = 'Enrollment request is pending approval';
        } elseif ($course['has_rejected_request']) {
            $button_class = 'btn btn-danger w-100 disabled';
            $button_text = '<i class="fas fa-times-circle"></i> Request Rejected';
            $disabled_reason = 'Enrollment request was rejected';
        } else {
            $button_class = $is_disabled ? 'btn btn-outline-primary w-100 disabled' : 'btn btn-outline-primary w-100';
            $button_text = '<i class="fas fa-plus"></i> Request Enrollment';
            $disabled_reason = $is_disabled ? 'Academic period is inactive' : '';
        }
        
        $statuses[] = [
            'course_id' => $course['course_id'],
            'is_enrolled' => (bool)$course['is_enrolled'],
            'has_pending_request' => (bool)$course['has_pending_request'],
            'has_rejected_request' => (bool)$course['has_rejected_request'],
            'is_section_assigned' => (bool)$course['is_section_assigned'],
            'academic_year_active' => (bool)$course['academic_year_active'],
            'semester_active' => (bool)$course['semester_active'],
            'is_disabled' => $is_disabled,
            'button_class' => $button_class,
            'button_text' => $button_text,
            'disabled_reason' => $disabled_reason
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'statuses' => $statuses,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
