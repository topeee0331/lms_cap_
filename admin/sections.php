<?php
$page_title = 'Manage Sections';
require_once '../config/config.php';
requireRole('admin');
require_once '../includes/header.php';
?>

<style>
/* Import Google Fonts for professional typography */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

/* Enhanced Sections Page Styling - Inspired by Admin Dashboard */
:root {
    --main-green: #2E5E4E;
    --accent-green: #7DCB80;
    --highlight-yellow: #FFE066;
    --off-white: #F7FAF7;
    --white: #FFFFFF;
    --text-dark: #2c3e50;
    --text-muted: #6c757d;
    --border-light: #e9ecef;
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 8px rgba(0,0,0,0.12);
    --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
    --border-radius: 8px;
    --border-radius-lg: 12px;
    --border-radius-xl: 20px;
    --transition: all 0.3s ease;
}

/* Page Background */
.page-container {
    background: var(--off-white);
    min-height: 100vh;
}

/* Enhanced Welcome Section */
.welcome-section {
    background: var(--main-green);
    border-radius: var(--border-radius-xl);
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
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
    font-family: 'Inter', sans-serif;
}

.welcome-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: 1.1rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
}

/* Decorative Elements */
.welcome-decoration {
    position: absolute;
    top: 25px;
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

.floating-shapes {
    position: absolute;
    top: 20px;
    right: 100px;
    width: 80px;
    height: 80px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
    z-index: 0;
}

.welcome-section .accent-line {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--accent-green);
    border-radius: 0 0 var(--border-radius-xl) var(--border-radius-xl);
}

/* Statistics Cards Styling - Inspired by Dashboard */
.stats-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.stats-icon {
    width: 60px;
    height: 60px;
    transition: all 0.3s ease;
}

.stats-card:hover .stats-icon {
    transform: scale(1.1);
}

.stats-primary {
    background: #0d6efd;
    border-left: 4px solid #0a58ca;
    color: white;
}

.stats-success {
    background: #198754;
    border-left: 4px solid #146c43;
    color: white;
}

.stats-info {
    background: #0dcaf0;
    border-left: 4px solid #0aa2c0;
    color: white;
}

.stats-warning {
    background: #ffc107;
    border-left: 4px solid #ffca2c;
    color: #000;
}

.stats-secondary {
    background: #6c757d;
    border-left: 4px solid #5c636a;
    color: white;
}

.stats-danger {
    background: #dc3545;
    border-left: 4px solid #b02a37;
    color: white;
}

.stats-danger-alt {
    background: #e91e63;
    border-left: 4px solid #d81b60;
    color: white;
}

.stats-purple {
    background: #9c27b0;
    border-left: 4px solid #7b1fa2;
    color: white;
}

/* Search and Filter Section */
.search-filter-card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.search-filter-card .card-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 2px solid var(--accent-green);
    padding: 1.25rem 1.5rem;
}

.search-filter-card .card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 1.1rem;
}

/* Table Container with Scrollable Table */
.table-container {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    overflow: hidden;
}

.table-container .card-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-bottom: 2px solid var(--accent-green);
    padding: 1.25rem 1.5rem;
}

.table-container .card-header h5 {
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 1.1rem;
}

/* Scrollable Table */
.scrollable-table {
    overflow-x: auto;
    max-height: 600px;
    overflow-y: auto;
}

.scrollable-table::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.scrollable-table::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.scrollable-table::-webkit-scrollbar-thumb {
    background: var(--main-green);
    border-radius: 4px;
}

.scrollable-table::-webkit-scrollbar-thumb:hover {
    background: var(--accent-green);
}

.scrollable-table {
    scrollbar-width: thin;
    scrollbar-color: var(--main-green) #f1f1f1;
}

/* Table Styling */
.table {
    margin-bottom: 0;
    min-width: 1200px; /* Ensure minimum width for proper display */
}

.table thead th {
    background: #f8f9fa;
    border-bottom: 2px solid var(--accent-green);
    font-weight: 600;
    color: var(--text-dark);
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.table tbody td {
    vertical-align: middle;
    white-space: nowrap;
}

/* Back Button */
.back-btn {
    background: var(--main-green);
    border: none;
    color: white;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.back-btn:hover {
    background: var(--accent-green);
    color: var(--main-green);
    transform: translateY(-1px);
}

/* Action Buttons */
.btn-sm {
    border-radius: var(--border-radius);
    font-weight: 500;
    transition: var(--transition);
    border: none;
}

.btn-sm:hover {
    transform: translateY(-1px);
}

/* Solid Action Button Styles */
.btn-outline-info {
    background: #0dcaf0;
    color: white;
    border: none;
}

.btn-outline-info:hover {
    background: #0aa2c0;
    color: white;
}

.btn-outline-primary {
    background: #0d6efd;
    color: white;
    border: none;
}

.btn-outline-primary:hover {
    background: #0b5ed7;
    color: white;
}

.btn-outline-warning {
    background: #ffc107;
    color: #000;
    border: none;
}

.btn-outline-warning:hover {
    background: #ffca2c;
    color: #000;
}

.btn-outline-success {
    background: #198754;
    color: white;
    border: none;
}

.btn-outline-success:hover {
    background: #146c43;
    color: white;
}

.btn-outline-danger {
    background: #dc3545;
    color: white;
    border: none;
}

.btn-outline-danger:hover {
    background: #bb2d3b;
    color: white;
}

.btn-outline-secondary {
    background: #6c757d;
    color: white;
    border: none;
}

.btn-outline-secondary:hover {
    background: #5c636a;
    color: white;
}

/* Add Section Button */
.add-section-btn {
    background: var(--main-green);
    border: none;
    color: white;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: var(--transition);
}

.add-section-btn:hover {
    background: var(--accent-green);
    color: var(--main-green);
    transform: translateY(-1px);
}

/* Modal Styling */
.modal-content {
    border-radius: var(--border-radius-lg);
    border: none;
    box-shadow: var(--shadow-lg);
}

.modal-header {
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
}

/* Enhanced Modal Styling */
.modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    overflow: hidden;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.modal-header {
    background: var(--main-green);
    border: none;
    padding: 1.5rem;
}

.modal-title {
    font-weight: 700;
    font-size: 1.3rem;
    margin: 0;
}

.modal-body {
    padding: 2rem;
    background: #fafbfc;
    flex: 1;
    overflow-y: auto;
    max-height: calc(90vh - 140px);
}

.modal-footer {
    background: #f8f9fa;
    border-top: 2px solid #e9ecef;
    padding: 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    min-height: 80px;
    align-items: center;
    flex-shrink: 0;
    position: sticky;
    bottom: 0;
}

.modal-footer .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

/* Enhanced Form Styling */
.form-label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
    font-size: 0.95rem;
}

.form-control:focus, .form-select:focus {
    border-color: var(--main-green);
    box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.15);
}

/* Course Search and Selection */
.course-search-container {
    position: relative;
    margin-bottom: 1rem;
}

.course-search-input {
    padding-right: 2.5rem;
}

.course-search-clear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    display: none;
    z-index: 10;
}

.course-search-clear:hover {
    color: var(--main-green);
}

.courses-selection-container {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    background: white;
    max-height: 200px;
    overflow-y: auto;
    padding: 1rem;
}

.course-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: #f8f9fa;
    transition: all 0.3s ease;
    cursor: pointer;
}

.course-item:hover {
    background: #e9ecef;
    border-color: var(--accent-green);
}

.course-item.selected {
    background: rgba(46, 94, 78, 0.1);
    border-color: var(--main-green);
}

.course-checkbox {
    margin-right: 0.75rem;
    transform: scale(1.1);
}

.course-info {
    flex: 1;
}

.course-name {
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 0.95rem;
}

.course-code {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0;
}

.course-description {
    color: #6c757d;
    font-size: 0.8rem;
    margin: 0.25rem 0 0 0;
    font-style: italic;
}

/* Course Count Badge */
.courses-count-badge {
    background: var(--main-green);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

/* Loading States */
.courses-loading {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.courses-loading .spinner-border {
    width: 2rem;
    height: 2rem;
    border-width: 0.2rem;
}

/* No Courses State */
.no-courses-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-muted);
}

.no-courses-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #dee2e6;
}

/* Selected Courses Summary */
.selected-courses-summary {
    background: rgba(46, 94, 78, 0.05);
    border: 1px solid rgba(46, 94, 78, 0.2);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

/* Course Badge Styling */
.course-badge {
    transition: all 0.2s ease;
    cursor: pointer;
}

.course-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    opacity: 0.9;
}

.course-badge:active {
    transform: translateY(0);
}

.selected-courses-count {
    font-weight: 600;
    color: var(--main-green);
    margin-bottom: 0.5rem;
}

.selected-courses-list {
    max-height: 100px;
    overflow-y: auto;
    font-size: 0.85rem;
}

.selected-course-tag {
    display: inline-block;
    background: var(--main-green);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    margin: 0.25rem 0.25rem 0.25rem 0;
    font-size: 0.75rem;
}

/* Enhanced Button Styling */
.btn-primary {
    background: var(--main-green);
    border: 2px solid var(--main-green);
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
    min-width: 120px;
}

.btn-primary:hover {
    background: var(--accent-green);
    border-color: var(--accent-green);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(46, 94, 78, 0.3);
}

.btn-secondary {
    background: #6c757d;
    border: 2px solid #6c757d;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
    min-width: 120px;
}

.btn-secondary:hover {
    background: #5c636a;
    border-color: #5c636a;
    color: white;
    transform: translateY(-1px);
}

/* Form Section Styling */
.form-section {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.form-section-title {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--accent-green);
    display: flex;
    align-items: center;
}

.form-section-title i {
    margin-right: 0.5rem;
    color: var(--main-green);
}

/* Responsive Design */
@media (max-width: 768px) {
    .welcome-title {
        font-size: 2rem;
    }
    
    .stats-card .card-body {
        padding: 1rem;
    }
    
    .search-filter-card .card-header,
    .table-container .card-header {
        padding: 1rem;
    }
    
    .modal-body {
        padding: 1.5rem;
        max-height: calc(90vh - 120px);
    }
    
    .courses-selection-container {
        max-height: 150px;
    }
    
    .modal-footer {
        padding: 1rem;
        min-height: 60px;
    }
}
</style>

<?php

// Helper function to format year with proper ordinal suffix
function formatYear($year) {
    $suffixes = [
        1 => 'st',
        2 => 'nd', 
        3 => 'rd',
        4 => 'th'
    ];
    return $year . ($suffixes[$year] ?? 'th') . ' Year';
}

// Fetch all active courses for assignment
$courses_for_assignment = [];
$course_stmt = $db->prepare("SELECT id, course_name, course_code FROM courses WHERE is_archived = 0 ORDER BY course_name");
$course_stmt->execute();
$courses_for_assignment = $course_stmt->fetchAll();

