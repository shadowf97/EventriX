<?php
// ===================================================
// EventriX - User Dashboard
// Attendee overview, upcoming & past bookings, and account summary
// ===================================================

$base_path = '../';
$page_title = 'User Dashboard';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: User role only
require_role('user', $base_path);

$user_id = current_user_id();

// 1. Fetch User Information
$stmt = $pdo->prepare("SELECT id, full_name, email, role, status, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// 2. Total Bookings Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_bookings = (int)$stmt->fetchColumn();

// 3. Total Tickets Purchased & Total Spent
$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) AS total_tickets, COALESCE(SUM(total_amount), 0) AS total_spent 
                      FROM bookings 
                      WHERE user_id = ? AND booking_status = 'Confirmed'");
$stmt->execute([$user_id]);
$booking_stats = $stmt->fetch();

// 4. Upcoming Bookings (event_date >= today)
$stmt = $pdo->prepare("SELECT b.*, e.title, e.event_date, e.start_time, e.end_time, e.location 
                      FROM bookings b 
                      JOIN events e ON b.event_id = e.id 
                      WHERE b.user_id = ? AND e.event_date >= CURDATE() 
                      ORDER BY e.event_date ASC, e.start_time ASC");
$stmt->execute([$user_id]);
$upcoming_bookings = $stmt->fetchAll();

// 5. Past Bookings (event_date < today)
$stmt = $pdo->prepare("SELECT b.*, e.title, e.event_date, e.start_time, e.end_time, e.location 
                      FROM bookings b 
                      JOIN events e ON b.event_id = e.id 
                      WHERE b.user_id = ? AND e.event_date < CURDATE() 
                      ORDER BY e.event_date DESC 
                      LIMIT 5");
$stmt->execute([$user_id]);
$past_bookings = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>My Dashboard, <?php echo e($user['full_name']); ?></h2>
    <p>View tickets, find events, and update your profile.</p>

    <!-- Quick Navigation -->
    <p>
        <a href="../events.html"><button type="button"><strong>Browse & Book Events</strong></button></a>
        <a href="my-bookings.html"><button type="button">All My Bookings (<?php echo $total_bookings; ?>)</button></a>
        <a href="profile.html"><button type="button">My Account Profile</button></a>
    </p>

    <hr>

    <!-- Dashboard Statistics Overview -->
    <section>
        <h3>My Summary</h3>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th width="180">Total Bookings</th>
                <th width="180">Tickets Owned</th>
                <th width="180">Total Amount Spent</th>
                <th width="180">Account Status</th>
            </tr>
            <tr align="center">
                <td><h3><?php echo $total_bookings; ?></h3></td>
                <td><h3><?php echo $booking_stats['total_tickets']; ?></h3></td>
                <td><h3><?php echo format_price($booking_stats['total_spent']); ?></h3></td>
                <td><h3><mark><?php echo strtoupper(e($user['status'])); ?></mark></h3></td>
            </tr>
        </table>
    </section>

    <hr>

    <!-- Upcoming Events Booked -->
    <section>
        <h3>Upcoming Events You Are Attending</h3>
        <?php if (!empty($upcoming_bookings)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="90%">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Event Title</th>
                        <th>Event Date & Time</th>
                        <th>Location</th>
                        <th>Tickets</th>
                        <th>Total Paid</th>
                        <th>Booking Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_bookings as $booking): ?>
                        <tr>
                            <td><strong><?php echo e($booking['booking_reference']); ?></strong></td>
                            <td><a href="../event-details.html?id=<?php echo $booking['event_id']; ?>"><?php echo e($booking['title']); ?></a></td>
                            <td>
                                <?php echo format_date($booking['event_date']); ?><br>
                                <small><?php echo format_time($booking['start_time']); ?> - <?php echo format_time($booking['end_time']); ?></small>
                            </td>
                            <td><?php echo e($booking['location']); ?></td>
                            <td align="center"><?php echo $booking['quantity']; ?></td>
                            <td><?php echo format_price($booking['total_amount']); ?></td>
                            <td align="center">
                                <strong><?php echo e($booking['booking_status']); ?></strong><br>
                                <small>(Payment: <?php echo e($booking['payment_status']); ?>)</small>
                            </td>
                            <td align="center">
                                <a href="booking-details.html?id=<?php echo $booking['id']; ?>">
                                    <button type="button">View Ticket Receipt</button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>You do not have any upcoming event bookings right now.</em></p>
            <p><a href="../events.html">Browse and discover upcoming events today &rarr;</a></p>
        <?php endif; ?>
    </section>

    <hr>

    <!-- Previous / Past Bookings -->
    <section>
        <h3>Past Event Bookings</h3>
        <?php if (!empty($past_bookings)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="90%">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Event Title</th>
                        <th>Date</th>
                        <th>Tickets</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($past_bookings as $p_booking): ?>
                        <tr>
                            <td><?php echo e($p_booking['booking_reference']); ?></td>
                            <td><?php echo e($p_booking['title']); ?></td>
                            <td><?php echo format_date($p_booking['event_date']); ?></td>
                            <td align="center"><?php echo $p_booking['quantity']; ?></td>
                            <td><?php echo format_price($p_booking['total_amount']); ?></td>
                            <td align="center"><?php echo e($p_booking['booking_status']); ?></td>
                            <td align="center">
                                <a href="booking-details.html?id=<?php echo $p_booking['id']; ?>">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="my-bookings.html">View complete booking history &rarr;</a></p>
        <?php else: ?>
            <p><em>No previous past bookings recorded.</em></p>
        <?php endif; ?>
    </section>

    <hr>

    <!-- Basic Account Information -->
    <section>
        <h3>Account Overview</h3>
        <table border="1" cellpadding="6" cellspacing="0">
            <tr>
                <td><strong>Full Name:</strong></td>
                <td><?php echo e($user['full_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Registered Email:</strong></td>
                <td><?php echo e($user['email']); ?></td>
            </tr>
            <tr>
                <td><strong>Role Type:</strong></td>
                <td><?php echo ucfirst(e($user['role'])); ?></td>
            </tr>
            <tr>
                <td><strong>Member Since:</strong></td>
                <td><?php echo format_date($user['created_at']); ?></td>
            </tr>
        </table>
        <p><a href="profile.html">Edit Profile / Change Password &rarr;</a></p>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
