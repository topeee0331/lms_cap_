<?php
/**
 * Test file to demonstrate Pusher leaderboard updates
 * This shows how to trigger real-time leaderboard updates
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/leaderboard_events.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Simulate a score update
if (isset($_POST['simulate_score_update'])) {
    $newScore = rand(50, 100);
    
    // Send real-time update
    LeaderboardEvents::updateLeaderboardAfterScoreChange($user_id, $newScore);
    
    echo "<div class='alert alert-success'>Score update sent! Check the leaderboard for real-time updates.</div>";
}

// Simulate a badge award
if (isset($_POST['simulate_badge_award'])) {
    $badgeName = "Test Badge " . rand(1, 100);
    $badgeCount = rand(1, 10);
    
    // Send real-time update
    LeaderboardEvents::sendBadgeAwarded($user_id, $badgeName, $badgeCount);
    
    echo "<div class='alert alert-success'>Badge award sent! Check the leaderboard for real-time updates.</div>";
}

// Simulate a general leaderboard update
if (isset($_POST['simulate_leaderboard_update'])) {
    // Send general update
    LeaderboardEvents::notifyLeaderboardChange('general_update', [
        'message' => 'Leaderboard has been refreshed',
        'reason' => 'New data available'
    ]);
    
    echo "<div class='alert alert-success'>Leaderboard update sent!</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Pusher Leaderboard Updates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Test Pusher Leaderboard Updates</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Use these buttons to test real-time leaderboard updates. 
                            Open the leaderboard page in another tab to see the updates in real-time.
                        </p>
                        
                        <div class="d-grid gap-2">
                            <form method="POST" class="d-inline">
                                <button type="submit" name="simulate_score_update" class="btn btn-primary">
                                    🎯 Simulate Score Update
                                </button>
                            </form>
                            
                            <form method="POST" class="d-inline">
                                <button type="submit" name="simulate_badge_award" class="btn btn-warning">
                                    🏅 Simulate Badge Award
                                </button>
                            </form>
                            
                            <form method="POST" class="d-inline">
                                <button type="submit" name="simulate_leaderboard_update" class="btn btn-info">
                                    🏆 Simulate Leaderboard Update
                                </button>
                            </form>
                        </div>
                        
                        <hr>
                        
                        <div class="alert alert-info">
                            <h6>How to Test:</h6>
                            <ol>
                                <li>Open the leaderboard page in another tab: <a href="student/leaderboard.php" target="_blank">student/leaderboard.php</a></li>
                                <li>Click any of the test buttons above</li>
                                <li>Watch the leaderboard update in real-time without page refresh!</li>
                            </ol>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6>Requirements:</h6>
                            <ul>
                                <li>Pusher must be configured with valid credentials</li>
                                <li>Both tabs must be open in the same browser</li>
                                <li>You must be logged in as a student</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
