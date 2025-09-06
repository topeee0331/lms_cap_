# Teacher Badge Management System

## Overview
The Teacher Badge Management System allows teachers to create, manage, and track student badge achievements within the LMS. This system provides a comprehensive gamification layer to enhance student engagement and motivation.

## Features

### For Teachers
- **Create Custom Badges**: Design badges with custom names, descriptions, icons, and criteria
- **Manage Badge Types**: Support for participation, course completion, high score, learning streak, and special achievement badges
- **Set Criteria**: Define JSON-based criteria for automatic badge awarding
- **Track Student Progress**: View which students have earned which badges
- **Badge Analytics**: See statistics on badge distribution and student engagement
- **Role-Based Access**: Teachers can only edit badges they created

### For Students
- **Automatic Badge Awarding**: Badges are automatically awarded when students meet criteria
- **Badge Notifications**: Students receive notifications when they earn new badges
- **Progress Tracking**: Students can view their earned badges and progress
- **Points System**: Each badge awards points that contribute to overall student ranking

## File Structure

```
teacher/
├── badges.php              # Main badge management interface
├── student_badges.php      # View student badge achievements
└── ...

includes/
├── badge_system.php        # Core badge awarding logic
├── badge_notification.php  # Badge notification system
└── ...

uploads/
└── badges/                 # Badge icon storage directory
```

## Database Schema

### badges Table
```sql
CREATE TABLE `badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `badge_name` varchar(50) NOT NULL,
  `badge_description` text DEFAULT NULL,
  `badge_icon` varchar(255) NOT NULL,
  `badge_type` enum('course_completion','high_score','participation','streak','special') DEFAULT 'participation',
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON criteria for earning the badge',
  `points_value` int(11) DEFAULT 0 COMMENT 'Points awarded for earning this badge',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL COMMENT 'Teacher who created this badge',
  `awarded_to` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of users who earned this badge',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `badge_name` (`badge_name`),
  KEY `idx_badge_type` (`badge_type`),
  KEY `fk_badges_created_by` (`created_by`),
  CONSTRAINT `fk_badges_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Installation & Setup

### 1. Database Migration
Run the database migration script to add required columns:
```bash
php update_badges_table.php
```

### 2. Test the System
Verify everything is working correctly:
```bash
php test_badge_system.php
```

### 3. Create Upload Directory
Ensure the badge uploads directory exists:
```bash
mkdir -p uploads/badges
chmod 755 uploads/badges
```

## Usage Guide

### Creating a Badge

1. **Access Badge Management**: Navigate to `/teacher/badges.php`
2. **Click "Create Badge"**: Open the badge creation modal
3. **Fill in Details**:
   - **Badge Name**: Unique name for the badge
   - **Description**: What the badge represents
   - **Badge Type**: Choose from available types
   - **Points Value**: Points awarded (0-1000)
   - **Icon**: Upload a custom icon (optional)
   - **Criteria**: JSON criteria for earning the badge

### Badge Criteria Examples

#### Participation Badges
```json
{"assessments_taken": 3}           // Complete 3 assessments
{"videos_watched": 20}             // Watch 20 videos
{"days_active": 7}                 // Active for 7 days
```

#### Course Completion Badges
```json
{"courses_completed": 1}           // Complete 1 course
{"modules_completed": 10}          // Complete 10 modules
```

#### High Score Badges
```json
{"average_score": 90}              // Maintain 90% average
{"perfect_scores": 3}              // Get 3 perfect scores
```

#### Learning Streak Badges
```json
{"consecutive_days": 7}            // 7 consecutive days of activity
```

### Viewing Student Badges

1. **Access Student Badges**: Navigate to `/teacher/student_badges.php`
2. **Filter Results**: Use course, student, or badge filters
3. **View Statistics**: See overall badge distribution and averages
4. **Track Progress**: Monitor individual student achievements

## Badge Types

### 1. Participation
Awarded for general engagement and activity
- Examples: "First Assessment", "Video Watcher", "Consistent Learner"

### 2. Course Completion
Awarded for completing courses or modules
- Examples: "First Course Complete", "Module Explorer", "Course Master"

### 3. High Score
Awarded for academic achievement
- Examples: "Perfect Score", "High Achiever", "Excellence"

### 4. Learning Streak
Awarded for consistent learning patterns
- Examples: "7-Day Streak", "Monthly Learner"

### 5. Special Achievement
Awarded for unique accomplishments
- Examples: "Early Bird", "Perfect Attendance", "Mentor"

## Automatic Badge Awarding

The system automatically awards badges when students meet criteria. This happens:

- After completing assessments
- After watching videos
- After completing courses
- During daily activity checks
- When viewing progress pages

## Customization

### Adding New Badge Types
1. Update the `badge_type` enum in the database
2. Add new criteria checking logic in `BadgeSystem::checkBadgeCriteria()`
3. Update the UI dropdown options

### Custom Criteria
The system supports flexible JSON-based criteria. You can create complex conditions by extending the `checkBadgeCriteria()` method.

## Security Features

- **Role-Based Access**: Teachers can only edit their own badges
- **Input Validation**: All inputs are sanitized and validated
- **File Upload Security**: Only image files are allowed for badge icons
- **CSRF Protection**: All forms include CSRF tokens

## Troubleshooting

### Common Issues

1. **Badges Not Awarding**
   - Check if criteria JSON is valid
   - Verify badge is active (`is_active = 1`)
   - Ensure student meets all criteria

2. **Upload Errors**
   - Check file permissions on `uploads/badges/`
   - Verify file is PNG, JPG, or JPEG
   - Check file size limits

3. **Database Errors**
   - Run `update_badges_table.php` to fix schema issues
   - Check foreign key constraints

### Debug Mode
Enable debug logging in `config/config.php` to see detailed badge awarding logs.

## Future Enhancements

- **Badge Categories**: Group badges by subject or difficulty
- **Badge Templates**: Pre-made badge templates for common achievements
- **Badge Challenges**: Time-limited or special event badges
- **Badge Leaderboards**: Show top badge earners
- **Badge Sharing**: Allow students to share achievements
- **Badge Analytics**: Detailed reporting on badge effectiveness

## Support

For technical support or feature requests, please contact the development team or create an issue in the project repository.

---

**Version**: 1.0.0  
**Last Updated**: December 2024  
**Compatibility**: PHP 7.4+, MySQL 5.7+
