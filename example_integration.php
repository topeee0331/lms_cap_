<?php
/**
 * Example Integration: How to Use Pusher Leaderboard Updates
 * 
 * This file shows how to integrate real-time leaderboard updates
 * into your existing assessment and badge systems.
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/leaderboard_events.php';

// Example 1: Assessment Completion Handler
function handleAssessmentCompletion($studentId, $assessmentId, $score) {
    // Your existing assessment completion logic here
    // ... save to database, calculate final score, etc.
    
    // NEW: Send real-time leaderboard update
    LeaderboardEvents::updateLeaderboardAfterScoreChange($studentId, $score);
    
    echo "Assessment completed! Leaderboard updated in real-time.";
}

// Example 2: Badge Award Handler
function awardBadge($studentId, $badgeName, $badgeId) {
    // Your existing badge awarding logic here
    // ... save to database, update badge count, etc.
    
    // Get updated badge count
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as badge_count FROM badges WHERE JSON_SEARCH(awarded_to, 'one', ?) IS NOT NULL");
    $stmt->execute([$studentId]);
    $badgeCount = $stmt->fetch()['badge_count'];
    
    // NEW: Send real-time badge notification
    LeaderboardEvents::sendBadgeAwarded($studentId, $badgeName, $badgeCount);
    
    echo "Badge awarded! Notification sent in real-time.";
}

// Example 3: Admin Action Handler
function adminUpdatesLeaderboard($adminId, $action) {
    // Your existing admin logic here
    // ... make changes to leaderboard data, etc.
    
    // NEW: Notify all students of changes
    LeaderboardEvents::notifyLeaderboardChange('admin_update', [
        'admin_id' => $adminId,
        'action' => $action,
        'message' => 'Leaderboard has been updated by administrator'
    ]);
    
    echo "Admin changes applied! All students notified in real-time.";
}

// Example 4: Module Completion Handler
function handleModuleCompletion($studentId, $moduleId, $completionScore) {
    // Your existing module completion logic here
    // ... save progress, update completion status, etc.
    
    // Calculate new total score
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM assessment_attempts aa WHERE aa.student_id = ? AND aa.status = 'completed') * 10 +
            COALESCE((SELECT AVG(aa.score) FROM assessment_attempts aa WHERE aa.student_id = ?), 0) * 0.5 +
            (SELECT COUNT(*) FROM badges b WHERE JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL) * 5
        as calculated_score
    ");
    $stmt->execute([$studentId, $studentId, $studentId]);
    $newScore = $stmt->fetch()['calculated_score'];
    
    // NEW: Send real-time score update
    LeaderboardEvents::sendScoreUpdate($studentId, $newScore);
    
    echo "Module completed! Score updated in real-time.";
}

// Example 5: Bulk Update Handler
function bulkUpdateLeaderboard($reason) {
    // Your existing bulk update logic here
    // ... update multiple records, recalculate scores, etc.
    
    // NEW: Send general leaderboard update
    LeaderboardEvents::notifyLeaderboardChange('bulk_update', [
        'reason' => $reason,
        'message' => 'Leaderboard has been refreshed with latest data'
    ]);
    
    echo "Bulk update completed! All students notified in real-time.";
}

// Example 6: Integration with Existing Assessment System
function integrateWithExistingAssessment() {
    // This is how you would modify your existing assessment completion code:
    
    /*
    // BEFORE (old way):
    if ($assessmentCompleted) {
        // Save to database
        $stmt = $pdo->prepare("INSERT INTO assessment_attempts ...");
        $stmt->execute([...]);
        
        // Maybe redirect or show success message
        header('Location: success.php');
    }
    */
    
    /*
    // AFTER (with Pusher):
    if ($assessmentCompleted) {
        // Save to database (same as before)
        $stmt = $pdo->prepare("INSERT INTO assessment_attempts ...");
        $stmt->execute([...]);
        
        // NEW: Send real-time update
        LeaderboardEvents::updateLeaderboardAfterScoreChange($studentId, $finalScore);
        
        // Redirect or show success message (same as before)
        header('Location: success.php');
    }
    */
}

