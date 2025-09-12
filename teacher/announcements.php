<?php
$page_title = 'Teacher Announcements';
require_once '../includes/header.php';
require_once '../config/pusher.php';
require_once '../includes/pusher_notifications.php';
requireRole('teacher');

// Add custom CSS for enhanced scrolling and visual improvements
echo '<style>
    /* Import Google Fonts */
    @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");
    
    /* System Color Variables */
    :root {
        --main-green: #2E5E4E;      /* Deep, modern green */
        --accent-green: #7DCB80;    /* Light, fresh green */
        --highlight-yellow: #FFE066;/* Softer yellow for highlights */
        --off-white: #F7FAF7;       /* Clean, soft background */
        --white: #FFFFFF;
        --border-radius: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --shadow: 0 8px 32px rgba(46,94,78,0.10);
        --shadow-hover: 0 20px 40px rgba(46,94,78,0.15);
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
    
    /* Enhanced Header Section */
    .announcements-header {
        background: var(--main-green);
        border-radius: var(--border-radius);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-hover);
        position: relative;
        overflow: hidden;
    }
    
    .announcements-header::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.1\'%3E%3Ccircle cx=\'30\' cy=\'30\' r=\'2\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        opacity: 0.3;
    }
    
    .announcements-header h1 {
        color: white;
        font-weight: 700;
        font-size: 2.5rem;
        margin: 0;
        position: relative;
        z-index: 2;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .announcements-header .btn {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
        font-weight: 600;
        padding: 12px 24px;
        border-radius: 50px;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        position: relative;
        z-index: 2;
    }
    
    .announcements-header .btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }
    
    /* Enhanced Search and Filter Section */
    .search-filter-card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--shadow);
        border: 2px solid var(--accent-green);
        backdrop-filter: blur(10px);
        margin-bottom: 2rem;
    }
    
    .search-filter-card .form-label {
        font-weight: 600;
        color: var(--main-green);
        margin-bottom: 0.5rem;
    }
    
    .search-filter-card .form-control,
    .search-filter-card .form-select {
        border: 2px solid var(--accent-green);
        border-radius: var(--border-radius);
        padding: 12px 16px;
        font-size: 0.95rem;
        transition: var(--transition);
        background: var(--off-white);
    }
    
    .search-filter-card .form-control:focus,
    .search-filter-card .form-select:focus {
        border-color: var(--main-green);
        box-shadow: 0 0 0 3px rgba(46, 94, 78, 0.1);
        background: var(--white);
    }
    
    .search-filter-card .btn {
        border-radius: var(--border-radius);
        padding: 12px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .search-filter-card .btn-primary {
        background: var(--main-green);
        border: none;
        color: var(--white);
    }
    
    .search-filter-card .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
        background: var(--accent-green);
    }
    
    .search-filter-card .btn-secondary {
        background: var(--off-white);
        border: 2px solid var(--accent-green);
        color: var(--main-green);
    }
    
    .search-filter-card .btn-secondary:hover {
        background: var(--accent-green);
        color: var(--white);
        transform: translateY(-2px);
    }
    
    /* Enhanced Announcements Table */
    .announcements-table-container {
        max-height: 600px;
        overflow-y: auto;
        overflow-x: hidden;
        scroll-behavior: smooth;
        border-radius: var(--border-radius);
        border: 2px solid var(--accent-green);
        position: relative;
        background: var(--white);
        box-shadow: var(--shadow);
    }
    
    /* Custom scrollbar for announcements table */
    .announcements-table-container::-webkit-scrollbar {
        width: 8px;
    }
    
    .announcements-table-container::-webkit-scrollbar-track {
        background: var(--off-white);
        border-radius: var(--border-radius);
    }
    
    .announcements-table-container::-webkit-scrollbar-thumb {
        background: var(--main-green);
        border-radius: var(--border-radius);
        transition: var(--transition);
    }
    
    .announcements-table-container::-webkit-scrollbar-thumb:hover {
        background: var(--accent-green);
    }
    
    /* Firefox scrollbar styling */
    .announcements-table-container {
        scrollbar-width: thin;
        scrollbar-color: var(--main-green) var(--off-white);
    }
    
    /* Enhanced table styling */
    .announcements-table-container .table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: var(--border-radius);
        overflow: hidden;
    }
    
    .announcements-table-container .table thead th {
        position: sticky;
        top: 0;
        background: var(--accent-green);
        z-index: 10;
        border-bottom: 3px solid var(--main-green);
        font-weight: 700;
        color: var(--main-green);
        padding: 20px 16px;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .announcements-table-container .table thead th:first-child {
        border-top-left-radius: var(--border-radius);
    }
    
    .announcements-table-container .table thead th:last-child {
        border-top-right-radius: var(--border-radius);
    }
    
    .announcements-table-container .table tbody tr {
        transition: var(--transition);
        border-bottom: 1px solid var(--off-white);
    }
    
    .announcements-table-container .table tbody tr:hover {
        background: rgba(125, 203, 128, 0.1);
        transform: translateX(5px);
        box-shadow: var(--shadow);
    }
    
    .announcements-table-container .table tbody td {
        padding: 20px 16px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.95rem;
    }
    
    .announcements-table-container .table tbody tr:last-child td:first-child {
        border-bottom-left-radius: var(--border-radius);
    }
    
    .announcements-table-container .table tbody tr:last-child td:last-child {
        border-bottom-right-radius: var(--border-radius);
    }
    
    /* Enhanced Badge Styling */
    .announcements-table-container .badge {
        font-size: 0.8rem;
        padding: 8px 12px;
        border-radius: var(--border-radius);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
    }
    
    .announcements-table-container .badge.bg-primary {
        background: var(--main-green) !important;
        box-shadow: 0 2px 8px rgba(46, 94, 78, 0.3);
        color: var(--white) !important;
    }
    
    .announcements-table-container .badge.bg-secondary {
        background: var(--accent-green) !important;
        box-shadow: 0 2px 8px rgba(125, 203, 128, 0.3);
        color: var(--white) !important;
    }
    
    .announcements-table-container .badge:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(46, 94, 78, 0.4);
    }
    
    /* Enhanced Action Buttons */
    .announcements-table-container .btn-group .btn {
        padding: 10px 16px;
        font-size: 0.85rem;
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
        margin: 0 3px;
        font-weight: 600;
        border: 2px solid transparent;
    }
    
    .announcements-table-container .btn-group .btn-outline-primary {
        background: rgba(46, 94, 78, 0.1);
        border-color: var(--main-green);
        color: var(--main-green);
    }
    
    .announcements-table-container .btn-group .btn-outline-primary:hover {
        background: var(--main-green);
        border-color: var(--main-green);
        color: var(--white);
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
    }
    
    .announcements-table-container .btn-group .btn-outline-warning {
        background: rgba(255, 224, 102, 0.1);
        border-color: var(--highlight-yellow);
        color: #b45309;
    }
    
    .announcements-table-container .btn-group .btn-outline-warning:hover {
        background: var(--highlight-yellow);
        border-color: var(--highlight-yellow);
        color: var(--main-green);
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
    }
    
    .announcements-table-container .btn-group .btn-outline-danger {
        background: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
        color: #dc2626;
    }
    
    .announcements-table-container .btn-group .btn-outline-danger:hover {
        background: #ef4444;
        border-color: #ef4444;
        color: var(--white);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
    }
    
    /* Scroll indicators for announcements table */
    .announcements-scroll-indicator {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 15;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .announcements-scroll-indicator.show {
        opacity: 1;
    }
    
    .announcements-scroll-indicator-content {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .announcements-scroll-indicator i {
        background: rgba(111, 66, 193, 0.8);
        color: white;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        box-shadow: 0 2px 8px rgba(111, 66, 193, 0.3);
    }
    
    .announcements-scroll-indicator-top.hide,
    .announcements-scroll-indicator-bottom.hide {
        opacity: 0.3;
    }
    
    /* Enhanced Card Styling */
    .announcements-card {
        box-shadow: var(--shadow-hover);
        border: 2px solid var(--accent-green);
        border-radius: var(--border-radius);
        overflow: hidden;
        background: var(--white);
        backdrop-filter: blur(10px);
    }
    
    .announcements-card .card-header {
        background: var(--accent-green);
        border-bottom: 3px solid var(--main-green);
        padding: 24px 28px;
        position: relative;
    }
    
    .announcements-card .card-header::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--main-green);
    }
    
    .announcements-card .card-header h5 {
        margin: 0;
        font-weight: 700;
        color: var(--main-green);
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .announcements-card .card-header h5::before {
        content: "📢";
        font-size: 1.5rem;
    }
    
    /* Enhanced Modal Styling */
    .modal-content {
        border-radius: var(--border-radius);
        border: 2px solid var(--accent-green);
        box-shadow: var(--shadow-hover);
        overflow: hidden;
    }
    
    .modal-header {
        background: var(--main-green);
        color: var(--white);
        border-bottom: none;
        padding: 24px 28px;
    }
    
    .modal-header .modal-title {
        font-weight: 700;
        font-size: 1.4rem;
    }
    
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
    }
    
    .modal-header .btn-close:hover {
        opacity: 1;
    }
    
    .modal-body {
        padding: 28px;
        background: var(--off-white);
    }
    
    .modal-footer {
        background: var(--white);
        border-top: 2px solid var(--accent-green);
        padding: 20px 28px;
    }
    
    .modal .form-label {
        font-weight: 600;
        color: var(--main-green);
        margin-bottom: 8px;
    }
    
    .modal .form-control,
    .modal .form-select {
        border: 2px solid var(--accent-green);
        border-radius: var(--border-radius);
        padding: 12px 16px;
        font-size: 0.95rem;
        transition: var(--transition);
        background: var(--white);
    }
    
    .modal .form-control:focus,
    .modal .form-select:focus {
        border-color: var(--main-green);
        box-shadow: 0 0 0 3px rgba(46, 94, 78, 0.1);
    }
    
    .modal .btn {
        border-radius: var(--border-radius);
        padding: 12px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .modal .btn-primary {
        background: var(--main-green);
        border: none;
        color: var(--white);
    }
    
    .modal .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
        background: var(--accent-green);
    }
    
    .modal .btn-secondary {
        background: var(--off-white);
        border: 2px solid var(--accent-green);
        color: var(--main-green);
    }
    
    .modal .btn-secondary:hover {
        background: var(--accent-green);
        color: var(--white);
        transform: translateY(-2px);
    }
    
    .modal .btn-danger {
        background: #ef4444;
        border: none;
        color: var(--white);
    }
    
    .modal .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
    }
    
    /* Enhanced Mobile Responsiveness */
    @media (max-width: 991.98px) {
        .announcements-header {
            padding: 1.5rem;
        }
        
        .announcements-header h1 {
            font-size: 2rem;
        }
        
        .search-filter-card {
            padding: 1.5rem;
        }
        
        .announcements-table-container {
            max-height: 450px;
        }
        
        .announcements-table-container .table thead th,
        .announcements-table-container .table tbody td {
            padding: 12px 8px;
            font-size: 0.9rem;
        }
    }
    
    @media (max-width: 575.98px) {
        .announcements-header {
            padding: 1rem;
            border-radius: 16px;
        }
        
        .announcements-header h1 {
            font-size: 1.5rem;
        }
        
        .announcements-header .btn {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        .search-filter-card {
            padding: 1rem;
            border-radius: 16px;
        }
        
        .announcements-table-container {
            max-height: 350px;
        }
        
        .announcements-table-container .table thead th,
        .announcements-table-container .table tbody td {
            padding: 8px 4px;
            font-size: 0.85rem;
        }
        
        .announcements-table-container .btn-group .btn {
            padding: 6px 10px;
            font-size: 0.75rem;
            margin: 0 1px;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-header {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
        }
    }
    
    /* Enhanced Animations */
    .announcements-container {
        animation: fadeInUp 0.6s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .announcements-header {
        animation: slideInDown 0.8s ease-out;
    }
    
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .search-filter-card {
        animation: slideInUp 0.6s ease-out 0.2s both;
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
    
    .announcements-card {
        animation: slideInUp 0.6s ease-out 0.4s both;
    }
    
    /* Enhanced Focus States */
    .form-control:focus,
    .form-select:focus {
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15) !important;
    }
    
    /* Smooth Transitions for All Interactive Elements */
    * {
        transition: all 0.3s ease;
    }
    
    /* Enhanced Alert Styling - More Visible with Borders */
    .alert {
        border-radius: var(--border-radius);
        border: 3px solid;
        font-weight: 600;
        font-size: 0.9rem;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2), inset 0 0 0 1px rgba(255,255,255,0.2);
        margin: 0.5rem 0;
        padding: 0.8rem 1.2rem;
        position: relative;
        animation: slideInDown 0.5s ease-out;
        z-index: 1050;
        outline: 1px solid rgba(0,0,0,0.1);
        outline-offset: 1px;
    }
    
    .alert-success {
        background: var(--accent-green);
        color: var(--white);
        border-color: var(--main-green);
        box-shadow: 0 8px 32px rgba(46, 94, 78, 0.4), inset 0 0 0 2px rgba(255,255,255,0.3);
        outline-color: var(--main-green);
    }
    
    .alert-success::before {
        content: "✓";
        position: absolute;
        left: 0.8rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.2rem;
        font-weight: bold;
        color: var(--white);
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }
    
    .alert-success .alert-message {
        margin-left: 1.5rem;
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }
    
    .alert-danger {
        background: #ef4444;
        color: var(--white);
        border-color: #dc2626;
        box-shadow: 0 8px 32px rgba(239, 68, 68, 0.4), inset 0 0 0 2px rgba(255,255,255,0.3);
        outline-color: #dc2626;
    }
    
    .alert-danger::before {
        content: "⚠";
        position: absolute;
        left: 0.8rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.2rem;
        font-weight: bold;
        color: var(--white);
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }
    
    .alert-danger .alert-message {
        margin-left: 1.5rem;
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }
    
    /* Alert Animation */
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Hide Alert Close Button */
    .alert .btn-close {
        display: none !important;
    }
    
    /* Alert Container for Better Positioning */
    .alert-container {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        width: 70%;
        max-width: 400px;
        z-index: 1060;
    }
    
    /* Mobile Alert Styling */
    @media (max-width: 768px) {
        .alert {
            font-size: 0.85rem;
            padding: 0.7rem 1rem;
            margin: 0.3rem;
        }
        
        .alert-container {
            width: 85%;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .alert::before {
            font-size: 1rem;
        }
        
        .alert .alert-message {
            margin-left: 1.2rem;
        }
    }
    
    /* Loading and animation states */
    .announcements-table-loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    .announcement-row-enter {
        animation: announcementRowEnter 0.5s ease-out;
    }
    
    @keyframes announcementRowEnter {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .announcement-row-exit {
        animation: announcementRowExit 0.5s ease-in;
    }
    
    @keyframes announcementRowExit {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(-100%);
        }
    }
</style>';

$message = '';
$message_type = '';

// Handle announcement actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        switch ($action) {
            case 'create':
                $title = sanitizeInput($_POST['title'] ?? '');
                $content = sanitizeInput($_POST['content'] ?? '');
                $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
                
                if (empty($title) || empty($content)) {
                    $message = 'Title and content are required.';
                    $message_type = 'danger';
                } else {
                    // Verify the course belongs to this teacher
                    if ($course_id) {
                        $courseCheck = $db->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ? AND is_archived = 0');
                        $courseCheck->execute([$course_id, $_SESSION['user_id']]);
                        if (!$courseCheck->fetch()) {
                            $message = 'Invalid course selected.';
                            $message_type = 'danger';
                            break;
                        }
                    }
                    
                    $is_global = empty($course_id) ? 1 : 0;
                    $target_audience = $course_id ? json_encode(['courses' => [$course_id]]) : null;
                    
                    $stmt = $db->prepare('INSERT INTO announcements (title, content, author_id, is_global, target_audience) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$title, $content, $_SESSION['user_id'], $is_global, $target_audience]);
                    
                    // Send Pusher notification
                    $authorName = $_SESSION['name'] ?? 'Unknown User';
                    $courseName = null;
                    if ($course_id) {
                        $courseStmt = $db->prepare('SELECT course_name FROM courses WHERE id = ?');
                        $courseStmt->execute([$course_id]);
                        $courseName = $courseStmt->fetchColumn();
                    }
                    
                    $announcementData = [
                        'title' => $title,
                        'content' => $content,
                        'course_id' => $course_id,
                        'course_name' => $courseName,
                        'author_name' => $authorName
                    ];
                    
                    PusherNotifications::sendNewAnnouncement($announcementData);
                    
                    $message = 'Announcement created successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'update':
                $announcement_id = (int)($_POST['announcement_id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $content = sanitizeInput($_POST['content'] ?? '');
                $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
                
                if (empty($title) || empty($content)) {
                    $message = 'Title and content are required.';
                    $message_type = 'danger';
                } else {
                    // Verify the announcement belongs to this teacher
                    $announcementCheck = $db->prepare('SELECT id FROM announcements WHERE id = ? AND author_id = ?');
                    $announcementCheck->execute([$announcement_id, $_SESSION['user_id']]);
                    if (!$announcementCheck->fetch()) {
                        $message = 'You can only edit your own announcements.';
                        $message_type = 'danger';
                        break;
                    }
                    
                    // Verify the course belongs to this teacher if course_id is provided
                    if ($course_id) {
                        $courseCheck = $db->prepare('SELECT id FROM courses WHERE id = ? AND teacher_id = ? AND is_archived = 0');
                        $courseCheck->execute([$course_id, $_SESSION['user_id']]);
                        if (!$courseCheck->fetch()) {
                            $message = 'Invalid course selected.';
                            $message_type = 'danger';
                            break;
                        }
                    }
                    
                    $is_global = empty($course_id) ? 1 : 0;
                    $target_audience = $course_id ? json_encode(['courses' => [$course_id]]) : null;
                    
                    $stmt = $db->prepare('UPDATE announcements SET title = ?, content = ?, is_global = ?, target_audience = ? WHERE id = ? AND author_id = ?');
                    $stmt->execute([$title, $content, $is_global, $target_audience, $announcement_id, $_SESSION['user_id']]);
                    
                    $message = 'Announcement updated successfully.';
                    $message_type = 'success';
                }
                break;
                
            case 'delete':
                $announcement_id = (int)($_POST['announcement_id'] ?? 0);
                
                // Verify the announcement belongs to this teacher
                $announcementCheck = $db->prepare('SELECT id FROM announcements WHERE id = ? AND author_id = ?');
                $announcementCheck->execute([$announcement_id, $_SESSION['user_id']]);
                if (!$announcementCheck->fetch()) {
                    $message = 'You can only delete your own announcements.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare('DELETE FROM announcements WHERE id = ? AND author_id = ?');
                    $stmt->execute([$announcement_id, $_SESSION['user_id']]);
                    
                    $message = 'Announcement deleted successfully.';
                    $message_type = 'success';
                }
                break;
        }
    }
}

// Get filter parameters
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : '';
$search_query = sanitizeInput($_GET['search'] ?? '');

// Build WHERE clause for teacher's announcements
$where_clause = 'WHERE a.author_id = ?';
$params = [$_SESSION['user_id']];

if ($course_filter) {
    $where_clause .= ' AND JSON_SEARCH(a.target_audience, "one", ?) IS NOT NULL';
    $params[] = $course_filter;
}

if ($search_query) {
    $where_clause .= ' AND (a.title LIKE ? OR a.content LIKE ?)';
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
}

// Get teacher's announcements
$stmt = $db->prepare("
    SELECT a.*, 
           CASE 
               WHEN a.is_global = 1 THEN 'General Announcement'
               ELSE 'Course Specific'
           END as announcement_type,
           CASE 
               WHEN a.is_global = 1 THEN NULL
               WHEN a.target_audience IS NOT NULL THEN (
                   SELECT c.course_name 
                   FROM courses c 
                   WHERE c.id = JSON_UNQUOTE(JSON_EXTRACT(a.target_audience, '$.courses[0]'))
               )
               ELSE NULL
           END as course_name,
           CASE 
               WHEN a.is_global = 1 THEN NULL
               WHEN a.target_audience IS NOT NULL THEN (
                   SELECT c.course_code 
                   FROM courses c 
                   WHERE c.id = JSON_UNQUOTE(JSON_EXTRACT(a.target_audience, '$.courses[0]'))
               )
               ELSE NULL
           END as course_code
    FROM announcements a
    $where_clause
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Get teacher's courses for filter and form
$stmt = $db->prepare('SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? AND is_archived = 0 ORDER BY course_name');
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();
?>

<div class="container-fluid py-4">
    <!-- Enhanced Header Section -->
    <div class="row">
        <div class="col-12">
            <div class="announcements-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>My Announcements</h1>
                    <button class="btn" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="bi bi-plus-circle me-2"></i>Create Announcement
                </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert-container">
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <div class="alert-message">
            <?php echo $message; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Enhanced Search and Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="search-filter-card">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                        <label for="search" class="form-label">
                            <i class="bi bi-search me-2"></i>Search Announcements
                        </label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Search by title or content...">
                        </div>
                        <div class="col-md-4">
                        <label for="course_id" class="form-label">
                            <i class="bi bi-funnel me-2"></i>Filter by Course
                        </label>
                            <select class="form-select" id="course_id" name="course_id">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search me-1"></i>Search
                            </button>
                            <a href="announcements.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Clear
                            </a>
                        </div>
                    </form>
            </div>
        </div>
    </div>

    <!-- Announcements List -->
    <div class="row announcements-container" style="margin-bottom: 280px;">
        <div class="col-12">
            <div class="card announcements-card">
                <div class="card-header">
                    <h5 class="mb-0">Announcements (<?php echo count($announcements); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon mb-4">
                                <i class="bi bi-megaphone" style="font-size: 4rem; color: #cbd5e0;"></i>
                            </div>
                            <h4 class="text-muted mb-3">No announcements found</h4>
                            <p class="text-muted mb-4" style="font-size: 1.1rem;">
                                <?php if ($search_query || $course_filter): ?>
                                    Try adjusting your search criteria or 
                                    <a href="announcements.php" class="text-decoration-none" style="color: #667eea; font-weight: 600;">view all announcements</a>.
                                <?php else: ?>
                                    Create your first announcement to get started.
                                <?php endif; ?>
                            </p>
                            <?php if (!$search_query && !$course_filter): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                                    <i class="bi bi-plus-circle me-2"></i>Create Your First Announcement
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive announcements-table-container">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Course</th>
                                        <th>Content Preview</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($announcement['course_name']): ?>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($announcement['course_name'] . ' (' . $announcement['course_code'] . ')'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">General</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars(substr($announcement['content'], 0, 100)); ?>
                                                <?php if (strlen($announcement['content']) > 100): ?>...<?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo formatDate($announcement['created_at']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)"
                                                            title="View Announcement">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" 
                                                            onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)"
                                                            title="Edit Announcement">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')"
                                                            title="Delete Announcement">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
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

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="create_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="create_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_content" class="form-label">Content</label>
                        <textarea class="form-control" id="create_content" name="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="create_course_id" class="form-label">Course (Optional)</label>
                        <select class="form-select" id="create_course_id" name="course_id">
                            <option value="">General Announcement</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty for a general announcement visible to all users.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="announcement_id" id="edit_announcement_id">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_content" class="form-label">Content</label>
                        <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_course_id" class="form-label">Course (Optional)</label>
                        <select class="form-select" id="edit_course_id" name="course_id">
                            <option value="">General Announcement</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty for a general announcement visible to all users.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Announcement Modal -->
<div class="modal fade" id="viewAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="view_announcement_title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Course:</strong> 
                    <span id="view_announcement_course"></span>
                </div>
                <div class="mb-3">
                    <strong>Content:</strong>
                    <div id="view_announcement_content" class="mt-2 p-3 bg-light rounded"></div>
                </div>
                <div class="mb-3">
                    <strong>Created:</strong> 
                    <span id="view_announcement_created"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAnnouncementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the announcement "<span id="delete_announcement_title"></span>"?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="announcement_id" id="delete_announcement_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function viewAnnouncement(announcement) {
    document.getElementById('view_announcement_title').textContent = announcement.title;
    document.getElementById('view_announcement_course').textContent = announcement.course_name ? 
        announcement.course_name + ' (' + announcement.course_code + ')' : 'General Announcement';
    document.getElementById('view_announcement_content').textContent = announcement.content;
    document.getElementById('view_announcement_created').textContent = new Date(announcement.created_at).toLocaleString();
    
    new bootstrap.Modal(document.getElementById('viewAnnouncementModal')).show();
}

function editAnnouncement(announcement) {
    document.getElementById('edit_announcement_id').value = announcement.id;
    document.getElementById('edit_title').value = announcement.title;
    document.getElementById('edit_content').value = announcement.content;
    
    // Extract course ID from target_audience JSON if it exists
    let courseId = '';
    if (announcement.target_audience) {
        try {
            const targetAudience = JSON.parse(announcement.target_audience);
            if (targetAudience.courses && targetAudience.courses.length > 0) {
                courseId = targetAudience.courses[0];
            }
        } catch (e) {
            console.error('Error parsing target_audience:', e);
        }
    }
    document.getElementById('edit_course_id').value = courseId;
    
    new bootstrap.Modal(document.getElementById('editAnnouncementModal')).show();
 }

function deleteAnnouncement(id, title) {
    document.getElementById('delete_announcement_id').value = id;
    document.getElementById('delete_announcement_title').textContent = title;
    
    new bootstrap.Modal(document.getElementById('deleteAnnouncementModal')).show();
}

// Auto-resize textareas
document.addEventListener('DOMContentLoaded', function() {
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });
    
    // Enhanced scrolling behavior for announcements table
    function enhanceAnnouncementsTableScrolling() {
        const tableContainer = document.querySelector('.announcements-table-container');
        
        if (tableContainer) {
            // Add smooth scrolling behavior
            tableContainer.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const cardContainer = tableContainer.closest('.card');
            if (cardContainer) {
                addAnnouncementsTableScrollIndicators(tableContainer, cardContainer);
            }
        }
    }
    
    // Add scroll indicators to announcements table
    function addAnnouncementsTableScrollIndicators(scrollContainer, cardContainer) {
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = 'announcements-scroll-indicator';
        scrollIndicator.innerHTML = `
            <div class="announcements-scroll-indicator-content">
                <i class="bi bi-chevron-up announcements-scroll-indicator-top"></i>
                <i class="bi bi-chevron-down announcements-scroll-indicator-bottom"></i>
            </div>
        `;
        
        cardContainer.style.position = 'relative';
        cardContainer.appendChild(scrollIndicator);
        
        // Update scroll indicators based on scroll position
        function updateAnnouncementsScrollIndicators() {
            const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
            const isAtTop = scrollContainer.scrollTop === 0;
            const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
            
            if (isScrollable) {
                scrollIndicator.classList.add('show');
                scrollIndicator.querySelector('.announcements-scroll-indicator-top').classList.toggle('hide', isAtTop);
                scrollIndicator.querySelector('.announcements-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
            } else {
                scrollIndicator.classList.remove('show');
            }
        }
        
        // Initial check
        updateAnnouncementsScrollIndicators();
        
        // Update on scroll
        scrollContainer.addEventListener('scroll', updateAnnouncementsScrollIndicators);
        
        // Update on resize
        window.addEventListener('resize', updateAnnouncementsScrollIndicators);
    }
    
    // Initialize enhanced announcements table scrolling
    enhanceAnnouncementsTableScrolling();
    
    // Enhanced Alert Visibility
    function enhanceAlertVisibility() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            // Add pulsing effect to make it more noticeable
            alert.style.animation = 'slideInDown 0.5s ease-out, pulse 2s infinite 1s';
            
                // Auto-dismiss after 3 seconds for success messages
                if (alert.classList.contains('alert-success')) {
                    setTimeout(() => {
                        if (alert && alert.parentNode) {
                            alert.style.animation = 'slideOutUp 0.5s ease-in forwards';
                            setTimeout(() => {
                                if (alert && alert.parentNode) {
                                    alert.remove();
                                }
                            }, 500);
                        }
                    }, 3000);
                }

                // Auto-dismiss after 3 seconds for error messages
                if (alert.classList.contains('alert-danger')) {
                    setTimeout(() => {
                        if (alert && alert.parentNode) {
                            alert.style.animation = 'slideOutUp 0.5s ease-in forwards';
                            setTimeout(() => {
                                if (alert && alert.parentNode) {
                                    alert.remove();
                                }
                            }, 500);
                        }
                    }, 3000);
                }
        });
    }
    
    // Add pulse animation for alerts
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        @keyframes slideOutUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-50px);
            }
        }
    `;
    document.head.appendChild(style);
    
    // Initialize alert enhancements
    enhanceAlertVisibility();
});
</script>
