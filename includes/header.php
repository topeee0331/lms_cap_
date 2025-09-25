<?php
require_once dirname(__DIR__) . '/config/config.php';

// Ensure current_page is set for active nav highlighting
if (!isset($current_page)) {
    $current_page = basename($_SERVER['SCRIPT_NAME']);
}

// Ensure database connection is available as $pdo
if (!isset($db) || !$db) {
    require_once __DIR__ . '/../config/database.php';
}
if (!isset($pdo) && isset($db)) {
    $pdo = $db;
}

// Only output HTML if this is a page that should display content
if (!defined('NO_HTML_OUTPUT')) {
    if (!isset($db)) {
        require_once __DIR__ . '/../config/database.php';
        $db = (new Database())->getConnection();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Pusher -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <!-- Tutorial System (Modal-based) -->
    
    <style>
        :root {
            --main-green: #2E5E4E;
            --accent-green: #7DCB80;
            --highlight-yellow: #FFE066;
            --off-white: #F7FAF7;
            --white: #FFFFFF;
            --navbar-height: 60px;
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --navbar-shadow: 0 6px 24px rgba(46,94,78,0.08), 0 1.5px 4px rgba(0,0,0,0.04);
            --navbar-accent: linear-gradient(90deg, var(--main-green) 0%, var(--accent-green) 100%);
        }
        
        /* Modern Navbar Styling */
        .navbar {
            background: rgba(255, 255, 255, 0.97) !important;
            backdrop-filter: blur(20px);
            border-bottom: none;
            box-shadow: var(--navbar-shadow);
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            height: var(--navbar-height);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            transition: var(--transition);
        }
        
        .navbar-accent-bar {
            height: 4px;
            width: 100%;
            background: var(--navbar-accent);
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 8px rgba(46,94,78,0.08);
            margin-bottom: 0.5rem;
        }
        
        /* Tutorial System Styles */
        .introjs-tooltip {
            background: var(--white) !important;
            border: 2px solid var(--main-green) !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(46,94,78,0.15) !important;
        }
        
        .introjs-tooltip .introjs-tooltip-header {
            background: var(--main-green) !important;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .introjs-tooltip .introjs-tooltip-title {
            color: var(--white) !important;
            font-weight: 700 !important;
        }
        
        .introjs-tooltip .introjs-tooltip-content {
            color: var(--main-green) !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
        }
        
        .introjs-tooltip .introjs-tooltip-buttons {
            border-top: 1px solid #e9ecef !important;
            padding: 12px !important;
        }
        
        .introjs-tooltip .introjs-button {
            background: var(--main-green) !important;
            border: none !important;
            color: var(--white) !important;
            border-radius: 6px !important;
            font-weight: 600 !important;
            padding: 8px 16px !important;
            margin: 0 4px !important;
            transition: all 0.2s ease !important;
        }
        
        .introjs-tooltip .introjs-button:hover {
            background: var(--accent-green) !important;
            color: var(--main-green) !important;
            transform: translateY(-1px) !important;
        }
        
        .introjs-tooltip .introjs-button.introjs-skipbutton {
            background: #6c757d !important;
            color: var(--white) !important;
        }
        
        .introjs-tooltip .introjs-button.introjs-skipbutton:hover {
            background: #5a6268 !important;
        }
        
        .introjs-tooltip .introjs-arrow {
            border-color: var(--main-green) !important;
        }
        
        .introjs-tooltip .introjs-arrow.top {
            border-bottom-color: var(--main-green) !important;
        }
        
        .introjs-tooltip .introjs-arrow.bottom {
            border-top-color: var(--main-green) !important;
        }
        
        .introjs-tooltip .introjs-arrow.left {
            border-right-color: var(--main-green) !important;
        }
        
        .introjs-tooltip .introjs-arrow.right {
            border-left-color: var(--main-green) !important;
        }
        
        .introjs-overlay {
            background: rgba(46, 94, 78, 0.1) !important;
        }
        
        .tutorial-trigger {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050 !important;
            background: var(--main-green);
            color: var(--white);
            border: none;
            border-radius: 50px;
            padding: 12px 20px;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(46,94,78,0.2);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .tutorial-trigger:hover {
            background: var(--accent-green);
            color: var(--main-green);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46,94,78,0.3);
        }
        
        .tutorial-trigger i {
            margin-right: 8px;
        }
        
        /* Tutorial Modal Styles */
        .tutorial-modal .modal-content {
            border: 2px solid var(--main-green);
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(46,94,78,0.2);
        }
        
        .tutorial-modal .modal-header {
            background: var(--main-green);
            color: var(--white);
            border-radius: 13px 13px 0 0;
            border-bottom: none;
        }
        
        .tutorial-modal .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .tutorial-modal .modal-body {
            padding: 2rem;
            background: var(--off-white);
        }
        
        .tutorial-modal .modal-footer {
            background: var(--off-white);
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 13px 13px;
        }
        
        .tutorial-modal .btn-primary {
            background: var(--main-green);
            border: none;
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 8px;
        }
        
        .tutorial-modal .btn-primary:hover {
            background: var(--accent-green);
            color: var(--main-green);
        }
        
        .tutorial-modal .btn-secondary {
            background: #6c757d;
            border: none;
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 8px;
        }
        
        .tutorial-modal .btn-secondary:hover {
            background: #5a6268;
        }
        
        .tutorial-feature {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--white);
            border-radius: 10px;
            border-left: 4px solid var(--accent-green);
            box-shadow: 0 2px 8px rgba(46,94,78,0.1);
        }
        
        .tutorial-feature i {
            color: var(--main-green);
            font-size: 1.5rem;
            margin-right: 1rem;
            margin-top: 0.2rem;
        }
        
        .tutorial-feature-content h6 {
            color: var(--main-green);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .tutorial-feature-content p {
            color: #6c757d;
            margin-bottom: 0;
            line-height: 1.5;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--main-green) !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            letter-spacing: 0.5px;
        }
        
        .navbar-brand:hover {
            transform: translateY(-1px) scale(1.04);
            color: var(--accent-green) !important;
        }
        
        .navbar-brand i {
            font-size: 1.7rem;
            color: var(--main-green);
            filter: drop-shadow(0 2px 4px rgba(46,94,78,0.08));
        }
        
        /* Modern Navigation Links */
        .navbar-nav .nav-link {
            color: #374151 !important;
            font-weight: 500;
            font-size: 1rem;
            padding: 0.5rem 1.1rem !important;
            border-radius: 999px;
            margin: 0 0.18rem;
            transition: var(--transition);
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            background: none;
            box-shadow: none;
        }
        
        .navbar-nav .nav-link:hover {
            color: var(--main-green) !important;
            background: rgba(46,94,78,0.10);
            transform: translateY(-2px) scale(1.06);
            box-shadow: 0 2px 8px rgba(46,94,78,0.06);
        }
        
        .navbar-nav .nav-link.active {
            color: #fff !important;
            background: var(--main-green);
            font-weight: 700;
            box-shadow: 0 4px 16px rgba(46,94,78,0.10);
            position: relative;
        }
        
        .navbar-nav .nav-link.active::after {
            content: '';
            display: block;
            position: absolute;
            left: 20%;
            right: 20%;
            bottom: 6px;
            height: 3px;
            border-radius: 2px;
            background: #fff;
            opacity: 0.7;
        }
        
        .navbar-nav .nav-link i {
            font-size: 1.1rem;
            transition: var(--transition);
            filter: drop-shadow(0 1px 2px rgba(46,94,78,0.08));
        }
        
        .navbar-nav .nav-link:hover i {
            transform: scale(1.13);
            color: var(--main-green);
        }
        

        

        
        /* Compact navbar for admin */
        .navbar-nav .nav-item {
            margin: 0 0.12rem;
        }
        
        /* Modern Dropdown Styling */
        .navbar-nav .dropdown-menu {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--accent-green);
            border-radius: 1rem;
            box-shadow: 0 10px 40px rgba(46,94,78,0.10);
            padding: 0.75rem 0;
            margin-top: 0.5rem;
            min-width: 220px;
            animation: dropdownFadeIn 0.3s ease-out;
            z-index: 1050 !important;
            position: absolute;
            top: 100%;
            left: 0;
            display: none; /* Let Bootstrap handle the display */
            float: left;
            font-size: 1rem;
            color: #212529;
            text-align: left;
            list-style: none;
            background-clip: padding-box;
        }
        
        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-item {
            color: #374151;
            font-weight: 500;
            padding: 0.7rem 1.5rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-radius: 0.75rem;
            margin: 0.1rem 0;
        }
        
        .dropdown-item.active, .dropdown-item:active {
            background: var(--accent-green);
            color: #fff !important;
            font-weight: 700;
        }
        
        .dropdown-item:hover {
            background-color: rgba(46,94,78,0.10);
            color: var(--main-green);
            transform: translateX(4px) scale(1.04);
        }
        
        .dropdown-item i {
            font-size: 1.1rem;
            width: 1.25rem;
            text-align: center;
        }
        
        .dropdown-divider {
            margin: 0.5rem 0;
            border-color: rgba(0, 0, 0, 0.08);
        }
        
        /* Profile Section */
        .navbar-nav .nav-item.dropdown:last-child {
            margin-left: 0.5rem;
        }
        
        .profile-section {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            border-radius: 999px;
            background: rgba(46,94,78,0.07);
            border: 1px solid var(--accent-green);
            transition: var(--transition);
            font-weight: 600;
        }
        
        .profile-section:hover {
            background: rgba(46,94,78,0.13);
            transform: translateY(-1px) scale(1.03);
        }
        
        .profile-picture {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(46,94,78,0.2);
            transition: var(--transition);
        }
        
        .profile-picture:hover {
            border-color: var(--accent-green);
            transform: scale(1.05);
        }
        
        /* Notification Bell */
        .notification-bell {
            position: relative;
            padding: 0.4rem;
            border-radius: 50%;
            background: rgba(46,94,78,0.05);
            border: 1px solid rgba(46,94,78,0.1);
            transition: var(--transition);
            margin-right: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-bell:hover {
            background: rgba(46,94,78,0.1);
            transform: scale(1.1);
        }
        
        /* Custom notification badge styling to match student notification icon */
        .notification-bell .badge {
            font-size: 0.7rem !important;
            font-weight: 600 !important;
            min-width: 18px !important;
            height: 18px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border: 2px solid white !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
            transform: translate(-50%, -50%) !important;
            top: 0 !important;
            right: 0 !important;
            position: absolute !important;
            border-radius: 50% !important;
            line-height: 1 !important;
            padding: 0 !important;
            z-index: 1000 !important;
        }
        
        /* Simple red dot styling - no animation, clean design */
        .notification-bell .red-dot {
            position: absolute !important;
            top: 0 !important;
            right: 0 !important;
            width: 12px !important;
            height: 12px !important;
            background-color: #dc3545 !important;
            border-radius: 50% !important;
            border: 2px solid white !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
            transform: translate(50%, -50%) !important;
            z-index: 1000 !important;
        }
        
        /* Tab badge styling for proper circular appearance */
        .nav-tabs .badge {
            width: 36px !important;
            height: 36px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            line-height: 1 !important;
            border: 3px solid white !important;
            font-size: 0.9rem !important;
            font-weight: 700 !important;
            border-radius: 50% !important;
            transform: translate(-50%, -50%) !important;
            top: 0 !important;
            right: 0 !important;
            position: absolute !important;
            z-index: 1000 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
        }
        
        /* Simple red dot for tab badges */
        .nav-tabs .red-dot {
            position: absolute !important;
            top: 0 !important;
            right: 0 !important;
            width: 16px !important;
            height: 16px !important;
            background-color: #dc3545 !important;
            border-radius: 50% !important;
            border: 3px solid white !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
            transform: translate(50%, -50%) !important;
            z-index: 1000 !important;
        }
        
        /* Ensure the notification bell container has proper positioning context */
         .notification-bell {
             position: relative !important;
             overflow: visible !important;
         }
         
         /* Ensure tab buttons have proper positioning context */
         .nav-tabs .nav-link.position-relative {
             position: relative !important;
             overflow: visible !important;
         }
        
        /* Additional styling for perfect badge positioning */
        .notification-bell .badge::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: inherit;
            z-index: -1;
        }
        

        
        /* Responsive Design */
        @media (max-width: 991.98px) {
            .navbar {
                height: auto;
                min-height: var(--navbar-height);
            }
            
            body {
                padding-top: calc(var(--navbar-height) + 2rem);
            }
            
            .navbar-nav .nav-link {
                padding: 1rem !important;
                margin: 0.25rem 0;
            }
            
            .dropdown-menu {
                border: none;
                box-shadow: none;
                background: rgba(0, 48, 135, 0.05);
                margin-top: 0;
            }
            
            .profile-section {
                margin: 1rem 0;
                justify-content: center;
            }
            
            .notification-bell {
                margin-right: 0;
                margin-bottom: 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 1.1rem;
            }
            
            .navbar-brand i {
                font-size: 1.3rem;
            }
        }
        
        /* Toggle Button */
        .navbar-toggler {
            border: none;
            padding: 0.4rem;
            border-radius: var(--border-radius);
            background: rgba(0, 48, 135, 0.05);
            transition: var(--transition);
        }
        
        .navbar-toggler:hover {
            background: rgba(0, 48, 135, 0.1);
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        /* Body padding for fixed navbar */
        body {
            padding-top: calc(var(--navbar-height) + 1rem);
        }
        
        /* Ensure main content doesn't overlap */
        main {
            margin-top: 1rem;
        }
        
        /* Prevent navbar overflow */
        .navbar .container {
            max-width: 100%;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .navbar-nav {
            flex-wrap: nowrap;
            align-items: center;
        }
        
        /* Ensure dropdowns don't cause horizontal scroll */
        .navbar-nav .dropdown-menu {
            max-width: 250px;
        }
        
        /* Compact spacing for admin navbar */
        .navbar-nav .nav-item {
            flex-shrink: 0;
        }
        
        /* Hide text on smaller screens for admin navbar */
        @media (max-width: 1200px) {
            .navbar-nav .nav-link span {
                display: none;
            }
            
            .navbar-nav .nav-link {
                padding: 0.4rem 0.5rem !important;
                font-size: 0.85rem;
            }
        }
        
        .btn-primary {
            background-color: var(--main-green);
            border-color: var(--main-green);
        }
        
        .btn-primary:hover {
            background-color: #002366;
            border-color: #002366;
        }
        
        .text-neust-blue {
            color: var(--main-green) !important;
        }
        
        .bg-neust-blue {
            background-color: var(--main-green) !important;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.5rem;
        }
        
        .card-header {
            background-color: var(--main-green);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        
        .sidebar {
            min-height: calc(100vh - 76px);
            background-color: #f8f9fa;
        }
        
        .sidebar .nav-link {
            color: #495057;
            border-radius: 0.375rem;
            margin: 0.125rem 0;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--main-green);
            color: white;
        }
        
        .progress {
            height: 0.75rem;
            border-radius: 0.375rem;
        }
        
        .badge-custom {
            background-color: var(--main-green);
            color: white;
        }
        
        .footer {
            background-color: var(--main-green);
            color: white;
            padding: 1rem 0;
            margin-top: auto;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--main-green) 0%, var(--accent-green) 100%);
            color: white;
            padding: 4rem 0;
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--main-green) 0%, var(--accent-green) 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
        }
        
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }
        
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .assessment-timer {
            background-color: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: bold;
        }
        
        .leaderboard-item {
            transition: transform 0.2s;
        }
        
        .leaderboard-item:hover {
            transform: translateY(-2px);
        }
        
        .profile-picture {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .profile-picture-lg {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Ensure dropdowns work properly - consolidated with modern styling above */
        
        .navbar-nav .dropdown-menu.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Ensure dropdowns are visible */
        .dropdown-menu.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
        }
        
        /* Force dropdown visibility - removed conflicting rule */
        
        .navbar-nav .dropdown-menu.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .navbar-nav .dropdown-toggle::after {
            display: inline-block;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }
        
        /* Ensure dropdown positioning and visibility */
        .navbar-nav .nav-item.dropdown {
            position: relative;
        }
        
        .navbar-nav .dropdown-menu {
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            right: auto !important;
            transform: none !important;
            z-index: 1050 !important;
        }
        
        /* Force dropdown to be visible when shown */
        .navbar-nav .dropdown-menu.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
        }
        
        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.25rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
        }
        
        .dropdown-item:hover,
        .dropdown-item:focus {
            color: #1e2125;
            background-color: #e9ecef;
        }
        
        .dropdown-divider {
            height: 0;
            margin: 0.5rem 0;
            overflow: hidden;
            border-top: 1px solid rgba(0, 0, 0, 0.15);
        }
        .notification-bell,
        .notification-bell:hover,
        .notification-bell:focus,
        .notification-bell:active {
            background: #fff !important;
            border: 2px solid #e5e7eb !important; /* subtle gray border */
            box-shadow: none !important;
            transform: none !important;
            cursor: pointer;
            padding: 0.4rem;
            border-radius: 50%;
            transition: none;
            outline: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
        }
        .notification-bell i {
            font-size: 1.7rem;
            color: #374151;
        }
        
        /* Teacher Notification Modal Styling */
        #teacherNotificationModal .modal-header {
            background: linear-gradient(135deg, var(--main-green) 0%, var(--accent-green) 100%) !important;
        }
        
        /* Light tabs for teacher notification modal */
        .nav-tabs-light {
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        
        .nav-tabs-light .nav-link {
            color: rgba(255,255,255,0.8) !important;
            border: none;
            background: transparent;
            border-radius: 0;
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .nav-tabs-light .nav-link:hover {
            color: white !important;
            background: rgba(255,255,255,0.1);
            border: none;
        }
        
        .nav-tabs-light .nav-link.active {
            color: white !important;
            background: rgba(255,255,255,0.2);
            border: none;
            border-bottom: 3px solid white;
        }
        
        #teacherNotificationModal .list-group-item {
            border-left: 4px solid var(--accent-green);
            transition: all 0.3s ease;
        }
        
        #teacherNotificationModal .list-group-item:hover {
            border-left-color: var(--main-green);
            background-color: rgba(46,94,78,0.05);
        }
        
        #teacherNotificationModal .btn-outline-primary {
            border-color: var(--accent-green);
            color: var(--accent-green);
        }
        
        #teacherNotificationModal .btn-outline-primary:hover {
            background-color: var(--accent-green);
            border-color: var(--accent-green);
            color: white;
        }
        
        /* Login link styling to match login page */
        .navbar-nav .nav-link[href*="login.php"] {
            background: var(--main-green) !important;
            color: white !important;
            border-radius: 999px;
            padding: 0.5rem 1.1rem !important;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(46,94,78,0.10);
        }
        
        .navbar-nav .nav-link[href*="login.php"]:hover {
            background: var(--accent-green) !important;
            color: var(--main-green) !important;
            transform: translateY(-2px) scale(1.06);
            box-shadow: 0 6px 20px rgba(46,94,78,0.15);
        }
        
        .navbar-nav .nav-link[href*="login.php"] i {
            color: inherit;
        }
        

        
        /* Alert Styling */
        .alert {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Scrollable announcements styling */
        .announcements-scrollable {
            max-height: 400px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0 15px;
        }
        
        .announcements-scrollable::-webkit-scrollbar {
            width: 6px;
        }
        
        .announcements-scrollable::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .announcements-scrollable::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .announcements-scrollable::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Smooth scrolling */
        .announcements-scrollable {
            scroll-behavior: smooth;
        }
        
        /* Ensure list items have proper spacing */
        .announcements-scrollable .list-group-item {
            border-left: none;
            border-right: none;
            border-radius: 0;
        }
        
        .announcements-scrollable .list-group-item:first-child {
            border-top: none;
        }
        
        .announcements-scrollable .list-group-item:last-child {
            border-bottom: none;
        }

        /* Real-time Calendar Widget Styles */
        .calendar-widget {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem !important;
            border-radius: 999px;
            margin: 0 0.18rem;
            transition: var(--transition);
            background: none;
            box-shadow: none;
            cursor: pointer;
            color: #374151 !important;
            font-weight: 500;
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .calendar-widget:hover {
            color: var(--main-green) !important;
            background: rgba(46,94,78,0.10);
            transform: translateY(-2px) scale(1.06);
            box-shadow: 0 2px 8px rgba(46,94,78,0.06);
        }
        
        .calendar-widget i {
            font-size: 1.1rem;
            color: var(--main-green);
            filter: drop-shadow(0 1px 2px rgba(46,94,78,0.1));
        }
        
        .calendar-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.2;
        }
        
        .calendar-date {
            font-weight: 600;
            color: #374151;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        .calendar-time {
            font-weight: 500;
            color: #6b7280;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 991.98px) {
            .calendar-widget {
                padding: 0.5rem 0.8rem !important;
            }
            
            .calendar-widget i {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 767.98px) {
            .calendar-widget {
                padding: 0.4rem 0.6rem !important;
                margin: 0 0.1rem;
            }
        }

        /* Calendar Modal Styles */
        .calendar-container {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .calendar-header h4 {
            color: var(--main-green);
            font-weight: 600;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .calendar-day-header {
            background: var(--main-green);
            color: white;
            padding: 0.75rem 0.5rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .calendar-day {
            background: white;
            padding: 0.75rem 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        
        .calendar-day:hover {
            background: rgba(46, 94, 78, 0.1);
            transform: scale(1.05);
        }
        
        .calendar-day.other-month {
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .calendar-day.today {
            background: var(--main-green);
            color: white;
            font-weight: 600;
        }
        
        .calendar-day.today:hover {
            background: var(--accent-green);
        }
        
        .calendar-day.selected {
            background: var(--accent-green);
            color: white;
            font-weight: 600;
        }
        
        .today-indicator {
            width: 12px;
            height: 12px;
            background: var(--main-green);
            border-radius: 50%;
        }
        
        .calendar-day-number {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .calendar-day-events {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 6px;
            height: 6px;
            background: #dc3545;
            border-radius: 50%;
            display: none;
        }
        
        .calendar-day.has-events .calendar-day-events {
            display: block;
        }
        
        @media (max-width: 768px) {
            .calendar-day {
                min-height: 50px;
                padding: 0.5rem 0.25rem;
            }
            
            .calendar-day-number {
                font-size: 0.9rem;
            }
        }

    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="bi bi-mortarboard-fill"></i>
                <span><?php echo SITE_NAME; ?></span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <?php
                        $home_link = SITE_URL . '/';
                        ?>
                        <a class="nav-link<?php if ($current_page == 'index.php') echo ' active'; ?>" href="<?php echo $home_link; ?>">
                            <i class="bi bi-house-door"></i>Home
                        </a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <?php if ($_SESSION['role'] === 'student'): ?>
                            <li class="nav-item">
                                <a class="nav-link<?php if ($current_page == 'dashboard.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/student/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link<?php if ($current_page == 'courses.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/student/courses.php">
                                    <i class="bi bi-book"></i> My Courses
                                </a>
                            </li>
                            <li class="nav-item dropdown<?php 
    $student_activity_pages = ['assessments.php','progress.php','badges.php','leaderboard.php'];
    foreach ($student_activity_pages as $sap) { if (strpos($current_page, basename($sap, '.php')) === 0) { echo ' active'; break; } }
?>">
                                <a class="nav-link dropdown-toggle" href="#" id="studentActivityDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-activity"></i> Activity
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item<?php if ($current_page == 'assessments.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/student/assessments.php">
                                        <i class="bi bi-clipboard-check"></i>Assessments
                                    </a></li>
                                    <li><a class="dropdown-item<?php if ($current_page == 'progress.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/student/progress.php">
                                        <i class="bi bi-graph-up"></i>Progress
                                    </a></li>
                                    <li><a class="dropdown-item<?php if ($current_page == 'badges.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/student/badges.php">
                                        <i class="bi bi-trophy"></i>Badges
                                    </a></li>
                                    <li><a class="dropdown-item<?php if ($current_page == 'leaderboard.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/student/leaderboard.php">
                                        <i class="bi bi-award"></i>Leaderboard
                                    </a></li>
                                </ul>
                            </li>
                        <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                            <li class="nav-item">
                                <a class="nav-link<?php if ($current_page == 'dashboard.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/teacher/dashboard.php" id="teacher-dashboard-link">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link<?php if ($current_page == 'courses.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/teacher/courses.php">
                                    <i class="bi bi-book"></i>My Courses
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link<?php if ($current_page == 'announcements.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/teacher/announcements.php">
                                    <i class="bi bi-megaphone"></i>Announcements
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link<?php if ($current_page == 'badges.php' || $current_page == 'student_badges.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/teacher/badges.php">
                                    <i class="bi bi-trophy"></i>Badges
                                </a>
                            </li>
                            <li class="nav-item dropdown<?php 
    $teacher_activity_pages = ['modules.php','assessments.php','students.php','videos.php','announcements.php'];
    foreach ($teacher_activity_pages as $tap) { if (strpos($current_page, basename($tap, '.php')) === 0) { echo ' active'; break; } }
?>">
                                <a class="nav-link dropdown-toggle" href="#" id="teacherActivityDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-activity"></i> Activity
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item<?php if ($current_page == 'modules.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/teacher/modules.php">
                                        <i class="bi bi-folder"></i>Modules
                                    </a></li>
                                    <li><a class="dropdown-item<?php if ($current_page == 'assessments.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/teacher/assessments.php">
                                        <i class="bi bi-clipboard-check"></i>Assessments
                                    </a></li>
                                    <li><a class="dropdown-item<?php if ($current_page == 'students.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/teacher/students.php">
                                        <i class="bi bi-people"></i>Students
                                    </a></li>
                                    <li><a class="dropdown-item<?php if ($current_page == 'videos.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/teacher/videos.php">
                                        <i class="bi bi-camera-video"></i>Videos
                                    </a></li>
                                </ul>
                            </li>
                        <?php elseif ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link<?php if ($current_page == 'dashboard.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                                    <i class="bi bi-speedometer2"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item dropdown<?php 
    $users_pages = ['users.php','teachers.php','students.php','users_edit.php','teachers_view.php','students_view.php'];
    foreach ($users_pages as $up) { if (strpos($current_page, basename($up, '.php')) === 0) { echo ' active'; break; } }
?>">
                                <a class="nav-link dropdown-toggle" href="#" id="usersDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-people"></i>Users
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item<?php if ($current_page == 'users.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/admin/users.php">
                                        <i class="bi bi-people"></i>All Users
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item<?php if ($current_page == 'teachers.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/admin/teachers.php">
                                        <i class="bi bi-person-badge"></i>Teachers
                                    </a></li>
                                    <li><a class="dropdown-item<?php if ($current_page == 'students.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/admin/students.php">
                                        <i class="bi bi-person-lines-fill"></i>Students
                                    </a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown<?php 
            $academic_pages = ['academic_periods.php','courses.php','sections.php','courses_edit.php','sections_edit.php'];
    foreach ($academic_pages as $ap) { if (strpos($current_page, basename($ap, '.php')) === 0) { echo ' active'; break; } }
?>">
                                <a class="nav-link dropdown-toggle" href="#" id="academicDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-calendar-event"></i>Academic
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item<?php if ($current_page == 'academic_periods.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/admin/academic_periods.php">
                                        <i class="bi bi-calendar-event"></i>Academic Periods
                                    </a></li>

                                    <li><a class="dropdown-item<?php if ($current_page == 'courses.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/admin/courses.php">
                                        <i class="bi bi-book"></i>Courses
                                    </a></li>
                                    <li><a class="dropdown-item<?php if ($current_page == 'sections.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/admin/sections.php">
                                        <i class="bi bi-journal-text"></i>Sections
                                    </a></li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link<?php if ($current_page == 'announcements.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/admin/announcements.php">
                                    <i class="bi bi-megaphone"></i>Announcements
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <!-- About Us nav item at the end -->
                    <li class="nav-item">
                        <a class="nav-link<?php if ($current_page == 'about_us.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/about_us.php">
                            <i class="bi bi-info-circle"></i>About Us
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <!-- Real-time Calendar -->
                        <li class="nav-item">
                            <div class="nav-link calendar-widget" id="calendarWidget" data-bs-toggle="modal" data-bs-target="#calendarModal">
                                <i class="bi bi-calendar3"></i>
                                <div class="calendar-info d-none d-lg-inline">
                                    <div class="calendar-date" id="currentDate"></div>
                                    <div class="calendar-time" id="currentTime"></div>
                                </div>
                            </div>
                        </li>

                        <?php
                        // Fetch unread announcements for the logged-in user
                        $user_id = $_SESSION['user_id'] ?? null;
                        $ann_count = 0;
                        $navbar_announcements = [];
                        if ($user_id) {
                            $ann_stmt = $pdo->prepare("
                                SELECT a.id, a.title, a.created_at, a.content
                                FROM announcements a
                                WHERE (a.read_by IS NULL OR JSON_SEARCH(a.read_by, 'one', ?) IS NULL)
                                ORDER BY a.created_at DESC
                                LIMIT 5
                            ");
                            $ann_stmt->execute([$user_id]);
                            $navbar_announcements = $ann_stmt->fetchAll();
                            $ann_count = count($navbar_announcements);
                        }
                        ?>
                        <?php
                        // Show notification bell for students, teachers, and admins on all pages
                        if (isLoggedIn() && isset($_SESSION['role']) && in_array($_SESSION['role'], ['student', 'teacher', 'admin'])):
                        ?>
                        <li class="nav-item">
                            <?php
                            // Calculate notification count based on role
                            $bell_active = '';
                            $notification_count = $ann_count; // Start with announcement count
                            
                            // For students, also include enrollment notifications
                            if ($_SESSION['role'] === 'student') {
                                try {
                                    $enrollment_stmt = $pdo->prepare("
                                        SELECT COUNT(*) as count
                                        FROM notifications n
                                        WHERE n.user_id = ? 
                                        AND n.type IN ('enrollment_approved', 'enrollment_rejected', 'course_kicked')
                                        AND n.is_read = 0
                                    ");
                                    $enrollment_stmt->execute([$user_id]);
                                    $enrollment_result = $enrollment_stmt->fetch();
                                    $enrollment_count = $enrollment_result['count'] ?? 0;
                                    $notification_count += $enrollment_count;
                                } catch (Exception $e) {
                                    error_log("Error counting enrollment notifications: " . $e->getMessage());
                                }
                            }
                            
                            if ($notification_count > 0) $bell_active = ' active';
                            ?>
                                                         <a class="nav-link notification-bell position-relative<?= $bell_active ?>" href="#" id="navbarAnnounceDropdown" role="button" data-bs-toggle="modal" data-bs-target="<?= $_SESSION['role'] === 'teacher' ? '#teacherNotificationModal' : ($_SESSION['role'] === 'student' ? '#studentNotificationModal' : '#announcementModal') ?>">
                                 <i class="bi bi-bell"></i>
                                 <?php if ($notification_count > 0): ?>
                                     <span class="red-dot"></span>
                                 <?php endif; ?>
                             </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle profile-section<?php if (in_array($current_page, ['profile.php', 'logout.php'])) echo ' active'; ?>" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?php echo getProfilePictureUrl($_SESSION['profile_picture'] ?? null, 'small'); ?>" 
                                     class="profile-picture" alt="Profile">
                                <span class="d-none d-md-inline"><?php echo $_SESSION['name'] ?? 'User'; ?></span>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item<?php if ($current_page == 'profile.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/profile.php">
                                    <i class="bi bi-person"></i>Profile
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item<?php if ($current_page == 'logout.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/logout.php">
                                    <i class="bi bi-box-arrow-right"></i>Logout
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link<?php if ($current_page == 'login.php') echo ' active'; ?>" href="<?php echo SITE_URL; ?>/login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
<div class="navbar-accent-bar"></div>

    <!-- Main Content -->
    <main class="flex-grow-1">
        <div class="container-fluid">
            <?php echo displayMessage(); ?>
        </div>
    </main>

    <!-- Enhanced Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-megaphone-fill me-2"></i>
                        <h5 class="modal-title mb-0" id="announcementModalLabel">
                            <span id="announcement-count">Unread Announcements</span>
                        </h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="announcement-loading" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading announcements...</p>
                    </div>
                    
                    <div id="announcement-content" class="announcements-scrollable">
                        <?php if ($ann_count === 0): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                                <h5 class="mt-3 text-muted">All Caught Up!</h5>
                                <p class="text-muted">No unread announcements at this time.</p>
                            </div>
                        <?php else: ?>
                            <div id="announcement-list">
                                <!-- Announcements will be loaded here dynamically -->
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="mark-all-read">
                        <i class="bi bi-check-all me-1"></i>Mark All as Read
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Announcement Detail Modal -->
    <div class="modal fade" id="announcementDetailModal" tabindex="-1" aria-labelledby="announcementDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="announcementDetailModalLabel">Announcement Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="announcement-detail-content">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="mark-detail-read">
                        <i class="bi bi-check me-1"></i>Mark as Read
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher Notification Modal -->
    <div class="modal fade" id="teacherNotificationModal" tabindex="-1" aria-labelledby="teacherNotificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-bell-fill me-2"></i>
                        <h5 class="modal-title mb-0" id="teacherNotificationModalLabel">
                            <span id="teacher-notification-count">Teacher Notifications</span>
                        </h5>
                        <ul class="nav nav-tabs nav-tabs-light" id="teacherNotificationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active position-relative" id="announcements-tab" data-bs-toggle="tab" data-bs-target="#announcements-content" type="button" role="tab">
                                    <i class="bi bi-megaphone me-1"></i>Announcements
                                    <span class="red-dot announcement-count-badge" style="display: none;"></span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="teacher-notification-loading" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading notifications...</p>
                    </div>
                    
                    <div class="tab-content" id="teacherNotificationTabContent">
                        <!-- Announcements Tab -->
                        <div class="tab-pane fade show active" id="announcements-content" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 border-bottom">
                                <h6 class="mb-0">Unread Announcements</h6>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="teacher-mark-all-read">
                                    <i class="bi bi-check-all me-1"></i>Mark All as Read
                                </button>
                            </div>
                            <div id="teacher-announcements-content" class="announcements-scrollable">
                                <div class="text-center py-5">
                                    <i class="bi bi-megaphone text-primary" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3 text-muted">Loading announcements...</h5>
                                    <p class="text-muted">Please wait while we fetch your announcements.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Notification Modal -->
    <div class="modal fade" id="studentNotificationModal" tabindex="-1" aria-labelledby="studentNotificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-bell-fill me-2"></i>
                        <h5 class="modal-title mb-0" id="studentNotificationModalLabel">
                            <span id="student-notification-count">Student Notifications</span>
                        </h5>
                        <ul class="nav nav-tabs nav-tabs-light" id="studentNotificationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active position-relative" id="student-enrollment-tab" data-bs-toggle="tab" data-bs-target="#student-enrollment-content" type="button" role="tab">
                                    <i class="bi bi-person-check me-1"></i>Enrollment Status
                                    <span class="red-dot enrollment-count-badge" style="display: none;"></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link position-relative" id="student-announcements-tab" data-bs-toggle="tab" data-bs-target="#student-announcements-content" type="button" role="tab">
                                    <i class="bi bi-megaphone me-1"></i>Announcements
                                    <span class="red-dot announcement-count-badge" style="display: none;"></span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="student-notification-loading" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading notifications...</p>
                    </div>
                    
                    <div class="tab-content" id="studentNotificationTabContent">
                        <!-- Enrollment Status Tab -->
                        <div class="tab-pane fade show active" id="student-enrollment-content" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 border-bottom">
                                <h6 class="mb-0">Enrollment Notifications</h6>
                                <button type="button" class="btn btn-outline-success btn-sm" id="student-mark-enrollment-read">
                                    <i class="bi bi-check-all me-1"></i>Mark All as Read
                                </button>
                            </div>
                            <div id="student-enrollment-list" class="announcements-scrollable">
                                <div class="text-center py-5">
                                    <i class="bi bi-person-check text-success" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3 text-muted">Loading enrollment notifications...</h5>
                                    <p class="text-muted">Please wait while we fetch your notifications.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Announcements Tab -->
                        <div class="tab-pane fade" id="student-announcements-content" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 border-bottom">
                                <h6 class="mb-0">Unread Announcements</h6>
                                <button type="button" class="btn btn-outline-success btn-sm" id="student-mark-all-read">
                                    <i class="bi bi-check-all me-1"></i>Mark All as Read
                                </button>
                            </div>
                            <div id="student-announcements-list" class="announcements-scrollable">
                                <div class="text-center py-5">
                                    <i class="bi bi-megaphone text-success" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3 text-muted">Loading announcements...</h5>
                                    <p class="text-muted">Please wait while we fetch your announcements.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Close
                    </button>
                    <a href="<?php echo SITE_URL; ?>/student/enrollment_requests.php" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>View Enrollment Requests
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Modal -->
    <div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-calendar3 me-2"></i>
                        <h5 class="modal-title mb-0" id="calendarModalLabel">Calendar</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="calendar-container">
                        <div class="calendar-header d-flex justify-content-between align-items-center mb-3">
                            <button class="btn btn-outline-primary" id="prevMonth">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <h4 class="mb-0" id="currentMonthYear"></h4>
                            <button class="btn btn-outline-primary" id="nextMonth">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div class="calendar-grid" id="calendarGrid">
                            <!-- Calendar will be generated here -->
                        </div>
                        <div class="calendar-info mt-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <div class="today-indicator me-2"></div>
                                        <small class="text-muted">Today</small>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small class="text-muted" id="currentDateTime"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

<script>
$(document).on('click', '.mark-ann-read', function() {
  var btn = $(this);
  var annId = btn.data('ann-id');
  $.post('<?php echo SITE_URL; ?>/mark_announcement_read.php', { id: annId }, function(res) {
    console.log('AJAX response:', res);
    if (res === 'OK') {
      // Remove the specific announcement row
      $('#ann-row-' + annId).fadeOut(300, function() { 
        $(this).remove(); 
        
        // Check which modal is open and handle accordingly
        if ($('#teacherNotificationModal').hasClass('show')) {
          // Teacher modal
          var remainingAnnouncements = $('#teacher-announcements-content .list-group-item').length;
          console.log('Remaining teacher announcements in modal:', remainingAnnouncements);
          
          if (remainingAnnouncements === 0) {
            $('#teacher-announcements-content').html('<div class="text-center py-5"><i class="bi bi-megaphone text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
            console.log('🚫 All teacher announcements read - hiding notification badges');
            hideAllStudentNotificationBadges();
          }
        } else if ($('#studentNotificationModal').hasClass('show')) {
          // Student modal
          var remainingAnnouncements = $('#student-announcements-content .list-group-item').length;
          console.log('Remaining student announcements in modal:', remainingAnnouncements);
          
          if (remainingAnnouncements === 0) {
            $('#student-announcements-content').html('<div class="text-center py-5"><i class="bi bi-megaphone text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
            console.log('🚫 All student announcements read - hiding notification badges');
            hideAllStudentNotificationBadges();
          }
          
          // Update student notification count
          updateStudentNotificationCount();
        } else if ($('#announcementModal').hasClass('show')) {
          // Admin modal
          var remainingAnnouncements = $('#announcementModal .list-group-item').length;
          console.log('Remaining admin announcements in modal:', remainingAnnouncements);
          
          if (remainingAnnouncements === 0) {
            $('#announcementModal .modal-body').html('<div class="text-center py-5"><i class="bi bi-megaphone text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
            console.log('🚫 All admin announcements read - hiding notification badges');
            hideAllStudentNotificationBadges();
          }
        }
      });
      
      // Update tab badges with new counts from actual modal content
      updateTabBadgesFromModalContent();
    } else {
      alert('Error: ' + res);
    }
  }).fail(function(xhr, status, error) {
    console.error('AJAX error:', status, error, xhr.responseText);
    alert('AJAX error: ' + error);
  });
});

// View announcement details
$(document).on('click', '.view-ann-details', function() {
  var btn = $(this);
  var annId = btn.data('ann-id');
  var annTitle = btn.data('ann-title');
  var annContent = btn.data('ann-content');
  var annAuthor = btn.data('ann-author');
  var annContext = btn.data('ann-context');
  
  // Populate the detail modal
  $('#announcementDetailModal .modal-title').text(annTitle);
  $('#announcement-detail-content').html(`
    <div class="mb-3">
      <strong>Author:</strong> ${annAuthor}
    </div>
    <div class="mb-3">
      <strong>Context:</strong> ${annContext}
    </div>
    <div class="mb-3">
      <strong>Content:</strong>
      <div class="mt-2 p-3 bg-light rounded">
        ${annContent}
      </div>
    </div>
  `);
  
  // Set the mark as read button to work with this announcement
  $('#mark-detail-read').data('ann-id', annId);
  
  // Show the detail modal
  var detailModal = new bootstrap.Modal(document.getElementById('announcementDetailModal'));
  detailModal.show();
});

// View announcement details from homepage
$(document).on('click', '.view-ann-details-home', function() {
  var btn = $(this);
  var annId = btn.data('ann-id');
  var annTitle = btn.data('ann-title');
  var annContent = btn.data('ann-content');
  var annAuthor = btn.data('ann-author');
  var annContext = btn.data('ann-context');
  
  // Populate the detail modal
  $('#announcementDetailModal .modal-title').text(annTitle);
  $('#announcement-detail-content').html(`
    <div class="mb-3">
      <strong>Author:</strong> ${annAuthor}
    </div>
    <div class="mb-3">
      <strong>Context:</strong> ${annContext}
    </div>
    <div class="mb-3">
      <strong>Content:</strong>
      <div class="mt-2 p-3 bg-light rounded">
        ${annContent}
      </div>
    </div>
  `);
  
  // Hide the mark as read button since this is just for viewing from homepage
  $('#mark-detail-read').hide();
  
  // Show the detail modal
  var detailModal = new bootstrap.Modal(document.getElementById('announcementDetailModal'));
  detailModal.show();
});

// Mark as read from detail modal
$(document).on('click', '#mark-detail-read', function() {
  var annId = $(this).data('ann-id');
  
  $.post('<?php echo SITE_URL; ?>/mark_announcement_read.php', { id: annId }, function(res) {
    console.log('AJAX response from detail modal:', res);
    if (res === 'OK') {
      // Close the detail modal
      var detailModal = bootstrap.Modal.getInstance(document.getElementById('announcementDetailModal'));
      detailModal.hide();
      
      // Remove the announcement from the main modal
      $('#ann-row-' + annId).fadeOut(300, function() { 
        $(this).remove(); 
        
        // Check if there are any remaining announcements in the modal
        var remainingAnnouncements = $('#student-announcements-content .list-group-item').length;
        console.log('Remaining announcements in modal:', remainingAnnouncements);
        
        if (remainingAnnouncements === 0) {
          // No more announcements, show "All Caught Up!" message
          $('#student-announcements-content').html('<div class="text-center py-5"><i class="bi bi-megaphone text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
          
          // Only hide badges when ALL announcements are read
          console.log('🚫 All announcements read - hiding notification badges');
          hideAllStudentNotificationBadges();
        }
      });
      
      // Update the badge count (don't remove badge if count > 0)
      updateNotificationBadgeCount();
    } else {
      alert('Error: ' + res);
    }
  }).fail(function(xhr, status, error) {
    console.error('AJAX error:', status, error, xhr.responseText);
    alert('AJAX error: ' + error);
  });
});



// Function to update student notification count
function updateStudentNotificationCount() {
  // Don't refresh the notification badge count when modal opens
  // Only update the modal title count based on actual remaining notifications
  var remainingAnnouncements = $('#student-announcements-content .list-group-item').length;
  var totalCount = remainingAnnouncements;
  
  if (totalCount > 0) {
    $('#student-notification-count').text('Student Notifications (' + totalCount + ')');
  } else {
    $('#student-notification-count').text('Student Notifications');
  }
  
  console.log('📊 Updated student notification count:', totalCount);
  
  // If no notifications remain, hide the red dot and badge
  if (totalCount === 0) {
    console.log('🚫 No notifications remaining - hiding all badges and red dots');
    hideAllStudentNotificationBadges();
    
    // Also ensure the notification bell badge is completely removed
    const notificationBell = document.getElementById('navbarAnnounceDropdown');
    if (notificationBell) {
      const badge = notificationBell.querySelector('.badge');
      if (badge) {
        badge.remove();
        console.log('✅ Notification bell badge removed (no notifications)');
      }
    }
    
    // Use the comprehensive function to hide all red dots
    hideAllRedDots();
  }
}

// Function to update notification badge count without removing the badge
function updateNotificationBadgeCount() {
  console.log('🔄 Updating notification badge count...');
  
  // Get the current badge
  var badge = $('#navbarAnnounceDropdown .badge');
  
  if (badge.length) {
    // Get current count
    var currentCount = parseInt(badge.text()) || 0;
    var newCount = Math.max(0, currentCount - 1);
    
    console.log('📊 Badge count: ' + currentCount + ' → ' + newCount);
    
    if (newCount > 0) {
      // Update the count but keep the badge visible
      badge.text(newCount);
      console.log('✅ Badge count updated to:', newCount);
    } else {
      // Only remove badge when count reaches 0
      badge.remove();
      console.log('✅ Badge removed (count reached 0)');
    }
  } else {
    console.log('ℹ️ No badge found to update');
  }
}

// Function to update tab badges when announcements are marked as read
function updateTabBadgesAfterMarkingRead() {
  console.log('🔄 Updating tab badges after marking announcement as read...');
  
  // Count remaining announcements in the current modal
  var remainingAnnouncements = 0;
  var remainingEnrollments = 0;
  
  // Check which modal is open and count remaining items
  if ($('#teacherNotificationModal').hasClass('show')) {
    // Teacher modal
    remainingAnnouncements = $('#teacher-announcements-content .list-group-item').length;
    remainingEnrollments = $('#teacher-enrollment-content .list-group-item').length;
  } else if ($('#studentNotificationModal').hasClass('show')) {
    // Student modal
    remainingAnnouncements = $('#student-announcements-content .list-group-item').length;
    remainingEnrollments = 0;
  } else if ($('#announcementModal').hasClass('show')) {
    // Admin modal
    remainingAnnouncements = $('#announcementModal .list-group-item').length;
  }
  
  console.log('📊 Remaining items:', { announcements: remainingAnnouncements, enrollments: remainingEnrollments });
  
  // Update tab badges with new counts
  updateTabBadges(remainingEnrollments, remainingAnnouncements);
  
  // If no notifications remain, hide the bell red dot
  var totalCount = remainingAnnouncements + remainingEnrollments;
  if (totalCount === 0) {
    var bellRedDot = $('#navbarAnnounceDropdown .red-dot');
    if (bellRedDot.length > 0) {
      bellRedDot.remove();
      console.log('✅ Bell red dot removed (no notifications remaining)');
    }
  }
  
  console.log('✅ Tab badges updated after marking as read');
}

// Function to update tab badges with counts
function updateTabBadges(enrollmentCount, announcementCount) {
  console.log('🔄 Updating tab badges:', { enrollment: enrollmentCount, announcement: announcementCount });
  
  // Update teacher modal tab badges

  var teacherAnnouncementBadge = $('.announcement-count-badge');
  

  
  if (teacherAnnouncementBadge.length) {
    if (announcementCount > 0) {
      teacherAnnouncementBadge.show();
    } else {
      teacherAnnouncementBadge.hide();
    }
  }
  
  // Update student modal tab badges
  var studentEnrollmentBadge = $('.enrollment-count-badge');
  var studentAnnouncementBadge = $('.announcement-count-badge');
  
  if (studentEnrollmentBadge.length) {
    if (enrollmentCount > 0) {
      studentEnrollmentBadge.text(enrollmentCount).show();
    } else {
      studentEnrollmentBadge.hide();
    }
  }
  
  if (studentAnnouncementBadge.length) {
    if (announcementCount > 0) {
      studentAnnouncementBadge.text(announcementCount).show();
    } else {
      studentAnnouncementBadge.hide();
    }
  }
  
  // Update bell icon red dot visibility
  var bellRedDot = $('#navbarAnnounceDropdown .red-dot');
  var totalCount = enrollmentCount + announcementCount;
  
  if (totalCount > 0) {
    if (bellRedDot.length === 0) {
      // Add red dot if it doesn't exist
      $('#navbarAnnounceDropdown').append('<span class="red-dot"></span>');
    }
  } else {
    // Remove red dot if no notifications
    bellRedDot.remove();
  }
  
  console.log('✅ Tab badges updated successfully');
}

// Function to update tab badges based on actual content in modals
function updateTabBadgesFromModalContent() {
  console.log('🔄 Updating tab badges from modal content...');
  
  var teacherEnrollmentCount = 0;
  var teacherAnnouncementCount = 0;
  var studentEnrollmentCount = 0;
  var studentAnnouncementCount = 0;
  
  // Count teacher modal notifications
  if ($('#teacherNotificationModal').hasClass('show')) {
    teacherEnrollmentCount = 0;
    teacherAnnouncementCount = $('#teacher-announcements-content .list-group-item').length;
    console.log('📊 Teacher modal counts:', { enrollment: teacherEnrollmentCount, announcement: teacherAnnouncementCount });
  }
  
  // Count student modal notifications
  if ($('#studentNotificationModal').hasClass('show')) {
    studentEnrollmentCount = $('#student-enrollment-list .list-group-item').length;
    studentAnnouncementCount = $('#student-announcements-content .list-group-item').length;
    console.log('📊 Student modal counts:', { enrollment: studentEnrollmentCount, announcement: studentAnnouncementCount });
  }
  
  // Count admin modal notifications
  if ($('#announcementModal').hasClass('show')) {
    var adminAnnouncementCount = $('#announcementModal .list-group-item').length;
    console.log('📊 Admin modal count:', { announcement: adminAnnouncementCount });
    
    // Update admin modal tab badges if they exist
    if (adminAnnouncementCount === 0) {
      // Hide all red dots when no announcements remain
      hideAllRedDots();
    }
  }
  
  // Update tab badges with actual counts
  updateTabBadges(studentEnrollmentCount, teacherAnnouncementCount + studentAnnouncementCount);
  
  console.log('✅ Tab badges updated from modal content');
}

// Function to handle "Mark All as Read" button for admin modal
$(document).on('click', '#mark-all-read', function() {
  var btn = $(this);
  var announcements = $('#announcementModal .list-group-item');
  
  if (announcements.length === 0) {
    alert('No announcements to mark as read.');
    return;
  }
  
  btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Marking...');
  
  var totalAnnouncements = announcements.length;
  var markedCount = 0;
  var errors = [];
  
  announcements.each(function(index, element) {
    var $element = $(element);
    var annId = $element.find('.mark-ann-read').data('ann-id');
    
    $.post('<?php echo SITE_URL; ?>/mark_announcement_read.php', { id: annId }, function(res) {
      markedCount++;
      
      if (res === 'OK') {
        $element.fadeOut(300, function() { $(this).remove(); });
      } else {
        errors.push('Announcement ' + annId + ': ' + res);
      }
      
      // Check if all announcements have been processed
      if (markedCount === totalAnnouncements) {
        btn.prop('disabled', false).html('<i class="bi bi-check-all me-1"></i>Mark All as Read');
        
        // Check if there are any remaining announcements
        var remainingAnnouncements = $('#announcementModal .list-group-item').length;
        
        if (remainingAnnouncements === 0) {
          // No more announcements, show "All Caught Up!" message
          $('#announcementModal .modal-body').html('<div class="text-center py-5"><i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
          
          // Only hide badges when ALL announcements are read
          console.log('🚫 All announcements marked as read - hiding notification badges');
          hideAllStudentNotificationBadges();
        }
        
        // Update tab badges after marking all as read
        updateTabBadgesFromModalContent();
        
        // Show results
        if (errors.length > 0) {
          alert('Marked ' + (totalAnnouncements - errors.length) + ' announcements as read. Errors: ' + errors.join(', '));
        } else {
          alert('Successfully marked all ' + totalAnnouncements + ' announcements as read!');
        }
      }
    }).fail(function(xhr, status, error) {
      markedCount++;
      errors.push('Announcement ' + annId + ': ' + error);
      
      if (markedCount === totalAnnouncements) {
        btn.prop('disabled', false).html('<i class="bi bi-check-all me-1"></i>Mark All as Read');
        alert('Some errors occurred while marking announcements as read. Errors: ' + error);
      }
    });
  });
});

// Function to handle "Mark All as Read" button for teacher announcements
$(document).on('click', '#teacher-mark-all-read', function() {
  var btn = $(this);
  var announcements = $('#teacher-announcements-content .list-group-item');
  
  if (announcements.length === 0) {
    alert('No announcements to mark as read.');
    return;
  }
  
  btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Marking...');
  
  var totalAnnouncements = announcements.length;
  var markedCount = 0;
  var errors = [];
  
  announcements.each(function(index, element) {
    var $element = $(element);
    var annId = $element.find('.mark-ann-read').data('ann-id');
    
    $.post('<?php echo SITE_URL; ?>/mark_announcement_read.php', { id: annId }, function(res) {
      markedCount++;
      
      if (res === 'OK') {
        $element.fadeOut(300, function() { $(this).remove(); });
      } else {
        errors.push('Announcement ' + annId + ': ' + res);
      }
      
      // Check if all announcements have been processed
      if (markedCount === totalAnnouncements) {
        btn.prop('disabled', false).html('<i class="bi bi-check-all me-1"></i>Mark All as Read');
        
        // Check if there are any remaining announcements
        var remainingAnnouncements = $('#teacher-announcements-content .list-group-item').length;
        
        if (remainingAnnouncements === 0) {
          // No more announcements, show "All Caught Up!" message
          $('#teacher-announcements-content').html('<div class="text-center py-5"><i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
          
          // Only hide badges when ALL announcements are read
          console.log('🚫 All teacher announcements marked as read - hiding notification badges');
          hideAllStudentNotificationBadges();
        }
        
        // Update tab badges after marking all as read
        updateTabBadgesFromModalContent();
        
        // Show results
        if (errors.length > 0) {
          alert('Marked ' + (totalAnnouncements - errors.length) + ' announcements as read. Errors: ' + errors.join(', '));
        } else {
          alert('Successfully marked all ' + totalAnnouncements + ' announcements as read!');
        }
      }
    }).fail(function(xhr, status, error) {
      markedCount++;
      errors.push('Announcement ' + annId + ': ' + error);
      
      if (markedCount === totalAnnouncements) {
        btn.prop('disabled', false).html('<i class="bi bi-check-all me-1"></i>Mark All as Read');
        alert('Some errors occurred while marking announcements as read. Errors: ' + error);
      }
    });
  });
});

// Function to handle "Mark All as Read" button for student announcements
$(document).on('click', '#student-mark-all-read', function() {
  var btn = $(this);
  var announcements = $('#student-announcements-content .list-group-item');
  
  if (announcements.length === 0) {
    alert('No announcements to mark as read.');
    return;
  }
  
  btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Marking...');
  
  var totalAnnouncements = announcements.length;
  var markedCount = 0;
  var errors = [];
  
  announcements.each(function(index, element) {
    var $element = $(element);
    var annId = $element.find('.mark-ann-read').data('ann-id');
    
    $.post('<?php echo SITE_URL; ?>/mark_announcement_read.php', { id: annId }, function(res) {
      markedCount++;
      
      if (res === 'OK') {
        $element.fadeOut(300, function() { $(this).remove(); });
      } else {
        errors.push('Announcement ' + annId + ': ' + res);
      }
      
      // Check if all announcements have been processed
      if (markedCount === totalAnnouncements) {
        btn.prop('disabled', false).html('<i class="bi bi-check-all me-1"></i>Mark All as Read');
        
        // Check if there are any remaining announcements
        var remainingAnnouncements = $('#student-announcements-content .list-group-item').length;
        
        if (remainingAnnouncements === 0) {
          // No more announcements, show "All Caught Up!" message
          $('#student-announcements-content').html('<div class="text-center py-5"><i class="bi bi-megaphone text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
          
          // Only hide badges when ALL announcements are read
          console.log('🚫 All student announcements marked as read - hiding notification badges');
          hideAllStudentNotificationBadges();
        }
        
        // Update tab badges after marking all as read
        updateTabBadgesFromModalContent();
        
        // Show results
        if (errors.length > 0) {
          alert('Marked ' + (totalAnnouncements - errors.length) + ' announcements as read. Errors: ' + errors.join(', '));
        } else {
          alert('Successfully marked all ' + totalAnnouncements + ' announcements as read!');
        }
      }
    }).fail(function(xhr, status, error) {
      markedCount++;
      errors.push('Announcement ' + annId + ': ' + error);
      
      if (markedCount === totalAnnouncements) {
        btn.prop('disabled', false).html('<i class="bi bi-check-all me-1"></i>Mark All as Read');
        alert('Some errors occurred while marking announcements as read. Errors: ' + error);
      }
    });
  });
});
</script>
<!-- Bootstrap Bundle JS (for dropdowns, modals, etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Enhanced dropdown initialization that works on all pages
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking dropdowns...');
    initializeDropdowns();
});

$(document).ready(function() {
    console.log('jQuery ready, initializing dropdowns...');
    initializeDropdowns();
});

function initializeDropdowns() {
    // Wait for Bootstrap to be available
    var checkBootstrap = setInterval(function() {
        if (typeof bootstrap !== 'undefined') {
            clearInterval(checkBootstrap);
            console.log('Bootstrap available, initializing dropdowns...');
            
            // Initialize Bootstrap dropdowns
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            console.log('Found dropdown elements:', dropdownElementList.length);
            
            dropdownElementList.forEach(function(dropdownToggleEl, index) {
                try {
                    // Check if dropdown is already initialized
                    var existingDropdown = bootstrap.Dropdown.getInstance(dropdownToggleEl);
                    if (!existingDropdown) {
                        var dropdown = new bootstrap.Dropdown(dropdownToggleEl, {
                            autoClose: true
                        });
                        console.log('✅ Initialized dropdown ' + index + ':', dropdownToggleEl.id || dropdownToggleEl.textContent.trim());
                    } else {
                        console.log('ℹ️ Dropdown ' + index + ' already initialized');
                    }
                } catch (error) {
                    console.error('❌ Error initializing dropdown ' + index + ':', error);
                }
            });
        }
    }, 50);
    
    // Fallback: Manual dropdown functionality
    setTimeout(function() {
        console.log('Setting up fallback dropdown functionality...');
        
        // Remove any existing click handlers
        $('.dropdown-toggle').off('click.dropdown-fallback');
        
        // Add fallback click handlers
        $('.dropdown-toggle').on('click.dropdown-fallback', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $dropdown = $(this).closest('.dropdown');
            var $menu = $dropdown.find('.dropdown-menu');
            
            // Close other dropdowns
            $('.dropdown-menu').not($menu).removeClass('show');
            
            // Toggle current dropdown
            $menu.toggleClass('show');
            
            console.log('Fallback dropdown toggle for:', this.id || this.textContent.trim());
        });
        
        // Close dropdowns when clicking outside
        $(document).off('click.dropdown-outside').on('click.dropdown-outside', function(e) {
            if (!$(e.target).closest('.dropdown').length) {
                $('.dropdown-menu').removeClass('show');
            }
        });
        
        // Close dropdowns when pressing Escape
        $(document).off('keydown.dropdown-escape').on('keydown.dropdown-escape', function(e) {
            if (e.key === 'Escape') {
                $('.dropdown-menu').removeClass('show');
            }
        });
    }, 500);
}

// Re-initialize dropdowns when page content changes (for dynamic content)
$(document).on('shown.bs.modal hidden.bs.modal', function() {
    setTimeout(initializeDropdowns, 100);
});
</script>
<script>
// Load announcements when modal opens
$(document).on('show.bs.modal', '#announcementModal', function() {
  console.log('Announcement modal opening, loading content...');
  
  // Show loading state
  var modalBody = $('#announcementModal .modal-body');
  modalBody.html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading announcements...</p></div>');
  
  // Load announcements without hiding badges
  $.get('<?php echo SITE_URL; ?>/navbar_announcements.php', function(data) {
    console.log('Announcement data received:', data);
    
    try {
      // Check if data is already an object (jQuery auto-parses JSON)
      var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
      console.log('Parsed announcements:', parsed);
      
      if (parsed.count === 0) {
        modalBody.html('<div class="text-center py-5"><i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
      } else {
        var html = '<ul class="list-group list-group-flush">';
        parsed.announcements.forEach(function(ann) {
          html += '<li class="list-group-item" id="ann-row-'+ann.id+'">';
          html += '<div class="d-flex justify-content-between align-items-center">';
          html += '<div><strong>'+ann.title+'</strong><div class="text-muted small">'+ann.created_at+'</div></div>';
          html += '<button class="btn btn-sm btn-outline-primary view-ann-details" data-ann-id="'+ann.id+'" data-ann-title="'+ann.title+'" data-ann-content="'+ann.content+'" data-ann-author="'+ann.author_name+'" data-ann-context="'+ann.context+'"><i class="bi bi-eye"></i> View Details</button>';
          html += '<button class="btn btn-sm btn-outline-success mark-ann-read" data-ann-id="'+ann.id+'">Mark as Read</button>';
          html += '</div>';
          html += '</div><div class="mt-1 text-muted small">'+ann.preview+'</div></li>';
        });
        html += '</ul>';
        modalBody.html(html);
      }
      
      // Update tab badges based on actual content after loading
      setTimeout(function() {
        updateTabBadgesFromModalContent();
      }, 500);
    } catch (e) {
      console.error('Error parsing announcement data:', e);
      modalBody.html('<div class="alert alert-danger">Error loading announcements: ' + e.message + '</div>');
    }
  }).fail(function(xhr, status, error) {
    console.error('AJAX Error loading announcements:', status, error);
    modalBody.html('<div class="alert alert-danger">Error loading announcements: ' + error + '</div>');
  });
});

// Load teacher notifications when modal opens
$(document).on('show.bs.modal', '#teacherNotificationModal', function() {
  console.log('Teacher notification modal opening, loading content...');
  
  // Load announcements first since it's the default active tab
  loadTeacherAnnouncements();
  
  // Update tab badges based on actual content after loading
  setTimeout(function() {
    updateTabBadgesFromModalContent();
  }, 1000);
});



// Function to load teacher announcements
function loadTeacherAnnouncements() {
  $.get('<?php echo SITE_URL; ?>/navbar_announcements.php', function(data) {
    console.log('Teacher announcement data received:', data);
    
    try {
      var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
      var announcementContent = $('#teacher-announcements-content');
      var announcementCount = parsed.count || 0;
      
      if (announcementCount > 0) {
        var html = '<ul class="list-group list-group-flush">';
        parsed.announcements.forEach(function(ann) {
          html += '<li class="list-group-item" id="ann-row-'+ann.id+'">';
          html += '<div class="d-flex justify-content-between align-items-center">';
          html += '<div><strong>'+ann.title+'</strong><div class="text-muted small">'+ann.created_at+'</div></div>';
          html += '<div class="btn-group" role="group">';
          html += '<button class="btn btn-sm btn-outline-primary view-ann-details" data-ann-id="'+ann.id+'" data-ann-title="'+ann.title+'" data-ann-content="'+ann.content+'" data-ann-author="'+ann.author_name+'" data-ann-context="'+ann.context+'"><i class="bi bi-eye"></i> View Details</button>';
          html += '<button class="btn btn-sm btn-outline-success mark-ann-read" data-ann-id="'+ann.id+'">Mark as Read</button>';
          html += '</div>';
          html += '</div><div class="mt-1 text-muted small">'+ann.preview+'</div></li>';
        });
        html += '</ul>';
        announcementContent.html(html);
      } else {
        announcementContent.html('<div class="text-center py-5"><i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
      }
      
      // Update tab badges with actual content counts
      updateTabBadgesFromModalContent();
      
    } catch (e) {
      console.error('Error parsing teacher announcement data:', e);
      $('#teacher-announcements-content').html('<div class="alert alert-danger">Error loading announcements: ' + e.message + '</div>');
    }
  }).fail(function(xhr, status, error) {
    console.error('AJAX Error loading teacher announcements:', status, error);
    $('#teacher-announcements-content').html('<div class="alert alert-danger">Error loading announcements: ' + error + '</div>');
  });
}

// Function to load student enrollment notifications
function loadStudentEnrollmentNotifications() {
  console.log('Loading student enrollment notifications...');
  
  $.get('<?php echo SITE_URL; ?>/student/get_notifications.php', function(data) {
    console.log('Student enrollment notifications data:', data);
    
    try {
      if (data.success && data.enrollment_notifications) {
        var notifications = data.enrollment_notifications;
        var enrollmentList = $('#student-enrollment-list');
        
        if (notifications.length > 0) {
          var html = '';
          notifications.forEach(function(notif) {
            var iconClass = '';
            var statusClass = '';
            var statusText = '';
            
            if (notif.type === 'course_kicked') {
              iconClass = 'bi-person-x text-danger';
              statusClass = 'text-danger';
              statusText = 'Removed from Course';
            } else if (notif.type === 'enrollment_approved') {
              iconClass = 'bi-check-circle text-success';
              statusClass = 'text-success';
              statusText = 'Approved';
            } else if (notif.type === 'enrollment_rejected') {
              iconClass = 'bi-x-circle text-danger';
              statusClass = 'text-danger';
              statusText = 'Rejected';
            }
            
            html += `
              <div class="list-group-item border-0 border-bottom" id="enrollment-notif-${notif.id}">
                <div class="d-flex align-items-start">
                  <div class="flex-shrink-0 me-3">
                    <i class="bi ${iconClass}" style="font-size: 1.5rem;"></i>
                  </div>
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <h6 class="mb-1">${notif.title}</h6>
                        <p class="mb-1 text-muted">${notif.message}</p>
                        <small class="text-muted">
                          <i class="bi bi-clock me-1"></i>${notif.created_at}
                        </small>
                      </div>
                      <div class="text-end">
                        <span class="badge ${statusClass}">${statusText}</span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="markEnrollmentNotificationRead(${notif.id})" title="Mark as Read">
                          <i class="bi bi-check"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            `;
          });
          
          enrollmentList.html(html);
        } else {
          enrollmentList.html(`
            <div class="text-center py-5">
              <i class="bi bi-person-check text-success" style="font-size: 3rem;"></i>
              <h5 class="mt-3 text-muted">All Caught Up!</h5>
              <p class="text-muted">No unread enrollment notifications at this time.</p>
            </div>
          `);
        }
        
        // Update enrollment tab badge
        updateEnrollmentTabBadge(notifications.length);
      } else {
        $('#student-enrollment-list').html('<div class="alert alert-danger">Error loading enrollment notifications</div>');
      }
    } catch (e) {
      console.error('Error parsing student enrollment notification data:', e);
      $('#student-enrollment-list').html('<div class="alert alert-danger">Error loading enrollment notifications: ' + e.message + '</div>');
    }
  }).fail(function(xhr, status, error) {
    console.error('AJAX Error loading student enrollment notifications:', status, error);
    $('#student-enrollment-list').html('<div class="alert alert-danger">Error loading enrollment notifications: ' + error + '</div>');
  });
}

// Function to mark enrollment notification as read
function markEnrollmentNotificationRead(notificationId) {
  console.log('Marking enrollment notification as read:', notificationId);
  
  $.post('<?php echo SITE_URL; ?>/student/mark_notification_read.php', {
    notification_id: notificationId
  }, function(response) {
    console.log('Mark enrollment notification response:', response);
    
    if (response.success) {
      // Remove the notification from the list
      $('#enrollment-notif-' + notificationId).fadeOut(300, function() {
        $(this).remove();
        
        // Check if any enrollment notifications remain
        var remainingNotifications = $('#student-enrollment-list .list-group-item').length;
        
        if (remainingNotifications === 0) {
          $('#student-enrollment-list').html(`
            <div class="text-center py-5">
              <i class="bi bi-person-check text-success" style="font-size: 3rem;"></i>
              <h5 class="mt-3 text-muted">All Caught Up!</h5>
              <p class="text-muted">No unread enrollment notifications at this time.</p>
            </div>
          `);
        }
        
        // Update tab badges and notification count
        updateEnrollmentTabBadge(remainingNotifications);
        updateStudentNotificationCount();
        updateNavbarBadgeCount();
      });
    } else {
      console.error('Error marking enrollment notification as read:', response.error);
      alert('Error marking notification as read: ' + response.error);
    }
  }).fail(function(xhr, status, error) {
    console.error('AJAX Error marking enrollment notification as read:', status, error);
    alert('Error marking notification as read: ' + error);
  });
}

// Function to update enrollment tab badge
function updateEnrollmentTabBadge(count) {
  var badge = $('.enrollment-count-badge');
  if (count > 0) {
    badge.text(count).show();
  } else {
    badge.hide();
  }
}

// Load student notifications when modal opens
$(document).on('show.bs.modal', '#studentNotificationModal', function() {
  console.log('Student notification modal opening, loading content...');
  
  // Show loading state
  $('#student-notification-loading').show();
  $('#studentNotificationTabContent').hide();
  
  // Load both enrollment notifications and announcements
  loadStudentEnrollmentNotifications();
  loadStudentAnnouncements();
  
  // Hide loading and show content
  setTimeout(function() {
    $('#student-notification-loading').hide();
    $('#studentNotificationTabContent').show();
    
    // Update notification count in modal title
    updateStudentNotificationCount();
    
    // Update tab badges based on actual content after loading
    updateTabBadgesFromModalContent();
  }, 500);
});



// Function to update navbar badge count in real-time
function updateNavbarBadgeCount(count) {
  var notificationBell = $('#navbarAnnounceDropdown');
  var badge = notificationBell.find('.badge');
  
  if (count > 0) {
    if (badge.length) {
      // Update existing badge count
      badge.text(count).show();
    } else {
      // Create new badge with count
      notificationBell.append('<span class="badge bg-danger">' + count + '</span>');
    }
  } else {
    // Hide badge but keep bell icon visible
    if (badge.length) {
      badge.hide();
    }
  }
  
  console.log('🔄 Navbar badge count updated to:', count + ' (bell icon preserved)');
}

// Function to show alerts
function showAlert(type, message) {
  // Remove any existing alerts
  $('.alert').remove();
  
  // Create alert HTML
  var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                  message +
                  '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                  '</div>';
  
  // Add alert to the top of the page
  $('body').prepend(alertHtml);
  
  // Auto-remove after 5 seconds
  setTimeout(function() {
    $('.alert').fadeOut();
  }, 5000);
}

// Function to mark student notifications as viewed (only called when actually marking as read)
function markStudentNotificationsAsViewed() {
  if (typeof window.currentUserId === 'undefined') {
    console.warn('⚠️ Current user ID not available');
    return;
  }
  
  console.log('👁️ Marking student notifications as viewed...');
  
  $.post('<?php echo SITE_URL; ?>/student/mark_notifications_viewed.php', {
    student_id: window.currentUserId
  }, function(response) {
    console.log('✅ Notifications marked as viewed:', response);
    
    // Update badge count immediately (like teacher system)
    refreshStudentNotificationBadge();
    
  }).fail(function(xhr, status, error) {
    console.warn('❌ Error marking notifications as viewed:', error);
  });
}



// Function to load student announcements
function loadStudentAnnouncements() {
  $.get('<?php echo SITE_URL; ?>/navbar_announcements.php', function(data) {
    console.log('Student announcement data received:', data);
    
    try {
      var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
      var announcementContent = $('#student-announcements-content');
      
      var announcementCount = parsed.count || 0;
      
      if (announcementCount > 0) {
        var html = '<ul class="list-group list-group-flush">';
        parsed.announcements.forEach(function(ann) {
          html += '<li class="list-group-item" id="ann-row-'+ann.id+'">';
          html += '<div class="d-flex justify-content-between align-items-center">';
          html += '<div><strong>'+ann.title+'</strong><div class="text-muted small">'+ann.created_at+'</div></div>';
          html += '<div class="btn-group" role="group">';
          html += '<button class="btn btn-sm btn-outline-primary view-ann-details" data-ann-id="'+ann.id+'" data-ann-title="'+ann.title+'" data-ann-content="'+ann.content+'" data-ann-author="'+ann.author_name+'" data-ann-context="'+ann.context+'"><i class="bi bi-eye"></i> View Details</button>';
          html += '<button class="btn btn-sm btn-outline-success mark-ann-read" data-ann-id="'+ann.id+'">Mark as Read</button>';
          html += '</div>';
          html += '</div><div class="mt-1 text-muted small">'+ann.preview+'</div></li>';
        });
        html += '</ul>';
        announcementContent.html(html);
      } else {
        announcementContent.html('<div class="text-center py-5"><i class="bi bi-megaphone text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
        
        // Don't hide badges when just loading content for display
        // Badges will only be hidden when announcements are actually marked as read
        console.log('ℹ️ No announcements to display');
      }
      
      // Update tab badges with actual content counts
      updateTabBadgesFromModalContent();
    } catch (e) {
      console.error('Error parsing student announcement data:', e);
      $('#student-announcements-content').html('<div class="alert alert-danger">Error loading announcements: ' + e.message + '</div>');
    }
  }).fail(function(xhr, status, error) {
    console.error('AJAX Error loading student announcements:', status, error);
    $('#student-announcements-content').html('<div class="alert alert-danger">Error loading announcements: ' + error + '</div>');
  });
}

// Notification polling (every 30 seconds)
function refreshNotifications() {
  // Get current user role
  var userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
  
  if (userRole === 'teacher') {
    // Refresh teacher notifications (announcements only)
    $.get('<?php echo SITE_URL; ?>/navbar_announcements.php', function(announcementData) {
      var announcementParsed = (typeof announcementData === 'string') ? JSON.parse(announcementData) : announcementData;
      
      // Get announcements count
      var announcementCount = announcementParsed.count || 0;
      
      var badge = $('#navbarAnnounceDropdown .badge');
      
      if (announcementCount > 0) {
        if (badge.length) {
          badge.text(announcementCount);
        } else {
          $('#navbarAnnounceDropdown').append('<span class="badge bg-danger">'+announcementCount+'</span>');
        }
      } else {
        badge.remove();
      }
      
      // Update modal content only if teacher notification modal is not currently open
      var teacherNotificationModal = $('#teacherNotificationModal');
      if (!teacherNotificationModal.hasClass('show')) {
        // Update announcements tab content
        if (announcementCount > 0) {
          var announcementHtml = '<ul class="list-group list-group-flush">';
          announcementParsed.announcements.forEach(function(ann) {
            announcementHtml += '<li class="list-group-item" id="ann-row-'+ann.id+'">';
            announcementHtml += '<div class="d-flex justify-content-between align-items-center">';
            announcementHtml += '<div><strong>'+ann.title+'</strong><div class="text-muted small">'+ann.created_at+'</div></div>';
            announcementHtml += '<div class="btn-group" role="group">';
            announcementHtml += '<button class="btn btn-sm btn-outline-primary view-ann-details" data-ann-id="'+ann.id+'" data-ann-title="'+ann.title+'" data-ann-content="'+ann.content+'" data-ann-author="'+ann.author_name+'" data-ann-context="'+ann.context+'"><i class="bi bi-eye"></i> View Details</button>';
            announcementHtml += '<button class="btn btn-sm btn-outline-success mark-ann-read" data-ann-id="'+ann.id+'">Mark as Read</button>';
            announcementHtml += '</div>';
            announcementHtml += '</div><div class="mt-1 text-muted small">'+ann.preview+'</div></li>';
          });
          announcementHtml += '</ul>';
          $('#teacher-announcements-content').html(announcementHtml);
        } else {
          $('#teacher-announcements-content').html('<div class="text-center py-5"><i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
        }
      }
    });
  } else if (userRole === 'student') {
    // Refresh student notifications (announcements only)
    $.get('<?php echo SITE_URL; ?>/navbar_announcements.php', function(announcementData) {
      var announcementParsed = (typeof announcementData === 'string') ? JSON.parse(announcementData) : announcementData;
      
      // Get announcements count
      var announcementCount = announcementParsed.count || 0;
      
      var badge = $('#navbarAnnounceDropdown .badge');
      
      if (announcementCount > 0) {
        if (badge.length) {
          badge.text(announcementCount);
        } else {
          $('#navbarAnnounceDropdown').append('<span class="badge bg-danger">'+announcementCount+'</span>');
        }
      } else {
        badge.remove();
      }
      
      // Update modal content only if student notification modal is not currently open
      var studentNotificationModal = $('#studentNotificationModal');
      if (!studentNotificationModal.hasClass('show')) {
        // Update announcements tab content
        if (announcementCount > 0) {
          var announcementHtml = '<ul class="list-group list-group-flush">';
          announcementParsed.announcements.forEach(function(ann) {
            announcementHtml += '<li class="list-group-item" id="ann-row-'+ann.id+'">';
            announcementHtml += '<div class="d-flex justify-content-between align-items-center">';
            announcementHtml += '<div><strong>'+ann.title+'</strong><div class="text-muted small">'+ann.created_at+'</div></div>';
            announcementHtml += '<div class="btn-group" role="group">';
            announcementHtml += '<button class="btn btn-sm btn-outline-primary view-ann-details" data-ann-id="'+ann.id+'" data-ann-title="'+ann.title+'" data-ann-content="'+ann.content+'" data-ann-author="'+ann.author_name+'" data-ann-context="'+ann.context+'"><i class="bi bi-eye"></i> View Details</button>';
            announcementHtml += '<button class="btn btn-sm btn-outline-success mark-ann-read" data-ann-id="'+ann.id+'">Mark as Read</button>';
            announcementHtml += '</div>';
            announcementHtml += '</div><div class="mt-1 text-muted small">'+ann.preview+'</div></li>';
          });
          announcementHtml += '</ul>';
          $('#student-announcements-content').html(announcementHtml);
        } else {
          $('#student-announcements-content').html('<div class="text-center py-5"><i class="bi bi-megaphone text-success" style="font-size: 3rem;"></i><h5 class="mt-3 text-muted">All Caught Up!</h5><p class="text-muted">No unread announcements at this time.</p></div>');
        }
      }
    });
  } else {
    // Refresh admin announcements
    $.get('<?php echo SITE_URL; ?>/navbar_announcements.php', function(data) {
      // Check if data is already an object (jQuery auto-parses JSON)
      var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
      var badge = $('#navbarAnnounceDropdown .badge');
      if (parsed.count > 0) {
        if (badge.length) {
          badge.text(parsed.count);
        } else {
          $('#navbarAnnounceDropdown').append('<span class="badge bg-danger">'+parsed.count+'</span>');
        }
      } else {
        badge.remove();
      }
      // Update modal content only if announcement modal is not currently open
      var announcementModal = $('#announcementModal');
      if (!announcementModal.hasClass('show')) {
        var modalBody = $('#announcementModal .modal-body');
        if (parsed.count === 0) {
          modalBody.html('<p class="text-muted">No unread announcements.</p>');
        } else {
          var html = '<ul class="list-group list-group-flush">';
          parsed.announcements.forEach(function(ann) {
            html += '<li class="list-group-item" id="ann-row-'+ann.id+'">';
            html += '<div class="text-muted small">'+ann.created_at+'</div>';
            html += '<div class="mt-1 text-muted small">'+ann.preview+'</div></li>';
          });
          html += '</ul>';
          modalBody.html(html);
        }
      }
    });
  }
}
setInterval(refreshNotifications, 30000);

// Pusher Configuration
<?php
require_once __DIR__ . '/../config/pusher.php';
$pusherConfig = PusherConfig::getConfig();
?>
window.pusherConfig = <?php echo json_encode($pusherConfig); ?>;
window.currentUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
window.currentUserRole = <?php echo isset($_SESSION['role']) ? json_encode($_SESSION['role']) : 'null'; ?>;

// Load Pusher client
$(document).ready(function() {
    if (typeof Pusher !== 'undefined') {
        $.getScript('<?php echo SITE_URL; ?>/assets/js/pusher-client.js');
    }
});

// Real-time notification count refresh (every 10 seconds for accuracy)
function startRealTimeNotificationRefresh() {
  setInterval(function() {
    if (document.visibilityState === 'visible' && typeof window.currentUserRole !== 'undefined' && window.currentUserRole === 'student') {
      refreshStudentNotificationCount();
    }
  }, 10000); // Refresh every 10 seconds
}

// Function to refresh student notification count in real-time
function refreshStudentNotificationCount() {
  $.get('<?php echo SITE_URL; ?>/ajax_get_student_enrollment_requests.php', function(data) {
    try {
      var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
      if (parsed.success) {
        var totalCount = parsed.pending_count || 0;
        
        // Use the new real-time update function that preserves bell icon
        if (typeof updateBadgeCountRealtime === 'function') {
          updateBadgeCountRealtime(totalCount);
        } else {
          // Fallback to old method
          updateNavbarBadgeCount(totalCount);
        }
        
        console.log('🔄 Real-time notification count refresh:', totalCount + ' (bell icon preserved)');
      }
    } catch (e) {
      console.error('Error parsing real-time notification count:', e);
    }
  }).fail(function(xhr, status, error) {
    console.warn('Real-time notification count refresh failed:', error);
  });
}

// Start real-time refresh when page loads
$(document).ready(function() {
  startRealTimeNotificationRefresh();
  
  // Start badge prevention to ensure badges don't reappear
  preventBadgeReappearing();
});

// Function to remove enrollment requests badge immediately
function removeEnrollmentRequestsBadge() {
  const enrollmentRequestsLink = document.getElementById('student-enrollment-requests-link');
  if (enrollmentRequestsLink) {
    // Remove the badge container
    const existingBadge = enrollmentRequestsLink.querySelector('.position-relative');
    if (existingBadge) {
      existingBadge.remove();
      console.log('✅ Removed enrollment requests badge');
    }
    
    // Also remove any red dots that might be directly on the link
    const redDots = enrollmentRequestsLink.querySelectorAll('.red-dot');
    redDots.forEach(dot => {
      dot.remove();
      console.log('✅ Removed red dot from enrollment requests link');
    });
  }
}

// Function to remove navbar badge (bell icon)
function removeNavbarBadge() {
  const notificationBell = document.getElementById('navbarAnnounceDropdown');
  if (notificationBell) {
    const badge = notificationBell.querySelector('.badge');
    if (badge) {
      // Only remove the badge, keep the bell icon
      badge.remove();
      console.log('✅ Removed navbar badge (bell icon preserved)');
    }
  }
}

// Function to hide notification bell badge (when no notifications)
function hideNotificationBellBadge() {
  const notificationBell = document.getElementById('navbarAnnounceDropdown');
  if (notificationBell) {
    const badge = notificationBell.querySelector('.badge');
    if (badge) {
      // Only hide the badge count, keep the bell icon visible
      badge.style.display = 'none';
      console.log('✅ Hidden notification bell badge count (bell icon preserved)');
    }
  }
}

// Function to refresh student notification badge count
function refreshStudentNotificationBadge() {
  $.get('<?php echo SITE_URL; ?>/ajax_get_student_enrollment_requests.php', function(data) {
    try {
      var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
      if (parsed.success) {
        var totalCount = parsed.pending_count || 0;
        
        console.log('🔄 Refreshed student notification badge count:', totalCount);
        
        // If no notifications, hide the badge completely (like teacher system)
        if (totalCount === 0) {
          hideNotificationBellBadge();
          removeEnrollmentRequestsBadge();
          console.log('✅ No notifications - badges hidden');
        } else {
          // Update the navbar badge count
          updateNavbarBadgeCount(totalCount);
          console.log('✅ Badge count updated to:', totalCount);
        }
      }
    } catch (e) {
      console.error('Error parsing student notification badge count:', e);
    }
  }).fail(function(xhr, status, error) {
    console.warn('Student notification badge refresh failed:', error);
  });
}

// Function to prevent badge from reappearing by checking periodically
function preventBadgeReappearing() {
  setInterval(function() {
    // Only check if user is on a student page
    if (document.getElementById('student-enrollment-requests-link')) {
      $.get('<?php echo SITE_URL; ?>/ajax_get_student_enrollment_requests.php', function(data) {
        try {
          var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
          if (parsed.success) {
            var totalCount = parsed.pending_count || 0;
            
            // If no notifications, ensure ALL badges are completely hidden
            if (totalCount === 0) {
              hideAllStudentNotificationBadges();
            }
          }
        } catch (e) {
          // Ignore parsing errors
        }
      });
    }
  }, 10000); // Check every 10 seconds (like teacher system)
}

// ===== MISSING FUNCTIONS FOR TEACHER ENROLLMENT REQUESTS =====

// Function to show red dot in navbar (called by teacher enrollment requests)
function showNavbarRedDot() {
  console.log('🔴 Showing navbar red dot...');
  
  // Show red dot on enrollment requests link for teachers
  const teacherEnrollmentLink = document.getElementById('teacher-enrollment-requests-link');
  if (teacherEnrollmentLink) {
    // Remove existing red dot if any
    const existingRedDot = teacherEnrollmentLink.querySelector('.position-relative');
    if (existingRedDot) {
      existingRedDot.remove();
    }
    
    // Add new red dot
    const redDotContainer = document.createElement('div');
    redDotContainer.className = 'position-relative';
    redDotContainer.innerHTML = '<span class="red-dot"></span>';
    
    teacherEnrollmentLink.appendChild(redDotContainer);
    console.log('✅ Teacher enrollment requests red dot shown');
  }
  
  // Also show red dot on notification bell for teachers
  const notificationBell = document.getElementById('navbarAnnounceDropdown');
  if (notificationBell) {
    // Remove existing badge if any
    const existingBadge = notificationBell.querySelector('.badge');
    if (existingBadge) {
      existingBadge.remove();
    }
    
    // Add red dot
    const redDotContainer = document.createElement('div');
    redDotContainer.className = 'position-relative';
    redDotContainer.innerHTML = '<span class="red-dot"></span>';
    
    notificationBell.appendChild(redDotContainer);
    console.log('✅ Teacher notification bell red dot shown');
  }
}

// Function to update navbar enrollment badge (called by teacher enrollment requests)
function updateNavbarEnrollmentBadge() {
  console.log('🔄 Updating navbar enrollment badge...');
  
  // For teachers, update the enrollment requests badge count
  if (window.currentUserRole === 'teacher') {
    // Get enrollment requests count via AJAX
    $.get('<?php echo SITE_URL; ?>/ajax_get_enrollment_requests.php', function(data) {
      try {
        var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
        if (parsed.success) {
          var count = parsed.pending_count || 0;
          
          // Update or create badge on enrollment requests link
          const teacherEnrollmentLink = document.getElementById('teacher-enrollment-requests-link');
          if (teacherEnrollmentLink) {
            // Remove existing badge
            const existingBadge = teacherEnrollmentLink.querySelector('.badge');
            if (existingBadge) {
              existingBadge.remove();
            }
            
            // Add new badge with count
            if (count > 0) {
              const badgeContainer = document.createElement('div');
              badgeContainer.className = 'position-relative';
              badgeContainer.innerHTML = '<span class="badge bg-danger">' + count + '</span>';
              teacherEnrollmentLink.appendChild(badgeContainer);
              console.log('✅ Teacher enrollment requests badge updated:', count);
            }
          }
          
          // Update notification bell badge
          const notificationBell = document.getElementById('navbarAnnounceDropdown');
          if (notificationBell) {
            // Remove existing badge
            const existingBadge = notificationBell.querySelector('.badge');
            if (existingBadge) {
              existingBadge.remove();
            }
            
            // Add new badge with count
            if (count > 0) {
              notificationBell.append('<span class="badge bg-danger">' + count + '</span>');
              console.log('✅ Teacher notification bell badge updated:', count);
            }
          }
        }
      } catch (e) {
        console.error('Error parsing enrollment requests count:', e);
      }
    }).fail(function(xhr, status, error) {
      console.warn('Failed to get enrollment requests count:', error);
    });
  }
}

// Function to update enrollment requests UI (called by teacher enrollment requests)
function updateEnrollmentRequestsUI() {
  console.log('🔄 Updating enrollment requests UI...');
  
  if (window.currentUserRole === 'teacher') {
    // Reload the page to show updated data
    location.reload();
  }
  
  return Promise.resolve();
}

// ===== IMPROVED STUDENT NOTIFICATION BADGE SYSTEM =====

// Enhanced function to completely hide student notification badges
function hideAllStudentNotificationBadges() {
  console.log('🚫 Hiding all student notification badges...');
  
  // Hide the notification bell badge count
  hideNotificationBellBadge();
  
  // Remove enrollment requests badge
  removeEnrollmentRequestsBadge();
  
  // Hide all red dots from notification elements
  hideAllRedDots();
  
  console.log('✅ All student notification badges and red dots completely hidden');
}

// Enhanced function to refresh student notification badge count
function refreshStudentNotificationBadge() {
  $.get('<?php echo SITE_URL; ?>/ajax_get_student_enrollment_requests.php', function(data) {
    try {
      var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
      if (parsed.success) {
        var totalCount = parsed.pending_count || 0;
        
        console.log('🔄 Refreshed student notification badge count:', totalCount);
        
        // If no notifications, hide ALL badges completely
        if (totalCount === 0) {
          hideAllStudentNotificationBadges();
          console.log('✅ No notifications - all badges completely hidden');
        } else {
          // Update the navbar badge count
          updateNavbarBadgeCount(totalCount);
          console.log('✅ Badge count updated to:', totalCount);
        }
      }
    } catch (e) {
      console.error('Error parsing student notification badge count:', e);
    }
  }).fail(function(xhr, status, error) {
    console.warn('Student notification badge refresh failed:', error);
  });
}

// Enhanced function to prevent badge reappearing
function preventBadgeReappearing() {
  setInterval(function() {
    // Only check if user is on a student page
    if (document.getElementById('student-enrollment-requests-link')) {
      $.get('<?php echo SITE_URL; ?>/ajax_get_student_enrollment_requests.php', function(data) {
        try {
          var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
          if (parsed.success) {
            var totalCount = parsed.pending_count || 0;
            
            // If no notifications, ensure ALL badges are completely hidden
            if (totalCount === 0) {
              hideAllStudentNotificationBadges();
            }
          }
        } catch (e) {
          // Ignore parsing errors
        }
      });
    }
  }, 10000); // Check every 10 seconds
}

// Function to preserve bell icon while updating badge count
function preserveBellIcon() {
  console.log('🔔 Preserving bell icon while updating badge...');
  
  const notificationBell = document.getElementById('navbarAnnounceDropdown');
  if (notificationBell) {
    // Ensure the bell icon container is always visible
    notificationBell.style.display = 'block';
    notificationBell.style.visibility = 'visible';
    
    // Get current badge
    const badge = notificationBell.querySelector('.badge');
    
    // If there's a badge, ensure it's properly positioned but don't remove the bell
    if (badge) {
      badge.style.position = 'absolute';
      badge.style.top = '0';
      badge.style.left = '100%';
      badge.style.transform = 'translate(-50%, -50%)';
      console.log('✅ Bell icon preserved, badge positioned correctly');
    } else {
      console.log('✅ Bell icon preserved, no badge to position');
    }
  }
}

// Enhanced real-time badge update function that preserves bell icon
function updateBadgeCountRealtime(count) {
  console.log('🔄 Updating badge count in real-time:', count);
  
  const notificationBell = document.getElementById('navbarAnnounceDropdown');
  if (notificationBell) {
    let badge = notificationBell.querySelector('.badge');
    
    if (count > 0) {
      if (badge) {
        // Update existing badge count
        badge.textContent = count;
        badge.style.display = 'block';
      } else {
        // Create new badge with count
        badge = document.createElement('span');
        badge.className = 'badge bg-danger';
        badge.textContent = count;
        notificationBell.appendChild(badge);
      }
      console.log('✅ Badge count updated to:', count);
    } else {
      // Hide badge but keep bell icon visible
      if (badge) {
        badge.style.display = 'none';
      }
      console.log('✅ Badge hidden, bell icon preserved');
    }
  }
  
  // Also update enrollment requests badge if it exists
  const enrollmentLink = document.getElementById('student-enrollment-requests-link');
  if (enrollmentLink) {
    let enrollmentBadge = enrollmentLink.querySelector('.position-relative');
    
    if (count > 0) {
      if (enrollmentBadge) {
        // Update existing badge
        const badgeSpan = enrollmentBadge.querySelector('.badge');
        if (badgeSpan) {
          badgeSpan.textContent = count;
        }
      } else {
        // Create new badge
        enrollmentBadge = document.createElement('div');
        enrollmentBadge.className = 'position-relative';
        enrollmentBadge.innerHTML = '<span class="badge bg-danger">' + count + '</span>';
        enrollmentLink.appendChild(enrollmentBadge);
      }
    } else {
      // Remove enrollment badge completely
      if (enrollmentBadge) {
        enrollmentBadge.remove();
      }
    }
  }
}

// Function to protect bell icon from being removed
function protectBellIcon() {
  const notificationBell = document.getElementById('navbarAnnounceDropdown');
  if (notificationBell) {
    // Ensure bell icon is always visible and never removed
    notificationBell.style.display = 'block';
    notificationBell.style.visibility = 'visible';
    notificationBell.style.opacity = '1';
    
    // If bell icon was accidentally removed, restore it
    if (!notificationBell.innerHTML.includes('bi-bell')) {
      console.log('⚠️ Bell icon content was removed, restoring...');
      notificationBell.innerHTML = '<i class="bi bi-bell"></i> Notifications';
    }
    
    console.log('🔒 Bell icon protected and preserved');
  }
}

// Function to ensure bell icon is never removed (called periodically)
function ensureBellIconNeverRemoved() {
  setInterval(function() {
    protectBellIcon();
  }, 5000); // Check every 5 seconds
}

// Check notifications on page load
$(document).ready(function() {
  // Wait a bit for the page to fully load
  setTimeout(function() {
    checkAndHideRedDotIfNoNotifications();
  }, 1000);
});

// Function to check and hide red dot if no notifications exist
function checkAndHideRedDotIfNoNotifications() {
  // Only run for students
  if (window.currentUserRole !== 'student') {
    return;
  }
  
  console.log('🔍 Checking if red dot should be hidden (no notifications)...');
  
  // Check enrollment notifications
  $.get('<?php echo SITE_URL; ?>/student/get_notifications.php', function(data) {
    try {
      var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
      if (parsed.success) {
        var totalCount = parsed.total_count || 0;
        
        console.log('📊 Total notification count on page load:', totalCount);
        
        // If no notifications, hide the red dot and badge
        if (totalCount === 0) {
          console.log('🚫 No notifications found - hiding red dot and badge');
          hideAllStudentNotificationBadges();
          
          // Also ensure the notification bell badge is completely removed
          const notificationBell = document.getElementById('navbarAnnounceDropdown');
          if (notificationBell) {
            const badge = notificationBell.querySelector('.badge');
            if (badge) {
              badge.remove();
              console.log('✅ Notification bell badge removed (no notifications on page load)');
            }
            
          }
          
          // Use the comprehensive function to hide all red dots
          hideAllRedDots();
        }
      }
    } catch (e) {
      console.error('Error checking notifications on page load:', e);
    }
  }).fail(function(xhr, status, error) {
    console.warn('Failed to check notifications on page load:', error);
  });
}

// Function to hide all red dots from notification elements
function hideAllRedDots() {
  console.log('🔴 Hiding all red dots from notification elements...');
  
  // Hide red dots from bell icon
  const notificationBell = document.getElementById('navbarAnnounceDropdown');
  if (notificationBell) {
    const bellRedDots = notificationBell.querySelectorAll('.red-dot');
    bellRedDots.forEach(dot => {
      dot.remove();
      console.log('✅ Red dot removed from bell icon');
    });
  }
  
  // Hide red dots from enrollment request link
  const enrollmentLink = document.getElementById('student-enrollment-requests-link');
  if (enrollmentLink) {
    const enrollmentRedDots = enrollmentLink.querySelectorAll('.position-relative .red-dot');
    enrollmentRedDots.forEach(dot => {
      const container = dot.closest('.position-relative');
      if (container) {
        container.remove();
        console.log('✅ Red dot removed from enrollment request link');
      }
    });
    
    // Also remove any red dots directly on the link
    const directRedDots = enrollmentLink.querySelectorAll('.red-dot');
    directRedDots.forEach(dot => {
      dot.remove();
      console.log('✅ Direct red dot removed from enrollment request link');
    });
  }
  
  console.log('✅ All red dots from notification elements hidden');
}

// Function to remove enrollment requests badge immediately
function removeEnrollmentRequestsBadge() {
  const enrollmentRequestsLink = document.getElementById('student-enrollment-requests-link');
  if (enrollmentRequestsLink) {
    // Remove the badge container
    const existingBadge = enrollmentRequestsLink.querySelector('.position-relative');
    if (existingBadge) {
      existingBadge.remove();
      console.log('✅ Removed enrollment requests badge');
    }
    
    // Also remove any red dots that might be directly on the link
    const redDots = enrollmentRequestsLink.querySelectorAll('.red-dot');
    redDots.forEach(dot => {
      dot.remove();
      console.log('✅ Removed red dot from enrollment requests link');
    });
  }
}

// Tutorial System
class TutorialSystem {
    constructor() {
        this.userRole = '<?php echo $_SESSION["role"] ?? "guest"; ?>';
        this.currentPage = '<?php echo $current_page ?? ""; ?>';
        this.init();
    }

    init() {
        // Only show tutorial for logged-in users
        if (this.userRole === 'guest') return;
        
        // Always add tutorial trigger button (no auto-show)
        this.addTutorialTrigger();
    }


    showTutorialModal(tutorialData) {
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade tutorial-modal" id="tutorialModal" tabindex="-1" aria-labelledby="tutorialModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tutorialModalLabel">
                                <i class="bi bi-info-circle me-2"></i>
                                ${tutorialData.title}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-4">
                                <p class="lead text-center">${tutorialData.description}</p>
                            </div>
                            
                            <div class="tutorial-features">
                                ${tutorialData.features.map(feature => `
                                    <div class="tutorial-feature">
                                        <i class="${feature.icon}"></i>
                                        <div class="tutorial-feature-content">
                                            <h6>${feature.title}</h6>
                                            <p>${feature.description}</p>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                            
                            ${tutorialData.tips ? `
                                <div class="alert alert-info mt-4">
                                    <h6><i class="bi bi-lightbulb me-2"></i>Quick Tips</h6>
                                    <ul class="mb-0">
                                        ${tutorialData.tips.map(tip => `<li>${tip}</li>`).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                                <i class="bi bi-check-circle me-1"></i>Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existingModal = document.getElementById('tutorialModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('tutorialModal'));
        modal.show();
    }

    getTutorialData() {
        const tutorials = {
            'student': {
                'index.php': {
                    title: 'Student Dashboard',
                    description: 'Welcome to your Student Dashboard! This is your central hub for tracking your learning progress, enrolled courses, and achievements.',
                    features: [
                        {
                            icon: 'bi bi-book',
                            title: 'Enrolled Courses',
                            description: 'View all your enrolled courses and track your progress through each one.'
                        },
                        {
                            icon: 'bi bi-clipboard-check',
                            title: 'Completed Assessments',
                            description: 'Keep track of all assessments you have completed across all courses.'
                        },
                        {
                            icon: 'bi bi-graph-up',
                            title: 'Average Score',
                            description: 'Monitor your overall performance with your average score across all assessments.'
                        },
                        {
                            icon: 'bi bi-award',
                            title: 'Badges Earned',
                            description: 'See the badges you have earned for your achievements and course completions.'
                        }
                    ],
                    tips: [
                        'Check your dashboard regularly to stay updated on your progress',
                        'Click on course cards to access course content and materials',
                        'Use the navigation menu to access different sections of the LMS'
                    ]
                },
                'courses.php': {
                    title: 'My Courses',
                    description: 'Browse and manage all your enrolled courses. Access course materials, assessments, and track your progress.',
                    features: [
                        {
                            icon: 'bi bi-book-open',
                            title: 'Course Access',
                            description: 'Click on any course card to access course content, videos, and materials.'
                        },
                        {
                            icon: 'bi bi-graph-up',
                            title: 'Progress Tracking',
                            description: 'View your progress percentage for each course based on completed assessments.'
                        },
                        {
                            icon: 'bi bi-person-badge',
                            title: 'Teacher Information',
                            description: 'See which teacher is instructing each course.'
                        }
                    ],
                    tips: [
                        'Click "Continue Learning" to access course content',
                        'Check your progress regularly to stay on track',
                        'Contact your teacher if you have questions about course content'
                    ]
                }
            },
            'teacher': {
                'index.php': {
                    title: 'Teacher Dashboard',
                    description: 'Welcome to your Teacher Dashboard! This is your command center for managing courses, students, and educational content.',
                    features: [
                        {
                            icon: 'bi bi-book',
                            title: 'Total Courses',
                            description: 'View the total number of courses you have created and are managing.'
                        },
                        {
                            icon: 'bi bi-people',
                            title: 'Total Students',
                            description: 'Track the total number of students enrolled in your courses.'
                        },
                        {
                            icon: 'bi bi-clipboard-check',
                            title: 'Assessments Created',
                            description: 'Monitor the total number of assessments you have created for your courses.'
                        },
                        {
                            icon: 'bi bi-graph-up',
                            title: 'Average Student Score',
                            description: 'See the average performance of your students across all assessments.'
                        }
                    ],
                    tips: [
                        'Use the "Create Course" button to add new courses',
                        'Click on course cards to manage course content and students',
                        'Monitor student performance regularly to identify areas for improvement'
                    ]
                },
                'courses.php': {
                    title: 'Course Management',
                    description: 'Manage all your courses, create new ones, and organize your educational content effectively.',
                    features: [
                        {
                            icon: 'bi bi-plus-circle',
                            title: 'Create New Course',
                            description: 'Click the "Create Course" button to add new courses for your students.'
                        },
                        {
                            icon: 'bi bi-gear',
                            title: 'Course Settings',
                            description: 'Click on course cards to manage course content, students, and settings.'
                        },
                        {
                            icon: 'bi bi-people',
                            title: 'Student Management',
                            description: 'View and manage students enrolled in each course.'
                        }
                    ],
                    tips: [
                        'Plan your course structure before creating new courses',
                        'Use descriptive course names and codes for easy identification',
                        'Regularly update course content to keep it current and engaging'
                    ]
                },
                'module_videos.php': {
                    title: 'Module Video Management',
                    description: 'Upload, organize, and manage video content for your course modules. This is where you add educational videos to enhance student learning.',
                    features: [
                        {
                            icon: 'bi bi-upload',
                            title: 'Video Upload',
                            description: 'Upload educational videos in MP4 format to your course modules.'
                        },
                        {
                            icon: 'bi bi-play-circle',
                            title: 'Video Organization',
                            description: 'Organize videos by modules and add descriptions to help students understand content.'
                        },
                        {
                            icon: 'bi bi-eye',
                            title: 'Video Preview',
                            description: 'Preview uploaded videos to ensure quality and content accuracy.'
                        },
                        {
                            icon: 'bi bi-trash',
                            title: 'Video Management',
                            description: 'Edit video details, delete outdated content, and manage video accessibility.'
                        }
                    ],
                    tips: [
                        'Keep video file sizes under 100MB for optimal performance',
                        'Add clear, descriptive titles and descriptions for each video',
                        'Test video playback before making videos available to students',
                        'Organize videos logically by topic or lesson sequence'
                    ]
                },
                'videos.php': {
                    title: 'Video Library',
                    description: 'Manage your entire video library across all courses. Upload, organize, and maintain your educational video content.',
                    features: [
                        {
                            icon: 'bi bi-collection',
                            title: 'Video Library',
                            description: 'View and manage all videos across all your courses in one central location.'
                        },
                        {
                            icon: 'bi bi-search',
                            title: 'Search & Filter',
                            description: 'Search for specific videos and filter by course or module.'
                        },
                        {
                            icon: 'bi bi-download',
                            title: 'Bulk Operations',
                            description: 'Download multiple videos or perform bulk operations on your video library.'
                        }
                    ],
                    tips: [
                        'Use consistent naming conventions for your videos',
                        'Add tags and categories to make videos easier to find',
                        'Regularly review and update video content for accuracy',
                        'Consider video quality and file size for optimal student experience'
                    ]
                }
            },
            'admin': {
                'index.php': {
                    title: 'Admin Dashboard',
                    description: 'Welcome to the Admin Dashboard! Monitor system-wide statistics and manage the entire LMS platform.',
                    features: [
                        {
                            icon: 'bi bi-people',
                            title: 'Total Users',
                            description: 'Monitor the total number of users registered in the system.'
                        },
                        {
                            icon: 'bi bi-book',
                            title: 'Total Courses',
                            description: 'Track the total number of courses created across the platform.'
                        },
                        {
                            icon: 'bi bi-mortarboard',
                            title: 'Total Students',
                            description: 'Monitor the total number of enrolled students in the system.'
                        },
                        {
                            icon: 'bi bi-clipboard-check',
                            title: 'Assessments Taken',
                            description: 'Track the total number of assessments completed by students.'
                        }
                    ],
                    tips: [
                        'Use the Quick Actions section to access common administrative tasks',
                        'Monitor system statistics regularly to identify trends and issues',
                        'Check user activity and course performance metrics'
                    ]
                }
            }
        };

        // Check for specific page tutorial first
        let tutorial = tutorials[this.userRole]?.[this.currentPage];
        
        // If no specific tutorial, provide a general one
        if (!tutorial) {
            tutorial = {
                title: `${this.userRole.charAt(0).toUpperCase() + this.userRole.slice(1)} Interface`,
                description: `Welcome to the ${this.userRole} section of the NEUST-MGT BSIT LMS. This interface provides you with the tools and features specific to your role.`,
                features: [
                    {
                        icon: 'bi bi-gear',
                        title: 'Role-Specific Features',
                        description: `Access features and tools designed specifically for ${this.userRole}s in the LMS.`
                    },
                    {
                        icon: 'bi bi-navigation',
                        title: 'Navigation Menu',
                        description: 'Use the navigation menu to access different sections and features of the system.'
                    },
                    {
                        icon: 'bi bi-question-circle',
                        title: 'Help & Support',
                        description: 'Click the tutorial button anytime to get help and learn about page features.'
                    }
                ],
                tips: [
                    'Explore the interface to familiarize yourself with available features',
                    'Use the navigation menu to access different sections',
                    'Contact support if you need assistance with specific features'
                ]
            };
        }

        return tutorial;
    }

    addTutorialTrigger() {
        // Only add tutorial button on dashboard pages
        const currentPath = window.location.pathname;
        const isDashboard = currentPath.includes('dashboard.php') || 
                          currentPath.includes('index.php') || 
                          currentPath.endsWith('/') || 
                          currentPath.endsWith('/student/') ||
                          currentPath.endsWith('/teacher/') ||
                          currentPath.endsWith('/admin/');
        
        // Don't add tutorial button on assessment pages, result pages, or other non-dashboard pages
        if (currentPath.includes('assessment') || 
            currentPath.includes('result') || 
            currentPath.includes('course.php') ||
            currentPath.includes('module') ||
            currentPath.includes('progress') ||
            currentPath.includes('badges') ||
            currentPath.includes('profile') ||
            currentPath.includes('enrollment') ||
            !isDashboard) {
            console.log('Skipping tutorial trigger on non-dashboard page:', currentPath);
            return;
        }
        
        console.log('Adding tutorial trigger button to dashboard');
        const trigger = document.createElement('button');
        trigger.className = 'tutorial-trigger';
        trigger.innerHTML = '<i class="bi bi-question-circle"></i> Tutorial';
        trigger.onclick = () => {
            console.log('Tutorial trigger clicked');
            const tutorialData = this.getTutorialData();
            if (tutorialData) {
                this.showTutorialModal(tutorialData);
            }
        };
        
        document.body.appendChild(trigger);
        console.log('Tutorial trigger button added to dashboard');
    }

    // Method to reset tutorial for testing (kept for compatibility)
    resetTutorial() {
        // No-op since we don't track completion anymore
        console.log('Tutorial reset requested (no-op)');
    }
}

// Initialize tutorial system when page loads
let tutorialSystem;
document.addEventListener('DOMContentLoaded', function() {
    tutorialSystem = new TutorialSystem();
});

// Global function to reset tutorial for testing
window.resetTutorial = function() {
    if (tutorialSystem) {
        tutorialSystem.resetTutorial();
    }
};

// Global function to start tutorial manually
window.startTutorial = function() {
    if (tutorialSystem) {
        const tutorialData = tutorialSystem.getTutorialData();
        if (tutorialData) {
            tutorialSystem.showTutorialModal(tutorialData);
        }
    }
};

// Simple dropdown fix - ensure Bootstrap dropdowns work
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 DOM loaded - checking Bootstrap dropdowns...');
    
    // Check if Bootstrap is available
    if (typeof bootstrap === 'undefined') {
        console.error('❌ Bootstrap not loaded!');
        return;
    }
    
    console.log('✅ Bootstrap is available');
    
    // Force re-initialization of all dropdowns
    var dropdowns = document.querySelectorAll('.dropdown-toggle');
    console.log('Found', dropdowns.length, 'dropdown elements');
    
    dropdowns.forEach(function(dropdown, index) {
        console.log('Processing dropdown', index + 1, ':', dropdown.id || dropdown.textContent.trim());
        
        try {
            // Dispose existing instance if any
            var existingInstance = bootstrap.Dropdown.getInstance(dropdown);
            if (existingInstance) {
                console.log('Disposing existing instance for dropdown', index + 1);
                existingInstance.dispose();
            }
            
            // Create new instance
            new bootstrap.Dropdown(dropdown);
            console.log('✅ Successfully initialized dropdown', index + 1);
        } catch (error) {
            console.error('❌ Error initializing dropdown', index + 1, ':', error);
        }
    });
    
    // Add manual click handler as fallback
    dropdowns.forEach(function(dropdown) {
        dropdown.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var menu = this.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                // Close other dropdowns
                document.querySelectorAll('.dropdown-menu.show').forEach(function(openMenu) {
                    if (openMenu !== menu) {
                        openMenu.classList.remove('show');
                    }
                });
                
                // Toggle current dropdown
                menu.classList.toggle('show');
                console.log('Manual dropdown toggle:', this.id || this.textContent.trim());
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
            });
        }
    });
    
    console.log('✅ Dropdown initialization complete');
    
    // Test the teacher activity dropdown specifically
    setTimeout(function() {
        var teacherDropdown = document.querySelector('#teacherActivityDropdown');
        if (teacherDropdown) {
            console.log('🧪 Testing teacher activity dropdown...');
            console.log('Dropdown element:', teacherDropdown);
            console.log('Dropdown classes:', teacherDropdown.className);
            console.log('Dropdown data-bs-toggle:', teacherDropdown.getAttribute('data-bs-toggle'));
            
            var menu = teacherDropdown.nextElementSibling;
            if (menu) {
                console.log('Menu element:', menu);
                console.log('Menu classes:', menu.className);
                console.log('Menu display style:', window.getComputedStyle(menu).display);
                console.log('Menu visibility style:', window.getComputedStyle(menu).visibility);
                console.log('Menu opacity style:', window.getComputedStyle(menu).opacity);
                console.log('Menu position style:', window.getComputedStyle(menu).position);
                console.log('Menu z-index style:', window.getComputedStyle(menu).zIndex);
                
                // Test click functionality
                teacherDropdown.addEventListener('click', function(e) {
                    console.log('🎯 Teacher dropdown clicked!');
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Toggle the menu manually
                    if (menu.classList.contains('show')) {
                        menu.classList.remove('show');
                        console.log('📤 Hiding dropdown menu');
                    } else {
                        // Close other dropdowns first
                        document.querySelectorAll('.dropdown-menu.show').forEach(function(openMenu) {
                            if (openMenu !== menu) {
                                openMenu.classList.remove('show');
                            }
                        });
                        menu.classList.add('show');
                        console.log('📥 Showing dropdown menu');
                    }
                });
                
                // Ensure the dropdown is properly initialized
                if (typeof bootstrap !== 'undefined') {
                    try {
                        var dropdownInstance = new bootstrap.Dropdown(teacherDropdown);
                        console.log('✅ Teacher activity dropdown initialized successfully');
                    } catch (error) {
                        console.error('❌ Error initializing teacher activity dropdown:', error);
                    }
                }
            } else {
                console.error('❌ Menu element not found!');
            }
        } else {
            console.error('❌ Teacher activity dropdown not found!');
        }
    }, 500);
});

// Real-time Calendar Widget
function updateCalendar() {
    const now = new Date();
    
    // Format date
    const dateOptions = { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    };
    const formattedDate = now.toLocaleDateString('en-US', dateOptions);
    
    // Format time
    const timeOptions = { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    };
    const formattedTime = now.toLocaleTimeString('en-US', timeOptions);
    
    // Update DOM elements
    const dateElement = document.getElementById('currentDate');
    const timeElement = document.getElementById('currentTime');
    
    if (dateElement) {
        dateElement.textContent = formattedDate;
    }
    if (timeElement) {
        timeElement.textContent = formattedTime;
    }
}

// Initialize calendar on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCalendar();
    
    // Update every second for real-time effect
    setInterval(updateCalendar, 1000);
    
    // Initialize calendar modal
    initializeCalendarModal();
});

// Calendar Modal Functionality
let currentDate = new Date();
let selectedDate = null;

function initializeCalendarModal() {
    // Generate calendar when modal is shown
    $('#calendarModal').on('show.bs.modal', function() {
        generateCalendar();
        updateModalDateTime();
    });
    
    // Navigation buttons
    $('#prevMonth').on('click', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        generateCalendar();
    });
    
    $('#nextMonth').on('click', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        generateCalendar();
    });
    
}

function generateCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Update month/year display
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    $('#currentMonthYear').text(`${monthNames[month]} ${year}`);
    
    // Get first day of month and number of days
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    
    // Day headers
    const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    let calendarHTML = '';
    
    // Add day headers
    dayHeaders.forEach(day => {
        calendarHTML += `<div class="calendar-day-header">${day}</div>`;
    });
    
    // Add empty cells for days before the first day of the month
    for (let i = 0; i < startingDayOfWeek; i++) {
        const prevMonthDay = new Date(year, month, -startingDayOfWeek + i + 1);
        calendarHTML += `
            <div class="calendar-day other-month">
                <div class="calendar-day-number">${prevMonthDay.getDate()}</div>
            </div>
        `;
    }
    
    // Add days of the current month
    const today = new Date();
    for (let day = 1; day <= daysInMonth; day++) {
        const cellDate = new Date(year, month, day);
        const isToday = cellDate.toDateString() === today.toDateString();
        const isSelected = selectedDate && cellDate.toDateString() === selectedDate.toDateString();
        
        calendarHTML += `
            <div class="calendar-day ${isToday ? 'today' : ''} ${isSelected ? 'selected' : ''}" 
                 data-date="${cellDate.toISOString().split('T')[0]}">
                <div class="calendar-day-number">${day}</div>
                <div class="calendar-day-events"></div>
            </div>
        `;
    }
    
    // Add empty cells for days after the last day of the month
    const totalCells = 42; // 6 weeks * 7 days
    const remainingCells = totalCells - (startingDayOfWeek + daysInMonth);
    for (let i = 1; i <= remainingCells; i++) {
        const nextMonthDay = new Date(year, month + 1, i);
        calendarHTML += `
            <div class="calendar-day other-month">
                <div class="calendar-day-number">${nextMonthDay.getDate()}</div>
            </div>
        `;
    }
    
    $('#calendarGrid').html(calendarHTML);
    
    // Add click handlers for calendar days
    $('.calendar-day').on('click', function() {
        const dateString = $(this).data('date');
        if (dateString) {
            selectedDate = new Date(dateString);
            $('.calendar-day').removeClass('selected');
            $(this).addClass('selected');
        }
    });
}

function updateModalDateTime() {
    const now = new Date();
    const dateOptions = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    const timeOptions = { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit',
        hour12: true 
    };
    
    const formattedDateTime = `${now.toLocaleDateString('en-US', dateOptions)} at ${now.toLocaleTimeString('en-US', timeOptions)}`;
    $('#currentDateTime').text(formattedDateTime);
}
</script>
</body>
</html>
<?php
}
?> 