// Fetch all academic periods for assignment
$academic_periods = [];
$period_stmt = $db->prepare("SELECT id, CONCAT(academic_year, ' - ', semester_name) as period_name FROM academic_periods ORDER BY academic_year DESC, semester_name");
$period_stmt->execute();
$academic_periods = $period_stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_section'])) {
        $section_code = trim($_POST['section_code']);
        $section_year = intval($_POST['section_year']);
        $academic_period_id = intval($_POST['academic_period_id']);
        $section_description = trim($_POST['section_description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($section_code) && $section_year >= 1 && $section_year <= 4 && $academic_period_id > 0) {
            $stmt = $db->prepare("INSERT INTO sections (section_name, year_level, academic_period_id, description, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$section_code, $section_year, $academic_period_id, $section_description, $is_active]);
            $section_id = $db->lastInsertId();
            // Handle course assignments - update courses.sections JSON column
            $assigned_courses = $_POST['assigned_courses'] ?? [];
            if (!empty($assigned_courses)) {
                foreach ($assigned_courses as $course_id) {
                    // Get current sections for this course
                    $stmt = $db->prepare("SELECT sections FROM courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch();
                    
                    $current_sections = [];
                    if ($course && $course['sections']) {
                        $current_sections = json_decode($course['sections'], true) ?: [];
                    }
                    
                    // Add section if not already present
                    if (!in_array($section_id, $current_sections)) {
                        $current_sections[] = $section_id;
                        $sections_json = json_encode($current_sections);
                        
                        $stmt = $db->prepare("UPDATE courses SET sections = ? WHERE id = ?");
                        $stmt->execute([$sections_json, $course_id]);
                    }
                }
            }
            echo "<script>window.location.href='sections.php';</script>";
            exit;
        }
    }
    
    if (isset($_POST['update_section'])) {
        $section_id = intval($_POST['section_id']);
        $section_code = trim($_POST['section_code']);
        $section_year = intval($_POST['section_year']);
        $academic_period_id = intval($_POST['academic_period_id']);
        $section_description = trim($_POST['section_description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!empty($section_code) && $section_year >= 1 && $section_year <= 4 && $academic_period_id > 0) {
            $stmt = $db->prepare("UPDATE sections SET section_name = ?, year_level = ?, academic_period_id = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$section_code, $section_year, $academic_period_id, $section_description, $is_active, $section_id]);
            // Handle course assignments - update courses.sections JSON column
            $assigned_courses = $_POST['assigned_courses'] ?? [];
            
            // First, remove this section from all courses
            $stmt = $db->prepare("SELECT id, sections FROM courses WHERE sections IS NOT NULL AND sections != '[]'");
            $stmt->execute();
            $all_courses = $stmt->fetchAll();
            
            foreach ($all_courses as $course) {
                if ($course['sections']) {
                    $current_sections = json_decode($course['sections'], true) ?: [];
                    $key = array_search($section_id, $current_sections);
                    if ($key !== false) {
                        unset($current_sections[$key]);
                        $sections_json = json_encode(array_values($current_sections));
                        
                        $stmt = $db->prepare("UPDATE courses SET sections = ? WHERE id = ?");
                        $stmt->execute([$sections_json, $course['id']]);
                    }
                }
            }
            
            // Now add this section to the assigned courses
            if (!empty($assigned_courses)) {
                foreach ($assigned_courses as $course_id) {
                    // Get current sections for this course
                    $stmt = $db->prepare("SELECT sections FROM courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch();
                    
                    $current_sections = [];
                    if ($course && $course['sections']) {
                        $current_sections = json_decode($course['sections'], true) ?: [];
                    }
                    
                    // Add section if not already present
                    if (!in_array($section_id, $current_sections)) {
                        $current_sections[] = $section_id;
                        $sections_json = json_encode($current_sections);
                        
                        $stmt = $db->prepare("UPDATE courses SET sections = ? WHERE id = ?");
                        $stmt->execute([$sections_json, $course_id]);
                    }
                }
            }
            echo "<script>window.location.href='sections.php';</script>";
            exit;
        }
    }
    
    if (isset($_POST['delete_section'])) {
        $section_id = intval($_POST['delete_section_id']);
        
        // First, delete all related records to avoid foreign key constraint violations
        $db->beginTransaction();
        try {
            // Clear existing assignments
            $stmt = $db->prepare("UPDATE sections SET students = '[]', teachers = '[]' WHERE id = ?");
            $stmt->execute([$section_id]);
            
            // Now delete the section itself
            $stmt = $db->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->execute([$section_id]);
            
            $db->commit();
            echo "<script>window.location.href='sections.php';</script>";
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            // You might want to show an error message here
            echo "<script>alert('Error deleting section: " . addslashes($e->getMessage()) . "'); window.location.href='sections.php';</script>";
            exit;
        }
    }
    
    if (isset($_POST['assign_users'])) {
        $section_id = intval($_POST['assign_section_id']);
        $students = $_POST['students'] ?? [];
        $teachers = $_POST['teachers'] ?? [];
        
        // Check for regular students already assigned to other sections
        if (!empty($students)) {
            // Get all sections with their students to check for existing assignments
            $stmt = $db->prepare("
                SELECT id, students, year_level, section_name 
                FROM sections 
                WHERE students IS NOT NULL AND students != '[]' AND students != '' AND id != ?
            ");
            $stmt->execute([$section_id]);
            $all_sections = $stmt->fetchAll();
            
            // Create a map of student assignments (student_id => [section_ids])
            $student_assignments = [];
            foreach ($all_sections as $sec) {
                $section_students = json_decode($sec['students'], true) ?: [];
                foreach ($section_students as $student_id) {
                    if (!isset($student_assignments[$student_id])) {
                        $student_assignments[$student_id] = [];
                    }
                    $student_assignments[$student_id][] = [
                        'section_id' => $sec['id'],
                        'year_level' => $sec['year_level'],
                        'section_name' => $sec['section_name']
                    ];
                }
            }
            
            // Check each student being assigned
            $invalid_regular_students = [];
            foreach ($students as $student_id) {
                // Get student info to check if they're regular
                $stmt = $db->prepare("SELECT first_name, last_name, is_irregular FROM users WHERE id = ? AND role = 'student'");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch();
                
                if ($student && !$student['is_irregular']) { // Regular student
                    if (isset($student_assignments[$student_id])) {
                        $existing_sections = $student_assignments[$student_id];
                        $section_names = array_map(function($assignment) {
                            return $assignment['section_name'] . ' (Year ' . $assignment['year_level'] . ')';
                        }, $existing_sections);
                        
                        $invalid_regular_students[] = [
                            'name' => $student['last_name'] . ', ' . $student['first_name'],
                            'sections' => $section_names
                        ];
                    }
                }
            }
            
            // If there are invalid regular students, show error and don't proceed
            if (!empty($invalid_regular_students)) {
                $error_message = "Cannot assign regular students who are already in other sections:\n\n";
                foreach ($invalid_regular_students as $student) {
                    $error_message .= "• " . $student['name'] . " (already in: " . implode(', ', $student['sections']) . ")\n";
                }
                echo "<script>alert('" . addslashes($error_message) . "'); window.location.href='sections.php';</script>";
                exit;
            }
        }
        
        // Clear existing assignments
        $stmt = $db->prepare("UPDATE sections SET students = '[]', teachers = '[]' WHERE id = ?");
        $stmt->execute([$section_id]);
        
        // Assign students
        if (!empty($students)) {
            $students_json = json_encode($students);
            $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
            $stmt->execute([$students_json, $section_id]);
        }
        
        // Assign teachers
        if (!empty($teachers)) {
            $teachers_json = json_encode($teachers);
            $stmt = $db->prepare("UPDATE sections SET teachers = ? WHERE id = ?");
            $stmt->execute([$teachers_json, $section_id]);
        }
        
        echo "<script>window.location.href='sections.php';</script>";
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'remove_multiple_students') {
        $section_id = intval($_POST['section_id']);
        $student_ids_json = $_POST['student_ids'] ?? '[]';
        $student_ids = json_decode($student_ids_json, true) ?: [];
        
        if (!empty($student_ids) && $section_id > 0) {
            // Get current students in the section
            $stmt = $db->prepare("SELECT students FROM sections WHERE id = ?");
            $stmt->execute([$section_id]);
            $section = $stmt->fetch();
            
            if ($section && $section['students']) {
                $current_students = json_decode($section['students'], true) ?: [];
                
                // Remove selected students
                $updated_students = array_diff($current_students, $student_ids);
                $students_json = json_encode(array_values($updated_students));
                
                // Update the section
                $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
                $stmt->execute([$students_json, $section_id]);
                
                echo "<script>alert('Students removed successfully!'); window.location.href='sections.php';</script>";
            } else {
                echo "<script>alert('Section not found.'); window.location.href='sections.php';</script>";
            }
        } else {
            echo "<script>alert('No students selected or invalid section.'); window.location.href='sections.php';</script>";
        }
        exit;
    }
    
    if (isset($_POST['add_students'])) {
        $section_id = intval($_POST['add_students_section_id']);
        $students_to_add = $_POST['students_to_add'] ?? [];
        
        if (!empty($students_to_add)) {
            // Check for regular students already assigned to other sections
            $stmt = $db->prepare("
                SELECT id, students, year_level, section_name 
                FROM sections 
                WHERE students IS NOT NULL AND students != '[]' AND students != '' AND id != ?
            ");
            $stmt->execute([$section_id]);
            $all_sections = $stmt->fetchAll();
            
            // Create a map of student assignments (student_id => [section_ids])
            $student_assignments = [];
            foreach ($all_sections as $sec) {
                $section_students = json_decode($sec['students'], true) ?: [];
                foreach ($section_students as $student_id) {
                    if (!isset($student_assignments[$student_id])) {
                        $student_assignments[$student_id] = [];
                    }
                    $student_assignments[$student_id][] = [
                        'section_id' => $sec['id'],
                        'year_level' => $sec['year_level'],
                        'section_name' => $sec['section_name']
                    ];
                }
            }
            
            // Check each student being added
            $invalid_regular_students = [];
            foreach ($students_to_add as $student_id) {
                // Get student info to check if they're regular
                $stmt = $db->prepare("SELECT first_name, last_name, is_irregular FROM users WHERE id = ? AND role = 'student'");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch();
                
                if ($student && !$student['is_irregular']) { // Regular student
                    if (isset($student_assignments[$student_id])) {
                        $existing_sections = $student_assignments[$student_id];
                        $section_names = array_map(function($assignment) {
                            return $assignment['section_name'] . ' (Year ' . $assignment['year_level'] . ')';
                        }, $existing_sections);
                        
                        $invalid_regular_students[] = [
                            'name' => $student['last_name'] . ', ' . $student['first_name'],
                            'sections' => $section_names
                        ];
                    }
                }
            }
            
            // If there are invalid regular students, show error and don't proceed
            if (!empty($invalid_regular_students)) {
                $error_message = "Cannot add regular students who are already in other sections:\n\n";
                foreach ($invalid_regular_students as $student) {
                    $error_message .= "• " . $student['name'] . " (already in: " . implode(', ', $student['sections']) . ")\n";
                }
                echo "<script>alert('" . addslashes($error_message) . "'); window.location.href='sections.php';</script>";
                exit;
            }
            
            // Get current students in the section
            $stmt = $db->prepare("SELECT students FROM sections WHERE id = ?");
            $stmt->execute([$section_id]);
            $section = $stmt->fetch();
            
            $current_students = [];
            if ($section && $section['students']) {
                $current_students = json_decode($section['students'], true) ?: [];
            }
            
            // Add new students (avoid duplicates)
            foreach ($students_to_add as $student_id) {
                if (!in_array($student_id, $current_students)) {
                    $current_students[] = $student_id;
                }
            }
            
            // Update the section with new students
            $students_json = json_encode($current_students);
            $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
            $stmt->execute([$students_json, $section_id]);
            
            echo "<script>window.location.href='sections.php';</script>";
        } else {
            echo "<script>window.location.href='sections.php';</script>";
        }
        exit;
    }
}

// Fetch all sections with academic period info and detailed statistics
$sections = [];
$section_sql = "SELECT s.*, ap.academic_year, ap.semester_name, ap.is_active as period_active
                FROM sections s 
                LEFT JOIN academic_periods ap ON s.academic_period_id = ap.id 
                ORDER BY s.academic_period_id DESC, s.year_level, s.section_name";
$section_result = $db->query($section_sql);
if ($section_result && $section_result->rowCount() > 0) {
    $sections = $section_result->fetchAll();
}

// --- FILTER LOGIC ---
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$period_filter = isset($_GET['period']) ? intval($_GET['period']) : '';

$filtered_sections = array_filter($sections, function($sec) use ($year_filter, $status_filter, $period_filter) {
    $ok = true;
    if ($year_filter && $sec['year_level'] != $year_filter) $ok = false;
    if ($status_filter !== '' && $status_filter !== 'all') {
        if ($status_filter === 'active' && !$sec['is_active']) $ok = false;
        if ($status_filter === 'inactive' && $sec['is_active']) $ok = false;
    }
    if ($period_filter && $sec['academic_period_id'] != $period_filter) $ok = false;
    return $ok;
});

// Build an array of used codes per year
$used_codes_per_year = [];
foreach ($sections as $sec) {
    $yr = (int)$sec['year_level'];
    $code = strtoupper($sec['section_name']);
    if (!isset($used_codes_per_year[$yr])) $used_codes_per_year[$yr] = [];
    $used_codes_per_year[$yr][] = $code;
}

// Get comprehensive statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_sections,
        SUM(JSON_LENGTH(COALESCE(students, '[]'))) as total_students_assigned,
        SUM(JSON_LENGTH(COALESCE(teachers, '[]'))) as total_teachers_assigned,
        COUNT(CASE WHEN s.is_active = 1 THEN 1 END) as active_sections,
        COUNT(CASE WHEN s.is_active = 0 THEN 1 END) as inactive_sections,
        COUNT(CASE WHEN ap.is_active = 1 THEN 1 END) as current_period_sections,
        COUNT(DISTINCT s.year_level) as year_levels_covered,
        COUNT(DISTINCT s.academic_period_id) as academic_periods_covered,
        (SELECT COUNT(*) FROM courses WHERE is_archived = 0) as total_courses
    FROM sections s
    LEFT JOIN academic_periods ap ON s.academic_period_id = ap.id
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get detailed teacher assignment data
$teacher_assignments_query = "
    SELECT 
        s.id as section_id,
        s.section_name,
        s.year_level,
        ap.academic_year,
        ap.semester_name,
        s.teachers,
        JSON_LENGTH(COALESCE(s.teachers, '[]')) as teacher_count
    FROM sections s
    LEFT JOIN academic_periods ap ON s.academic_period_id = ap.id
    WHERE s.teachers IS NOT NULL 
    AND JSON_LENGTH(COALESCE(s.teachers, '[]')) > 0
    ORDER BY s.year_level, s.section_name
";
$teacher_assignments_stmt = $db->prepare($teacher_assignments_query);
$teacher_assignments_stmt->execute();
$teacher_assignments = $teacher_assignments_stmt->fetchAll();

// Get all assigned teacher details
$assigned_teachers_details = [];
if (!empty($teacher_assignments)) {
    foreach ($teacher_assignments as $assignment) {
        $teacher_ids = json_decode($assignment['teachers'], true) ?? [];
        if (!empty($teacher_ids)) {
            $placeholders = str_repeat('?,', count($teacher_ids) - 1) . '?';
            $teacher_details_query = "
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.status
                FROM users u 
                WHERE u.id IN ($placeholders) AND u.role = 'teacher'
            ";
            $teacher_details_stmt = $db->prepare($teacher_details_query);
            $teacher_details_stmt->execute($teacher_ids);
            $teachers = $teacher_details_stmt->fetchAll();
            
            $assigned_teachers_details[] = [
                'section_id' => $assignment['section_id'],
                'section_name' => $assignment['section_name'],
                'year_level' => $assignment['year_level'],
                'academic_year' => $assignment['academic_year'],
                'semester_name' => $assignment['semester_name'],
                'teacher_count' => $assignment['teacher_count'],
                'teachers' => $teachers
            ];
        }
    }
}

// Get summary statistics for teachers (MariaDB compatible)
$teacher_summary_query = "
    SELECT 
        SUM(JSON_LENGTH(COALESCE(s.teachers, '[]'))) as total_assignments,
        AVG(JSON_LENGTH(COALESCE(s.teachers, '[]'))) as avg_teachers_per_section,
        MAX(JSON_LENGTH(COALESCE(s.teachers, '[]'))) as max_teachers_in_section
    FROM sections s
    WHERE s.teachers IS NOT NULL 
    AND JSON_LENGTH(COALESCE(s.teachers, '[]')) > 0
";
$teacher_summary_stmt = $db->prepare($teacher_summary_query);
$teacher_summary_stmt->execute();
$teacher_summary = $teacher_summary_stmt->fetch();

// Get unique teachers count separately (MariaDB compatible)
$unique_teachers_query = "
    SELECT COUNT(DISTINCT u.id) as unique_teachers_assigned
    FROM users u
    WHERE u.role = 'teacher' 
    AND u.id IN (
        SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(s.teachers, CONCAT('$[', numbers.n, ']')))
        FROM sections s
        CROSS JOIN (
            SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
        ) numbers
        WHERE s.teachers IS NOT NULL 
        AND JSON_LENGTH(COALESCE(s.teachers, '[]')) > numbers.n
        AND JSON_UNQUOTE(JSON_EXTRACT(s.teachers, CONCAT('$[', numbers.n, ']'))) IS NOT NULL
    )
";
$unique_teachers_stmt = $db->prepare($unique_teachers_query);
$unique_teachers_stmt->execute();
$unique_teachers_result = $unique_teachers_stmt->fetch();

// Merge the results
$teacher_summary['unique_teachers_assigned'] = $unique_teachers_result['unique_teachers_assigned'];


?>

<style>
/* Enhanced Section Row Styling */
.section-row {
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

/* Fix modal scrolling and footer visibility */
.modal-dialog {
    max-height: 90vh;
}

.modal-content {
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.modal-body {
    overflow-y: auto;
    flex: 1;
    max-height: calc(90vh - 120px); /* Account for header and footer */
}

.modal-footer {
    flex-shrink: 0;
    border-top: 1px solid #dee2e6;
    background-color: #f8f9fa;
}

.section-row:hover {
    background-color: #f8f9fa !important;
    border-left-color: #0d6efd;
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.section-avatar {
    transition: transform 0.3s ease;
}

.section-row:hover .section-avatar {
    transform: scale(1.05);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
}

/* Statistics Cards Styling */
.stats-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.stats-icon {
    width: 60px;
    height: 60px;
    transition: all 0.3s ease;
}

.stats-card:hover .stats-icon {
    transform: scale(1.1);
}

.stats-primary {
    background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
    border-left: 4px solid #0d6efd;
}

.stats-success {
    background: linear-gradient(135deg, #e8f5e8 0%, #f8f9fa 100%);
    border-left: 4px solid #198754;
}

.stats-info {
    background: linear-gradient(135deg, #e0f7fa 0%, #f8f9fa 100%);
    border-left: 4px solid #0dcaf0;
}

.stats-warning {
    background: linear-gradient(135deg, #fff8e1 0%, #f8f9fa 100%);
    border-left: 4px solid #ffc107;
}

.stats-secondary {
    background: linear-gradient(135deg, #f5f5f5 0%, #f8f9fa 100%);
    border-left: 4px solid #6c757d;
}

.stats-danger {
    background: linear-gradient(135deg, #ffebee 0%, #f8f9fa 100%);
    border-left: 4px solid #dc3545;
}

.stats-danger-alt {
    background: linear-gradient(135deg, #fce4ec 0%, #f8f9fa 100%);
    border-left: 4px solid #e91e63;
}

.stats-purple {
    background: linear-gradient(135deg, #f3e5f5 0%, #f8f9fa 100%);
    border-left: 4px solid #9c27b0;
}

.bg-purple {
    background-color: #9c27b0 !important;
}

.text-purple {
    color: #9c27b0 !important;
}

/* Teacher Assignments Table Styling */
.teacher-assignments-table .table tbody tr:hover {
    background-color: #f8f9fa;
    transform: translateX(2px);
    transition: all 0.2s ease;
}

.teacher-assignments-table .badge {
    font-size: 0.75rem;
    font-weight: 500;
}

.students-info-container .btn {
    transition: all 0.2s ease;
    min-width: 80px;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.students-info-container .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.actions-container {
    min-width: 450px;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: nowrap;
    gap: 0.5rem;
}

.actions-container .btn {
    transition: all 0.2s ease;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 65px;
    padding: 0.3rem 0.6rem;
    letter-spacing: 0.2px;
    white-space: nowrap;
    flex-shrink: 0;
}

.actions-container .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.description-container {
    max-width: 200px;
    word-wrap: break-word;
}

.courses-container {
    text-align: center;
}

.badge {
    font-weight: 500;
    letter-spacing: 0.5px;
}

/* Statistics Cards Responsive */
@media (max-width: 1200px) {
    .stats-icon {
        width: 55px;
        height: 55px;
    }
    
    .stats-card h3 {
        font-size: 1.5rem;
    }
}

@media (max-width: 992px) {
    .actions-container {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .actions-container .btn {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        min-width: 60px;
    }
    
    .stats-icon {
        width: 50px;
        height: 50px;
    }
    
    .stats-card h3 {
        font-size: 1.25rem;
    }
    
    .stats-card .card-body {
        padding: 1rem !important;
    }
}

@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 1rem;
    }
    
    .stats-icon {
        width: 45px;
        height: 45px;
    }
    
    .stats-card h3 {
        font-size: 1.1rem;
    }
    
    .stats-card .card-body {
        padding: 0.75rem !important;
    }
}

@media (max-width: 768px) {
    .section-row:hover {
        transform: none;
    }
    
    .actions-container {
        min-width: auto;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .actions-container .btn {
        width: 100%;
        justify-content: center;
        min-width: auto;
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
        font-weight: 600;
        flex-shrink: 1;
    }
    
    .students-info-container .d-flex {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .students-info-container .btn {
        width: 100%;
        justify-content: center;
        min-width: auto;
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
        font-weight: 600;
    }
}
</style>

<div class="page-container">
    <div class="container-fluid py-4">
        <!-- Enhanced Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">Manage Sections</h1>
                    <p class="welcome-subtitle">Create, edit, and manage student sections and class assignments</p>
                    <a href="dashboard.php" class="back-btn">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="welcome-decoration">
                        <i class="bi bi-collection"></i>
                    </div>
                    <div class="floating-shapes"></div>
                </div>
            </div>
            <div class="accent-line"></div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-primary border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-collection-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark"><?= $stats['total_sections'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Total Sections</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-success border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark"><?= $stats['active_sections'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Active Sections</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-info border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-calendar-check-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark"><?= $stats['current_period_sections'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Current Period</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-warning border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark"><?= $stats['total_students_assigned'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Students Assigned</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-secondary border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-mortarboard-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark"><?= $stats['year_levels_covered'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Year Levels</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-danger border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-danger text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-book-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark"><?= $stats['total_courses'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Total Courses</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-danger-alt border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-danger text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-x-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark"><?= $stats['inactive_sections'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Inactive Sections</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-purple border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-purple text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark"><?= $stats['total_teachers_assigned'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Teachers Assigned</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher Assignments Overview -->
    <?php if (!empty($assigned_teachers_details)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">
                                <i class="bi bi-person-workspace me-2 text-purple"></i>Teacher Assignments Overview
                            </h5>
                            <small class="text-muted">Detailed view of all teacher assignments across sections</small>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge bg-purple text-white px-3 py-2">
                                <i class="bi bi-people me-1"></i><?= $teacher_summary['unique_teachers_assigned'] ?> Unique Teachers
                            </span>
                            <span class="badge bg-info text-white px-3 py-2">
                                <i class="bi bi-diagram-3 me-1"></i><?= $teacher_summary['total_assignments'] ?> Total Assignments
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-scrollable">
                        <table class="table table-hover mb-0 teacher-assignments-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="border-0">
                                        <i class="bi bi-collection me-2"></i>Section
                                    </th>
                                    <th class="border-0">
                                        <i class="bi bi-calendar me-2"></i>Academic Period
                                    </th>
                                    <th class="border-0">
                                        <i class="bi bi-people me-2"></i>Assigned Teachers
                                    </th>
                                    <th class="border-0">
                                        <i class="bi bi-hash me-2"></i>Count
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_teachers_details as $assignment): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-purple rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi bi-collection text-white"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-semibold">BSIT-<?= $assignment['year_level'] ?><?= $assignment['section_name'] ?></h6>
                                                <small class="text-muted"><?= formatYear($assignment['year_level']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($assignment['academic_year']) ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($assignment['semester_name']) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($assignment['teachers'] as $teacher): ?>
                                            <span class="badge bg-purple text-white px-2 py-1">
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark px-3 py-2">
                                            <i class="bi bi-hash me-1"></i><?= $assignment['teacher_count'] ?>
                                        </span>
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
    <?php endif; ?>

    <!-- Real-time Performance Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">
                                <i class="bi bi-graph-up me-2 text-success"></i>Real-time Performance Summary
                                <span class="badge bg-success ms-2" id="liveIndicator">
                                    <i class="bi bi-circle-fill me-1"></i>Live
                                </span>
                            </h5>
                            <small class="text-muted">Live performance data across all sections and assessments</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-success" onclick="refreshPerformanceData()" id="refreshBtn">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                            <button class="btn btn-sm btn-outline-info" onclick="toggleAutoRefresh()" id="autoRefreshBtn">
                                <i class="bi bi-play-circle me-1"></i>Auto Refresh
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Performance Overview Cards -->
                    <div class="row mb-4" id="performanceOverview">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-center mb-2">
                                        <i class="bi bi-people-fill fs-2"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" id="totalStudents">-</h3>
                                    <p class="mb-0 small">Total Students</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-center mb-2">
                                        <i class="bi bi-clipboard-check fs-2"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" id="totalAttempts">-</h3>
                                    <p class="mb-0 small">Total Attempts</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-center mb-2">
                                        <i class="bi bi-percent fs-2"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" id="averageScore">-</h3>
                                    <p class="mb-0 small">Average Score</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white h-100">
                                <div class="card-body text-center">
                                    <div class="d-flex justify-content-center mb-2">
                                        <i class="bi bi-trophy fs-2"></i>
                                    </div>
                                    <h3 class="fw-bold mb-1" id="passingRate">-</h3>
                                    <p class="mb-0 small">Passing Rate</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <h6 class="fw-bold text-primary mb-2">Today's Activity</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <i class="bi bi-person-check text-success me-2"></i>
                                                <div>
                                                    <div class="fw-bold" id="activeStudentsToday">-</div>
                                                    <small class="text-muted">Active Students</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <i class="bi bi-clipboard-data text-info me-2"></i>
                                                <div>
                                                    <div class="fw-bold" id="attemptsToday">-</div>
                                                    <small class="text-muted">Attempts</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <h6 class="fw-bold text-primary mb-2">Question Type Performance</h6>
                                    <div id="questionTypePerformance">
                                        <div class="text-center text-muted">
                                            <i class="bi bi-hourglass-split"></i> Loading...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Performance Table - Compact & Scrollable -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light py-2">
                            <h6 class="mb-0 fw-bold text-dark small">
                                <i class="bi bi-bar-chart me-1"></i>Section Performance Rankings
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="scrollable-table" style="max-height: 300px;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="border-0 py-1 small">#</th>
                                            <th class="border-0 py-1 small">Section</th>
                                            <th class="border-0 py-1 small text-center">Students</th>
                                            <th class="border-0 py-1 small text-center">Score</th>
                                            <th class="border-0 py-1 small text-center">Attempts</th>
                                            <th class="border-0 py-1 small text-center">Pass Rate</th>
                                            <th class="border-0 py-1 small text-center">Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sectionPerformanceTable">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-3 small">
                                                <i class="bi bi-hourglass-split me-1"></i>Loading performance data...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="h4 mb-0 text-dark">
                        <i class="bi bi-collection me-2"></i>Section Management
                    </h2>
                    <button class="btn add-section-btn" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                        <i class="bi bi-plus-circle me-2"></i>Add Section
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters above the table -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card search-filter-card">
                    <div class="card-header">
                        <h5>
                            <i class="bi bi-funnel me-2"></i>Filter Sections
                        </h5>
                    </div>
                    <div class="card-body py-3">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label for="year_filter" class="form-label fw-semibold">Year Level:</label>
                            <select name="year" id="year_filter" class="form-select form-select-sm">
                                <option value="">All Years</option>
                                <option value="1" <?= $year_filter === 1 ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= $year_filter === 2 ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= $year_filter === 3 ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= $year_filter === 4 ? 'selected' : '' ?>>4th Year</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status_filter" class="form-label fw-semibold">Status:</label>
                            <select name="status" id="status_filter" class="form-select form-select-sm">
                                <option value="all" <?= $status_filter === 'all' || $status_filter === '' ? 'selected' : '' ?>>All</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="period_filter" class="form-label fw-semibold">Academic Period:</label>
                            <select name="period" id="period_filter" class="form-select form-select-sm">
                                <option value="">All Periods</option>
                                <?php foreach ($academic_periods as $period): ?>
                                    <option value="<?= $period['id'] ?>" <?= (isset($_GET['period']) && $_GET['period'] == $period['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($period['period_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                            <a href="sections.php" class="btn btn-outline-secondary btn-sm ms-1">
                                <i class="bi bi-x-circle me-1"></i>Clear
                            </a>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <span class="badge bg-info fs-6">
                                    <i class="bi bi-list-ul me-1"></i><?= count($filtered_sections) ?> sections found
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

        <!-- Sections List -->
        <div class="row">
            <div class="col-12">
                <div class="card table-container">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5>
                                <i class="bi bi-list-ul me-2"></i>All Sections
                            </h5>
                            <div class="d-flex gap-2">
                                <span class="badge bg-primary fs-6"><?= count($filtered_sections) ?> sections</span>
                                <?php if ($period_filter): ?>
                                    <span class="badge bg-info fs-6">Filtered by Period</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($filtered_sections)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-collection fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">No sections found</h5>
                                <p class="text-muted">Start by adding your first section for this course.</p>
                                <button class="btn add-section-btn" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                                    <i class="bi bi-plus-circle me-2"></i>Add Section
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="scrollable-table">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="border-0">
                                            <i class="bi bi-collection me-2"></i>Section Code
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-calendar me-2"></i>Year Level
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-text-paragraph me-2"></i>Description
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-toggle-on me-2"></i>Status
                                        </th>
                                        <th class="border-0">
                                            <i class="bi bi-people me-2"></i>Students
                                        </th>
                                        <th class="border-0"><i class="bi bi-book me-2"></i>Courses</th>
                                        <th class="border-0 text-center">
                                            <i class="bi bi-gear me-2"></i>Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filtered_sections as $section): ?>
                                        <?php
                                        // Get student count for this section
                                        $student_count_stmt = $db->prepare("SELECT JSON_LENGTH(COALESCE(students, '[]')) as student_count FROM sections WHERE id = ?");
                                        $student_count_stmt->execute([$section['id']]);
                                        $student_count = $student_count_stmt->fetchColumn();
                                        
                                        // Get teacher count for this section
                                        $teacher_count_stmt = $db->prepare("SELECT JSON_LENGTH(COALESCE(teachers, '[]')) as teacher_count FROM sections WHERE id = ?");
                                        $teacher_count_stmt->execute([$section['id']]);
                                        $teacher_count = $teacher_count_stmt->fetchColumn();
                                        
                                        
                                        // Create display name with academic period info
                                        $display_name = "BSIT-{$section['year_level']}{$section['section_name']}";
                                        $period_info = $section['academic_year'] . ' - ' . $section['semester_name'];
                                        $is_current_period = $section['period_active'] == 1;
                                        ?>
                                        <tr data-section-id="<?= $section['id'] ?>" class="section-row">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <div class="section-avatar position-relative">
                                                            <div class="bg-gradient-primary rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                                                                <i class="bi bi-collection text-white fs-5"></i>
                                                            </div>
                                                            <?php if ($is_current_period): ?>
                                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success" style="font-size: 0.6rem;">
                                                                    <i class="bi bi-circle-fill"></i>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <h6 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($display_name) ?></h6>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($section['section_name']) ?></span>
                                                            <small class="text-muted"><?= htmlspecialchars($period_info) ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column align-items-center">
                                                    <span class="badge bg-secondary text-white fs-6 px-3 py-2 rounded-pill shadow-sm">
                                                        <i class="bi bi-mortarboard me-1"></i><?= formatYear($section['year_level']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="description-container">
                                                    <?php if ($section['description']): ?>
                                                        <p class="mb-0 text-dark fw-medium"><?= htmlspecialchars($section['description']) ?></p>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">
                                                            <i class="bi bi-dash-circle me-1"></i>No description
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                <?php if ($section['is_active']): ?>
                                                        <span class="badge bg-success text-white px-3 py-2 rounded-pill shadow-sm">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Active
                                                    </span>
                                                <?php else: ?>
                                                        <span class="badge bg-danger text-white px-3 py-2 rounded-pill shadow-sm">
                                                            <i class="bi bi-x-circle-fill me-1"></i>Inactive
                                                    </span>
                                                <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="students-info-container">
                                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                                        <span class="badge bg-info text-white px-3 py-2 rounded-pill shadow-sm">
                                                            <i class="bi bi-people-fill me-1"></i><?= $student_count ?> assigned
                                                        </span>
                                                    </div>
                                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                                        <?php if ($student_count > 0): ?>
                                                            <button class="btn btn-sm btn-outline-info rounded-pill px-3 py-2" 
                                                                    onclick="viewSectionStudents(<?= $section['id'] ?>, '<?= htmlspecialchars($display_name) ?>')"
                                                                    title="View Students">
                                                                <i class="bi bi-eye me-1"></i>View
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-outline-success rounded-pill px-3 py-2" 
                                                                onclick="openAddStudentsModal(<?= $section['id'] ?>)"
                                                                title="Add Students">
                                                            <i class="bi bi-person-plus me-1"></i>Add
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $assigned_courses = [];
                                                // Get courses that have this section assigned via JSON columns
                                                $cs_stmt = $db->prepare("SELECT c.course_name, c.course_code, c.year_level, c.status FROM courses c WHERE c.sections IS NOT NULL AND JSON_SEARCH(c.sections, 'one', ?) IS NOT NULL AND c.is_archived = 0");
                                                $cs_stmt->execute([$section['id']]);
                                                $assigned_courses = $cs_stmt->fetchAll();
                                                
                                                if (empty($assigned_courses)) {
                                                    echo '<div class="d-flex justify-content-center">';
                                                    echo '<span class="text-muted fst-italic">';
                                                    echo '<i class="bi bi-dash-circle me-1"></i>No courses assigned';
                                                    echo '</span>';
                                                    echo '</div>';
                                                } else {
                                                    echo '<div class="courses-container">';
                                                    echo '<div class="d-flex flex-wrap gap-1 mb-2">';
                                                    foreach ($assigned_courses as $course) {
                                                        $status_class = $course['status'] === 'active' ? 'bg-success text-white' : 'bg-secondary text-white';
                                                        echo '<span class="badge ' . $status_class . ' px-2 py-1 rounded-pill shadow-sm small course-badge" ';
                                                        echo 'onclick="showCourseDetails(\'' . htmlspecialchars($course['course_code']) . '\', \'' . htmlspecialchars($course['course_name']) . '\', \'' . $course['year_level'] . '\', \'' . $course['status'] . '\')" ';
                                                        echo 'style="cursor: pointer;" title="Click to view course details">';
                                                        echo htmlspecialchars($course['course_code']);
                                                        echo '</span>';
                                                    }
                                                    echo '</div>';
                                                    echo '<small class="text-muted d-block text-center">' . count($assigned_courses) . ' course(s) assigned</small>';
                                                    echo '</div>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="actions-container">
                                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 py-2" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editSectionModal<?= $section['id'] ?>"
                                                            title="Edit Section">
                                                        <i class="bi bi-pencil me-1"></i>Edit
                                                    </button>
                                                    <form method="post" action="sections.php" 
                                                          style="display:inline;" 
                                                          onsubmit="return confirm('Are you sure you want to delete this section?');">
                                                    <input type="hidden" name="delete_section_id" value="<?= $section['id'] ?>">
                                                        <button type="submit" name="delete_section" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-2" title="Delete Section">
                                                            <i class="bi bi-trash me-1"></i>Delete
                                                        </button>
                                                </form>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Edit Section Modal -->
                                        <div class="modal fade" id="editSectionModal<?= $section['id'] ?>" tabindex="-1" aria-labelledby="editSectionLabel<?= $section['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post" action="sections.php">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title" id="editSectionLabel<?= $section['id'] ?>">
                                                                <i class="bi bi-pencil-square me-2"></i>Edit Section
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="section_id" value="<?= $section['id'] ?>">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="section_code<?= $section['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-collection me-2"></i>Section Code
                                                                        </label>
                                                                        <input type="text" class="form-control" id="section_code<?= $section['id'] ?>" name="section_code" required value="<?= htmlspecialchars($section['section_name']) ?>" maxlength="1" placeholder="A-Z">
                                                                        <small class="text-muted">Single letter (A-Z)</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                            <div class="mb-3">
                                                                        <label for="section_year<?= $section['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-calendar me-2"></i>Year Level
                                                                        </label>
                                                                        <select class="form-select" id="section_year<?= $section['id'] ?>" name="section_year" required>
                                                                            <option value="1" <?= $section['year_level'] == 1 ? 'selected' : '' ?>>1st Year</option>
                                                                            <option value="2" <?= $section['year_level'] == 2 ? 'selected' : '' ?>>2nd Year</option>
                                                                            <option value="3" <?= $section['year_level'] == 3 ? 'selected' : '' ?>>3rd Year</option>
                                                                            <option value="4" <?= $section['year_level'] == 4 ? 'selected' : '' ?>>4th Year</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label for="academic_period<?= $section['id'] ?>" class="form-label fw-semibold">
                                                                            <i class="bi bi-calendar-week me-2"></i>Academic Period
                                                                        </label>
                                                                        <select class="form-select" id="academic_period<?= $section['id'] ?>" name="academic_period_id" required>
                                                                            <?php foreach ($academic_periods as $period): ?>
                                                                                <option value="<?= $period['id'] ?>" <?= $section['academic_period_id'] == $period['id'] ? 'selected' : '' ?>>
                                                                                    <?= htmlspecialchars($period['period_name']) ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="section_description<?= $section['id'] ?>" class="form-label fw-semibold">
                                                                    <i class="bi bi-text-paragraph me-2"></i>Description
                                                                </label>
                                                                <textarea class="form-control" id="section_description<?= $section['id'] ?>" name="section_description" rows="2"><?= htmlspecialchars($section['description']) ?></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="is_active<?= $section['id'] ?>" name="is_active" <?= $section['is_active'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label fw-semibold" for="is_active<?= $section['id'] ?>">
                                                                        <i class="bi bi-toggle-on me-2"></i>Active Section
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <?php
                                                            $assigned_course_ids = [];
                                                            // Get course IDs that have this section assigned via JSON columns
                                                            $cs_stmt = $db->prepare("SELECT c.id FROM courses c WHERE c.sections IS NOT NULL AND JSON_SEARCH(c.sections, 'one', ?) IS NOT NULL");
                                                            $cs_stmt->execute([$section['id']]);
                                                            $assigned_course_ids = $cs_stmt->fetchAll(PDO::FETCH_COLUMN);
                                                            ?>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-semibold d-flex align-items-center">
                                                                    <i class="bi bi-book me-2"></i>Assign Courses
                                                                    <span class="badge bg-primary ms-2 selectedCoursesCountBadge">0</span>
                                                                </label>
                                                                <div class="position-relative mb-2">
                                                                    <input type="text" class="form-control pr-5" placeholder="Search courses..." onkeyup="searchCoursesInModal(this)" oninput="toggleClearBtn(this)" style="padding-right:2.5rem;">
                                                                    <span class="position-absolute top-50 end-0 translate-middle-y me-2" style="z-index:2; cursor:pointer; display:none;" onclick="clearCourseSearch(this)">
                                                                        <i class="bi bi-x-lg text-secondary"></i>
                                                                    </span>
                                                                </div>
                                                                <div class="border rounded p-2 assign-courses-list" style="max-height: 200px; overflow-y: auto; background: #f8f9fa;">
                                                                    <?php foreach ($courses_for_assignment as $course): ?>
                                                                        <div class="form-check mb-1">
                                                                            <input class="form-check-input assign-course-checkbox" type="checkbox" name="assigned_courses[]" value="<?= $course['id'] ?>" id="edit_course_<?= $section['id'] ?>_<?= $course['id'] ?>" <?= in_array($course['id'], $assigned_course_ids) ? 'checked' : '' ?>>
                                                                            <label class="form-check-label" for="edit_course_<?= $section['id'] ?>_<?= $course['id'] ?>">
                                                                                <?= htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')') ?>
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                                <small class="text-muted">Check to assign courses to this section.</small>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="bi bi-x-circle me-2"></i>Cancel
                                                            </button>
                                                            <button type="submit" name="update_section" class="btn btn-primary">
                                                                <i class="bi bi-check-circle me-2"></i>Save Changes
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Assign Users Modal -->
                                        <div class="modal fade" id="assignUsersModal<?= $section['id'] ?>" tabindex="-1" aria-labelledby="assignUsersLabel<?= $section['id'] ?>">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <form method="post" action="sections.php">
                                                        <div class="modal-header bg-info text-white">
                                                            <h5 class="modal-title" id="assignUsersLabel<?= $section['id'] ?>">
                                                                <i class="bi bi-people me-2"></i>Assign Users to <?= htmlspecialchars($display_name) ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="assign_section_id" value="<?= $section['id'] ?>">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-funnel me-2"></i>Filter Students
                                                                        </label>
                                                                        <select class="form-select" id="studentStatusFilter<?= $section['id'] ?>" onchange="filterStudents(<?= $section['id'] ?>)">
                                                                            <option value="all">All Students</option>
                                                                            <option value="regular">Regular Students</option>
                                                                            <option value="irregular">Irregular Students</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-search me-2"></i>Search Students
                                                                        </label>
                                                                        <input type="text" class="form-control mb-2" id="studentSearch<?= $section['id'] ?>" placeholder="Type to search..." onkeyup="searchStudents(<?= $section['id'] ?>)">
                                                                        <label class="form-label fw-semibold d-flex align-items-center">
    <i class="bi bi-people me-2"></i>Assign Students
    <span class="badge bg-primary ms-2 selectedStudentsCountBadge">0</span>
</label>
                                                                        <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto; background: #f8f9fa;">
                                                                    <?php
                                                                    $students = [];
                                                                    $stu_sql = "SELECT id, CONCAT(last_name, ', ', first_name) AS name, is_irregular FROM users WHERE role='student' ORDER BY last_name, first_name";
                                                                    $stu_res = $db->query($stu_sql);
                                                                    if ($stu_res && $stu_res->rowCount() > 0) {
                                                                        $students = $stu_res->fetchAll();
                                                                    }
                                                                    $assigned_students = [];
                                                                    $as_sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(students, '$[*]')) as student_ids FROM sections WHERE id = ?";
                                                                    $as_stmt = $db->prepare($as_sql);
                                                                    $as_stmt->execute([$section['id']]);
                                                                    $assigned_students_json = $as_stmt->fetchColumn();
                                                                    $assigned_students = json_decode($assigned_students_json, true) ?? [];
                                                                    
                                                                    // Get all sections with their students to check for existing assignments
                                                                    $all_sections_sql = "SELECT id, students, year_level, section_name FROM sections WHERE students IS NOT NULL AND students != '[]' AND students != ''";
                                                                    $all_sections_res = $db->query($all_sections_sql);
                                                                    $all_sections = $all_sections_res ? $all_sections_res->fetchAll() : [];
                                                                    
                                                                    // Create a map of student assignments (student_id => [section_ids])
                                                                    $student_assignments = [];
                                                                    foreach ($all_sections as $sec) {
                                                                        $section_students = json_decode($sec['students'], true) ?: [];
                                                                        foreach ($section_students as $student_id) {
                                                                            if (!isset($student_assignments[$student_id])) {
                                                                                $student_assignments[$student_id] = [];
                                                                            }
                                                                            $student_assignments[$student_id][] = [
                                                                                'section_id' => $sec['id'],
                                                                                'year_level' => $sec['year_level'],
                                                                                'section_name' => $sec['section_name']
                                                                            ];
                                                                        }
                                                                    }
                                                                    
                                                                    foreach ($students as $stu) {
                                                                        $checked = in_array($stu['id'], $assigned_students) ? 'checked' : '';
                                                                        $status = ($stu['is_irregular'] ? 'irregular' : 'regular');
                                                                        $badge = $stu['is_irregular'] ? '<span class=\'badge bg-danger ms-2\'>Irregular</span>' : '<span class=\'badge bg-success ms-2\'>Regular</span>';
                                                                        
                                                                        // Check if regular student is already assigned to another section
                                                                        $is_regular = !$stu['is_irregular'];
                                                                        $already_assigned = isset($student_assignments[$stu['id']]) && 
                                                                                           !empty(array_filter($student_assignments[$stu['id']], function($assignment) use ($section) {
                                                                                               return $assignment['section_id'] != $section['id'];
                                                                                           }));
                                                                        
                                                                        // For regular students already assigned to other sections, show them as disabled
                                                                        $disabled = '';
                                                                        $disabled_class = '';
                                                                        $disabled_note = '';
                                                                        
                                                                        if ($is_regular && $already_assigned && !in_array($stu['id'], $assigned_students)) {
                                                                            $existing_sections = array_filter($student_assignments[$stu['id']], function($assignment) use ($section) {
                                                                                return $assignment['section_id'] != $section['id'];
                                                                            });
                                                                            $section_names = array_map(function($assignment) {
                                                                                return $assignment['section_name'] . ' (Year ' . $assignment['year_level'] . ')';
                                                                            }, $existing_sections);
                                                                            
                                                                            $disabled = 'disabled';
                                                                            $disabled_class = 'text-muted';
                                                                            $disabled_note = '';
                                                                        }
                                                                        
                                                                        echo "<div class='form-check student-option-{$status}' data-status='{$status}' style='margin-bottom: 4px;'>";
                                                                        echo "<input class='form-check-input' type='checkbox' name='students[]' value='{$stu['id']}' id='stu{$section['id']}_{$stu['id']}' $checked $disabled onchange='updateSelectedStudentsCount(this.closest(\".modal\"))'>";
                                                                        echo "<label class='form-check-label $disabled_class' for='stu{$section['id']}_{$stu['id']}'>" . htmlspecialchars($stu['name']) . " $badge$disabled_note</label>";
                                                                        echo "</div>";
                                                                    }
                                                                    ?>
                                                                        </div>
                                                                        <small class="text-muted">Check to assign students</small>
                                                                    </div>
                                                            </div>
                                                                <div class="col-md-6">
                                                            <div class="mb-3">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-search me-2"></i>Search Teachers
                                                                        </label>
                                                                        <input type="text" class="form-control mb-2" id="teacherSearch<?= $section['id'] ?>" placeholder="Type to search..." onkeyup="searchTeachers(<?= $section['id'] ?>)">
                                                                        <label class="form-label fw-semibold">
                                                                            <i class="bi bi-person-workspace me-2"></i>Assign Teachers
                                                                        </label>
                                                                        <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto; background: #f8f9fa;">
                                                                    <?php
                                                                    $teachers = [];
                                                                            $teach_sql = "SELECT id, CONCAT(last_name, ', ', first_name) AS name FROM users WHERE role='teacher' AND status='active' ORDER BY last_name, first_name";
                                                                    $teach_res = $db->query($teach_sql);
                                                                    if ($teach_res && $teach_res->rowCount() > 0) {
                                                                        $teachers = $teach_res->fetchAll();
                                                                    }
                                                                    $assigned_teachers = [];
                                                                    $at_sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(teachers, '$[*]')) as teacher_ids FROM sections WHERE id = ?";
                                                                    $at_stmt = $db->prepare($at_sql);
                                                                    $at_stmt->execute([$section['id']]);
                                                                    $assigned_teachers_json = $at_stmt->fetchColumn();
                                                                    $assigned_teachers = json_decode($assigned_teachers_json, true) ?? [];
                                                                    foreach ($teachers as $teach) {
                                                                                $checked = in_array($teach['id'], $assigned_teachers) ? 'checked' : '';
                                                                                echo "<div class='form-check' style='margin-bottom: 4px;'>";
                                                                                echo "<input class='form-check-input' type='checkbox' name='teachers[]' value='{$teach['id']}' id='teach{$section['id']}_{$teach['id']}' $checked>";
                                                                                echo "<label class='form-check-label' for='teach{$section['id']}_{$teach['id']}'>" . htmlspecialchars($teach['name']) . "</label>";
                                                                                echo "</div>";
                                                                            }
                                                                            ?>
                                                                        </div>
                                                                        <small class="text-muted">Check to assign teachers</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                <i class="bi bi-x-circle me-2"></i>Cancel
                                                            </button>
                                                            <button type="submit" name="assign_users" class="btn btn-info">
                                                                <i class="bi bi-check-circle me-2"></i>Save Assignments
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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
</div>

<!-- Course Details Modal -->
<div class="modal fade" id="courseDetailsModal" tabindex="-1" aria-labelledby="courseDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="courseDetailsLabel">
                    <i class="bi bi-book me-2"></i>Course Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted">Course Code</label>
                            <div class="p-3 bg-light rounded">
                                <span class="fw-bold text-primary" id="courseCodeDisplay"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted">Year Level</label>
                            <div class="p-3 bg-light rounded">
                                <span class="badge bg-info" id="courseYearDisplay"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Course Name</label>
                    <div class="p-3 bg-light rounded">
                        <span class="fw-medium" id="courseNameDisplay"></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Status</label>
                    <div class="p-3 bg-light rounded">
                        <span id="courseStatusDisplay"></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Description</label>
                    <div class="p-3 bg-light rounded">
                        <span id="courseDescriptionDisplay" class="text-muted">No description available</span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Assigned Teachers</label>
                    <div class="p-3 bg-light rounded">
                        <div id="assignedTeachersDisplay">
                            <span class="text-muted">Loading teachers...</span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Assigned Sections</label>
                    <div class="p-3 bg-light rounded">
                        <div id="assignedSectionsDisplay">
                            <span class="text-muted">Loading sections...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Enhanced Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" action="sections.php" id="addSectionForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSectionLabel">
                            <i class="bi bi-plus-circle me-2"></i>Create New Section
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Section Information -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-info-circle"></i>
                                Section Information
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="section_year_add" class="form-label">
                                            <i class="bi bi-calendar me-2"></i>Year Level
                                        </label>
                                        <select class="form-select" id="section_year_add" name="section_year" required onchange="updateSectionCodeDropdown()">
                                            <option value="">Select Year Level</option>
                                            <option value="1">1st Year</option>
                                            <option value="2">2nd Year</option>
                                            <option value="3">3rd Year</option>
                                            <option value="4">4th Year</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="section_code_add" class="form-label">
                                            <i class="bi bi-collection me-2"></i>Section Code
                                        </label>
                                        <select class="form-select" id="section_code_add" name="section_code" required>
                                            <option value="">Select Section Code</option>
                                            <?php
                                            foreach (range('A', 'Z') as $letter) {
                                                echo "<option value=\"$letter\">$letter</option>";
                                            }
                                            ?>
                                        </select>
                                        <small class="text-muted">Choose a unique section code (A-Z) for the selected year</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="academic_period_add" class="form-label">
                                            <i class="bi bi-calendar-week me-2"></i>Academic Period
                                        </label>
                                        <select class="form-select" id="academic_period_add" name="academic_period_id" required>
                                            <option value="">Select Academic Period</option>
                                            <?php foreach ($academic_periods as $period): ?>
                                                <option value="<?= $period['id'] ?>">
                                                    <?= htmlspecialchars($period['period_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="section_description_add" class="form-label">
                                            <i class="bi bi-text-paragraph me-2"></i>Description
                                        </label>
                                        <textarea class="form-control" id="section_description_add" name="section_description" rows="2" placeholder="Optional section description"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active_add" name="is_active" checked>
                                    <label class="form-check-label" for="is_active_add">
                                        <i class="bi bi-toggle-on me-2"></i>Active Section
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Course Assignment -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-book"></i>
                                Assign Courses
                                <span class="courses-count-badge" id="coursesCountBadge" style="display: none;">0 courses</span>
                            </div>
                            
                            <!-- Course Search -->
                            <div class="course-search-container">
                                <label for="courseSearchInput" class="form-label">
                                    <i class="bi bi-search me-2"></i>Search Courses
                                </label>
                                <div class="position-relative">
                                    <input type="text" class="form-control course-search-input" id="courseSearchInput" 
                                           placeholder="Type to search courses..." onkeyup="filterCourses()">
                                    <button type="button" class="course-search-clear" id="courseSearchClear" onclick="clearCourseSearch()">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Course Selection Container -->
                            <div class="courses-selection-container" id="coursesSelectionContainer">
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-book fs-1"></i>
                                    <p class="mb-0">Select a year level to load available courses</p>
                                </div>
                            </div>

                            <!-- Selected Courses Summary -->
                            <div class="selected-courses-summary" id="selectedCoursesSummary" style="display: none;">
                                <div class="selected-courses-count" id="selectedCoursesCount">0 courses selected</div>
                                <div class="selected-courses-list" id="selectedCoursesList"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="submit" name="add_section" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Create Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- Add Students Modal Template -->
<div class="modal fade" id="addStudentsModalTemplate" tabindex="-1" aria-labelledby="addStudentsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="sections.php">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addStudentsLabel">
                        <i class="bi bi-person-plus me-2"></i>Add Students to Section
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_students_section_id" id="addStudentsSectionId">
                    
                    <!-- Student Search and Filter -->
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <label for="studentSearchAdd" class="form-label fw-semibold">
                                <i class="bi bi-search me-2"></i>Search Students
                            </label>
                            <input type="text" class="form-control" id="studentSearchAdd" placeholder="Type student name..." onkeyup="searchStudentsAdd()">
                        </div>
                        <div class="col-md-4">
                            <label for="studentStatusFilterAdd" class="form-label fw-semibold">
                                <i class="bi bi-funnel me-2"></i>Filter by Status
                            </label>
                            <select class="form-select" id="studentStatusFilterAdd" onchange="filterStudentsAdd()">
                                <option value="available">Available Students Only</option>
                                <option value="regular">Regular Students</option>
                                <option value="irregular">Irregular Students</option>
                                <option value="all">All Students</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-gear me-2"></i>Actions
                            </label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyFilters()" id="filterBtn">
                                    <i class="bi bi-funnel me-1"></i>Filter
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearFilters()" id="clearFilterBtn">
                                    <i class="bi bi-x-circle me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Status Display -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="alert alert-info alert-sm mb-0" id="filterStatusAlert">
                                <i class="bi bi-info-circle me-2"></i>
                                <span id="filterStatusText">Loading students...</span>
                                <div class="float-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshStudentsList()" id="refreshBtn">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                    </button>
                                    <div class="spinner-border spinner-border-sm ms-2" role="status" id="refreshSpinner" style="display: none;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="badge bg-success ms-2" id="realtimeIndicator" style="display: none;">
                                        <i class="bi bi-wifi me-1"></i>Live
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Selection -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-flex align-items-center">
                            <i class="bi bi-people me-2"></i>Select Students to Add
                            <span class="badge bg-primary ms-2" id="selectedStudentsCountAdd">0</span>
                        </label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto; background: #f8f9fa;">
                            <div id="studentsListAdd">
                                <!-- Students will be loaded here via AJAX -->
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-people fs-1"></i>
                                    <p>Loading students...</p>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">Check the students you want to add to this section.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" name="add_students" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Add Selected Students
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Used codes per year from PHP
const usedCodesPerYear = <?php echo json_encode($used_codes_per_year); ?>;
function updateSectionCodeDropdown() {
    const year = document.getElementById('section_year_add').value;
    const codeSelect = document.getElementById('section_code_add');
    
    // Load courses for the selected year level
    loadCoursesForYear(year);
    
    // Enable all first
    for (let opt of codeSelect.options) {
        opt.disabled = false;
    }
    if (year && usedCodesPerYear[year]) {
        const used = usedCodesPerYear[year];
        for (let opt of codeSelect.options) {
            if (used.includes(opt.value)) {
                opt.disabled = true;
            }
        }
    }
    // Reset selection if current is disabled
    if (codeSelect.selectedOptions.length && codeSelect.selectedOptions[0].disabled) {
        codeSelect.value = '';
    }
}

// Global variables for course management
let allCourses = [];
let filteredCourses = [];
let selectedCourses = new Set();

// Function to load courses for selected year level
function loadCoursesForYear(yearLevel) {
    const coursesContainer = document.getElementById('coursesSelectionContainer');
    const coursesCountBadge = document.getElementById('coursesCountBadge');
    const courseSearchInput = document.getElementById('courseSearchInput');
    
    if (!yearLevel) {
        coursesContainer.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-book fs-1"></i>
                <p class="mb-0">Select a year level to load available courses</p>
            </div>
        `;
        coursesCountBadge.style.display = 'none';
        courseSearchInput.disabled = true;
        return;
    }
    
    // Show loading state
    coursesContainer.innerHTML = `
        <div class="courses-loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Loading courses for ${yearLevel}${yearLevel == '1' ? 'st' : yearLevel == '2' ? 'nd' : yearLevel == '3' ? 'rd' : 'th'} Year...</p>
        </div>
    `;
    
    // Make AJAX request to fetch courses
    fetch(`ajax_get_courses_by_year.php?year_level=${yearLevel}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allCourses = data.courses || [];
                filteredCourses = [...allCourses];
                selectedCourses.clear();
                
                // Update courses count badge
                if (allCourses.length > 0) {
                    coursesCountBadge.textContent = `${allCourses.length} courses available`;
                    coursesCountBadge.style.display = 'inline-block';
                    courseSearchInput.disabled = false;
                    renderCourses();
                } else {
                    coursesContainer.innerHTML = `
                        <div class="no-courses-state">
                            <i class="bi bi-book"></i>
                            <h6>No courses available</h6>
                            <p class="mb-0">No courses found for ${yearLevel}${yearLevel == '1' ? 'st' : yearLevel == '2' ? 'nd' : yearLevel == '3' ? 'rd' : 'th'} Year</p>
                        </div>
                    `;
                    coursesCountBadge.style.display = 'none';
                    courseSearchInput.disabled = true;
                }
            } else {
                console.error('Error loading courses:', data.error);
                coursesContainer.innerHTML = `
                    <div class="text-center text-danger py-4">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                        <h6>Error loading courses</h6>
                        <p class="mb-0">${data.error || 'Unknown error occurred'}</p>
                    </div>
                `;
                coursesCountBadge.style.display = 'none';
                courseSearchInput.disabled = true;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            coursesContainer.innerHTML = `
                <div class="text-center text-danger py-4">
                    <i class="bi bi-exclamation-triangle fs-1"></i>
                    <h6>Error loading courses</h6>
                    <p class="mb-0">Failed to load courses. Please try again.</p>
                </div>
            `;
            coursesCountBadge.style.display = 'none';
            courseSearchInput.disabled = true;
        });
}

// Function to render courses in the container
function renderCourses() {
    const coursesContainer = document.getElementById('coursesSelectionContainer');
    
    if (filteredCourses.length === 0) {
        coursesContainer.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-search fs-1"></i>
                <h6>No courses found</h6>
                <p class="mb-0">Try adjusting your search terms</p>
            </div>
        `;
        return;
    }
    
    let coursesHTML = '';
    filteredCourses.forEach(course => {
        const isSelected = selectedCourses.has(course.id);
        coursesHTML += `
            <div class="course-item ${isSelected ? 'selected' : ''}" onclick="toggleCourseSelection(${course.id})">
                <input type="checkbox" class="course-checkbox" ${isSelected ? 'checked' : ''} 
                       onchange="toggleCourseSelection(${course.id})" id="course_${course.id}">
                <div class="course-info">
                    <div class="course-name">${course.course_name}</div>
                    <div class="course-code">${course.course_code}</div>
                    ${course.description ? `<div class="course-description">${course.description}</div>` : ''}
                </div>
            </div>
        `;
    });
    
    coursesContainer.innerHTML = coursesHTML;
    updateSelectedCoursesSummary();
}

// Function to toggle course selection
function toggleCourseSelection(courseId) {
    if (selectedCourses.has(courseId)) {
        selectedCourses.delete(courseId);
    } else {
        selectedCourses.add(courseId);
    }
    
    // Update visual state
    const courseItem = document.querySelector(`[onclick="toggleCourseSelection(${courseId})"]`);
    const checkbox = document.getElementById(`course_${courseId}`);
    
    if (selectedCourses.has(courseId)) {
        courseItem.classList.add('selected');
        checkbox.checked = true;
    } else {
        courseItem.classList.remove('selected');
        checkbox.checked = false;
    }
    
    updateSelectedCoursesSummary();
}

// Function to filter courses based on search
function filterCourses() {
    const searchTerm = document.getElementById('courseSearchInput').value.toLowerCase();
    const clearButton = document.getElementById('courseSearchClear');
    
    // Show/hide clear button
    clearButton.style.display = searchTerm ? 'block' : 'none';
    
    if (searchTerm === '') {
        filteredCourses = [...allCourses];
    } else {
        filteredCourses = allCourses.filter(course => 
            course.course_name.toLowerCase().includes(searchTerm) ||
            course.course_code.toLowerCase().includes(searchTerm) ||
            (course.description && course.description.toLowerCase().includes(searchTerm))
        );
    }
    
    renderCourses();
}

// Function to clear course search
function clearCourseSearch() {
    document.getElementById('courseSearchInput').value = '';
    document.getElementById('courseSearchClear').style.display = 'none';
    filterCourses();
}

// Function to update selected courses summary
function updateSelectedCoursesSummary() {
    const summary = document.getElementById('selectedCoursesSummary');
    const count = document.getElementById('selectedCoursesCount');
    const list = document.getElementById('selectedCoursesList');
    
    if (selectedCourses.size === 0) {
        summary.style.display = 'none';
        return;
    }
    
    summary.style.display = 'block';
    count.textContent = `${selectedCourses.size} course${selectedCourses.size !== 1 ? 's' : ''} selected`;
    
    // Create hidden inputs for selected courses
    const existingInputs = document.querySelectorAll('input[name="assigned_courses[]"]');
    existingInputs.forEach(input => input.remove());
    
    let listHTML = '';
    selectedCourses.forEach(courseId => {
        const course = allCourses.find(c => c.id == courseId);
        if (course) {
            listHTML += `<span class="selected-course-tag">${course.course_name}</span>`;
            
            // Add hidden input for form submission
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'assigned_courses[]';
            hiddenInput.value = courseId;
            document.getElementById('addSectionForm').appendChild(hiddenInput);
        }
    });
    
    list.innerHTML = listHTML;
}

// Generic functions for all sections
function filterStudents(sectionId) {
    try {
        var filter = document.getElementById('studentStatusFilter' + sectionId).value;
        var modal = document.getElementById('assignUsersModal' + sectionId);
        var options = modal.querySelectorAll('.form-check.student-option-regular, .form-check.student-option-irregular');
    options.forEach(function(opt) {
        if (filter === 'all' || opt.getAttribute('data-status') === filter) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
        updateSelectedStudentsCount(modal);
    } catch (error) {
        console.error('Error in filterStudents:', error);
    }
}

function searchStudents(sectionId) {
    try {
        var input = document.getElementById('studentSearch' + sectionId).value.toLowerCase();
        var modal = document.getElementById('assignUsersModal' + sectionId);
        var options = modal.querySelectorAll('.form-check.student-option-regular, .form-check.student-option-irregular');
    options.forEach(function(opt) {
        var label = opt.querySelector('label');
        var text = label ? label.textContent.toLowerCase() : '';
        opt.style.display = text.includes(input) ? '' : 'none';
    });
        updateSelectedStudentsCount(modal);
    } catch (error) {
        console.error('Error in searchStudents:', error);
    }
}

function searchTeachers(sectionId) {
    try {
        var input = document.getElementById('teacherSearch' + sectionId).value.toLowerCase();
        var modal = document.getElementById('assignUsersModal' + sectionId);
        var options = modal.querySelectorAll('.form-check');
        options.forEach(function(opt) {
            var label = opt.querySelector('label');
            var text = label ? label.textContent.toLowerCase() : '';
            // Only show teacher options (not student options)
            if (opt.querySelector('input[name="teachers[]"]')) {
                opt.style.display = text.includes(input) ? '' : 'none';
            }
        });
    } catch (error) {
        console.error('Error in searchTeachers:', error);
    }
}
function updateSelectedStudentsCount(modal) {
    if (!modal) return;
    var count = modal.querySelectorAll('input[name="students[]"]:checked').length;
    var badge = modal.querySelector('.selectedStudentsCountBadge');
    if (badge) badge.textContent = count;
}
// On modal show, update the count for that modal
if (window.bootstrap && window.bootstrap.Modal) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function () {
            updateSelectedStudentsCount(modal);
        });
    });
} else {
    // Fallback for Bootstrap 4
    $(document).on('shown.bs.modal', '.modal', function() {
        updateSelectedStudentsCount(this);
    });
}
// On checkbox change, update the count for the parent modal only
document.addEventListener('change', function(e) {
    if (e.target.matches('input[name="students[]"]')) {
        var modal = e.target.closest('.modal');
        if (modal) updateSelectedStudentsCount(modal);
    }
});
function updateSelectedCoursesCount(modal) {
    if (!modal) return;
    var count = modal.querySelectorAll('input.assign-course-checkbox:checked').length;
    var badge = modal.querySelector('.selectedCoursesCountBadge');
    if (badge) badge.textContent = count;
}
// On modal show, update the count for that modal
if (window.bootstrap && window.bootstrap.Modal) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function () {
            updateSelectedCoursesCount(modal);
        });
    });
} else {
    // Fallback for Bootstrap 4
    $(document).on('shown.bs.modal', '.modal', function() {
        updateSelectedCoursesCount(this);
    });
}
// On checkbox change, update the count for the parent modal only
// (reuse if already present for students, otherwise add this)
document.addEventListener('change', function(e) {
    if (e.target.matches('input.assign-course-checkbox')) {
        var modal = e.target.closest('.modal');
        if (modal) updateSelectedCoursesCount(modal);
    }
});
function searchCoursesInModal(input) {
    var filter = input.value.toLowerCase();
    var list = input.closest('.mb-3').querySelector('.assign-courses-list');
    var options = list.querySelectorAll('.form-check');
    options.forEach(function(opt) {
        var label = opt.querySelector('label');
        var text = label ? label.textContent.toLowerCase() : '';
        opt.style.display = text.includes(filter) ? '' : 'none';
    });
}
function toggleClearBtn(input) {
    var clearBtn = input.parentElement.querySelector('span');
    clearBtn.style.display = input.value ? '' : 'none';
}
function clearCourseSearch(span) {
    var input = span.parentElement.querySelector('input');
    input.value = '';
    span.style.display = 'none';
    searchCoursesInModal(input);
    input.focus();
}

// Add Students Modal Functions
function openAddStudentsModal(sectionId) {
    // Create a unique modal for this section
    const modalId = 'addStudentsModal' + sectionId;
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        // Clone the template modal
        const template = document.getElementById('addStudentsModalTemplate');
        if (!template) {
            console.error('Template modal not found!');
            return;
        }
        modal = template.cloneNode(true);
        modal.id = modalId;
        modal.setAttribute('aria-labelledby', 'addStudentsLabel' + sectionId);
        
        // Update the title
        const title = modal.querySelector('.modal-title');
        title.id = 'addStudentsLabel' + sectionId;
        
        // Update the form action and hidden input
        const form = modal.querySelector('form');
        const hiddenInput = modal.querySelector('#addStudentsSectionId');
        hiddenInput.value = sectionId;
        
        // Add to the document
        document.body.appendChild(modal);
        
        // Load students for this section
        loadStudentsForSection(sectionId, modal);
        
        // Start auto-refresh when modal is shown
        modal.addEventListener('shown.bs.modal', function() {
            startAutoRefresh();
        });
        
        // Stop auto-refresh when modal is hidden
        modal.addEventListener('hidden.bs.modal', function() {
            stopAutoRefresh();
        });
    }
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function loadStudentsForSection(sectionId, modal) {
    const studentsList = modal.querySelector('#studentsListAdd');
    const refreshBtn = modal.querySelector('#refreshBtn');
    const refreshSpinner = modal.querySelector('#refreshSpinner');
    const realtimeIndicator = modal.querySelector('#realtimeIndicator');
    
    // Show loading state
    studentsList.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-arrow-clockwise fs-1"></i><p>Loading students...</p></div>';
    if (refreshBtn) refreshBtn.disabled = true;
    if (refreshSpinner) refreshSpinner.style.display = 'inline-block';
    if (realtimeIndicator) realtimeIndicator.style.display = 'none';
    
    // Store section ID for refresh functionality
    modal.dataset.sectionId = sectionId;
    
    // Fetch students via AJAX
    fetch('../ajax_get_available_students.php?section_id=' + sectionId + '&t=' + Date.now())
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayStudentsList(data.students, studentsList, data.invalid_students, data.target_section);
                // Store current data for real-time updates
                modal.dataset.lastUpdate = Date.now();
                modal.dataset.studentsData = JSON.stringify(data);
                
                // Show real-time indicator
                if (realtimeIndicator) {
                    realtimeIndicator.style.display = 'inline-block';
                    // Add pulsing animation
                    realtimeIndicator.classList.add('animate__animated', 'animate__pulse');
                }
            } else {
                studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error: ' + (data.message || 'Unknown error') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error fetching students:', error);
            studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error loading students: ' + error.message + '</p></div>';
        })
        .finally(() => {
            // Hide loading state
            if (refreshBtn) refreshBtn.disabled = false;
            if (refreshSpinner) refreshSpinner.style.display = 'none';
        });
}

function displayStudentsList(students, container, invalidStudents = [], targetSection = null) {
    let html = '';
    
    // Count students by type
    const regularStudents = students.filter(s => !s.is_irregular);
    const irregularStudents = students.filter(s => s.is_irregular);
    
    // Show target section info
    if (targetSection) {
        html += `
            <div class="alert alert-info mb-3">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Target Section:</strong> ${targetSection.section_name} (Year ${targetSection.year_level})
            </div>
        `;
    }
    
    // Show available students
    if (students.length === 0 && invalidStudents.length === 0) {
        html += '<div class="text-center text-muted py-4"><i class="bi bi-people fs-1"></i><p>No students available to add</p></div>';
    } else {
        // Available students section
        if (students.length > 0) {
            html += `
                <div class="mb-3">
                    <h6 class="text-success">
                        <i class="bi bi-check-circle me-2"></i>Available Students (${students.length})
                        <small class="text-muted ms-2">Regular: ${regularStudents.length} | Irregular: ${irregularStudents.length}</small>
                    </h6>
                    <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
            `;
            
            students.forEach(student => {
                const status = student.is_irregular ? 'irregular' : 'regular';
                const badge = student.is_irregular ? 
                    '<span class="badge bg-danger ms-2">Irregular</span>' : 
                    '<span class="badge bg-success ms-2">Regular</span>';
                const yearBadge = `<span class="badge bg-primary ms-1">${student.year_level_text}</span>`;
                
                html += `
                    <div class="form-check student-option-${status} mb-2" data-status="${status}">
                        <input class="form-check-input" type="checkbox" name="students_to_add[]" value="${student.id}" id="add_stu_${student.id}" onchange="updateSelectedStudentsCountAdd()">
                        <label class="form-check-label" for="add_stu_${student.id}">
                            ${student.name} ${badge} ${yearBadge}
                        </label>
                    </div>
                `;
            });
            
            html += '</div></div>';
        }
        
    }
    
    container.innerHTML = html;
    
    // Update filter status
    updateFilterStatus(students, invalidStudents, targetSection);
    
    // Set default filter to "available" and apply it
    const filterSelect = document.getElementById('studentStatusFilterAdd');
    const clearBtn = document.getElementById('clearFilterBtn');
    
    if (filterSelect) {
        filterSelect.value = 'available';
        filterStudentsAdd();
    }
    
    // Initialize button states
    if (clearBtn) {
        clearBtn.disabled = true; // Start with clear button disabled since no filters are applied yet
    }
    
    updateSelectedStudentsCountAdd();
}

function updateFilterStatus(students, invalidStudents, targetSection) {
    const statusAlert = document.getElementById('filterStatusAlert');
    const statusText = document.getElementById('filterStatusText');
    
    if (!statusAlert || !statusText) return;
    
    const regularCount = students.filter(s => !s.is_irregular).length;
    const irregularCount = students.filter(s => s.is_irregular).length;
    const totalAvailable = students.length;
    const totalInvalid = invalidStudents.length;
    const currentTime = new Date().toLocaleTimeString();
    
    let statusMessage = '';
    if (totalAvailable > 0) {
        statusMessage = `Showing ${totalAvailable} available students (${regularCount} regular, ${irregularCount} irregular)`;
        if (totalInvalid > 0) {
            statusMessage += ` | ${totalInvalid} students not eligible`;
        }
        statusMessage += ` | Last updated: ${currentTime}`;
    } else {
        statusMessage = 'No students available to add to this section';
        if (totalInvalid > 0) {
            statusMessage += ` (${totalInvalid} students not eligible)`;
        }
        statusMessage += ` | Last updated: ${currentTime}`;
    }
    
    statusText.textContent = statusMessage;
    
    // Update alert class based on availability
    statusAlert.className = totalAvailable > 0 ? 'alert alert-info alert-sm mb-0' : 'alert alert-warning alert-sm mb-0';
}

function searchStudentsAdd() {
    const input = document.getElementById('studentSearchAdd');
    const searchTerm = input.value.toLowerCase();
    const modal = input.closest('.modal');
    const options = modal.querySelectorAll('.student-option-regular, .student-option-irregular');
    const clearBtn = document.getElementById('clearFilterBtn');
    
    let visibleCount = 0;
    let regularCount = 0;
    let irregularCount = 0;
    
    options.forEach(opt => {
        const label = opt.querySelector('label');
        const text = label ? label.textContent.toLowerCase() : '';
        const status = opt.getAttribute('data-status');
        
        // Check if student matches search term
        const matchesSearch = searchTerm === '' || text.includes(searchTerm);
        
        // Check if student should be visible based on current filter
        const filterSelect = document.getElementById('studentStatusFilterAdd');
        const currentFilter = filterSelect ? filterSelect.value : 'available';
        let matchesFilter = false;
        
        switch (currentFilter) {
            case 'available':
                matchesFilter = true; // All available students
                break;
            case 'regular':
                matchesFilter = status === 'regular';
                break;
            case 'irregular':
                matchesFilter = status === 'irregular';
                break;
            case 'all':
                matchesFilter = true;
                break;
            default:
                matchesFilter = true;
        }
        
        const shouldShow = matchesSearch && matchesFilter;
        opt.style.display = shouldShow ? '' : 'none';
        
        if (shouldShow) {
            visibleCount++;
            if (status === 'regular') regularCount++;
            if (status === 'irregular') irregularCount++;
        }
    });
    
    // Enable/disable clear button based on whether there are active filters
    if (clearBtn) {
        const hasSearchTerm = searchTerm.length > 0;
        const filterSelect = document.getElementById('studentStatusFilterAdd');
        const hasCustomFilter = filterSelect && filterSelect.value !== 'available';
        clearBtn.disabled = !hasSearchTerm && !hasCustomFilter;
    }
    
    // Update filter status with search results
    updateFilterStatusDisplay(visibleCount, regularCount, irregularCount, 
        document.getElementById('studentStatusFilterAdd')?.value || 'available');
    
    updateSelectedStudentsCountAdd();
}

function filterStudentsAdd() {
    const filter = document.getElementById('studentStatusFilterAdd').value;
    const modal = document.getElementById('studentStatusFilterAdd').closest('.modal');
    const options = modal.querySelectorAll('.student-option-regular, .student-option-irregular');
    const clearBtn = document.getElementById('clearFilterBtn');
    const searchInput = document.getElementById('studentSearchAdd');
    
    let visibleCount = 0;
    let regularCount = 0;
    let irregularCount = 0;
    
    options.forEach(opt => {
        const status = opt.getAttribute('data-status');
        let shouldShow = false;
        
        switch (filter) {
            case 'available':
                // Show all available students (this is the default smart filter)
                shouldShow = true;
                break;
            case 'regular':
                shouldShow = status === 'regular';
                break;
            case 'irregular':
                shouldShow = status === 'irregular';
                break;
            case 'all':
                shouldShow = true;
                break;
            default:
                shouldShow = true;
        }
        
        opt.style.display = shouldShow ? '' : 'none';
        
        if (shouldShow) {
            visibleCount++;
            if (status === 'regular') regularCount++;
            if (status === 'irregular') irregularCount++;
        }
    });
    
    // Enable/disable clear button based on whether there are active filters
    if (clearBtn) {
        const hasSearchTerm = searchInput && searchInput.value.length > 0;
        const hasCustomFilter = filter !== 'available';
        clearBtn.disabled = !hasSearchTerm && !hasCustomFilter;
    }
    
    // Update filter status with current counts
    updateFilterStatusDisplay(visibleCount, regularCount, irregularCount, filter);
    
    updateSelectedStudentsCountAdd();
}

function updateFilterStatusDisplay(visibleCount, regularCount, irregularCount, currentFilter) {
    const statusAlert = document.getElementById('filterStatusAlert');
    const statusText = document.getElementById('filterStatusText');
    
    if (!statusAlert || !statusText) return;
    
    let statusMessage = '';
    
    switch (currentFilter) {
        case 'available':
            statusMessage = `Showing ${visibleCount} available students (${regularCount} regular, ${irregularCount} irregular)`;
            break;
        case 'regular':
            statusMessage = `Showing ${regularCount} regular students`;
            break;
        case 'irregular':
            statusMessage = `Showing ${irregularCount} irregular students`;
            break;
        case 'all':
            statusMessage = `Showing all ${visibleCount} students (${regularCount} regular, ${irregularCount} irregular)`;
            break;
        default:
            statusMessage = `Showing ${visibleCount} students`;
    }
    
    statusText.textContent = statusMessage;
    
    // Update alert class based on visibility
    statusAlert.className = visibleCount > 0 ? 'alert alert-info alert-sm mb-0' : 'alert alert-warning alert-sm mb-0';
}

function updateSelectedStudentsCountAdd() {
    const modal = document.querySelector('.modal.show');
    if (!modal) return;
    
    const count = modal.querySelectorAll('input[name="students_to_add[]"]:checked').length;
    const badge = modal.querySelector('#selectedStudentsCountAdd');
    if (badge) badge.textContent = count;
}

// Filter button functions
function applyFilters() {
    const filterBtn = document.getElementById('filterBtn');
    const clearBtn = document.getElementById('clearFilterBtn');
    
    // Add loading state to filter button
    if (filterBtn) {
        const originalText = filterBtn.innerHTML;
        filterBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Filtering...';
        filterBtn.disabled = true;
        
        // Apply the current filter
        filterStudentsAdd();
        
        // Reset button after a short delay
        setTimeout(() => {
            filterBtn.innerHTML = originalText;
            filterBtn.disabled = false;
        }, 500);
    }
    
    // Enable clear button
    if (clearBtn) {
        clearBtn.disabled = false;
    }
}

function clearFilters() {
    const searchInput = document.getElementById('studentSearchAdd');
    const filterSelect = document.getElementById('studentStatusFilterAdd');
    const clearBtn = document.getElementById('clearFilterBtn');
    const filterBtn = document.getElementById('filterBtn');
    
    // Clear search input
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Reset filter to default
    if (filterSelect) {
        filterSelect.value = 'available';
    }
    
    // Apply the cleared filters
    filterStudentsAdd();
    
    // Disable clear button since filters are now cleared
    if (clearBtn) {
        clearBtn.disabled = true;
    }
    
    // Enable filter button
    if (filterBtn) {
        filterBtn.disabled = false;
    }
}

// Real-time refresh functionality
function refreshStudentsList() {
    const modal = document.querySelector('.modal.show');
    if (!modal || !modal.dataset.sectionId) return;
    
    const sectionId = modal.dataset.sectionId;
    loadStudentsForSection(sectionId, modal);
}

// Auto-refresh functionality
function startAutoRefresh() {
    const modal = document.querySelector('.modal.show');
    if (!modal) return;
    
    // Clear existing interval
    if (modal.dataset.autoRefreshInterval) {
        clearInterval(modal.dataset.autoRefreshInterval);
    }
    
    // Set up auto-refresh every 15 seconds
    const intervalId = setInterval(() => {
        const modal = document.querySelector('.modal.show');
        if (modal && modal.dataset.sectionId) {
            // Only refresh if modal is visible and not being interacted with
            if (document.activeElement && document.activeElement.tagName === 'INPUT') {
                return; // Don't refresh while user is typing
            }
            
            refreshStudentsListWithChanges();
        } else {
            clearInterval(intervalId);
        }
    }, 15000); // 15 seconds
    
    modal.dataset.autoRefreshInterval = intervalId;
}

function stopAutoRefresh() {
    const modal = document.querySelector('.modal.show');
    if (modal && modal.dataset.autoRefreshInterval) {
        clearInterval(modal.dataset.autoRefreshInterval);
        delete modal.dataset.autoRefreshInterval;
    }
}

// Real-time update when students are added to sections
function notifyStudentAssignment(sectionId, studentIds) {
    // Check if any open modals need to be updated
    const openModals = document.querySelectorAll('.modal.show');
    openModals.forEach(modal => {
        if (modal.dataset.sectionId && modal.dataset.sectionId !== sectionId) {
            // This is a different section modal, refresh it
            refreshStudentsList();
        }
    });
}

// Enhanced refresh with change detection
function refreshStudentsListWithChanges() {
    const modal = document.querySelector('.modal.show');
    if (!modal || !modal.dataset.sectionId) return;
    
    const sectionId = modal.dataset.sectionId;
    const lastData = modal.dataset.studentsData;
    
    // Fetch fresh data
    fetch('../ajax_get_available_students.php?section_id=' + sectionId + '&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Compare with previous data to detect changes
                const currentData = JSON.stringify(data);
                if (lastData !== currentData) {
                    // Data has changed, update the display
                    const studentsList = modal.querySelector('#studentsListAdd');
                    displayStudentsList(data.students, studentsList, data.invalid_students, data.target_section);
                    modal.dataset.studentsData = currentData;
                    modal.dataset.lastUpdate = Date.now();
                    
                    // Show a subtle notification of the update
                    showUpdateNotification();
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing students:', error);
        });
}

function showUpdateNotification() {
    const modal = document.querySelector('.modal.show');
    if (!modal) return;
    
    const notification = document.createElement('div');
    notification.className = 'alert alert-success alert-sm position-absolute';
    notification.style.cssText = 'top: 10px; right: 10px; z-index: 9999; min-width: 200px;';
    notification.innerHTML = '<i class="bi bi-check-circle me-2"></i>Student list updated';
    
    modal.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}

// View Section Students Function
function viewSectionStudents(sectionId, sectionName) {
    // Create a modal to display students
    const modalId = 'viewStudentsModal' + sectionId;
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        // Create modal HTML
        modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = modalId;
        modal.innerHTML = `
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-people me-2"></i>Students in ${sectionName}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Bulk Actions Bar -->
                        <div class="row mb-3" id="bulkActionsBar" style="display: none;">
                            <div class="col-12">
                                <div class="alert alert-warning d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <span id="selectedCount">0</span> student(s) selected for removal
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-danger me-2" onclick="confirmBulkRemove(${sectionId})">
                                            <i class="bi bi-person-x me-1"></i>Remove Selected
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearBulkSelection()">
                                            <i class="bi bi-x-circle me-1"></i>Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="studentsList${sectionId}">
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-arrow-clockwise fs-1"></i>
                                <p>Loading students...</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary" onclick="toggleBulkMode(${sectionId})" id="bulkModeBtn">
                            <i class="bi bi-check-square me-2"></i>Select Multiple
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Load students for this section
        loadSectionStudents(sectionId, modal);
    }
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function loadSectionStudents(sectionId, modal) {
    const studentsList = modal.querySelector(`#studentsList${sectionId}`);
    
    // Fetch students via AJAX
    fetch('../ajax_get_section_students.php?section_id=' + sectionId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displaySectionStudentsList(data.students, studentsList, data.debug);
            } else {
                studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error: ' + (data.message || 'Unknown error') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error fetching section students:', error);
            studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error loading students: ' + error.message + '</p></div>';
        });
}

function displaySectionStudentsList(students, container, debugInfo = null) {
    if (students.length === 0) {
        
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-people fs-1"></i>
                <p class="mt-2">No students assigned to this section</p>
                <small class="text-muted">Use the "Add Students" button to assign students to this section</small>
            </div>
            `;
        return;
    }
    
    
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
                <i class="bi bi-people-fill text-info me-2"></i>
                Total Students: <span class="badge bg-info">${students.length}</span>
                ${debugInfo ? `<small class="text-muted ms-2">(JSON: ${debugInfo.total_in_json})</small>` : ''}
            </h6>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="exportSectionStudents(${students[0].section_id})" title="Export to CSV">
                    <i class="bi bi-download me-1"></i>Export
                </button>
                <button class="btn btn-sm btn-outline-success" onclick="openAddStudentsModal(${students[0].section_id})" title="Add More Students">
                    <i class="bi bi-person-plus me-1"></i>Add More
                </button>
            </div>
        </div>
        <div class="table-scrollable">
            <table class="table table-sm table-hover">
                <thead class="table-light">
                    <tr>
                        <th width="50" id="bulkSelectHeader" style="display: none;">
                            <input type="checkbox" id="selectAllStudents" onchange="toggleSelectAll()">
                        </th>
                        <th><i class="bi bi-hash me-1"></i>#</th>
                        <th><i class="bi bi-person me-1"></i>Name</th>
                        <th><i class="bi bi-card-text me-1"></i>Student ID</th>
                        <th><i class="bi bi-tag me-1"></i>Status</th>
                        <th class="text-center"><i class="bi bi-gear me-1"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>`;
    
    students.forEach((student, index) => {
        const status = student.is_irregular ? 'irregular' : 'regular';
        const badge = student.is_irregular ? 
            '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Irregular</span>' : 
            '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Regular</span>';
        
        html += `
            <tr>
                <td class="text-center" id="bulkSelectCell_${student.id}" style="display: none;">
                    <input type="checkbox" class="student-checkbox" value="${student.id}" onchange="updateBulkSelection()">
                </td>
                <td class="text-muted">${index + 1}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                            <i class="bi bi-person text-white"></i>
                        </div>
                        <strong>${student.name}</strong>
                    </div>
                </td>
                <td><code class="bg-light px-2 py-1 rounded">${student.identifier || 'No ID'}</code></td>
                <td>${badge}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-info" onclick="viewStudentProfile(${student.id})" title="View Profile">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="editStudentSection(${student.id}, ${student.section_id})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="removeStudentFromSection(${student.id}, ${student.section_id})" title="Remove from Section">
                            <i class="bi bi-person-x"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Bulk selection functions
function toggleBulkMode(sectionId) {
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const bulkModeBtn = document.getElementById('bulkModeBtn');
    const bulkSelectHeader = document.getElementById('bulkSelectHeader');
    const bulkSelectCells = document.querySelectorAll('[id^="bulkSelectCell_"]');
    
    if (bulkActionsBar.style.display === 'none') {
        // Enable bulk mode
        bulkActionsBar.style.display = 'block';
        bulkSelectHeader.style.display = 'table-cell';
        bulkSelectCells.forEach(cell => cell.style.display = 'table-cell');
        bulkModeBtn.innerHTML = '<i class="bi bi-x-square me-2"></i>Exit Selection';
        bulkModeBtn.className = 'btn btn-outline-secondary';
    } else {
        // Disable bulk mode
        clearBulkSelection();
        bulkActionsBar.style.display = 'none';
        bulkSelectHeader.style.display = 'none';
        bulkSelectCells.forEach(cell => cell.style.display = 'none');
        bulkModeBtn.innerHTML = '<i class="bi bi-check-square me-2"></i>Select Multiple';
        bulkModeBtn.className = 'btn btn-outline-primary';
    }
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllStudents');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    
    studentCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateBulkSelection();
}

function updateBulkSelection() {
    const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
    const selectedCount = document.getElementById('selectedCount');
    const selectAllCheckbox = document.getElementById('selectAllStudents');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    
    if (selectedCount) {
        selectedCount.textContent = selectedCheckboxes.length;
    }
    
    // Update select all checkbox state
    if (selectAllCheckbox) {
        if (selectedCheckboxes.length === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (selectedCheckboxes.length === studentCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }
}

function clearBulkSelection() {
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllStudents');
    
    studentCheckboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    }
    
    updateBulkSelection();
}

function confirmBulkRemove(sectionId) {
    const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        showAlert('warning', 'Please select at least one student to remove.');
        return;
    }
    
    const studentIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
    const studentNames = Array.from(selectedCheckboxes).map(checkbox => {
        const row = checkbox.closest('tr');
        const nameCell = row.querySelector('td:nth-child(3) strong');
        return nameCell ? nameCell.textContent : 'Unknown';
    });
    
    const confirmMessage = `Are you sure you want to remove the following ${studentIds.length} student(s) from this section?\n\n${studentNames.join('\n')}`;
    
    if (confirm(confirmMessage)) {
        removeMultipleStudentsFromSection(sectionId, studentIds);
    }
}

function removeMultipleStudentsFromSection(sectionId, studentIds) {
    // Show loading state
    const bulkActionsBar = document.querySelector('#bulkActionsBar .alert');
    const originalContent = bulkActionsBar.innerHTML;
    bulkActionsBar.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Removing students...';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'remove_multiple_students');
    formData.append('section_id', sectionId);
    formData.append('student_ids', JSON.stringify(studentIds));
    
    // Send request
    fetch('sections.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Check if response contains success message
        if (data.includes('Students removed successfully')) {
            showAlert('success', `Successfully removed ${studentIds.length} student(s) from the section.`);
            
            // Refresh the student list
            const modal = document.querySelector('.modal.show');
            if (modal) {
                const studentsList = modal.querySelector(`#studentsList${sectionId}`);
                loadSectionStudents(sectionId, modal);
            }
            
            // Clear selection and exit bulk mode
            clearBulkSelection();
            toggleBulkMode(sectionId);
        } else {
            showAlert('danger', 'Error removing students. Please try again.');
            bulkActionsBar.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Error removing students:', error);
        showAlert('danger', 'Error removing students. Please try again.');
        bulkActionsBar.innerHTML = originalContent;
    });
}

// Utility function to format year level
function formatYear(yearLevel) {
    if (yearLevel == 1) return '1st Year';
    if (yearLevel == 2) return '2nd Year';
    if (yearLevel == 3) return '3rd Year';
    if (yearLevel == 4) return '4th Year';
    return yearLevel + 'th Year';
}

// Course details functions
function showCourseDetails(courseCode, courseName, yearLevel, status) {
    // Update modal content
    document.getElementById('courseCodeDisplay').textContent = courseCode;
    document.getElementById('courseNameDisplay').textContent = courseName;
    document.getElementById('courseYearDisplay').textContent = formatYear(yearLevel);
    
    // Update status with proper styling
    const statusDisplay = document.getElementById('courseStatusDisplay');
    if (status === 'active') {
        statusDisplay.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>';
    } else {
        statusDisplay.innerHTML = '<span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>Inactive</span>';
    }
    
    // Load course details via AJAX
    loadCourseDetails(courseCode);
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('courseDetailsModal'));
    modal.show();
}

function loadCourseDetails(courseCode) {
    // Show loading state
    document.getElementById('assignedTeachersDisplay').innerHTML = '<span class="text-muted">Loading teachers...</span>';
    document.getElementById('assignedSectionsDisplay').innerHTML = '<span class="text-muted">Loading sections...</span>';
    document.getElementById('courseDescriptionDisplay').innerHTML = '<span class="text-muted">Loading description...</span>';
    
    // Fetch course details
    fetch(`ajax_get_course_details.php?course_code=${encodeURIComponent(courseCode)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update description
                const description = data.course.description || 'No description available';
                document.getElementById('courseDescriptionDisplay').textContent = description;
                
                // Update assigned teachers
                const teachersDisplay = document.getElementById('assignedTeachersDisplay');
                if (data.teachers && data.teachers.length > 0) {
                    let teachersHtml = '<div class="d-flex flex-wrap gap-2">';
                    data.teachers.forEach(teacher => {
                        teachersHtml += `
                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                                <i class="bi bi-person-workspace me-1"></i>${teacher.first_name} ${teacher.last_name}
                                <small class="ms-1">(${teacher.email})</small>
                            </span>
                        `;
                    });
                    teachersHtml += '</div>';
                    teachersHtml += `<small class="text-muted d-block mt-2">${data.teachers.length} teacher(s) assigned</small>`;
                    teachersDisplay.innerHTML = teachersHtml;
                } else {
                    teachersDisplay.innerHTML = '<span class="text-muted">No teachers assigned</span>';
                }
                
                // Update assigned sections
                const sectionsDisplay = document.getElementById('assignedSectionsDisplay');
                if (data.sections && data.sections.length > 0) {
                    let sectionsHtml = '<div class="d-flex flex-wrap gap-2">';
                    data.sections.forEach(section => {
                        sectionsHtml += `
                            <span class="badge bg-primary px-3 py-2 rounded-pill">
                                <i class="bi bi-collection me-1"></i>${section.section_name}
                                <small class="ms-1">(${formatYear(section.year_level)})</small>
                            </span>
                        `;
                    });
                    sectionsHtml += '</div>';
                    sectionsHtml += `<small class="text-muted d-block mt-2">${data.sections.length} section(s) assigned</small>`;
                    sectionsDisplay.innerHTML = sectionsHtml;
                } else {
                    sectionsDisplay.innerHTML = '<span class="text-muted">No sections assigned</span>';
                }
            } else {
                document.getElementById('assignedTeachersDisplay').innerHTML = '<span class="text-danger">Error loading course details</span>';
                document.getElementById('assignedSectionsDisplay').innerHTML = '<span class="text-danger">Error loading course details</span>';
                document.getElementById('courseDescriptionDisplay').innerHTML = '<span class="text-danger">Error loading description</span>';
            }
        })
        .catch(error => {
            console.error('Error loading course details:', error);
            document.getElementById('assignedTeachersDisplay').innerHTML = '<span class="text-danger">Error loading course details</span>';
            document.getElementById('assignedSectionsDisplay').innerHTML = '<span class="text-danger">Error loading course details</span>';
            document.getElementById('courseDescriptionDisplay').innerHTML = '<span class="text-danger">Error loading description</span>';
        });
}

// Function to export section students to CSV
function exportSectionStudents(sectionId) {
    // Get section name first
    const sectionRow = document.querySelector(`tr[data-section-id="${sectionId}"]`);
    const sectionName = sectionRow ? sectionRow.querySelector('h6')?.textContent || 'Section' : 'Section';
    
    // Fetch students data
    fetch(`../ajax_get_section_students.php?section_id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.students.length > 0) {
                // Create CSV content
                let csvContent = 'data:text/csv;charset=utf-8,';
                
                // Add header
                csvContent += 'No.,Name,Student ID,Status,Section\n';
                
                // Add data rows
                data.students.forEach((student, index) => {
                    const status = student.is_irregular ? 'Irregular' : 'Regular';
                    const row = `${index + 1},"${student.name}","${student.identifier || 'No ID'}","${status}","${sectionName}"\n`;
                    csvContent += row;
                });
                
                // Create download link
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', `${sectionName}_Students_${new Date().toISOString().split('T')[0]}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Show success message
                showAlert('success', `CSV exported successfully! ${data.students.length} students exported.`);
            } else {
                showAlert('warning', 'No students to export.');
            }
        })
        .catch(error => {
            console.error('Export error:', error);
            showAlert('danger', 'Error exporting CSV. Please try again.');
        });
}

// Function to view student profile
function viewStudentProfile(studentId) {
    // Fetch student data
    fetch(`../ajax_get_student_profile.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStudentProfileModal(data.student);
            } else {
                showAlert('danger', data.message || 'Error loading student profile.');
            }
        })
        .catch(error => {
            console.error('Profile error:', error);
            showAlert('danger', 'Error loading student profile. Please try again.');
        });
}

// Function to show student profile modal
function showStudentProfileModal(student) {
    // Create modal HTML
    const modalHtml = `
        <div class="modal fade" id="studentProfileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-person-circle me-2"></i>Student Profile
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="mb-3">
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                        <i class="bi bi-person text-white" style="font-size: 3rem;"></i>
                                    </div>
                                </div>
                                <h5 class="mb-1">${student.first_name} ${student.last_name}</h5>
                                <span class="badge ${student.is_irregular ? 'bg-danger' : 'bg-success'} mb-2">
                                    ${student.is_irregular ? 'Irregular' : 'Regular'}
                                </span>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-muted">Student ID</label>
                                            <p class="mb-0">${student.identifier || 'No ID'}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-muted">Email</label>
                                            <p class="mb-0">${student.email}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-muted">Username</label>
                                            <p class="mb-0">${student.username}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-muted">Status</label>
                                            <p class="mb-0">
                                                <span class="badge ${student.status === 'active' ? 'bg-success' : 'bg-danger'}">
                                                    ${student.status}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-muted">Enrolled Courses</label>
                                    <div class="list-group list-group-flush">
                                        ${student.enrolled_courses && student.enrolled_courses.length > 0 ? 
                                            student.enrolled_courses.map(course => 
                                                `<div class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span>${course.course_name}</span>
                                                    <span class="badge bg-info">${course.progress || 0}%</span>
                                                </div>`
                                            ).join('') : 
                                            '<p class="text-muted mb-0">No courses enrolled</p>'
                                        }
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Close
                        </button>
                        <button type="button" class="btn btn-info" onclick="viewStudentAssessmentDetails(${student.id}, '${student.first_name} ${student.last_name}')">
                            <i class="bi bi-clipboard-data me-2"></i>View Assessments
                        </button>
                        <button type="button" class="btn btn-primary" onclick="editStudentSection(${student.id}, ${student.section_id || 0})">
                            <i class="bi bi-pencil me-2"></i>Edit Section
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('studentProfileModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('studentProfileModal'));
    modal.show();
}

// Function to edit student section assignment
function editStudentSection(studentId, sectionId) {
    // Fetch current sections and student info
    Promise.all([
        fetch('../ajax_get_all_sections.php'),
        fetch(`../ajax_get_student_profile.php?student_id=${studentId}`)
    ])
    .then(responses => Promise.all(responses.map(r => r.json())))
    .then(([sectionsData, studentData]) => {
        if (sectionsData.success && studentData.success) {
            showEditStudentSectionModal(studentData.student, sectionsData.sections, sectionId);
        } else {
            showAlert('danger', 'Error loading data. Please try again.');
        }
    })
    .catch(error => {
        console.error('Edit error:', error);
        showAlert('danger', 'Error loading data. Please try again.');
    });
}

// Function to show edit student section modal
function showEditStudentSectionModal(student, sections, currentSectionId) {
    const modalHtml = `
        <div class="modal fade" id="editStudentSectionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil-square me-2"></i>Edit Student Section
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 60px; height: 60px;">
                                <i class="bi bi-person text-white" style="font-size: 1.5rem;"></i>
                            </div>
                            <h6 class="mb-1">${student.first_name} ${student.last_name}</h6>
                            <small class="text-muted">${student.identifier || 'No ID'}</small>
                        </div>
                        
                        <form id="editStudentSectionForm">
                            <input type="hidden" name="student_id" value="${student.id}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-collection me-2"></i>Current Section
                                </label>
                                <p class="text-muted mb-2">
                                    ${currentSectionId > 0 ? 
                                        sections.find(s => s.id == currentSectionId)?.section_name || 'Unknown' : 
                                        'Not assigned to any section'
                                    }
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <label for="newSectionId" class="form-label fw-semibold">
                                    <i class="bi bi-arrow-right me-2"></i>New Section
                                </label>
                                <select class="form-select" id="newSectionId" name="new_section_id" required>
                                    <option value="">Select a section...</option>
                                    <option value="0">Remove from all sections</option>
                                    ${sections.map(section => 
                                        `<option value="${section.id}" ${section.id == currentSectionId ? 'selected' : ''}>
                                            ${section.section_name} (Year ${section.year_level})
                                        </option>`
                                    ).join('')}
                                </select>
                                <small class="text-muted">Choose a section or select "Remove from all sections" to unassign</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-info-circle me-2"></i>Student Status
                                </label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isIrregular" name="is_irregular" ${student.is_irregular ? 'checked' : ''}>
                                    <label class="form-check-label" for="isIrregular">
                                        Mark as Irregular Student
                                    </label>
                                </div>
                                <small class="text-muted">Irregular students may have different requirements or schedules</small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-warning" onclick="saveStudentSectionChanges()">
                            <i class="bi bi-check-circle me-2"></i>Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('editStudentSectionModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editStudentSectionModal'));
    modal.show();
}

// Function to save student section changes
function saveStudentSectionChanges() {
    const form = document.getElementById('editStudentSectionForm');
    const formData = new FormData(form);
    
    // Get additional form data
    const newSectionId = document.getElementById('newSectionId').value;
    const isIrregular = document.getElementById('isIrregular').checked;
    
    const data = {
        student_id: formData.get('student_id'),
        new_section_id: newSectionId,
        is_irregular: isIrregular ? 1 : 0
    };
    
    // Send update request
    fetch('../ajax_update_student_section.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('success', 'Student section updated successfully!');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editStudentSectionModal'));
            modal.hide();
            
            // Refresh the page to show updated data
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('danger', result.message || 'Error updating student section.');
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        showAlert('danger', 'Error saving changes. Please try again.');
    });
}

// Function to show alerts
function showAlert(type, message) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create alert HTML
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Add alert to body
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Function to remove student from section
function removeStudentFromSection(studentId, sectionId) {
    if (!confirm('Are you sure you want to remove this student from the section?')) {
        return;
    }
    
    const data = {
        student_id: studentId,
        new_section_id: 0, // 0 means remove from all sections
        is_irregular: 0 // Keep current irregular status
    };
    
    fetch('../ajax_update_student_section.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('success', 'Student removed from section successfully!');
            // Refresh the page to show updated data
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('danger', result.message || 'Error removing student from section.');
        }
    })
    .catch(error => {
        console.error('Remove error:', error);
        showAlert('danger', 'Error removing student. Please try again.');
    });
}

// Real-time Performance Data Functions
let autoRefreshInterval = null;
let isAutoRefreshEnabled = false;

// Load performance data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadPerformanceData();
});

function loadPerformanceData() {
    fetch('../ajax_get_performance_summary.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updatePerformanceOverview(data.data.overview);
                updateRecentActivity(data.data.recent_activity);
                updateQuestionTypePerformance(data.data.question_type_performance);
                updateSectionPerformance(data.data.section_performance);
                updateLiveIndicator();
            } else {
                console.error('Error loading performance data:', data.message);
                showPerformanceError(data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching performance data:', error);
            showPerformanceError('Failed to load performance data');
        });
}

function updatePerformanceOverview(overview) {
    document.getElementById('totalStudents').textContent = overview.total_students;
    document.getElementById('totalAttempts').textContent = overview.total_attempts;
    document.getElementById('averageScore').textContent = overview.average_score + '%';
    document.getElementById('passingRate').textContent = overview.passing_rate + '%';
}

function updateRecentActivity(activity) {
    document.getElementById('activeStudentsToday').textContent = activity.active_students_today;
    document.getElementById('attemptsToday').textContent = activity.attempts_today;
}

function updateQuestionTypePerformance(questionTypes) {
    const container = document.getElementById('questionTypePerformance');
    
    if (questionTypes.length === 0) {
        container.innerHTML = '<div class="text-center text-muted"><i class="bi bi-info-circle"></i> No data available</div>';
        return;
    }
    
    let html = '';
    questionTypes.forEach(type => {
        const accuracyClass = type.accuracy_rate >= 80 ? 'text-success' : 
                            type.accuracy_rate >= 60 ? 'text-warning' : 'text-danger';
        html += `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-medium">${type.question_type.replace('_', ' ').toUpperCase()}</span>
                <span class="badge ${accuracyClass.replace('text-', 'bg-')} text-white">
                    ${type.accuracy_rate}%
                </span>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateSectionPerformance(sections) {
    const tbody = document.getElementById('sectionPerformanceTable');
    
    if (sections.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-2 small"><i class="bi bi-info-circle me-1"></i>No performance data available</td></tr>';
        return;
    }
    
    let html = '';
    sections.forEach((section, index) => {
        const rank = index + 1;
        const performanceClass = section.avg_score >= 80 ? 'success' : 
                               section.avg_score >= 60 ? 'warning' : 'danger';
        const passingClass = section.passing_rate >= 70 ? 'success' : 
                           section.passing_rate >= 50 ? 'warning' : 'danger';
        
        html += `
            <tr>
                <td class="py-1">
                    <span class="badge bg-${rank <= 3 ? 'primary' : 'secondary'} text-white small">
                        #${rank}
                    </span>
                </td>
                <td class="py-1">
                    <div class="fw-semibold small">${section.section_name}</div>
                </td>
                <td class="py-1 text-center">
                    <span class="badge bg-info text-white small">${section.student_count}</span>
                </td>
                <td class="py-1 text-center">
                    <span class="badge bg-${performanceClass} text-white small">${section.avg_score}%</span>
                </td>
                <td class="py-1 text-center">
                    <span class="badge bg-secondary text-white small">${section.total_attempts}</span>
                </td>
                <td class="py-1 text-center">
                    <span class="badge bg-${passingClass} text-white small">${section.passing_rate}%</span>
                </td>
                <td class="py-1 text-center">
                    <div class="progress" style="height: 6px; width: 60px; margin: 0 auto;">
                        <div class="progress-bar bg-${performanceClass}" style="width: ${section.avg_score}%"></div>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function updateLiveIndicator() {
    const indicator = document.getElementById('liveIndicator');
    indicator.innerHTML = '<i class="bi bi-circle-fill me-1"></i>Live';
    indicator.className = 'badge bg-success ms-2';
    
    // Add a subtle animation
    indicator.style.animation = 'pulse 1s ease-in-out';
    setTimeout(() => {
        indicator.style.animation = '';
    }, 1000);
}

function showPerformanceError(message) {
    const overview = document.getElementById('performanceOverview');
    overview.innerHTML = `
        <div class="col-12">
            <div class="alert alert-danger text-center">
                <i class="bi bi-exclamation-triangle me-2"></i>
                ${message}
            </div>
        </div>
    `;
}

function refreshPerformanceData() {
    const refreshBtn = document.getElementById('refreshBtn');
    const originalContent = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Refreshing...';
    refreshBtn.disabled = true;
    
    loadPerformanceData();
    
    setTimeout(() => {
        refreshBtn.innerHTML = originalContent;
        refreshBtn.disabled = false;
    }, 1000);
}

function toggleAutoRefresh() {
    const autoRefreshBtn = document.getElementById('autoRefreshBtn');
    
    if (isAutoRefreshEnabled) {
        clearInterval(autoRefreshInterval);
        isAutoRefreshEnabled = false;
        autoRefreshBtn.innerHTML = '<i class="bi bi-play-circle me-1"></i>Auto Refresh';
        autoRefreshBtn.className = 'btn btn-sm btn-outline-info';
    } else {
        autoRefreshInterval = setInterval(loadPerformanceData, 30000); // Refresh every 30 seconds
        isAutoRefreshEnabled = true;
        autoRefreshBtn.innerHTML = '<i class="bi bi-pause-circle me-1"></i>Stop Auto';
        autoRefreshBtn.className = 'btn btn-sm btn-outline-warning';
    }
}

// Enhanced Student Answer Display Functions
function viewStudentAssessmentDetails(studentId, studentName) {
    // Show loading modal
    const modalHtml = `
        <div class="modal fade" id="studentAssessmentModal" tabindex="-1" aria-labelledby="studentAssessmentLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="studentAssessmentLabel">
                            <i class="bi bi-clipboard-data me-2"></i>Assessment Details - ${studentName}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading assessment details...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('studentAssessmentModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('studentAssessmentModal'));
    modal.show();
    
    // Fetch assessment details
    fetch(`../ajax_get_student_assessment_details.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStudentAssessmentDetails(data.data);
            } else {
                showAssessmentError(data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching assessment details:', error);
            showAssessmentError('Failed to load assessment details');
        });
}

function displayStudentAssessmentDetails(studentData) {
    const modalBody = document.querySelector('#studentAssessmentModal .modal-body');
    
    let html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body text-center">
                        <h6 class="fw-bold text-primary mb-2">Student Information</h6>
                        <div class="d-flex align-items-center justify-content-center mb-2">
                            <i class="bi bi-person-circle fs-1 text-primary me-3"></i>
                            <div class="text-start">
                                <div class="fw-bold">${studentData.student_name}</div>
                                <small class="text-muted">${studentData.username}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-body text-center">
                        <h6 class="fw-bold text-primary mb-2">Assessment Summary</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="fw-bold text-success">${studentData.attempts.length}</div>
                                <small class="text-muted">Total Attempts</small>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold text-info">${calculateAverageScore(studentData.attempts)}%</div>
                                <small class="text-muted">Average Score</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    if (studentData.attempts && studentData.attempts.length > 0) {
        html += '<div class="accordion" id="assessmentAccordion">';
        
        studentData.attempts.forEach((attempt, index) => {
            const statusClass = attempt.status === 'completed' ? 'success' : 'warning';
            const scoreClass = attempt.score >= 70 ? 'success' : attempt.score >= 50 ? 'warning' : 'danger';
            
            html += `
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading${index}">
                        <button class="accordion-button ${index === 0 ? '' : 'collapsed'}" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#collapse${index}" 
                                aria-expanded="${index === 0 ? 'true' : 'false'}" aria-controls="collapse${index}">
                            <div class="d-flex align-items-center w-100">
                                <div class="flex-grow-1">
                                    <div class="fw-bold">${attempt.assessment_title}</div>
                                    <small class="text-muted">${attempt.course_name} (${attempt.course_code})</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-${statusClass} text-white">${attempt.status}</span>
                                    <span class="badge bg-${scoreClass} text-white">${attempt.score}%</span>
                                    <span class="badge bg-secondary text-white">${attempt.difficulty}</span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse${index}" class="accordion-collapse collapse ${index === 0 ? 'show' : ''}" 
                         aria-labelledby="heading${index}" data-bs-parent="#assessmentAccordion">
                        <div class="accordion-body">
                            ${displayAttemptAnswers(attempt.answers)}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    } else {
        html += `
            <div class="text-center py-4">
                <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                <p class="text-muted mt-2">No assessment attempts found for this student.</p>
            </div>
        `;
    }
    
    modalBody.innerHTML = html;
}

function displayAttemptAnswers(answers) {
    if (!answers || answers.length === 0) {
        return '<div class="text-center text-muted py-3">No answers available for this attempt.</div>';
    }
    
    let html = '<div class="row">';
    
    answers.forEach((answer, index) => {
        const isCorrect = answer.is_correct;
        const correctClass = isCorrect ? 'success' : 'danger';
        const iconClass = isCorrect ? 'check-circle' : 'x-circle';
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="card border-0 ${isCorrect ? 'border-success' : 'border-danger'}">
                    <div class="card-header bg-${correctClass} text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Question ${index + 1}</span>
                            <div>
                                <i class="bi bi-${iconClass} me-1"></i>
                                <span class="badge bg-white text-${correctClass}">${answer.points_earned}/${answer.points} pts</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="fw-bold text-dark">${answer.question_text}</h6>
                            <span class="badge bg-secondary">${answer.question_type.replace('_', ' ').toUpperCase()}</span>
                        </div>
                        
                        <div class="mb-2">
                            <strong class="text-primary">Student Answer:</strong>
                            <div class="mt-1 p-2 bg-light rounded">
                                <span class="text-${correctClass} fw-medium">${answer.formatted_answer}</span>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <strong class="text-success">Correct Answer:</strong>
                            <div class="mt-1 p-2 bg-light rounded">
                                <span class="text-success fw-medium">${answer.formatted_correct_answer}</span>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    return html;
}

function calculateAverageScore(attempts) {
    if (!attempts || attempts.length === 0) return 0;
    
    const totalScore = attempts.reduce((sum, attempt) => sum + (attempt.score || 0), 0);
    return Math.round(totalScore / attempts.length);
}

function showAssessmentError(message) {
    const modalBody = document.querySelector('#studentAssessmentModal .modal-body');
    modalBody.innerHTML = `
        <div class="alert alert-danger text-center">
            <i class="bi bi-exclamation-triangle me-2"></i>
            ${message}
        </div>
    `;
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    .accordion-button:not(.collapsed) {
        background-color: #e7f3ff;
        border-color: #b3d9ff;
    }
    
    .card.border-success {
        border-left: 4px solid #28a745 !important;
    }
    
    .card.border-danger {
        border-left: 4px solid #dc3545 !important;
    }
`;
document.head.appendChild(style);
</script>

<?php require_once '../includes/footer.php'; ?> 