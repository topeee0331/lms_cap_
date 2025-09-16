<?php
$page_title = 'Profile';
require_once 'config/config.php';
requireLogin();
require_once 'includes/header.php';

// Get current user data first
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Add modern profile page styling with announcement page colors
$role_colors = [
    'admin' => ['primary' => '#2E5E4E', 'secondary' => '#6c757d', 'accent' => '#7DCB80'],
    'teacher' => ['primary' => '#2E5E4E', 'secondary' => '#6c757d', 'accent' => '#7DCB80'],
    'student' => ['primary' => '#2E5E4E', 'secondary' => '#6c757d', 'accent' => '#7DCB80']
];

$user_role = $user['role'];
$role_theme = $role_colors[$user_role] ?? $role_colors['student'];

echo '<style>
    :root {
        --role-primary: ' . $role_theme['primary'] . ';
        --role-secondary: ' . $role_theme['secondary'] . ';
        --role-accent: ' . $role_theme['accent'] . ';
    }
    
    body {
        background: linear-gradient(120deg, #F7FAF7 0%, #7DCB80 100%);
        min-height: 100vh;
        position: relative;
    }
    
    /* Subtle pattern overlay */
    .home-bg-pattern {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        width: 100vw;
        height: 100vh;
        z-index: 0;
        pointer-events: none;
        opacity: 0.13;
        background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Ccircle cx=\'30\' cy=\'30\' r=\'1.5\' fill=\'%237DCB80\'/%3E%3Ccircle cx=\'10\' cy=\'50\' r=\'1\' fill=\'%23FDD744\'/%3E%3Ccircle cx=\'50\' cy=\'10\' r=\'1\' fill=\'%232E5E4E\'/%3E%3C/svg%3E");
        background-repeat: repeat;
    }
    
    /* Abstract SVG shapes overlay */
    .home-bg-svg {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        width: 100vw;
        height: 100vh;
        z-index: 1;
        pointer-events: none;
    }
    
    .profile-container {
        background: transparent;
        min-height: 100vh;
        padding: 2rem 0;
        position: relative;
        overflow: hidden;
        z-index: 2;
    }
    
     .profile-header {
         background: var(--role-primary);
         color: white;
         padding: 3rem 0;
         margin-bottom: 3rem;
         border-radius: 0 0 3rem 3rem;
         box-shadow: 0 12px 40px rgba(0,0,0,0.15);
         position: relative;
         overflow: hidden;
     }
    
    .profile-header::before {
        content: "";
        position: absolute;
        top: -50%;
        right: -20%;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        z-index: 0;
    }
    
    .profile-header::after {
        content: "";
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 150px;
        height: 150px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        z-index: 0;
    }
    
    .profile-header-content {
        position: relative;
        z-index: 1;
    }
    
    .profile-card {
        background: white;
        border: none;
        border-radius: 2rem;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }
    
     .profile-card::before {
         content: "";
         position: absolute;
         top: 0;
         left: 0;
         right: 0;
         height: 4px;
         background: var(--role-primary);
     }
    
    .profile-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    }
    
    .profile-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #e9ecef;
        padding: 2rem;
        border-radius: 2rem 2rem 0 0;
        position: relative;
    }
    
     .profile-card .card-header::before {
         content: "";
         position: absolute;
         top: 0;
         left: 0;
         right: 0;
         height: 3px;
         background: var(--role-primary);
     }
    
    .profile-card .card-header h5 {
        color: var(--role-primary);
        font-weight: 700;
        margin: 0;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .profile-card .card-header h5 i {
        font-size: 1.1em;
        opacity: 0.8;
    }
    
    .profile-card .card-body {
        padding: 2.5rem;
    }
    
    .profile-picture-lg {
        width: 220px;
        height: 220px;
        object-fit: cover;
        border-radius: 50%;
        border: 8px solid white;
        box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    
     .profile-picture-lg::before {
         content: "";
         position: absolute;
         top: -8px;
         left: -8px;
         right: -8px;
         bottom: -8px;
         border-radius: 50%;
         background: var(--role-primary);
         z-index: -1;
     }
    
    .profile-picture-lg:hover {
        transform: scale(1.08) rotate(2deg);
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    .form-control {
        border: 2px solid #e9ecef;
        border-radius: 1rem;
        padding: 1rem 1.25rem;
        transition: all 0.3s ease;
        font-size: 1rem;
        background: #fafbfc;
    }
    
    .form-control:focus {
        border-color: var(--role-primary);
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.15);
        transform: translateY(-2px);
        background: white;
    }
    
    .form-label {
        font-weight: 700;
        color: #495057;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-label i {
        color: var(--role-primary);
        font-size: 0.9em;
    }
    
     .btn-primary {
         background: var(--role-primary);
         border: none;
         border-radius: 1rem;
         padding: 1rem 2.5rem;
         font-weight: 700;
         font-size: 1rem;
         transition: all 0.3s ease;
         box-shadow: 0 6px 20px rgba(46, 94, 78, 0.3);
         text-transform: uppercase;
         letter-spacing: 0.5px;
     }
     
     .btn-primary:hover {
         transform: translateY(-3px);
         box-shadow: 0 12px 30px rgba(46, 94, 78, 0.4);
         background: var(--role-accent);
     }
    
     .btn-warning {
         background: #ffc107;
         border: none;
         border-radius: 1rem;
         padding: 1rem 2.5rem;
         font-weight: 700;
         transition: all 0.3s ease;
         box-shadow: 0 6px 20px rgba(255, 193, 7, 0.3);
     }
     
     .btn-warning:hover {
         transform: translateY(-3px);
         box-shadow: 0 12px 30px rgba(255, 193, 7, 0.4);
         background: #fd7e14;
     }
    
    .badge {
        border-radius: 1rem;
        padding: 0.75rem 1.25rem;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
     .role-badge {
         background: var(--role-primary);
         color: white;
         padding: 0.75rem 1.5rem;
         border-radius: 2rem;
         font-weight: 700;
         font-size: 1rem;
         text-transform: uppercase;
         letter-spacing: 1px;
         box-shadow: 0 4px 15px rgba(0,0,0,0.2);
     }
    
     .upload-area {
         border: 3px dashed #dee2e6;
         border-radius: 1.5rem;
         padding: 3rem 2rem;
         text-align: center;
         transition: all 0.3s ease;
         background: #f8f9fa;
         position: relative;
         overflow: hidden;
     }
    
    .upload-area::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
        transform: translateX(-100%);
        transition: transform 0.6s ease;
    }
    
     .upload-area:hover {
         border-color: var(--role-primary);
         background: #f0f8f0;
         transform: scale(1.02);
     }
    
    .upload-area:hover::before {
        transform: translateX(100%);
    }
    
    .alert {
        border-radius: 1.5rem;
        border: none;
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        padding: 1.5rem 2rem;
        font-weight: 500;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: white;
        border-radius: 1.5rem;
        padding: 2rem;
        text-align: center;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 1px solid #f0f0f0;
        position: relative;
        overflow: hidden;
    }
    
     .stat-card::before {
         content: "";
         position: absolute;
         top: 0;
         left: 0;
         right: 0;
         height: 4px;
         background: var(--role-primary);
     }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--role-primary);
        margin-bottom: 0.5rem;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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
        background: rgba(255,255,255,0.1);
        animation: float 6s ease-in-out infinite;
    }
    
    .floating-circle:nth-child(1) {
        width: 80px;
        height: 80px;
        top: 20%;
        right: 10%;
        animation-delay: 0s;
    }
    
    .floating-circle:nth-child(2) {
        width: 120px;
        height: 120px;
        bottom: 30%;
        left: 5%;
        animation-delay: 2s;
    }
    
    .floating-circle:nth-child(3) {
        width: 60px;
        height: 60px;
        top: 60%;
        right: 20%;
        animation-delay: 4s;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }
    
    @media (max-width: 768px) {
        .profile-header {
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .profile-picture-lg {
            width: 180px;
            height: 180px;
        }
        
        .profile-card .card-body {
            padding: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
        }
    }
</style>';

$message = '';
$message_type = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $first_name = sanitizeInput($_POST['first_name'] ?? '');
            $last_name = sanitizeInput($_POST['last_name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (empty($first_name) || empty($last_name) || empty($email)) {
                $message = 'All fields are required.';
                $message_type = 'danger';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Invalid email address.';
                $message_type = 'danger';
            } else {
                // Check for duplicate email (excluding current user)
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
                $stmt->execute([$email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $message = 'Email already exists.';
                    $message_type = 'danger';
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?');
                    $stmt->execute([$first_name, $last_name, $email, $_SESSION['user_id']]);
                    
                    // Update session
                    $_SESSION['name'] = $first_name . ' ' . $last_name;
                    $_SESSION['email'] = $email;
                    
                    $message = 'Profile updated successfully.';
                    $message_type = 'success';
                }
            }
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $message = 'All password fields are required.';
                $message_type = 'danger';
            } elseif ($new_password !== $confirm_password) {
                $message = 'New passwords do not match.';
                $message_type = 'danger';
            } elseif (strlen($new_password) < 6) {
                $message = 'Password must be at least 6 characters.';
                $message_type = 'danger';
            } else {
                // Verify current password
                $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!password_verify($current_password, $user['password'])) {
                    $message = 'Current password is incorrect.';
                    $message_type = 'danger';
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $stmt->execute([$hashed, $_SESSION['user_id']]);
                    
                    $message = 'Password changed successfully.';
                    $message_type = 'success';
                }
            }
        } elseif ($action === 'upload_picture') {
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                
                if (validateFileUpload($file, ALLOWED_IMAGE_TYPES, MAX_IMAGE_SIZE)) {
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = generateUniqueFilename($file['name'], $extension);
                    $upload_path = PROFILE_UPLOAD_PATH . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Update database
                        $stmt = $pdo->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
                        $stmt->execute([$filename, $_SESSION['user_id']]);
                        
                        // Update session
                        $_SESSION['profile_picture'] = $filename;
                        
                        $message = 'Profile picture updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to upload file.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'Invalid file type or size. Please upload a JPEG or PNG image under 10MB.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Please select a file to upload.';
                $message_type = 'danger';
            }
        }
    }
}


