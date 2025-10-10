<?php
$page_title = 'Badge Management';
require_once '../includes/header.php';
requireRole('teacher');

$message = '';
$message_type = '';

// Check if created_by column exists
$stmt = $db->prepare("SHOW COLUMNS FROM badges LIKE 'created_by'");
$stmt->execute();
$has_created_by = $stmt->fetch();

// Handle badge creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['badge_name'] ?? '');
    $desc = trim($_POST['badge_description'] ?? '');
    $type = trim($_POST['badge_type'] ?? '');
    $criteria_type = trim($_POST['criteria_type'] ?? '');
    $criteria_value = (int)($_POST['criteria_value'] ?? 0);
    $points_value = (int)($_POST['points_value'] ?? 0);
    $icon = null; // Will be set to default Bootstrap icon
    
    // Build criteria JSON from simplified inputs
    $criteria = json_encode([$criteria_type => $criteria_value]);
    
    // Bootstrap icon mapping for badges
    $type_defaults = [
        'course_completion' => 'bi-mortarboard',
        'high_score' => 'bi-trophy',
        'participation' => 'bi-people',
        'streak' => 'bi-lightning',
        'special' => 'bi-star'
    ];
    
    $name_mappings = [
        'first course complete' => 'bi-play-circle',
        'first steps' => 'bi-play-circle',
        'high achiever' => 'bi-award',
        'perfect score' => 'bi-check-circle-fill',
        'consistent performer' => 'bi-graph-up',
        'assessment warrior' => 'bi-shield-check',
        'video learner' => 'bi-play-btn',
        'knowledge seeker' => 'bi-search',
        'focused learner' => 'bi-eye',
        'daily learner' => 'bi-calendar-check',
        'dedicated student' => 'bi-book',
        'weekend warrior' => 'bi-calendar-week',
        'early bird' => 'bi-sunrise',
        'speed demon' => 'bi-lightning-charge',
        'perfect attendance' => 'bi-calendar-check-fill',
        'module master' => 'bi-collection',
        'assessment ace' => 'bi-patch-check',
        'freshman explorer' => 'bi-compass',
        'sophomore scholar' => 'bi-book-half',
        'junior expert' => 'bi-gear',
        'senior specialist' => 'bi-tools',
        'easy rider' => 'bi-bicycle',
        'medium master' => 'bi-speedometer2',
        'hard core' => 'bi-fire',
        'night owl' => 'bi-moon',
        'morning person' => 'bi-sun',
        'century club' => 'bi-100',
        'point collector' => 'bi-coin',
        'legend' => 'bi-star-fill',
        'active participant' => 'bi-chat-dots',
        'course master' => 'bi-mortarboard-fill',
        'academic excellence' => 'bi-award-fill',
        'nice' => 'bi-emoji-smile'
    ];
    
    // Check if a specific Bootstrap icon was selected
    if (isset($_POST['badge_icon_select']) && !empty($_POST['badge_icon_select'])) {
        $icon = $_POST['badge_icon_select'];
    } else {
        // Assign default Bootstrap icon based on badge name or type
        $badge_name_lower = strtolower(trim($name));
        if (isset($name_mappings[$badge_name_lower])) {
            $icon = $name_mappings[$badge_name_lower];
        } elseif (isset($type_defaults[$type])) {
            $icon = $type_defaults[$type];
        } else {
            $icon = 'bi-award'; // Default Bootstrap icon
        }
    }
    
    if (empty($name) || empty($desc) || empty($type) || empty($criteria_type) || $criteria_value <= 0) {
        $message = 'All fields are required and criteria value must be greater than 0.';
        $message_type = 'danger';
    } else {
        if ($has_created_by) {
            if ($icon) {
                $stmt = $db->prepare('INSERT INTO badges (badge_name, badge_description, badge_icon, badge_type, criteria, points_value, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $desc, $icon, $type, $criteria, $points_value, $_SESSION['user_id']]);
            } else {
                $stmt = $db->prepare('INSERT INTO badges (badge_name, badge_description, badge_type, criteria, points_value, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $desc, $type, $criteria, $points_value, $_SESSION['user_id']]);
            }
        } else {
            if ($icon) {
                $stmt = $db->prepare('INSERT INTO badges (badge_name, badge_description, badge_icon, badge_type, criteria, points_value) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $desc, $icon, $type, $criteria, $points_value]);
            } else {
                $stmt = $db->prepare('INSERT INTO badges (badge_name, badge_description, badge_type, criteria, points_value) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $desc, $type, $criteria, $points_value]);
            }
        }
        $message = 'Badge created successfully!';
        $message_type = 'success';
    }
}

// Handle badge edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)$_POST['badge_id'];
    $name = trim($_POST['badge_name'] ?? '');
    $desc = trim($_POST['badge_description'] ?? '');
    $type = trim($_POST['badge_type'] ?? '');
    $criteria_type = trim($_POST['criteria_type'] ?? '');
    $criteria_value = (int)($_POST['criteria_value'] ?? 0);
    $points_value = (int)($_POST['points_value'] ?? 0);
    $icon = null; // Will be set to current icon or new Bootstrap icon
    
    // Build criteria JSON from simplified inputs
    $criteria = json_encode([$criteria_type => $criteria_value]);
    
    // Bootstrap icon mapping for badges
    $type_defaults = [
        'course_completion' => 'bi-mortarboard',
        'high_score' => 'bi-trophy',
        'participation' => 'bi-people',
        'streak' => 'bi-lightning',
        'special' => 'bi-star'
    ];
    
    $name_mappings = [
        'first course complete' => 'bi-play-circle',
        'first steps' => 'bi-play-circle',
        'high achiever' => 'bi-award',
        'perfect score' => 'bi-check-circle-fill',
        'consistent performer' => 'bi-graph-up',
        'assessment warrior' => 'bi-shield-check',
        'video learner' => 'bi-play-btn',
        'knowledge seeker' => 'bi-search',
        'focused learner' => 'bi-eye',
        'daily learner' => 'bi-calendar-check',
        'dedicated student' => 'bi-book',
        'weekend warrior' => 'bi-calendar-week',
        'early bird' => 'bi-sunrise',
        'speed demon' => 'bi-lightning-charge',
        'perfect attendance' => 'bi-calendar-check-fill',
        'module master' => 'bi-collection',
        'assessment ace' => 'bi-patch-check',
        'freshman explorer' => 'bi-compass',
        'sophomore scholar' => 'bi-book-half',
        'junior expert' => 'bi-gear',
        'senior specialist' => 'bi-tools',
        'easy rider' => 'bi-bicycle',
        'medium master' => 'bi-speedometer2',
        'hard core' => 'bi-fire',
        'night owl' => 'bi-moon',
        'morning person' => 'bi-sun',
        'century club' => 'bi-100',
        'point collector' => 'bi-coin',
        'legend' => 'bi-star-fill',
        'active participant' => 'bi-chat-dots',
        'course master' => 'bi-mortarboard-fill',
        'academic excellence' => 'bi-award-fill',
        'nice' => 'bi-emoji-smile'
    ];
    
    // Verify teacher owns this badge
    $stmt = $db->prepare('SELECT id FROM badges WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        $message = 'You can only edit badges you created.';
        $message_type = 'danger';
    } else {
        // Get current icon
        $stmt = $db->prepare('SELECT badge_icon FROM badges WHERE id = ?');
        $stmt->execute([$id]);
        $current_badge = $stmt->fetch();
        $icon = $current_badge['badge_icon']; // Keep current icon by default
        
        // Check if a specific Bootstrap icon was selected
        if (isset($_POST['badge_icon_select']) && !empty($_POST['badge_icon_select'])) {
            $icon = $_POST['badge_icon_select'];
        } elseif (empty($icon) || $icon === 'default_badge.png' || strpos($icon, '.png') !== false) {
            // Assign default Bootstrap icon if no icon or using old image system
            $badge_name_lower = strtolower(trim($name));
            if (isset($name_mappings[$badge_name_lower])) {
                $icon = $name_mappings[$badge_name_lower];
            } elseif (isset($type_defaults[$type])) {
                $icon = $type_defaults[$type];
            } else {
                $icon = 'bi-award'; // Default Bootstrap icon
            }
        }
        
        if (empty($name) || empty($desc) || empty($type) || empty($criteria_type) || $criteria_value <= 0) {
            $message = 'All fields are required and criteria value must be greater than 0.';
            $message_type = 'danger';
        } else {
            if ($icon && $icon !== $current_badge['badge_icon']) {
                // New icon uploaded
                $stmt = $db->prepare('UPDATE badges SET badge_name=?, badge_description=?, badge_icon=?, badge_type=?, criteria=?, points_value=? WHERE id=? AND created_by=?');
                $stmt->execute([$name, $desc, $icon, $type, $criteria, $points_value, $id, $_SESSION['user_id']]);
            } else {
                // No new icon, keep existing
                $stmt = $db->prepare('UPDATE badges SET badge_name=?, badge_description=?, badge_type=?, criteria=?, points_value=? WHERE id=? AND created_by=?');
                $stmt->execute([$name, $desc, $type, $criteria, $points_value, $id, $_SESSION['user_id']]);
            }
            $message = 'Badge updated successfully!';
            $message_type = 'success';
        }
    }
}

// Handle badge delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['badge_id'];
    
    // Verify teacher owns this badge
    $stmt = $db->prepare('SELECT id FROM badges WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        $message = 'You can only delete badges you created.';
        $message_type = 'danger';
    } else {
        $stmt = $db->prepare('DELETE FROM badges WHERE id=? AND created_by=?');
        $stmt->execute([$id, $_SESSION['user_id']]);
        $message = 'Badge deleted successfully.';
        $message_type = 'success';
    }
}

// Handle badge archive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive') {
    $id = (int)$_POST['badge_id'];
    
    // Verify teacher owns this badge
    $stmt = $db->prepare('SELECT id FROM badges WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        $message = 'You can only archive badges you created.';
        $message_type = 'danger';
    } else {
        $stmt = $db->prepare('UPDATE badges SET is_archived = 1 WHERE id = ? AND created_by = ?');
        $stmt->execute([$id, $_SESSION['user_id']]);
        $message = 'Badge archived successfully.';
        $message_type = 'success';
    }
}

