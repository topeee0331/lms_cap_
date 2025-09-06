<?php
/**
 * Create achievement badges for the LMS system
 * Based on the database structure from lms_neust_normalized (6).sql
 */

require_once 'config/config.php';

try {
    echo "Creating achievement badges for the LMS system...\n\n";
    
    // Define achievement badges based on the database structure
    $achievement_badges = [
        // Course Completion Badges
        [
            'badge_name' => 'First Steps',
            'badge_description' => 'Completed your first course in the LMS',
            'badge_icon' => 'first_steps.png',
            'badge_type' => 'course_completion',
            'criteria' => '{"type":"course_completion","courses_required":1}',
            'points_value' => 10,
            'created_by' => null
        ],
        [
            'badge_name' => 'Course Master',
            'badge_description' => 'Completed 5 courses successfully',
            'badge_icon' => 'course_master.png',
            'badge_type' => 'course_completion',
            'criteria' => '{"type":"course_completion","courses_required":5}',
            'points_value' => 50,
            'created_by' => null
        ],
        [
            'badge_name' => 'Academic Excellence',
            'badge_description' => 'Completed 10 courses with outstanding performance',
            'badge_icon' => 'academic_excellence.png',
            'badge_type' => 'course_completion',
            'criteria' => '{"type":"course_completion","courses_required":10,"min_average_grade":85}',
            'points_value' => 100,
            'created_by' => null
        ],
        
        // Assessment Performance Badges
        [
            'badge_name' => 'Perfect Score',
            'badge_description' => 'Achieved 100% on any assessment',
            'badge_icon' => 'perfect_score.png',
            'badge_type' => 'high_score',
            'criteria' => '{"type":"high_score","min_score":100}',
            'points_value' => 25,
            'created_by' => null
        ],
        [
            'badge_name' => 'High Achiever',
            'badge_description' => 'Scored 90% or higher on an assessment',
            'badge_icon' => 'high_achiever.png',
            'badge_type' => 'high_score',
            'criteria' => '{"type":"high_score","min_score":90}',
            'points_value' => 15,
            'created_by' => null
        ],
        [
            'badge_name' => 'Consistent Performer',
            'badge_description' => 'Achieved 80% or higher on 5 consecutive assessments',
            'badge_icon' => 'consistent_performer.png',
            'badge_type' => 'high_score',
            'criteria' => '{"type":"consecutive_high_scores","min_score":80,"consecutive_count":5}',
            'points_value' => 40,
            'created_by' => null
        ],
        [
            'badge_name' => 'Assessment Warrior',
            'badge_description' => 'Completed 20 assessments successfully',
            'badge_icon' => 'assessment_warrior.png',
            'badge_type' => 'participation',
            'criteria' => '{"type":"assessment_completion","assessments_required":20}',
            'points_value' => 30,
            'created_by' => null
        ],
        
        // Video Learning Badges
        [
            'badge_name' => 'Video Learner',
            'badge_description' => 'Watched 10 videos completely',
            'badge_icon' => 'video_learner.png',
            'badge_type' => 'participation',
            'criteria' => '{"type":"video_completion","videos_required":10}',
            'points_value' => 20,
            'created_by' => null
        ],
        [
            'badge_name' => 'Knowledge Seeker',
            'badge_description' => 'Watched 50 videos across all courses',
            'badge_icon' => 'knowledge_seeker.png',
            'badge_type' => 'participation',
            'criteria' => '{"type":"video_completion","videos_required":50}',
            'points_value' => 60,
            'created_by' => null
        ],
        [
            'badge_name' => 'Focused Learner',
            'badge_description' => 'Watched 5 videos in a single day',
            'badge_icon' => 'focused_learner.png',
            'badge_type' => 'streak',
            'criteria' => '{"type":"daily_video_watching","videos_required":5}',
            'points_value' => 15,
            'created_by' => null
        ],
        
        // Streak Badges
        [
            'badge_name' => 'Daily Learner',
            'badge_description' => 'Logged in and studied for 7 consecutive days',
            'badge_icon' => 'daily_learner.png',
            'badge_type' => 'streak',
            'criteria' => '{"type":"login_streak","days_required":7}',
            'points_value' => 25,
            'created_by' => null
        ],
        [
            'badge_name' => 'Dedicated Student',
            'badge_description' => 'Maintained a 30-day learning streak',
            'badge_icon' => 'dedicated_student.png',
            'badge_type' => 'streak',
            'criteria' => '{"type":"login_streak","days_required":30}',
            'points_value' => 100,
            'created_by' => null
        ],
        [
            'badge_name' => 'Weekend Warrior',
            'badge_description' => 'Completed activities on 5 consecutive weekends',
            'badge_icon' => 'weekend_warrior.png',
            'badge_type' => 'streak',
            'criteria' => '{"type":"weekend_activity","weekends_required":5}',
            'points_value' => 35,
            'created_by' => null
        ],
        
        // Special Achievement Badges
        [
            'badge_name' => 'Early Bird',
            'badge_description' => 'Completed an assessment within the first hour of availability',
            'badge_icon' => 'early_bird.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"early_completion","time_threshold":3600}',
            'points_value' => 20,
            'created_by' => null
        ],
        [
            'badge_name' => 'Speed Demon',
            'badge_description' => 'Completed an assessment in less than half the time limit',
            'badge_icon' => 'speed_demon.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"fast_completion","time_ratio":0.5}',
            'points_value' => 30,
            'created_by' => null
        ],
        [
            'badge_name' => 'Perfect Attendance',
            'badge_description' => 'Never missed a single day of course activities for a month',
            'badge_icon' => 'perfect_attendance.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"perfect_attendance","days_required":30}',
            'points_value' => 75,
            'created_by' => null
        ],
        [
            'badge_name' => 'Module Master',
            'badge_description' => 'Completed all modules in a single course',
            'badge_icon' => 'module_master.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"module_completion","all_modules":true}',
            'points_value' => 40,
            'created_by' => null
        ],
        [
            'badge_name' => 'Assessment Ace',
            'badge_description' => 'Passed 10 assessments on the first attempt',
            'badge_icon' => 'assessment_ace.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"first_attempt_success","assessments_required":10}',
            'points_value' => 50,
            'created_by' => null
        ],
        
        // Year-Level Specific Badges
        [
            'badge_name' => 'Freshman Explorer',
            'badge_description' => 'Completed 3 first-year courses',
            'badge_icon' => 'freshman_explorer.png',
            'badge_type' => 'course_completion',
            'criteria' => '{"type":"year_level_completion","year_level":1,"courses_required":3}',
            'points_value' => 30,
            'created_by' => null
        ],
        [
            'badge_name' => 'Sophomore Scholar',
            'badge_description' => 'Completed 3 second-year courses',
            'badge_icon' => 'sophomore_scholar.png',
            'badge_type' => 'course_completion',
            'criteria' => '{"type":"year_level_completion","year_level":2,"courses_required":3}',
            'points_value' => 35,
            'created_by' => null
        ],
        [
            'badge_name' => 'Junior Expert',
            'badge_description' => 'Completed 3 third-year courses',
            'badge_icon' => 'junior_expert.png',
            'badge_type' => 'course_completion',
            'criteria' => '{"type":"year_level_completion","year_level":3,"courses_required":3}',
            'points_value' => 40,
            'created_by' => null
        ],
        [
            'badge_name' => 'Senior Specialist',
            'badge_description' => 'Completed 3 fourth-year courses',
            'badge_icon' => 'senior_specialist.png',
            'badge_type' => 'course_completion',
            'criteria' => '{"type":"year_level_completion","year_level":4,"courses_required":3}',
            'points_value' => 45,
            'created_by' => null
        ],
        
        // Difficulty-Based Badges
        [
            'badge_name' => 'Easy Rider',
            'badge_description' => 'Completed 5 easy-level assessments',
            'badge_icon' => 'easy_rider.png',
            'badge_type' => 'participation',
            'criteria' => '{"type":"difficulty_completion","difficulty":"easy","assessments_required":5}',
            'points_value' => 15,
            'created_by' => null
        ],
        [
            'badge_name' => 'Medium Master',
            'badge_description' => 'Completed 10 medium-level assessments',
            'badge_icon' => 'medium_master.png',
            'badge_type' => 'participation',
            'criteria' => '{"type":"difficulty_completion","difficulty":"medium","assessments_required":10}',
            'points_value' => 35,
            'created_by' => null
        ],
        [
            'badge_name' => 'Hard Core',
            'badge_description' => 'Completed 5 hard-level assessments',
            'badge_icon' => 'hard_core.png',
            'badge_type' => 'participation',
            'criteria' => '{"type":"difficulty_completion","difficulty":"hard","assessments_required":5}',
            'points_value' => 50,
            'created_by' => null
        ],
        
        // Time-Based Badges
        [
            'badge_name' => 'Night Owl',
            'badge_description' => 'Completed 5 activities between 10 PM and 6 AM',
            'badge_icon' => 'night_owl.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"time_based_activity","time_range":"night","activities_required":5}',
            'points_value' => 25,
            'created_by' => null
        ],
        [
            'badge_name' => 'Morning Person',
            'badge_description' => 'Completed 5 activities between 6 AM and 10 AM',
            'badge_icon' => 'morning_person.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"time_based_activity","time_range":"morning","activities_required":5}',
            'points_value' => 25,
            'created_by' => null
        ],
        
        // Milestone Badges
        [
            'badge_name' => 'Century Club',
            'badge_description' => 'Earned 100 total points from all activities',
            'badge_icon' => 'century_club.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"total_points","points_required":100}',
            'points_value' => 0, // This badge itself doesn't give points
            'created_by' => null
        ],
        [
            'badge_name' => 'Point Collector',
            'badge_description' => 'Earned 500 total points from all activities',
            'badge_icon' => 'point_collector.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"total_points","points_required":500}',
            'points_value' => 0,
            'created_by' => null
        ],
        [
            'badge_name' => 'Legend',
            'badge_description' => 'Earned 1000 total points from all activities',
            'badge_icon' => 'legend.png',
            'badge_type' => 'special',
            'criteria' => '{"type":"total_points","points_required":1000}',
            'points_value' => 0,
            'created_by' => null
        ]
    ];
    
    // Check if badges already exist
    $stmt = $db->prepare("SELECT COUNT(*) FROM badges");
    $stmt->execute();
    $existing_count = $stmt->fetchColumn();
    
    if ($existing_count > 0) {
        echo "⚠️  Found $existing_count existing badges in the database.\n";
        echo "This script will add new achievement badges without duplicating existing ones.\n\n";
    }
    
    $added_count = 0;
    $skipped_count = 0;
    
    foreach ($achievement_badges as $badge) {
        // Check if badge already exists
        $stmt = $db->prepare("SELECT id FROM badges WHERE badge_name = ?");
        $stmt->execute([$badge['badge_name']]);
        
        if ($stmt->fetch()) {
            echo "⏭️  Skipped: {$badge['badge_name']} (already exists)\n";
            $skipped_count++;
            continue;
        }
        
        // Insert new badge
        $stmt = $db->prepare("
            INSERT INTO badges (badge_name, badge_description, badge_icon, badge_type, criteria, points_value, created_by, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $badge['badge_name'],
            $badge['badge_description'],
            $badge['badge_icon'],
            $badge['badge_type'],
            $badge['criteria'],
            $badge['points_value'],
            $badge['created_by']
        ]);
        
        echo "✅ Added: {$badge['badge_name']} ({$badge['badge_type']}) - {$badge['points_value']} points\n";
        $added_count++;
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 Badge Creation Summary:\n";
    echo "✅ Added: $added_count new badges\n";
    echo "⏭️  Skipped: $skipped_count existing badges\n";
    echo "📊 Total badges in database: " . ($existing_count + $added_count) . "\n\n";
    
    // Display badge categories
    $stmt = $db->prepare("
        SELECT badge_type, COUNT(*) as count, SUM(points_value) as total_points 
        FROM badges 
        GROUP BY badge_type 
        ORDER BY badge_type
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    echo "📋 Badge Categories:\n";
    foreach ($categories as $category) {
        echo "   • " . ucfirst(str_replace('_', ' ', $category['badge_type'])) . ": {$category['count']} badges ({$category['total_points']} total points)\n";
    }
    
    echo "\n🎯 Next Steps:\n";
    echo "1. Access the badge management system at: /teacher/badges.php\n";
    echo "2. View student badge achievements at: /teacher/student_badges.php\n";
    echo "3. Implement badge awarding logic in your application\n";
    echo "4. Create badge icons and place them in the uploads/badges/ directory\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
