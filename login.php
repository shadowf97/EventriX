<?php
// ===================================================
// EventriX - Unified Authentication Page
// Sign In and Sign Up for Users and Hosts
// ===================================================

$base_path = '';
$page_title = 'Sign In / Sign Up';

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already logged in, redirect them directly to their role dashboard
if (is_logged_in()) {
    redirect_by_role(current_user_role(), $base_path);
}

$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'signup' ? 'signup' : 'signin';
$errors = [];
$success_message = '';

if (isset($_GET['status']) && $_GET['status'] === 'logged_out') {
    $success_message = 'You have been successfully logged out.';
}

// Handle POST Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submitted_token = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (!verify_csrf_token($submitted_token)) {
        $errors[] = 'Security token invalid or expired. Please refresh the page and try again.';
    } else {
        // ---------------------------------------------------
        // 1. Process Sign In
        // ---------------------------------------------------
        if ($action === 'signin') {
            $active_tab = 'signin';
            $login_name = strtolower(sanitize_input($_POST['signin_email'] ?? ''));
            $password = $_POST['signin_password'] ?? '';

            if (empty($login_name) || empty($password)) {
                $errors[] = 'Please enter your email or demo username and password.';
            } else {
                // Demo usernames keep the assessment login process quick and simple.
                // Other accounts continue to sign in with their registered email address.
                if (in_array($login_name, ['admin', 'host', 'user'], true)) {
                    $stmt = $pdo->prepare("SELECT id, full_name, email, password, role, status FROM users WHERE role = ? ORDER BY id ASC LIMIT 1");
                    $stmt->execute([$login_name]);
                } else {
                    $stmt = $pdo->prepare("SELECT id, full_name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$login_name]);
                }
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Check if account is suspended
                    if ($user['status'] === 'suspended') {
                        $errors[] = 'Your account has been suspended by the administrator. Access is disabled.';
                    } else {
                        // Successful login: regenerate session ID to prevent session fixation
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];


                        set_flash_message('success', 'Welcome back, ' . $user['full_name'] . '!');
                        redirect_by_role($user['role'], $base_path);
                    }
                } else {
                    $errors[] = 'Invalid login details. Please check your username/email and password.';
                }
            }
        }

        // ---------------------------------------------------
        // 2. Process Sign Up
        // ---------------------------------------------------
        elseif ($action === 'signup') {
            $active_tab = 'signup';
            $full_name = sanitize_input($_POST['signup_name'] ?? '');
            $email = sanitize_input($_POST['signup_email'] ?? '');
            $password = $_POST['signup_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $role = sanitize_input($_POST['signup_role'] ?? 'user');

            // Validation rules
            if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
                $errors[] = 'All registration fields are required.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please provide a valid email address.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            }
            if ($password !== $confirm_password) {
                $errors[] = 'Password confirmation does not match.';
            }
            // Strict role restriction: Public sign up only allows 'user' or 'host'
            if (!in_array($role, ['user', 'host'], true)) {
                $errors[] = 'Invalid account type selected. You can register as a User or Host.';
            }

            // Check if email already registered
            if (empty($errors)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = 'An account with this email address already exists. Please sign in instead.';
                }
            }

            // Insert new user if no errors
            if (empty($errors)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([$full_name, $email, $hashed_password, $role]);

                $new_user_id = $pdo->lastInsertId();

                // Auto login after registration
                session_regenerate_id(true);
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;

                set_flash_message('success', 'Registration successful! Welcome to EventriX, ' . $full_name . '.');
                redirect_by_role($role, $base_path);
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<center>
    <h2>EventriX Authentication</h2>
    <p>Sign in to your existing account or create a new user or host profile.</p>

    <?php if (!empty($success_message)): ?>
        <p><mark><strong>[Success]</strong> <?php echo e($success_message); ?></mark></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div>
            <?php foreach ($errors as $err): ?>
                <p><mark><strong>[Warning]</strong> <?php echo e($err); ?></mark></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Switch options -->
    <p>
        <button type="button" id="tab-btn-signin" onclick="showAuthTab('signin')"><strong>Sign In</strong></button>
        <button type="button" id="tab-btn-signup" onclick="showAuthTab('signup')"><strong>Sign Up</strong></button>
    </p>

    <!-- ---------------------------------------------------
         SIGN IN SECTION
         --------------------------------------------------- -->
    <section id="auth-signin-section">
        <fieldset>
            <legend><h3>Sign In to EventriX</h3></legend>
            <form action="login.html" method="POST" id="signin-form" onsubmit="return validateSignInForm()">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="signin">

                <p>
                    <label for="signin_email"><strong>Email Address or Demo Username:</strong></label><br>
                    <input type="text" id="signin_email" name="signin_email" required 
                           value="<?php echo isset($_POST['signin_email']) ? e($_POST['signin_email']) : ''; ?>"
                           placeholder="e.g. alex@example.com or admin" size="35">
                </p>

                <p>
                    <label for="signin_password"><strong>Password:</strong></label><br>
                    <input type="password" id="signin_password" name="signin_password" required 
                           placeholder="Enter your password" size="35">
                </p>

                <p>
                    <button type="submit"><strong>Sign In</strong></button>
                </p>
            </form>
        </fieldset>

        <p class="demo-logins"><strong>Demo logins:</strong> <code>admin / @admin</code> | <code>host / @host</code> | <code>user / @user</code></p>
    </section>

    <!-- ---------------------------------------------------
         SIGN UP SECTION
         --------------------------------------------------- -->
    <section id="auth-signup-section">
        <fieldset>
            <legend><h3>Create an EventriX Account</h3></legend>
            <form action="login.html" method="POST" id="signup-form" onsubmit="return validateSignUpForm()">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="signup">

                <p>
                    <label for="signup_name"><strong>Full Name:</strong></label><br>
                    <input type="text" id="signup_name" name="signup_name" required 
                           value="<?php echo isset($_POST['signup_name']) ? e($_POST['signup_name']) : ''; ?>"
                           placeholder="e.g. John Doe" size="35">
                </p>

                <p>
                    <label for="signup_email"><strong>Email Address:</strong></label><br>
                    <input type="email" id="signup_email" name="signup_email" required 
                           value="<?php echo isset($_POST['signup_email']) ? e($_POST['signup_email']) : ''; ?>"
                           placeholder="e.g. john@example.com" size="35">
                </p>

                <p>
                    <label for="signup_role"><strong>I want to register as:</strong></label><br>
                    <select id="signup_role" name="signup_role" required>
                        <option value="user" <?php echo (isset($_POST['signup_role']) && $_POST['signup_role'] === 'user') ? 'selected' : ''; ?>>User (Discover & Book Event Tickets)</option>
                        <option value="host" <?php echo (isset($_POST['signup_role']) && $_POST['signup_role'] === 'host') ? 'selected' : ''; ?>>Host (Create & Manage Events)</option>
                    </select>
                </p>

                <p>
                    <label for="signup_password"><strong>Password (at least 8 characters):</strong></label><br>
                    <input type="password" id="signup_password" name="signup_password" minlength="8" required 
                           placeholder="Minimum 8 characters" size="35">
                </p>

                <p>
                    <label for="confirm_password"><strong>Confirm Password:</strong></label><br>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required 
                           placeholder="Re-type password" size="35">
                </p>

                <p><mark id="password-match-error"></mark></p>

                <p>
                    <button type="submit"><strong>Create Account</strong></button>
                </p>
            </form>
        </fieldset>
    </section>
</center>

<script>
// Vanilla JavaScript tab switching and validation
function showAuthTab(tab) {
    var signinSection = document.getElementById('auth-signin-section');
    var signupSection = document.getElementById('auth-signup-section');
    var btnSignin = document.getElementById('tab-btn-signin');
    var btnSignup = document.getElementById('tab-btn-signup');

    if (tab === 'signup') {
        signinSection.hidden = true;
        signupSection.hidden = false;
        btnSignup.disabled = true;
        btnSignin.disabled = false;
    } else {
        signinSection.hidden = false;
        signupSection.hidden = true;
        btnSignin.disabled = true;
        btnSignup.disabled = false;
    }
}

function validateSignInForm() {
    var email = document.getElementById('signin_email').value.trim();
    var pass = document.getElementById('signin_password').value;
    if (email === '' || pass === '') {
        alert('Please fill in both email and password.');
        return false;
    }
    return true;
}

function validateSignUpForm() {
    var name = document.getElementById('signup_name').value.trim();
    var email = document.getElementById('signup_email').value.trim();
    var pass = document.getElementById('signup_password').value;
    var confirm = document.getElementById('confirm_password').value;
    var errBox = document.getElementById('password-match-error');

    if (name === '' || email === '' || pass === '' || confirm === '') {
        alert('Please complete all registration fields.');
        return false;
    }

    if (pass.length < 8) {
        errBox.textContent = 'Password must be at least 8 characters long.';
        return false;
    }

    if (pass !== confirm) {
        errBox.textContent = 'Passwords do not match.';
        return false;
    }

    errBox.textContent = '';
    return true;
}

// Initialize active tab based on server state
showAuthTab("<?php echo $active_tab; ?>");
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
