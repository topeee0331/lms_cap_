<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/student_id_generator.php';

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $year = intval($_POST['year'] ?? 0);
    $section_id = intval($_POST['section_id'] ?? 0);
    $is_irregular = isset($_POST['is_irregular']) ? 1 : 0;
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token.';
    } elseif (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password) || !$year || (!$is_irregular && !$section_id)) {
        $error = 'All fields are required, including year. Section is required for regular students.';
    } elseif (strlen($first_name) < 2 || !preg_match('/^[a-zA-Z\s]+$/', $first_name)) {
        $error = 'First name must be at least 2 characters and contain only letters.';
    } elseif (strlen($last_name) < 2 || !preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $error = 'Last name must be at least 2 characters and contain only letters.';
    } elseif (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username must be at least 3 characters and contain only letters, numbers, and underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must contain at least one special character.';
    } else {
        // Check for duplicate email/username
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = 'Email or username already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate unique student ID
            $studentId = generateStudentId($db);
            
            // Insert user with identifier, is_irregular, and year_level if columns exist
            $columns = 'username, email, password, first_name, last_name, role, identifier';
            $values = '?, ?, ?, ?, ?, "student", ?';
            $params = [$username, $email, $hashed, $first_name, $last_name, $studentId];
            if (columnExists($db, 'users', 'is_irregular')) {
                $columns .= ', is_irregular';
                $values .= ', ?';
                $params[] = $is_irregular;
            }
            if (columnExists($db, 'users', 'year_level')) {
                $columns .= ', year_level';
                $values .= ', ?';
                $params[] = $year;
            }
            $stmt = $db->prepare("INSERT INTO users ($columns) VALUES ($values)");
            $stmt->execute($params);
            $user_id = $db->lastInsertId();
            // Assign to section if selected (for both regular and irregular)
            if ($section_id) {
                // First, remove student from any existing sections to prevent duplicates
                $stmt = $db->prepare("UPDATE sections SET students = JSON_REMOVE(students, JSON_UNQUOTE(JSON_SEARCH(students, 'one', ?))) WHERE JSON_SEARCH(students, 'one', ?) IS NOT NULL");
                $stmt->execute([$user_id, $user_id]);
                
                // Get current students in the selected section
                $stmt = $db->prepare("SELECT students FROM sections WHERE id = ?");
                $stmt->execute([$section_id]);
                $current_students = json_decode($stmt->fetchColumn(), true) ?? [];
                
                // Add new student to array (only if not already present)
                if (!in_array($user_id, $current_students)) {
                    $current_students[] = $user_id;
                    
                    // Update section
                    $stmt = $db->prepare("UPDATE sections SET students = ? WHERE id = ?");
                    $stmt->execute([json_encode($current_students), $section_id]);
                }
            }
            // Auto-login
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = 'student';
            $_SESSION['name'] = $first_name . ' ' . $last_name;
            $_SESSION['email'] = $email;
            $_SESSION['profile_picture'] = 'default.png';
            header('Location: student/dashboard.php');
            exit();
        }
    }
}
// Helper to check if column exists
function columnExists($db, $table, $column) {
    $result = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->rowCount() > 0;
}
// Fetch all active sections for the current academic period
$sections = [];
// Get the current active academic period
$active_period_stmt = $db->query('SELECT id FROM academic_periods WHERE is_active = 1 ORDER BY academic_year DESC, semester_name DESC LIMIT 1');
$active_period_id = $active_period_stmt ? $active_period_stmt->fetchColumn() : 1;

$section_stmt = $db->prepare('SELECT id, section_name as name, year_level as year FROM sections WHERE is_active = 1 AND academic_period_id = ? ORDER BY year_level, section_name');
$section_stmt->execute([$active_period_id]);
if ($section_stmt && $section_stmt->rowCount() > 0) {
    $sections = $section_stmt->fetchAll();
}

