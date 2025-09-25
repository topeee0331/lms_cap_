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
    z-index: 3;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    font-family: 'Inter', sans-serif;
}

.welcome-subtitle {
    color: white !important;
    font-size: 1.1rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 3;
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

/* Minimal floating icon */
.minimal-floating-icon {
    position: absolute;
    top: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    animation: float 3s ease-in-out infinite;
    backdrop-filter: blur(10px);
}

.minimal-floating-icon i {
    font-size: 1.2rem;
    color: rgba(255,255,255,0.9);
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}

/* Additional floating elements */
.floating-element {
    position: absolute;
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 0;
    backdrop-filter: blur(5px);
    opacity: 0.5;
    margin: 5px;
}

.floating-element i {
    font-size: 1rem;
    color: rgba(255,255,255,0.8);
}

/* Individual floating element positions and animations */
.floating-element-1 {
    top: 10%;
    left: 8%;
    animation: floatSlow 4s ease-in-out infinite;
}

.floating-element-2 {
    top: 20%;
    right: 12%;
    animation: floatReverse 3.5s ease-in-out infinite;
}

.floating-element-3 {
    top: 65%;
    left: 3%;
    animation: floatUp 5s ease-in-out infinite;
}

.floating-element-4 {
    top: 75%;
    right: 15%;
    animation: floatDown 4.5s ease-in-out infinite;
}

.floating-element-5 {
    top: 35%;
    left: 15%;
    animation: floatSide 6s ease-in-out infinite;
}

.floating-element-6 {
    top: 45%;
    right: 3%;
    animation: floatRotate 5.5s ease-in-out infinite;
}

/* Center floating elements */
.floating-element-7 {
    top: 30%;
    left: 50%;
    transform: translateX(-50%);
    animation: floatCenter 4s ease-in-out infinite;
}

.floating-element-8 {
    top: 40%;
    left: 40%;
    animation: floatPulse 3.8s ease-in-out infinite;
}

.floating-element-9 {
    top: 50%;
    left: 60%;
    animation: floatBounce 4.2s ease-in-out infinite;
}

.floating-element-10 {
    top: 60%;
    left: 50%;
    transform: translateX(-50%);
    animation: floatWave 5.8s ease-in-out infinite;
}

/* Different animation keyframes */
@keyframes floatSlow {
    0%, 100% {
        transform: translateY(0px) translateX(0px);
    }
    50% {
        transform: translateY(-15px) translateX(10px);
    }
}

@keyframes floatReverse {
    0%, 100% {
        transform: translateY(0px) translateX(0px);
    }
    50% {
        transform: translateY(15px) translateX(-10px);
    }
}

@keyframes floatUp {
    0%, 100% {
        transform: translateY(0px) scale(1);
    }
    50% {
        transform: translateY(-20px) scale(1.1);
    }
}

@keyframes floatDown {
    0%, 100% {
        transform: translateY(0px) scale(1);
    }
    50% {
        transform: translateY(20px) scale(0.9);
    }
}

@keyframes floatSide {
    0%, 100% {
        transform: translateX(0px) rotate(0deg);
    }
    50% {
        transform: translateX(15px) rotate(180deg);
    }
}

@keyframes floatRotate {
    0%, 100% {
        transform: rotate(0deg) translateY(0px);
    }
    50% {
        transform: rotate(180deg) translateY(-10px);
    }
}

/* Center element animations */
@keyframes floatCenter {
    0%, 100% {
        transform: translateX(-50%) translateY(0px) scale(1);
    }
    50% {
        transform: translateX(-50%) translateY(-15px) scale(1.1);
    }
}

@keyframes floatPulse {
    0%, 100% {
        transform: scale(1) translateY(0px);
        opacity: 0.5;
    }
    50% {
        transform: scale(1.2) translateY(-8px);
        opacity: 0.8;
    }
}

@keyframes floatBounce {
    0%, 100% {
        transform: translateY(0px) rotate(0deg);
    }
    25% {
        transform: translateY(-12px) rotate(90deg);
    }
    50% {
        transform: translateY(-20px) rotate(180deg);
    }
    75% {
        transform: translateY(-12px) rotate(270deg);
    }
}

@keyframes floatWave {
    0%, 100% {
        transform: translateX(-50%) translateY(0px) rotate(0deg);
    }
    25% {
        transform: translateX(-45%) translateY(-10px) rotate(90deg);
    }
    50% {
        transform: translateX(-55%) translateY(-15px) rotate(180deg);
    }
    75% {
        transform: translateX(-45%) translateY(-10px) rotate(270deg);
    }
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
    width: 45px;
    height: 45px;
    transition: all 0.3s ease;
}

.stats-card:hover .stats-icon {
    transform: scale(1.1);
}

.stats-primary {
    background: white;
    border-left: 4px solid #0d6efd;
    color: #0d6efd;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-success {
    background: white;
    border-left: 4px solid #198754;
    color: #198754;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-info {
    background: white;
    border-left: 4px solid #0dcaf0;
    color: #0dcaf0;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-warning {
    background: white;
    border-left: 4px solid #ffc107;
    color: #ffc107;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-secondary {
    background: white;
    border-left: 4px solid #6c757d;
    color: #6c757d;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-danger {
    background: white;
    border-left: 4px solid #dc3545;
    color: #dc3545;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-danger-alt {
    background: white;
    border-left: 4px solid #e91e63;
    color: #e91e63;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-purple {
    background: white;
    border-left: 4px solid #9c27b0;
    color: #9c27b0;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
    position: relative;
    z-index: 3;
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
    
    if (isset($_POST['add_students'])) {
        $section_id = intval($_POST['add_students_section_id']);
        $students_to_add = $_POST['students_to_add'] ?? [];
        
        if (!empty($students_to_add)) {
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
            
            echo "<script>alert('Students added successfully!'); window.location.href='sections.php';</script>";
        } else {
            echo "<script>alert('No students selected.'); window.location.href='sections.php';</script>";
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
    width: 45px;
    height: 45px;
    transition: all 0.3s ease;
}

.stats-card:hover .stats-icon {
    transform: scale(1.1);
}

.stats-primary {
    background: white;
    border-left: 4px solid #0d6efd;
    color: #0d6efd;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-success {
    background: white;
    border-left: 4px solid #198754;
    color: #198754;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-info {
    background: white;
    border-left: 4px solid #0dcaf0;
    color: #0dcaf0;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-warning {
    background: white;
    border-left: 4px solid #ffc107;
    color: #ffc107;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-secondary {
    background: white;
    border-left: 4px solid #6c757d;
    color: #6c757d;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-danger {
    background: white;
    border-left: 4px solid #dc3545;
    color: #dc3545;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-danger-alt {
    background: white;
    border-left: 4px solid #e91e63;
    color: #e91e63;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-purple {
    background: white;
    border-left: 4px solid #9c27b0;
    color: #9c27b0;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
                    <!-- Minimal floating icon -->
                    <div class="minimal-floating-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <!-- Additional floating elements -->
                    <div class="floating-element floating-element-1">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="floating-element floating-element-2">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="floating-element floating-element-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="floating-element floating-element-4">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="floating-element floating-element-5">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="floating-element floating-element-6">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <!-- Center floating elements -->
                    <div class="floating-element floating-element-7">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="floating-element floating-element-8">
                        <i class="fas fa-puzzle-piece"></i>
                    </div>
                    <div class="floating-element floating-element-9">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="floating-element floating-element-10">
                        <i class="fas fa-brain"></i>
                    </div>
                </div>
            </div>
            <div class="accent-line"></div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-primary border-0 shadow-sm h-100">
                <div class="card-body text-center p-2">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-collection-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-primary"><?= $stats['total_sections'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Total Sections</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-success border-0 shadow-sm h-100">
                <div class="card-body text-center p-2">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-success"><?= $stats['active_sections'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Active Sections</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-info border-0 shadow-sm h-100">
                <div class="card-body text-center p-2">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-calendar-check-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-info"><?= $stats['current_period_sections'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Current Period</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-warning border-0 shadow-sm h-100">
                <div class="card-body text-center p-2">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-warning"><?= $stats['total_students_assigned'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Students Assigned</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-secondary border-0 shadow-sm h-100">
                <div class="card-body text-center p-2">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-mortarboard-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-secondary"><?= $stats['year_levels_covered'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Year Levels</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-danger border-0 shadow-sm h-100">
                <div class="card-body text-center p-2">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-danger text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-book-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-danger"><?= $stats['total_courses'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Total Courses</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-danger-alt border-0 shadow-sm h-100">
                <div class="card-body text-center p-2">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-danger text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-x-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-danger"><?= $stats['inactive_sections'] ?></h3>
                    <p class="text-muted mb-0 small fw-medium">Inactive Sections</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
            <div class="card stats-card stats-purple border-0 shadow-sm h-100">
                <div class="card-body text-center p-2">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="stats-icon bg-purple text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-purple"><?= $stats['total_teachers_assigned'] ?></h3>
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
                                        <th class="border-0">
                                            <i class="bi bi-person-workspace me-2"></i>Teachers
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
                                                <div class="d-flex justify-content-center">
                                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm">
                                                        <i class="bi bi-person-workspace-fill me-1"></i><?= $teacher_count ?> teachers
                                                </span>
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
                                                        echo '<span class="badge ' . $status_class . ' px-2 py-1 rounded-pill shadow-sm small">';
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
                                                                    foreach ($students as $stu) {
                                                                                $checked = in_array($stu['id'], $assigned_students) ? 'checked' : '';
                                                                        $status = ($stu['is_irregular'] ? 'irregular' : 'regular');
                                                                        $badge = $stu['is_irregular'] ? '<span class=\'badge bg-danger ms-2\'>Irregular</span>' : '<span class=\'badge bg-success ms-2\'>Regular</span>';
                                                                                echo "<div class='form-check student-option-{$status}' data-status='{$status}' style='margin-bottom: 4px;'>";
                                                                                echo "<input class='form-check-input' type='checkbox' name='students[]' value='{$stu['id']}' id='stu{$section['id']}_{$stu['id']}' $checked onchange='updateSelectedStudentsCount(this.closest(\".modal\"))'>";
                                                                                echo "<label class='form-check-label' for='stu{$section['id']}_{$stu['id']}'>" . htmlspecialchars($stu['name']) . " $badge</label>";
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
                        <div class="col-md-6">
                            <label for="studentSearchAdd" class="form-label fw-semibold">
                                <i class="bi bi-search me-2"></i>Search Students
                            </label>
                            <input type="text" class="form-control" id="studentSearchAdd" placeholder="Type student name..." onkeyup="searchStudentsAdd()">
                        </div>
                        <div class="col-md-6">
                            <label for="studentStatusFilterAdd" class="form-label fw-semibold">
                                <i class="bi bi-funnel me-2"></i>Filter by Status
                            </label>
                            <select class="form-select" id="studentStatusFilterAdd" onchange="filterStudentsAdd()">
                                <option value="all">All Students</option>
                                <option value="regular">Regular Students</option>
                                <option value="irregular">Irregular Students</option>
                            </select>
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
    }
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function loadStudentsForSection(sectionId, modal) {
    const studentsList = modal.querySelector('#studentsListAdd');
    studentsList.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-arrow-clockwise fs-1"></i><p>Loading students...</p></div>';
    
    // Fetch students via AJAX
    fetch('../ajax_get_available_students.php?section_id=' + sectionId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayStudentsList(data.students, studentsList, data.invalid_students, data.target_section);
            } else {
                studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error: ' + (data.message || 'Unknown error') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error fetching students:', error);
            studentsList.innerHTML = '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle fs-1"></i><p>Error loading students: ' + error.message + '</p></div>';
        });
}

function displayStudentsList(students, container, invalidStudents = [], targetSection = null) {
    let html = '';
    
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
        
        // Invalid students section
        if (invalidStudents.length > 0) {
            html += `
                <div class="mb-3">
                    <h6 class="text-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>Students Not Eligible (${invalidStudents.length})
                    </h6>
                    <div class="alert alert-warning mb-0">
                        <small>These students cannot be assigned to this section due to year level restrictions:</small>
                        <div class="mt-2" style="max-height: 200px; overflow-y: auto;">
            `;
            
            invalidStudents.forEach(student => {
                const yearBadge = `<span class="badge bg-secondary ms-1">${student.year_level_text}</span>`;
                html += `
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span>${student.name} ${yearBadge}</span>
                        <small class="text-muted">${student.validation_error}</small>
                    </div>
                `;
            });
            
            html += '</div></div></div>';
        }
    }
    
    container.innerHTML = html;
    updateSelectedStudentsCountAdd();
}

function searchStudentsAdd() {
    const input = document.getElementById('studentSearchAdd');
    const filter = input.value.toLowerCase();
    const modal = input.closest('.modal');
    const options = modal.querySelectorAll('.student-option-regular, .student-option-irregular');
    
    options.forEach(opt => {
        const label = opt.querySelector('label');
        const text = label ? label.textContent.toLowerCase() : '';
        opt.style.display = text.includes(filter) ? '' : 'none';
    });
    
    updateSelectedStudentsCountAdd();
}

function filterStudentsAdd() {
    const filter = document.getElementById('studentStatusFilterAdd').value;
    const modal = document.getElementById('studentStatusFilterAdd').closest('.modal');
    const options = modal.querySelectorAll('.student-option-regular, .student-option-irregular');
    
    options.forEach(opt => {
        if (filter === 'all' || opt.getAttribute('data-status') === filter) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    
    updateSelectedStudentsCountAdd();
}

function updateSelectedStudentsCountAdd() {
    const modal = document.querySelector('.modal.show');
    if (!modal) return;
    
    const count = modal.querySelectorAll('input[name="students_to_add[]"]:checked').length;
    const badge = modal.querySelector('#selectedStudentsCountAdd');
    if (badge) badge.textContent = count;
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
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-people me-2"></i>Students in ${sectionName}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="studentsList${sectionId}">
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-arrow-clockwise fs-1"></i>
                                <p>Loading students...</p>
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
        let debugHtml = '';
        if (debugInfo && debugInfo.total_in_json > 0) {
            debugHtml = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Data Mismatch Detected!</strong><br>
                    JSON shows ${debugInfo.total_in_json} students, but only ${debugInfo.found_students} were found.<br>
                    Missing Student IDs: ${debugInfo.missing_student_ids.join(', ')}
                </div>`;
        }
        
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-people fs-1"></i>
                <p class="mt-2">No students assigned to this section</p>
                <small class="text-muted">Use the "Add Students" button to assign students to this section</small>
            </div>
            ${debugHtml}`;
        return;
    }
    
    let debugHtml = '';
    if (debugInfo && debugInfo.total_in_json !== debugInfo.found_students) {
        debugHtml = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Data Mismatch Detected!</strong><br>
                JSON shows ${debugInfo.total_in_json} students, but only ${debugInfo.found_students} were found.<br>
                Missing Student IDs: ${debugInfo.missing_student_ids.join(', ')}
            </div>`;
    }
    
    let html = `
        ${debugHtml}
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
                        
                        ${!isCorrect ? `
                            <div class="alert alert-warning alert-sm">
                                <i class="bi bi-info-circle me-1"></i>
                                <small>This answer was marked incorrect. Review the question and correct answer above.</small>
                            </div>
                        ` : ''}
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