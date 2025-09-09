<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/pusher.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$db = new Database();
$pdo = $db->getConnection();

// Get current user's basic info for fallback display
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user_basic = $stmt->fetch();

// Helper function to format section display name
function formatSectionName($section) {
    if (!$section || !isset($section['section_year']) || !isset($section['section_name'])) {
        return "No Section";
    }
    return "BSIT-{$section['section_year']}{$section['section_name']}";
}

// Get all courses for the course filter
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.course_name, c.course_code
    FROM courses c
    WHERE c.is_archived = 0
    ORDER BY c.course_name
");
$stmt->execute();
$all_courses = $stmt->fetchAll();

// Get all sections for the section filter
$stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.section_name, s.year_level
    FROM sections s
    ORDER BY s.year_level, s.section_name
");
$stmt->execute();
$all_sections = $stmt->fetchAll();

// Define year levels for the year filter
$year_levels = [
    ['value' => '1', 'label' => '1st Year'],
    ['value' => '2', 'label' => '2nd Year'],
    ['value' => '3', 'label' => '3rd Year'],
    ['value' => '4', 'label' => '4th Year']
];

// Handle filters
$selected_course_id = $_GET['course_id'] ?? '';
$selected_section_id = $_GET['section_id'] ?? '';
$selected_year = $_GET['year'] ?? '';

// Build the leaderboard query based on filters
$filter_where = "";
$filter_params = [];

if (!empty($selected_course_id)) {
    $filter_where .= "AND u.id IN (
        SELECT DISTINCT e.student_id 
        FROM course_enrollments e 
        WHERE e.course_id = ? AND e.status = 'active'
    )";
    $filter_params[] = $selected_course_id;
}

if (!empty($selected_section_id)) {
    $filter_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.id = ?
    )";
    $filter_params[] = $selected_section_id;
}

if (!empty($selected_year)) {
    $filter_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.year_level = ?
    )";
    $filter_params[] = $selected_year;
}

// Get total count of students for accurate ranking
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total_students
    FROM users u
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE u.role = 'student' $filter_where
");
$count_stmt->execute($filter_params);
$total_students = $count_stmt->fetch()['total_students'];

// Get leaderboard data with section information and filters (removed LIMIT to show all students)
$stmt = $pdo->prepare("
    SELECT 
        u.id, u.first_name, u.last_name, u.email, u.profile_picture,
        s.section_name, s.year_level as section_year,
        (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) as badge_count,
        (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as completed_modules,
        (SELECT COUNT(*) FROM course_enrollments e WHERE e.student_id = u.id AND e.status = 'active') as watched_videos,
        (SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id) as average_score,
        (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.score >= 70) as high_scores,
        (
            (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') * 10 +
            COALESCE((SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id), 0) * 0.5 +
            (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) * 5
        ) as calculated_score
    FROM users u
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE u.role = 'student' $filter_where
    ORDER BY calculated_score DESC
");
$stmt->execute($filter_params);
$leaderboard = $stmt->fetchAll();

// Get current user's stats with section information (with filters)
$current_user_where = "";
$current_user_params = [$user_id];

if (!empty($selected_course_id)) {
    $current_user_where .= "AND u.id IN (
        SELECT DISTINCT e.student_id 
        FROM course_enrollments e 
        WHERE e.course_id = ? AND e.status = 'active'
    )";
    $current_user_params[] = $selected_course_id;
}

if (!empty($selected_section_id)) {
    $current_user_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.id = ?
    )";
    $current_user_params[] = $selected_section_id;
}

if (!empty($selected_year)) {
    $current_user_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.year_level = ?
    )";
    $current_user_params[] = $selected_year;
}

$stmt = $pdo->prepare("
    SELECT 
        u.id, u.first_name, u.last_name, u.email, u.profile_picture,
        s.section_name, s.year_level as section_year,
        (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) as badge_count,
        (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as completed_modules,
        (SELECT COUNT(*) FROM course_enrollments e WHERE e.student_id = u.id AND e.status = 'active') as watched_videos,
        (SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id) as average_score,
        (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.score >= 70) as high_scores,
        (
            (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') * 10 +
            COALESCE((SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id), 0) * 0.5 +
            (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) * 5
        ) as calculated_score
    FROM users u
    LEFT JOIN sections s ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL
    WHERE u.id = ? $current_user_where
");
$stmt->execute($current_user_params);
$current_user = $stmt->fetch();

// Find user's rank
$user_rank = 0;
if ($current_user) {
    foreach ($leaderboard as $index => $student) {
        if ($student['id'] == $user_id) {
            $user_rank = $index + 1;
            break;
        }
    }
}

// Get top performers in different categories (with filters)
$top_modules_where = "";
$top_scores_where = "";
$top_videos_where = "";

if (!empty($selected_course_id)) {
    $top_modules_where .= "AND u.id IN (
        SELECT DISTINCT e.student_id 
        FROM course_enrollments e 
        WHERE e.course_id = ? AND e.status = 'active'
    )";
    $top_scores_where .= "AND u.id IN (
        SELECT DISTINCT e.student_id 
        FROM course_enrollments e 
        WHERE e.course_id = ? AND e.status = 'active'
    )";
    $top_videos_where .= "AND u.id IN (
        SELECT DISTINCT e.student_id 
        FROM course_enrollments e 
        WHERE e.course_id = ? AND e.status = 'active'
    )";
}

if (!empty($selected_section_id)) {
    $top_modules_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.id = ?
    )";
    $top_scores_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.id = ?
    )";
    $top_videos_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.id = ?
    )";
}

if (!empty($selected_year)) {
    $top_modules_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.year_level = ?
    )";
    $top_scores_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.year_level = ?
    )";
    $top_videos_where .= "AND u.id IN (
        SELECT DISTINCT u.id 
        FROM sections s 
        JOIN users u ON JSON_SEARCH(s.students, 'one', u.id) IS NOT NULL 
        WHERE s.year_level = ?
    )";
}

// Build parameters for top performers queries
$top_modules_params = [];
$top_scores_params = [];
$top_videos_params = [];

if (!empty($selected_course_id)) {
    $top_modules_params[] = $selected_course_id;
    $top_scores_params[] = $selected_course_id;
    $top_videos_params[] = $selected_course_id;
}
if (!empty($selected_section_id)) {
    $top_modules_params[] = $selected_section_id;
    $top_scores_params[] = $selected_section_id;
    $top_videos_params[] = $selected_section_id;
}
if (!empty($selected_year)) {
    $top_modules_params[] = $selected_year;
    $top_scores_params[] = $selected_year;
    $top_videos_params[] = $selected_year;
}

