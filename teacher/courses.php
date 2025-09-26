<?php
require_once '../config/database.php';
require_once '../includes/header.php';
?>
<!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    /* Import Google Fonts for professional typography */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    
    /* System Color Variables - Matching Announcements Page */
    :root {
        --main-green: #2E5E4E;      /* Deep, modern green */
        --accent-green: #7DCB80;    /* Light, fresh green */
        --highlight-yellow: #FFE066;/* Softer yellow for highlights */
        --off-white: #F7FAF7;       /* Clean, soft background */
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
    
    /* Global Styles */
    body {
        font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: var(--off-white);
        min-height: 100vh;
    }
    
    .container-fluid {
        margin-top: 0 !important;
        padding-top: 0 !important;
        background: transparent;
    }
    
    .row:first-child {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    
    .mb-4 {
        margin-bottom: 1.5rem !important;
    }
    
    .mb-3 {
        margin-bottom: 1rem !important;
    }
    
    .card:last-child {
        margin-bottom: 2rem !important;
    }
    
    .text-center.py-5 {
        padding-bottom: 3rem !important;
        margin-bottom: 2rem !important;
    }
    
    .course-card {
        transition: var(--transition);
        height: 100%;
        border: 1px solid var(--border-light);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        background: var(--white);
    }
    
    .course-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
        border-color: var(--accent-green);
    }
    
    .course-image {
        height: 200px;
        object-fit: cover;
    }
    
    /* Creative Course Code Styling */
    .course-code-text {
        font-family: 'Inter', sans-serif;
        font-size: 2.8rem;
        font-weight: 800;
        color: rgba(255, 255, 255, 0.9);
        text-align: center;
        mix-blend-mode: overlay;
        position: relative;
        z-index: 2;
    }
    
    /* Enhanced Welcome Section - Matching Announcements Design with Animations */
    .welcome-section {
        background: var(--main-green);
        border-radius: var(--border-radius-xl);
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-lg);
        opacity: 0;
        transform: translateY(-30px);
        animation: slideInDown 0.8s ease-out forwards;
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .welcome-section::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        opacity: 0.3;
    }

    .welcome-title {
        color: white;
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0;
        position: relative;
        z-index: 2;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        font-family: 'Inter', sans-serif;
    }

    .welcome-subtitle {
        color: white !important;
        font-size: 1.1rem;
        margin-bottom: 1rem;
        position: relative;
        z-index: 2;
    }

    .welcome-actions {
        position: relative;
        z-index: 2;
    }

    .quick-stats {
        display: flex;
        gap: 2rem;
        background: rgba(255,255,255,0.2);
        border: 2px solid rgba(255,255,255,0.3);
        backdrop-filter: blur(10px);
        border-radius: var(--border-radius);
        padding: 1rem 1.5rem;
        width: fit-content;
        position: relative;
        z-index: 2;
    }

    .stat-item {
        text-align: center;
        color: white;
    }

    .stat-number {
        display: block;
        font-size: 2rem;
        font-weight: 700;
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        font-family: 'Inter', sans-serif;
    }

    .stat-label {
        display: block;
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin-top: 0.25rem;
        font-weight: 500;
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

    .welcome-section .accent-line {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: #7DCB80;
        border-radius: 0 0 20px 20px;
    }

    /* Enhanced Course Cards - Clean Design with Animations */
    .course-card {
        background: var(--white);
        border: 1px solid var(--border-light);
        border-radius: var(--border-radius);
        padding: 0;
        margin-bottom: 1.5rem;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: var(--shadow-sm);
        height: 100%;
        overflow: hidden;
        position: relative;
        opacity: 0;
        transform: translateY(30px);
        animation: slideInUp 0.6s ease-out forwards;
    }

    .course-card:nth-child(1) { animation-delay: 0.1s; }
    .course-card:nth-child(2) { animation-delay: 0.2s; }
    .course-card:nth-child(3) { animation-delay: 0.3s; }
    .course-card:nth-child(4) { animation-delay: 0.4s; }
    .course-card:nth-child(5) { animation-delay: 0.5s; }
    .course-card:nth-child(6) { animation-delay: 0.6s; }

    .course-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        border-color: var(--accent-green);
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .course-card .card-header {
        background: var(--white);
        border-bottom: 1px solid var(--border-light);
        padding: 1.25rem;
        position: relative;
    }

    .course-card .card-title {
        color: var(--main-green);
        font-weight: 700;
        font-size: 1.3rem;
        margin-bottom: 0.5rem;
        font-family: 'Inter', sans-serif;
    }

    .course-card .card-text {
        color: var(--text-muted);
        margin-bottom: 1rem;
        font-family: 'Inter', sans-serif;
    }

    .course-card .card-body {
        padding: 1.25rem;
    }

    .course-stats {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .course-stat {
        text-align: center;
        color: #2E5E4E;
    }

    .course-stat .stat-number {
        display: block;
        font-size: 1.5rem;
        font-weight: 700;
        color: #7DCB80;
    }

    .course-stat .stat-label {
        font-size: 0.8rem;
        color: #666;
    }

    /* Enhanced Academic Year Selector - Matching Announcements Design with Animations */
    .academic-year-selector {
        background: var(--white);
        border: 2px solid var(--accent-green);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-sm);
        position: relative;
        opacity: 0;
        transform: translateX(-30px);
        animation: slideInLeft 0.6s ease-out 0.2s forwards;
    }

    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .academic-year-selector::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--main-green);
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .academic-year-selector .form-label {
        color: var(--main-green);
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-family: 'Inter', sans-serif;
    }

    .academic-year-selector .form-select {
        border: 2px solid var(--accent-green);
        border-radius: var(--border-radius);
        color: var(--text-dark);
        font-weight: 500;
        font-family: 'Inter', sans-serif;
    }

    .academic-year-selector .form-select:focus {
        border-color: var(--main-green);
        box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25);
    }

    /* Enhanced Create Course Button - Matching Announcements Design */
    .create-course-btn {
        background: var(--main-green);
        color: white;
        border: none;
        border-radius: var(--border-radius);
        padding: 0.75rem 2rem;
        font-weight: 600;
        font-size: 1rem;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
        font-family: 'Inter', sans-serif;
    }

    .create-course-btn:hover {
        background: var(--accent-green);
        color: var(--main-green);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .welcome-title {
            font-size: 2rem;
        }
        
        .quick-stats {
            flex-direction: column;
            gap: 1rem;
            width: 100%;
        }
        
        .course-stats {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .academic-year-selector {
            padding: 1rem;
        }
        
        .academic-year-selector .d-flex {
            flex-direction: column;
            gap: 1rem;
        }
        
        .academic-year-selector .d-flex > div {
            width: 100%;
        }
        
        .academic-year-selector .form-select {
            width: 100% !important;
        }
        
        .create-course-btn {
            width: 100%;
            margin-top: 1rem;
        }
        
        .course-card {
            margin-bottom: 1rem;
        }
        
        .welcome-section {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .floating-shapes {
            display: none;
        }
        
        .welcome-decoration {
            display: none;
        }
    }
    
    @media (max-width: 576px) {
        .welcome-title {
            font-size: 1.5rem;
        }
        
        .welcome-subtitle {
            font-size: 1rem;
        }
        
        .course-code-text {
            font-size: 2rem;
        }
        
        .course-image {
            height: 150px;
        }
        
        .stat-number {
            font-size: 1.5rem;
        }
        
        .quick-stats {
            padding: 0.75rem 1rem;
        }
        
        .welcome-section {
            padding: 1rem;
        }
        
        .academic-year-selector {
            padding: 0.75rem;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        .course-stats .col-3 {
            margin-bottom: 0.5rem;
        }
    }
    
    @media (max-width: 480px) {
        .container-fluid {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        
        .welcome-title {
            font-size: 1.25rem;
        }
        
        .course-code-text {
            font-size: 1.5rem;
        }
        
        .course-image {
            height: 120px;
        }
        
        .btn {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .modal-body {
            padding: 1rem;
        }
        
        .modal-header {
            padding: 1rem;
        }
        
        .modal-footer {
            padding: 1rem;
        }
        
        .form-control, .form-select {
            font-size: 16px; /* Prevent zoom on iOS */
        }
    }
    
    /* Additional mobile optimizations */
    @media (max-width: 576px) {
        .modal-dialog {
            max-width: calc(100vw - 1rem);
        }
        
        .btn {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .course-card .card-body {
            padding: 0.75rem;
        }
        
        .course-card .card-footer {
            padding: 0.75rem;
        }
    }
    
    /* Enhanced Modal Styling - Matching Announcements Design */
    .modal-content {
        border-radius: var(--border-radius);
        border: 2px solid var(--accent-green);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }
    
    .modal-header {
        background: var(--main-green);
        color: var(--white);
        border-bottom: none;
        padding: 1.5rem;
    }
    
    .modal-header .modal-title {
        font-weight: 700;
        font-family: 'Inter', sans-serif;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    /* Scrollable modal body styling */
    .modal-dialog-scrollable .modal-body {
        max-height: 70vh;
        overflow-y: auto;
        padding-right: 1rem;
    }
    
    /* Custom scrollbar styling for better UX */
    .modal-dialog-scrollable .modal-body::-webkit-scrollbar {
        width: 8px;
    }
    
    .modal-dialog-scrollable .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .modal-dialog-scrollable .modal-body::-webkit-scrollbar-thumb {
        background: var(--accent-green);
        border-radius: 4px;
    }
    
    .modal-dialog-scrollable .modal-body::-webkit-scrollbar-thumb:hover {
        background: var(--main-green);
    }
    
    /* Ensure list items have proper spacing in scrollable modal */
    .modal-dialog-scrollable .list-group-item {
        border-left: none;
        border-right: none;
        padding: 1rem;
    }
    
    .modal-dialog-scrollable .list-group-item:first-child {
        border-top: none;
    }
    
    .modal-dialog-scrollable .list-group-item:last-child {
        border-bottom: none;
    }
    
    .modal-footer {
        background: var(--off-white);
        border-top: 1px solid var(--border-light);
        padding: 1rem 1.5rem;
    }
    
    .form-control, .form-select {
        border: 2px solid var(--border-light);
        border-radius: var(--border-radius);
        font-family: 'Inter', sans-serif;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--accent-green);
        box-shadow: 0 0 0 0.2rem rgba(125, 203, 128, 0.25);
    }
    
    .form-label {
        font-weight: 600;
        color: var(--text-dark);
        font-family: 'Inter', sans-serif;
    }

    /* Statistics Cards Animation */
    .stats-card {
        opacity: 0;
        transform: translateY(20px);
        animation: fadeInUp 0.6s ease-out forwards;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stats-card:nth-child(1) { animation-delay: 0.1s; }
    .stats-card:nth-child(2) { animation-delay: 0.2s; }
    .stats-card:nth-child(3) { animation-delay: 0.3s; }
    .stats-card:nth-child(4) { animation-delay: 0.4s; }

    .stats-card:hover {
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Button Animations */
    .create-course-btn {
        position: relative;
        overflow: hidden;
    }

    .create-course-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }

    .create-course-btn:hover::before {
        left: 100%;
    }

    /* Course Image Animation */
    .course-image {
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .course-image:hover {
        transform: scale(1.05);
    }

    .course-image i {
        transition: all 0.3s ease;
    }

    .course-image:hover i {
        transform: scale(1.1);
        opacity: 0.8;
    }

    /* Badge Animations */
    .badge {
        transition: all 0.3s ease;
    }

    .badge:hover {
        transform: scale(1.1);
    }

    /* Loading Animation for Empty State */
    .empty-state-card {
        opacity: 0;
        transform: scale(0.9);
        animation: scaleIn 0.5s ease-out 0.3s forwards;
    }

    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Pulse Animation for Icons */
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    .welcome-decoration i {
        animation: pulse 2s infinite;
    }

    /* Floating Animation for Decorative Elements */
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }

    .floating-shapes {
        animation: float 3s ease-in-out infinite;
    }
</style>
<?php

// Assume teacher is logged in and their ID is in $_SESSION['user_id']
$teacher_id = $_SESSION['user_id'];

// 1. Fetch all academic periods for the dropdown (active first)
$ay_stmt = $db->prepare('SELECT id, academic_year, semester_name, is_active FROM academic_periods ORDER BY is_active DESC, academic_year DESC, semester_name');
$ay_stmt->execute();
$all_years = $ay_stmt->fetchAll();

// 2. Handle academic period selection (GET or SESSION)
if (isset($_GET['academic_period_id'])) {
    $_SESSION['teacher_courses_academic_period_id'] = (int)$_GET['academic_period_id'];
}
// Find the first active academic year
$active_year = null;
foreach ($all_years as $year) {
    if ($year['is_active']) {
        $active_year = $year['id'];
        break;
    }
}
$selected_year_id = $_SESSION['teacher_courses_academic_period_id'] ?? $active_year ?? ($all_years[0]['id'] ?? null);

// Check if selected academic period is active
$year_stmt = $db->prepare('SELECT is_active FROM academic_periods WHERE id = ?');
$year_stmt->execute([$selected_year_id]);
$year_row = $year_stmt->fetch();
$is_acad_year_active = $year_row ? (bool)$year_row['is_active'] : true;

// 3. Fetch courses for selected academic period with student count only
$course_sql = "SELECT c.*, ap.academic_year, ap.semester_name,
               COALESCE(student_counts.total_students, 0) as student_count
               FROM courses c 
               LEFT JOIN academic_periods ap ON c.academic_period_id = ap.id
               
               -- Student count subquery (from sections assigned to course)
               LEFT JOIN (
                   SELECT 
                       c.id as course_id,
                       COUNT(DISTINCT u.id) as total_students
                   FROM courses c
                   LEFT JOIN sections s ON JSON_SEARCH(c.sections, 'one', s.id) IS NOT NULL
                   LEFT JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL AND u.role = 'student'
                   WHERE c.teacher_id = ? AND c.academic_period_id = ?
                   GROUP BY c.id
               ) student_counts ON student_counts.course_id = c.id
               
               WHERE c.teacher_id = ? AND c.academic_period_id = ? AND c.is_archived = 0
               ORDER BY c.course_name";
$course_stmt = $db->prepare($course_sql);
$course_stmt->execute([$teacher_id, $selected_year_id, $teacher_id, $selected_year_id]);
$courses = $course_stmt->fetchAll();

// Calculate module, video, and assessment counts in PHP for better accuracy
foreach ($courses as &$course) {
    // Module count
    $modules = json_decode($course['modules'] ?? '[]', true);
    $course['module_count'] = is_array($modules) ? count($modules) : 0;
    
    // Video and assessment counts
    $total_videos = 0;
    $total_assessments = 0;
    
    
    if (is_array($modules)) {
        foreach ($modules as $module) {
            if (isset($module['videos']) && is_array($module['videos'])) {
                $total_videos += count($module['videos']);
            }
            if (isset($module['assessments']) && is_array($module['assessments'])) {
                $total_assessments += count($module['assessments']);
            }
        }
    }
    
    $course['video_count'] = $total_videos;
    $course['assessment_count'] = $total_assessments;
}

// After fetching courses, get teacher info for each course
foreach ($courses as &$course) {
    $stmt = $db->prepare('SELECT first_name, last_name, username FROM users WHERE id = ?');
    $stmt->execute([$course['teacher_id']]);
    $creator = $stmt->fetch();
    $course['creator_name'] = $creator ? $creator['first_name'] . ' ' . $creator['last_name'] : '';
    $course['creator_username'] = $creator['username'] ?? '';
}
unset($course);

// Fetch all sections for mapping (for both display and creation), but only active ones
$section_sql = "SELECT id, section_name, year_level FROM sections WHERE is_active = 1 ORDER BY year_level, section_name";
$section_res = $db->query($section_sql);
$sections_raw = $section_res ? $section_res->fetchAll() : [];
$sections = [];
foreach ($sections_raw as $section) {
    $sections[$section['id']] = formatSectionName($section);
}

// Fetch active academic periods for form
$ay_stmt = $db->prepare('SELECT id, academic_year, semester_name FROM academic_periods WHERE is_active = 1 ORDER BY academic_year DESC, semester_name');
$ay_stmt->execute();
$academic_years = $ay_stmt->fetchAll();

// Fetch all distinct year levels from sections for the assign year level UI
$year_stmt = $db->query('SELECT DISTINCT year_level FROM sections WHERE is_active = 1 ORDER BY year_level');
$year_levels = $year_stmt ? $year_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year_level']}{$section['section_name']}";
}

// Helper: get sections for a course (uses JSON sections field in courses table)
function get_course_sections($db, $course_id) {
    try {
        // First get the sections JSON from the course
        $stmt = $db->prepare("SELECT sections FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if (!$course || !$course['sections']) {
            return [];
        }
        
        // Parse the JSON sections array
        $section_ids = json_decode($course['sections'], true);
        if (!$section_ids || !is_array($section_ids)) {
            return [];
        }
        
        // If no sections, return empty array
        if (empty($section_ids)) {
            return [];
        }
        
        // Fetch section details for the IDs
        $placeholders = str_repeat('?,', count($section_ids) - 1) . '?';
        $sql = "SELECT id, section_name, year_level FROM sections 
                WHERE id IN ($placeholders) AND is_active = 1 
                ORDER BY year_level, section_name";
        $stmt = $db->prepare($sql);
        $stmt->execute($section_ids);
        $section_data = $stmt->fetchAll();
        
        $names = [];
        foreach ($section_data as $section) {
            $names[] = [
                'id' => $section['id'],
                'name' => formatSectionName($section)
            ];
        }
        return $names;
    } catch (Exception $e) {
        // Log error and return empty array to prevent page crash
        error_log("Error in get_course_sections: " . $e->getMessage());
        return [];
    }
}

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

// Helper: get students for a section
function get_section_students($db, $section_id) {
    try {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.is_irregular, u.identifier, u.created_at 
                FROM users u 
                WHERE u.role = 'student' 
                AND JSON_SEARCH((SELECT students FROM sections WHERE id = ?), 'one', u.id) IS NOT NULL
                ORDER BY u.last_name, u.first_name";
        $stmt = $db->prepare($sql);
        $stmt->execute([$section_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        // Log error and return empty array to prevent page crash
        error_log("Error in get_section_students: " . $e->getMessage());
        return [];
    }
}

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $course_name = trim($_POST['course_name']);
    $course_code = trim($_POST['course_code']);
    $description = trim($_POST['description']);
    $academic_period_id = isset($_POST['academic_period_id']) ? intval($_POST['academic_period_id']) : null;
    $selected_year_level = $_POST['year_level'] ?? null;
    
    // Validation messages
    $errors = [];
    
    if (!$course_name) {
        $errors[] = "Course name is required.";
    }
    if (!$course_code) {
        $errors[] = "Course code is required.";
    }
    if (!$academic_period_id) {
        $errors[] = "Academic period is required.";
    }
    if (!$selected_year_level) {
        $errors[] = "Year level is required.";
    }
    
    // Check for duplicate course code (globally unique)
    if ($course_code) {
        $check_code_stmt = $db->prepare('SELECT id FROM courses WHERE course_code = ?');
        $check_code_stmt->execute([$course_code]);
        if ($check_code_stmt->fetch()) {
            $errors[] = "Course code '{$course_code}' already exists. Please choose a different code.";
        }
    }
    
    // Check for duplicate course name within the same academic period
    if ($course_name && $academic_period_id) {
        $check_name_stmt = $db->prepare('SELECT id FROM courses WHERE course_name = ? AND academic_period_id = ?');
        $check_name_stmt->execute([$course_name, $academic_period_id]);
        if ($check_name_stmt->fetch()) {
            $errors[] = "Course name '{$course_name}' already exists for this academic period. Please choose a different name.";
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare('INSERT INTO courses (course_name, course_code, description, teacher_id, academic_period_id, year_level) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$course_name, $course_code, $description, $teacher_id, $academic_period_id, $selected_year_level]);
            $course_id = $db->lastInsertId();
            
            // Set success message and redirect with JavaScript
            $_SESSION['success_message'] = 'Course created successfully!';
            echo "<script>window.location.href='courses.php?academic_period_id=" . $academic_period_id . "';</script>";
            exit;
        } catch (PDOException $e) {
            // Handle any other database errors
            if ($e->getCode() == 23000) {
                $errors[] = "A course with this information already exists. Please check your input and try again.";
            } else {
                $errors[] = "Database error occurred. Please try again.";
                error_log("Course creation error: " . $e->getMessage());
            }
        }
    }
    
    // Display errors if any
    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><strong>Please fix the following errors:</strong><ul class="mb-0 mt-2">';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
    }
}
?>
<div class="container-fluid">
    <div class="row">
        <!-- Removed Sidebar -->
        <!-- Main content -->
        <main class="col-12 px-md-4">
            <!-- Enhanced Welcome Section -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="welcome-title">My Courses Management</h1>
                        <p class="welcome-subtitle">Create, manage, and monitor your courses and student progress</p>
                        
                        <!-- Back Button -->
                        <div class="welcome-actions mt-3">
                            <a href="dashboard.php" class="btn btn-outline-light btn-lg">
                                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex justify-content-end">
                        <!-- Quick Stats -->
                        <div class="quick-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo count($courses); ?></span>
                                <span class="stat-label">Total Courses</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo array_sum(array_column($courses, 'student_count')); ?></span>
                                <span class="stat-label">Total Students</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo array_sum(array_column($courses, 'module_count')); ?></span>
                                <span class="stat-label">Total Modules</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="welcome-decoration">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="floating-shapes"></div>
                <div class="accent-line"></div>
            </div>
        </div>
    </div>

    <!-- Enhanced Academic Year Selection -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="academic-year-selector">
                <form method="get" class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <div class="d-flex flex-column flex-md-row align-items-md-center mb-3 mb-md-0">
                        <i class="fas fa-calendar-alt me-2 mb-2 mb-md-0" style="color: #7DCB80; font-size: 1.2rem;"></i>
                        <label for="academic_period_id" class="form-label mb-0 me-3 mb-2 mb-md-0">Academic Period:</label>
                        <select name="academic_period_id" id="academic_period_id" class="form-select" style="min-width: 250px;" onchange="this.form.submit()">
                            <?php foreach ($all_years as $year): ?>
                                <option value="<?= $year['id'] ?>" <?= $selected_year_id == $year['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year['academic_year']) ?> - <?= htmlspecialchars($year['semester_name']) ?><?= !$year['is_active'] ? ' (Inactive)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn create-course-btn" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                        <i class="bi bi-plus-circle me-2"></i>Create Course
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (!$is_acad_year_active): ?>
        <div class="alert alert-warning mb-4">
            <strong>Inactive Academic Year:</strong> This academic year is inactive. You can only view and review content. All editing, adding, and uploading is disabled.
        </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <?php if (!empty($courses)): ?>
        <?php
        $total_students = array_sum(array_column($courses, 'student_count'));
        $total_modules = array_sum(array_column($courses, 'module_count'));
        $total_videos = array_sum(array_column($courses, 'video_count'));
        $total_assessments = array_sum(array_column($courses, 'assessment_count'));
        
        ?>
        <div class="row mb-4">
            <div class="col-6 col-md-3 mb-3">
                <div class="card h-100 stats-card" style="background: var(--main-green); color: white; border: 2px solid var(--accent-green); border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0" style="font-family: 'Inter', sans-serif; font-weight: 700;"><?php echo count($courses); ?></h4>
                                <p class="mb-0" style="font-family: 'Inter', sans-serif; font-weight: 500;">Total Courses</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-book fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="card h-100 stats-card" style="background: var(--accent-green); color: var(--main-green); border: 2px solid var(--main-green); border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0" style="font-family: 'Inter', sans-serif; font-weight: 700;"><?php echo $total_students; ?></h4>
                                <p class="mb-0" style="font-family: 'Inter', sans-serif; font-weight: 500;">Total Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="card h-100 stats-card" style="background: var(--highlight-yellow); color: var(--main-green); border: 2px solid var(--main-green); border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0" style="font-family: 'Inter', sans-serif; font-weight: 700;"><?php echo $total_modules; ?></h4>
                                <p class="mb-0" style="font-family: 'Inter', sans-serif; font-weight: 500;">Total Modules</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-collection fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="card h-100 stats-card" style="background: var(--white); color: var(--main-green); border: 2px solid var(--accent-green); border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0" style="font-family: 'Inter', sans-serif; font-weight: 700;"><?php echo $total_videos; ?></h4>
                                <p class="mb-0" style="font-family: 'Inter', sans-serif; font-weight: 500;">Total Videos</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-play-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Courses Grid -->
    <div class="row">
        <?php if (empty($courses)): ?>
            <div class="col-12">
                <div class="card empty-state-card" style="border: 1px solid var(--border-light); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); background: var(--white);">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-book fs-1 mb-3" style="color: var(--text-muted);"></i>
                        <h5 style="color: var(--text-dark); font-family: 'Inter', sans-serif; font-weight: 700;">No Courses Found</h5>
                        <p style="color: var(--text-muted); font-family: 'Inter', sans-serif;">No courses found for the selected academic year. Create your first course to get started.</p>
                        <button class="btn create-course-btn" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                            <i class="bi bi-plus-circle me-2"></i>Create Your First Course
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
                <?php
                    $theme = $course_themes[$course['id'] % count($course_themes)];
                ?>
                <div class="col-12 col-sm-6 col-lg-6 col-xl-4 mb-4">
                    <div class="card course-card h-100">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                <?php 
                                $year_level = $course['year_level'] ?? 'N/A';
                                $year_colors = [
                                    '1' => 'success',
                                    '1st Year' => 'success',
                                    '2' => 'info', 
                                    '2nd Year' => 'info',
                                    '3' => 'warning',
                                    '3rd Year' => 'warning',
                                    '4' => 'danger',
                                    '4th Year' => 'danger'
                                ];
                                $badge_color = $year_colors[$year_level] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badge_color ?> fs-6">
                                    <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($year_level) ?> Year
                                </span>
                            </div>
                            <div class="course-image d-flex align-items-center justify-content-center <?php echo $theme['bg']; ?>" style="height: 120px; position: relative; overflow: hidden; border-radius: var(--border-radius);">
                                <i class="<?php echo $theme['icon']; ?>" style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0.4; pointer-events: none; font-size: 6rem; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6);"></i>
                                <h2 class="course-code-text" style="font-size: 2rem;">
                                    <?php echo htmlspecialchars($course['course_code']); ?>
                                </h2>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="card-text text-muted">
                                <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?>
                            </p>
                            
                            <!-- Academic Year and Semester Info -->
                            <div class="mb-2">
                                <?php if (isset($course['academic_year'])): ?>
                                    <span class="badge" style="background: var(--highlight-yellow); color: var(--main-green); font-weight: 700;"><?php echo htmlspecialchars($course['academic_year']); ?></span>
                                <?php endif; ?>
                                <?php if (isset($course['semester_name'])): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($course['semester_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Sections as Subjects -->
                            <div class="mb-2">
                                <strong>Sections:</strong>
                                <?php
                                // Minimalist section badge grid with 'View' text button and smaller font
                                $course_sections = get_course_sections($db, $course['id']);
                                if ($course_sections):
                                    $max_sections = 9;
                                    $display_sections = array_slice($course_sections, 0, $max_sections);
                                ?>
                                    <div class="row g-2 mb-2">
                                        <?php foreach ($display_sections as $i => $sec):
                                            $students = get_section_students($db, $sec['id']);
                                            $student_count = count($students);
                                        ?>
                                            <div class="col-12 col-sm-6 col-md-4">
                                                <span class="d-flex align-items-center justify-content-between w-100 mb-1 px-2 py-1" style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 1.2em; font-size: 0.92rem; min-height: 2em;">
                                                    <span style="font-weight: 500; color: var(--main-green); letter-spacing: 0.5px;"><?= htmlspecialchars($sec['name']) ?></span>
                                                    <span class="badge bg-light text-dark ms-2" style="font-size:0.82em; border-radius:1em; min-width:1.6em; border:1px solid #e5e7eb; color:var(--main-green);"> <?= $student_count ?> </span>
                                                    <button type="button" class="btn btn-link p-0 ms-2" style="color:var(--main-green); border:none; background:none; font-size:0.95em; line-height:1; text-decoration:underline; font-weight:500;" title="View students" onclick="viewStudents(<?= $course['id'] ?>, <?= $sec['id'] ?>, '<?= htmlspecialchars($sec['name']) ?>', '<?= htmlspecialchars($course['course_name']) ?>')">
                                                        View
                                                    </button>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($course_sections) > $max_sections): ?>
                                        <div><small class="text-muted">Showing first 9 sections. <a href="#" onclick="event.preventDefault(); this.textContent='Feature coming soon!'; this.style.color='#6c757d';" style="cursor: pointer;">Show all</a></small></div>
                                    <?php endif; ?>
                                <?php
                                else:
                                    echo '<span class="text-muted">None</span>';
                                endif;
                                ?>
                            </div>
                            <!-- Course Statistics -->
                            <div class="row text-center mb-3">
                                <div class="col-3">
                                    <div class="border-end">
                                        <h6 class="mb-0 text-primary"><?php echo $course['student_count'] ?? 0; ?></h6>
                                        <small class="text-muted">Students</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border-end">
                                        <h6 class="mb-0 text-success"><?php echo $course['module_count'] ?? 0; ?></h6>
                                        <small class="text-muted">Modules</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border-end">
                                        <h6 class="mb-0 text-info"><?php echo $course['video_count'] ?? 0; ?></h6>
                                        <small class="text-muted">Videos</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <h6 class="mb-0 text-warning"><?php echo $course['assessment_count'] ?? 0; ?></h6>
                                    <small class="text-muted">Assessments</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <?php if (isset($course['created_at'])): ?>
                                    <small class="text-muted">Created <?php echo date('M j, Y', strtotime($course['created_at'])); ?></small>
                                <?php endif; ?>
                                <small class="text-muted">
                                    Created by: <?php echo htmlspecialchars($course['creator_name'] . ' (' . $course['creator_username'] . ')'); ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-footer" style="background: var(--white); border-top: 1px solid var(--border-light); padding: 1rem 1.25rem;">
                            <div class="d-grid gap-2">
                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn create-course-btn" style="background: var(--main-green); color: var(--white); font-weight: 600; border: none; border-radius: var(--border-radius);">
                                    <i class="bi bi-gear me-1"></i>Manage Course
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
        </main>
    </div>
</div>

<!-- Single Dynamic Students Modal -->
<div class="modal fade" id="studentsModal" tabindex="-1" aria-labelledby="studentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentsModalLabel">
                    <span id="modalTitle">Students</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="studentsModalContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading students...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Course Modal -->
<div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createCourseModalLabel">Create New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="course_name" class="form-label">Course Name</label>
                            <input type="text" class="form-control" id="course_name" name="course_name" required>
                            <div id="course_name_feedback" class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="course_code" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="course_code" name="course_code" required>
                            <div id="course_code_feedback" class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="academic_period_id" class="form-label">Academic Period</label>
                            <select class="form-select" id="academic_period_id" name="academic_period_id" required>
                                <option value="">Select Academic Year</option>
                                <?php foreach ($academic_years as $year): ?>
                                    <option value="<?= $year['id'] ?>" <?= $selected_year_id == $year['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['academic_year']) ?> - <?= htmlspecialchars($year['semester_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                    <div class="mb-3">
                        <label for="year_level" class="form-label">Year Level</label>
                        <select class="form-select" id="year_level" name="year_level" required>
                            <option value="">Select Year Level</option>
                            <?php foreach ($year_levels as $year): ?>
                                <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?> Year</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_course" class="btn" style="background: var(--main-green); color: var(--white); font-weight: 700; border: none;">Create Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>


// Function to load and display students for a specific section
function viewStudents(courseId, sectionId, sectionName, courseName) {
    const modalTitle = document.getElementById('modalTitle');
    const modalContent = document.getElementById('studentsModalContent');
    
    // Show loading state
    modalTitle.textContent = `Students in ${sectionName} (Course: ${courseName})`;
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading students...</p>
        </div>
    `;

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('studentsModal'));
    modal.show();

    // Fetch student data
    fetch(`get_section_students.php?course_id=${courseId}&section_id=${sectionId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(students => {
            if (students.length === 0) {
                modalContent.innerHTML = '<p class="text-center text-muted">No students assigned to this section.</p>';
            } else {
                const ul = document.createElement('ul');
                ul.classList.add('list-group');
                students.forEach(stu => {
                    const li = document.createElement('li');
                    li.classList.add('list-group-item');
                    li.innerHTML = `
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                            <div>
                                <strong>${stu.last_name}, ${stu.first_name}</strong> 
                                <span class="text-muted">(${stu.username})</span> 
                                ${stu.is_irregular ? '<span class="badge bg-danger ms-2">Irregular</span>' : '<span class="badge bg-success ms-2">Regular</span>'}
                            </div>
                            <div class="small text-muted">${stu.email}</div>
                        </div>
                        <div class="mt-1 small text-secondary">
                            Student ID: <span class="fw-semibold">${stu.identifier || '-'}</span> | 
                            Registered: <span>${stu.created_at ? new Date(stu.created_at).toLocaleDateString() : '-'}</span>
                        </div>
                    `;
                    ul.appendChild(li);
                });
                modalContent.innerHTML = '';
                modalContent.appendChild(ul);
            }
        })
        .catch(error => {
            modalContent.innerHTML = '<div class="alert alert-danger">Error loading students. Please try again.</div>';
            console.error('Error fetching students:', error);
        });
}

// Course validation functions
let courseCodeTimeout;
let courseNameTimeout;

// Validate course code uniqueness
function validateCourseCode() {
    const courseCode = document.getElementById('course_code').value.trim();
    const feedback = document.getElementById('course_code_feedback');
    const input = document.getElementById('course_code');
    
    if (courseCode.length === 0) {
        input.classList.remove('is-valid', 'is-invalid');
        feedback.textContent = '';
        return;
    }
    
    // Clear previous timeout
    clearTimeout(courseCodeTimeout);
    
    // Set new timeout for debouncing
    courseCodeTimeout = setTimeout(() => {
        fetch('check_course_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'course_code=' + encodeURIComponent(courseCode)
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                feedback.textContent = 'Course code already exists. Please choose a different code.';
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                feedback.textContent = 'Course code is available.';
            }
        })
        .catch(error => {
            console.error('Error checking course code:', error);
        });
    }, 500); // 500ms delay
}

// Validate course name uniqueness within academic period
function validateCourseName() {
    const courseName = document.getElementById('course_name').value.trim();
    const academicPeriodId = document.getElementById('academic_period_id').value;
    const feedback = document.getElementById('course_name_feedback');
    const input = document.getElementById('course_name');
    
    if (courseName.length === 0 || !academicPeriodId) {
        input.classList.remove('is-valid', 'is-invalid');
        feedback.textContent = '';
        return;
    }
    
    // Clear previous timeout
    clearTimeout(courseNameTimeout);
    
    // Set new timeout for debouncing
    courseNameTimeout = setTimeout(() => {
        fetch('check_course_name.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'course_name=' + encodeURIComponent(courseName) + '&academic_period_id=' + encodeURIComponent(academicPeriodId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                feedback.textContent = 'Course name already exists for this academic period. Please choose a different name.';
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                feedback.textContent = 'Course name is available.';
            }
        })
        .catch(error => {
            console.error('Error checking course name:', error);
        });
    }, 500); // 500ms delay
}

// Add event listeners when the page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing course form...');
    
    const courseCodeInput = document.getElementById('course_code');
    const courseNameInput = document.getElementById('course_name');
    const academicPeriodSelect = document.getElementById('academic_period_id');
    const createCourseBtn = document.querySelector('[data-bs-target="#createCourseModal"]');
    
    // Debug modal functionality
    if (createCourseBtn) {
        console.log('Create course button found:', createCourseBtn);
        createCourseBtn.addEventListener('click', function(e) {
            console.log('Create course button clicked');
            e.preventDefault();
            
            // Check if Bootstrap is available
            if (typeof bootstrap !== 'undefined') {
                console.log('Bootstrap is available, opening modal...');
                const modal = new bootstrap.Modal(document.getElementById('createCourseModal'));
                modal.show();
            } else {
                console.error('Bootstrap not available!');
                // Fallback: try to show modal using data attributes
                const modalElement = document.getElementById('createCourseModal');
                if (modalElement) {
                    modalElement.style.display = 'block';
                    modalElement.classList.add('show');
                    document.body.classList.add('modal-open');
                }
            }
        });
    } else {
        console.error('Create course button not found!');
    }
    
    if (courseCodeInput) {
        courseCodeInput.addEventListener('input', validateCourseCode);
    }
    
    if (courseNameInput) {
        courseNameInput.addEventListener('input', validateCourseName);
    }
    
    if (academicPeriodSelect) {
        academicPeriodSelect.addEventListener('change', validateCourseName);
    }
    
    // Initialize Bootstrap modals
    if (typeof bootstrap !== 'undefined') {
        console.log('Initializing Bootstrap modals...');
        const modalElements = document.querySelectorAll('.modal');
        modalElements.forEach(function(modalEl) {
            new bootstrap.Modal(modalEl);
        });
    }
    
    // Add scroll-triggered animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    }, observerOptions);
    
    // Observe all animated elements
    const animatedElements = document.querySelectorAll('.course-card, .stats-card, .welcome-section, .academic-year-selector');
    animatedElements.forEach(el => {
        observer.observe(el);
    });
    
    // Add ripple effect to buttons
    function createRipple(event) {
        const button = event.currentTarget;
        const circle = document.createElement('span');
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
        circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
        circle.classList.add('ripple');
        
        const ripple = button.getElementsByClassName('ripple')[0];
        if (ripple) {
            ripple.remove();
        }
        
        button.appendChild(circle);
    }
    
    // Add ripple effect to all buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', createRipple);
    });
    
    // Add CSS for ripple effect
    const style = document.createElement('style');
    style.textContent = `
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        .ripple {
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple 600ms linear;
            pointer-events: none;
        }
        
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php require_once '../includes/footer.php'; ?> 