<?php
// ===================================================
// EventriX - Host Dashboard
// Event organizer overview, key metrics, and upcoming events
// ===================================================

$base_path = '../';
$page_title = 'Host Dashboard';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Host role only
require_role('host', $base_path);

$host_id = current_user_id();

// 1. Host Events Counts
$stmt = $pdo->prepare("SELECT 
    COUNT(*) AS total_events,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS published_events,
    SUM(CASE WHEN status = 'Pending Approval' THEN 1 ELSE 0 END) AS pending_events,
    SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) AS draft_events
    FROM events WHERE host_id = ?");
$stmt->execute([$host_id]);
$event_counts = $stmt->fetch();

// 2. Total Tickets Sold & Total Revenue from Confirmed Bookings
$stmt = $pdo->prepare("SELECT 
    COALESCE(SUM(b.quantity), 0) AS total_tickets_sold,
    COALESCE(SUM(b.total_amount), 0) AS total_revenue
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    WHERE e.host_id = ? AND b.booking_status = 'Confirmed'");
$stmt->execute([$host_id]);
$sales_stats = $stmt->fetch();

// 3. Upcoming Events Hosted by this User
$stmt = $pdo->prepare("SELECT * FROM events 
                      WHERE host_id = ? AND event_date >= CURDATE() 
                      ORDER BY event_date ASC, start_time ASC 
                      LIMIT 5");
$stmt->execute([$host_id]);
$upcoming_events = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Host Dashboard, <?php echo e(current_user_name()); ?></h2>
    <p>Create events, manage bookings, and track sales.</p>

    <!-- Quick Navigation -->
    <p>
        <a href="create-event.html"><button type="button"><strong>+ Create New Event</strong></button></a>
        <a href="manage-events.html"><button type="button">Manage All Events (<?php echo (int)$event_counts['total_events']; ?>)</button></a>
        <a href="manage-bookings.html"><button type="button">Attendee Bookings</button></a>
        <a href="event-statistics.html"><button type="button">Sales Statistics</button></a>
        <a href="profile.html"><button type="button">Host Profile</button></a>
    </p>

    <hr>

    <!-- Dashboard Metrics (6 Key Requirements) -->
    <section>
        <h3>Host Operations Overview</h3>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr align="center">
                <th width="150">Total Events</th>
                <th width="150">Published / Approved</th>
                <th width="150">Pending Approval</th>
                <th width="150">Tickets Sold</th>
                <th width="160">Total Revenue</th>
            </tr>
            <tr align="center">
                <td><h3><?php echo (int)$event_counts['total_events']; ?></h3></td>
                <td><h3><?php echo (int)$event_counts['published_events']; ?></h3></td>
                <td><h3><?php echo (int)$event_counts['pending_events']; ?></h3></td>
                <td><h3><?php echo (int)$sales_stats['total_tickets_sold']; ?></h3></td>
                <td><h3><?php echo format_price($sales_stats['total_revenue']); ?></h3></td>
            </tr>
        </table>
    </section>

    <hr>

    <!-- Upcoming Events Section -->
    <section>
        <h3>Your Upcoming Events</h3>
        <?php if (!empty($upcoming_events)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="90%">
                <thead>
                    <tr>
                        <th>Event Title</th>
                        <th>Category</th>
                        <th>Event Date &amp; Time</th>
                        <th>Location</th>
                        <th>Ticket Price</th>
                        <th>Tickets Left</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_events as $ev): ?>
                        <tr>
                            <td><strong><?php echo e($ev['title']); ?></strong></td>
                            <td><?php echo e($ev['category']); ?></td>
                            <td>
                                <?php echo format_date($ev['event_date']); ?><br>
                                <small><?php echo format_time($ev['start_time']); ?> - <?php echo format_time($ev['end_time']); ?></small>
                            </td>
                            <td><?php echo e($ev['location']); ?></td>
                            <td><?php echo format_price($ev['ticket_price']); ?></td>
                            <td align="center"><?php echo $ev['available_tickets']; ?> / <?php echo $ev['total_tickets']; ?></td>
                            <td align="center">
                                <?php if ($ev['status'] === 'Approved'): ?>
                                    <strong><?php echo e($ev['status']); ?></strong>
                                <?php elseif ($ev['status'] === 'Pending Approval'): ?>
                                    <mark>Pending Admin Approval</mark>
                                <?php else: ?>
                                    <em><?php echo e($ev['status']); ?></em>
                                <?php endif; ?>
                            </td>
                            <td align="center">
                                <a href="edit-event.html?id=<?php echo $ev['id']; ?>"><button type="button">Edit</button></a>
                                <a href="manage-bookings.html?event_id=<?php echo $ev['id']; ?>"><button type="button">Attendees</button></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="manage-events.html">View and manage all your events &rarr;</a></p>
        <?php else: ?>
            <p><em>You do not have any upcoming events scheduled.</em></p>
            <p><a href="create-event.html">+ Click here to create your first event!</a></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
