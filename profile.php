<?php
$page_title = 'Profile';
require_once 'config/config.php';
requireLogin();
require_once 'includes/header.php';

// Add modern profile page styling
echo '<style>
    .profile-container {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        min-height: 100vh;
        padding: 2rem 0;
    }
    
    .profile-header {
        background: linear-gradient(135deg, var(--main-green) 0%, var(--accent-green) 100%);
        color: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
        border-radius: 0 0 2rem 2rem;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    }
    
    .profile-card {
        background: white;
        border: none;
        border-radius: 1.5rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    
    .profile-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    .profile-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #e9ecef;
        padding: 1.5rem;
        border-radius: 1.5rem 1.5rem 0 0;
    }
    
    .profile-card .card-header h5 {
        color: var(--main-green);
        font-weight: 600;
        margin: 0;
        font-size: 1.1rem;
    }
    
    .profile-card .card-body {
        padding: 2rem;
    }
    
    .profile-picture-lg {
        width: 200px;
        height: 200px;
        object-fit: cover;
        border-radius: 50%;
        border: 6px solid white;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }
    
    .profile-picture-lg:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }
    
    .form-control {
        border: 2px solid #e9ecef;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }
    
    .form-control:focus {
        border-color: var(--accent-green);
        box-shadow: 0 0 0 0.2rem rgba(125, 203, 128, 0.25);
        transform: translateY(-1px);
    }
    
    .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--main-green) 0%, var(--accent-green) 100%);
        border: none;
        border-radius: 0.75rem;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(46, 94, 78, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(46, 94, 78, 0.4);
    }
    
    .badge {
        border-radius: 0.75rem;
        padding: 0.5rem 1rem;
        font-weight: 500;
        font-size: 0.85rem;
    }
    
    .upload-area {
        border: 2px dashed #dee2e6;
        border-radius: 1rem;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    
    .upload-area:hover {
        border-color: var(--accent-green);
        background: #f0f8f0;
    }
    
    .alert {
        border-radius: 1rem;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    @media (max-width: 768px) {
        .profile-header {
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        
        .profile-picture-lg {
            width: 150px;
            height: 150px;
        }
        
        .profile-card .card-body {
            padding: 1.5rem;
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

// Get current user data
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

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
    <!-- Background Design Elements -->
    <div class="floating-shape-1"></div>
    <div class="floating-shape-2"></div>
    <div class="floating-shape-3"></div>
    <div class="system-watermark"></div>
    
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="h3 mb-4">Profile</h1>
            </div>
        </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Picture Section -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Profile Picture</h5>
                </div>
                <div class="card-body text-center">
                    <img src="<?php echo getProfilePictureUrl($user['profile_picture'] ?? null, 'xlarge'); ?>" 
                         class="profile-picture-lg mb-3" alt="Profile Picture">
                    
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="upload_picture">
                        
                        <div class="mb-3">
                            <input type="file" class="form-control" name="profile_picture" accept="image/jpeg,image/jpg,image/png" required>
                            <div class="form-text">Max size: 10MB. Formats: JPEG, PNG</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Upload Picture
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Profile Information Section -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?php echo getRoleDisplayName($user['role']); ?>" readonly>
                        </div>
                        
                        <?php if (!empty($user['student_id'])): ?>
                        <div class="mb-3">
                            <label class="form-label"><?php echo getRoleDisplayName($user['role']); ?> ID</label>
                            <input type="text" class="form-control fw-bold text-primary" value="<?php echo htmlspecialchars($user['student_id']); ?>" readonly style="background-color: #f8f9fa;">
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Member Since</label>
                            <input type="text" class="form-control" value="<?php echo formatDate($user['created_at']); ?>" readonly>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Role-Specific Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Role-Specific Details</h5>
                </div>
                <div class="card-body">
                    <?php if ($user['role'] === 'student'): ?>
                        <div class="mb-2">
                            <strong>Section(s):</strong>
                            <?php if ($user_sections): ?>
                                <?php foreach ($user_sections as $section): ?>
                                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(formatSectionName($section)); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No section assigned</span>
                            <?php endif; ?>
                        </div>
                        <div class="mb-2">
                            <strong>Student Status:</strong>
                            <span class="badge bg-<?php echo ($user['is_irregular'] ?? 0) ? 'danger' : 'success'; ?>">
                                <?php echo ($user['is_irregular'] ?? 0) ? 'Irregular' : 'Regular'; ?>
                            </span>
                        </div>
                        <?php if (isset($user['status'])): ?>
                        <div class="mb-2">
                            <strong>Account Status:</strong>
                            <span class="badge bg-<?php echo $user['status'] === 'inactive' ? 'secondary' : 'success'; ?>">
                                <?php echo $user['status'] === 'inactive' ? 'Inactive' : 'Active'; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    <?php elseif ($user['role'] === 'teacher'): ?>
                        <div class="mb-2">
                            <strong>Assigned Section(s):</strong>
                            <?php if ($user_sections): ?>
                                <?php foreach ($user_sections as $section): ?>
                                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(formatSectionName($section)); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No section assigned</span>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($user['status'])): ?>
                        <div class="mb-2">
                            <strong>Account Status:</strong>
                            <span class="badge bg-<?php echo $user['status'] === 'inactive' ? 'secondary' : 'success'; ?>">
                                <?php echo $user['status'] === 'inactive' ? 'Inactive' : 'Active'; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mb-2">
                            <strong>Role:</strong> Administrator
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Badges Section (Student Only) -->
            <?php if ($user['role'] === 'student'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">My Badges</h5>
                </div>
                <div class="card-body text-center">
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#badgesModal">
                        <i class="fas fa-award me-2"></i>
                        View My Badges
                        <?php if (!empty($earned_badges)): ?>
                            <span class="badge bg-light text-dark ms-2"><?php echo count($earned_badges); ?></span>
                        <?php endif; ?>
                    </button>
                    <?php if (empty($earned_badges)): ?>
                    <p class="text-muted mt-3 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Complete modules and assessments to earn badges!
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Change Password Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-2"></i>Change Password
                        </button>
                    </form>
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
 
 <?php require_once 'includes/footer.php'; ?> 