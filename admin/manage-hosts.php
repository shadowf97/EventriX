<?php
// ===================================================
// EventriX - Manage Registered Hosts (Admin)
// View organizer accounts, event volumes, sales performance, and access states
// ===================================================

$base_path = '../';
$page_title = 'Manage Hosts';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Admin role only
require_role('admin', $base_path);

// Handle Host Account Suspension / Reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_host_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid.');
    } else {
        $host_id = (int)($_POST['host_id'] ?? 0);
        $new_status = sanitize_input($_POST['new_status'] ?? '');

        if (in_array($new_status, ['active', 'suspended'], true) && $host_id > 0) {
            $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ? AND role = 'host'");
            $stmt->execute([$host_id]);
            $hst = $stmt->fetch();

            if ($hst) {
                $upd = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $upd->execute([$new_status, $host_id]);

                set_flash_message('success', "Host account '{$hst['full_name']}' has been {$new_status}.");
            }
        }
    }
    header("Location: manage-hosts.html");
    exit();
}

$search = sanitize_input($_GET['search'] ?? '');
$query = "SELECT u.*, 
          COUNT(DISTINCT e.id) AS total_events,
          COALESCE(SUM(CASE WHEN b.booking_status = 'Confirmed' THEN b.quantity ELSE 0 END), 0) AS tickets_sold,
          COALESCE(SUM(CASE WHEN b.booking_status = 'Confirmed' THEN b.total_amount ELSE 0 END), 0) AS total_revenue
          FROM users u
          LEFT JOIN events e ON u.id = e.host_id
          LEFT JOIN bookings b ON e.id = b.event_id
          WHERE u.role = 'host'";
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
$hosts = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Manage Registered Event Hosts</h2>
    <p>Review event organizers, monitor publishing volume, verify commercial volume, and manage host privileges.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="manage-users.html">Manage Users</a> | 
        <a href="manage-events.html">All Platform Events</a>
    </p>

    <hr>

    <!-- Search Box -->
    <fieldset>
        <legend><strong>Find Host Account</strong></legend>
        <form action="manage-hosts.html" method="GET">
            <p>
                <label for="search"><strong>Search by Name or Email:</strong></label>
                <input type="text" id="search" name="search" value="<?php echo e($search); ?>" placeholder="e.g. Sarah, Marcus, host@example.com" size="30">
                &nbsp;
                <button type="submit">Search</button>
                <a href="manage-hosts.html"><button type="button">Reset</button></a>
            </p>
        </form>
    </fieldset>

    <br><br>

    <section>
        <p>Total Registered Hosts Found: <strong><?php echo count($hosts); ?></strong></p>

        <?php if (!empty($hosts)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="95%">
                <thead>
                    <tr>
                        <th>Host ID</th>
                        <th>Organizer Name</th>
                        <th>Contact Email</th>
                        <th>Registered Date</th>
                        <th>Events Created</th>
                        <th>Tickets Sold</th>
                        <th>Gross Revenue Generated</th>
                        <th>Host Status</th>
                        <th>Administrative Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hosts as $h): ?>
                        <tr>
                            <td><?php echo $h['id']; ?></td>
                            <td><strong><?php echo e($h['full_name']); ?></strong></td>
                            <td><a href="mailto:<?php echo e($h['email']); ?>"><?php echo e($h['email']); ?></a></td>
                            <td><?php echo format_date($h['created_at']); ?></td>
                            <td align="center"><strong><?php echo $h['total_events']; ?></strong></td>
                            <td align="center"><?php echo $h['tickets_sold']; ?></td>
                            <td><?php echo format_price($h['total_revenue']); ?></td>
                            <td align="center">
                                <?php if ($h['status'] === 'active'): ?>
                                    <strong>Active</strong>
                                <?php else: ?>
                                    <mark><strong>SUSPENDED</strong></mark>
                                <?php endif; ?>
                            </td>
                            <td align="center">
                                <form action="manage-hosts.html" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_host_status">
                                    <input type="hidden" name="host_id" value="<?php echo $h['id']; ?>">
                                    <?php if ($h['status'] === 'active'): ?>
                                        <input type="hidden" name="new_status" value="suspended">
                                        <button type="submit" onclick="return confirm('Suspend this host account? The host will not be able to log in or manage events.');">
                                            Suspend Host
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" onclick="return confirm('Reactivate this host account?');">
                                            Reactivate Host
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No hosts found matching your search.</em></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
