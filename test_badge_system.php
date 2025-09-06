<?php
/**
 * Test the Badge Awarding System
 */

require_once 'config/config.php';
require_once 'includes/badge_awarding_system.php';

try {
    echo "🧪 Testing Badge Awarding System\n";
    echo str_repeat("=", 50) . "\n\n";
    
    $badgeSystem = new BadgeAwardingSystem($db);
    
    // Test with student ID 4 (John Joseph Espiritu from the database)
    $test_student_id = 4;
    
    echo "👤 Testing with Student ID: $test_student_id\n";
    echo "📊 Getting current badge statistics...\n\n";
    
    // Get current badge stats
    $stats = $badgeSystem->getStudentBadgeStats($test_student_id);
    
    echo "Current Badge Statistics:\n";
    echo "• Total Badges: {$stats['total_badges']}\n";
    echo "• Total Points: {$stats['total_points']}\n";
    echo "• Badges by Type:\n";
    foreach ($stats['badges_by_type'] as $type => $count) {
        echo "  - " . ucfirst(str_replace('_', ' ', $type)) . ": $count\n";
    }
    
    echo "\n" . str_repeat("-", 30) . "\n";
    echo "🎯 Testing Badge Awarding...\n\n";
    
    // Test course completion badges
    echo "1. Testing Course Completion Badges:\n";
    $course_badges = $badgeSystem->checkCourseCompletionBadges($test_student_id);
    if (empty($course_badges)) {
        echo "   No new course completion badges awarded.\n";
    } else {
        foreach ($course_badges as $badge) {
            echo "   ✅ Awarded: {$badge['badge_name']} (+{$badge['points_value']} points)\n";
        }
    }
    
    // Test assessment badges
    echo "\n2. Testing Assessment Badges:\n";
    $assessment_badges = $badgeSystem->checkAssessmentBadges($test_student_id, 'test_assessment', 95);
    if (empty($assessment_badges)) {
        echo "   No new assessment badges awarded.\n";
    } else {
        foreach ($assessment_badges as $badge) {
            echo "   ✅ Awarded: {$badge['badge_name']} (+{$badge['points_value']} points)\n";
        }
    }
    
    // Test video badges
    echo "\n3. Testing Video Completion Badges:\n";
    $video_badges = $badgeSystem->checkVideoBadges($test_student_id, 1);
    if (empty($video_badges)) {
        echo "   No new video badges awarded.\n";
    } else {
        foreach ($video_badges as $badge) {
            echo "   ✅ Awarded: {$badge['badge_name']} (+{$badge['points_value']} points)\n";
        }
    }
    
    // Test streak badges
    echo "\n4. Testing Streak Badges:\n";
    $streak_badges = $badgeSystem->checkStreakBadges($test_student_id);
    if (empty($streak_badges)) {
        echo "   No new streak badges awarded.\n";
    } else {
        foreach ($streak_badges as $badge) {
            echo "   ✅ Awarded: {$badge['badge_name']} (+{$badge['points_value']} points)\n";
        }
    }
    
    // Test special badges
    echo "\n5. Testing Special Badges:\n";
    $special_badges = $badgeSystem->checkSpecialBadges($test_student_id, 'early_completion');
    if (empty($special_badges)) {
        echo "   No new special badges awarded.\n";
    } else {
        foreach ($special_badges as $badge) {
            echo "   ✅ Awarded: {$badge['badge_name']} (+{$badge['points_value']} points)\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📋 Updated Badge Statistics:\n\n";
    
    // Get updated stats
    $updated_stats = $badgeSystem->getStudentBadgeStats($test_student_id);
    
    echo "• Total Badges: {$updated_stats['total_badges']}\n";
    echo "• Total Points: {$updated_stats['total_points']}\n";
    echo "• Badges by Type:\n";
    foreach ($updated_stats['badges_by_type'] as $type => $count) {
        echo "  - " . ucfirst(str_replace('_', ' ', $type)) . ": $count\n";
    }
    
    echo "\n🏆 Recent Badges:\n";
    foreach ($updated_stats['recent_badges'] as $badge) {
        $awarded_at = $badge['awarded_at'] ? date('M j, Y', strtotime($badge['awarded_at'])) : 'Unknown';
        echo "• {$badge['badge_name']} - {$awarded_at} (+{$badge['points_value']} pts)\n";
    }
    
    echo "\n✅ Badge system test completed successfully!\n";
    echo "\n💡 Next Steps:\n";
    echo "1. Integrate badge checking into your assessment completion logic\n";
    echo "2. Add badge checking to course completion workflows\n";
    echo "3. Implement daily login streak tracking\n";
    echo "4. Create badge icons and place them in uploads/badges/\n";
    echo "5. Display badges in student dashboard\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}