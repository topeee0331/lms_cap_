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
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
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
function filterSectionsByYear() {
    var year = document.getElementById('year').value;
    var sectionSelect = document.getElementById('section_id');
    
    // Store all original options for filtering
    if (!window.allSectionOptions) {
        window.allSectionOptions = [];
        for (var i = 0; i < sectionSelect.options.length; i++) {
            window.allSectionOptions.push({
                value: sectionSelect.options[i].value,
                text: sectionSelect.options[i].textContent,
                dataYear: sectionSelect.options[i].getAttribute('data-year')
            });
        }
    }
    
    // Clear current options
    sectionSelect.innerHTML = '';
    
    // Add the default option
    var defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Section';
    sectionSelect.appendChild(defaultOption);
    
    // If no year is selected, don't show any sections
    if (!year) {
        return;
    }
    
    // Add filtered options
    var hasVisibleOptions = false;
    for (var i = 0; i < window.allSectionOptions.length; i++) {
        var option = window.allSectionOptions[i];
        if (option.value && option.dataYear === year) {
            var newOption = document.createElement('option');
            newOption.value = option.value;
            newOption.textContent = option.text;
            newOption.setAttribute('data-year', option.dataYear);
            sectionSelect.appendChild(newOption);
            hasVisibleOptions = true;
        }
    }
    
    // If no sections available for selected year, show message
    if (!hasVisibleOptions && year) {
        var noSectionOption = document.createElement('option');
        noSectionOption.value = '';
        noSectionOption.textContent = 'No sections available for Year ' + year;
        noSectionOption.disabled = true;
        sectionSelect.appendChild(noSectionOption);
    }
}
function togglePassword(fieldId) {
    var input = document.getElementById(fieldId);
    var icon = fieldId === 'password' ? document.getElementById('togglePasswordIcon') : document.getElementById('toggleConfirmPasswordIcon');
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
function updatePasswordChecklist() {
    var pwd = document.getElementById('password').value;
    var liUpper = document.getElementById('pwd-upper');
    var liLength = document.getElementById('pwd-length');
    var liNumber = document.getElementById('pwd-number');
    var liSymbol = document.getElementById('pwd-symbol');
    // Check requirements
    var hasUpper = /[A-Z]/.test(pwd);
    var hasLength = pwd.length >= 8;
    var hasNumber = /[0-9]/.test(pwd);
    var hasSymbol = /[^A-Za-z0-9]/.test(pwd);
    // Show/hide each li
    liUpper.style.display = hasUpper ? 'none' : '';
    liLength.style.display = hasLength ? 'none' : '';
    liNumber.style.display = hasNumber ? 'none' : '';
    liSymbol.style.display = hasSymbol ? 'none' : '';
}
document.getElementById('password').addEventListener('input', updatePasswordChecklist);

// Form validation to enable/disable sign up button
function validateForm() {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const year = document.getElementById('year').value;
    const section = document.getElementById('section_id').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const isIrregular = document.getElementById('is_irregular').checked;
    
    // Check if all required fields are filled
    const isFormValid = firstName && lastName && username && email && year && 
                       password && confirmPassword && 
                       (isIrregular || section) && // Section required if not irregular
                       password === confirmPassword &&
                       password.length >= 8;
    
    // Enable/disable sign up button
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

// Add event listeners to all form fields
document.addEventListener('DOMContentLoaded', function() {
    const formFields = [
        'first_name', 'last_name', 'username', 'email', 
        'year', 'section_id', 'password', 'confirm_password'
    ];
    
    formFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', validateForm);
            field.addEventListener('change', validateForm);
        }
    });
    
    // Add event listener for irregular checkbox
    const irregularCheckbox = document.getElementById('is_irregular');
    if (irregularCheckbox) {
        irregularCheckbox.addEventListener('change', validateForm);
    }
    
    // Add event listener for year level to filter sections
    const yearSelect = document.getElementById('year');
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            filterSectionsByYear();
            validateForm();
        });
    }
    
    // Initial validation
    validateForm();
});
</script>