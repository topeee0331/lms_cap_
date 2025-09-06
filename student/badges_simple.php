<?php
require_once '../config/config.php';
require_once '../config/database.php';

$db = new Database();
$pdo = $db->getConnection();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get earned badges from JSON awarded_to field
$stmt = $pdo->prepare("
    SELECT b.*, 
           JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', ?), '].awarded_at')) as earned_at
    FROM badges b
    WHERE JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL
    ORDER BY earned_at DESC
");
$stmt->execute([$user_id, $user_id]);
$earned_badges = $stmt->fetchAll();

// Get all available badges with earned status from JSON
$stmt = $pdo->prepare("
    SELECT b.*, 
           CASE WHEN JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL THEN 1 ELSE 0 END as is_earned,
           JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', ?), '].awarded_at')) as earned_at
    FROM badges b
    ORDER BY b.created_at ASC
");
$stmt->execute([$user_id, $user_id]);
$all_badges = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badges - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">NEUST-MGT BSIT LMS</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <main class="col-12 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Badges</h1>
                </div>

                <!-- Badge Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-trophy fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Earned Badges</h5>
                                <p class="card-text h4"><?php echo count($earned_badges); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-medal fa-3x text-info mb-3"></i>
                                <h5 class="card-title">Total Badges</h5>
                                <p class="card-text h4"><?php echo count($all_badges); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-percentage fa-3x text-success mb-3"></i>
                                <h5 class="card-title">Completion Rate</h5>
                                <p class="card-text h4"><?php echo count($all_badges) > 0 ? round((count($earned_badges) / count($all_badges)) * 100) : 0; ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Earned Badges -->
                <?php if (!empty($earned_badges)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Earned Badges</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($earned_badges as $badge): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card border-success">
                                            <div class="card-body text-center">
                                                <i class="fas fa-<?php echo htmlspecialchars($badge['icon']); ?> fa-3x text-success mb-3"></i>
                                                <h6 class="card-title"><?php echo htmlspecialchars($badge['badge_name']); ?></h6>
                                                <p class="card-text small"><?php echo htmlspecialchars($badge['description']); ?></p>
                                                <small class="text-muted">
                                                    Earned: <?php echo date('M j, Y', strtotime($badge['earned_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- All Badges -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">All Available Badges</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($all_badges as $badge): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card <?php echo $badge['is_earned'] ? 'border-success' : 'border-secondary'; ?>">
                                            <div class="card-body text-center">
                                                <i class="fas fa-<?php echo htmlspecialchars($badge['icon']); ?> fa-3x <?php echo $badge['is_earned'] ? 'text-success' : 'text-muted'; ?> mb-3"></i>
                                                <h6 class="card-title"><?php echo htmlspecialchars($badge['badge_name']); ?></h6>
                                                <p class="card-text small"><?php echo htmlspecialchars($badge['description']); ?></p>
                                                <?php if ($badge['is_earned']): ?>
                                                    <span class="badge bg-success">Earned</span>
                                                    <br><small class="text-muted">
                                                        Earned: <?php echo date('M j, Y', strtotime($badge['earned_at'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not Earned</span>
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