// Only include header if we're not redirecting
if (!isset($_SESSION['user_id'])) {
    $page_title = 'Register';
    require_once 'includes/header.php';
} else {
    // If user is logged in, redirect to appropriate dashboard
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($_SESSION['role'] === 'teacher') {
        header('Location: teacher/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
    exit();
}
?>
<style>
:root {
    --main-green: #2E5E4E;
    --accent-green: #7DCB80;
    --highlight-yellow: #FFE066;
    --off-white: #F7FAF7;
    --white: #FFFFFF;
    --login-green: #7DCB80;
}
body {
    background: var(--off-white);
    min-height: 100vh;
    position: relative;
    padding-top: 0; /* Remove padding-top since we have navbar */
    margin: 0 !important;
    overflow-x: hidden !important;
}

/* Ensure navbar is visible and properly positioned */
.navbar {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 1030 !important;
    background: rgba(255, 255, 255, 0.97) !important;
    backdrop-filter: blur(20px) !important;
    border-bottom: none !important;
    box-shadow: 0 6px 24px rgba(46,94,78,0.08), 0 1.5px 4px rgba(0,0,0,0.04) !important;
    border-radius: 0 0 16px 16px !important;
    height: 60px !important;
}

.navbar-accent-bar {
    display: block !important;
    visibility: visible !important;
    height: 4px !important;
    width: 100% !important;
    background: linear-gradient(90deg, var(--main-green) 0%, var(--accent-green) 100%) !important;
    border-radius: 0 0 12px 12px !important;
    box-shadow: 0 2px 8px rgba(46,94,78,0.08) !important;
    margin-bottom: 0.5rem !important;
}
.login-split-container {
    display: flex;
    min-height: calc(100vh - 60px); /* Subtract navbar height */
    margin-top: 60px; /* Add margin to account for fixed navbar */
}
.login-left-bg {
    flex: 0 0 60%;
    max-width: 60%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: none;
    position: relative;
    min-width: 0;
    padding: 3.5rem 2.5rem 2.5rem 2.5rem;
    overflow: hidden;
}
.login-left-bg-bgimg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background: url('images/neust.png') center center/cover no-repeat;
    opacity: 0.35;
    z-index: 1;
}
.login-left-bg-gradient {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, rgba(125,203,128,0.12) 0%, var(--login-green) 100%);
    z-index: 2;
}
.login-logos-bg, .login-title, .login-desc {
    position: relative;
    z-index: 3;
}
.login-logos-bg {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 2.2rem;
    margin-bottom: 2.2rem;
}
.login-logos-bg img {
    max-width: 140px;
    max-height: 140px;
}
.login-title {
    color: var(--main-green);
    font-size: 2.9rem;
    font-weight: 900;
    margin-bottom: 1.2rem;
    line-height: 1.1;
    text-shadow: 0 2px 8px rgba(255,255,255,0.18);
}
.login-desc {
    color: #333;
    font-size: 1.3rem;
    font-weight: 400;
    max-width: 600px;
    margin-bottom: 0.7rem;
    background: rgba(255,255,255,0.7);
    border-radius: 0.7rem;
    padding: 1.1rem 1.7rem;
    box-shadow: 0 2px 8px rgba(46,94,78,0.06);
}
.login-right-content {
    flex: 0 0 40%;
    max-width: 40%;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
    background: var(--login-green);
}
.login-card {
    background: #fff;
    border-radius: 1.5rem;
    box-shadow: 0 8px 32px rgba(46,94,78,0.13);
    border: 1.5px solid var(--accent-green);
    padding: 3rem 2.5rem 2.5rem 2.5rem;
    margin: 2.5rem 0;
    width: 100%;
    max-width: 480px;
    animation: fadeInUp 1.1s;
}
.login-card .card-header {
    background: none;
    border: none;
    color: var(--main-green);
    font-weight: 800;
    font-size: 2rem;
    letter-spacing: 1px;
    margin-bottom: 0.5rem;
}
.login-card .logo {
    display: block;
    margin: 0 auto 1.2rem auto;
    max-width: 80px;
    filter: drop-shadow(0 4px 16px rgba(0,0,0,0.10));
}
.login-card .form-label {
    color: var(--main-green);
    font-weight: 600;
}
.login-card .form-control {
    border-radius: 1rem;
    border: 1.5px solid var(--accent-green);
    font-size: 1.1rem;
    padding: 0.8rem 1rem;
    background: #f8faf7;
    color: #2E5E4E;
    box-shadow: none;
    transition: border 0.2s;
}
.login-card .form-control:focus {
    border: 1.5px solid var(--main-green);
    box-shadow: 0 0 0 2px #7DCB8033;
}

