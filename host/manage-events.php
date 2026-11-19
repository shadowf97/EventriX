<?php
// ===================================================
// EventriX - Manage Events (Host)
// View, filter, edit, cancel, and delete events owned by this host
// ===================================================

$base_path = '../';
$page_title = 'Manage Events';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Host role only
require_role('host', $base_path);

$host_id = current_user_id();

// Handle Event Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event') {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid.');
    } else {
        // Strict ownership check: ensure event belongs to this host
        $chk = $pdo->prepare("SELECT id, title, total_tickets, available_tickets, image FROM events WHERE id = ? AND host_id = ?");
        $chk->execute([$event_id, $host_id]);
        $ev = $chk->fetch();

        if (!$ev) {
            set_flash_message('error', 'Event not found or permission denied.');
        } else {
            // Check if any tickets have been booked
            $book_chk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE event_id = ? AND booking_status = 'Confirmed'");
            $book_chk->execute([$event_id]);
            $confirmed_bookings = (int)$book_chk->fetchColumn();

            if ($confirmed_bookings > 0) {
                set_flash_message('error', "Cannot delete '{$ev['title']}' because {$confirmed_bookings} confirmed ticket booking(s) exist. You may cancel the event instead.");
            } else {
                // Delete cover image from disk if exists
                if (!empty($ev['image']) && file_exists(__DIR__ . '/../' . $ev['image'])) {
                    unlink(__DIR__ . '/../' . $ev['image']);
                }

                $del = $pdo->prepare("DELETE FROM events WHERE id = ? AND host_id = ?");
                $del->execute([$event_id, $host_id]);

                set_flash_message('success', "Event '{$ev['title']}' has been deleted.");
            }
        }
    }
    header("Location: manage-events.html");
    exit();
}

// Handle Event Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_event') {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid.');
    } else {
        $upd = $pdo->prepare("UPDATE events SET status = 'Cancelled' WHERE id = ? AND host_id = ?");
        $upd->execute([$event_id, $host_id]);

        set_flash_message('success', "Event marked as Cancelled.");
    }
    header("Location: manage-events.html");
    exit();
}

// Filter by status if requested
$filter_status = sanitize_input($_GET['status'] ?? '');
$query = "SELECT e.*, 
          (e.total_tickets - e.available_tickets) AS tickets_sold,
          COALESCE((SELECT SUM(total_amount) FROM bookings WHERE event_id = e.id AND booking_status = 'Confirmed'), 0) AS total_sales
          FROM events e 
          WHERE e.host_id = ?";
$params = [$host_id];

if (!empty($filter_status)) {
    $query .= " AND e.status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY e.event_date DESC, e.start_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>My Managed Events</h2>
    <p>Monitor your active, pending, draft, and completed events.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="create-event.html"><strong>+ Create New Event</strong></a>
    </p>

    <!-- Status Filters -->
    <p>
        Filter: 
        <a href="manage-events.html">[All]</a> &nbsp;
        <a href="manage-events.html?status=Approved">[Approved]</a> &nbsp;
        <a href="manage-events.html?status=Pending+Approval">[Pending Approval]</a> &nbsp;
        <a href="manage-events.html?status=Draft">[Drafts]</a> &nbsp;
        <a href="manage-events.html?status=Completed">[Completed]</a> &nbsp;
        <a href="manage-events.html?status=Cancelled">[Cancelled]</a>
    </p>

    <hr>

    <section>
        <?php if (!empty($events)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="95%">
                <thead>
                    <tr>
                        <th>Title &amp; Category</th>
                        <th>Event Date &amp; Time</th>
                        <th>Location</th>
                        <th>Price</th>
                        <th>Sold / Total</th>
                        <th>Gross Revenue</th>
                        <th>Status</th>
                        <th>Management Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $ev): ?>
                        <tr>
                            <td>
                                <strong><a href="../event-details.html?id=<?php echo $ev['id']; ?>"><?php echo e($ev['title']); ?></a></strong><br>
                                <small>[<?php echo e($ev['category']); ?>]</small>
                            </td>
                            <td>
                                <?php echo format_date($ev['event_date']); ?><br>
                                <small><?php echo format_time($ev['start_time']); ?> - <?php echo format_time($ev['end_time']); ?></small>
                            </td>
                            <td><?php echo e($ev['location']); ?></td>
                            <td><?php echo format_price($ev['ticket_price']); ?></td>
                            <td align="center">
                                <strong><?php echo $ev['tickets_sold']; ?></strong> / <?php echo $ev['total_tickets']; ?><br>
                                <small>(<?php echo $ev['available_tickets']; ?> left)</small>
                            </td>
                            <td align="center"><strong><?php echo format_price($ev['total_sales']); ?></strong></td>
                            <td align="center">
                                <?php if ($ev['status'] === 'Approved'): ?>
                                    <strong><?php echo e($ev['status']); ?></strong>
                                <?php elseif ($ev['status'] === 'Pending Approval'): ?>
                                    <mark>Pending Approval</mark>
                                <?php elseif ($ev['status'] === 'Cancelled'): ?>
                                    <em>Cancelled</em>
                                <?php else: ?>
                                    <?php echo e($ev['status']); ?>
                                <?php endif; ?>
                            </td>
                            <td align="center">
                                <a href="edit-event.html?id=<?php echo $ev['id']; ?>"><button type="button">Edit</button></a>
                                <a href="manage-bookings.html?event_id=<?php echo $ev['id']; ?>"><button type="button">Attendees</button></a>
                                
                                <?php if ($ev['status'] !== 'Cancelled'): ?>
                                    <form action="manage-events.html" method="POST" onsubmit="return confirm('Cancel this event?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="cancel_event">
                                        <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                        <button type="submit">Cancel</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($ev['tickets_sold'] == 0): ?>
                                    <form action="manage-events.html" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this event?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No events found for your account under this filter.</em></p>
            <p><a href="create-event.html">Create a new event now &rarr;</a></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
