<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$db = new Database();
$pdo = $db->getConnection();

// Get earned badges from JSON awarded_to field
$stmt = $pdo->prepare("
    SELECT b.*, 
           JSON_UNQUOTE(JSON_EXTRACT(b.awarded_to, '$[0].awarded_at')) as earned_at
    FROM badges b
    WHERE JSON_SEARCH(b.awarded_to, 'one', ?, NULL, '$[*].student_id') IS NOT NULL
    ORDER BY earned_at DESC
");
$stmt->execute([$user_id]);
$earned_badges = $stmt->fetchAll();

// Get all available badges with earned status from JSON
$stmt = $pdo->prepare("
    SELECT b.*, 
           CASE WHEN JSON_SEARCH(b.awarded_to, 'one', ?, NULL, '$[*].student_id') IS NOT NULL THEN 1 ELSE 0 END as is_earned,
           CASE WHEN JSON_SEARCH(b.awarded_to, 'one', ?, NULL, '$[*].student_id') IS NOT NULL 
                THEN JSON_UNQUOTE(JSON_EXTRACT(b.awarded_to, '$[0].awarded_at')) 
                ELSE NULL END as earned_at
    FROM badges b
    ORDER BY b.created_at ASC
");
$stmt->execute([$user_id, $user_id]);
$all_badges = $stmt->fetchAll();

// Calculate statistics
$total_badges = count($all_badges);
$earned_badges_count = count($earned_badges);

// Get recent badge earnings
$recent_badges = array_slice($earned_badges, 0, 5);

// Get progress towards next badge
$next_badge = null;
foreach ($all_badges as $badge) {
    if (!$badge['is_earned']) {
        $next_badge = $badge;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badges - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .badge-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            height: 100%;
        }
        .badge-card:hover {
            transform: translateY(-5px);
        }
        .badge-card.earned {
            border: 2px solid #28a745;
        }
        .badge-card.locked {
            opacity: 0.6;
            filter: grayscale(100%);
        }
        .badge-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: block;
        }
        .badge-locked {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .badge-earned {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(#007bff 0deg, #007bff <?php echo ($earned_badges_count / $total_badges) * 360; ?>deg, #e9ecef <?php echo ($earned_badges_count / $total_badges) * 360; ?>deg, #e9ecef 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .progress-circle::before {
            content: '';
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: white;
        }
        .progress-text {
            position: absolute;
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        
        /* Enhanced Badge Icon Styling */
        .recent-badge-item {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .recent-badge-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
        }
        
        .badge-icon-container {
            position: relative;
            display: inline-block;
        }
        
        .badge-icon {
            transition: transform 0.3s ease;
        }
        
        .badge-icon:hover {
            transform: scale(1.1);
        }
        
        .earned-badge-check {
            animation: badgeEarned 0.5s ease-in-out;
        }
        
        @keyframes badgeEarned {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* Enhanced Student Badges Scrolling */
        .all-badges-container {
            max-height: 600px;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
            border-radius: 8px;
            position: relative;
        }

        /* Custom scrollbar for all badges */
        .all-badges-container::-webkit-scrollbar {
            width: 8px;
        }

        .all-badges-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .all-badges-container::-webkit-scrollbar-thumb {
            background: #ffc107;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .all-badges-container::-webkit-scrollbar-thumb:hover {
            background: #e0a800;
        }

        /* Firefox scrollbar styling */
        .all-badges-container {
            scrollbar-width: thin;
            scrollbar-color: #ffc107 #f1f1f1;
        }

        /* Recent badges scrolling */
        .recent-badges-container {
            max-height: 400px;
            overflow-y: auto;
            overflow-x: hidden;
            scroll-behavior: smooth;
            border-radius: 8px;
            position: relative;
        }

        /* Custom scrollbar for recent badges */
        .recent-badges-container::-webkit-scrollbar {
            width: 6px;
        }

        .recent-badges-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .recent-badges-container::-webkit-scrollbar-thumb {
            background: #28a745;
            border-radius: 3px;
            transition: background 0.3s ease;
        }

        .recent-badges-container::-webkit-scrollbar-thumb:hover {
            background: #218838;
        }

        /* Firefox scrollbar styling for recent badges */
        .recent-badges-container {
            scrollbar-width: thin;
            scrollbar-color: #28a745 #f1f1f1;
        }

        /* Enhanced badge cards */
        .all-badges-container .badge-card {
            transition: all 0.3s ease;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid transparent;
            margin-bottom: 16px;
        }

        .all-badges-container .badge-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.2);
            border-color: #ffc107;
        }

        .all-badges-container .badge-card.earned:hover {
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
            border-color: #28a745;
        }

        .all-badges-container .badge-card.locked:hover {
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.2);
            border-color: #6c757d;
        }

        /* Enhanced recent badge items */
        .recent-badges-container .recent-badge-item {
            transition: all 0.3s ease;
            border-radius: 12px;
            margin-bottom: 12px;
            background: rgba(40, 167, 69, 0.05);
            border: 1px solid rgba(40, 167, 69, 0.1);
            padding: 12px;
        }

        .recent-badges-container .recent-badge-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
            background: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
        }

        /* Enhanced badge images */
        .all-badges-container .badge-image {
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }

        .all-badges-container .badge-card:hover .badge-image {
            transform: scale(1.1);
            border-color: #ffc107;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }

        .all-badges-container .badge-card.earned:hover .badge-image {
            border-color: #28a745;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        /* Enhanced recent badge icons */
        .recent-badges-container .badge-icon {
            transition: all 0.3s ease;
        }

        .recent-badges-container .recent-badge-item:hover .badge-icon {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        /* Scroll indicators for all badges */
        .all-badges-scroll-indicator {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 15;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .all-badges-scroll-indicator.show {
            opacity: 1;
        }

        .all-badges-scroll-indicator-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .all-badges-scroll-indicator i {
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

        .all-badges-scroll-indicator-top.hide,
        .all-badges-scroll-indicator-bottom.hide {
            opacity: 0.3;
        }

        /* Scroll indicators for recent badges */
        .recent-badges-scroll-indicator {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 15;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .recent-badges-scroll-indicator.show {
            opacity: 1;
        }

        .recent-badges-scroll-indicator-content {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .recent-badges-scroll-indicator i {
            background: rgba(40, 167, 69, 0.8);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .recent-badges-scroll-indicator-top.hide,
        .recent-badges-scroll-indicator-bottom.hide {
            opacity: 0.3;
        }

        /* Enhanced statistics cards */
        .badges-stats .card {
            transition: all 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
        }

        .badges-stats .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .badges-stats .card i {
            transition: transform 0.3s ease;
        }

        .badges-stats .card:hover i {
            transform: scale(1.1);
        }

        /* Enhanced progress circle */
        .progress-circle {
            transition: all 0.3s ease;
        }

        .badges-stats .card:hover .progress-circle {
            transform: scale(1.05);
        }

        /* Mobile responsiveness */
        @media (max-width: 991.98px) {
            .all-badges-container {
                max-height: 450px;
            }
            
            .recent-badges-container {
                max-height: 300px;
            }
        }

        @media (max-width: 575.98px) {
            .all-badges-container {
                max-height: 350px;
            }
            
            .recent-badges-container {
                max-height: 250px;
            }
            
            .all-badges-container .badge-card {
                margin-bottom: 12px;
            }
            
            .recent-badges-container .recent-badge-item {
                padding: 10px;
                margin-bottom: 10px;
            }
        }

        /* Loading and animation states */
        .badges-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .badge-item-enter {
            animation: badgeItemEnter 0.5s ease-out;
        }

        @keyframes badgeItemEnter {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .badge-item-exit {
            animation: badgeItemExit 0.5s ease-in;
        }

        @keyframes badgeItemExit {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(-100%);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar removed -->
            <!-- Main content -->
            <main class="col-12 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Badges & Achievements</h1>
                </div>

                <!-- Statistics -->
                <div class="row mb-4 badges-stats">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <div class="position-relative">
                                    <div class="progress-circle"></div>
                                    <div class="progress-text"><?php echo $earned_badges_count; ?>/<?php echo $total_badges; ?></div>
                                </div>
                                <h5 class="card-title mt-3">Badge Progress</h5>
                                <p class="card-text"><?php echo round(($earned_badges_count / $total_badges) * 100); ?>% complete</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-trophy fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Badges Earned</h5>
                                <p class="card-text display-6"><?php echo $earned_badges_count; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-lock fa-3x text-secondary mb-3"></i>
                                <h5 class="card-title">Badges Remaining</h5>
                                <p class="card-text display-6"><?php echo $total_badges - $earned_badges_count; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Next Badge Progress -->
                <?php if ($next_badge): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Next Badge to Unlock</h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                                                 <div class="col-md-2 text-center">
                                     <?php 
                                     $next_badge_image_path = "../uploads/badges/" . htmlspecialchars($next_badge['badge_icon']);
                                     $next_badge_image_exists = !empty($next_badge['badge_icon']) && file_exists(__DIR__ . "/../uploads/badges/" . $next_badge['badge_icon']);
                                     ?>
                                     <?php if ($next_badge_image_exists): ?>
                                         <img src="<?php echo $next_badge_image_path; ?>" 
                                              alt="<?php echo htmlspecialchars($next_badge['badge_name']); ?>"
                                              class="badge-image" style="filter: grayscale(100%);">
                                     <?php else: ?>
                                         <div class="badge-image d-flex align-items-center justify-content-center" 
                                              style="background: linear-gradient(135deg, #6c757d, #495057); filter: grayscale(100%);">
                                             <i class="fas fa-trophy" style="font-size: 32px; color: white;"></i>
                                         </div>
                                     <?php endif; ?>
                                 </div>
                                <div class="col-md-10">
                                    <h6><?php echo htmlspecialchars($next_badge['badge_name']); ?></h6>
                                    <p class="text-muted"><?php echo htmlspecialchars($next_badge['badge_description']); ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> Keep learning to unlock this badge!
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- All Badges -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">All Badges</h5>
                            </div>
                            <div class="card-body">
                                <div class="all-badges-container">
                                    <div class="row">
                                        <?php foreach ($all_badges as $badge): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card badge-card <?php echo $badge['is_earned'] ? 'earned' : 'locked'; ?> position-relative">
                                                <div class="card-body text-center">
                                                    <?php if ($badge['is_earned']): ?>
                                                        <div class="badge-earned">
                                                            <i class="fas fa-check"></i> Earned
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="badge-locked">
                                                            <i class="fas fa-lock"></i> Locked
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                                                                         <?php 
                                                     $badge_image_path = "../uploads/badges/" . htmlspecialchars($badge['badge_icon']);
                                                     $badge_image_exists = !empty($badge['badge_icon']) && file_exists(__DIR__ . "/../uploads/badges/" . $badge['badge_icon']);
                                                     ?>
                                                     <?php if ($badge_image_exists): ?>
                                                         <img src="<?php echo $badge_image_path; ?>" 
                                                              alt="<?php echo htmlspecialchars($badge['badge_name']); ?>"
                                                              class="badge-image">
                                                     <?php else: ?>
                                                         <div class="badge-image d-flex align-items-center justify-content-center" 
                                                              style="background: linear-gradient(135deg, #28a745, #20c997);">
                                                             <i class="fas fa-trophy" style="font-size: 32px; color: white;"></i>
                                                         </div>
                                                     <?php endif; ?>
                                                    
                                                    <h6 class="card-title"><?php echo htmlspecialchars($badge['badge_name']); ?></h6>
                                                    <p class="card-text small"><?php echo htmlspecialchars($badge['badge_description']); ?></p>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-star"></i> <?php echo ucfirst(str_replace('_', ' ', $badge['badge_type'])); ?>
                                                        </span>
                                                        <?php if ($badge['is_earned']): ?>
                                                            <small class="text-muted">
                                                                Earned <?php echo !empty($badge['earned_at']) ? date('M j, Y', strtotime($badge['earned_at'])) : 'Date not available'; ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <small class="text-muted">
                                                                Keep learning to unlock
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Badges -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recently Earned</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_badges)): ?>
                                    <p class="text-muted">No badges earned yet. Keep learning to earn badges!</p>
                                <?php else: ?>
                                    <div class="recent-badges-container">
                                        <?php foreach ($recent_badges as $badge): ?>
                                        <div class="d-flex align-items-center mb-3 p-2 rounded recent-badge-item" style="background: rgba(40, 167, 69, 0.05); border: 1px solid rgba(40, 167, 69, 0.1);">
                                                                                         <div class="badge-icon-container me-3">
                                                 <?php 
                                                 $badge_image_path = "../uploads/badges/" . htmlspecialchars($badge['badge_icon']);
                                                 $badge_image_exists = !empty($badge['badge_icon']) && file_exists(__DIR__ . "/../uploads/badges/" . $badge['badge_icon']);
                                                 ?>
                                                 <?php if ($badge_image_exists): ?>
                                                     <img src="<?php echo $badge_image_path; ?>" 
                                                          alt="<?php echo htmlspecialchars($badge['badge_name']); ?>"
                                                          class="badge-icon"
                                                          style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #28a745; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);">
                                                 <?php else: ?>
                                                     <div class="badge-icon d-flex align-items-center justify-content-center" 
                                                          style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #28a745, #20c997); border: 3px solid #28a745; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);">
                                                         <i class="fas fa-trophy" style="font-size: 24px; color: white;"></i>
                                                     </div>
                                                 <?php endif; ?>
                                                 <div class="position-absolute top-0 start-100 translate-middle">
                                                     <span class="badge bg-success rounded-circle earned-badge-check" style="width: 20px; height: 20px; font-size: 0.6rem; display: flex; align-items: center; justify-content: center;">
                                                         <i class="fas fa-check"></i>
                                                     </span>
                                                 </div>
                                             </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($badge['badge_name']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar-check text-success"></i> Earned <?php echo !empty($badge['earned_at']) ? date('M j, Y', strtotime($badge['earned_at'])) : 'Date not available'; ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-star"></i> <?php echo ucfirst(str_replace('_', ' ', $badge['badge_type'])); ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- How to Earn Badges -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">How to Earn Badges</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled small">
                                    <li class="mb-2">
                                        <i class="fas fa-play text-primary"></i> Complete video lessons
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> Finish modules
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-clipboard-check text-warning"></i> Take assessments
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-star text-info"></i> Achieve high scores
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-calendar text-secondary"></i> Maintain consistent learning
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced scrolling behavior for student badges
        document.addEventListener('DOMContentLoaded', function() {
            function enhanceStudentBadgesScrolling() {
                // All badges scrolling
                const allBadgesContainer = document.querySelector('.all-badges-container');
                if (allBadgesContainer) {
                    allBadgesContainer.style.scrollBehavior = 'smooth';
                    const allBadgesCard = allBadgesContainer.closest('.card');
                    if (allBadgesCard) {
                        addAllBadgesScrollIndicators(allBadgesContainer, allBadgesCard);
                    }
                }
                
                // Recent badges scrolling
                const recentBadgesContainer = document.querySelector('.recent-badges-container');
                if (recentBadgesContainer) {
                    recentBadgesContainer.style.scrollBehavior = 'smooth';
                    const recentBadgesCard = recentBadgesContainer.closest('.card');
                    if (recentBadgesCard) {
                        addRecentBadgesScrollIndicators(recentBadgesContainer, recentBadgesCard);
                    }
                }
            }
            
            // Add scroll indicators to all badges
            function addAllBadgesScrollIndicators(scrollContainer, cardContainer) {
                const scrollIndicator = document.createElement('div');
                scrollIndicator.className = 'all-badges-scroll-indicator';
                scrollIndicator.innerHTML = `
                    <div class="all-badges-scroll-indicator-content">
                        <i class="fas fa-chevron-up all-badges-scroll-indicator-top"></i>
                        <i class="fas fa-chevron-down all-badges-scroll-indicator-bottom"></i>
                    </div>
                `;
                
                cardContainer.style.position = 'relative';
                cardContainer.appendChild(scrollIndicator);
                
                function updateAllBadgesScrollIndicators() {
                    const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
                    const isAtTop = scrollContainer.scrollTop === 0;
                    const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
                    
                    if (isScrollable) {
                        scrollIndicator.classList.add('show');
                        scrollIndicator.querySelector('.all-badges-scroll-indicator-top').classList.toggle('hide', isAtTop);
                        scrollIndicator.querySelector('.all-badges-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
                    } else {
                        scrollIndicator.classList.remove('show');
                    }
                }
                
                updateAllBadgesScrollIndicators();
                scrollContainer.addEventListener('scroll', updateAllBadgesScrollIndicators);
                window.addEventListener('resize', updateAllBadgesScrollIndicators);
            }
            
            // Add scroll indicators to recent badges
            function addRecentBadgesScrollIndicators(scrollContainer, cardContainer) {
                const scrollIndicator = document.createElement('div');
                scrollIndicator.className = 'recent-badges-scroll-indicator';
                scrollIndicator.innerHTML = `
                    <div class="recent-badges-scroll-indicator-content">
                        <i class="fas fa-chevron-up recent-badges-scroll-indicator-top"></i>
                        <i class="fas fa-chevron-down recent-badges-scroll-indicator-bottom"></i>
                    </div>
                `;
                
                cardContainer.style.position = 'relative';
                cardContainer.appendChild(scrollIndicator);
                
                function updateRecentBadgesScrollIndicators() {
                    const isScrollable = scrollContainer.scrollHeight > scrollContainer.clientHeight;
                    const isAtTop = scrollContainer.scrollTop === 0;
                    const isAtBottom = scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 1;
                    
                    if (isScrollable) {
                        scrollIndicator.classList.add('show');
                        scrollIndicator.querySelector('.recent-badges-scroll-indicator-top').classList.toggle('hide', isAtTop);
                        scrollIndicator.querySelector('.recent-badges-scroll-indicator-bottom').classList.toggle('hide', isAtBottom);
                    } else {
                        scrollIndicator.classList.remove('show');
                    }
                }
                
                updateRecentBadgesScrollIndicators();
                scrollContainer.addEventListener('scroll', updateRecentBadgesScrollIndicators);
                window.addEventListener('resize', updateRecentBadgesScrollIndicators);
            }
            
            // Initialize enhanced scrolling
            enhanceStudentBadgesScrolling();
        });
    </script>
</body>
</html> 