/* Make select dropdowns look like form-control fields */
.login-card .form-select {
    border-radius: 1rem;
    border: 1.5px solid var(--accent-green);
    font-size: 1.1rem;
    padding: 0.8rem 1rem;
    background: #f8faf7;
    color: #2E5E4E;
    box-shadow: none;
    transition: border 0.2s;
    width: 100%;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%232E5E4E' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
}

.login-card .form-select:focus {
    border: 1.5px solid var(--main-green);
    box-shadow: 0 0 0 2px #7DCB8033;
    outline: none;
}

.login-card .form-select option {
    background: #f8faf7;
    color: #2E5E4E;
    padding: 0.5rem;
}

.login-card .form-select option:disabled {
    background: #f0f0f0;
    color: #999;
    font-style: italic;
}

/* Password field with toggle icon */
.password-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input-wrapper .form-control {
    padding-right: 3rem; /* Make room for the icon */
}

.password-toggle {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #6c757d;
    transition: color 0.2s;
    z-index: 10;
    background: none;
    border: none;
    padding: 0.25rem;
    border-radius: 0.25rem;
}

.password-toggle:hover {
    color: var(--main-green);
}

.password-toggle i {
    font-size: 1.1rem;
}
.login-card .btn-primary {
    background: var(--main-green);
    border: none;
    font-weight: 700;
    border-radius: 2rem;
    font-size: 1.1rem;
    padding: 0.7rem 2.2rem;
    transition: background 0.2s;
}
.login-card .btn-primary:hover {
    background: var(--accent-green);
    color: var(--main-green);
}

.login-card .btn-secondary {
    background: #6c757d;
    border: none;
    font-weight: 700;
    border-radius: 2rem;
    font-size: 1.1rem;
    padding: 0.7rem 2.2rem;
    transition: background 0.2s;
    cursor: not-allowed;
    opacity: 0.6;
}

.login-card .btn-secondary:hover {
    background: #6c757d;
    color: white;
    cursor: not-allowed;
}
.login-card .alert {
    border-radius: 1rem;
    font-size: 1rem;
}
.login-card .register-link {
    color: var(--main-green);
    font-weight: 500;
    text-decoration: underline;
    transition: color 0.2s;
}
.login-card .register-link:hover {
    color: var(--accent-green);
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form validation feedback styles */
.form-feedback {
    font-size: 0.875rem;
    margin-top: 0.25rem;
    min-height: 1.25rem;
    display: block;
}

.form-feedback.invalid-feedback {
    color: #dc3545;
    display: block;
}

.form-feedback.valid-feedback {
    color: #198754;
    display: block;
}

.form-control.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25);
}

.form-control.is-valid {
    border-color: #198754;
    box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.25);
}

.form-select.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25);
}

.form-select.is-valid {
    border-color: #198754;
    box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.25);
}

/* Password checklist styles */
.password-checklist {
    list-style: none;
    padding: 0;
    margin: 0.5rem 0 0 0;
    font-size: 0.8rem;
}

.password-checklist li {
    padding: 0.25rem 0;
    color: #6c757d;
    transition: color 0.2s;
}

.password-checklist li.unmet {
    color: #dc3545;
}

.password-checklist li.met {
    color: #198754;
}

.password-checklist li.met::before {
    content: "✓ ";
    font-weight: bold;
}

.password-checklist li.unmet::before {
    content: "✗ ";
    font-weight: bold;
}

