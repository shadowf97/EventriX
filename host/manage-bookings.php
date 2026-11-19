<?php
// ===================================================
// EventriX - Manage Bookings & Attendee Rosters (Host)
// View attendees and ticket allocations for host-owned events
// ===================================================

$base_path = '../';
$page_title = 'Event Bookings & Attendees';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Host role only
require_role('host', $base_path);

$host_id = current_user_id();
$selected_event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Fetch all events belonging to this host for the filter dropdown
$events_stmt = $pdo->prepare("SELECT id, title, available_tickets, total_tickets, event_date FROM events WHERE host_id = ? ORDER BY event_date DESC");
$events_stmt->execute([$host_id]);
$host_events = $events_stmt->fetchAll();

// Build query for attendee bookings strictly isolated to this host's events
$query = "SELECT b.*, 
                 u.full_name AS attendee_name, u.email AS attendee_email,
                 e.title AS event_title, e.event_date, e.ticket_price, e.available_tickets, e.total_tickets
          FROM bookings b
          JOIN events e ON b.event_id = e.id
          JOIN users u ON b.user_id = u.id
          WHERE e.host_id = ?";
$params = [$host_id];

if ($selected_event_id > 0) {
    $query .= " AND e.id = ?";
    $params[] = $selected_event_id;
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendee_bookings = $stmt->fetchAll();

// Calculate total metrics for currently displayed view
$total_tickets_shown = 0;
$total_revenue_shown = 0;
foreach ($attendee_bookings as $row) {
    if ($row['booking_status'] === 'Confirmed') {
        $total_tickets_shown += (int)$row['quantity'];
        $total_revenue_shown += (float)$row['total_amount'];
    }
}

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Attendee Bookings &amp; Ticket Management</h2>
    <p>Track guest lists, ticket quantities, and payment status for your hosted events.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="manage-events.html">My Events</a> | 
        <a href="event-statistics.html">View Sales Statistics</a>
    </p>

    <hr>

    <!-- Filter by Specific Event -->
    <fieldset>
        <legend><strong>Filter Attendees by Event</strong></legend>
        <form action="manage-bookings.html" method="GET">
            <p>
                <label for="event_id"><strong>Select Hosted Event:</strong></label>
                <select id="event_id" name="event_id" onchange="this.form.submit()">
                    <option value="0">-- All My Events --</option>
                    <?php foreach ($host_events as $hev): ?>
                        <option value="<?php echo $hev['id']; ?>" <?php echo ($selected_event_id === (int)$hev['id']) ? 'selected' : ''; ?>>
                            <?php echo e($hev['title']); ?> (<?php echo format_date($hev['event_date']); ?>) - [<?php echo $hev['available_tickets']; ?> left]
                        </option>
                    <?php endforeach; ?>
                </select>
                &nbsp;
                <button type="submit">Filter</button>
                <a href="manage-bookings.html"><button type="button">Reset</button></a>
            </p>
        </form>
    </fieldset>

    <br><br>

    <!-- Summary Box -->
    <table border="1" cellpadding="6" cellspacing="0">
        <tr align="center">
            <td width="200">Total Bookings Recorded: <strong><?php echo count($attendee_bookings); ?></strong></td>
            <td width="200">Confirmed Tickets: <strong><?php echo $total_tickets_shown; ?></strong></td>
            <td width="200">Confirmed Revenue: <strong><?php echo format_price($total_revenue_shown); ?></strong></td>
        </tr>
    </table>

    <br>

    <!-- Attendees Table -->
    <section>
        <?php if (!empty($attendee_bookings)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="95%">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Attendee Name</th>
                        <th>Attendee Email</th>
                        <th>Event Title</th>
                        <th>Tickets Booked</th>
                        <th>Amount</th>
                        <th>Booking Date</th>
                        <th>Booking Status</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendee_bookings as $b): ?>
                        <tr>
                            <td><strong><?php echo e($b['booking_reference']); ?></strong></td>
                            <td><?php echo e($b['attendee_name']); ?></td>
                            <td><a href="mailto:<?php echo e($b['attendee_email']); ?>"><?php echo e($b['attendee_email']); ?></a></td>
                            <td><?php echo e($b['event_title']); ?></td>
                            <td align="center"><strong><?php echo $b['quantity']; ?></strong></td>
                            <td><?php echo format_price($b['total_amount']); ?></td>
                            <td><small><?php echo format_date($b['created_at']); ?> <?php echo date('g:i A', strtotime($b['created_at'])); ?></small></td>
                            <td align="center">
                                <?php if ($b['booking_status'] === 'Confirmed'): ?>
                                    <strong><?php echo e($b['booking_status']); ?></strong>
                                <?php else: ?>
                                    <em><?php echo e($b['booking_status']); ?></em>
                                <?php endif; ?>
                            </td>
                            <td align="center">
                                <?php if ($b['payment_status'] === 'Paid'): ?>
                                    <strong><?php echo e($b['payment_status']); ?></strong>
                                <?php else: ?>
                                    <em><?php echo e($b['payment_status']); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No bookings have been made yet for the selected criteria.</em></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
