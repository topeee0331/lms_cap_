# APPENDIX G
## SYSTEM DOCUMENTATION
### NEUST-MGT BSIT Learning Management System (LMS)

---

## TABLE OF CONTENTS

1. [System Overview](#1-system-overview)
2. [Technical Specifications](#2-technical-specifications)
3. [System Architecture](#3-system-architecture)
4. [Database Schema](#4-database-schema)
5. [User Roles and Permissions](#5-user-roles-and-permissions)
6. [Core Features and Modules](#6-core-features-and-modules)
7. [Student Features](#7-student-features)
8. [Teacher Features](#8-teacher-features)
9. [Administrator Features](#9-administrator-features)
10. [Security Features](#10-security-features)
11. [System Requirements](#11-system-requirements)
12. [Installation and Setup](#12-installation-and-setup)
13. [API Documentation](#13-api-documentation)
14. [Troubleshooting Guide](#14-troubleshooting-guide)

---

## 1. SYSTEM OVERVIEW

### 1.1 Purpose
The NEUST-MGT BSIT Learning Management System (LMS) is a comprehensive digital learning platform designed specifically for the Bachelor of Science in Information Technology (BSIT) Department at Nueva Ecija University of Science and Technology - Muñoz Campus. The system provides a modern, robust, and accessible platform for managing courses, assessments, student progress, and educational content.

### 1.2 Key Objectives
- Streamline the educational experience for students, teachers, and administrators
- Provide real-time progress tracking and analytics
- Enable efficient course and content management
- Support gamification through badges and leaderboards
- Ensure secure and scalable platform architecture
- Facilitate communication through announcements and notifications

### 1.3 Target Users
- **Students**: BSIT students enrolled in various courses
- **Teachers**: Faculty members teaching BSIT courses
- **Administrators**: System administrators managing the platform

---

## 2. TECHNICAL SPECIFICATIONS

### 2.1 Frontend Technologies
- **HTML5**: Semantic markup and modern web standards
- **CSS3**: Advanced styling with custom properties and animations
- **JavaScript (ES6+)**: Modern JavaScript with AJAX functionality
- **Bootstrap 5**: Responsive framework for mobile-first design
- **Font Awesome**: Icon library for enhanced UI elements
- **Chart.js**: Data visualization and analytics charts

### 2.2 Backend Technologies
- **PHP 8.2+**: Server-side scripting language
- **MySQL/MariaDB**: Relational database management system
- **PDO**: PHP Data Objects for secure database operations
- **JSON**: Data storage and API communication format
- **Composer**: Dependency management for PHP packages

### 2.3 Third-Party Integrations
- **Pusher**: Real-time notifications and live updates
- **PHPPresentation**: PowerPoint file processing
- **PHPSpreadsheet**: Excel file processing
- **PHPWord**: Word document processing
- **HTMLPurifier**: XSS protection and content sanitization

### 2.4 Development Environment
- **XAMPP**: Local development server stack
- **Apache**: Web server
- **MySQL**: Database server
- **PHP**: Server-side language runtime

---

## 3. SYSTEM ARCHITECTURE

### 3.1 MVC Pattern Implementation
The system follows a Model-View-Controller (MVC) architectural pattern:

- **Model**: Database operations and business logic (`config/database.php`, `includes/` functions)
- **View**: User interface templates (`student/`, `teacher/`, `admin/` directories)
- **Controller**: Request handling and data processing (PHP files in root and subdirectories)

### 3.2 Directory Structure
```
lms_cap/
├── admin/                 # Administrator interface
├── student/              # Student interface
├── teacher/              # Teacher interface
├── assets/               # Static assets (CSS, JS, images)
├── config/               # Configuration files
├── database/             # SQL scripts and database files
├── includes/             # Shared PHP functions and utilities
├── uploads/              # User-uploaded files
│   ├── badges/           # Badge images
│   ├── modules/          # Course materials
│   ├── profiles/         # User profile pictures
│   └── videos/           # Educational videos
└── vendor/               # Composer dependencies
```

### 3.3 Real-time Features
- **Live Progress Updates**: 30-second interval automatic refresh
- **Real-time Notifications**: Instant updates via Pusher integration
- **Live Leaderboards**: Dynamic ranking updates
- **Activity Tracking**: Real-time user activity monitoring

---

## 4. DATABASE SCHEMA

### 4.1 Core Tables

#### Users Table
```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `status` enum('active','inactive','suspended','pending') DEFAULT 'active',
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_irregular` tinyint(1) DEFAULT 0,
  `year_level` int(11) DEFAULT 1,
  `identifier` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `access_level` enum('super_admin','admin','moderator') DEFAULT NULL,
  `academic_period_id` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
);
```

#### Courses Table
```sql
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_name` varchar(100) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `status` enum('active','inactive','archived','draft') DEFAULT 'active',
  `academic_period_id` int(11) NOT NULL,
  `year_level` varchar(10) DEFAULT NULL,
  `credits` int(11) DEFAULT 3,
  `is_archived` tinyint(1) DEFAULT 0,
  `modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `sections` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_code` (`course_code`)
);
```

#### Course Enrollments Table
```sql
CREATE TABLE `course_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `status` enum('active','completed','dropped','pending') DEFAULT 'active',
  `enrolled_at` timestamp DEFAULT current_timestamp(),
  `completion_date` timestamp NULL DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `is_completed` tinyint(1) DEFAULT 0,
  `started_at` timestamp DEFAULT current_timestamp(),
  `last_accessed` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `module_progress` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `video_progress` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`)
);
```

### 4.2 Supporting Tables
- **academic_periods**: Academic year and semester management
- **sections**: Student section organization
- **assessments**: Course assessments and quizzes
- **assessment_attempts**: Student assessment attempts and scores
- **badges**: Gamification badge system
- **announcements**: System and course announcements
- **notifications**: User notification system
- **file_uploads**: File management system
- **login_attempts**: Security and login tracking

### 4.3 JSON Data Storage
The system utilizes JSON columns for flexible data storage:
- **modules**: Course module structure and content
- **sections**: Course section assignments
- **module_progress**: Student progress tracking
- **video_progress**: Video completion tracking
- **questions**: Assessment question storage

---

## 5. USER ROLES AND PERMISSIONS

### 5.1 Student Role
**Capabilities:**
- View enrolled courses and modules
- Access course content (videos, documents, assessments)
- Take assessments and view results
- Track personal progress and statistics
- View earned badges and leaderboard rankings
- Receive notifications and announcements
- Update profile information

**Restrictions:**
- Cannot create or modify courses
- Cannot access other students' data
- Cannot modify assessment questions
- Cannot access administrative functions

### 5.2 Teacher Role
**Capabilities:**
- Create and manage courses
- Upload and organize course content
- Create and manage assessments
- Monitor student progress and performance
- Manage student enrollments
- Create announcements
- View analytics and reports
- Manage badges and gamification

**Restrictions:**
- Cannot access other teachers' courses
- Cannot modify system settings
- Cannot access administrative functions
- Cannot view other teachers' student data

### 5.3 Administrator Role
**Capabilities:**
- Full system access and control
- User management (create, edit, delete users)
- Course management across all teachers
- System configuration and settings
- Analytics and reporting
- Database management
- Security monitoring
- Academic period management

**Sub-roles:**
- **Super Admin**: Complete system control
- **Admin**: Most administrative functions
- **Moderator**: Limited administrative access

---

## 6. CORE FEATURES AND MODULES

### 6.1 Course Management System
- **Course Creation**: Teachers can create comprehensive courses with detailed information
- **Module Organization**: Hierarchical content organization with prerequisites
- **Content Upload**: Support for videos, documents, presentations, and other materials
- **Academic Period Integration**: Courses tied to specific academic periods
- **Section Management**: Organize students into manageable groups

### 6.2 Assessment System
- **Multiple Question Types**: Multiple choice, true/false, identification
- **Time Management**: Configurable time limits for assessments
- **Attempt Tracking**: Multiple attempts with best score retention
- **Automatic Grading**: Instant feedback and scoring
- **Difficulty Levels**: Easy, medium, hard classification
- **Progress Prerequisites**: Module unlocking based on assessment performance

### 6.3 Progress Tracking
- **Real-time Updates**: Live progress monitoring with 30-second refresh
- **Comprehensive Analytics**: Detailed progress statistics and charts
- **Video Progress**: Individual video completion tracking
- **Module Progress**: Step-by-step module completion
- **Course Progress**: Overall course completion percentages
- **Performance Metrics**: Scores, attempts, and improvement tracking

### 6.4 Gamification System
- **Badge System**: Achievement badges for various accomplishments
- **Points System**: Point accumulation for activities and achievements
- **Leaderboards**: Public ranking system for motivation
- **Progress Rewards**: Unlockable content and features
- **Achievement Tracking**: Comprehensive achievement monitoring

### 6.5 Communication System
- **Announcements**: Global and course-specific announcements
- **Notifications**: Real-time notification system
- **Email Integration**: Email notifications for important events
- **Pusher Integration**: Live updates and real-time communication

---

## 7. STUDENT FEATURES

### 7.1 Dashboard
- **Course Overview**: Enrolled courses with progress indicators
- **Statistics Display**: Personal performance metrics
- **Recent Activity**: Latest course activities and updates
- **Quick Access**: Direct links to important features
- **Announcements**: Latest system and course announcements

### 7.2 Course Interface
- **Course List**: View all enrolled courses
- **Course Details**: Comprehensive course information
- **Progress Tracking**: Visual progress indicators
- **Content Access**: Organized access to all course materials
- **Teacher Information**: Contact details and course instructor info

### 7.3 Module System
- **Module Navigation**: Sequential module progression
- **Content Viewing**: Videos, documents, and other materials
- **Progress Tracking**: Individual module completion status
- **Prerequisites**: Module unlocking based on performance
- **Assessment Access**: Integrated assessment system

### 7.4 Assessment Interface
- **Assessment List**: Available assessments for each module
- **Question Interface**: User-friendly question presentation
- **Timer Display**: Real-time countdown for timed assessments
- **Result Display**: Immediate feedback and scoring
- **Attempt History**: View previous attempts and improvements

### 7.5 Progress and Analytics
- **Personal Dashboard**: Comprehensive progress overview
- **Performance Charts**: Visual representation of progress
- **Score Tracking**: Assessment scores and trends
- **Badge Collection**: Earned achievements and badges
- **Leaderboard Position**: Current ranking and competition

---

## 8. TEACHER FEATURES

### 8.1 Course Management
- **Course Creation**: Comprehensive course setup and configuration
- **Content Management**: Upload and organize course materials
- **Module Organization**: Create and structure course modules
- **Assessment Creation**: Design and configure assessments
- **Student Management**: Monitor and manage enrolled students

### 8.2 Student Monitoring
- **Progress Tracking**: Real-time student progress monitoring
- **Performance Analytics**: Detailed student performance data
- **Engagement Metrics**: Student activity and participation tracking
- **Grade Management**: Assessment scoring and grade management
- **Communication Tools**: Announcements and notifications

### 8.3 Content Management
- **File Upload**: Support for various file types and formats
- **Video Management**: Video upload and streaming capabilities
- **Document Processing**: Support for Word, PowerPoint, and PDF files
- **Content Organization**: Hierarchical content structure
- **Version Control**: Content versioning and updates

### 8.4 Analytics and Reporting
- **Student Performance**: Individual and class performance metrics
- **Course Analytics**: Course effectiveness and engagement data
- **Progress Reports**: Comprehensive progress reporting
- **Export Capabilities**: Data export for external analysis
- **Visual Dashboards**: Interactive charts and graphs

---

## 9. ADMINISTRATOR FEATURES

### 9.1 User Management
- **User Creation**: Add new students, teachers, and administrators
- **Role Assignment**: Assign appropriate roles and permissions
- **User Status Management**: Activate, deactivate, or suspend accounts
- **Bulk Operations**: Mass user management capabilities
- **User Analytics**: User activity and engagement metrics

### 9.2 System Management
- **Course Oversight**: Monitor all courses across the platform
- **Content Moderation**: Review and moderate uploaded content
- **System Configuration**: Configure system settings and parameters
- **Database Management**: Database maintenance and optimization
- **Security Monitoring**: Track security events and potential threats

### 9.3 Academic Management
- **Academic Periods**: Manage academic years and semesters
- **Section Management**: Organize students into sections
- **Course Approval**: Review and approve course creations
- **Enrollment Management**: Oversee student enrollments
- **Grade Management**: System-wide grade and assessment oversight

### 9.4 Analytics and Reporting
- **System Analytics**: Platform-wide usage and performance metrics
- **User Engagement**: Overall user activity and engagement
- **Content Analytics**: Content usage and effectiveness
- **Performance Reports**: System performance and optimization reports
- **Custom Reports**: Generate custom reports for specific needs

---

## 10. SECURITY FEATURES

### 10.1 Authentication and Authorization
- **Password Hashing**: Secure password storage using bcrypt
- **Session Management**: Secure session handling and timeout
- **Role-Based Access Control**: Granular permission system
- **CSRF Protection**: Cross-site request forgery prevention
- **Input Validation**: Comprehensive input sanitization and validation

### 10.2 Data Protection
- **SQL Injection Prevention**: Prepared statements and parameterized queries
- **XSS Protection**: Cross-site scripting prevention
- **File Upload Security**: Secure file upload and validation
- **Data Encryption**: Sensitive data encryption
- **Access Logging**: Comprehensive access and activity logging

### 10.3 System Security
- **Login Attempt Monitoring**: Brute force attack prevention
- **IP Address Tracking**: Suspicious activity monitoring
- **Security Headers**: HTTP security headers implementation
- **Error Handling**: Secure error handling without information disclosure
- **Regular Security Updates**: Ongoing security maintenance

---

## 11. SYSTEM REQUIREMENTS

### 11.1 Server Requirements
- **PHP**: Version 8.2 or higher
- **MySQL/MariaDB**: Version 5.7 or higher
- **Apache**: Version 2.4 or higher
- **Web Server**: Apache or Nginx
- **Memory**: Minimum 2GB RAM (4GB recommended)
- **Storage**: Minimum 10GB available space
- **Operating System**: Linux, Windows, or macOS

### 11.2 Client Requirements
- **Web Browser**: Modern browser with JavaScript support
- **Internet Connection**: Stable internet connection
- **Screen Resolution**: Minimum 1024x768 (responsive design)
- **JavaScript**: Must be enabled
- **Cookies**: Must be enabled for session management

### 11.3 PHP Extensions
- **PDO**: Database connectivity
- **JSON**: JSON data handling
- **GD**: Image processing
- **cURL**: External API communication
- **OpenSSL**: Secure connections
- **mbstring**: Multibyte string handling

---

## 12. INSTALLATION AND SETUP

### 12.1 Prerequisites
1. Install XAMPP or similar LAMP/WAMP stack
2. Ensure PHP 8.2+ and MySQL 5.7+ are installed
3. Enable required PHP extensions
4. Configure Apache virtual host (optional)

### 12.2 Installation Steps
1. **Download and Extract**: Place files in web server directory
2. **Database Setup**: Import the provided SQL file
3. **Configuration**: Update database credentials in `config/database.php`
4. **File Permissions**: Set appropriate permissions for upload directories
5. **Composer Dependencies**: Run `composer install` for dependencies
6. **Initial Setup**: Access the system and create admin account

### 12.3 Configuration
- **Database Connection**: Update connection parameters
- **File Upload Paths**: Configure upload directory paths
- **Email Settings**: Configure SMTP for notifications
- **Pusher Settings**: Configure real-time notifications
- **Security Settings**: Review and adjust security parameters

---

## 13. API DOCUMENTATION

### 13.1 AJAX Endpoints
The system provides various AJAX endpoints for real-time functionality:

#### Student Endpoints
- `ajax_get_student_progress.php`: Get student progress data
- `ajax_get_course_progress.php`: Get course-specific progress
- `ajax_get_assessment_attempts.php`: Get assessment attempt history
- `ajax_get_leaderboard.php`: Get leaderboard data

#### Teacher Endpoints
- `ajax_get_dashboard_stats.php`: Get teacher dashboard statistics
- `ajax_get_section_students.php`: Get students in a section
- `ajax_get_student_assessment_details.php`: Get detailed assessment data
- `ajax_update_student_section.php`: Update student section assignment

#### System Endpoints
- `ajax_check_email.php`: Email availability checking
- `ajax_check_username.php`: Username availability checking
- `ajax_get_available_orders.php`: Get available assessment orders
- `ajax_validate_order.php`: Validate assessment order

### 13.2 Data Formats
All API responses use JSON format with standardized structure:
```json
{
  "success": true,
  "data": {...},
  "message": "Operation completed successfully"
}
```

---

## 14. TROUBLESHOOTING GUIDE

### 14.1 Common Issues

#### Database Connection Issues
- **Problem**: "Database connection failed" error
- **Solution**: Check database credentials in `config/database.php`
- **Verification**: Ensure MySQL service is running

#### File Upload Issues
- **Problem**: Files not uploading or processing
- **Solution**: Check file permissions on upload directories
- **Verification**: Ensure PHP upload limits are adequate

#### Session Issues
- **Problem**: Users being logged out unexpectedly
- **Solution**: Check session configuration and timeout settings
- **Verification**: Ensure cookies are enabled in browser

#### Real-time Features Not Working
- **Problem**: Live updates not functioning
- **Solution**: Check Pusher configuration and credentials
- **Verification**: Ensure internet connection and Pusher service status

### 14.2 Performance Optimization
- **Database Optimization**: Regular database maintenance and indexing
- **File Management**: Regular cleanup of temporary and old files
- **Cache Management**: Implement appropriate caching strategies
- **Resource Monitoring**: Monitor server resources and performance

### 14.3 Maintenance Tasks
- **Regular Backups**: Automated database and file backups
- **Security Updates**: Regular security patches and updates
- **Log Monitoring**: Regular review of system and error logs
- **Performance Monitoring**: Ongoing performance assessment and optimization

---

## CONCLUSION

The NEUST-MGT BSIT Learning Management System represents a comprehensive, modern, and scalable solution for educational institutions. With its robust architecture, extensive feature set, and focus on user experience, the system provides an effective platform for managing digital learning environments.

The system's modular design, real-time capabilities, and comprehensive analytics make it suitable for both small-scale implementations and large-scale educational institutions. The emphasis on security, performance, and user experience ensures that the platform can effectively serve the needs of students, teachers, and administrators while maintaining high standards of reliability and functionality.

This documentation serves as a comprehensive guide for understanding, implementing, and maintaining the NEUST-MGT BSIT LMS system, providing all necessary information for successful deployment and operation.

---

**Document Version**: 1.0  
**Last Updated**: December 2024  
**Prepared by**: NEUST-MGT BSIT Development Team  
**Contact**: raymond.salvador777@gmail.com