// Helper: format section name
function formatSectionName($section) {
    if (!$section || !isset($section['year_level']) || !isset($section['section_name'])) {
        return "No Section";
    }
    return "BSIT-{$section['year_level']}{$section['section_name']}";
}
// Fetch sections for the user
$user_sections = [];
if ($user['role'] === 'student') {
    $stmt = $pdo->prepare("SELECT s.id, s.section_name, s.year_level FROM sections s WHERE JSON_SEARCH(s.students, 'one', ?) IS NOT NULL ORDER BY s.year_level, s.section_name");
    $stmt->execute([$user['id']]);
    $user_sections = $stmt->fetchAll();
} elseif ($user['role'] === 'teacher') {
    $stmt = $pdo->prepare("SELECT s.id, s.section_name, s.year_level FROM sections s WHERE JSON_SEARCH(s.teachers, 'one', ?) IS NOT NULL ORDER BY s.year_level, s.section_name");
    $stmt->execute([$user['id']]);
    $user_sections = $stmt->fetchAll();
}

// --- BADGES SECTION ---
if ($user['role'] === 'student') {
    // Fetch earned badges for the modal using new database structure
    $stmt = $pdo->prepare("
        SELECT b.id as badge_id, 
               JSON_EXTRACT(b.awarded_to, CONCAT('$[', JSON_SEARCH(b.awarded_to, 'one', ?), '].awarded_at')) as awarded_at
        FROM badges b
        WHERE JSON_SEARCH(b.awarded_to, 'one', ?) IS NOT NULL
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $earned_badges = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // badge_id => awarded_at
}
?>


<div class="profile-container">
    <!-- Home page background pattern -->
    <div class="home-bg-pattern"></div>
    
    <!-- Home page SVG overlay -->
    <div class="home-bg-svg"></div>
    
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
    </div>
    
    <div class="container py-4">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="container profile-header-content">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <img src="<?php echo getProfilePictureUrl($user['profile_picture'] ?? null, 'xlarge'); ?>" 
                             class="profile-picture-lg mb-3" alt="Profile Picture">
                    </div>
                    <div class="col-md-6">
                        <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="role-badge">
                                <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'crown' : ($user['role'] === 'teacher' ? 'chalkboard-teacher' : 'graduation-cap'); ?> me-2"></i>
                                <?php echo getRoleDisplayName($user['role']); ?>
                            </span>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-calendar me-1"></i>
                                Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                            </span>
                        </div>
                        <p class="mb-0 opacity-75 fs-5">
                            <i class="fas fa-envelope me-2"></i>
                            <?php echo htmlspecialchars($user['email']); ?>
                        </p>
                    </div>
                    <div class="col-md-3 text-end">
                        <div class="d-flex flex-column gap-2">
                            <button class="btn btn-light btn-lg" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print Profile
                            </button>
                            <button class="btn btn-outline-light" onclick="shareProfile()">
                                <i class="fas fa-share-alt me-2"></i>Share
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

        <!-- Role-Specific Statistics -->
        <?php if ($user['role'] === 'student'): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['completed_modules'] ?? 0; ?></div>
                <div class="stat-label">Modules Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['watched_videos'] ?? 0; ?></div>
                <div class="stat-label">Videos Watched</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['total_attempts'] ?? 0; ?></div>
                <div class="stat-label">Assessments Taken</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['badge_count'] ?? 0; ?></div>
                <div class="stat-label">Badges Earned</div>
            </div>
        </div>
        <?php elseif ($user['role'] === 'teacher'): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($user_sections); ?></div>
                <div class="stat-label">Sections Assigned</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['total_courses'] ?? 0; ?></div>
                <div class="stat-label">Courses Teaching</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['total_students'] ?? 0; ?></div>
                <div class="stat-label">Students Teaching</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['years_experience'] ?? 0; ?></div>
                <div class="stat-label">Years Experience</div>
            </div>
        </div>
        <?php else: // Admin ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['total_users'] ?? 0; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['total_courses'] ?? 0; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['total_enrollments'] ?? 0; ?></div>
                <div class="stat-label">Active Enrollments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $user['system_uptime'] ?? '99.9'; ?>%</div>
                <div class="stat-label">System Uptime</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Picture Section -->
            <div class="col-md-4 mb-4">
                <div class="profile-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-camera me-2"></i>Profile Picture</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="upload-area">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <h6 class="mb-3">Update Your Profile Picture</h6>
                            <p class="text-muted mb-4">Drag and drop or click to select an image</p>
                            
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="upload_picture">
                                
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="profile_picture" accept="image/jpeg,image/jpg,image/png" required>
                                    <div class="form-text">Max size: 10MB. Formats: JPEG, PNG</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Upload Picture
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Information Section -->
            <div class="col-md-8">
                <div class="profile-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="first_name" class="form-label">
                                        <i class="fas fa-user me-1"></i>First Name
                                    </label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="last_name" class="form-label">
                                        <i class="fas fa-user me-1"></i>Last Name
                                    </label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-1"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'crown' : ($user['role'] === 'teacher' ? 'chalkboard-teacher' : 'graduation-cap'); ?> me-1"></i>Role
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo getRoleDisplayName($user['role']); ?>" readonly>
                                </div>
                                
                                <?php if (!empty($user['student_id'])): ?>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-id-card me-1"></i><?php echo getRoleDisplayName($user['role']); ?> ID
                                    </label>
                                    <input type="text" class="form-control fw-bold text-primary" value="<?php echo htmlspecialchars($user['student_id']); ?>" readonly style="background-color: #f8f9fa;">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-calendar-plus me-1"></i>Member Since
                                </label>
                                <input type="text" class="form-control" value="<?php echo formatDate($user['created_at']); ?>" readonly>
                            </div>
                            
                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Role-Specific Details Card -->
                <div class="profile-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-<?php echo $user['role'] === 'admin' ? 'crown' : ($user['role'] === 'teacher' ? 'chalkboard-teacher' : 'graduation-cap'); ?> me-2"></i>Role-Specific Details</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($user['role'] === 'student'): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-users text-primary me-3 fa-lg"></i>
                                        <div>
                                            <h6 class="mb-1">Section(s)</h6>
                                            <?php if ($user_sections): ?>
                                                <?php foreach ($user_sections as $section): ?>
                                                    <span class="badge bg-primary me-1 mb-1"><?php echo htmlspecialchars(formatSectionName($section)); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No section assigned</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-<?php echo ($user['is_irregular'] ?? 0) ? 'exclamation-triangle' : 'check-circle'; ?> text-<?php echo ($user['is_irregular'] ?? 0) ? 'warning' : 'success'; ?> me-3 fa-lg"></i>
                                        <div>
                                            <h6 class="mb-1">Student Status</h6>
                                            <span class="badge bg-<?php echo ($user['is_irregular'] ?? 0) ? 'warning' : 'success'; ?>">
                                                <?php echo ($user['is_irregular'] ?? 0) ? 'Irregular' : 'Regular'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php if (isset($user['status'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-<?php echo $user['status'] === 'inactive' ? 'pause-circle' : 'play-circle'; ?> text-<?php echo $user['status'] === 'inactive' ? 'secondary' : 'success'; ?> me-3 fa-lg"></i>
                                        <div>
                                            <h6 class="mb-1">Account Status</h6>
                                            <span class="badge bg-<?php echo $user['status'] === 'inactive' ? 'secondary' : 'success'; ?>">
                                                <?php echo $user['status'] === 'inactive' ? 'Inactive' : 'Active'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($user['role'] === 'teacher'): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-users text-primary me-3 fa-lg"></i>
                                        <div>
                                            <h6 class="mb-1">Assigned Section(s)</h6>
                                            <?php if ($user_sections): ?>
                                                <?php foreach ($user_sections as $section): ?>
                                                    <span class="badge bg-primary me-1 mb-1"><?php echo htmlspecialchars(formatSectionName($section)); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No section assigned</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (isset($user['status'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-<?php echo $user['status'] === 'inactive' ? 'pause-circle' : 'play-circle'; ?> text-<?php echo $user['status'] === 'inactive' ? 'secondary' : 'success'; ?> me-3 fa-lg"></i>
                                        <div>
                                            <h6 class="mb-1">Account Status</h6>
                                            <span class="badge bg-<?php echo $user['status'] === 'inactive' ? 'secondary' : 'success'; ?>">
                                                <?php echo $user['status'] === 'inactive' ? 'Inactive' : 'Active'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: // Admin ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-crown text-danger me-3 fa-lg"></i>
                                        <div>
                                            <h6 class="mb-1">Administrative Role</h6>
                                            <span class="badge bg-danger">System Administrator</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-shield-alt text-success me-3 fa-lg"></i>
                                        <div>
                                            <h6 class="mb-1">Access Level</h6>
                                            <span class="badge bg-success">Full System Access</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Badges Section (Student Only) -->
                <?php if ($user['role'] === 'student'): ?>
                <div class="profile-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-award me-2"></i>My Badges</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="row">
                            <div class="col-md-8 mx-auto">
                                <div class="badge-showcase">
                                    <i class="fas fa-trophy fa-4x text-warning mb-4"></i>
                                    <h4 class="mb-3">Achievement Badges</h4>
                                    <p class="text-muted mb-4">Complete modules and assessments to earn recognition badges!</p>
                                    
                                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#badgesModal">
                                        <i class="fas fa-award me-2"></i>
                                        View My Badges
                                        <?php if (!empty($earned_badges)): ?>
                                            <span class="badge bg-light text-dark ms-2"><?php echo count($earned_badges); ?></span>
                                        <?php endif; ?>
                                    </button>
                                    
                                    <?php if (empty($earned_badges)): ?>
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
                                            <strong>Great job!</strong> You've earned <?php echo count($earned_badges); ?> badge<?php echo count($earned_badges) > 1 ? 's' : ''; ?>!
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Change Password Section -->
                <div class="profile-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="passwordForm">
                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="current_password" class="form-label">
                                        <i class="fas fa-key me-1"></i>Current Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <label for="new_password" class="form-label">
                                        <i class="fas fa-lock me-1"></i>New Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Confirm New Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="password-match" class="form-text"></div>
                            </div>
                            
                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetPasswordForm()">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
</div>

<!-- Badges Modal (Student Only) -->
<?php if ($user['role'] === 'student'): ?>
<div class="modal fade" id="badgesModal" tabindex="-1" aria-labelledby="badgesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="badgesModalLabel">
                    <i class="fas fa-award me-2"></i>
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>'s Badges
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($earned_badges)): ?>
                <div class="row">
                    <?php foreach ($earned_badges as $badge_id => $earned_date): ?>
                    <?php 
                    // Get badge details
                    $badge_stmt = $pdo->prepare("SELECT * FROM badges WHERE id = ?");
                    $badge_stmt->execute([$badge_id]);
                    $badge = $badge_stmt->fetch();
                    
                    if ($badge):
                        $icon_path = "uploads/badges/" . ($badge['badge_icon'] ?: 'default.png');
                        $icon_url = file_exists($icon_path) ? $icon_path : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/icons/award.svg';
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="badge-modal-card text-center p-3 border rounded cursor-pointer" 
                             data-badge-id="<?php echo $badge['id']; ?>"
                             data-badge-name="<?php echo htmlspecialchars($badge['badge_name']); ?>"
                             data-badge-description="<?php echo htmlspecialchars($badge['badge_description']); ?>"
                             data-badge-icon="<?php echo htmlspecialchars($icon_url); ?>"
                             data-badge-type="<?php echo htmlspecialchars($badge['badge_type']); ?>"
                             data-badge-criteria="<?php echo htmlspecialchars($badge['criteria']); ?>"
                             data-earned-date="<?php echo htmlspecialchars($earned_date); ?>">
                            <img src="<?php echo htmlspecialchars($icon_url); ?>" 
                                 alt="<?php echo htmlspecialchars($badge['badge_name']); ?>"
                                 class="badge-icon mb-2" style="width: 60px; height: 60px;">
                            <h6 class="mb-2"><?php echo htmlspecialchars($badge['badge_name']); ?></h6>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($badge['badge_description']); ?></p>
                            <small class="text-success">
                                <i class="fas fa-calendar me-1"></i>
                                Earned <?php echo date('M j, Y', strtotime($earned_date)); ?>
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
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

<!-- Badge Detail Modal -->
<div class="modal fade" id="badgeDetailModal" tabindex="-1" aria-labelledby="badgeDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="badgeDetailModalLabel">
                    <i class="fas fa-award me-2"></i>
                    Badge Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="badge-detail-icon mb-4">
                    <img id="badgeDetailIcon" src="" alt="Badge Icon" style="width: 120px; height: 120px; object-fit: contain;">
                </div>
                <h4 id="badgeDetailName" class="mb-3"></h4>
                <p id="badgeDetailDescription" class="text-muted mb-4"></p>
                
                <div class="row text-start">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Type:</strong>
                            <span id="badgeDetailType" class="badge bg-info ms-2"></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>Earned:</strong>
                            <span id="badgeDetailEarned" class="text-success ms-2"></span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <strong>Criteria:</strong>
                    <div class="mt-2 p-3 bg-light rounded">
                        <code id="badgeDetailCriteria"></code>
                    </div>
                </div>
                
                <div class="alert alert-success">
                    <i class="fas fa-trophy me-2"></i>
                    <strong>Congratulations!</strong> You've earned this badge for your achievements!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.badge-modal-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
}

.badge-modal-card:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.badge-icon {
    width: 50px;
    height: 50px;
    object-fit: contain;
}

.cursor-pointer {
    cursor: pointer;
}
</style>

<script>
// Badge detail modal functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle badge card clicks
    document.querySelectorAll('.badge-modal-card').forEach(function(card) {
        card.addEventListener('click', function() {
            const badgeId = this.getAttribute('data-badge-id');
            const badgeName = this.getAttribute('data-badge-name');
            const badgeDescription = this.getAttribute('data-badge-description');
            const badgeIcon = this.getAttribute('data-badge-icon');
            const badgeType = this.getAttribute('data-badge-type');
            const badgeCriteria = this.getAttribute('data-badge-criteria');
            const earnedDate = this.getAttribute('data-earned-date');
            
            // Populate the detail modal
            document.getElementById('badgeDetailIcon').src = badgeIcon;
            document.getElementById('badgeDetailName').textContent = badgeName;
            document.getElementById('badgeDetailDescription').textContent = badgeDescription;
            document.getElementById('badgeDetailType').textContent = badgeType.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
            document.getElementById('badgeDetailEarned').textContent = new Date(earnedDate).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('badgeDetailCriteria').textContent = badgeCriteria;
            
            // Show the detail modal without closing the main modal
            const detailModal = new bootstrap.Modal(document.getElementById('badgeDetailModal'));
            detailModal.show();
        });
    });
});
</script>
<?php endif; ?>

<script>
// Enhanced Profile Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Password validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('password-match');
    
    if (newPassword && confirmPassword && passwordMatch) {
        function validatePasswords() {
            if (confirmPassword.value === '') {
                passwordMatch.textContent = '';
                passwordMatch.className = 'form-text';
                return;
            }
            
            if (newPassword.value === confirmPassword.value) {
                passwordMatch.textContent = '✓ Passwords match';
                passwordMatch.className = 'form-text text-success';
            } else {
                passwordMatch.textContent = '✗ Passwords do not match';
                passwordMatch.className = 'form-text text-danger';
            }
        }
        
        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            }
        });
    });
    
    // File upload preview
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // You could add image preview here
                    console.log('File selected:', file.name);
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Reset form
function resetForm() {
    const form = document.querySelector('form[action*="update_profile"]');
    if (form) {
        form.reset();
        // Reset to original values
        const inputs = form.querySelectorAll('input[type="text"], input[type="email"]');
        inputs.forEach(input => {
            input.value = input.getAttribute('value') || '';
        });
    }
}

// Reset password form
function resetPasswordForm() {
    const form = document.getElementById('passwordForm');
    if (form) {
        form.reset();
        const passwordMatch = document.getElementById('password-match');
        if (passwordMatch) {
            passwordMatch.textContent = '';
            passwordMatch.className = 'form-text';
        }
    }
}

// Share profile functionality
function shareProfile() {
    if (navigator.share) {
        navigator.share({
            title: 'My Profile - LMS System',
            text: 'Check out my profile on the Learning Management System',
            url: window.location.href
        });
    } else {
        // Fallback for browsers that don't support Web Share API
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Profile link copied to clipboard!');
        }).catch(() => {
            alert('Unable to copy link. Please copy the URL manually.');
        });
    }
}

// Print profile functionality
function printProfile() {
    window.print();
}

// Add smooth scrolling for better UX
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add loading states for better UX
function showLoading(element) {
    const originalContent = element.innerHTML;
    element.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
    element.disabled = true;
    
    return function hideLoading() {
        element.innerHTML = originalContent;
        element.disabled = false;
    };
}

// Enhanced form validation
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Add form validation on submit
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!validateForm(this)) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
});
</script>
 
 <?php require_once 'includes/footer.php'; ?> 