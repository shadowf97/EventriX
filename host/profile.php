<?php
// ===================================================
// EventriX - Host Profile Management
// Update organizer contact information and security credentials
// ===================================================

$base_path = '../';
$page_title = 'Host Profile';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Host role only
require_role('host', $base_path);

$host_id = current_user_id();
$errors = [];

// Fetch latest host profile data
$stmt = $pdo->prepare("SELECT id, full_name, email, role, status, created_at FROM users WHERE id = ?");
$stmt->execute([$host_id]);
$host = $stmt->fetch();

if (!$host) {
    set_flash_message('error', 'Host account record not found.');
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

        // 1. Update Profile (Name & Email)
        if ($action === 'update_profile') {
            $new_name = sanitize_input($_POST['full_name'] ?? '');
            $new_email = sanitize_input($_POST['email'] ?? '');

            if (empty($new_name) || empty($new_email)) {
                $errors[] = 'Host full name and email are required.';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please provide a valid email address.';
            } else {
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $chk->execute([$new_email, $host_id]);
                if ($chk->fetch()) {
                    $errors[] = 'That email is already registered to another account.';
                } else {
                    $upd = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    $upd->execute([$new_name, $new_email, $host_id]);

                    $_SESSION['full_name'] = $new_name;
                    $_SESSION['email'] = $new_email;
                    $host['full_name'] = $new_name;
                    $host['email'] = $new_email;

                    set_flash_message('success', 'Host profile details updated successfully.');
                }
            }
        }

        // 2. Change Password
        elseif ($action === 'change_password') {
            $current_pass = $_POST['current_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';

            if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
                $errors[] = 'Please fill out all password fields.';
            } elseif (strlen($new_pass) < 8) {
                $errors[] = 'New password must be at least 8 characters long.';
            } elseif ($new_pass !== $confirm_pass) {
                $errors[] = 'New password confirmation does not match.';
            } else {
                $pass_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $pass_stmt->execute([$host_id]);
                $db_hash = $pass_stmt->fetchColumn();

                if (!password_verify($current_pass, $db_hash)) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upd->execute([$new_hash, $host_id]);

                    set_flash_message('success', 'Host password updated successfully.');
                }
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Host Profile Settings</h2>
    <p>Manage your organizer contact details and account security.</p>

    <p><a href="dashboard.html">&larr; Return to Dashboard</a> | <a href="manage-events.html">My Events</a></p>

    <?php if (!empty($errors)): ?>
        <div>
            <?php foreach ($errors as $err): ?>
                <p><mark><strong>[Error]</strong> <?php echo e($err); ?></mark></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <hr>

    <!-- Host Information Form -->
    <fieldset>
        <legend><h3>Organizer Details</h3></legend>
        <form action="profile.html" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_profile">

            <p>
                <label for="full_name"><strong>Host / Company Name:</strong></label><br>
                <input type="text" id="full_name" name="full_name" required size="35"
                       value="<?php echo e($host['full_name']); ?>">
            </p>

            <p>
                <label for="email"><strong>Official Contact Email:</strong></label><br>
                <input type="email" id="email" name="email" required size="35"
                       value="<?php echo e($host['email']); ?>">
            </p>

            <p>
                <strong>Role:</strong> <?php echo ucfirst(e($host['role'])); ?><br>
                <strong>Status:</strong> <?php echo ucfirst(e($host['status'])); ?><br>
                <strong>Joined Date:</strong> <?php echo format_date($host['created_at']); ?>
            </p>

            <p>
                <button type="submit"><strong>Update Host Details</strong></button>
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
                <label for="new_password"><strong>New Password (at least 8 chars):</strong></label><br>
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
