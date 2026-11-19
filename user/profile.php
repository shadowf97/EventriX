<?php
// ===================================================
// EventriX - User Profile Management
// Update personal details and change account password
// ===================================================

$base_path = '../';
$page_title = 'My Profile';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: User role only
require_role('user', $base_path);

$user_id = current_user_id();
$errors = [];

// Fetch latest user data
$stmt = $pdo->prepare("SELECT id, full_name, email, role, status, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash_message('error', 'User not found.');
    header("Location: ../logout.html");
    exit();
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token invalid. Please refresh the page.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Update Profile Info (Name & Email)
        if ($action === 'update_profile') {
            $new_name = sanitize_input($_POST['full_name'] ?? '');
            $new_email = sanitize_input($_POST['email'] ?? '');

            if (empty($new_name) || empty($new_email)) {
                $errors[] = 'Full name and email address are required.';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please provide a valid email address.';
            } else {
                // Check if new email is taken by another user
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $chk->execute([$new_email, $user_id]);
                if ($chk->fetch()) {
                    $errors[] = 'That email address is already in use by another account.';
                } else {
                    $upd = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    $upd->execute([$new_name, $new_email, $user_id]);

                    $_SESSION['full_name'] = $new_name;
                    $_SESSION['email'] = $new_email;
                    $user['full_name'] = $new_name;
                    $user['email'] = $new_email;

                    set_flash_message('success', 'Profile information updated successfully.');
                }
            }
        }

        // 2. Change Password
        elseif ($action === 'change_password') {
            $current_pass = $_POST['current_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';

            if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
                $errors[] = 'Please fill in all password fields.';
            } elseif (strlen($new_pass) < 8) {
                $errors[] = 'New password must be at least 8 characters long.';
            } elseif ($new_pass !== $confirm_pass) {
                $errors[] = 'New password and confirmation do not match.';
            } else {
                // Verify current password against database
                $pass_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $pass_stmt->execute([$user_id]);
                $db_hash = $pass_stmt->fetchColumn();

                if (!password_verify($current_pass, $db_hash)) {
                    $errors[] = 'Your current password was incorrect.';
                } else {
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upd->execute([$new_hash, $user_id]);

                    set_flash_message('success', 'Password changed successfully.');
                }
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Account Profile &amp; Settings</h2>
    <p>Manage your personal identification details and account password.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="my-bookings.html">My Bookings</a>
    </p>

    <?php if (!empty($errors)): ?>
        <div>
            <?php foreach ($errors as $err): ?>
                <p><mark><strong>[Warning]</strong> <?php echo e($err); ?></mark></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <hr>

    <!-- Profile Details Form -->
    <fieldset>
        <legend><h3>Edit Personal Information</h3></legend>
        <form action="profile.html" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_profile">

            <p>
                <label for="full_name"><strong>Full Name:</strong></label><br>
                <input type="text" id="full_name" name="full_name" required size="35" 
                       value="<?php echo e($user['full_name']); ?>">
            </p>

            <p>
                <label for="email"><strong>Email Address:</strong></label><br>
                <input type="email" id="email" name="email" required size="35" 
                       value="<?php echo e($user['email']); ?>">
            </p>

            <p>
                <strong>Role:</strong> <?php echo ucfirst(e($user['role'])); ?><br>
                <strong>Account Status:</strong> <?php echo ucfirst(e($user['status'])); ?><br>
                <strong>Registered Since:</strong> <?php echo format_date($user['created_at']); ?>
            </p>

            <p>
                <button type="submit"><strong>Update Profile</strong></button>
            </p>
        </form>
    </fieldset>

    <br><br>

    <!-- Change Password Form -->
    <fieldset>
        <legend><h3>Change Password</h3></legend>
        <form action="profile.html" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="change_password">

            <p>
                <label for="current_password"><strong>Current Password:</strong></label><br>
                <input type="password" id="current_password" name="current_password" required size="35">
            </p>

            <p>
                <label for="new_password"><strong>New Password (min 8 chars):</strong></label><br>
                <input type="password" id="new_password" name="new_password" minlength="8" required size="35">
            </p>

            <p>
                <label for="confirm_password"><strong>Confirm New Password:</strong></label><br>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" required size="35">
            </p>

            <p>
                <button type="submit"><strong>Save New Password</strong></button>
            </p>
        </form>
    </fieldset>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
