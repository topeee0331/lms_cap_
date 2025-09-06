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
            --primary-color: #2c3e50;
            --secondary-color: #95a5a6;
            --success-color: #27ae60;
            --info-color: #3498db;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
            --border-radius: 8px;
            --box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            --transition: all 0.2s ease;
        }

        body {
            background-color: #fafafa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            object-fit: cover;
        }

        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            border-radius: var(--border-radius);
            background: var(--light-color);
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .badge-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
            transition: var(--transition);
        }

        .badge-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .badge-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 0.5rem;
        }

        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background: var(--success-color);
        }





        .rank-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .section-badge {
            background: var(--success-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .back-button {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .badge-modal-card {
            transition: transform 0.2s ease;
        }
        
        .badge-modal-card:hover {
            transform: scale(1.02);
        }
        
        .badge-icon {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        @media (max-width: 768px) {
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .achievement-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <img src="<?php echo getProfilePictureUrl($student['profile_picture'] ?? null, 'large'); ?>" 
                         alt="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                         class="profile-avatar">
                </div>
                <div class="col-md-8">
                    <h1 class="mb-2"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
                    <div class="d-flex align-items-center gap-3 mb-2">
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
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-star me-2"></i>
                        <?php echo round($student['calculated_score'] ?? 0); ?> Total Points
                    </p>
                </div>
                <div class="col-md-2 text-end">
                    <a href="leaderboard.php" class="back-button">
                        <i class="fas fa-arrow-left me-2"></i>Back to Leaderboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Statistics Overview -->
            <div class="col-lg-4">
                <div class="stats-card">
                    <h5 class="mb-3">
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
                    <h5 class="mb-3">
                        <i class="fas fa-target me-2"></i>Performance Metrics
                    </h5>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Average Score</span>
                            <span class="fw-bold"><?php echo round($student['average_score'] ?? 0, 1); ?>%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo min(100, $student['average_score'] ?? 0); ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">High Scores (≥70%)</span>
                            <span class="fw-bold"><?php echo $student['high_scores'] ?? 0; ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo $student['total_attempts'] > 0 ? ($student['high_scores'] / $student['total_attempts']) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Success Rate</span>
                            <span class="fw-bold"><?php echo $student['total_attempts'] > 0 ? round(($student['high_scores'] / $student['total_attempts']) * 100, 1) : 0; ?>%</span>
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
                    <div class="text-center py-4">
                        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#badgesModal">
                            <i class="fas fa-award me-2"></i>
                            My Badges
                            <?php if (!empty($student_badges)): ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo count($student_badges); ?></span>
                            <?php endif; ?>
                        </button>
                        <?php if (empty($student_badges)): ?>
                        <p class="text-muted mt-3 mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Complete modules and assessments to earn badges!
                        </p>
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
</body>
</html>
