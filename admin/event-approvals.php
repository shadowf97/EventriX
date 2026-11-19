<?php
// ===================================================
// EventriX - Event Approvals Workflow (Admin)
// Dedicated queue for reviewing, approving, or rejecting host event submissions
// ===================================================

$base_path = '../';
$page_title = 'Event Approvals';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Admin role only
require_role('admin', $base_path);

// Handle Moderation Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid.');
    } else {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $action = $_POST['action'] ?? '';

        if ($event_id > 0) {
            $stmt = $pdo->prepare("SELECT title FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            $event_title = $stmt->fetchColumn();

            if ($action === 'approve') {
                $upd = $pdo->prepare("UPDATE events SET status = 'Approved' WHERE id = ?");
                $upd->execute([$event_id]);

                set_flash_message('success', "Event '{$event_title}' has been Approved and is now live on the public website.");
            } elseif ($action === 'reject') {
                $upd = $pdo->prepare("UPDATE events SET status = 'Rejected' WHERE id = ?");
                $upd->execute([$event_id]);

                set_flash_message('success', "Event '{$event_title}' was rejected.");
            }
        }
    }
    header("Location: event-approvals.html");
    exit();
}

// Fetch events awaiting approval
$stmt = $pdo->query("SELECT e.*, u.full_name AS host_name, u.email AS host_email 
                    FROM events e 
                    JOIN users u ON e.host_id = u.id 
                    WHERE e.status = 'Pending Approval' 
                    ORDER BY e.created_at ASC");
$pending_events = $stmt->fetchAll();

// Fetch recently rejected events
$stmt = $pdo->query("SELECT e.*, u.full_name AS host_name 
                    FROM events e 
                    JOIN users u ON e.host_id = u.id 
                    WHERE e.status = 'Rejected' 
                    ORDER BY e.updated_at DESC 
                    LIMIT 5");
$rejected_events = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Event Approvals</h2>
    <p>Review host event submissions.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="manage-events.html">Browse All Events</a>
    </p>

    <hr>

    <section>
        <h3>Pending Events (<?php echo count($pending_events); ?>)</h3>
        <?php if (!empty($pending_events)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="95%">
                <thead>
                    <tr>
                        <th>Title &amp; Category</th>
                        <th>Host Organizer</th>
                        <th>Description Preview</th>
                        <th>Date &amp; Time</th>
                        <th>Location</th>
                        <th>Price</th>
                        <th>Capacity</th>
                        <th>Deadline</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_events as $pe): ?>
                        <tr>
                            <td>
                                <strong><?php echo e($pe['title']); ?></strong><br>
                                <small>[<?php echo e($pe['category']); ?>]</small>
                            </td>
                            <td>
                                <?php echo e($pe['host_name']); ?><br>
                                <small><a href="mailto:<?php echo e($pe['host_email']); ?>"><?php echo e($pe['host_email']); ?></a></small>
                            </td>
                            <td><small><?php echo e(substr($pe['description'], 0, 100)); ?>...</small></td>
                            <td>
                                <?php echo format_date($pe['event_date']); ?><br>
                                <small><?php echo format_time($pe['start_time']); ?> - <?php echo format_time($pe['end_time']); ?></small>
                            </td>
                            <td><?php echo e($pe['location']); ?></td>
                            <td><?php echo format_price($pe['ticket_price']); ?></td>
                            <td align="center"><?php echo $pe['total_tickets']; ?></td>
                            <td><small><?php echo date('M j, Y g:i A', strtotime($pe['registration_deadline'])); ?></small></td>
                            <td align="center">
                                <a href="../event-details.html?id=<?php echo $pe['id']; ?>" target="_blank"><button type="button">Preview</button></a><br><br>
                                
                                <form action="event-approvals.html" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="event_id" value="<?php echo $pe['id']; ?>">
                                    <button type="submit"><strong>&check; Approve</strong></button>
                                </form>
                                &nbsp;
                                <form action="event-approvals.html" method="POST" onsubmit="return confirm('Are you sure you want to reject this event?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="event_id" value="<?php echo $pe['id']; ?>">
                                    <button type="submit">&cross; Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No pending event submissions at this moment.</em></p>
        <?php endif; ?>
    </section>

    <hr>

    <!-- Previously Rejected Submissions -->
    <?php if (!empty($rejected_events)): ?>
        <section>
            <h3>Recently Rejected Events</h3>
            <table border="1" cellpadding="8" cellspacing="0" width="85%">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Host</th>
                        <th>Event Date</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rejected_events as $re): ?>
                        <tr>
                            <td><?php echo e($re['title']); ?></td>
                            <td><?php echo e($re['host_name']); ?></td>
                            <td><?php echo format_date($re['event_date']); ?></td>
                            <td><?php echo e($re['location']); ?></td>
                            <td><mark><?php echo e($re['status']); ?></mark></td>
                            <td align="center">
                                <form action="event-approvals.html" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="event_id" value="<?php echo $re['id']; ?>">
                                    <button type="submit">Re-Approve</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
