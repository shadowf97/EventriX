<?php
// ===================================================
// EventriX - Administrator Dashboard
// Complete system governance, core platform KPIs, and moderation queue
// ===================================================

$base_path = '../';
$page_title = 'Admin Dashboard';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Admin role only
require_role('admin', $base_path);

// Handle Quick Event Approval / Rejection from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['quick_approve', 'quick_reject'])) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid.');
    } else {
        $new_status = ($_POST['action'] === 'quick_approve') ? 'Approved' : 'Rejected';
        $upd = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
        $upd->execute([$new_status, $event_id]);

        set_flash_message('success', "Event #{$event_id} has been marked as {$new_status}.");
    }
    header("Location: dashboard.html");
    exit();
}

// 1. Total Registered Users
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_users = (int)$stmt->fetchColumn();

// 2. Total Registered Hosts
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'host'");
$total_hosts = (int)$stmt->fetchColumn();

// 3. Total Published Events
$stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'Approved'");
$total_published_events = (int)$stmt->fetchColumn();

// 4. Pending Event Approvals
$stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'Pending Approval'");
$total_pending_approvals = (int)$stmt->fetchColumn();

// 5. Total Ticket Bookings
$stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
$total_bookings = (int)$stmt->fetchColumn();

// 6. Total Platform Revenue (from successful Paid transactions)
$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Paid'");
$total_platform_revenue = (float)$stmt->fetchColumn();

// Pending Events for review queue
$stmt = $pdo->query("SELECT e.*, u.full_name AS host_name, u.email AS host_email 
                    FROM events e 
                    JOIN users u ON e.host_id = u.id 
                    WHERE e.status = 'Pending Approval' 
                    ORDER BY e.created_at ASC 
                    LIMIT 5");
$pending_events = $stmt->fetchAll();

// Recent Bookings across platform
$stmt = $pdo->query("SELECT b.*, u.full_name AS attendee_name, e.title AS event_title 
                    FROM bookings b 
                    JOIN users u ON b.user_id = u.id 
                    JOIN events e ON b.event_id = e.id 
                    ORDER BY b.created_at DESC 
                    LIMIT 5");
$recent_bookings = $stmt->fetchAll();


require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>EventriX Administration Dashboard</h2>
    <p>Manage events, accounts, bookings, and payments.</p>

    <!-- Navigation Hub -->
    <p>
        <a href="event-approvals.html"><button type="button"><strong>Pending Approvals (<?php echo $total_pending_approvals; ?>)</strong></button></a>
        <a href="manage-events.html"><button type="button">All Events (<?php echo $total_published_events; ?> Active)</button></a>
        <a href="manage-users.html"><button type="button">Manage Users (<?php echo $total_users; ?>)</button></a>
        <a href="manage-hosts.html"><button type="button">Manage Hosts (<?php echo $total_hosts; ?>)</button></a>
        <a href="manage-bookings.html"><button type="button">All Bookings (<?php echo $total_bookings; ?>)</button></a>
        <a href="manage-payments.html"><button type="button">Payments</button></a>
    </p>

    <hr>

    <!-- 6 Primary Dashboard KPI Metrics -->
    <section>
        <h3>Platform Overview</h3>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr align="center">
                <th width="150">Registered Users</th>
                <th width="150">Registered Hosts</th>
                <th width="150">Published Events</th>
                <th width="160">Pending Approvals</th>
                <th width="150">Total Bookings</th>
                <th width="180">Platform Revenue</th>
            </tr>
            <tr align="center">
                <td><h3><?php echo $total_users; ?></h3></td>
                <td><h3><?php echo $total_hosts; ?></h3></td>
                <td><h3><?php echo $total_published_events; ?></h3></td>
                <td><h3><mark><?php echo $total_pending_approvals; ?></mark></h3></td>
                <td><h3><?php echo $total_bookings; ?></h3></td>
                <td><h3><mark><?php echo format_price($total_platform_revenue); ?></mark></h3></td>
            </tr>
        </table>
    </section>

    <hr>

    <!-- Priority Moderation Queue (Pending Event Approvals) -->
    <section>
        <h3>Pending Events (<?php echo count($pending_events); ?>)</h3>
        <?php if (!empty($pending_events)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="95%">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Host</th>
                        <th>Event Date</th>
                        <th>Location</th>
                        <th>Capacity</th>
                        <th>Price</th>
                        <th>Submitted On</th>
                        <th>Moderation Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_events as $pev): ?>
                        <tr>
                            <td><strong><a href="../event-details.html?id=<?php echo $pev['id']; ?>"><?php echo e($pev['title']); ?></a></strong></td>
                            <td><?php echo e($pev['category']); ?></td>
                            <td><?php echo e($pev['host_name']); ?><br><small>(<?php echo e($pev['host_email']); ?>)</small></td>
                            <td><?php echo format_date($pev['event_date']); ?></td>
                            <td><?php echo e($pev['location']); ?></td>
                            <td align="center"><?php echo $pev['total_tickets']; ?></td>
                            <td><?php echo format_price($pev['ticket_price']); ?></td>
                            <td><small><?php echo format_date($pev['created_at']); ?></small></td>
                            <td align="center">
                                <form action="dashboard.html" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="quick_approve">
                                    <input type="hidden" name="event_id" value="<?php echo $pev['id']; ?>">
                                    <button type="submit"><strong>Approve</strong></button>
                                </form>
                                <form action="dashboard.html" method="POST" onsubmit="return confirm('Reject this event?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="quick_reject">
                                    <input type="hidden" name="event_id" value="<?php echo $pev['id']; ?>">
                                    <button type="submit">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="event-approvals.html">View complete approval queue &rarr;</a></p>
        <?php else: ?>
            <p><em>No events need approval.</em></p>
        <?php endif; ?>
    </section>

    <hr>

    <section>
                <h3>Recent Bookings</h3>
                <?php if (!empty($recent_bookings)): ?>
                    <table border="1" cellpadding="6" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Attendee</th>
                                <th>Event</th>
                                <th>Tickets</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $rb): ?>
                                <tr>
                                    <td><strong><?php echo e($rb['booking_reference']); ?></strong></td>
                                    <td><?php echo e($rb['attendee_name']); ?></td>
                                    <td><?php echo e($rb['event_title']); ?></td>
                                    <td align="center"><?php echo $rb['quantity']; ?></td>
                                    <td><?php echo format_price($rb['total_amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><a href="manage-bookings.html">View All Bookings</a></p>
                <?php else: ?>
                    <p><em>No bookings recorded yet.</em></p>
                <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