// Handle badge recover
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recover') {
    $id = (int)$_POST['badge_id'];
    
    // Verify teacher owns this badge
    $stmt = $db->prepare('SELECT id FROM badges WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        $message = 'You can only recover badges you created.';
        $message_type = 'danger';
    } else {
        $stmt = $db->prepare('UPDATE badges SET is_archived = 0 WHERE id = ? AND created_by = ?');
        $stmt->execute([$id, $_SESSION['user_id']]);
        $message = 'Badge recovered successfully.';
        $message_type = 'success';
    }
}

// Get archive filter
$show_archived = sanitizeInput($_GET['show_archived'] ?? '0');

if ($has_created_by) {
    // Fetch teacher's badges and system badges with archive filtering
    $where_conditions = [];
    $params = [$_SESSION['user_id'], $_SESSION['user_id']];
    
    // Add archive filter - show only archived badges when requested, otherwise show only active badges
    if ($show_archived === '1') {
        $where_conditions[] = "b.is_archived = 1";
    } else {
        $where_conditions[] = "(b.is_archived = 0 OR b.is_archived IS NULL)";
    }
    
    $where_clause = !empty($where_conditions) ? 'AND ' . implode(' AND ', $where_conditions) : '';
    
    $stmt = $db->prepare("
        SELECT b.*, u.first_name, u.last_name,
               CASE WHEN b.created_by = ? THEN 1 ELSE 0 END as is_teacher_badge
        FROM badges b
        LEFT JOIN users u ON b.created_by = u.id
        WHERE (b.created_by = ? OR b.created_by IS NULL) $where_clause
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($params);
    $badges = $stmt->fetchAll();
} else {
    // Fallback for older badges table structure with archive filtering
    $where_conditions = [];
    $params = [];
    
    // Add archive filter - show only archived badges when requested, otherwise show only active badges
    if ($show_archived === '1') {
        $where_conditions[] = "b.is_archived = 1";
    } else {
        $where_conditions[] = "(b.is_archived = 0 OR b.is_archived IS NULL)";
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $stmt = $db->prepare("
        SELECT b.*, NULL as first_name, NULL as last_name, 0 as is_teacher_badge
        FROM badges b
        $where_clause
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($params);
    $badges = $stmt->fetchAll();
}

// Get badge statistics
$stmt = $db->prepare('
    SELECT 
        COUNT(*) as total_badges,
        COUNT(CASE WHEN created_by = ? THEN 1 END) as teacher_badges,
        COUNT(CASE WHEN created_by IS NULL THEN 1 END) as system_badges
    FROM badges
');
$stmt->execute([$_SESSION['user_id']]);
$badge_stats = $stmt->fetch();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* Import Google Fonts */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap');

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

.welcome-actions {
    position: relative;
    z-index: 1;
}

.quick-stats {
    display: flex;
    gap: 2rem;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1rem 1.5rem;
    width: fit-content;
}

.stat-item {
    text-align: center;
    color: white;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: white;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-label {
    display: block;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.9);
    margin-top: 0.25rem;
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

/* Enhanced Statistics Cards */
.stats-card {
    background: #F7FAF7;
    border: 1px solid #7DCB80;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stats-card .card-title {
    color: #2E5E4E;
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.stats-card .display-4 {
    color: #7DCB80;
    font-weight: 800;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Enhanced Section Headers */
.section-header {
    background: #2E5E4E;
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 15px;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.section-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
    pointer-events: none;
}

.section-header h3 {
    margin: 0;
    font-weight: 700;
    position: relative;
    z-index: 1;
}

.section-header .badge {
    background: #7DCB80;
    color: #2E5E4E;
    font-weight: 600;
    position: relative;
    z-index: 1;
}

/* Enhanced Badge Cards */
.badge-card {
    background: white;
    border: 1px solid #E8F5E8;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    height: 100%;
}

.badge-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: #7DCB80;
}

.badge-card .badge-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7DCB80 0%, #2E5E4E 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    margin: 0 auto 1rem;
}

.badge-card .badge-title {
    color: #2E5E4E;
    font-weight: 700;
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    text-align: center;
}

.badge-card .badge-description {
    color: #666;
    margin-bottom: 1rem;
    text-align: center;
    font-size: 0.9rem;
}

.badge-card .badge-type {
    background: #F7FAF7;
    color: #2E5E4E;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 1rem;
}

.badge-card .badge-points {
    color: #7DCB80;
    font-weight: 700;
    font-size: 1.1rem;
    text-align: center;
    margin-bottom: 1rem;
}

/* Enhanced Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.action-buttons .btn {
    border-radius: 20px;
    font-weight: 600;
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

/* Enhanced Create Badge Button */
.create-badge-btn {
    background: #7DCB80;
    color: white;
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 700;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(125, 203, 128, 0.3);
}

.create-badge-btn:hover {
    background: #2E5E4E;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(125, 203, 128, 0.4);
    color: white;
}

/* Enhanced Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: #F7FAF7;
    border-radius: 20px;
    border: 2px dashed #7DCB80;
}

.empty-state i {
    font-size: 4rem;
    color: #7DCB80;
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: #2E5E4E;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #666;
    margin-bottom: 0;
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
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Removed Sidebar -->
        <!-- Main content -->
        <main class="col-12 px-md-4">
            <!-- Enhanced Welcome Section -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="welcome-title">Badge Management System</h1>
                        <p class="welcome-subtitle">Create and manage achievement badges to motivate your students</p>
                        
                        <!-- Action Buttons -->
                        <div class="welcome-actions mt-3">
                            <button class="btn create-badge-btn" data-bs-toggle="modal" data-bs-target="#createBadgeModal">
                                <i class="bi bi-plus-circle me-2"></i>Create Badge
                            </button>
                            <a href="student_badges.php" class="btn btn-outline-light btn-lg ms-2">
                                <i class="bi bi-eye me-2"></i>View Student Badges
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex justify-content-end">
                        <!-- Quick Stats -->
                        <div class="quick-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $badge_stats['total_badges'] ?? 0; ?></span>
                                <span class="stat-label">Total Badges</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $badge_stats['awarded_badges'] ?? 0; ?></span>
                                <span class="stat-label">Awarded</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $badge_stats['my_badges'] ?? 0; ?></span>
                                <span class="stat-label">My Badges</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="welcome-decoration">
                    <i class="fas fa-medal"></i>
                </div>
                <div class="floating-shapes"></div>
                <div class="accent-line"></div>
            </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Badge Statistics -->
    <div class="row mb-4 badges-stats">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $badge_stats['total_badges'] ?></h4>
                            <small>Total Badges</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-trophy fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $badge_stats['teacher_badges'] ?></h4>
                            <small>Your Badges</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-person-badge fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= $badge_stats['system_badges'] ?></h4>
                            <small>System Badges</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-gear fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Badges List -->
    <div class="row">
        <div class="col-12">
            <?php if ($show_archived === '1'): ?>
                <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                    <i class="bi bi-archive-fill me-2"></i>
                    <div>
                        <strong>Viewing Archived Badges</strong> - These badges have been archived and can be recovered. 
                        Click the green recover button (🔄) to restore them to active status.
                    </div>
                </div>
            <?php endif; ?>
            <div class="card badges-card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= $show_archived === '1' ? 'Archived Badges' : 'All Badges' ?></h5>
                        <div class="d-flex gap-2">
                            <?php if ($show_archived === '1'): ?>
                                <a href="badges.php" class="btn btn-outline-success btn-sm" title="Back to Active Badges">
                                    <i class="bi bi-arrow-left me-1"></i>Back to Active
                                </a>
                            <?php else: ?>
                                <a href="badges.php?show_archived=1" class="btn btn-outline-warning btn-sm" title="View Archived Badges">
                                    <i class="bi bi-archive me-1"></i>View Archived
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive badges-table-container">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Icon</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Points</th>
                                    <th>Creator</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($badges as $badge): ?>
                                <tr class="<?= (isset($badge['is_archived']) && $badge['is_archived']) ? 'table-warning opacity-75' : '' ?>">
                                    <td>
                                        <?php 
                                        $icon_class = $badge['badge_icon'] ?: 'bi-award';
                                        // Check if it's an old image file and convert to Bootstrap icon
                                        if (strpos($icon_class, '.png') !== false || strpos($icon_class, '.jpg') !== false) {
                                            $icon_class = 'bi-award'; // Default fallback
                                        }
                                        ?>
                                        <div class="badge-icon-display" style="width:40px;height:40px;background:linear-gradient(135deg, #7DCB80 0%, #2E5E4E 100%);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                                            <i class="<?php echo htmlspecialchars($icon_class); ?>" style="font-size:20px;color:white;"></i>
                                            </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($badge['badge_name']); ?></div>
                                        <?php if ($badge['is_teacher_badge']): ?>
                                            <span class="badge bg-success">Your Badge</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">System Badge</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($badge['badge_description']); ?></td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <span class="badge bg-<?php 
                                                echo $badge['badge_type'] === 'course_completion' ? 'primary' : 
                                                    ($badge['badge_type'] === 'high_score' ? 'warning' : 
                                                    ($badge['badge_type'] === 'participation' ? 'info' : 'secondary')); 
                                            ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$badge['badge_type']))); ?>
                                            </span>
                                            <?php if (isset($badge['is_archived']) && $badge['is_archived']): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-archive me-1"></i>Archived
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?= $badge['points_value'] ?> pts</span>
                                    </td>
                                    <td>
                                        <?php if ($badge['first_name']): ?>
                                            <?php echo htmlspecialchars($badge['first_name'] . ' ' . $badge['last_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($badge['is_teacher_badge']): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editBadgeModal<?php echo $badge['id']; ?>" title="Edit Badge">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if (isset($badge['is_archived']) && $badge['is_archived']): ?>
                                                    <!-- Recover button for archived badges -->
                                                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recoverBadgeModal<?php echo $badge['id']; ?>" title="Recover Badge">
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Archive button for active badges -->
                                                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#archiveBadgeModal<?php echo $badge['id']; ?>" title="Archive Badge">
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteBadgeModal<?php echo $badge['id']; ?>" title="Delete Badge">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Read Only</span>
                                        <?php endif; ?>
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
</div>

<!-- Create Badge Modal -->
<div class="modal fade" id="createBadgeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Badge</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Badge Name</label>
                            <input type="text" class="form-control" name="badge_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Badge Type</label>
                            <select class="form-select" name="badge_type" required>
                                <option value="participation">Participation</option>
                                <option value="course_completion">Course Completion</option>
                                <option value="high_score">High Score</option>
                                <option value="streak">Learning Streak</option>
                                <option value="special">Special Achievement</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="badge_description" rows="3" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Points Value</label>
                            <input type="number" class="form-control" name="points_value" value="10" min="0" max="1000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Icon</label>
                            <select class="form-select" name="badge_icon_select">
                                <option value="">Auto-select based on name/type</option>
                                <optgroup label="Achievement Icons">
                                    <option value="bi-award">Award</option>
                                    <option value="bi-trophy">Trophy</option>
                                    <option value="bi-star">Star</option>
                                    <option value="bi-star-fill">Star (Filled)</option>
                                    <option value="bi-check-circle-fill">Perfect Score</option>
                                    <option value="bi-patch-check">Assessment</option>
                                </optgroup>
                                <optgroup label="Learning Icons">
                                    <option value="bi-mortarboard">Graduation Cap</option>
                                    <option value="bi-book">Book</option>
                                    <option value="bi-book-half">Half Book</option>
                                    <option value="bi-search">Search/Knowledge</option>
                                    <option value="bi-eye">Focus</option>
                                    <option value="bi-collection">Collection</option>
                                </optgroup>
                                <optgroup label="Activity Icons">
                                    <option value="bi-play-circle">Start/First Steps</option>
                                    <option value="bi-play-btn">Video Learning</option>
                                    <option value="bi-calendar-check">Daily Activity</option>
                                    <option value="bi-calendar-week">Weekly Activity</option>
                                    <option value="bi-lightning">Streak</option>
                                    <option value="bi-lightning-charge">Speed</option>
                                </optgroup>
                                <optgroup label="Time Icons">
                                    <option value="bi-sunrise">Early Bird</option>
                                    <option value="bi-sun">Morning Person</option>
                                    <option value="bi-moon">Night Owl</option>
                                    <option value="bi-calendar-check-fill">Perfect Attendance</option>
                                </optgroup>
                                <optgroup label="Special Icons">
                                    <option value="bi-shield-check">Warrior/Defender</option>
                                    <option value="bi-fire">Hard Core</option>
                                    <option value="bi-100">Century Club</option>
                                    <option value="bi-coin">Points</option>
                                    <option value="bi-compass">Explorer</option>
                                    <option value="bi-gear">Expert</option>
                                    <option value="bi-tools">Specialist</option>
                                    <option value="bi-bicycle">Easy Rider</option>
                                    <option value="bi-speedometer2">Medium Master</option>
                                    <option value="bi-graph-up">Consistent</option>
                                    <option value="bi-chat-dots">Participation</option>
                                    <option value="bi-emoji-smile">Nice</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Criteria Type</label>
                        <select class="form-select" id="criteriaType" name="criteria_type" required>
                            <option value="">Select criteria type</option>
                            <option value="assessments_taken">Complete Assessments</option>
                            <option value="courses_completed">Complete Courses</option>
                            <option value="average_score">Achieve Average Score</option>
                            <option value="videos_watched">Watch Videos</option>
                            <option value="login_streak">Login Streak</option>
                            <option value="perfect_scores">Perfect Scores</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Value</label>
                        <input type="number" class="form-control" id="criteriaValue" name="criteria_value" min="1" max="1000" required placeholder="Enter target number">
                        <div class="form-text" id="criteriaHelp">
                            Enter the target number for this criteria
                        </div>
                    </div>
                    <input type="hidden" id="criteriaJson" name="criteria" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Badge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($badges as $badge): ?>
    <?php if ($badge['is_teacher_badge']): ?>
    <!-- Edit Badge Modal -->
    <div class="modal fade" id="editBadgeModal<?php echo $badge['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Badge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Badge Name</label>
                                <input type="text" class="form-control" name="badge_name" value="<?php echo htmlspecialchars($badge['badge_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Badge Type</label>
                                <select class="form-select" name="badge_type" required>
                                    <option value="participation" <?= $badge['badge_type']==='participation'?'selected':'' ?>>Participation</option>
                                    <option value="course_completion" <?= $badge['badge_type']==='course_completion'?'selected':'' ?>>Course Completion</option>
                                    <option value="high_score" <?= $badge['badge_type']==='high_score'?'selected':'' ?>>High Score</option>
                                    <option value="streak" <?= $badge['badge_type']==='streak'?'selected':'' ?>>Learning Streak</option>
                                    <option value="special" <?= $badge['badge_type']==='special'?'selected':'' ?>>Special Achievement</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="badge_description" rows="3" required><?php echo htmlspecialchars($badge['badge_description']); ?></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Points Value</label>
                                <input type="number" class="form-control" name="points_value" value="<?= $badge['points_value'] ?>" min="0" max="1000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Icon</label>
                                <select class="form-select" name="badge_icon_select">
                                    <option value="">Keep current icon</option>
                                    <optgroup label="Achievement Icons">
                                        <option value="bi-award" <?= $badge['badge_icon']==='bi-award'?'selected':'' ?>>Award</option>
                                        <option value="bi-trophy" <?= $badge['badge_icon']==='bi-trophy'?'selected':'' ?>>Trophy</option>
                                        <option value="bi-star" <?= $badge['badge_icon']==='bi-star'?'selected':'' ?>>Star</option>
                                        <option value="bi-star-fill" <?= $badge['badge_icon']==='bi-star-fill'?'selected':'' ?>>Star (Filled)</option>
                                        <option value="bi-check-circle-fill" <?= $badge['badge_icon']==='bi-check-circle-fill'?'selected':'' ?>>Perfect Score</option>
                                        <option value="bi-patch-check" <?= $badge['badge_icon']==='bi-patch-check'?'selected':'' ?>>Assessment</option>
                                    </optgroup>
                                    <optgroup label="Learning Icons">
                                        <option value="bi-mortarboard" <?= $badge['badge_icon']==='bi-mortarboard'?'selected':'' ?>>Graduation Cap</option>
                                        <option value="bi-book" <?= $badge['badge_icon']==='bi-book'?'selected':'' ?>>Book</option>
                                        <option value="bi-book-half" <?= $badge['badge_icon']==='bi-book-half'?'selected':'' ?>>Half Book</option>
                                        <option value="bi-search" <?= $badge['badge_icon']==='bi-search'?'selected':'' ?>>Search/Knowledge</option>
                                        <option value="bi-eye" <?= $badge['badge_icon']==='bi-eye'?'selected':'' ?>>Focus</option>
                                        <option value="bi-collection" <?= $badge['badge_icon']==='bi-collection'?'selected':'' ?>>Collection</option>
                                    </optgroup>
                                    <optgroup label="Activity Icons">
                                        <option value="bi-play-circle" <?= $badge['badge_icon']==='bi-play-circle'?'selected':'' ?>>Start/First Steps</option>
                                        <option value="bi-play-btn" <?= $badge['badge_icon']==='bi-play-btn'?'selected':'' ?>>Video Learning</option>
                                        <option value="bi-calendar-check" <?= $badge['badge_icon']==='bi-calendar-check'?'selected':'' ?>>Daily Activity</option>
                                        <option value="bi-calendar-week" <?= $badge['badge_icon']==='bi-calendar-week'?'selected':'' ?>>Weekly Activity</option>
                                        <option value="bi-lightning" <?= $badge['badge_icon']==='bi-lightning'?'selected':'' ?>>Streak</option>
                                        <option value="bi-lightning-charge" <?= $badge['badge_icon']==='bi-lightning-charge'?'selected':'' ?>>Speed</option>
                                    </optgroup>
                                    <optgroup label="Time Icons">
                                        <option value="bi-sunrise" <?= $badge['badge_icon']==='bi-sunrise'?'selected':'' ?>>Early Bird</option>
                                        <option value="bi-sun" <?= $badge['badge_icon']==='bi-sun'?'selected':'' ?>>Morning Person</option>
                                        <option value="bi-moon" <?= $badge['badge_icon']==='bi-moon'?'selected':'' ?>>Night Owl</option>
                                        <option value="bi-calendar-check-fill" <?= $badge['badge_icon']==='bi-calendar-check-fill'?'selected':'' ?>>Perfect Attendance</option>
                                    </optgroup>
                                    <optgroup label="Special Icons">
                                        <option value="bi-shield-check" <?= $badge['badge_icon']==='bi-shield-check'?'selected':'' ?>>Warrior/Defender</option>
                                        <option value="bi-fire" <?= $badge['badge_icon']==='bi-fire'?'selected':'' ?>>Hard Core</option>
                                        <option value="bi-100" <?= $badge['badge_icon']==='bi-100'?'selected':'' ?>>Century Club</option>
                                        <option value="bi-coin" <?= $badge['badge_icon']==='bi-coin'?'selected':'' ?>>Points</option>
                                        <option value="bi-compass" <?= $badge['badge_icon']==='bi-compass'?'selected':'' ?>>Explorer</option>
                                        <option value="bi-gear" <?= $badge['badge_icon']==='bi-gear'?'selected':'' ?>>Expert</option>
                                        <option value="bi-tools" <?= $badge['badge_icon']==='bi-tools'?'selected':'' ?>>Specialist</option>
                                        <option value="bi-bicycle" <?= $badge['badge_icon']==='bi-bicycle'?'selected':'' ?>>Easy Rider</option>
                                        <option value="bi-speedometer2" <?= $badge['badge_icon']==='bi-speedometer2'?'selected':'' ?>>Medium Master</option>
                                        <option value="bi-graph-up" <?= $badge['badge_icon']==='bi-graph-up'?'selected':'' ?>>Consistent</option>
                                        <option value="bi-chat-dots" <?= $badge['badge_icon']==='bi-chat-dots'?'selected':'' ?>>Participation</option>
                                        <option value="bi-emoji-smile" <?= $badge['badge_icon']==='bi-emoji-smile'?'selected':'' ?>>Nice</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Criteria Type</label>
                            <select class="form-select" id="editCriteriaType<?php echo $badge['id']; ?>" name="criteria_type" required>
                                <option value="">Select criteria type</option>
                                <option value="assessments_taken">Complete Assessments</option>
                                <option value="courses_completed">Complete Courses</option>
                                <option value="average_score">Achieve Average Score</option>
                                <option value="videos_watched">Watch Videos</option>
                                <option value="login_streak">Login Streak</option>
                                <option value="perfect_scores">Perfect Scores</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Value</label>
                            <input type="number" class="form-control" id="editCriteriaValue<?php echo $badge['id']; ?>" name="criteria_value" min="1" max="1000" required placeholder="Enter target number">
                            <div class="form-text" id="editCriteriaHelp<?php echo $badge['id']; ?>">
                                Enter the target number for this criteria
                            </div>
                        </div>
                        <input type="hidden" id="editCriteriaJson<?php echo $badge['id']; ?>" name="criteria" value="<?php echo htmlspecialchars($badge['criteria']); ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Badge Modal -->
    <div class="modal fade" id="deleteBadgeModal<?php echo $badge['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Badge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the badge "<strong><?php echo htmlspecialchars($badge['badge_name']); ?></strong>"?</p>
                        <p class="text-danger"><small>This action cannot be undone.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Badge</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Archive Badge Modal -->
    <div class="modal fade" id="archiveBadgeModal<?php echo $badge['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Archive Badge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to archive the badge "<strong><?php echo htmlspecialchars($badge['badge_name']); ?></strong>"?</p>
                        <p class="text-warning"><small>This badge will be hidden but can be recovered later.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Archive Badge</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recover Badge Modal -->
    <div class="modal fade" id="recoverBadgeModal<?php echo $badge['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="recover">
                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Recover Badge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to recover the badge "<strong><?php echo htmlspecialchars($badge['badge_name']); ?></strong>"?</p>
                        <p class="text-success"><small>This badge will be restored to active status.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Recover Badge</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>
        </main>
    </div>
</div>

<style>
/* Enhanced Badges Table Scrolling */
.badges-table-container {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    position: relative;
}

/* Custom scrollbar for badges table */
.badges-table-container::-webkit-scrollbar {
    width: 8px;
}

.badges-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.badges-table-container::-webkit-scrollbar-thumb {
    background: #ffc107;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.badges-table-container::-webkit-scrollbar-thumb:hover {
    background: #e0a800;
}

/* Firefox scrollbar styling */
.badges-table-container {
    scrollbar-width: thin;
    scrollbar-color: #ffc107 #f1f1f1;
}

/* Enhanced table styling */
.badges-table-container .table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.badges-table-container .table thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 16px 12px;
}

.badges-table-container .table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid #f0f0f0;
}

.badges-table-container .table tbody tr:hover {
    background-color: rgba(255, 193, 7, 0.05);
    transform: translateX(3px);
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.1);
}

.badges-table-container .table tbody td {
    padding: 16px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

/* Enhanced button styling */
.badges-table-container .btn-group .btn {
    padding: 6px 12px;
    font-size: 0.875rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    margin: 0 2px;
}

.badges-table-container .btn-group .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Badge enhancements */
.badges-table-container .badge {
    font-size: 0.75rem;
    padding: 6px 10px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.badges-table-container .badge:hover {
    transform: scale(1.05);
}

/* Enhanced Badge icon styling */
.badges-table-container .table tbody td img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 50%;
    transition: all 0.3s ease;
    display: block;
    background: transparent;
}

.badges-table-container .table tbody tr:hover td img {
    transform: scale(1.1);
}

/* Ensure images are visible */
.badges-table-container .table tbody td {
    text-align: center;
}

.badges-table-container .table tbody td img[src=""],
.badges-table-container .table tbody td img:not([src]) {
    display: none;
}

/* Scroll indicators for badges table */
.badges-scroll-indicator {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 15;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.badges-scroll-indicator.show {
    opacity: 1;
}

.badges-scroll-indicator-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.badges-scroll-indicator i {
    background: rgba(255, 193, 7, 0.8);
    color: white;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
}

.badges-scroll-indicator-top.hide,
.badges-scroll-indicator-bottom.hide {
    opacity: 0.3;
}

/* Card enhancements */
.badges-card {
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
}

.badges-card .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    padding: 16px 20px;
}

.badges-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #495057;
}

/* Statistics cards enhancements */
.badges-stats .card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.badges-stats .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.badges-stats .card .card-body {
    padding: 20px;
}

.badges-stats .card i {
    transition: transform 0.3s ease;
}

.badges-stats .card:hover i {
    transform: scale(1.1);
}

/* Mobile responsiveness */
@media (max-width: 991.98px) {
    .badges-table-container {
        max-height: 450px;
    }
    
    .badges-table-container .table thead th,
    .badges-table-container .table tbody td {
        padding: 12px 8px;
        font-size: 0.9rem;
    }
}

@media (max-width: 575.98px) {
    .badges-table-container {
        max-height: 350px;
    }
    
    .badges-table-container .table thead th,
    .badges-table-container .table tbody td {
        padding: 8px 4px;
        font-size: 0.85rem;
    }
    
    .badges-table-container .btn-group .btn {
        padding: 4px 8px;
        font-size: 0.75rem;
    }
    
    .badges-table-container .table tbody td img {
        width: 35px !important;
        height: 35px !important;
    }
}

/* Loading and animation states */
.badges-table-loading {
    opacity: 0.6;
    pointer-events: none;
}

.badge-row-enter {
    animation: badgeRowEnter 0.5s ease-out;
}

@keyframes badgeRowEnter {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.badge-row-exit {
    animation: badgeRowExit 0.5s ease-in;
}

@keyframes badgeRowExit {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(-100%);
    }
}

/* Enhanced modal styling */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}

.modal-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    border-radius: 12px 12px 0 0;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 12px 12px;
}