// Example 7: Integration with Badge System
function integrateWithBadgeSystem() {
    // This is how you would modify your existing badge awarding code:
    
    /*
    // BEFORE (old way):
    if ($badgeEarned) {
        // Save badge to database
        $stmt = $pdo->prepare("INSERT INTO badges ...");
        $stmt->execute([...]);
        
        // Maybe show success message
        echo "Badge earned!";
    }
    */
    
    /*
    // AFTER (with Pusher):
    if ($badgeEarned) {
        // Save badge to database (same as before)
        $stmt = $pdo->prepare("INSERT INTO badges ...");
        $stmt->execute([...]);
        
        // NEW: Send real-time notification
        LeaderboardEvents::sendBadgeAwarded($studentId, $badgeName, $totalBadges);
        
        // Show success message (same as before)
        echo "Badge earned!";
    }
    */
}

// Example 8: Error Handling
function safeLeaderboardUpdate($studentId, $score) {
    try {
        // Attempt to send leaderboard update
        $success = LeaderboardEvents::updateLeaderboardAfterScoreChange($studentId, $score);
        
        if ($success) {
            error_log("Leaderboard update sent successfully for student $studentId");
        } else {
            error_log("Failed to send leaderboard update for student $studentId");
        }
        
    } catch (Exception $e) {
        // Log error but don't break the main flow
        error_log("Leaderboard update error: " . $e->getMessage());
    }
}

// Example 9: Conditional Updates
function conditionalLeaderboardUpdate($studentId, $score, $threshold = 70) {
    // Only send updates for significant score changes
    if ($score >= $threshold) {
        LeaderboardEvents::updateLeaderboardAfterScoreChange($studentId, $score);
    }
    
    // Or only send updates during certain hours
    $currentHour = date('H');
    if ($currentHour >= 8 && $currentHour <= 22) {
        LeaderboardEvents::updateLeaderboardAfterScoreChange($studentId, $score);
    }
}

// Example 10: Batch Updates
function batchLeaderboardUpdate($updates) {
    // Process multiple updates efficiently
    foreach ($updates as $update) {
        LeaderboardEvents::sendScoreUpdate(
            $update['student_id'], 
            $update['score']
        );
    }
    
    // Send one general update at the end
    LeaderboardEvents::notifyLeaderboardChange('batch_update', [
        'count' => count($updates),
        'message' => 'Multiple scores updated'
    ]);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusher Integration Examples</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Pusher Leaderboard Integration Examples</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            This file contains practical examples of how to integrate
                            real-time leaderboard updates into your existing LMS code.
                        </p>
                        
                        <div class="alert alert-info">
                            <h6>Key Integration Points:</h6>
                            <ul class="mb-0">
                                <li><strong>Assessment Completion:</strong> Send score updates when students complete assessments</li>
                                <li><strong>Badge Awards:</strong> Notify when students earn new badges</li>
                                <li><strong>Admin Actions:</strong> Broadcast changes when admins modify leaderboard data</li>
                                <li><strong>Module Completion:</strong> Update scores when modules are completed</li>
                                <li><strong>Bulk Updates:</strong> Notify about system-wide changes</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6>Best Practices:</h6>
                            <ul class="mb-0">
                                <li>Always wrap Pusher calls in try-catch blocks</li>
                                <li>Don't let Pusher failures break your main functionality</li>
                                <li>Use conditional updates to avoid spam</li>
                                <li>Log all Pusher activities for debugging</li>
                                <li>Test with multiple browser tabs open</li>
                            </ul>
                        </div>
                        
                        <h5>Quick Start:</h5>
                        <ol>
                            <li>Include <code>includes/leaderboard_events.php</code> in your files</li>
                            <li>Add Pusher calls after your existing database operations</li>
                            <li>Test with the <code>test_leaderboard_pusher.php</code> file</li>
                            <li>Monitor browser console for real-time updates</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
