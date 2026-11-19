<?php
// ===================================================
// EventriX - Platform Event Management (Admin)
// View, filter, edit, and moderate all platform events
// ===================================================

$base_path = '../';
$page_title = 'Manage All Events';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Admin role only
require_role('admin', $base_path);

// Handle Admin Event Deletion / Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_delete_event') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid.');
    } else {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT title, image FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $ev = $stmt->fetch();

        if ($ev) {
            // Delete image file if exists
            if (!empty($ev['image']) && file_exists(__DIR__ . '/../' . $ev['image'])) {
                unlink(__DIR__ . '/../' . $ev['image']);
            }

            $del = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $del->execute([$event_id]);

            set_flash_message('success', "Event '{$ev['title']}' has been permanently removed.");
        }
    }
    header("Location: manage-events.html");
    exit();
}

// Handle Admin Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_change_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid.');
    } else {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $new_status = sanitize_input($_POST['new_status'] ?? '');
        $allowed = ['Draft', 'Pending Approval', 'Approved', 'Rejected', 'Completed', 'Cancelled'];

        if (in_array($new_status, $allowed, true) && $event_id > 0) {
            $upd = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
            $upd->execute([$new_status, $event_id]);

            set_flash_message('success', "Event #{$event_id} status updated to '{$new_status}'.");
        }
    }
    header("Location: manage-events.html");
    exit();
}

// Filter and Search parameters
$search = sanitize_input($_GET['search'] ?? '');
$status_filter = sanitize_input($_GET['status'] ?? '');
$category_filter = sanitize_input($_GET['category'] ?? '');

$query = "SELECT e.*, u.full_name AS host_name, u.email AS host_email,
          (e.total_tickets - e.available_tickets) AS tickets_sold,
          COALESCE((SELECT SUM(total_amount) FROM bookings WHERE event_id = e.id AND booking_status = 'Confirmed'), 0) AS total_revenue
          FROM events e
          JOIN users u ON e.host_id = u.id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (e.title LIKE ? OR u.full_name LIKE ? OR e.location LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($status_filter)) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $query .= " AND e.category = ?";
    $params[] = $category_filter;
}

$query .= " ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_events = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Platform Event Directory &amp; Moderation</h2>
    <p>Monitor, moderate, reclassify, or remove any event listed across the EventriX network.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="event-approvals.html">Event Approvals Queue</a>
    </p>

    <hr>

    <!-- Search & Filter Controls -->
    <fieldset>
        <legend><strong>Filter Events</strong></legend>
        <form action="manage-events.html" method="GET">
            <p>
                <label for="search"><strong>Search:</strong></label>
                <input type="text" id="search" name="search" value="<?php echo e($search); ?>" placeholder="Title, host, or city..." size="25">
                &nbsp;

                <label for="status"><strong>Status:</strong></label>
                <select id="status" name="status">
                    <option value="">-- All Statuses --</option>
                    <?php
                    $statuses = ['Approved', 'Pending Approval', 'Draft', 'Rejected', 'Completed', 'Cancelled'];
                    foreach ($statuses as $st) {
                        $sel = ($status_filter === $st) ? 'selected' : '';
                        echo "<option value='" . e($st) . "' {$sel}>" . e($st) . "</option>";
                    }
                    ?>
                </select>
                &nbsp;

                <button type="submit">Filter</button>
                <a href="manage-events.html"><button type="button">Reset</button></a>
            </p>
        </form>
    </fieldset>

    <br><br>

    <section>
        <p>Found <strong><?php echo count($all_events); ?></strong> event record(s).</p>
        <?php if (!empty($all_events)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="98%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Event Title &amp; Category</th>
                        <th>Host</th>
                        <th>Date &amp; Location</th>
                        <th>Price</th>
                        <th>Sold / Total</th>
                        <th>Revenue</th>
                        <th>Current Status</th>
                        <th>Admin Status Override</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_events as $ev): ?>
                        <tr>
                            <td><?php echo $ev['id']; ?></td>
                            <td>
                                <strong><a href="../event-details.html?id=<?php echo $ev['id']; ?>"><?php echo e($ev['title']); ?></a></strong><br>
                                <small>[<?php echo e($ev['category']); ?>]</small>
                            </td>
                            <td>
                                <?php echo e($ev['host_name']); ?><br>
                                <small><?php echo e($ev['host_email']); ?></small>
                            </td>
                            <td>
                                <?php echo format_date($ev['event_date']); ?><br>
                                <small><?php echo e($ev['location']); ?></small>
                            </td>
                            <td><?php echo format_price($ev['ticket_price']); ?></td>
                            <td align="center"><?php echo $ev['tickets_sold']; ?> / <?php echo $ev['total_tickets']; ?></td>
                            <td><?php echo format_price($ev['total_revenue']); ?></td>
                            <td align="center">
                                <?php if ($ev['status'] === 'Approved'): ?>
                                    <strong><?php echo e($ev['status']); ?></strong>
                                <?php elseif ($ev['status'] === 'Pending Approval'): ?>
                                    <mark>Pending Approval</mark>
                                <?php else: ?>
                                    <em><?php echo e($ev['status']); ?></em>
                                <?php endif; ?>
                            </td>
                            <td align="center">
                                <form action="manage-events.html" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="admin_change_status">
                                    <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                    <select name="new_status" onchange="this.form.submit()">
                                        <?php foreach ($statuses as $s_opt): ?>
                                            <option value="<?php echo $s_opt; ?>" <?php echo ($ev['status'] === $s_opt) ? 'selected' : ''; ?>>
                                                <?php echo $s_opt; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td align="center">
                                <a href="manage-bookings.html?event_id=<?php echo $ev['id']; ?>"><button type="button">Bookings</button></a>
                                
                                <form action="manage-events.html" method="POST" onsubmit="return confirm('Are you sure you want to permanently remove this event from the platform?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="admin_delete_event">
                                    <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                    <button type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No events found matching your criteria.</em></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
