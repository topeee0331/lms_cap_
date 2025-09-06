<?php
require_once 'config/config.php';
require_once 'config/database.php';

$error = '';
$show_captcha = false;
$captcha_question = '';

// Check if IP is locked
if (isIPLocked()) {
    $remaining_time = getRemainingLockoutTime();
    $minutes = floor($remaining_time / 60);
    $seconds = $remaining_time % 60;
    $error = "Too many failed login attempts. Please try again in {$minutes}m {$seconds}s.";
} else {
    // Generate CAPTCHA if needed
    if (isset($_SESSION['failed_attempts']) && $_SESSION['failed_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $show_captcha = true;
        // Only generate new CAPTCHA if one doesn't exist or is expired
        if (!getCurrentCaptcha()) {
            generateSimpleCaptcha();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $captcha_answer = $_POST['captcha_answer'] ?? '';

    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif ($show_captcha && empty($captcha_answer)) {
        $error = 'Please complete the CAPTCHA.';
    } elseif ($show_captcha && !validateCaptcha($captcha_answer)) {
        $error = 'Incorrect CAPTCHA answer. Please try again.';
        // Don't generate new CAPTCHA here - let the existing one be used
        // The CAPTCHA will be regenerated only when needed
    } else {
        // Check if login is throttled
        if (isLoginThrottled($email)) {
            $error = 'Too many failed login attempts for this account. Please try again later.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                recordLoginAttempt($email, true);
                clearLoginAttempts($email);
                
                // Reset failed attempts counter
                unset($_SESSION['failed_attempts']);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['profile_picture'] = $user['profile_picture'] ?? 'default.png';
                
                // Redirect to main page for all roles
                header('Location: index.php');
                exit();
            } else {
                // Failed login
                recordLoginAttempt($email, false);
                
                // Increment failed attempts counter
                if (!isset($_SESSION['failed_attempts'])) {
                    $_SESSION['failed_attempts'] = 0;
                }
                $_SESSION['failed_attempts']++;
                
                $error = 'Invalid email or password.';
                
                // Show CAPTCHA after 3 failed attempts
                if ($_SESSION['failed_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                    $show_captcha = true;
                    // Generate new CAPTCHA only if needed
                    if (!getCurrentCaptcha()) {
                        generateSimpleCaptcha();
                    }
                }
            }
        }
    }
}

// Only include header if we're not redirecting
if (!isset($_SESSION['user_id'])) {
    $page_title = 'Login';
    require_once 'includes/header.php';
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
}
.login-split-container {
    display: flex;
    min-height: 100vh;
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

/* CAPTCHA styling */
.captcha-container {
    background: #f8f9fa;
    border: 1px solid var(--accent-green);
    border-radius: 0.8rem;
    padding: 1rem;
    margin-top: 0.5rem;
}

.captcha-question {
    color: var(--main-green);
    font-size: 1.1rem;
    text-align: center;
    background: white;
    padding: 0.5rem;
    border-radius: 0.5rem;
    border: 1px solid #e9ecef;
}

.captcha-container .form-control {
    margin-top: 0.5rem;
}

.captcha-container .form-text {
    color: #6c757d;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 991px) {
    .login-split-container {
        flex-direction: column;
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
          Login
        </div>
        <div class="card-body">
          <?php if ($error): ?>
            <div class="alert alert-danger"> <?php echo $error; ?> </div>
          <?php endif; ?>
          <form method="post" autocomplete="off">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" id="email" name="email" required autofocus>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <?php if ($show_captcha): ?>
            <div class="mb-3">
              <label for="captcha_answer" class="form-label">Security Check</label>
              <div class="captcha-container">
                <div class="captcha-question mb-2">
                  <strong>What is <?php echo htmlspecialchars(getCurrentCaptcha() ?: 'Loading...'); ?>?</strong>
                </div>
                <input type="number" class="form-control" id="captcha_answer" name="captcha_answer" 
                       placeholder="Enter your answer" required>
                <small class="form-text text-muted">Please solve this simple math problem to continue.</small>
                <?php if (isset($_SESSION['captcha_data']['attempts']) && $_SESSION['captcha_data']['attempts'] > 0): ?>
                  <div class="mt-2">
                    <small class="text-warning">Attempts: <?php echo $_SESSION['captcha_data']['attempts']; ?>/3</small>
                  </div>
                <?php endif; ?>
                

              </div>
            </div>
            <?php endif; ?>
            
            <div class="d-grid mb-3">
              <button type="submit" class="btn btn-primary">Login</button>
            </div>
            <div class="text-center">
              <a href="register.php" class="register-link">Don't have an account? Register</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php 
    require_once 'includes/footer.php';
}
?> 