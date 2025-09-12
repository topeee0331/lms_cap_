<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get the student ID from URL parameter
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id === 0) {
    header('Location: leaderboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Helper function to format section display name
function formatSectionName($section) {
    return "BSIT-{$section['year']}{$section['name']}";
}

// Get student information
$stmt = $pdo->prepare("
    SELECT 
        u.id, u.first_name, u.last_name, u.email, u.profile_picture, u.created_at, u.is_irregular,
        s.name as section_name, s.year as section_year,
        (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) as badge_count,
        (SELECT COUNT(*) FROM module_progress mp WHERE mp.student_id = u.id AND mp.is_completed = 1) as completed_modules,
        (SELECT COUNT(*) FROM video_views vv WHERE vv.student_id = u.id) as watched_videos,
        (SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as average_score,
        (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.score >= 70 AND aa.status = 'completed') as high_scores,
        (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed') as total_attempts,
        (
            (SELECT COUNT(*) FROM module_progress mp WHERE mp.student_id = u.id AND mp.is_completed = 1) * 10 +
            COALESCE((SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed'), 0) * 0.5 +
            (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) * 5
        ) as calculated_score
    FROM users u
    LEFT JOIN sections s ON u.section_id = s.id
    WHERE u.id = ? AND u.role = 'student'
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: leaderboard.php');
    exit();
}

// Get student's badges from JSON awarded_to field
$stmt = $pdo->prepare("
    SELECT b.*, 
           JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', ?), '].awarded_at')) as awarded_at
    FROM badges b
    WHERE JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL
    ORDER BY awarded_at DESC
");
$stmt->execute([$student_id, $student_id]);
$student_badges = $stmt->fetchAll();

// Get leaderboard rank
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        (
            (SELECT COUNT(*) FROM module_progress mp WHERE mp.student_id = u.id AND mp.is_completed = 1) * 10 +
            COALESCE((SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = u.id AND aa.status = 'completed'), 0) * 0.5 +
            (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', u.id) IS NOT NULL) * 5
        ) as calculated_score
    FROM users u
    WHERE u.role = 'student'
    ORDER BY calculated_score DESC
");
$stmt->execute();
$all_students = $stmt->fetchAll();

$student_rank = 0;
foreach ($all_students as $index => $s) {
    if ($s['id'] == $student_id) {
        $student_rank = $index + 1;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> - Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2E5E4E;
            --secondary-color: #6c757d;
            --success-color: #7DCB80;
            --info-color: #0dcaf0;
            --warning-color: #FFE066;
            --danger-color: #dc3545;
            --light-color: #F7FAF7;
            --dark-color: #212529;
            --border-radius: 1rem;
            --box-shadow: 0 8px 32px rgba(46, 94, 78, 0.1);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: var(--light-color);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%237DCB80\' fill-opacity=\'0.1\'%3E%3Ccircle cx=\'30\' cy=\'30\' r=\'2\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
            opacity: 0.3;
            z-index: -1;
        }

        .profile-header {
            background: var(--primary-color);
            color: white;
            padding: 4rem 0;
            margin-bottom: 3rem;
            border-radius: 0 0 3rem 3rem;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 0;
        }

        .profile-header::after {
            content: "";
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            z-index: 0;
        }

        .profile-header-content {
            position: relative;
            z-index: 1;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 6px solid white;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
            object-fit: cover;
            transition: var(--transition);
            position: relative;
        }

        .profile-avatar::before {
            content: "";
            position: absolute;
            top: -6px;
            left: -6px;
            right: -6px;
            bottom: -6px;
            border-radius: 50%;
            background: var(--primary-color);
            z-index: -1;
        }

        .profile-avatar:hover {
            transform: scale(1.05) rotate(2deg);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .stats-card {
            background: white;
            border-radius: 2rem;
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #f0f0f0;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-color);
        }

        .stats-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            border-radius: 1.5rem;
            background: #f8f9fa;
            margin-bottom: 1.5rem;
            transition: var(--transition);
            border: 1px solid #e9ecef;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            background: #e8f5e8;
        }

        .stat-value {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            line-height: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary-color);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-card {
            background: white;
            border-radius: 2rem;
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #f0f0f0;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .badge-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-color);
        }

        .badge-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .badge-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            transition: var(--transition);
            border: 3px solid white;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .badge-icon:hover {
            transform: scale(1.1) rotate(5deg);
        }

        .progress-bar {
            height: 12px;
            border-radius: 6px;
            background: var(--success-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .rank-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 2rem;
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .section-badge {
            background: var(--success-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 1.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .back-button {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 2rem;
            text-decoration: none;
            transition: var(--transition);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .badge-modal-card {
            transition: var(--transition);
            border-radius: 1.5rem;
            overflow: hidden;
        }
        
        .badge-modal-card:hover {
            transform: scale(1.05) rotate(2deg);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        
        .badge-modal-card .badge-icon {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: 0;
        }

        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 8s ease-in-out infinite;
        }

        .floating-circle:nth-child(1) {
            width: 100px;
            height: 100px;
            top: 20%;
            right: 10%;
            animation-delay: 0s;
        }

        .floating-circle:nth-child(2) {
            width: 150px;
            height: 150px;
            bottom: 30%;
            left: 5%;
            animation-delay: 3s;
        }

        .floating-circle:nth-child(3) {
            width: 80px;
            height: 80px;
            top: 60%;
            right: 20%;
            animation-delay: 6s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(180deg); }
        }

        .achievement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .achievement-card {
            background: white;
            border-radius: 2rem;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: 1px solid #f0f0f0;
            position: relative;
            overflow: hidden;
        }

        .achievement-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-color);
        }

        .achievement-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        @media (max-width: 768px) {
            .profile-avatar {
                width: 120px;
                height: 120px;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .achievement-grid {
                grid-template-columns: 1fr;
            }

            .profile-header {
                padding: 2rem 0;
            }

            .stats-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container profile-header-content">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'large'); ?>" 
                         alt="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                         class="profile-avatar">
                </div>
                <div class="col-md-6">
                    <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="rank-badge">
                            <i class="fas fa-trophy me-2"></i>Rank #<?php echo $student_rank; ?>
                        </span>
                        <?php if ($student['section_name'] && $student['section_year']): ?>
                            <span class="section-badge">
                                <i class="fas fa-graduation-cap me-1"></i>
                                <?php echo formatSectionName(['year' => $student['section_year'], 'name' => $student['section_name']]); ?>
                            </span>
                        <?php endif; ?>
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-calendar me-1"></i>
                            Member since <?php echo date('M Y', strtotime($student['created_at'])); ?>
                        </span>
                    </div>
                    <p class="mb-0 opacity-75 fs-5">
                        <i class="fas fa-star me-2"></i>
                        <?php echo round($student['calculated_score'] ?? 0); ?> Total Points
                    </p>
                </div>
                <div class="col-md-3 text-end">
                    <a href="leaderboard.php" class="back-button">
                        <i class="fas fa-arrow-left me-2"></i>Back to Leaderboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Floating Background Elements -->
        <div class="floating-elements">
            <div class="floating-circle"></div>
            <div class="floating-circle"></div>
            <div class="floating-circle"></div>
        </div>

        <div class="row">
            <!-- Statistics Overview -->
            <div class="col-lg-4">
                <div class="stats-card">
                    <h5 class="mb-4">
                        <i class="fas fa-chart-bar me-2"></i>Academic Statistics
                    </h5>
                    <div class="row">
                        <div class="col-6">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $student['completed_modules'] ?? 0; ?></div>
                                <div class="stat-label">Modules Completed</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $student['watched_videos'] ?? 0; ?></div>
                                <div class="stat-label">Videos Watched</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $student['total_attempts'] ?? 0; ?></div>
                                <div class="stat-label">Assessments Taken</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $student['badge_count'] ?? 0; ?></div>
                                <div class="stat-label">Badges Earned</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <h5 class="mb-4">
                        <i class="fas fa-target me-2"></i>Performance Metrics
                    </h5>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted fw-bold">Average Score</span>
                            <span class="fw-bold fs-5"><?php echo round($student['average_score'] ?? 0, 1); ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo min(100, $student['average_score'] ?? 0); ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted fw-bold">High Scores (≥70%)</span>
                            <span class="fw-bold fs-5"><?php echo $student['high_scores'] ?? 0; ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo $student['total_attempts'] > 0 ? ($student['high_scores'] / $student['total_attempts']) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted fw-bold">Success Rate</span>
                            <span class="fw-bold fs-5"><?php echo $student['total_attempts'] > 0 ? round(($student['high_scores'] / $student['total_attempts']) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo $student['total_attempts'] > 0 ? ($student['high_scores'] / $student['total_attempts']) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- My Badges Button -->
                <div class="stats-card">
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-trophy fa-4x text-warning mb-3"></i>
                            <h3 class="mb-3">Achievement Badges</h3>
                            <p class="text-muted fs-5">Complete modules and assessments to earn recognition badges!</p>
                        </div>
                        
                        <button class="btn btn-primary btn-lg px-5 py-3" data-bs-toggle="modal" data-bs-target="#badgesModal">
                            <i class="fas fa-award me-2"></i>
                            View My Badges
                            <?php if (!empty($student_badges)): ?>
                                <span class="badge bg-light text-dark ms-2 fs-6"><?php echo count($student_badges); ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <?php if (empty($student_badges)): ?>
                        <div class="mt-4">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Get Started!</strong> Complete modules and assessments to start earning badges!
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mt-4">
                            <div class="alert alert-success">
                                <i class="fas fa-star me-2"></i>
                                <strong>Great job!</strong> You've earned <?php echo count($student_badges); ?> badge<?php echo count($student_badges) > 1 ? 's' : ''; ?>!
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Badges Modal -->
    <div class="modal fade" id="badgesModal" tabindex="-1" aria-labelledby="badgesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="badgesModalLabel">
                        <i class="fas fa-award me-2"></i>
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>'s Badges
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($student_badges)): ?>
                    <div class="row">
                        <?php foreach ($student_badges as $badge): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="badge-modal-card text-center p-3 border rounded">
                                <?php 
                                $icon_path = "../uploads/badges/" . ($badge['badge_icon'] ?: 'default.png');
                                $icon_url = file_exists($icon_path) ? $icon_path : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/icons/award.svg';
                                ?>
                                <img src="<?php echo htmlspecialchars($icon_url); ?>" 
                                     alt="<?php echo htmlspecialchars($badge['badge_name']); ?>"
                                     class="badge-icon mb-2" style="width: 60px; height: 60px;">
                                <h6 class="mb-2"><?php echo htmlspecialchars($badge['badge_name']); ?></h6>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($badge['badge_description']); ?></p>
                                <small class="text-success">
                                    <i class="fas fa-calendar me-1"></i>
                                    Earned <?php echo date('M j, Y', strtotime($badge['awarded_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-award" style="font-size: 4rem; color: #ccc; margin-bottom: 1.5rem;"></i>
                        <h5 class="text-muted mb-3">No Badges Earned Yet</h5>
                        <p class="text-muted mb-4">
                            Complete modules and assessments to start earning badges!<br>
                            Badges are awarded for various achievements like:
                        </p>
                        <div class="row text-start">
                            <div class="col-md-6">
                                <ul class="text-muted">
                                    <li>Completing modules</li>
                                    <li>Achieving high scores</li>
                                    <li>Watching videos</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="text-muted">
                                    <li>Consistent participation</li>
                                    <li>Perfect assessments</li>
                                    <li>Special achievements</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced animations and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add staggered animation to stat items
            const statItems = document.querySelectorAll('.stat-item');
            statItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
                item.classList.add('animate__animated', 'animate__fadeInUp');
            });

            // Add hover effects to cards
            const cards = document.querySelectorAll('.stats-card, .badge-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add click animation to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });
    </script>
</body>
</html>