/* Irregular student tooltip */
.irregular-tooltip {
    display: inline-block;
    width: 16px;
    height: 16px;
    background: var(--main-green);
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 16px;
    font-size: 12px;
    font-weight: bold;
    margin-left: 0.25rem;
    cursor: help;
}

/* Loading spinner for async validation */
.loading-spinner {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid var(--main-green);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 0.5rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
@media (max-width: 991px) {
    .login-split-container {
        flex-direction: column;
        margin-top: 80px; /* More margin on mobile for navbar */
    }
    .login-left-bg, .login-right-content {
        max-width: 100%;
        flex: 1 1 100%;
    }
    .login-left-bg {
        min-height: 180px;
        padding: 1.2rem 0.5rem 0.5rem 0.5rem;
    }
    .login-logos-bg img {
        max-width: 64px;
        max-height: 64px;
    }
    .login-title {
        font-size: 1.3rem;
    }
    .login-desc {
        font-size: 0.95rem;
        padding: 0.5rem 0.7rem;
    }
    .login-right-content {
        min-height: 60vh;
        padding: 1.2rem 0.5rem;
    }
    .login-card {
        max-width: 100%;
        padding: 1.2rem 0.7rem 1.2rem 0.7rem;
    }
}
</style>
<div class="login-main-content">
  <div class="login-split-container">
    <div class="login-left-bg">
      <div class="login-left-bg-bgimg"></div>
      <div class="login-left-bg-gradient"></div>
      <div class="login-logos-bg">
        <img src="uploads/logo/Itlogo.svg" alt="IT Logo">
        <img src="uploads/logo/mainLogo.png" alt="LMS Logo">
      </div>
      <div class="login-title">
        Learning Management<br>System of NEUST - MGT<br>BSIT Program
      </div>
      <div class="login-desc">
        "Empowering NEUST-MGT BSIT students and educators with a smart, all-in-one Learning Management System — featuring real-time grading, interactive quizzes, lesson uploads, and performance analytics."
      </div>
</div>
    <div class="login-right-content">
      <div class="login-card shadow-sm">
        <div class="card-header text-center">
          Register
        </div>
        <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"> <?php echo $error; ?> </div>
        <?php endif; ?>
        <form method="post" autocomplete="off" id="registerForm" novalidate>
            <div class="register-grid mb-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.2rem; width: 100%;">
              <div>
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First Name" required autofocus aria-required="true">
                    <div class="form-feedback" id="firstNameFeedback"></div>
                </div>
              <div>
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last Name" required aria-required="true">
                    <div class="form-feedback" id="lastNameFeedback"></div>
                </div>
              <div>
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required aria-required="true" aria-describedby="usernameFeedback">
                <div class="form-feedback" id="usernameFeedback"></div>
            </div>
              <div>
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required aria-required="true" aria-describedby="emailFeedback">
                <div class="form-feedback" id="emailFeedback"></div>
                </div>
              <div>
                <label for="year" class="form-label">Year Level</label>
                <select class="form-select" id="year" name="year" required aria-required="true" style="width: 100%;">
                  <option value="">Year Level</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                    <div class="form-feedback" id="yearFeedback"></div>
                </div>
              <div>
                    <label for="section_id" class="form-label">Section</label>
                <select class="form-select" id="section_id" name="section_id" aria-describedby="sectionFeedback" style="width: 100%;">
                  <option value="">Section</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" data-year="<?php echo $section['year']; ?>">
                                <?php echo htmlspecialchars($section['name']); ?> (Year <?php echo $section['year']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-feedback" id="sectionFeedback"></div>
                </div>
              <div class="password-group">
                <label for="password" class="form-label">New Password</label>
                <div class="password-input-wrapper">
                    <input type="password" class="form-control" id="password" name="password" placeholder="New Password" required aria-required="true" aria-describedby="passwordFeedback passwordChecklist">
                    <span class="password-toggle" onclick="togglePassword('password')"><i class="bi bi-eye-slash" id="togglePasswordIcon"></i></span>
                </div>
                <div class="form-feedback" id="passwordFeedback"></div>
                <div class="form-group form-check mb-2 mt-2">
                <input type="checkbox" class="form-check-input" id="is_irregular" name="is_irregular">
                <label class="form-check-label" for="is_irregular">
                    Irregular Student
                    <span class="irregular-tooltip" title="Check if you are not enrolled in a regular section.">?</span>
                </label>
            </div>
                <ul class="password-checklist" id="passwordChecklist" style="margin-bottom:0;">
                <li id="pwd-upper" class="unmet">One uppercase letter</li>
                <li id="pwd-length" class="unmet">At least 8 characters</li>
                  <li id="pwd-number" class="unmet">One number</li>
                  <li id="pwd-symbol" class="unmet">One special character</li>
                </ul>
              </div>
              <div class="password-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="password-input-wrapper">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required aria-required="true" aria-describedby="confirmPasswordFeedback">
                    <span class="password-toggle" onclick="togglePassword('confirm_password')"><i class="bi bi-eye-slash" id="toggleConfirmPasswordIcon"></i></span>
                </div>
                <div class="form-feedback" id="confirmPasswordFeedback"></div>
              </div>
              <div style="grid-column: 1 / span 2; text-align: center;">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                <div class="d-flex justify-content-center mb-3 mt-4">
                  <button type="submit" class="btn btn-primary" id="signupBtn" disabled>Sign Up</button>
            </div>
            <div class="text-center mt-2">
                <span style="color: #888; font-size: 0.98rem;">Already have an account?</span>
                <a href="login.php" class="login-link ms-1">Log in</a>
                </div>
              </div>
            </div>
        </form>
        </div>
      </div>
    </div>
    </div>
</div>
<?php 
    require_once 'includes/footer.php';
?>
<script>
// Global validation state
let validationState = {
    firstName: false,
    lastName: false,
    username: false,
    email: false,
    year: false,
    section: false,
    password: false,
    confirmPassword: false,
    usernameAvailable: null,
    emailAvailable: null
};

// Debounce function for async validation
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Show validation feedback
function showFeedback(fieldId, isValid, message) {
    const field = document.getElementById(fieldId);
    // Map field IDs to correct feedback element IDs
    const feedbackIdMap = {
        'first_name': 'firstNameFeedback',
        'last_name': 'lastNameFeedback',
        'username': 'usernameFeedback',
        'email': 'emailFeedback',
        'year': 'yearFeedback',
        'section_id': 'sectionFeedback',
        'password': 'passwordFeedback',
        'confirm_password': 'confirmPasswordFeedback'
    };
    const feedbackId = feedbackIdMap[fieldId] || fieldId + 'Feedback';
    const feedback = document.getElementById(feedbackId);
    
    if (isValid) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        feedback.classList.remove('invalid-feedback');
        feedback.classList.add('valid-feedback');
        feedback.textContent = message || 'Looks good!';
    } else {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        feedback.classList.remove('valid-feedback');
        feedback.classList.add('invalid-feedback');
        feedback.textContent = message || 'Please check this field.';
    }
}

// Clear validation feedback
function clearFeedback(fieldId) {
    const field = document.getElementById(fieldId);
    // Map field IDs to correct feedback element IDs
    const feedbackIdMap = {
        'first_name': 'firstNameFeedback',
        'last_name': 'lastNameFeedback',
        'username': 'usernameFeedback',
        'email': 'emailFeedback',
        'year': 'yearFeedback',
        'section_id': 'sectionFeedback',
        'password': 'passwordFeedback',
        'confirm_password': 'confirmPasswordFeedback'
    };
    const feedbackId = feedbackIdMap[fieldId] || fieldId + 'Feedback';
    const feedback = document.getElementById(feedbackId);
    
    field.classList.remove('is-valid', 'is-invalid');
    feedback.classList.remove('valid-feedback', 'invalid-feedback');
    feedback.textContent = '';
}

// Validate first name
function validateFirstName() {
    const firstName = document.getElementById('first_name').value.trim();
    const isValid = firstName.length >= 2 && /^[a-zA-Z\s]+$/.test(firstName);
    
    validationState.firstName = isValid;
    
    if (firstName.length === 0) {
        clearFeedback('first_name');
    } else if (!isValid) {
        showFeedback('first_name', false, 'First name must be at least 2 characters and contain only letters.');
    } else {
        showFeedback('first_name', true);
    }
    
    updateSubmitButton();
}

// Validate last name
function validateLastName() {
    const lastName = document.getElementById('last_name').value.trim();
    const isValid = lastName.length >= 2 && /^[a-zA-Z\s]+$/.test(lastName);
    
    validationState.lastName = isValid;
    
    if (lastName.length === 0) {
        clearFeedback('last_name');
    } else if (!isValid) {
        showFeedback('last_name', false, 'Last name must be at least 2 characters and contain only letters.');
    } else {
        showFeedback('last_name', true);
    }
    
    updateSubmitButton();
}

// Validate username
function validateUsername() {
    const username = document.getElementById('username').value.trim();
    const isValid = username.length >= 3 && /^[a-zA-Z0-9_]+$/.test(username);
    
    validationState.username = isValid;
    
    if (username.length === 0) {
        clearFeedback('username');
    } else if (!isValid) {
        showFeedback('username', false, 'Username must be at least 3 characters and contain only letters, numbers, and underscores.');
    } else {
        showFeedback('username', true);
        // Check availability asynchronously
        checkUsernameAvailability(username);
    }
    
    updateSubmitButton();
}

// Validate email
function validateEmail() {
    const email = document.getElementById('email').value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const isValid = emailRegex.test(email);
    
    validationState.email = isValid;
    
    if (email.length === 0) {
        clearFeedback('email');
    } else if (!isValid) {
        showFeedback('email', false, 'Please enter a valid email address.');
    } else {
        showFeedback('email', true);
        // Check availability asynchronously
        checkEmailAvailability(email);
    }
    
    updateSubmitButton();
}

// Validate year level
function validateYear() {
    const year = document.getElementById('year').value;
    const isValid = year !== '';
    
    validationState.year = isValid;
    
    if (!isValid) {
        showFeedback('year', false, 'Please select your year level.');
        // Clear section validation when no year is selected
        clearFeedback('section_id');
    } else {
        showFeedback('year', true);
        // Filter sections when year is selected
        filterSectionsByYear();
    }
    
    updateSubmitButton();
}

// Validate section
function validateSection() {
    const section = document.getElementById('section_id').value;
    const isIrregular = document.getElementById('is_irregular').checked;
    const year = document.getElementById('year').value;
    const isValid = isIrregular || section !== '';
    
    validationState.section = isValid;
    
    // Only show error if year is selected and no section is chosen
    if (!isIrregular && section === '' && year !== '') {
        showFeedback('section_id', false, 'Please select a section or check "Irregular Student".');
    } else {
        clearFeedback('section_id');
    }
    
    updateSubmitButton();
}

// Validate password
function validatePassword() {
    const password = document.getElementById('password').value;
    const hasUpper = /[A-Z]/.test(password);
    const hasLength = password.length >= 8;
    const hasNumber = /[0-9]/.test(password);
    const hasSymbol = /[^A-Za-z0-9]/.test(password);
    const isValid = hasUpper && hasLength && hasNumber && hasSymbol;
    
    validationState.password = isValid;
    
    if (password.length === 0) {
        clearFeedback('password');
    } else if (!isValid) {
        showFeedback('password', false, 'Password must meet all requirements below.');
    } else {
        showFeedback('password', true);
    }
    
    // Update password checklist
    updatePasswordChecklist();
    
    // Re-validate confirm password if it has a value
    const confirmPassword = document.getElementById('confirm_password').value;
    if (confirmPassword.length > 0) {
        validateConfirmPassword();
    }
    
    updateSubmitButton();
}

// Validate confirm password
function validateConfirmPassword() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const isValid = password === confirmPassword && password.length > 0;
    
    validationState.confirmPassword = isValid;
    
    if (confirmPassword.length === 0) {
        clearFeedback('confirm_password');
    } else if (!isValid) {
        showFeedback('confirm_password', false, 'Passwords do not match.');
    } else {
        showFeedback('confirm_password', true);
    }
    
    updateSubmitButton();
}