$stmt = $pdo->prepare("
    SELECT u.first_name, u.last_name, 
           (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as completed_modules
    FROM users u
    WHERE u.role = 'student' $top_modules_where
    ORDER BY completed_modules DESC
    LIMIT 5
");
$stmt->execute($top_modules_params);
$top_modules = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT u.first_name, u.last_name, 
           (SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id) as average_score
    FROM users u
    WHERE u.role = 'student' $top_scores_where
    ORDER BY average_score DESC
    LIMIT 5
");
$stmt->execute($top_scores_params);
$top_scores = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT u.first_name, u.last_name, 
           (SELECT COUNT(*) FROM course_enrollments e WHERE e.student_id = u.id AND e.status = 'active') as watched_videos
    FROM users u
    WHERE u.role = 'student' $top_videos_where
    ORDER BY watched_videos DESC
    LIMIT 5
");
$stmt->execute($top_videos_params);
$top_videos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap');
        
        :root {
            --main-green: #2E5E4E;
            --accent-green: #7DCB80;
            --highlight-yellow: #FFE066;
            --off-white: #F7FAF7;
            --white: #FFFFFF;
        }
        
        body {
            background: linear-gradient(135deg, var(--off-white) 0%, var(--accent-green) 100%);
            font-family: 'Rajdhani', sans-serif;
            min-height: 100vh;
        }
        
        .game-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(46,94,78,0.1);
            margin: 20px 0;
        }
        
        .leaderboard-card {
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border: none;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .leaderboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--main-green), var(--accent-green), var(--highlight-yellow));
            background-size: 200% 100%;
            animation: rainbow 3s ease-in-out infinite;
        }
        
        @keyframes rainbow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .rank-1 {
            background: linear-gradient(135deg, var(--highlight-yellow), #fff6b0);
            color: var(--main-green);
            transform: scale(1.02);
            box-shadow: 0 15px 35px rgba(255, 224, 102, 0.3);
        }
        
        .rank-2 {
            background: linear-gradient(135deg, var(--accent-green), #9dd8a0);
            color: var(--main-green);
            transform: scale(1.01);
            box-shadow: 0 12px 30px rgba(125, 203, 128, 0.3);
        }
        
        .rank-3 {
            background: linear-gradient(135deg, var(--main-green), #3a6b5a);
            color: white;
            transform: scale(1.005);
            box-shadow: 0 10px 25px rgba(46, 94, 78, 0.3);
        }
        
        .current-user {
            background: linear-gradient(135deg, var(--main-green), var(--accent-green));
            color: white;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(46, 94, 78, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(46, 94, 78, 0); }
        }
        
        .leaderboard-item {
            transition: all 0.3s ease;
            border-radius: 15px;
            margin-bottom: 10px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: 1px solid rgba(46, 94, 78, 0.1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            min-height: 120px;
        }
        
                .leaderboard-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .leaderboard-item:hover::before {
            left: 100%;
        }

        /* Enhanced student section styling */
        .leaderboard-item h6 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--main-green);
            margin-bottom: 0.5rem;
        }

        .leaderboard-item small {
            font-size: 0.85rem;
            color: #6c757d;
            line-height: 1.4;
        }

        .leaderboard-item .text-end h6 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--main-green);
        }

        .leaderboard-item .text-end small {
            font-size: 0.8rem;
            color: var(--accent-green);
            font-weight: 500;
        }
        
        .leaderboard-item:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: var(--accent-green);
        }
        
        .rank-badge {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.4rem;
            font-family: 'Orbitron', monospace;
            position: relative;
            animation: bounce 2s infinite;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        .rank-1 .rank-badge {
            background: linear-gradient(135deg, var(--highlight-yellow), #fff6b0);
            color: var(--main-green);
            box-shadow: 0 5px 15px rgba(255, 224, 102, 0.5);
        }
        
        .rank-2 .rank-badge {
            background: linear-gradient(135deg, var(--accent-green), #9dd8a0);
            color: var(--main-green);
            box-shadow: 0 4px 12px rgba(125, 203, 128, 0.5);
        }
        
        .rank-3 .rank-badge {
            background: linear-gradient(135deg, var(--main-green), #3a6b5a);
            color: white;
            box-shadow: 0 3px 10px rgba(46, 94, 78, 0.5);
        }
        
        .current-user .rank-badge {
            background: linear-gradient(135deg, var(--main-green), var(--accent-green));
            color: white;
            box-shadow: 0 5px 15px rgba(46, 94, 78, 0.5);
        }
        
        /* Profile Picture Styling */
        .student-profile-pic {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }

        .podium-profile-link, .student-profile-link {
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all 0.3s ease;
        }

        .podium-profile-link:hover, .student-profile-link:hover {
            transform: scale(1.05);
            text-decoration: none;
            color: inherit;
        }

        .podium-profile-link:hover .podium-profile,
        .student-profile-link:hover .student-profile-pic {
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border-color: var(--highlight-yellow);
        }
        
        .leaderboard-item:hover .student-profile-pic {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        
        .rank-1 .student-profile-pic {
            border-color: var(--highlight-yellow);
            box-shadow: 0 5px 15px rgba(255, 224, 102, 0.5);
        }
        
        .rank-2 .student-profile-pic {
            border-color: var(--accent-green);
            box-shadow: 0 4px 12px rgba(125, 203, 128, 0.5);
        }
        
        .rank-3 .student-profile-pic {
            border-color: var(--main-green);
            box-shadow: 0 3px 10px rgba(46, 94, 78, 0.5);
        }
        
        .current-user .student-profile-pic {
            border-color: var(--main-green);
            box-shadow: 0 5px 15px rgba(46, 94, 78, 0.5);
        }
        
        /* Progress Bar Styling */
        .progress-container {
            background: rgba(0,0,0,0.1);
            border-radius: 10px;
            height: 8px;
            margin: 5px 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, var(--main-green), var(--accent-green), var(--highlight-yellow));
            background-size: 200% 100%;
            animation: shimmer 2s ease-in-out infinite;
            transition: width 1s ease;
        }
        
        @keyframes shimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        /* Achievement Badges */
        .achievement-badge {
            display: inline-block;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            margin: 0 2px;
            text-align: center;
            line-height: 25px;
            font-size: 12px;
            color: white;
            animation: badgeGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes badgeGlow {
            0% { box-shadow: 0 0 5px currentColor; }
            100% { box-shadow: 0 0 20px currentColor; }
        }
        
        .badge-modules { background: var(--main-green); }
        .badge-scores { background: var(--highlight-yellow); color: var(--main-green); }
        .badge-videos { background: var(--accent-green); }
        .badge-badges { background: var(--main-green); }
        
        /* Game-like Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--main-green), var(--accent-green));
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            font-family: 'Orbitron', monospace;
            color: var(--main-green);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Animated Title */
        .game-title {
            font-family: 'Orbitron', monospace;
            font-weight: 900;
            background: linear-gradient(45deg, var(--main-green), var(--accent-green), var(--highlight-yellow));
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleGlow 3s ease-in-out infinite;
            text-align: center;
            margin: 20px 0;
        }
        
        @keyframes titleGlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        /* Level System */
        .level-indicator {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            margin-left: 10px;
            animation: levelPulse 2s infinite;
        }
        
        @keyframes levelPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .level-beginner { background: var(--accent-green); color: var(--main-green); }
        .level-intermediate { background: var(--highlight-yellow); color: var(--main-green); }
        .level-advanced { background: var(--main-green); color: white; }
        .level-expert { background: var(--main-green); color: white; }
        
        /* Floating Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(46, 94, 78, 0.3);
            border-radius: 50%;
            animation: float 6s infinite linear;
        }
        
        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100px) rotate(360deg); opacity: 0; }
        }
        
        /* Podium Styling */
        .podium-container {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            margin: 30px 0;
            height: 320px;
            position: relative;
            background: linear-gradient(135deg, 
                rgba(255, 215, 0, 0.15) 0%, 
                rgba(255, 193, 7, 0.12) 25%, 
                rgba(46, 94, 78, 0.08) 50%, 
                rgba(125, 203, 128, 0.12) 75%, 
                rgba(255, 215, 0, 0.15) 100%);
            border-radius: 30px;
            padding: 30px;
            border: 3px solid transparent;
            background-clip: padding-box;
            box-shadow: 
                0 20px 60px rgba(255, 215, 0, 0.2),
                0 10px 30px rgba(46, 94, 78, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }
        
        .podium-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255, 215, 0, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 193, 7, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(46, 94, 78, 0.1) 0%, transparent 70%),
                linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            border-radius: 30px;
            pointer-events: none;
            animation: shimmer 3s ease-in-out infinite;
        }
        
        .podium-container::after {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, 
                rgba(255, 215, 0, 0.6), 
                rgba(255, 193, 7, 0.4), 
                rgba(46, 94, 78, 0.6), 
                rgba(125, 203, 128, 0.4), 
                rgba(255, 215, 0, 0.6));
            border-radius: 32px;
            z-index: -1;
            animation: gradientShift 4s ease-in-out infinite;
        }
        
        /* Floating decorative elements */
        .podium-container .floating-star {
            position: absolute;
            color: rgba(255, 215, 0, 0.6);
            font-size: 1.5rem;
            animation: float 3s ease-in-out infinite;
            pointer-events: none;
        }
        
        .podium-container .floating-star:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .podium-container .floating-star:nth-child(2) {
            top: 30%;
            right: 15%;
            animation-delay: 1s;
        }
        
        .podium-container .floating-star:nth-child(3) {
            top: 60%;
            left: 5%;
            animation-delay: 2s;
        }
        
        .podium-container .floating-star:nth-child(4) {
            top: 70%;
            right: 10%;
            animation-delay: 0.5s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); opacity: 0.6; }
            50% { transform: translateY(-10px) rotate(180deg); opacity: 1; }
        }
        
        @keyframes shimmer {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 1; }
        }
        
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .podium-row {
            display: flex;
            align-items: flex-end;
            gap: 40px;
            position: relative;
            width: 100%;
            max-width: 500px;
        }
        
        .podium-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            min-width: 140px;
            flex: 1;
            filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.1));
        }
        
        .podium-item:hover {
            transform: translateY(-15px) scale(1.02);
            filter: drop-shadow(0 12px 24px rgba(0, 0, 0, 0.15));
        }
        
        /* 1st Place - Center with Gold */
        .podium-1st {
            order: 2;
            z-index: 3;
            transform: scale(1.15);
            animation: championGlow 2s ease-in-out infinite alternate;
        }
        
        /* 2nd Place - Left with Silver */
        .podium-2nd {
            order: 1;
            z-index: 2;
            transform: scale(0.95);
            animation: silverGlow 2.5s ease-in-out infinite alternate;
        }
        
        /* 3rd Place - Right with Bronze */
        .podium-3rd {
            order: 3;
            z-index: 1;
            transform: scale(0.85);
            animation: bronzeGlow 3s ease-in-out infinite alternate;
        }
        
        @keyframes championGlow {
            0% { 
                filter: drop-shadow(0 8px 16px rgba(255, 215, 0, 0.3)) drop-shadow(0 0 20px rgba(255, 215, 0, 0.2));
                box-shadow: 0 0 30px rgba(255, 215, 0, 0.3);
            }
            100% { 
                filter: drop-shadow(0 12px 24px rgba(255, 215, 0, 0.5)) drop-shadow(0 0 30px rgba(255, 215, 0, 0.4));
                box-shadow: 0 0 40px rgba(255, 215, 0, 0.5);
            }
        }
        
        @keyframes silverGlow {
            0% { 
                filter: drop-shadow(0 8px 16px rgba(192, 192, 192, 0.3)) drop-shadow(0 0 15px rgba(192, 192, 192, 0.2));
                box-shadow: 0 0 25px rgba(192, 192, 192, 0.3);
            }
            100% { 
                filter: drop-shadow(0 12px 24px rgba(192, 192, 192, 0.5)) drop-shadow(0 0 25px rgba(192, 192, 192, 0.4));
                box-shadow: 0 0 35px rgba(192, 192, 192, 0.5);
            }
        }
        
        @keyframes bronzeGlow {
            0% { 
                filter: drop-shadow(0 8px 16px rgba(205, 127, 50, 0.3)) drop-shadow(0 0 15px rgba(205, 127, 50, 0.2));
                box-shadow: 0 0 25px rgba(205, 127, 50, 0.3);
            }
            100% { 
                filter: drop-shadow(0 12px 24px rgba(205, 127, 50, 0.5)) drop-shadow(0 0 25px rgba(205, 127, 50, 0.4));
                box-shadow: 0 0 35px rgba(205, 127, 50, 0.5);
            }
        }
        
        .podium-rank {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            font-family: 'Orbitron', monospace;
            color: white;
            z-index: 10;
        }
        
        .podium-1st .podium-rank {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #8B4513;
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.6);
            animation: goldGlow 2s ease-in-out infinite alternate;
            border: 3px solid #FFA500;
        }
        
        .podium-2nd .podium-rank {
            background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
            color: #2F4F4F;
            box-shadow: 0 6px 20px rgba(192, 192, 192, 0.6);
            animation: silverGlow 2s ease-in-out infinite alternate;
            border: 3px solid #A9A9A9;
        }
        
        .podium-3rd .podium-rank {
            background: linear-gradient(135deg, #CD7F32, #B8860B);
            color: white;
            box-shadow: 0 4px 15px rgba(205, 127, 50, 0.6);
            animation: bronzeGlow 2s ease-in-out infinite alternate;
            border: 3px solid #B8860B;
        }
        
        @keyframes goldGlow {
            0% { box-shadow: 0 8px 25px rgba(255, 215, 0, 0.6); }
            100% { box-shadow: 0 12px 35px rgba(255, 215, 0, 0.9); }
        }
        
        @keyframes silverGlow {
            0% { box-shadow: 0 6px 20px rgba(192, 192, 192, 0.6); }
            100% { box-shadow: 0 10px 30px rgba(192, 192, 192, 0.9); }
        }
        
        @keyframes bronzeGlow {
            0% { box-shadow: 0 4px 15px rgba(205, 127, 50, 0.6); }
            100% { box-shadow: 0 8px 25px rgba(205, 127, 50, 0.9); }
        }
        
        .podium-profile {
            width: 100px;
            height: 100px;
            border-radius: 50% !important;
            margin-bottom: 15px;
            position: relative;
            z-index: 5;
            overflow: hidden !important;
        }

        .podium-1st .podium-profile {
            width: 120px;
            height: 120px;
        }
        
        .podium-profile-pic {
            width: 100%;
            height: 100%;
            border-radius: 50% !important;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            background: linear-gradient(135deg, var(--accent-green), var(--main-green));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            overflow: hidden !important;
            position: relative;
            aspect-ratio: 1 !important;
        }

        .podium-profile-pic img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 50% !important;
            display: block;
        }

        .podium-placeholder {
            background: linear-gradient(135deg, var(--accent-green), var(--main-green));
            color: white;
            font-size: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50% !important;
            width: 100% !important;
            height: 100% !important;
            border: 4px solid white;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            overflow: hidden !important;
            position: relative;
            aspect-ratio: 1 !important;
        }
        
        .podium-1st .podium-profile-pic {
            border-color: #FFD700;
            box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
            background: linear-gradient(135deg, #FFD700, #FFA500);
            animation: goldPulse 3s ease-in-out infinite;
        }

        .podium-1st .podium-profile-pic img {
            border-color: #FFD700;
            box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
        }

        .podium-1st .podium-placeholder {
            background: linear-gradient(135deg, #FFD700, #FFA500) !important;
            color: #8B4513 !important;
            font-weight: bold;
            border-color: #FFD700 !important;
            box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6) !important;
            overflow: hidden !important;
        }
        
        .podium-2nd .podium-profile-pic {
            border-color: #C0C0C0;
            box-shadow: 0 10px 30px rgba(192, 192, 192, 0.6);
            background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
            animation: silverPulse 3s ease-in-out infinite;
        }

        .podium-2nd .podium-profile-pic img {
            border-color: #C0C0C0;
            box-shadow: 0 10px 30px rgba(192, 192, 192, 0.6);
        }

        .podium-2nd .podium-placeholder {
            background: linear-gradient(135deg, #C0C0C0, #A9A9A9) !important;
            color: #2F4F4F !important;
            font-weight: bold;
            border-color: #C0C0C0 !important;
            box-shadow: 0 10px 30px rgba(192, 192, 192, 0.6) !important;
            overflow: hidden !important;
        }
        
        .podium-3rd .podium-profile-pic {
            border-color: #CD7F32;
            box-shadow: 0 8px 25px rgba(205, 127, 50, 0.6);
            background: linear-gradient(135deg, #CD7F32, #B8860B);
            animation: bronzePulse 3s ease-in-out infinite;
        }

        .podium-3rd .podium-profile-pic img {
            border-color: #CD7F32;
            box-shadow: 0 8px 25px rgba(205, 127, 50, 0.6);
        }

        .podium-3rd .podium-placeholder {
            background: linear-gradient(135deg, #CD7F32, #B8860B) !important;
            color: white !important;
            font-weight: bold;
            border-color: #CD7F32 !important;
            box-shadow: 0 8px 25px rgba(205, 127, 50, 0.6) !important;
            overflow: hidden !important;
        }
        
        @keyframes goldPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes silverPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.03); }
        }
        
        @keyframes bronzePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        .podium-name {
            font-weight: bold;
            font-size: 1rem;
            text-align: center;
            margin-bottom: 8px;
            color: var(--main-green);
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .podium-section {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--accent-green);
            text-align: center;
            background: rgba(125, 203, 128, 0.1);
            padding: 3px 10px;
            border-radius: 12px;
            margin-bottom: 5px;
            border: 1px solid rgba(125, 203, 128, 0.2);
        }

        .podium-score {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
            text-align: center;
            background: rgba(255,255,255,0.8);
            padding: 4px 12px;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
            z-index: 10;
        }
        
        .podium-1st .podium-name {
            color: #8B4513;
            font-size: 1.1rem;
        }
        
        .podium-1st .podium-section {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 165, 0, 0.2));
            color: #8B4513;
            border-color: rgba(255, 215, 0, 0.3);
        }

        .podium-2nd .podium-section {
            background: linear-gradient(135deg, rgba(192, 192, 192, 0.2), rgba(169, 169, 169, 0.2));
            color: #2F4F4F;
            border-color: rgba(192, 192, 192, 0.3);
        }

        .podium-3rd .podium-section {
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.2), rgba(184, 134, 11, 0.2));
            color: #8B4513;
            border-color: rgba(205, 127, 50, 0.3);
        }

        .podium-1st .podium-score {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #8B4513;
            font-weight: bold;
            font-size: 1rem;
            padding: 6px 16px;
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
            z-index: 15;
        }
        
        .podium-2nd .podium-name {
            color: #2F4F4F;
        }
        
        .podium-2nd .podium-score {
            background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
            color: #2F4F4F;
            font-weight: bold;
        }
        
        .podium-3rd .podium-name {
            color: #8B4513;
        }
        
        .podium-3rd .podium-score {
            background: linear-gradient(135deg, #CD7F32, #B8860B);
            color: white;
            font-weight: bold;
        }
        

        
        /* Podium Base */
        .podium-item::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 25px;
            background: linear-gradient(135deg, var(--main-green), var(--accent-green));
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(46, 94, 78, 0.4);
        }
        
        .podium-1st::after {
            width: 160px;
            height: 35px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.6);
            border-radius: 20px;
        }
        
        .podium-2nd::after {
            width: 140px;
            height: 30px;
            background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
            box-shadow: 0 8px 25px rgba(192, 192, 192, 0.6);
            border-radius: 18px;
        }
        
        .podium-3rd::after {
            width: 120px;
            height: 25px;
            background: linear-gradient(135deg, #CD7F32, #B8860B);
            box-shadow: 0 6px 20px rgba(205, 127, 50, 0.6);
            border-radius: 15px;
        }
        
        /* Special effects for top 3 */
        .podium-1st {
            animation: goldGlow 3s ease-in-out infinite;
        }
        
        .podium-2nd {
            animation: silverGlow 3s ease-in-out infinite;
        }
        
        .podium-3rd {
            animation: bronzeGlow 3s ease-in-out infinite;
        }
        
        @keyframes goldGlow {
            0%, 100% { 
                filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3));
            }
            50% { 
                filter: drop-shadow(0 0 20px rgba(255, 215, 0, 0.6));
            }
        }
        
        @keyframes silverGlow {
            0%, 100% { 
                filter: drop-shadow(0 0 8px rgba(192, 192, 192, 0.3));
            }
            50% { 
                filter: drop-shadow(0 0 16px rgba(192, 192, 192, 0.6));
            }
        }
        
        @keyframes bronzeGlow {
            0%, 100% { 
                filter: drop-shadow(0 0 6px rgba(205, 127, 50, 0.3));
            }
            50% { 
                filter: drop-shadow(0 0 12px rgba(205, 127, 50, 0.6));
            }
        }
        
        /* Medal icons for top 3 */
        .podium-1st .podium-rank::before {
            content: '🥇';
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 24px;
            animation: medalBounce 2s ease-in-out infinite;
        }
        
        .podium-2nd .podium-rank::before {
            content: '🥈';
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 20px;
            animation: medalBounce 2s ease-in-out infinite 0.3s;
        }
        
        .podium-3rd .podium-rank::before {
            content: '🥉';
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 18px;
            animation: medalBounce 2s ease-in-out infinite 0.6s;
        }
        
        @keyframes medalBounce {
            0%, 20%, 50%, 80%, 100% { transform: translateX(-50%) translateY(0); }
            40% { transform: translateX(-50%) translateY(-8px); }
            60% { transform: translateX(-50%) translateY(-4px); }
        }

        /* Filters Styling */
        .course-filter-card {
            background: linear-gradient(135deg, rgba(46, 94, 78, 0.05), rgba(125, 203, 128, 0.05));
            border: 1px solid rgba(46, 94, 78, 0.1);
        }
        
        .course-filter-card .form-select {
            border: 2px solid rgba(46, 94, 78, 0.2);
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .course-filter-card .form-select:focus {
            border-color: var(--main-green);
            box-shadow: 0 0 0 0.2rem rgba(46, 94, 78, 0.25);
        }
        
        .course-filter-card .btn-outline-secondary {
            border: 2px solid rgba(46, 94, 78, 0.2);
            color: var(--main-green);
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .course-filter-card .btn-outline-secondary:hover {
            background-color: var(--main-green);
            border-color: var(--main-green);
            color: white;
        }
        
        .filter-info-badge {
            background: linear-gradient(135deg, var(--accent-green), var(--main-green));
            border: none;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .course-filter-card .form-label {
            font-size: 0.9rem;
            color: var(--main-green);
            font-weight: 600;
        }

        /* Enhanced Leaderboard Scrolling */
        .main-leaderboard-container {
            max-height: 600px;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
            border-radius: 8px;
            position: relative;
        }

        /* Custom scrollbar for main leaderboard */
        .main-leaderboard-container::-webkit-scrollbar {
            width: 8px;
        }

        .main-leaderboard-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .main-leaderboard-container::-webkit-scrollbar-thumb {
            background: #FFD700;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .main-leaderboard-container::-webkit-scrollbar-thumb:hover {
            background: #FFA500;
        }

        /* Firefox scrollbar styling */
        .main-leaderboard-container {
            scrollbar-width: thin;
            scrollbar-color: #FFD700 #f1f1f1;
        }

        /* Top performers scrolling */
        .top-performers-container {
            max-height: 400px;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
            border-radius: 8px;
            position: relative;
        }

        /* Custom scrollbar for top performers */
        .top-performers-container::-webkit-scrollbar {
            width: 6px;
        }

        .top-performers-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .top-performers-container::-webkit-scrollbar-thumb {
            background: #2E5E4E;
            border-radius: 3px;
            transition: background 0.3s ease;
        }

        .top-performers-container::-webkit-scrollbar-thumb:hover {
            background: #1a3d32;
        }

        /* Firefox scrollbar styling for top performers */
        .top-performers-container {
            scrollbar-width: thin;
            scrollbar-color: #2E5E4E #f1f1f1;
        }

        /* Enhanced leaderboard items */
        .main-leaderboard-container .leaderboard-item {
            transition: all 0.3s ease;
            border-radius: 15px;
            margin-bottom: 12px;
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border: 1px solid rgba(46, 94, 78, 0.1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            min-height: 120px;
        }

        .main-leaderboard-container .leaderboard-item:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 20px 40px rgba(255, 215, 0, 0.2);
            border-color: #FFD700;
        }

        .main-leaderboard-container .leaderboard-item.current-user:hover {
            box-shadow: 0 20px 40px rgba(46, 94, 78, 0.3);
            border-color: #2E5E4E;
        }

        /* Enhanced top performers items */
        .top-performers-container .d-flex {
            transition: all 0.3s ease;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, rgba(46, 94, 78, 0.05), rgba(125, 203, 128, 0.05));
            border: 1px solid rgba(46, 94, 78, 0.1);
            position: relative;
            overflow: hidden;
        }

        .top-performers-container .d-flex::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .top-performers-container .d-flex:hover::before {
            left: 100%;
        }

        .top-performers-container .d-flex:hover {
            transform: translateX(8px) scale(1.02);
            box-shadow: 0 6px 20px rgba(46, 94, 78, 0.25);
            background: linear-gradient(135deg, rgba(46, 94, 78, 0.1), rgba(125, 203, 128, 0.1));
            border-color: #2E5E4E;
        }

        /* Enhanced top performers badges */
        .top-performers-container .badge {
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 6px 10px;
        }

        .top-performers-container .d-flex:hover .badge {
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        /* Enhanced student names in top performers */
        .top-performers-container .d-flex span:not(.badge) {
            transition: all 0.3s ease;
            font-weight: 500;
            color: #2E5E4E;
        }

        .top-performers-container .d-flex:hover span:not(.badge) {
            color: #1a3d32;
            font-weight: 600;
        }

        /* Specific styling for each top performer category */
        .top-performers-container .badge.bg-primary {
            background: linear-gradient(135deg, #007bff, #0056b3) !important;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }

        .top-performers-container .badge.bg-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800) !important;
            color: #212529 !important;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }

        .top-performers-container .badge.bg-info {
            background: linear-gradient(135deg, #17a2b8, #138496) !important;
            box-shadow: 0 2px 8px rgba(23, 162, 184, 0.3);
        }

        .top-performers-container .badge.bg-success {
            background: linear-gradient(135deg, #28a745, #1e7e34) !important;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .top-performers-container .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62) !important;
            box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
        }

        /* Enhanced hover effects for specific badges */
        .top-performers-container .d-flex:hover .badge.bg-primary {
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.5);
        }

        .top-performers-container .d-flex:hover .badge.bg-warning {
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.5);
        }

        .top-performers-container .d-flex:hover .badge.bg-info {
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.5);
        }

        .top-performers-container .d-flex:hover .badge.bg-success {
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.5);
        }

        .top-performers-container .d-flex:hover .badge.bg-secondary {
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.5);
        }

        /* Enhanced card headers for top performers */
        .top-performers-container + .card-header {
            background: linear-gradient(135deg, rgba(46, 94, 78, 0.1), rgba(125, 203, 128, 0.1));
            border-bottom: 2px solid rgba(46, 94, 78, 0.2);
        }

        .top-performers-container + .card-header h6 {
            color: #2E5E4E;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .top-performers-container + .card-header i {
            color: #2E5E4E;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        /* Empty state styling for top performers */
        .top-performers-container:empty::after {
            content: 'No data available';
            display: block;
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
            background: rgba(46, 94, 78, 0.05);
            border-radius: 8px;
            border: 2px dashed rgba(46, 94, 78, 0.2);
        }

        /* Enhanced ranking numbers */
        .top-performers-container .badge:first-child {
            position: relative;
            overflow: hidden;
        }

        .top-performers-container .badge:first-child::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .top-performers-container .d-flex:hover .badge:first-child::before {
            left: 100%;
        }

        /* Staggered animation for top performers */
        .top-performers-container .d-flex {
            animation: slideInFromRight 0.5s ease-out;
            animation-fill-mode: both;
        }

        .top-performers-container .d-flex:nth-child(1) { animation-delay: 0.1s; }
        .top-performers-container .d-flex:nth-child(2) { animation-delay: 0.2s; }
        .top-performers-container .d-flex:nth-child(3) { animation-delay: 0.3s; }
        .top-performers-container .d-flex:nth-child(4) { animation-delay: 0.4s; }
        .top-performers-container .d-flex:nth-child(5) { animation-delay: 0.5s; }

        @keyframes slideInFromRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Enhanced scroll indicators for top performers */
        .top-performers-scroll-indicator i {
            background: linear-gradient(135deg, #2E5E4E, #1a3d32);
            box-shadow: 0 2px 8px rgba(46, 94, 78, 0.4);
        }

        .top-performers-scroll-indicator i:hover {
            background: linear-gradient(135deg, #1a3d32, #0f241f);
            transform: scale(1.1);
        }

        /* Enhanced badges in top performers */
        .top-performers-container .badge {
            transition: all 0.3s ease;
        }

        .top-performers-container .d-flex:hover .badge {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        /* Enhanced profile pictures */
        .main-leaderboard-container .student-profile-pic {
            transition: all 0.3s ease;
        }

        .main-leaderboard-container .leaderboard-item:hover .student-profile-pic {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.3);
        }

        .main-leaderboard-container .leaderboard-item.current-user:hover .student-profile-pic {
            box-shadow: 0 8px 20px rgba(46, 94, 78, 0.3);
        }

        /* Enhanced rank badges */
        .main-leaderboard-container .rank-badge {
            transition: all 0.3s ease;
        }

        .main-leaderboard-container .leaderboard-item:hover .rank-badge {
            transform: scale(1.1);
            box-shadow: 0 6px 18px rgba(255, 215, 0, 0.4);
        }

        .main-leaderboard-container .leaderboard-item.current-user:hover .rank-badge {
            box-shadow: 0 6px 18px rgba(46, 94, 78, 0.4);
        }

        /* Enhanced progress bars */
        .main-leaderboard-container .progress-container {
            transition: all 0.3s ease;
        }

        .main-leaderboard-container .leaderboard-item:hover .progress-container {
            transform: scaleY(1.2);
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
        }

        .main-leaderboard-container .leaderboard-item:hover .progress-bar {
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        /* Scroll indicators for main leaderboard */
        .main-leaderboard-scroll-indicator {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 15;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .main-leaderboard-scroll-indicator.show {
            opacity: 1;
        }

        .main-leaderboard-scroll-indicator-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .main-leaderboard-scroll-indicator i {
            background: rgba(255, 215, 0, 0.8);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
        }

        .main-leaderboard-scroll-indicator-top.hide,
        .main-leaderboard-scroll-indicator-bottom.hide {
            opacity: 0.3;
        }

        /* Scroll indicators for top performers */
        .top-performers-scroll-indicator {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 15;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .top-performers-scroll-indicator.show {
            opacity: 1;
        }

        .top-performers-scroll-indicator-content {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .top-performers-scroll-indicator i {
            background: rgba(46, 94, 78, 0.8);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(46, 94, 78, 0.3);
        }

        .top-performers-scroll-indicator-top.hide,
        .top-performers-scroll-indicator-bottom.hide {
            opacity: 0.3;
        }

        /* Enhanced statistics cards */
        .leaderboard-stats .stat-card {
            transition: all 0.3s ease;
        }

        .leaderboard-stats .stat-card:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(46, 94, 78, 0.2);
        }

        .leaderboard-stats .stat-value {
            transition: all 0.3s ease;
        }

        .leaderboard-stats .stat-card:hover .stat-value {
            transform: scale(1.1);
            color: #FFD700;
        }

        /* Enhanced podium container */
        .podium-container {
            transition: all 0.3s ease;
        }

        .podium-container:hover {
            transform: scale(1.02);
            box-shadow: 0 25px 50px rgba(255, 215, 0, 0.2);
        }

        /* Enhanced podium items */
        .podium-item {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .podium-item:hover {
            transform: translateY(-15px) scale(1.05);
            filter: drop-shadow(0 15px 30px rgba(0, 0, 0, 0.2));
        }

        /* Enhanced podium profiles */
        .podium-profile-pic {
            transition: all 0.3s ease;
        }

        .podium-item:hover .podium-profile-pic {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(255, 215, 0, 0.6);
        }

        .podium-2nd:hover .podium-profile-pic {
            box-shadow: 0 12px 35px rgba(192, 192, 192, 0.6);
        }

        .podium-3rd:hover .podium-profile-pic {
            box-shadow: 0 12px 35px rgba(205, 127, 50, 0.6);
        }

        /* Enhanced achievement badges */
        .achievement-badge {
            transition: all 0.3s ease;
        }

        .leaderboard-item:hover .achievement-badge {
            transform: scale(1.2);
            box-shadow: 0 4px 12px currentColor;
        }

        /* Mobile responsiveness */
        @media (max-width: 991.98px) {
            .main-leaderboard-container {
                max-height: 450px;
            }
            
            .top-performers-container {
                max-height: 300px;
            }
        }

        @media (max-width: 575.98px) {
            .main-leaderboard-container {
                max-height: 350px;
            }
            
            .top-performers-container {
                max-height: 250px;
            }
            
            .main-leaderboard-container .leaderboard-item {
                margin-bottom: 10px;
                min-height: 100px;
            }
            
            .top-performers-container .d-flex {
                padding: 8px 12px;
                margin-bottom: 8px;
                font-size: 0.9rem;
            }
            
            .top-performers-container .badge {
                font-size: 0.75rem;
                padding: 4px 8px;
            }
            
            .top-performers-container .d-flex:hover {
                transform: translateX(5px) scale(1.01);
            }
        }

        /* Loading and animation states */
        .leaderboard-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .leaderboard-item-enter {
            animation: leaderboardItemEnter 0.5s ease-out;
        }

        @keyframes leaderboardItemEnter {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .leaderboard-item-exit {
            animation: leaderboardItemExit 0.5s ease-in;
        }

        @keyframes leaderboardItemExit {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(-100%);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .leaderboard-item {
                min-height: 100px;
            }

            .rank-badge {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }

            .student-profile-pic {
                width: 45px;
                height: 45px;
            }

            .leaderboard-item h6 {
                font-size: 1rem;
            }

            .leaderboard-item small {
                font-size: 0.8rem;
            }

            .podium-container {
                height: 250px;
                padding: 15px;
            }

            .podium-profile {
                width: 80px;
                height: 80px;
            }

            .podium-1st .podium-profile {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Particles -->
    <div class="particles" id="particles"></div>
    
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <main class="col-12 px-md-4">
                <div class="game-container">
                    <h1 class="game-title">🏆 LEADERBOARD ARENA 🏆</h1>

                <!-- Filters -->
                <div class="card leaderboard-card course-filter-card mb-4">
                    <div class="card-body">
                        <form method="GET" action="leaderboard.php" class="row align-items-end">
                            <div class="col-md-3">
                                <label for="course_id" class="form-label fw-semibold">
                                    <i class="fas fa-book me-2"></i>Course
                                </label>
                                <select class="form-select" id="course_id" name="course_id" onchange="this.form.submit()">
                                    <option value="">All Courses</option>
                                    <?php foreach ($all_courses as $course): ?>
                                        <option value="<?= $course['id'] ?>" <?= $selected_course_id == $course['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="section_id" class="form-label fw-semibold">
                                    <i class="fas fa-users me-2"></i>Section
                                </label>
                                <select class="form-select" id="section_id" name="section_id" onchange="this.form.submit()">
                                    <option value="">All Sections</option>
                                    <?php foreach ($all_sections as $section): ?>
                                        <option value="<?= $section['id'] ?>" <?= $selected_section_id == $section['id'] ? 'selected' : '' ?>>
                                            <?= formatSectionName($section) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="year" class="form-label fw-semibold">
                                    <i class="fas fa-calendar me-2"></i>Year Level
                                </label>
                                <select class="form-select" id="year" name="year" onchange="this.form.submit()">
                                    <option value="">All Years</option>
                                    <?php foreach ($year_levels as $year): ?>
                                        <option value="<?= $year['value'] ?>" <?= $selected_year == $year['value'] ? 'selected' : '' ?>>
                                            <?= $year['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <a href="leaderboard.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times me-2"></i>Clear All Filters
                                </a>
                            </div>
                        </form>
                        <?php if (!empty($selected_course_id) || !empty($selected_section_id) || !empty($selected_year)): ?>
                            <div class="mt-3">
                                <span class="badge filter-info-badge">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php
                                    $filter_texts = [];
                                    if (!empty($selected_course_id)) {
                                        $course_name = '';
                                        foreach ($all_courses as $course) {
                                            if ($course['id'] == $selected_course_id) {
                                                $course_name = $course['course_name'];
                                                break;
                                            }
                                        }
                                        $filter_texts[] = "Course: $course_name";
                                    }
                                    if (!empty($selected_section_id)) {
                                        $section_name = '';
                                        foreach ($all_sections as $section) {
                                            if ($section['id'] == $selected_section_id) {
                                                $section_name = formatSectionName($section);
                                                break;
                                            }
                                        }
                                        $filter_texts[] = "Section: $section_name";
                                    }
                                    if (!empty($selected_year)) {
                                        $year_label = '';
                                        foreach ($year_levels as $year) {
                                            if ($year['value'] == $selected_year) {
                                                $year_label = $year['label'];
                                                break;
                                            }
                                        }
                                        $filter_texts[] = "Year: $year_label";
                                    }
                                    echo "Showing leaderboard for: " . implode(', ', $filter_texts);
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User's Current Position -->
                <div class="card leaderboard-card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                    <h5 class="card-title">
                                        <i class="fas fa-crown me-2"></i>Your Current Position
                                        <?php 
                                        $level = '';
                                        if ($user_rank <= 3) $level = 'level-expert';
                                        elseif ($user_rank <= 10) $level = 'level-advanced';
                                        elseif ($user_rank <= 20) $level = 'level-intermediate';
                                        else $level = 'level-beginner';
                                        ?>
                                        <span class="level-indicator <?php echo $level; ?>">
                                            <?php 
                                            if ($user_rank <= 3) echo 'EXPERT';
                                            elseif ($user_rank <= 10) echo 'ADVANCED';
                                            elseif ($user_rank <= 20) echo 'INTERMEDIATE';
                                            else echo 'BEGINNER';
                                            ?>
                                        </span>
                                    </h5>
                                <div class="d-flex align-items-center">
                                    <div class="rank-badge me-3"><?php echo $user_rank; ?></div>
                                        <div class="me-3">
                                            <img src="<?php echo getProfilePictureUrl($current_user['profile_picture'] ?? null, 'medium'); ?>" 
                                                 alt="<?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>"
                                                 class="student-profile-pic">
                                        </div>
                                    <div>
                                        <?php if ($current_user): ?>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></h6>
                                            <small class="text-muted">
                                                Rank #<?php echo $user_rank; ?> out of <?php echo $total_students; ?> students
                                                <?php if ($current_user['section_name'] && $current_user['section_year']): ?>
                                                    • <?php echo formatSectionName(['section_year' => $current_user['section_year'], 'section_name' => $current_user['section_name']]); ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($current_user_basic['first_name'] . ' ' . $current_user_basic['last_name']); ?></h6>
                                            <small class="text-muted">
                                                Not in filtered results • <?php echo $total_students; ?> students found
                                            </small>
                                        <?php endif; ?>
                                            
                                            <!-- Achievement Badges -->
                                            <?php if ($current_user): ?>
                                            <div class="mt-2">
                                                <?php if (($current_user['completed_modules'] ?? 0) > 0): ?>
                                                    <span class="achievement-badge badge-modules" title="Modules Completed"><i class="fas fa-book"></i></span>
                                                <?php endif; ?>
                                                <?php if (($current_user['average_score'] ?? 0) > 70): ?>
                                                    <span class="achievement-badge badge-scores" title="High Scorer"><i class="fas fa-star"></i></span>
                                                <?php endif; ?>
                                                <?php if (($current_user['watched_videos'] ?? 0) > 0): ?>
                                                    <span class="achievement-badge badge-videos" title="Video Watcher"><i class="fas fa-play"></i></span>
                                                <?php endif; ?>
                                                <?php if (($current_user['badge_count'] ?? 0) > 0): ?>
                                                    <span class="achievement-badge badge-badges" title="Badge Collector"><i class="fas fa-trophy"></i></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <?php if ($current_user): ?>
                                    <div class="stats-grid leaderboard-stats">
                                        <div class="stat-card">
                                            <div class="stat-value"><?php echo round($current_user['calculated_score'] ?? 0); ?></div>
                                            <div class="stat-label">Score</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-value"><?php echo $current_user['badge_count'] ?? 0; ?></div>
                                            <div class="stat-label">Badges</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-value"><?php echo $current_user['completed_modules'] ?? 0; ?></div>
                                            <div class="stat-label">Modules</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-value"><?php echo round($current_user['average_score'] ?? 0, 1); ?>%</div>
                                            <div class="stat-label">Avg Score</div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="stats-grid leaderboard-stats">
                                        <div class="stat-card">
                                            <div class="stat-value">--</div>
                                            <div class="stat-label">Score</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-value">--</div>
                                            <div class="stat-label">Badges</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-value">--</div>
                                            <div class="stat-label">Modules</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-value">--</div>
                                            <div class="stat-label">Avg Score</div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Main Leaderboard -->
                    <div class="col-lg-8">
                        <div class="card leaderboard-card">
                            <div class="card-header" style="background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(46, 94, 78, 0.1)); border-bottom: 2px solid rgba(255, 215, 0, 0.2);">
                                    <h5 class="mb-0" style="background: linear-gradient(45deg, #FFD700, #2E5E4E, #FFD700); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <i class="fas fa-medal me-2" style="color: #FFD700; text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);"></i>Top Students by Points
                                    </h5>
                            </div>
                            <div class="card-body">
                                    <!-- Podium for Top 3 -->
                                    <div class="podium-container mb-4">
                                        <!-- Floating decorative stars -->
                                        <div class="floating-star">⭐</div>
                                        <div class="floating-star">✨</div>
                                        <div class="floating-star">🌟</div>
                                        <div class="floating-star">💫</div>
                                        
                                        <div class="podium-row">
                                            <!-- 2nd Place -->
                                            <?php $student_2nd = $leaderboard[1] ?? null; ?>
                                            <div class="podium-item podium-2nd">
                                                <div class="podium-rank">2</div>
                                                <a href="#" class="podium-profile-link" data-bs-toggle="modal" data-bs-target="#studentModal" 
                                                   <?php if ($student_2nd): ?>
                                                   data-student-id="<?php echo $student_2nd['id']; ?>"
                                                   data-student-name="<?php echo htmlspecialchars($student_2nd['first_name'] . ' ' . $student_2nd['last_name']); ?>"
                                                   data-student-email="<?php echo htmlspecialchars($student_2nd['email']); ?>"
                                                   data-student-section="<?php echo $student_2nd['section_name'] && $student_2nd['section_year'] ? formatSectionName(['section_year' => $student_2nd['section_year'], 'section_name' => $student_2nd['section_name']]) : 'No Section'; ?>"
                                                   data-student-score="<?php echo round($student_2nd['calculated_score'] ?? 0); ?>"
                                                   data-student-badges="<?php echo $student_2nd['badge_count']; ?>"
                                                   data-student-modules="<?php echo $student_2nd['completed_modules']; ?>"
                                                   data-student-videos="<?php echo $student_2nd['watched_videos']; ?>"
                                                   data-student-avg-score="<?php echo round($student_2nd['average_score'] ?? 0, 1); ?>"
                                                   data-student-rank="2"
                                                   <?php endif; ?>>
                                                    <div class="podium-profile">
                                                        <?php if ($student_2nd): ?>
                                                            <img src="<?php echo getProfilePictureUrl($student_2nd['profile_picture'] ?? null, 'large'); ?>" 
                                                                 alt="<?php echo htmlspecialchars($student_2nd['first_name'] . ' ' . $student_2nd['last_name']); ?>"
                                                                 class="podium-profile-pic">
                                                        <?php endif; ?>
                                                    </div>
                                                </a>
                                                <div class="podium-name"><?php echo $student_2nd ? htmlspecialchars($student_2nd['first_name'] . ' ' . $student_2nd['last_name']) : 'N/A'; ?></div>
                                                <?php if ($student_2nd && $student_2nd['section_name'] && $student_2nd['section_year']): ?>
                                                    <div class="podium-section"><?php echo formatSectionName(['section_year' => $student_2nd['section_year'], 'section_name' => $student_2nd['section_name']]); ?></div>
                                                <?php endif; ?>
                                                <div class="podium-score"><?php echo $student_2nd ? round($student_2nd['calculated_score'] ?? 0) : 0; ?> pts</div>
                                            </div>
                                            
                                            <!-- 1st Place -->
                                            <?php $student_1st = $leaderboard[0] ?? null; ?>
                                            <div class="podium-item podium-1st">
                                                <div class="podium-rank">1</div>
                                                <a href="#" class="podium-profile-link" data-bs-toggle="modal" data-bs-target="#studentModal" 
                                                   <?php if ($student_1st): ?>
                                                   data-student-id="<?php echo $student_1st['id']; ?>"
                                                   data-student-name="<?php echo htmlspecialchars($student_1st['first_name'] . ' ' . $student_1st['last_name']); ?>"
                                                   data-student-email="<?php echo htmlspecialchars($student_1st['email']); ?>"
                                                   data-student-section="<?php echo $student_1st['section_name'] && $student_1st['section_year'] ? formatSectionName(['section_year' => $student_1st['section_year'], 'section_name' => $student_1st['section_name']]) : 'No Section'; ?>"
                                                   data-student-score="<?php echo round($student_1st['calculated_score'] ?? 0); ?>"
                                                   data-student-badges="<?php echo $student_1st['badge_count']; ?>"
                                                   data-student-modules="<?php echo $student_1st['completed_modules']; ?>"
                                                   data-student-videos="<?php echo $student_1st['watched_videos']; ?>"
                                                   data-student-avg-score="<?php echo round($student_1st['average_score'] ?? 0, 1); ?>"
                                                   data-student-rank="1"
                                                   <?php endif; ?>>
                                                    <div class="podium-profile">
                                                        <?php if ($student_1st): ?>
                                                            <img src="<?php echo getProfilePictureUrl($student_1st['profile_picture'] ?? null, 'large'); ?>" 
                                                                 alt="<?php echo htmlspecialchars($student_1st['first_name'] . ' ' . $student_1st['last_name']); ?>"
                                                                 class="podium-profile-pic">
                                                        <?php endif; ?>
                                                    </div>
                                                </a>
                                                <div class="podium-name"><?php echo $student_1st ? htmlspecialchars($student_1st['first_name'] . ' ' . $student_1st['last_name']) : 'N/A'; ?></div>
                                                <?php if ($student_1st && $student_1st['section_name'] && $student_1st['section_year']): ?>
                                                    <div class="podium-section"><?php echo formatSectionName(['section_year' => $student_1st['section_year'], 'section_name' => $student_1st['section_name']]); ?></div>
                                                <?php endif; ?>
                                                <div class="podium-score"><?php echo $student_1st ? round($student_1st['calculated_score'] ?? 0) : 0; ?> pts</div>
                                            </div>
                                            
                                            <!-- 3rd Place -->
                                            <?php $student_3rd = $leaderboard[2] ?? null; ?>
                                            <div class="podium-item podium-3rd">
                                                <div class="podium-rank">3</div>
                                                <a href="#" class="podium-profile-link" data-bs-toggle="modal" data-bs-target="#studentModal" 
                                                   <?php if ($student_3rd): ?>
                                                   data-student-id="<?php echo $student_3rd['id']; ?>"
                                                   data-student-name="<?php echo htmlspecialchars($student_3rd['first_name'] . ' ' . $student_3rd['last_name']); ?>"
                                                   data-student-email="<?php echo htmlspecialchars($student_3rd['email']); ?>"
                                                   data-student-section="<?php echo $student_3rd['section_name'] && $student_3rd['section_year'] ? formatSectionName(['section_year' => $student_3rd['section_year'], 'section_name' => $student_3rd['section_name']]) : 'No Section'; ?>"
                                                   data-student-score="<?php echo round($student_3rd['calculated_score'] ?? 0); ?>"
                                                   data-student-badges="<?php echo $student_3rd['badge_count']; ?>"
                                                   data-student-modules="<?php echo $student_3rd['completed_modules']; ?>"
                                                   data-student-videos="<?php echo $student_3rd['watched_videos']; ?>"
                                                   data-student-avg-score="<?php echo round($student_3rd['average_score'] ?? 0, 1); ?>"
                                                   data-student-rank="3"
                                                   <?php endif; ?>>
                                                    <div class="podium-profile">
                                                        <?php if ($student_3rd): ?>
                                                            <img src="<?php echo getProfilePictureUrl($student_3rd['profile_picture'] ?? null, 'large'); ?>" 
                                                                 alt="<?php echo htmlspecialchars($student_3rd['first_name'] . ' ' . $student_3rd['last_name']); ?>"
                                                                 class="podium-profile-pic">
                                                        <?php endif; ?>
                                                    </div>
                                                </a>
                                                <div class="podium-name"><?php echo $student_3rd ? htmlspecialchars($student_3rd['first_name'] . ' ' . $student_3rd['last_name']) : 'N/A'; ?></div>
                                                <?php if ($student_3rd && $student_3rd['section_name'] && $student_3rd['section_year']): ?>
                                                    <div class="podium-section"><?php echo formatSectionName(['section_year' => $student_3rd['section_year'], 'section_name' => $student_3rd['section_name']]); ?></div>
                                                <?php endif; ?>
                                                <div class="podium-score"><?php echo $student_3rd ? round($student_3rd['calculated_score'] ?? 0) : 0; ?> pts</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Rest of Leaderboard (4th onwards) -->
                                    <div class="main-leaderboard-container">
                                        <div class="row">
                                            <?php foreach (array_slice($leaderboard, 3) as $index => $student): ?>
                                        <?php 
                                        $rank_class = '';
                                        if ($student['id'] == $user_id) $rank_class = 'current-user';
                                        ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="leaderboard-item d-flex align-items-center p-3 rounded <?php echo $rank_class; ?>" 
                                                 data-student-id="<?php echo $student['id']; ?>"
                                                 style="animation-delay: <?php echo ($index + 3) * 0.1; ?>s;">
                                                <div class="rank-badge me-3"><?php echo $index + 4; ?></div>
                                                <div class="me-3">
                                                    <a href="#" class="student-profile-link" data-bs-toggle="modal" data-bs-target="#studentModal" 
                                                       data-student-id="<?php echo $student['id']; ?>"
                                                       data-student-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                       data-student-email="<?php echo htmlspecialchars($student['email']); ?>"
                                                       data-student-section="<?php echo $student['section_name'] && $student['section_year'] ? formatSectionName(['section_year' => $student['section_year'], 'section_name' => $student['section_name']]) : 'No Section'; ?>"
                                                       data-student-score="<?php echo round($student['calculated_score'] ?? 0); ?>"
                                                       data-student-badges="<?php echo $student['badge_count']; ?>"
                                                       data-student-modules="<?php echo $student['completed_modules']; ?>"
                                                       data-student-videos="<?php echo $student['watched_videos']; ?>"
                                                       data-student-avg-score="<?php echo round($student['average_score'] ?? 0, 1); ?>"
                                                       data-student-rank="<?php echo $index + 4; ?>">
                                                        <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'medium'); ?>" 
                                                             alt="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                             class="student-profile-pic">
                                                    </a>
                                                </div>
                                                                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo $student['badge_count']; ?> badges • 
                                                <?php echo $student['completed_modules']; ?> modules • 
                                                <?php echo $student['watched_videos']; ?> videos
                                                <?php if ($student['section_name'] && $student['section_year']): ?>
                                                    • <?php echo formatSectionName(['section_year' => $student['section_year'], 'section_name' => $student['section_name']]); ?>
                                                <?php endif; ?>
                                            </small>
                                                        
                                                        <!-- Progress Bar -->
                                                        <div class="progress-container mt-2">
                                                            <div class="progress-bar" style="width: <?php echo min(100, ($student['calculated_score'] / max(array_column($leaderboard, 'calculated_score'))) * 100); ?>%"></div>
                                                        </div>
                                                </div>
                                                <div class="text-end">
                                                        <h6 class="mb-0"><?php echo round($student['calculated_score'] ?? 0); ?> pts</h6>
                                                    <?php if ($student['average_score']): ?>
                                                        <small class="text-muted"><?php echo round($student['average_score'] ?? 0, 1); ?>% avg</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Performers -->
                    <div class="col-lg-4">
                        <!-- Top Module Completers -->
                        <div class="card leaderboard-card mb-3">
                            <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-book-open me-2"></i>Top Module Completers
                                    </h6>
                            </div>
                            <div class="card-body">
                                <div class="top-performers-container">
                                    <?php foreach ($top_modules as $index => $student): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                            <span><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                        </div>
                                        <span class="badge bg-success"><?php echo $student['completed_modules']; ?> modules</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Top Scorers -->
                        <div class="card leaderboard-card mb-3">
                            <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-star me-2"></i>Top Assessment Scorers
                                    </h6>
                            </div>
                            <div class="card-body">
                                <div class="top-performers-container">
                                    <?php foreach ($top_scores as $index => $student): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-warning me-2"><?php echo $index + 1; ?></span>
                                            <span><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                        </div>
                                        <span class="badge bg-info"><?php echo round($student['average_score'] ?? 0, 1); ?>%</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Top Video Watchers -->
                        <div class="card leaderboard-card">
                            <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-play-circle me-2"></i>Top Video Watchers
                                    </h6>
                            </div>
                            <div class="card-body">
                                <div class="top-performers-container">
                                    <?php foreach ($top_videos as $index => $student): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-info me-2"><?php echo $index + 1; ?></span>
                                            <span><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                        </div>
                                        <span class="badge bg-secondary"><?php echo $student['watched_videos']; ?> videos</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Student Information Modal -->
    <div class="modal fade" id="studentModal" tabindex="-1" aria-labelledby="studentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="studentModalLabel">
                        <i class="fas fa-user me-2"></i>Student Information
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Profile Picture and Basic Info -->
                        <div class="col-md-4 text-center">
                            <img id="modalStudentAvatar" src="" alt="Student Avatar" class="img-fluid rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                            <h4 id="modalStudentName" class="mb-2"></h4>
                            <p id="modalStudentEmail" class="text-muted mb-2"></p>
                            <span id="modalStudentSection" class="badge bg-success mb-3"></span>
                        </div>
                        
                        <!-- Statistics -->
                        <div class="col-md-8">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-bar me-2"></i>Academic Statistics
                            </h5>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="card text-center h-100">
                                        <div class="card-body">
                                            <h3 id="modalStudentRank" class="text-primary mb-1"></h3>
                                            <small class="text-muted">Current Rank</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card text-center h-100">
                                        <div class="card-body">
                                            <h3 id="modalStudentScore" class="text-success mb-1"></h3>
                                            <small class="text-muted">Total Score</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="card text-center h-100">
                                        <div class="card-body">
                                            <h4 id="modalStudentBadges" class="text-warning mb-1"></h4>
                                            <small class="text-muted">Badges Earned</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card text-center h-100">
                                        <div class="card-body">
                                            <h4 id="modalStudentModules" class="text-info mb-1"></h4>
                                            <small class="text-muted">Modules Completed</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-6">
                                    <div class="card text-center h-100">
                                        <div class="card-body">
                                            <h4 id="modalStudentVideos" class="text-secondary mb-1"></h4>
                                            <small class="text-muted">Videos Watched</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card text-center h-100">
                                        <div class="card-body">
                                            <h4 id="modalStudentAvgScore" class="text-danger mb-1"></h4>
                                            <small class="text-muted">Average Score</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="../assets/js/pusher-client.js"></script>
    <script>
        // Pusher configuration for leaderboard
        window.pusherConfig = <?php echo json_encode(PusherConfig::getConfig()); ?>;
        window.currentUserId = <?php echo json_encode($user_id); ?>;
        window.currentUserRole = '<?php echo $_SESSION['role']; ?>';
    </script>
    <script>
        // Create floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
                particlesContainer.appendChild(particle);
            }
        }
        
        // Animate leaderboard items on load
        function animateLeaderboard() {
            const items = document.querySelectorAll('.leaderboard-item');
            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
        
        // Animate podium items
        function animatePodium() {
            const podiumItems = document.querySelectorAll('.podium-item');
            podiumItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(50px) scale(0.8)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.8s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0) scale(1)';
                }, index * 200);
            });
        }
        
        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            animateLeaderboard();
            animatePodium();
            initializeStudentModal();
            enhanceLeaderboardScrolling();
        });
        
        // Enhanced scrolling behavior for leaderboard
        function enhanceLeaderboardScrolling() {
            // Main leaderboard scrolling
            const mainLeaderboardContainer = document.querySelector('.main-leaderboard-container');
            if (mainLeaderboardContainer) {
                mainLeaderboardContainer.style.scrollBehavior = 'smooth';
                const mainLeaderboardCard = mainLeaderboardContainer.closest('.card');
                if (mainLeaderboardCard) {
                    addMainLeaderboardScrollIndicators(mainLeaderboardContainer, mainLeaderboardCard);
                }
            }
            
            // Top performers scrolling
            const topPerformersContainers = document.querySelectorAll('.top-performers-container');
            topPerformersContainers.forEach((container, index) => {
                container.style.scrollBehavior = 'smooth';
                const topPerformersCard = container.closest('.card');
                if (topPerformersCard) {
                    addTopPerformersScrollIndicators(container, topPerformersCard);
                    addTopPerformersInteractions(container, index);
                }
            });
        }

        // Add interactive features to top performers
        function addTopPerformersInteractions(container, sectionIndex) {
            const items = container.querySelectorAll('.d-flex');
            
            items.forEach((item, index) => {
                // Add click handler for more details
                item.style.cursor = 'pointer';
                item.addEventListener('click', function() {
                    // Add a subtle click animation
                    this.style.transform = 'translateX(8px) scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = 'translateX(8px) scale(1.02)';
                    }, 150);
                });

                // Add hover sound effect (visual feedback)
                item.addEventListener('mouseenter', function() {
                    this.style.transition = 'all 0.2s ease';
                });

                // Add ranking highlight effect
                const rankBadge = item.querySelector('.badge:first-child');
                if (rankBadge) {
                    rankBadge.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.2) rotate(5deg)';
                        this.style.boxShadow = '0 6px 20px rgba(0,0,0,0.4)';
                    });

                    rankBadge.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1) rotate(0deg)';
                        this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
                    });
                }

                // Add achievement badge hover effects
                const achievementBadge = item.querySelector('.badge:last-child');
                if (achievementBadge) {
                    achievementBadge.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.1)';
                        this.style.boxShadow = '0 4px 15px currentColor';
                    });

                    achievementBadge.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                        this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
                    });
                }
            });

            // Add section-specific animations
            const sectionNames = ['Module Completers', 'Assessment Scorers', 'Video Watchers'];
            const sectionIcons = ['fas fa-book-open', 'fas fa-star', 'fas fa-play-circle'];
            
            if (sectionIndex < sectionNames.length) {
                const cardHeader = container.closest('.card').querySelector('.card-header h6');
                if (cardHeader) {
                    cardHeader.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.05)';
                        this.style.color = '#1a3d32';
                    });

                    cardHeader.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                        this.style.color = '#2E5E4E';
                    });
                }
            }
        }
        
        // Add scroll indicators to main leaderboard
        function addMainLeaderboardScrollIndicators(scrollContainer, cardContainer) {
            const scrollIndicator = document.createElement('div');
            scrollIndicator.className = 'main-leaderboard-scroll-indicator';
            scrollIndicator.innerHTML = `
                <div class="main-leaderboard-scroll-indicator-content">
                    <i class="fas fa-chevron-up main-leaderboard-scroll-indicator-top"></i>
                    <i class="fas fa-chevron-down main-leaderboard-scroll-indicator-bottom"></i>
                </div>
            `;
            
            cardContainer.style.position = 'relative';
            cardContainer.appendChild(scrollIndicator);
            
            function updateMainLeaderboardScrollIndicators() {
                const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
                const isAtTop = scrollContainer.scrollTop === 0;
                const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
                
                if (isScrollable) {
                    scrollIndicator.classList.add('show');
                    scrollIndicator.querySelector('.main-leaderboard-scroll-indicator-top').classList.toggle('hide', isAtTop);
                    scrollIndicator.querySelector('.main-leaderboard-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
                } else {
                    scrollIndicator.classList.remove('show');
                }
            }
            
            updateMainLeaderboardScrollIndicators();
            scrollContainer.addEventListener('scroll', updateMainLeaderboardScrollIndicators);
            window.addEventListener('resize', updateMainLeaderboardScrollIndicators);
        }
        
        // Add scroll indicators to top performers
        function addTopPerformersScrollIndicators(scrollContainer, cardContainer) {
            const scrollIndicator = document.createElement('div');
            scrollIndicator.className = 'top-performers-scroll-indicator';
            scrollIndicator.innerHTML = `
                <div class="top-performers-scroll-indicator-content">
                    <i class="fas fa-chevron-up top-performers-scroll-indicator-top"></i>
                    <i class="fas fa-chevron-down top-performers-scroll-indicator-bottom"></i>
                </div>
            `;
            
            cardContainer.style.position = 'relative';
            cardContainer.appendChild(scrollIndicator);
            
            function updateTopPerformersScrollIndicators() {
                const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
                const isAtTop = scrollContainer.scrollTop === 0;
                const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
                
                if (isScrollable) {
                    scrollIndicator.classList.add('show');
                    scrollIndicator.querySelector('.top-performers-scroll-indicator-top').classList.toggle('hide', isAtTop);
                    scrollIndicator.querySelector('.top-performers-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
                } else {
                    scrollIndicator.classList.remove('show');
                }
            }
            
            updateTopPerformersScrollIndicators();
            scrollContainer.addEventListener('scroll', updateTopPerformersScrollIndicators);
            window.addEventListener('resize', updateTopPerformersScrollIndicators);
        }

        // Initialize student modal functionality
        function initializeStudentModal() {
            const studentModal = document.getElementById('studentModal');
            if (studentModal) {
                studentModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    
                    // Get student data from data attributes
                    const studentId = button.getAttribute('data-student-id');
                    const studentName = button.getAttribute('data-student-name');
                    const studentEmail = button.getAttribute('data-student-email');
                    const studentSection = button.getAttribute('data-student-section');
                    const studentScore = button.getAttribute('data-student-score');
                    const studentBadges = button.getAttribute('data-student-badges');
                    const studentModules = button.getAttribute('data-student-modules');
                    const studentVideos = button.getAttribute('data-student-videos');
                    const studentAvgScore = button.getAttribute('data-student-avg-score');
                    const studentRank = button.getAttribute('data-student-rank');
                    
                    // Get the profile picture from the clicked image
                    const profileImg = button.querySelector('img');
                    const profileSrc = profileImg ? profileImg.src : '';
                    
                    // Update modal content
                    document.getElementById('modalStudentAvatar').src = profileSrc;
                    document.getElementById('modalStudentName').textContent = studentName || 'Unknown Student';
                    document.getElementById('modalStudentEmail').textContent = studentEmail || 'No email available';
                    document.getElementById('modalStudentSection').textContent = studentSection || 'No Section';
                    document.getElementById('modalStudentRank').textContent = '#' + (studentRank || 'N/A');
                    document.getElementById('modalStudentScore').textContent = (studentScore || '0') + ' pts';
                    document.getElementById('modalStudentBadges').textContent = studentBadges || '0';
                    document.getElementById('modalStudentModules').textContent = studentModules || '0';
                    document.getElementById('modalStudentVideos').textContent = studentVideos || '0';
                    document.getElementById('modalStudentAvgScore').textContent = (studentAvgScore || '0') + '%';
                });
            }
        }
    </script>
</body>
</html> 