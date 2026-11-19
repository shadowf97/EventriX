<?php
// ===================================================
// EventriX - Manage Registered Users (Admin)
// View user accounts and manage access
// ===================================================

$base_path = '../';
$page_title = 'Manage Users';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Admin role only
require_role('admin', $base_path);

// Handle Account Suspension / Reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid.');
    } else {
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $new_status = sanitize_input($_POST['new_status'] ?? '');

        if (in_array($new_status, ['active', 'suspended'], true) && $target_user_id > 0) {
            $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ? AND role = 'user'");
            $stmt->execute([$target_user_id]);
            $usr = $stmt->fetch();

            if ($usr) {
                $upd = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $upd->execute([$new_status, $target_user_id]);

                set_flash_message('success', "User account for '{$usr['full_name']}' has been marked as {$new_status}.");
            }
        }
    }
    header("Location: manage-users.html");
    exit();
}

$search = sanitize_input($_GET['search'] ?? '');
$query = "SELECT u.*, 
          COUNT(b.id) AS total_bookings,
          COALESCE(SUM(b.total_amount), 0) AS total_spent
          FROM users u
          LEFT JOIN bookings b ON u.id = b.user_id AND b.booking_status = 'Confirmed'
          WHERE u.role = 'user'";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Manage Registered Users</h2>
    <p>View attendee accounts and manage access.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="manage-hosts.html">Manage Host Accounts</a> | 
        <a href="manage-bookings.html">All Platform Bookings</a>
    </p>

    <hr>

    <!-- Search Box -->
    <fieldset>
        <legend><strong>Find User Account</strong></legend>
        <form action="manage-users.html" method="GET">
            <p>
                <label for="search"><strong>Search by Name or Email:</strong></label>
                <input type="text" id="search" name="search" value="<?php echo e($search); ?>" placeholder="e.g. Alex, Rivera, alex@example.com" size="30">
                &nbsp;
                <button type="submit">Search</button>
                <a href="manage-users.html"><button type="button">Reset</button></a>
            </p>
        </form>
    </fieldset>

    <br><br>

    <section>
        <p>Total Registered Users Found: <strong><?php echo count($users); ?></strong></p>

        <?php if (!empty($users)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="90%">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Registered On</th>
                        <th>Total Bookings</th>
                        <th>Total Amount Spent</th>
                        <th>Account Status</th>
                        <th>Administrative Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><strong><?php echo e($u['full_name']); ?></strong></td>
                            <td><a href="mailto:<?php echo e($u['email']); ?>"><?php echo e($u['email']); ?></a></td>
                            <td><?php echo format_date($u['created_at']); ?></td>
                            <td align="center"><strong><?php echo $u['total_bookings']; ?></strong></td>
                            <td><?php echo format_price($u['total_spent']); ?></td>
                            <td align="center">
                                <?php if ($u['status'] === 'active'): ?>
                                    <strong>Active</strong>
                                <?php else: ?>
                                    <mark><strong>SUSPENDED</strong></mark>
                                <?php endif; ?>
                            </td>
                            <td align="center">
                                <form action="manage-users.html" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <?php if ($u['status'] === 'active'): ?>
                                        <input type="hidden" name="new_status" value="suspended">
                                        <button type="submit" onclick="return confirm('Suspend this user account? The user will be blocked from logging in or booking tickets.');">
                                            Suspend Account
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" onclick="return confirm('Reactivate this user account?');">
                                            Reactivate Account
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No users found matching your search.</em></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