// Check username availability
const checkUsernameAvailability = debounce(async function(username) {
    if (username.length < 3) return;
    
    const feedback = document.getElementById('usernameFeedback');
    const originalText = feedback.textContent;
    
    // Show loading
    feedback.innerHTML = 'Checking availability... <span class="loading-spinner"></span>';
    
    try {
        const response = await fetch('ajax_check_username.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `username=${encodeURIComponent(username)}`
        });
        
        const data = await response.json();
        
        if (data.available) {
            validationState.usernameAvailable = true;
            showFeedback('username', true, 'Username is available!');
        } else {
            validationState.usernameAvailable = false;
            showFeedback('username', false, 'Username is already taken.');
        }
    } catch (error) {
        console.error('Error checking username:', error);
        validationState.usernameAvailable = null;
        feedback.textContent = originalText;
    }
    
    updateSubmitButton();
}, 500);

// Check email availability
const checkEmailAvailability = debounce(async function(email) {
    if (!email.includes('@')) return;
    
    const feedback = document.getElementById('emailFeedback');
    const originalText = feedback.textContent;
    
    // Show loading
    feedback.innerHTML = 'Checking availability... <span class="loading-spinner"></span>';
    
    try {
        const response = await fetch('ajax_check_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}`
        });
        
        const data = await response.json();
        
        if (data.available) {
            validationState.emailAvailable = true;
            showFeedback('email', true, 'Email is available!');
        } else {
            validationState.emailAvailable = false;
            showFeedback('email', false, 'Email is already registered.');
        }
    } catch (error) {
        console.error('Error checking email:', error);
        validationState.emailAvailable = null;
        feedback.textContent = originalText;
    }
    
    updateSubmitButton();
}, 500);