/* Form enhancements */
.form-control:focus,
.form-select:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    transition: all 0.3s ease;
}

.btn-success:hover {
    background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}
</style>

<script>
// Enhanced scrolling behavior for badges table
document.addEventListener('DOMContentLoaded', function() {
    function enhanceBadgesTableScrolling() {
        const tableContainer = document.querySelector('.badges-table-container');
        
        if (tableContainer) {
            // Add smooth scrolling behavior
            tableContainer.style.scrollBehavior = 'smooth';
            
            // Add scroll indicators
            const cardContainer = tableContainer.closest('.card');
            if (cardContainer) {
                addBadgesTableScrollIndicators(tableContainer, cardContainer);
            }
        }
    }
    
    // Add scroll indicators to badges table
    function addBadgesTableScrollIndicators(scrollContainer, cardContainer) {
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = 'badges-scroll-indicator';
        scrollIndicator.innerHTML = `
            <div class="badges-scroll-indicator-content">
                <i class="bi bi-chevron-up badges-scroll-indicator-top"></i>
                <i class="bi bi-chevron-down badges-scroll-indicator-bottom"></i>
            </div>
        `;
        
        cardContainer.style.position = 'relative';
        cardContainer.appendChild(scrollIndicator);
        
        // Update scroll indicators based on scroll position
        function updateBadgesScrollIndicators() {
            const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
            const isAtTop = scrollContainer.scrollTop === 0;
            const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
            
            if (isScrollable) {
                scrollIndicator.classList.add('show');
                scrollIndicator.querySelector('.badges-scroll-indicator-top').classList.toggle('hide', isAtTop);
                scrollIndicator.querySelector('.badges-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
            } else {
                scrollIndicator.classList.remove('show');
            }
        }
        
        // Initial check
        updateBadgesScrollIndicators();
        
        // Update on scroll
        scrollContainer.addEventListener('scroll', updateBadgesScrollIndicators);
        
        // Update on resize
        window.addEventListener('resize', updateBadgesScrollIndicators);
    }
    
    // Initialize enhanced badges table scrolling
    enhanceBadgesTableScrolling();
    
    // Initialize criteria form handling
    initializeCriteriaForms();
});

// Initialize criteria form handling
function initializeCriteriaForms() {
    // Handle create form
    const createCriteriaType = document.getElementById('criteriaType');
    const createCriteriaValue = document.getElementById('criteriaValue');
    const createCriteriaHelp = document.getElementById('criteriaHelp');
    const createCriteriaJson = document.getElementById('criteriaJson');
    
    if (createCriteriaType && createCriteriaValue && createCriteriaHelp && createCriteriaJson) {
        // Update help text and build JSON on change
        createCriteriaType.addEventListener('change', function() {
            updateCriteriaHelp(createCriteriaHelp, this.value);
            buildCriteriaJson(createCriteriaType, createCriteriaValue, createCriteriaJson);
        });
        
        createCriteriaValue.addEventListener('input', function() {
            buildCriteriaJson(createCriteriaType, createCriteriaValue, createCriteriaJson);
        });
    }
    
    // Handle edit forms for each badge
    <?php foreach ($badges as $badge): ?>
        <?php if ($badge['is_teacher_badge']): ?>
            const editCriteriaType<?php echo $badge['id']; ?> = document.getElementById('editCriteriaType<?php echo $badge['id']; ?>');
            const editCriteriaValue<?php echo $badge['id']; ?> = document.getElementById('editCriteriaValue<?php echo $badge['id']; ?>');
            const editCriteriaHelp<?php echo $badge['id']; ?> = document.getElementById('editCriteriaHelp<?php echo $badge['id']; ?>');
            const editCriteriaJson<?php echo $badge['id']; ?> = document.getElementById('editCriteriaJson<?php echo $badge['id']; ?>');
            
            if (editCriteriaType<?php echo $badge['id']; ?> && editCriteriaValue<?php echo $badge['id']; ?> && editCriteriaHelp<?php echo $badge['id']; ?> && editCriteriaJson<?php echo $badge['id']; ?>) {
                // Parse existing criteria and populate form
                const existingCriteria = <?php echo $badge['criteria']; ?>;
                if (existingCriteria && typeof existingCriteria === 'object') {
                    const criteriaType = Object.keys(existingCriteria)[0];
                    const criteriaValue = existingCriteria[criteriaType];
                    
                    editCriteriaType<?php echo $badge['id']; ?>.value = criteriaType;
                    editCriteriaValue<?php echo $badge['id']; ?>.value = criteriaValue;
                    updateCriteriaHelp(editCriteriaHelp<?php echo $badge['id']; ?>, criteriaType);
                }
                
                // Update help text and build JSON on change
                editCriteriaType<?php echo $badge['id']; ?>.addEventListener('change', function() {
                    updateCriteriaHelp(editCriteriaHelp<?php echo $badge['id']; ?>, this.value);
                    buildCriteriaJson(editCriteriaType<?php echo $badge['id']; ?>, editCriteriaValue<?php echo $badge['id']; ?>, editCriteriaJson<?php echo $badge['id']; ?>);
                });
                
                editCriteriaValue<?php echo $badge['id']; ?>.addEventListener('input', function() {
                    buildCriteriaJson(editCriteriaType<?php echo $badge['id']; ?>, editCriteriaValue<?php echo $badge['id']; ?>, editCriteriaJson<?php echo $badge['id']; ?>);
                });
            }
        <?php endif; ?>
    <?php endforeach; ?>
}

// Update criteria help text based on selected type
function updateCriteriaHelp(helpElement, criteriaType) {
    const helpTexts = {
        'assessments_taken': 'Enter the number of assessments students must complete',
        'courses_completed': 'Enter the number of courses students must complete',
        'average_score': 'Enter the minimum average score percentage (1-100)',
        'videos_watched': 'Enter the number of videos students must watch',
        'login_streak': 'Enter the number of consecutive days students must log in',
        'perfect_scores': 'Enter the number of perfect scores students must achieve'
    };
    
    helpElement.textContent = helpTexts[criteriaType] || 'Enter the target number for this criteria';
}

// Build criteria JSON from form inputs
function buildCriteriaJson(typeSelect, valueInput, jsonInput) {
    const criteriaType = typeSelect.value;
    const criteriaValue = parseInt(valueInput.value) || 0;
    
    if (criteriaType && criteriaValue > 0) {
        const criteria = {};
        criteria[criteriaType] = criteriaValue;
        jsonInput.value = JSON.stringify(criteria);
    } else {
        jsonInput.value = '';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