// Update password checklist
function updatePasswordChecklist() {
    const password = document.getElementById('password').value;
    const liUpper = document.getElementById('pwd-upper');
    const liLength = document.getElementById('pwd-length');
    const liNumber = document.getElementById('pwd-number');
    const liSymbol = document.getElementById('pwd-symbol');
    
    // Check requirements
    const hasUpper = /[A-Z]/.test(password);
    const hasLength = password.length >= 8;
    const hasNumber = /[0-9]/.test(password);
    const hasSymbol = /[^A-Za-z0-9]/.test(password);
    
    // Update checklist items
    liUpper.className = hasUpper ? 'met' : 'unmet';
    liLength.className = hasLength ? 'met' : 'unmet';
    liNumber.className = hasNumber ? 'met' : 'unmet';
    liSymbol.className = hasSymbol ? 'met' : 'unmet';
}

// Filter sections by year
function filterSectionsByYear() {
    const year = document.getElementById('year').value;
    const sectionSelect = document.getElementById('section_id');
    
    // Store all original options for filtering
    if (!window.allSectionOptions) {
        window.allSectionOptions = [];
        for (let i = 0; i < sectionSelect.options.length; i++) {
            window.allSectionOptions.push({
                value: sectionSelect.options[i].value,
                text: sectionSelect.options[i].textContent,
                dataYear: sectionSelect.options[i].getAttribute('data-year')
            });
        }
    }
    
    // Store currently selected section value
    const currentSectionValue = sectionSelect.value;
    
    // Clear current options
    sectionSelect.innerHTML = '';
    
    // Add the default option
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Section';
    sectionSelect.appendChild(defaultOption);
    
    // If no year is selected, don't show any sections
    if (!year) {
        // Clear validation when no year is selected
        clearFeedback('section_id');
        return;
    }
    
    // Add filtered options
    let hasVisibleOptions = false;
    let selectedSectionStillValid = false;
    
    for (let i = 0; i < window.allSectionOptions.length; i++) {
        const option = window.allSectionOptions[i];
        if (option.value && option.dataYear === year) {
            const newOption = document.createElement('option');
            newOption.value = option.value;
            newOption.textContent = option.text;
            newOption.setAttribute('data-year', option.dataYear);
            sectionSelect.appendChild(newOption);
            hasVisibleOptions = true;
            
            // Check if the previously selected section is still valid for this year
            if (option.value === currentSectionValue) {
                selectedSectionStillValid = true;
            }
        }
    }
    
    // If no sections available for selected year, show message
    if (!hasVisibleOptions && year) {
        const noSectionOption = document.createElement('option');
        noSectionOption.value = '';
        noSectionOption.textContent = 'No sections available for Year ' + year;
        noSectionOption.disabled = true;
        sectionSelect.appendChild(noSectionOption);
    }
    
    // Restore selection if it's still valid, otherwise clear it
    if (selectedSectionStillValid && currentSectionValue) {
        sectionSelect.value = currentSectionValue;
    } else {
        sectionSelect.value = '';
    }
    
    // Only validate section if we're not in the middle of filtering
    // The change event will handle validation
}

// Toggle password visibility
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const icon = fieldId === 'password' ? document.getElementById('togglePasswordIcon') : document.getElementById('toggleConfirmPasswordIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    }
}

// Update submit button state
function updateSubmitButton() {
    const isFormValid = validationState.firstName && 
                       validationState.lastName && 
                       validationState.username && 
                       validationState.email && 
                       validationState.year && 
                       validationState.section && 
                       validationState.password && 
                       validationState.confirmPassword &&
                       validationState.usernameAvailable === true &&
                       validationState.emailAvailable === true;
    
    const signupBtn = document.getElementById('signupBtn');
    signupBtn.disabled = !isFormValid;
    
    // Update button appearance
    if (isFormValid) {
        signupBtn.classList.remove('btn-secondary');
        signupBtn.classList.add('btn-primary');
    } else {
        signupBtn.classList.remove('btn-primary');
        signupBtn.classList.add('btn-secondary');
    }
}

// Form submission validation
function validateFormSubmission() {
    // Run all validations one more time
    validateFirstName();
    validateLastName();
    validateUsername();
    validateEmail();
    validateYear();
    validateSection();
    validatePassword();
    validateConfirmPassword();
    
    // Check if form is valid
    const isFormValid = validationState.firstName && 
                       validationState.lastName && 
                       validationState.username && 
                       validationState.email && 
                       validationState.year && 
                       validationState.section && 
                       validationState.password && 
                       validationState.confirmPassword &&
                       validationState.usernameAvailable === true &&
                       validationState.emailAvailable === true;
    
    if (!isFormValid) {
        // Scroll to first invalid field
        const firstInvalidField = document.querySelector('.is-invalid');
        if (firstInvalidField) {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalidField.focus();
        }
        return false;
    }
    
    return true;
}

// Initialize form
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners
    document.getElementById('first_name').addEventListener('input', validateFirstName);
    document.getElementById('first_name').addEventListener('blur', validateFirstName);
    
    document.getElementById('last_name').addEventListener('input', validateLastName);
    document.getElementById('last_name').addEventListener('blur', validateLastName);
    
    document.getElementById('username').addEventListener('input', validateUsername);
    document.getElementById('username').addEventListener('blur', validateUsername);
    
    document.getElementById('email').addEventListener('input', validateEmail);
    document.getElementById('email').addEventListener('blur', validateEmail);
    
    document.getElementById('year').addEventListener('change', validateYear);
    
    document.getElementById('section_id').addEventListener('change', validateSection);
    
    document.getElementById('password').addEventListener('input', validatePassword);
    document.getElementById('password').addEventListener('blur', validatePassword);
    
    document.getElementById('confirm_password').addEventListener('input', validateConfirmPassword);
    document.getElementById('confirm_password').addEventListener('blur', validateConfirmPassword);
    
    document.getElementById('is_irregular').addEventListener('change', validateSection);
    
    // Form submission
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        if (!validateFormSubmission()) {
            e.preventDefault();
        }
    });
    
    // Initial validation
    updateSubmitButton();
});
</script>